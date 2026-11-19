#!/usr/bin/env bash
# Emit GitHub check annotations from a build log so failures can be read
# through the API (job logs themselves are not reachable from every tool).
# Usage: tool/ci_annotate.sh <title> <logfile>
# Progress spinners write \r-separated updates; normalise to lines first.
# Portable: runs under macOS bash 3.2 / BSD sed as well as GNU (uses awk only).
set -u
TITLE="${1:?title}"; LOG="${2:?log}"
tr '\r' '\n' < "$LOG" | grep -vE '^\s*$' | cut -c1-240 > "$LOG.lines"

# Join lines with %0A (GitHub's newline escape) and neutralise "::".
enc() { awk '{ gsub(/::/, ": :"); printf "%s%%0A", $0 }' | cut -c1-3900; }

# 1. Interesting lines, with line numbers.
excerpt=$(grep -nE "error|Error|FAILED|failed|\*\*\*|Exception|not found|No such|unbound|xcodebuild|Undefined|Unhandled|warning: " "$LOG.lines" | head -60)
echo "::error title=$TITLE (matches)::$(printf '%s\n' "$excerpt" | enc)"

# 2. Log tail in ~25-line chunks (GitHub caps a message near 4 KB), newest last.
total=$(wc -l < "$LOG.lines" | tr -d ' ')
start=$(( total > 150 ? total - 150 + 1 : 1 ))
n=1
while [ "$start" -le "$total" ]; do
  end=$(( start + 24 )); [ "$end" -gt "$total" ] && end="$total"
  chunk=$(sed -n "${start},${end}p" "$LOG.lines" | enc)
  echo "::error title=$TITLE (log $n: lines $start-$end of $total)::$chunk"
  start=$(( end + 1 )); n=$(( n + 1 ))
done
