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

Stream URL schemes/formats on live data (2026-09-20): 26/26 `https://`; channels `.m3u8` on `headend.caritech.net`; VOD `master.m3u8` on `vod1.caritech.net:8090`; one movie has a YouTube URL as `stream_url` (#21).

| 21 | A movie's `stream_url` can be a YouTube watch URL | Not playable by a native player | **Worked around** — URLs on youtube.com/youtu.be/vimeo.com (or without a media extension) open in the browser with a "Watch on YouTube" button |
| 22 | No EPG data on the live install; `/movies/featured` empty; no mobile layout published | Empty guide, no hero | **Worked around** — fallback home uses latest titles; guide shows an empty state. Publish a mobile layout in Admin → App Layout to take over the home screen |

## Free content and the lock rule

Channels flagged "Available Without Purchase" and movies flagged "Free Content" in admin now reach the API as `is_free` (`backend:` commit, BACKEND_CHANGES.md §11, not deployed). Lock rule in both clients: free → playable; else no subscription → locked; else restricted and not entitled → locked. Until an install carries the commit the field is absent and such content stays locked for accounts without a subscription, as before.

## Guest (no-login) mode — design, not built

Decided 2026-10-11: not now. What it needs on top of the free-content rule, for when it is wanted:

1. **Anonymous device account.** `POST /auth/guest` with a device id creates a subscriber row with `is_guest = 1`, no email or password, status active, `max_connections` 1, keyed by the device id (new column or `external_id`), and returns the usual token pair; a repeat call with the same device id returns the same account. Needed because every content endpoint is behind `api_auth`.
2. **What a guest sees.** Entitlements give `has_subscription` false and empty id sets, so the rule above yields exactly the free content. Lists should hide the rest rather than lock it (a `guest=1` filter on the list queries, or the existing `channels.show_to_demo_users`, default 1, as the "visible to guests" switch separate from "playable").
3. **Protected surfaces.** Guests get 403 on watchlist, ratings, profile edits, package activation and billing; watch progress is kept so it survives the upgrade.
4. **Upgrade.** `POST /auth/upgrade` on a guest token takes the registration fields and converts the same row (email, password, name, `is_guest = 0`), sends the verification email and keeps history and progress; the web register page and the app register screen call it instead of `register` when a guest token is present.
5. **Clients.** "Browse as guest" on the login screens, a persistent "Create account" prompt, padlock wording "Sign up to watch" for guests; in the app a guest is an `AuthSignedIn` user with `isGuest` true and a few screens gated on it.
6. **Housekeeping.** Purge guest rows idle for 90 days; hide or label guests in Admin → Subscribers.

Rough size: backend about a day, web and app about a day each.

## Padlock data on list rows

`/search` rows and `/auth/continue-watching` rows carry no `is_restricted`, `is_adult` or `category_id`, so cards built from them cannot show the padlock and the play gate sees them as unrestricted until the detail endpoint is opened (movie and series detail pages re-check; a restricted **channel** opened straight from a search result plays without the gate). Fixed in the `backend:` commit "search and continue-watching rows carry the entitlement fields" (`ContentApiService::search()`, `SubscriberAuthService::getContinueWatching()`), not yet deployed; until an install carries it the app sees those rows as unrestricted. The app parses the three fields (`SearchResult`, `ContinueWatchingItem`) and gates on them.

