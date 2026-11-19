#!/usr/bin/env bash
# Emit GitHub check annotations from a build log so failures can be read
# through the API (job logs themselves are not reachable from every tool).
# Usage: tool/ci_annotate.sh <title> <logfile>
# Progress spinners write \r-separated updates; normalise to lines first.
set -u
TITLE="${1:?title}"; LOG="${2:?log}"
tr '\r' '\n' < "$LOG" | grep -vE '^\s*$' > "$LOG.lines"
enc() { sed ':a;N;$!ba;s/\n/%0A/g' | sed 's/::/: :/g' | cut -c1-3900; }
excerpt=$(grep -nE "error|Error|FAILED|failed|\*\*\*|Exception|not found|No such|unbound|xcodebuild|Undefined|Unhandled" "$LOG.lines" | head -60 | cut -c1-240)
echo "::error title=$TITLE (matches)::$(printf '%s' "$excerpt" | enc)"
echo "::error title=$TITLE (log tail)::$(tail -n 45 "$LOG.lines" | cut -c1-240 | enc)"
