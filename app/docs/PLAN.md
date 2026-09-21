# CARI TV mobile app — build plan

Flutter client for the existing CARI-IPTV `/api/v1` API. Android first; iOS-ready (no Android-only plugins, `ios/` kept intact).
Facts about the API are in `API_DISCOVERY.md`; gaps in `API_GAPS.md`; backend changes in `BACKEND_CHANGES.md`.

## Architecture

```
lib/
  main_dev.dart / main_prod.dart      flavour entry points → bootstrap(AppConfig.dev|prod)
  app.dart                            MaterialApp.router, theme from AppConfig
  config/app_config.dart              ONE place for name, colours, logo, API base URL, legal URLs, platform tag
  core/
    network/  api_client.dart (dio), auth_interceptor.dart (bearer + single-flight refresh), api_envelope.dart, api_exception.dart
    storage/  token_store.dart (flutter_secure_storage), cache_store.dart (hive_ce boxes + manifest hashes)
    util/     json.dart (tolerant parsing — PHP/PDO returns numbers as strings), url_resolver.dart, time.dart
    theme/    app_theme.dart
    widgets/  async_view.dart (loading/empty/error/offline), poster_card.dart, content_rail.dart, …
  models/                             immutable Dart classes with fromJson (no codegen)
  features/<feature>/                 data/ (repository) · state/ (Riverpod Notifier/AsyncNotifier) · ui/
  router/app_router.dart              go_router, StatefulShellRoute for bottom tabs
test/                                 unit tests: envelope, client+interceptor (http_mock_adapter), url resolver, models from fixtures
```

Decisions (and why):
- **Riverpod 3 without code generation** and **hand-written models** — the API returns numeric fields inconsistently typed (PDO strings vs ints, `1`/`"1"`/`true`), so tolerant hand-written `fromJson` is safer than generated strict parsers, and the build stays `build_runner`-free.
- **video_player 2.14** (ExoPlayer/AVPlayer) with custom controls — HLS + AES-128 keys are handled natively; the key URL is public (see discovery §4) so no header injection is needed for keys. Headers are still supported if that changes.
- **Cache**: Hive boxes per endpoint keyed by URL; invalidated per content type when the `/manifest` hashes change (polled every 30 s in the foreground and on resume), mirroring the web player.
- **Whitelabel**: `AppConfig` + `assets/branding/` + Android product flavours `dev`/`prod` (`applicationIdSuffix ".dev"`, distinct app name). Dev sends `platform: "mobile-dev"` on analytics and ad calls.
- **No purchase flows**: any `402 PAYMENT_REQUIRED` shows "Not available in the app".
- **Permissions**: only `INTERNET` (network) and `WAKE_LOCK` (added by wakelock_plus). Nothing else.

## Packages
| Package | Why |
|---|---|
| flutter_riverpod 3.x | state management, testable providers |
| dio 5.x | HTTP with interceptors (bearer, refresh, ETag), cancellation |
| flutter_secure_storage 11.x | tokens in Keystore / Keychain |
| hive_ce 2.x (+ hive_ce_flutter) | offline cache with manifest-hash invalidation |
| connectivity_plus 7.x | offline banner and retry |
| go_router 18.x | declarative routes, bottom-tab shell, deep links later |
| video_player 2.14 | HLS/AES-128 playback via ExoPlayer & AVPlayer, headers, track selection |
| wakelock_plus 1.8 | keep screen awake while playing |
| cached_network_image 4.x | poster/backdrop caching |
| package_info_plus / device_info_plus | app version in Settings; `device_name` at login |
| url_launcher 6.x | open privacy/terms pages in the browser |
| intl 0.20 | EPG time formatting |
| shimmer 4.x | loading skeletons |
| flutter_launcher_icons / flutter_native_splash | branding assets from one folder |
| flutter_lints 6 / mocktail / http_mock_adapter | analyze gate and unit tests |

## Quality gates (every phase)
`flutter analyze` → no issues · `flutter test` → green · `flutter build apk --debug --flavor dev` → succeeds.

## Phases

### P0 Discovery ✅
- [x] Read routes, controllers, services; write `API_DISCOVERY.md`
- [x] Write `API_GAPS.md`

### P1 Plan ✅
- [x] Architecture, packages, phases (this file) — approved with changes on 2026-09-20

### P2 Toolchain and scaffold
- [x] Install Flutter 3.47.5 + Android SDK 36 (build-tools 36.0.0)
- [x] `flutter create` into `app/` (org `net.caritech`, Android + iOS)
- [x] pubspec with pinned, resolved dependencies
- [x] `AppConfig` with dev/prod values; Android product flavours; entry points
- [x] Theme, branding placeholders (`assets/branding/`)
- [x] `.gitignore` for keystores / `key.properties`; README skeleton
- [x] Quality gates pass; commit

### P3 Core: network, storage, models, tests
- [x] Tolerant JSON helpers + `UrlResolver` (relative `/uploads` → absolute)
- [x] Models: `AuthTokens`, `User`, `Entitlements`, `Package`, `Channel`, `Movie`, `Series`, `Season`, `Episode`, `Category`, `SearchResult`, `WatchProgress`, `ContinueWatchingItem`, `WatchlistEntry`, `Rating`, `Layout`/`Section`/`LayoutItem`, `Navigation`/`NavItem`, `AppPage`, `Manifest`, `EpgProgramme`, `Ad`, `AdBreak`, `OverlaySettings`, `RecommendationSet`
- [x] `ApiEnvelope` parser for the three envelope shapes + `ApiException` with `code`
- [x] `ApiClient` (dio) + `AuthInterceptor` (bearer, pre-emptive refresh at exp−30 s, single-flight, retry once on 401, force sign-out on refresh failure)
- [x] `TokenStore` (secure storage) and `CacheStore` (Hive)
- [x] Repositories: auth, content, layout, epg, ads, recommendations, analytics
- [x] Unit tests: envelope, models (fixtures), url resolver, interceptor refresh flow
- [x] Quality gates pass; commit

### P4 Auth UI
- [x] Splash/session restore → login or home
- [x] Login (identity/password, device_name, `device_type: mobile`), unverified-email notice with resend
- [x] Register (all fields, validation), "check your email" screen
- [x] Forgot password (email) and "link sent" screen; reset link opens the web page
- [x] Logout (revokes refresh, clears storage); evicted-device handling on refresh 401
- [x] Privacy and terms links on login and register
- [x] Quality gates pass; commit

### P5 Server-driven home, navigation and pages
- [x] Manifest poller + cache invalidation
- [x] Bottom navigation from `/app/navigation/mobile` (max 5), page routing by `page_type`
- [x] Layout renderer: `hero_slideshow`, `content_row` (server + client sources), `channel_grid`, `continue_watching`, `category_grid`, `banner`, `spotlight`, `text_divider`, recommendation sections, `live_now`, `epg_schedule`; unknown types skipped
- [x] Built-in fallback home when `/app/layout/mobile` is 404
- [x] Loading / empty / error / offline states
- [x] Quality gates pass; commit

### P6 VOD
- [x] Movies list (sort, category, pagination), Series list
- [x] Movie detail (backdrop, metadata, cast, trailers link, rating, watchlist, play/resume)
- [x] Series detail (seasons, episodes, per-episode progress via batch endpoint)
- [x] Entitlement gating (`is_restricted` vs entitlements), adult gating with PIN
- [x] Quality gates pass; commit

### P7 Player
- [x] Playback screen (video_player), portrait 16:9 stage with full-screen (landscape) toggle, wakelock, custom controls
- [x] Resume prompt + progress POST every 10 s, completed handling
- [x] Markers: skip intro, next-episode countdown at credits; `next_episode` chaining
- [x] Subtitles (VTT from `subtitles[]`) toggle
- [x] Error states with retry and the error code; QoE events
- [x] Quality gates pass; commit

### P8 Live TV and EPG
- [x] Channel list with categories and now/next
- [x] Guide (grid by date) with programme detail
- [x] Live playback (no resume), channel switching
- [x] Quality gates pass; commit

### P9 Search, categories, watchlist, continue watching, profile, settings
- [x] Search with type filter; Categories → filtered lists
- [x] My List (watchlist) with detail hydration; Continue Watching
- [x] Profile: account info, adult toggle, parental PIN, packages/entitlements (view only, no purchases)
- [x] Settings: app version, clear cache, privacy/terms links, sign out, **Delete account** (confirmation + password re-entry)
- [x] Quality gates pass; commit

### P10 Ads, recommendations, analytics
- [x] Pre-roll / mid-roll (from `/ads/breaks`) with skip, impression + events; banner and text-scroller overlays per `/ads/overlay-settings`
- [x] Recommendation sections; analytics event queue with batch flush (`platform` per flavour)
- [x] Quality gates pass; commit

### P11 Live verification and release
- [x] Verify every authenticated endpoint against `player.caritech.net` with the test account (credentials in env only); correct `API_DISCOVERY.md`; record stream URL schemes/formats (`tool/verify_api.py`, 2026-09-20)
- [x] Android cleartext policy decided from real stream URLs (all HTTPS → caritv HTTPS-only; policy is per brand)
- [x] Release signing config (`key.properties`, gitignored) documented; `flutter build appbundle --release --flavor prod` builds (60 MB, debug-signed until a keystore is added); target SDK = Play requirement (verified: API 36 as of 2026-08-31)
- [x] `README.md` (setup, config, build, install), `STORE_READINESS.md`, `IOS_NEXT_STEPS.md`
- [x] Final tick of this plan; `API_GAPS.md` final

### P12 White-label (added 2026-09-20)
- [x] `brands/<brand>/brand.json` + 4 PNGs as the only brand input; `caritv` placeholder and `islandtv` example
- [x] `AppConfig` built from `--dart-define-from-file` (defaults = caritv); no code edits per brand
- [x] `tool/apply_brand.dart`: assets, `android/brand.properties` (id, name, signing file), generated `network_security_config.xml` from `CLEARTEXT_HOSTS`, icons + splash, iOS display name, ATS snippet
- [x] `tool/build_brand.sh <brand> <apk|appbundle|run> [dev|prod]`
- [x] Gradle reads brand id/name and per-brand `key.properties`; separate key per brand documented
- [x] Player shows a specific message when a stream is blocked by the cleartext policy
- [x] Proof: `com.example.islandtv` / "Island TV" and `net.caritech.caritv.dev` / "CARI TV Dev" verified with `aapt dump badging`
- [x] `docs/WHITE_LABEL.md`; README, IOS_NEXT_STEPS, STORE_READINESS updated
