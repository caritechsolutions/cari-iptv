#!/usr/bin/env bash
# audit_pixfmt.sh — read-only. Lists every packaged rendition that is not 8-bit yuv420p
# so it can be re-packaged (phone hardware decoders reject High 10 / Main 10 output).
#
# Usage:  sudo bash audit_pixfmt.sh [library_path]
#   library_path defaults to library_path in /etc/vod-server/vod-server.conf
#   FFPROBE=/path/to/ffprobe overrides the probe binary (default: ffprobe_path from the conf, else ffprobe)
#
# Checks the rendition MP4s the packager copies from (360p.mp4, 720p.mp4 ...). The HLS
# step is `-c copy`, so they are exactly what players receive. Falls back to the
# stream_*.m3u8 variant playlists when a title has no MP4s.
#
# ffprobe output is parsed as key=value lines (-of default=noprint_wrappers=1); ffprobe
# prints -show_entries fields in its own fixed order, never in the requested order, so
# positional parsing must not be used.
set -uo pipefail

CONF="${CONF:-/etc/vod-server/vod-server.conf}"
cfg() { awk -F= -v k="$1" '$1 ~ "^[ \t]*"k"[ \t]*$" {gsub(/^[ \t]+|[ \t]+$/,"",$2); print $2}' "$CONF" 2>/dev/null | head -1; }

LIB="${1:-$(cfg library_path)}"; LIB="${LIB:-/var/lib/vod-server/library}"
FFPROBE="${FFPROBE:-$(cfg ffprobe_path)}"; FFPROBE="${FFPROBE:-ffprobe}"

if [ ! -d "$LIB" ]; then echo "library not found: $LIB" >&2; exit 2; fi
if ! command -v "$FFPROBE" >/dev/null 2>&1 && [ ! -x "$FFPROBE" ]; then echo "ffprobe not found: $FFPROBE" >&2; exit 2; fi

# Prints "codec|profile|pix_fmt|level" for the first video stream of a file.
probe() {
  local codec="" profile="" pix="" level="" k v
  while IFS='=' read -r k v; do
    case "$k" in
      codec_name) codec="$v" ;;
      profile)    profile="$v" ;;
      pix_fmt)    pix="$v" ;;
      level)      level="$v" ;;
    esac
  done < <("$FFPROBE" -v error -select_streams v:0 \
             -show_entries stream=codec_name,profile,pix_fmt,level \
             -of default=noprint_wrappers=1 "$1" 2>/dev/null)
  printf '%s|%s|%s|%s\n' "${codec:-probe-failed}" "$profile" "$pix" "$level"
}

# OK only for 8-bit 4:2:0. Anything else (10/12-bit, 4:2:2, 4:4:4, High 10, Main 10,
# unreadable) needs re-packaging.
verdict() {
  local codec="$1" profile="$2" pix="$3"
  [ "$codec" = "probe-failed" ] && { echo "UNREADABLE"; return; }
  if [ "$pix" != "yuv420p" ]; then echo "REPACKAGE"; return; fi
  case "$profile" in *10*|*12*|*4:2:2*|*4:4:4*) echo "REPACKAGE"; return ;; esac
  echo "OK"
}

printf '%-36s %-9s %-5s %-13s %-14s %-5s %s\n' TITLE RENDITION CODEC PIX_FMT PROFILE LEVEL VERDICT
total=0; bad=0; badtitles=()
for dir in "$LIB"/*/; do
  [ -d "$dir" ] || continue
  title=$(basename "$dir")
  files=()
  while IFS= read -r f; do files+=("$f"); done < <(find "$dir" -maxdepth 1 -name '*.mp4' ! -name 'source.*' | sort)
  if [ ${#files[@]} -eq 0 ]; then
    while IFS= read -r f; do files+=("$f"); done < <(find "$dir" -maxdepth 1 -name 'stream_*.m3u8' | sort)
  fi
  titlebad=0
  for f in "${files[@]}"; do
    total=$((total+1))
    IFS='|' read -r codec profile pix level <<<"$(probe "$f")"
    v=$(verdict "$codec" "$profile" "$pix")
    if [ "$v" != "OK" ]; then bad=$((bad+1)); titlebad=1; fi
    rendition=$(basename "$f" | sed -E 's/^stream_//; s/\.(mp4|m3u8)$//')
    printf '%-36s %-9s %-5s %-13s %-14s %-5s %s\n' "$title" "$rendition" "$codec" "${pix:--}" "${profile:--}" "${level:--}" "$v"
  done
  [ $titlebad = 1 ] && badtitles+=("$title")
done

echo
echo "renditions checked: $total   needing re-package: $bad"
if [ ${#badtitles[@]} -gt 0 ]; then
  echo "titles to re-package:"
  printf '  %s\n' "${badtitles[@]}"
fi
