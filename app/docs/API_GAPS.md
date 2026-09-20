# API gaps and quirks relevant to the mobile app

Status key: **Closed** = backend change made in this branch (see BACKEND_CHANGES.md); **Worked around** = handled in the app; **Open** = left as is, decision needed.

| # | Gap | Impact on app | Status |
|---|---|---|---|
| 1 | No subscriber password reset endpoint | "Forgot password" impossible | **Closed** — `/auth/forgot-password`, `/auth/reset-password`, web page `/reset-password/{token}` |
| 2 | No account deletion endpoint or public deletion page | Store compliance | **Closed** — `/auth/delete-account`, web page `/delete-account` |
| 3 | No public privacy policy route | Store listing needs a URL | **Closed** — `/privacy` with placeholder text |
| 4 | No mobile layout is seeded and the layout API has no platform fallback (404 for `mobile`) | Home would be empty | **Worked around** — app renders a built-in default home (featured, latest movies/series, channels) until an admin publishes a mobile layout |
| 5 | No branding/theme endpoint | Whitelabel values | **Worked around** — name, colours, logo live in `lib/config/app_config.dart` |
| 6 | Refresh tokens do not rotate; logout does not revoke the access token (valid up to 1 h) | Security posture only | **Open** |
| 7 | Device limit evicts the oldest session silently; no `device_id`; each login consumes a slot | Evicted device sees 401 `TOKEN_EXPIRED` on refresh only | **Worked around** — app treats refresh 401 as "signed out on another device" and returns to login with that message |
| 8 | No sub-profiles (kids/adult); `parental_pin` returned in cleartext | Profiles screen is account-level only | **Open** (pin stays server-side concern) |
| 9 | `GET /auth/watchlist` returns bare `{content_type, content_id}` rows | N+1 detail fetches | **Worked around** — app fetches details per item (cached); acceptable for typical list sizes |
| 10 | Entitlement and adult filtering are client-side only (`is_restricted`, `is_adult`) | App must gate | **Worked around** — gated in app; not a security boundary |
| 11 | Three response envelopes (`data/meta`, bare `data`, `success`) | Parser complexity | **Worked around** — envelope-aware client |
| 12 | Image URLs are mixed relative (`/uploads/…`) and absolute (TMDB) | Broken images | **Worked around** — `UrlResolver` prefixes relative paths with the site origin |
| 13 | EPG times are `Z`-stamped by string replace; channel `now_playing` times are raw MySQL datetimes | Wrong clock if DB TZ ≠ UTC | **Worked around** — app parses both formats, treats them as UTC; if the server TZ is not UTC an offset setting can be added |
| 14 | No dedicated now/next endpoint | Extra work per channel | **Worked around** — derived from `/epg` guide client-side |
| 15 | `drm`, `markers`, `subtitles` absent on episodes embedded in `/series/{id}` | Must fetch episode detail before playing | **Worked around** — app calls `/episodes/{id}` on play |
| 16 | Ad impression/event endpoints are form-encoded, everything else is JSON | – | **Worked around** |
| 17 | `POST /auth/subscribe` returns 402 for paid packages; there is no payment flow | Store policy | **Worked around** — app shows a neutral "not available in the app" message and never links to payment |
| 18 | No terms-of-service route | App needs a terms link | **Worked around** — app links to `/privacy#terms` placeholder section |
| 19 | Web login page has no "Forgot password?" link | Web users cannot start a reset | **Open** — one-template change, awaiting your go-ahead |
| 20 | `subscriber_subscriptions` retention after account deletion is not time-limited | Privacy policy wording | **Open** — document retention period in policy or add a purge job |

Stream URL schemes/formats on live data: _to be filled in from live verification_.
