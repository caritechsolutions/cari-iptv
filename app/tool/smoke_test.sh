#!/usr/bin/env bash
# Emulator smoke test: install an APK, launch its main activity, wait, and
# decide from facts (process alive, resumed activity, logcat) whether the app
# actually started. Always writes logcat + a screenshot to $OUT_DIR.
#
#   ./tool/smoke_test.sh <apk> <package> [out_dir] [wait_seconds]
#
# Exit codes: 0 = app running with our activity resumed and no FATAL EXCEPTION;
#             1 = crash / not running / FATAL EXCEPTION in logcat.
set -uo pipefail

APK="${1:?apk path}"
PKG="${2:?package name}"
OUT_DIR="${3:-smoke-out}"
WAIT="${4:-20}"
mkdir -p "$OUT_DIR"
LOG="$OUT_DIR/logcat.txt"

step() { echo; echo "== $*"; }

step "device"
adb wait-for-device
adb shell getprop ro.build.version.release | sed 's/^/Android /'
adb shell getprop ro.build.version.sdk | sed 's/^/API /'
adb shell getprop ro.product.cpu.abi | sed 's/^/ABI /'

step "install $APK"
adb logcat -c || true
adb uninstall "$PKG" >/dev/null 2>&1 || true
if ! adb install -r -t "$APK"; then
  echo "SMOKE RESULT: FAIL (install failed)"
  exit 1
fi

step "launch $PKG"
adb shell monkey -p "$PKG" -c android.intent.category.LAUNCHER 1 >/dev/null 2>&1 || adb shell am start -n "$PKG/net.caritech.caritv.MainActivity"

step "wait ${WAIT}s"
sleep "$WAIT"

step "facts"
PID="$(adb shell pidof "$PKG" 2>/dev/null | tr -d '\r\n ' || true)"
RESUMED="$(adb shell dumpsys activity activities 2>/dev/null | grep -E 'mResumedActivity|topResumedActivity' | head -3 | tr -s ' ')"
echo "pid: ${PID:-<none>}"
echo "resumed: ${RESUMED:-<none>}"
adb shell screencap -p /sdcard/smoke.png >/dev/null 2>&1 && adb pull /sdcard/smoke.png "$OUT_DIR/screenshot.png" >/dev/null 2>&1 || echo "(screenshot failed)"

step "logcat"
adb logcat -d > "$LOG" 2>/dev/null || true
echo "logcat lines: $(wc -l < "$LOG")"
FATAL="$(grep -n -A 40 'FATAL EXCEPTION' "$LOG" | head -120 || true)"
ANR="$(grep -n 'ANR in' "$LOG" | head -5 || true)"
FLUTTER_ERR="$(grep -nE 'flutter.*(Unhandled Exception|Exception:|Error:)|E/flutter' "$LOG" | head -40 || true)"
MARKER="$(grep -n 'CARI_SMOKE' "$LOG" | head -5 || true)"

if [[ -n "$FATAL" ]]; then echo "--- FATAL EXCEPTION ---"; echo "$FATAL"; fi
if [[ -n "$ANR" ]]; then echo "--- ANR ---"; echo "$ANR"; fi
if [[ -n "$FLUTTER_ERR" ]]; then echo "--- flutter errors ---"; echo "$FLUTTER_ERR"; fi
if [[ -n "$MARKER" ]]; then echo "--- app markers ---"; echo "$MARKER"; fi
echo "--- last 60 lines for $PKG ---"
grep -E "$PKG|flutter|AndroidRuntime" "$LOG" | tail -60 || true

step "verdict"
OK=1
[[ -z "$PID" ]] && { echo "process not running"; OK=0; }
[[ -n "$FATAL" ]] && { echo "FATAL EXCEPTION in logcat"; OK=0; }
if ! echo "$RESUMED" | grep -q "$PKG"; then echo "our activity is not the resumed activity"; OK=0; fi
if [[ "$OK" == 1 ]]; then
  echo "SMOKE RESULT: PASS ($PKG pid $PID, activity resumed, no FATAL EXCEPTION)"
  exit 0
fi
echo "SMOKE RESULT: FAIL"
exit 1
