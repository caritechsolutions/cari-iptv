# CARI TV — mobile app

Flutter client for the CARI-IPTV platform (`/api/v1`). Android first; the `ios/` project is generated and kept intact for a later iOS build (see `docs/IOS_NEXT_STEPS.md`).

| Doc | Purpose |
|---|---|
| `docs/API_DISCOVERY.md` | What the API actually does, read from code and checked live |
| `docs/API_GAPS.md` | What the API lacks and how the app works around it |
| `docs/BACKEND_CHANGES.md` | Backend endpoints/pages added for the app (password reset, account deletion, privacy page) and how to deploy them |
| `docs/PLAN.md` | Phased build plan with progress |
| `docs/STORE_READINESS.md` | Everything the stores need that code cannot supply |
| `docs/IOS_NEXT_STEPS.md` | What is needed to ship iOS |
| `docs/WHITE_LABEL.md` | How to add and build a brand |

## 1. Requirements

- Flutter 3.47.x stable (Dart 3.13) — `flutter --version`
- Android SDK: platform 36, build-tools 36.0.0, platform-tools (`sdkmanager "platforms;android-36" "build-tools;36.0.0" "platform-tools"`)
- JDK 17 or 21
- A device or emulator on Android 7.0+ (minSdk 24)

## 2. Setup

```bash
cd app
flutter pub get
flutter doctor            # Android toolchain must be green; Chrome/desktop are not needed
```

## 3. Brands (white-label)

The app is brand-agnostic. A brand is one folder `brands/<brand>/` holding `brand.json` (application id, name, colours, API base URLs, legal URLs, analytics tag, cleartext hosts) and four PNGs (logo, icon, adaptive foreground, splash). No code is edited to add a brand. `caritv` is the development placeholder; `islandtv` is a worked example. Full guide: **`docs/WHITE_LABEL.md`**.

```bash
./tool/build_brand.sh <brand> apk dev --debug     # debug APK for a device
./tool/build_brand.sh <brand> appbundle prod      # release AAB for Google Play
./tool/build_brand.sh <brand> run dev             # flutter run with the brand applied
```

The script runs `dart run tool/apply_brand.dart <brand>` (copies assets, writes `android/brand.properties`, generates `network_security_config.xml`, regenerates icons/splash, sets the iOS display name) and then `flutter build … --dart-define-from-file=brands/<brand>/brand.json`. Plain `flutter run` without a brand uses the caritv defaults compiled into `lib/config/app_config.dart`.

## 4. Flavours (environments)

| Flavour | Entry point | Application id | Platform tag |
|---|---|---|---|
| dev | `lib/main_dev.dart` | `<APPLICATION_ID>.dev`, label "<APP_NAME> Dev" | `<PLATFORM_TAG_PROD>-dev` (e.g. `mobile-dev`) |
| prod | `lib/main_prod.dart` | `<APPLICATION_ID>` | `<PLATFORM_TAG_PROD>` (e.g. `mobile`) |

Both flavours of a brand can be installed side by side. Dev builds use `API_BASE_URL_DEV`, prod builds `API_BASE_URL_PROD`.

## 5. Build and run

```bash
# Quality gates (run before every commit)
flutter analyze
flutter test

# Run on a connected device
flutter run --flavor dev -t lib/main_dev.dart

# Debug APK
flutter build apk --debug --flavor dev -t lib/main_dev.dart
#   → build/app/outputs/flutter-apk/app-dev-debug.apk

# Release APK / App Bundle for a brand (signing per brand, see §6)
./tool/build_brand.sh <brand> apk prod
./tool/build_brand.sh <brand> appbundle prod
#   → build/app/outputs/bundle/prodRelease/app-prod-release.aab
```

Running `flutter build` directly (without `tool/build_brand.sh`) builds whatever brand was last applied, with the caritv defaults if none was.

Target SDK is 36 (Google Play requirement for new apps and updates from 31 August 2026). Release builds use R8 minification and resource shrinking.

## 6. Release signing — one key per brand

Signing material is **never committed** (`brands/*/key.properties`, `brands/*/*.jks`, `*.keystore` are gitignored). Each brand has its own upload keystore in its folder:

```bash
cd brands/<brand>
keytool -genkey -v -keystore upload-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias upload
printf 'storePassword=…\nkeyPassword=…\nkeyAlias=upload\nstoreFile=upload-keystore.jks\n' > key.properties
```

`tool/apply_brand.dart` passes the brand's `key.properties` path to Gradle. Without it the release build is signed with the debug key so local release builds still work; the stores reject such uploads. Enable Play App Signing per listing and back each keystore up outside the repo.

## 7. Getting a test build (GitHub Actions)

You do not need a local toolchain to try the app. The workflow `.github/workflows/android-debug.yml` runs on every push to the mobile-app branch that changes `app/**`:

1. It builds `./tool/build_brand.sh caritv apk dev --debug` on Flutter 3.47.5 with Java 21 (pub and Gradle caches keep repeat runs short).
2. It publishes a GitHub **prerelease** tagged `dev-<short sha>` under the repository's Releases page with `caritv-dev-debug-<sha>.apk` (and its SHA-256) attached as a direct download.
3. The same APK is also uploaded as a workflow artifact (Actions → the run → Artifacts) as a fallback; artifacts expire after 14 days.

On the phone: open the release page in the browser, download the `.apk`, open it and allow installing from that source. It is a debug build signed with the debug key (application id `net.caritech.caritv.dev`, label "CARI TV Dev"), so it installs alongside any store build. The workflow uses no secrets and no signing keys; it can also be started by hand from the Actions tab (`workflow_dispatch`).

## 8. Install on a device (local build)

```bash
adb devices
adb install -r build/app/outputs/flutter-apk/app-dev-debug.apk
```
Or copy the APK to the phone and open it (allow "install from unknown sources").

## 9. Permissions

`INTERNET`, `WAKE_LOCK` (screen stays on during playback) and `ACCESS_NETWORK_STATE` (offline detection, added by connectivity_plus). Nothing else is requested; verified with `aapt dump badging`.

## 10. Network security (cleartext)

Generated per brand by `tool/apply_brand.dart` from `CLEARTEXT_HOSTS` in `brand.json` into `android/app/src/main/res/xml/network_security_config.xml`. Empty (the caritv value — live verification on 2026-09-20 found all 26 stream URLs on HTTPS) means HTTPS only. Listed hosts may serve plain http:// streams; any other http:// stream fails to play with an explicit message in the player. HTTPS streams are the recommended fix. iOS ATS mirrors this (see `docs/IOS_NEXT_STEPS.md`).

## 11. Project layout

```
brands/<brand>/               brand.json + logo/icon/splash PNGs (+ gitignored key.properties, keystore)
tool/apply_brand.dart         applies a brand to the tree; tool/build_brand.sh builds it; tool/verify_api.py checks a live API
lib/
  config/app_config.dart      build-time defines → AppConfig (defaults = caritv)
  core/                       network (dio + auth interceptor), storage (secure tokens, Hive cache), utils, theme, shared widgets
  models/                     API models (tolerant fromJson)
  features/                   auth, layout (server-driven home), content (VOD), player, live (EPG), search, library, profile, ads, recommendations, analytics
  router/app_router.dart      go_router with auth redirects and a bottom-tab shell
test/                         unit tests (48): envelope, client/refresh, url resolver, models, config, playback errors
```

## 12. Testing notes

- `flutter test` covers the API client (bearer, pre-emptive refresh, retry-on-401, refresh failure → sign-out), the response envelopes and all model parsers.
- Manual device checks: sign in, home renders (server layout or built-in fallback), movie play/resume, series episode chaining, live channel playback, guide, search, My List, profile PIN/adult toggle, settings → delete account, airplane mode (offline banner + cached lists).
