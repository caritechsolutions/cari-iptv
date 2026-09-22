# White-label guide — one brand = one folder

The app is brand-agnostic. Everything that identifies a brand is a **build-time input** read from `brands/<brand>/`; no Dart, Gradle or Xcode file is edited to add a brand. `caritv` is the development placeholder, `islandtv` is a worked example. Neither has been published anywhere, so no application id is locked in yet.

## What is per brand

| Input | Where | Reaches the app via |
|---|---|---|
| Application id (Android) / bundle id (iOS) | `brand.json` → `APPLICATION_ID` | `android/brand.properties` (generated) → Gradle `applicationId`; iOS: set in Xcode (see below) |
| Display / launcher name | `APP_NAME` | Gradle `resValue app_name` (dev flavour appends " Dev"); iOS `CFBundleDisplayName` (written by the tool); in-app via dart-define |
| Logo, launcher icon, adaptive foreground, splash | `logo.png` (512²), `icon.png` (1024²), `icon_foreground.png` (1024², transparent), `splash.png` (512²) | copied to `assets/branding/`; icons and splash regenerated with flutter_launcher_icons / flutter_native_splash |
| Colours | `PRIMARY_COLOR`, `ACCENT_COLOR`, `BACKGROUND_COLOR`, `SURFACE_COLOR` (`#RRGGBB`) | dart-define → `AppConfig` → theme; background also used for the adaptive icon and splash |
| API base URL | `API_BASE_URL_DEV`, `API_BASE_URL_PROD` (site origin, no path) | dart-define; the API is at `/api/v1` |
| Legal pages | `PRIVACY_URL`, `TERMS_URL`, `DELETE_ACCOUNT_URL` | dart-define; opened in the browser from login, register, settings |
| Analytics/ads platform tag | `PLATFORM_TAG_PROD` (default `mobile`; dev builds send `<tag>-dev`) | dart-define |
| Cleartext (plain http) stream hosts | `CLEARTEXT_HOSTS` (comma-separated; **empty = HTTPS only**) | `android/app/src/main/res/xml/network_security_config.xml` (generated); iOS ATS snippet printed by the tool |
| Billing surfaces | `BILLING_UI` (`auto` = follow the server's `features.billing`, `on`, `off`; default `auto`) | dart-define → `AppConfig.billingUi`; `off` hides the packages page and nav item, package rows and sections, activate/cancel actions and any plan/price wording whatever the server says (use it for store builds that must not show billing) |
| Signing key | `key.properties` + `upload-keystore.jks` in the brand folder (gitignored) | `android/brand.properties` → Gradle release signing |

Shared across brands: all code, the Android `namespace` (`net.caritech.caritv`, a code identifier unrelated to the store id), permissions, version number in `pubspec.yaml`.

## Add a brand

1. Copy a brand folder: `cp -r brands/islandtv brands/<newbrand>`.
2. Edit `brands/<newbrand>/brand.json`. Required keys: `BRAND_KEY`, `APP_NAME`, `APPLICATION_ID`, `API_BASE_URL_DEV`, `API_BASE_URL_PROD`, the four colours, the three legal URLs. Optional: `PLATFORM_TAG_PROD`, `CLEARTEXT_HOSTS`, `BILLING_UI` (`auto` / `on` / `off`, see the table; the tool rejects other values).
   - `APPLICATION_ID` must be reverse-DNS the operator controls (e.g. `com.operator.tv`). **It cannot change after the first Play/App Store upload.**
3. Replace the four PNGs with the operator's artwork (same sizes; the icon must not rely on transparency, the foreground must be transparent outside the glyph).
4. Create the brand's signing key (see below).
5. Build:
   ```bash
   ./tool/build_brand.sh <newbrand> apk dev --debug     # try it on a device
   ./tool/build_brand.sh <newbrand> appbundle prod      # Play upload (AAB)
   ./tool/build_brand.sh <newbrand> apk prod            # sideload / other stores
   ./tool/build_brand.sh <newbrand> run dev             # flutter run with the brand applied
   ```
   Add `--no-icons` to skip regenerating icons/splash on repeat builds.
6. Verify: `aapt dump badging build/app/outputs/flutter-apk/app-prod-release.apk | grep -E "package:|application-label"`.

`tool/build_brand.sh` runs `dart run tool/apply_brand.dart <brand>` and then `flutter build … --dart-define-from-file=brands/<brand>/brand.json`. You can run `apply_brand.dart` alone to switch the working tree to a brand (for `flutter run` from an IDE, add the same `--dart-define-from-file` to the run configuration).

The last applied brand leaves generated files in the tree (`assets/branding/`, launcher icons, splash resources, `network_security_config.xml`, `ios/Runner/Info.plist` display name). The committed state is `caritv`; run `dart run tool/apply_brand.dart caritv` before committing unrelated changes so the diff stays clean.

## Signing — one key per brand

Each brand is a separate store listing, so each has its own upload key. Keys are never shared between brands and never committed (`brands/*/key.properties`, `brands/*/*.jks` are gitignored).

```bash
cd brands/<brand>
keytool -genkey -v -keystore upload-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias upload
cat > key.properties <<EOF
storePassword=<store password>
keyPassword=<key password>
keyAlias=upload
storeFile=upload-keystore.jks
EOF
```
`storeFile` is relative to the brand folder. When `key.properties` is absent the release build is signed with the debug key (fine for local testing, rejected by the stores). Enable Play App Signing for each listing and back the keystore up outside the repo; losing it means losing the ability to update that brand.

## Cleartext (plain http://) streams

Stream URLs come from the operator's API at runtime, so the policy is per brand:

- `CLEARTEXT_HOSTS` empty (default, and the `caritv` value after live verification — all its streams are HTTPS): the app allows **only https://** media and API traffic.
- `CLEARTEXT_HOSTS="cdn-a.example.com, cdn-b.example.net"`: plain http is allowed **only** for those hosts (and subdomains). Any other http:// stream fails to play and the player shows *"This stream uses an insecure http:// address (host) that this app is not allowed to play"* instead of a generic error.
- The recommended fix is always HTTPS on the stream host, not a longer allow-list. Play's Data safety form asks whether data is encrypted in transit; a non-empty list changes that answer.
- iOS: `apply_brand.dart` prints the matching `NSAppTransportSecurity` / `NSExceptionDomains` snippet to paste into `ios/Runner/Info.plist` (see `IOS_NEXT_STEPS.md`). An http host not listed there fails on iOS too.

## iOS bundle id

The tool sets the display name in `Info.plist`; the bundle id is set once per brand in Xcode (`Runner` target → Signing & Capabilities → Bundle Identifier = `APPLICATION_ID`) together with the brand's Apple team and provisioning profile. Keep a separate Apple distribution certificate/profile per brand as well.

## Per-brand store items

Each brand needs its own: privacy policy URL and account-deletion URL (backend pages on the brand's host), Play listing and Data safety answers, App Store listing and privacy labels, screenshots, reviewer test account, keystore/certificates. See `STORE_READINESS.md`.

## Checklist for a new brand

- [ ] `brands/<brand>/brand.json` filled in, application id confirmed with the operator
- [ ] Four PNGs replaced
- [ ] Backend host serves `/privacy`, `/delete-account`, `/forgot-password`, `/reset-password/{token}` and has a **published mobile layout** (or accept the built-in fallback home)
- [ ] `CLEARTEXT_HOSTS` decided from the operator's real stream URLs (`python3 tool/verify_api.py` with a test account on their host lists every scheme and host)
- [ ] Keystore created and backed up; `key.properties` in place
- [ ] `./tool/build_brand.sh <brand> appbundle prod` succeeds; `aapt dump badging` shows the right id and label
- [ ] Installed and smoke-tested on a device: login, home, VOD playback, live channel, guide, delete account
