#!/usr/bin/env bash
# Archive, sign and upload the iOS app to App Store Connect (TestFlight).
#
# Run after `flutter build ios --release --no-codesign` (tool/build_brand.sh
# <brand> ipa does both). Signing is Xcode "automatic" with cloud-managed
# certificates, authenticated by an App Store Connect API key, so no
# certificate or provisioning profile ever has to be exported from a Mac.
#
# Required environment (from the operator's Apple Developer account):
#   APPLE_TEAM_ID        10-character team id
#   ASC_KEY_ID           App Store Connect API key id
#   ASC_ISSUER_ID        App Store Connect issuer id
#   ASC_KEY_P8           contents of the AuthKey_<KEY_ID>.p8 file
# Optional:
#   IOS_UPLOAD=0         archive and export the .ipa only, do not upload
set -euo pipefail
BRAND="${1:?brand}"
cd "$(dirname "$0")/.."

: "${APPLE_TEAM_ID:?}" "${ASC_KEY_ID:?}" "${ASC_ISSUER_ID:?}" "${ASC_KEY_P8:?}"
KEYDIR="$HOME/.appstoreconnect/private_keys"
mkdir -p "$KEYDIR"
KEYFILE="$KEYDIR/AuthKey_${ASC_KEY_ID}.p8"
printf '%s\n' "$ASC_KEY_P8" > "$KEYFILE"
chmod 600 "$KEYFILE"

AUTH=(-allowProvisioningUpdates -allowProvisioningDeviceRegistration
      -authenticationKeyPath "$KEYFILE" -authenticationKeyID "$ASC_KEY_ID" -authenticationKeyIssuerID "$ASC_ISSUER_ID")

OUT="build/ios/ipa"
rm -rf "$OUT"; mkdir -p "$OUT"
ARCHIVE="$OUT/Runner.xcarchive"

echo "== archive ($BRAND, team $APPLE_TEAM_ID)"
xcodebuild -workspace ios/Runner.xcworkspace -scheme Runner -configuration Release \
  -destination 'generic/platform=iOS' -archivePath "$ARCHIVE" \
  CODE_SIGN_STYLE=Automatic DEVELOPMENT_TEAM="$APPLE_TEAM_ID" "${AUTH[@]}" \
  archive | tail -40

UPLOAD="${IOS_UPLOAD:-1}"
cat > "$OUT/ExportOptions.plist" <<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
  <key>method</key><string>app-store-connect</string>
  <key>destination</key><string>$([ "$UPLOAD" = "1" ] && echo upload || echo export)</string>
  <key>signingStyle</key><string>automatic</string>
  <key>teamID</key><string>$APPLE_TEAM_ID</string>
  <key>uploadSymbols</key><true/>
  <key>manageAppVersionAndBuildNumber</key><false/>
</dict></plist>
PLIST

echo "== export ($([ "$UPLOAD" = "1" ] && echo 'upload to App Store Connect' || echo 'ipa only'))"
xcodebuild -exportArchive -archivePath "$ARCHIVE" -exportOptionsPlist "$OUT/ExportOptions.plist" \
  -exportPath "$OUT" "${AUTH[@]}" | tail -40

ls -la "$OUT"
