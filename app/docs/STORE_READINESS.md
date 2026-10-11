# Store readiness — what the stores need that code cannot supply

**Per brand.** Every item below is needed once per white-label brand (own listing, own policy URLs on the brand's host, own keystore/certificates, own reviewer account). URLs below use the caritv placeholder host; substitute the brand's `PRIVACY_URL` / `DELETE_ACCOUNT_URL` from `brands/<brand>/brand.json`.

Everything below must be provided or decided by you before submission. Items marked **(app fact)** describe what the app actually does, derived from the code, so the answers are accurate.

## 1. Privacy policy

- URL to enter in Play Console and App Store Connect: `https://player.caritech.net/privacy` (route added to the backend; **placeholder text** in `templates/player/privacy.php` must be replaced and reviewed by a legal adviser).
- The page includes a `#deletion` section describing in-app and web deletion, and a `#terms` placeholder section that the app links to as "Terms of Service".
- The account deletion web page for users without the app: `https://player.caritech.net/delete-account`.

## 2. Google Play Data safety form (answers based on what the app actually collects)

**Does the app collect or share user data?** Yes (collected; not shared with third parties by the app itself — confirm your server-side processors).

| Data type | Collected? | Shared? | Purpose | Required/optional | Notes (app fact) |
|---|---|---|---|---|---|
| Name (first, last) | Yes | No | Account management | Required at registration | Sent to `/auth/register` |
| Email address | Yes | No | Account management, password reset | Required | Login identity, reset link |
| Phone number | Yes | No | Account management | Optional | Registration field |
| Date of birth / Country | Yes | No | Account management | Optional | Registration fields |
| User ID (subscriber id) | Yes | No | App functionality, analytics | — | In JWT and analytics events |
| Device or other IDs | No | No | — | — | No advertising ID, no device_id; only a human-readable `device_name` (manufacturer + model) is sent at login |
| App interactions (pages viewed, searches, watchlist, ratings) | Yes | No | Analytics, personalisation | — | `/analytics/*`, `/auth/*` |
| Video watch history and position | Yes | No | App functionality (resume), personalisation (recommendations) | — | `/auth/watch-progress` |
| Playback quality diagnostics (buffering, errors) | Yes | No | Performance/diagnostics | — | `/analytics/qoe` |
| Ad interactions (impressions, clicks) | Yes | No* | Advertising | — | `/ads/impression`, `/ads/event`; *if advertisers receive reports, declare sharing |
| Approximate location | No (by the app) | — | — | — | The server may derive country from IP; declare if used |
| Precise location, contacts, photos, files, calendar, health, financial info, messages | No | — | — | — | Not accessed |

Security practices: data is encrypted in transit (HTTPS for the API; streams may be HTTP — see below); users can request deletion (in-app and web). Data is **not** encrypted at rest by the app beyond the OS keystore for tokens.

Declare "Data collected is optional" only for phone/DOB/country.

## 3. Content rating questionnaire (IARC)

Answer from the content you distribute, not the app code. Points to consider:
- The app can show adult-rated titles when the subscriber enables adult content (account-level toggle with an optional 4-digit PIN). If you host such content, answer the "sexual content"/"mature" questions accordingly and describe the PIN control.
- Ads are shown (pre-roll/mid-roll video, banners, text) → answer "contains ads": yes.
- No user-generated content, no user-to-user communication, no gambling, no in-app purchases.
- Billing surfaces (packages page, package rows and sections, activate/cancel actions) are behind a server switch that defaults to **off** (Admin → Settings → Mobile App → `mobile_billing_enabled`) and a per-brand override (`BILLING_UI` in `brand.json`, see WHITE_LABEL.md). For a store submission set `BILLING_UI` to `off` so the build cannot show them whatever the server says; the 402 response then reads only "Not available." with no plan, price, purchase or website wording. Entitlement padlocks and "Not included in your plan" remain, they sell nothing.

## 4. Store listing assets (not produced by code)

- App name (≤30 chars), short description (≤80), full description (≤4000).
- App icon 512×512 PNG (the placeholder in `assets/branding/icon.png` must be replaced).
- Feature graphic 1024×500.
- Phone screenshots (min 2, recommended 4–8; 16:9 or 9:16, min 320 px, max 3840 px): suggested set — home, movie detail, player, live TV list, guide, search, My List, profile.
- Optional: 7-inch and 10-inch tablet screenshots (the layout is responsive), promo video URL.
- Category: Entertainment. Contact email and website. Target audience/age.
- App Store: 6.7" and 6.5" iPhone screenshots, iPad if supported, keywords, support URL.

## 5. Play Console settings to enter

- **Account deletion URL** (Data safety → Data deletion): `https://player.caritech.net/delete-account`.
- **Privacy policy URL**: `https://player.caritech.net/privacy`.
- **Ads declaration**: "Yes, my app contains ads".
- **App access**: provide reviewer credentials (a test subscriber account) because all content requires sign-in. Note that the account's `max_connections` should be ≥ 2 so reviewers on several devices are not evicted.
- **Target API**: 36 (built in).
- **Play App Signing**: enable and upload the upload key certificate from `keytool -exportcert`.
- **Financial features / Health / Government apps**: none.

## 6. Free TV (`freetv`): publishing as an update to the client's existing listing

Facts (2026-10-11): the client's app on Google Play is `com.freetv.ng`, Play App Signing enabled, latest release version code 3 (1.0.2). Its current **upload key** belongs to a third party (Afrikastream, Rwanda; certificate from 2017, SHA-256 `E6:0E:D1:81…`), not to the client. Google's **app signing key** for the listing has fingerprint `E2:26:9B:25…`; that key never changes and is what installed apps are verified against, so an update signed through Play keeps working for existing users.

Decision: publish our build as an update to that listing, not as a new app. The brand's `APPLICATION_ID` is therefore `com.freetv.ng` (changed from the placeholder `ng.freetv.app`, which was never uploaded anywhere), `VERSION_CODE` 4, `VERSION_NAME` 1.1.0, and the route in is an **upload key reset** to our `freetv` upload key (SHA-256 `88:C4:22:05…`, certificate PEM exported with `keytool -export -rfc`). Steps for the client are in `app/brands/freetv/README.md` ("Play release"). Users on the old app get the Play in-app update prompt once the update is live (`PLAY_UPDATES` true for this brand).

## 7. Open items that affect the answers above

- Live verification (2026-09-20): all 26 stream URLs are HTTPS; the caritv brand is built HTTPS-only. Other brands set `CLEARTEXT_HOSTS` in their `brand.json`; if non-empty, answer "encrypted in transit" accordingly.
- Whether ad impression data is shared with advertisers as reports (affects "Shared" for ad interactions).
- Retention period for anonymised subscription records after deletion (state it in the policy).
