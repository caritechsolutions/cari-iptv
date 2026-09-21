#!/usr/bin/env bash
# Tests audit_pixfmt.sh against sample ffprobe output using a fake ffprobe.
# The fake prints fields in ffprobe's real, fixed order (codec_name, profile,
# pix_fmt, level), which is NOT the order the script requests.
set -euo pipefail
HERE=$(cd "$(dirname "$0")" && pwd)
TMP=$(mktemp -d); trap 'rm -rf "$TMP"' EXIT

mkdir -p "$TMP/lib/movie-8bit" "$TMP/lib/series-9-s1e3" "$TMP/lib/hevc-main10" "$TMP/lib/playlist-only"
touch "$TMP/lib/movie-8bit/1080p.mp4" "$TMP/lib/movie-8bit/720p.mp4" "$TMP/lib/movie-8bit/source.mp4"
touch "$TMP/lib/series-9-s1e3/1080p.mp4"
touch "$TMP/lib/hevc-main10/1080p.mp4"
touch "$TMP/lib/playlist-only/stream_720p.m3u8"

cat > "$TMP/ffprobe" <<'FAKE'
#!/usr/bin/env bash
# args: -v error -select_streams v:0 -show_entries stream=... -of default=noprint_wrappers=1 FILE
file="${@: -1}"
case "$file" in
  *movie-8bit*)     printf 'codec_name=h264\nprofile=High\npix_fmt=yuv420p\nlevel=40\n' ;;
  *series-9-s1e3*)  printf 'codec_name=h264\nprofile=High 10\npix_fmt=yuv420p10le\nlevel=40\n' ;;
  *hevc-main10*)    printf 'codec_name=hevc\nprofile=Main 10\npix_fmt=yuv420p10le\nlevel=120\n' ;;
  *playlist-only*)  printf 'codec_name=h264\nprofile=High\npix_fmt=yuv420p\nlevel=31\n' ;;
  *) exit 1 ;;
esac
FAKE
chmod +x "$TMP/ffprobe"

out=$(FFPROBE="$TMP/ffprobe" CONF=/nonexistent bash "$HERE/audit_pixfmt.sh" "$TMP/lib")
echo "$out"
echo

fail=0
check() { if echo "$out" | grep -Eq "$1"; then echo "PASS: $2"; else echo "FAIL: $2"; fail=1; fi; }
check '^movie-8bit +1080p +h264 +yuv420p +High +40 +OK$'            '8-bit High 1080p is OK'
check '^movie-8bit +720p +h264 +yuv420p +High +40 +OK$'             '8-bit High 720p is OK'
check '^series-9-s1e3 +1080p +h264 +yuv420p10le +High 10 +40 +REPACKAGE$' '10-bit High 10 needs re-package'
check '^hevc-main10 +1080p +hevc +yuv420p10le +Main 10 +120 +REPACKAGE$'  'HEVC Main 10 needs re-package'
check '^playlist-only +720p +h264 +yuv420p +High +31 +OK$'          'falls back to stream_*.m3u8 when no MP4'
check '^renditions checked: 5 +needing re-package: 2$'              'summary counts'
check '^  series-9-s1e3$'                                           'bad title listed'
check '^  hevc-main10$'                                             'bad HEVC title listed'
if echo "$out" | grep -q 'source'; then echo "FAIL: source.mp4 must be skipped"; fail=1; else echo "PASS: source.mp4 skipped"; fi
# A fixed-order parser would have put "High" into PIX_FMT and "yuv420p" into PROFILE.
if echo "$out" | grep -Eq '^movie-8bit +1080p +h264 +High'; then echo "FAIL: positional parsing detected"; fail=1; else echo "PASS: key=value parsing (columns not swapped)"; fi
exit $fail
