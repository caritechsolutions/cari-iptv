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

## 3. Configuration and whitelabelling

Everything brand- and environment-specific lives in **one file**: `lib/config/app_config.dart`.

| Field | Meaning |
|---|---|
| `apiBaseUrl` | Site origin, e.g. `https://player.caritech.net` (the API is at `/api/v1`). Dev and prod currently point at the same host; change `prod` when a production host exists. |
| `appName` | In-app display name |
| `primaryColor`, `accentColor`, `backgroundColor`, `surfaceColor` | Theme colours |
| `logoAsset` | Logo shown on the login screen and splash |
| `privacyUrl`, `termsUrl`, `deleteAccountUrl` | Public legal pages served by the backend |
| `platformTag` | `mobile` (prod) / `mobile-dev` (dev), sent on analytics and ad calls so test traffic can be filtered |

Images: replace the files in `assets/branding/` (`logo.png` 512², `icon.png` 1024², `icon_foreground.png` 1024² with transparent background, `splash.png` 512²), then regenerate launcher icons and splash:

```bash
dart run flutter_launcher_icons
dart run flutter_native_splash:create
```

The launcher label per flavour is set in `android/app/build.gradle.kts` (`resValue "app_name"`). The application id is `net.caritech.caritv` (`.dev` suffix for the dev flavour) — **it cannot change after the app is published on Google Play**, so confirm it before the first upload.

## 4. Flavours

| Flavour | Entry point | Application id | Platform tag |
|---|---|---|---|
| dev | `lib/main_dev.dart` | `net.caritech.caritv.dev` | `mobile-dev` |
| prod | `lib/main_prod.dart` | `net.caritech.caritv` | `mobile` |

Both flavours can be installed side by side.

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

# Release APK / App Bundle (needs signing, see §6)
flutter build apk --release --flavor prod -t lib/main_prod.dart
flutter build appbundle --release --flavor prod -t lib/main_prod.dart
#   → build/app/outputs/bundle/prodRelease/app-prod-release.aab
```

Target SDK is 36 (Google Play requirement for new apps and updates from 31 August 2026). Release builds use R8 minification and resource shrinking.

## 6. Release signing

Signing material is **never committed** (`android/key.properties`, `*.jks`, `*.keystore` are gitignored).

1. Create an upload keystore (once; keep it safe and backed up — losing it means you cannot update the app unless Play App Signing is enabled, which is recommended):
   ```bash
   keytool -genkey -v -keystore android/app/upload-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias upload
   ```
2. Create `android/key.properties`:
   ```properties
   storePassword=<store password>
   keyPassword=<key password>
   keyAlias=upload
   storeFile=upload-keystore.jks
   ```
   (`storeFile` is relative to `android/app/`.)
3. Build with `flutter build appbundle --release --flavor prod -t lib/main_prod.dart`. When `key.properties` is absent the release build falls back to the debug key so local release builds still work; Play will reject such a bundle.

## 7. Install on a device

```bash
adb devices
adb install -r build/app/outputs/flutter-apk/app-dev-debug.apk
```
Or copy the APK to the phone and open it (allow "install from unknown sources").

## 8. Permissions

Only `INTERNET` and `WAKE_LOCK` (keeps the screen on during playback). Nothing else is requested.

## 9. Network security

`android/app/src/main/res/xml/network_security_config.xml` currently **allows cleartext (http://)** because live channel `stream_url` values come from upstream providers and may be plain HTTP. The API itself is HTTPS. Tighten this after checking the real stream URLs (see the "Live verification" section of `docs/API_DISCOVERY.md`); with only HTTPS streams you can set `cleartextTrafficPermitted="false"`.

## 10. Project layout

```
lib/
  config/app_config.dart      flavour + branding
  core/                       network (dio + auth interceptor), storage (secure tokens, Hive cache), utils, theme, shared widgets
  models/                     API models (tolerant fromJson)
  features/                   auth, layout (server-driven home), content (VOD), player, live (EPG), search, library, profile, ads, recommendations, analytics
  router/app_router.dart      go_router with auth redirects and a bottom-tab shell
test/                         unit tests (39): envelope, client/refresh, url resolver, models
```

## 11. Testing notes

- `flutter test` covers the API client (bearer, pre-emptive refresh, retry-on-401, refresh failure → sign-out), the response envelopes and all model parsers.
- Manual device checks: sign in, home renders (server layout or built-in fallback), movie play/resume, series episode chaining, live channel playback, guide, search, My List, profile PIN/adult toggle, settings → delete account, airplane mode (offline banner + cached lists).
