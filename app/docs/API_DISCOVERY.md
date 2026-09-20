# API discovery — CARI-IPTV `/api/v1` as read from code

Every statement here was read from the PHP source (citations are `file:line` relative to the repo root).
Items marked **LIVE** were later checked against `https://player.caritech.net`; see the "Live verification" section at the end.

## 0. Transport, envelopes, caching

- Router: `public/api/index.php:90-180`, single `api/v1` group. Only `GET` and `POST` exist (CORS allows only those, `BaseApiController.php:71`).
- Request bodies are **JSON** (`Api\AuthController::getJsonInput()`, `RecommendationController.php:272-277`). Exception: `/ads/impression` and `/ads/event` read `$_POST` (form-encoded).
- Auth header: `Authorization: Bearer <access_jwt>` (`ApiAuthMiddleware.php:19-31`).
- Three envelopes coexist:
  1. `{"data": …, "meta": {…, "server_time": "…Z", "api_version": "1.0"}}` — `BaseApiController::ok()` (Content, App, Epg, Recommendation GETs). `meta` also carries `version`, `total`, `limit`, `offset`, `platform` where relevant.
  2. `{"data": …}` with no `meta` — every `/auth/*` endpoint (`AuthController` calls `json()` directly).
  3. `{"success": true, …}` — all `/ads/*` and all `/analytics/*` endpoints.
- Errors: `{"error": {"code": "…", "message": "…"}}`. Codes seen: `UNAUTHORIZED` (401, middleware, plus `WWW-Authenticate: Bearer`), `TOKEN_EXPIRED` (401 refresh), `AUTH_FAILED`, `EMAIL_NOT_VERIFIED` (401 login, extra keys `needs_verification`, `email`), `VALIDATION_ERROR` (400/422), `NOT_FOUND` (404), `INVALID_PLATFORM` (400), `PAYMENT_REQUIRED` (402), `SUBSCRIPTION_ERROR` (422), `ACCOUNT_INACTIVE` (403), `INTERNAL_ERROR` (500, with `detail`/`file` when `APP_DEBUG=true`).
- `ETag` = `"<meta.version>"`; sending `If-None-Match` with the same value yields `304` with an empty body (`BaseApiController.php:24-35`). The manifest itself has no `meta.version`, so no ETag.
- `Cache-Control: public, max-age=30, stale-while-revalidate=60` on 200s; layout/navigation/pages/config use `max-age=300`; recommendations `private, no-cache`; auth responses `no-store`.

## 1. Auth (`src/Controllers/Api/AuthController.php`, `src/Services/SubscriberAuthService.php`, `src/Services/JwtService.php`)

| Method / path | Auth | Body / params | Success | Errors |
|---|---|---|---|---|
| `POST /auth/register` | – | `first_name, last_name, email, password (≥8), password_confirm, phone?, country?, birthday? (Y-m-d)` | 201 `{data:{requires_verification:true, message}}` | 422 `VALIDATION_ERROR` |
| `GET /auth/verify-email/{token}` | – | – | `{data:{message}}` | 400 `VERIFICATION_FAILED` |
| `POST /auth/resend-verification` | – | `email` | 200 generic message | 400 |
| `POST /auth/login` | – | `identity` (username **or** email), `password`, `device_name?`, `device_type?` ∈ `web|mobile|tv|stb` (default `web`) | 200 `{data:{access_token, refresh_token, expires_in:3600, token_type:"Bearer", user}}` | 400 `VALIDATION_ERROR`; 401 `AUTH_FAILED`; 401 `EMAIL_NOT_VERIFIED` + `needs_verification:true`, `email` |
| `POST /auth/refresh` | – | `refresh_token` | 200 `{data:{access_token, expires_in, token_type}}` (**no new refresh token**) | 401 `TOKEN_EXPIRED` |
| `POST /auth/logout` | – | `refresh_token?` | 200 `{data:{message:"Logged out"}}` always | – |
| `GET /auth/me` | Bearer | – | `{data: user}` | 403 `ACCOUNT_INACTIVE` |
| `POST /auth/update-profile` | Bearer | `adult_enabled?` (bool), `parental_pin?` (4 digits or null) | `{data:{success:true, message}}` | 422 |
| `GET /auth/entitlements` | Bearer | – | `{data:{movies:[ids], series:[ids], channels:[ids], categories:[ids], packages:[…], has_subscription, adult_enabled}}` | – |
| `POST /auth/subscribe` | Bearer | `package_id` | `{data:{success, status:"active"|"trial", message}}` | 402 `PAYMENT_REQUIRED` (paid package), 422 `SUBSCRIPTION_ERROR` |
| `POST /auth/unsubscribe` | Bearer | `package_id` | `{data:{success, message}}` | 422 |
| `POST /auth/forgot-password` **(added)** | – | `email` | 200 `{data:{message}}` always | 400 malformed email |
| `GET /auth/reset-password/{token}` **(added)** | – | – | `{data:{valid:bool}}` | – |
| `POST /auth/reset-password` **(added)** | – | `token, password, password_confirm` | `{data:{message}}` | 400 `INVALID_TOKEN`, 422 |
| `POST /auth/delete-account` **(added)** | Bearer | `password` | `{data:{deleted:true, message}}` | 403 `AUTH_FAILED`, 400 |

`user` object (`SubscriberAuthService::formatSubscriber()`, `:688-701`): `id, username, email, first_name, last_name, avatar, max_connections, parental_pin, adult_enabled`. No package or expiry information; call `/auth/entitlements`.

**JWT**: HS256, access TTL **3600 s**, claims `sub` (subscriber id), `type:"access"`, `username`, `email`, `iat`, `exp`, `iss:"cari-iptv"` (`JwtService.php:18-37`). Refresh token is an opaque 64-hex string, TTL **30 days**, stored SHA-256-hashed in `subscriber_tokens`. Refresh does **not** rotate (`SubscriberAuthService.php:287-301`). Logout revokes only the refresh token; the access token stays valid until `exp`. Expired and malformed access tokens both produce 401 `UNAUTHORIZED`.

**Device / connection limit**: `subscribers.max_connections` (default 1). At login, if the number of unrevoked, unexpired refresh tokens ≥ limit, the **oldest token is silently revoked** (`SubscriberAuthService.php:226-231`). No error is returned; the evicted device only learns at its next `/auth/refresh` (401 `TOKEN_EXPIRED`). There is no `device_id`; each login creates a new token row.

**Packages** (`entitlements.packages[]`): every active package with `id, name, slug, description, icon, color, price, currency, billing_period, trial_days, max_connections, geo_countries, platforms, features[], is_subscribed, is_adult, is_free, is_featured, price_display`. Subscription `expires_at` is never exposed.

## 2. Watch progress, continue watching, watchlist, ratings (Bearer)

| Method / path | Body / params | Response |
|---|---|---|
| `POST /auth/watch-progress` | `content_type` ∈ `movie|episode|channel`, `content_id`, `progress` (s), `duration` (s) | `{data:{saved:true}}`; server sets `completed` when `progress ≥ 0.9·duration` |
| `GET /auth/watch-progress?content_type=&content_id=` | – | `{data:{progress_seconds, duration_seconds, completed, last_watched_at}}` or `{data:null}` |
| `GET /auth/watch-progress/batch?content_type=&ids=1,2,3` | ≤100 ids | `{data:{"<id>":{content_id, progress_seconds, duration_seconds, completed}, …}}` (map) |
| `GET /auth/continue-watching?type=movie|series` | limit 20 | `{data:[…]}`; movie items carry `content_type:"movie", content_id, progress_seconds, duration_seconds, completed, last_watched_at, id, title, slug, year, poster_url, backdrop_url, runtime, vote_average, stream_url`; episode rows are folded into one item per series with `content_type:"series"`, `id`=series id, plus `resume_episode_id, resume_season_id, resume_season_number, resume_episode_number, resume_episode_title` |
| `POST /auth/watchlist/toggle` | `content_type` ∈ `movie|series|channel`, `content_id` | `{data:{in_watchlist:bool}}` (true = just added) |
| `GET /auth/watchlist` | – | `{data:[{id, subscriber_id, content_type, content_id, created_at}]}` — **raw rows, no titles/images** |
| `POST /auth/rate` | `content_type` ∈ `movie|series`, `content_id`, `rating` 1–5 | `{data:{community_rating, rating_count, user_rating}}` |
| `GET /auth/rating?content_type=&content_id=` | – | same shape; `user_rating` null if unrated |

Web player cadence: progress POST every 10 s of playback (`public/assets/js/player/app.js:4656-4688`), resume prompt from `progress_seconds`.

## 3. Content (Bearer) — `src/Controllers/Api/ContentController.php`, `src/Services/ContentApiService.php`

| Path | Params (defaults, caps) |
|---|---|
| `GET /manifest?platform=` | see §5 |
| `GET /channels` | `category_id, since, limit=500 (≤1000), offset` |
| `GET /channels/{id}` | – |
| `GET /movies` | `category_id, featured, genre (LIKE), year, sort ∈ latest|title|year|rating|popular, since, limit=50 (≤200), offset` |
| `GET /movies/featured` | fixed `featured=1, limit=20` |
| `GET /movies/{id}` | – |
| `GET /series` | `category_id, featured, genre, sort, since, limit=50 (≤200), offset` |
| `GET /series/{id}` | – |
| `GET /episodes/{id}` | – |
| `GET /categories` | `type ∈ live|vod|series, since` (no pagination) |
| `GET /person/{tmdbPersonId}` | – |
| `GET /search` | `q` (≥2 chars, else 400), `type ∈ all|movie|series|channel`, `limit=20 (≤50)` |
| `GET /drm/license` (GET/POST) | `content_id, server_id` — ClearKey JSON proxy |
| `GET /drm/key/{contentId}` | **public**, returns 16 raw bytes |

`meta.total` is a real count for `/channels`, `/movies`, `/series`; for `/search` and `/categories` it is just the returned row count.

### Item shapes
- **Channel (list)**: `id, name, slug, logo_url, stream_url, epg_channel_id, category_id, country, is_hd, is_adult, channel_number, sort_order, updated_at, category_name, is_restricted (bool), content_type:"channel"`.
- **Channel (detail)**: all columns + `now_playing`, `next_up` = `{title, description, start_time, end_time}` or null. Times are raw MySQL `YYYY-MM-DD HH:MM:SS` (no `Z`).
- **Movie (list)**: `id, title, slug, year, genres, runtime, vote_average, poster_url, backdrop_url, stream_url, is_featured, is_adult, category_id, updated_at, category_name, is_restricted, content_type:"movie"`.
- **Movie (detail)**: all columns + `category_name, is_restricted, content_type, drm, trailers[{id,title,video_key,url,is_primary}], artwork[{id,type,url,language,is_primary}], cast[] (SELECT *), markers[{id,marker_type,position_seconds,label}], subtitles[{id,language_code,language_name,file_path,format,is_default,is_forced}]`. Optional `rating_source:"community"`, `rating_count` when community ratings exist.
- **Series (list)**: `id, title, slug, year, genres, synopsis, vote_average, poster_url, backdrop_url, is_featured, is_adult, category_id, updated_at, season_count, episode_count, category_name, is_restricted, content_type:"series"`.
- **Series (detail)**: all columns + `seasons[]` (all `series_seasons` columns) each with `episodes[{id, title, episode_number, synopsis, runtime, stream_url, still_url, air_date, vote_average, vod_status}]`, plus `trailers[]`, `cast[]`. **No `drm`/`markers`/`subtitles` on embedded episodes.**
- **Episode**: `id, series_id, season_id, episode_number, title, synopsis, runtime, stream_url, still_url, air_date, vote_average, vod_server_id, vod_status, vod_content_id, series_title, series_poster_url, series_backdrop_url, season_number, season_name, content_type:"episode", drm, markers[], subtitles[], next_episode, prev_episode` (`{id, episode_number, title, stream_url, still_url, runtime}` + `season_number, season_name` when crossing seasons).
- **Category**: `id, name, slug, type, icon, sort_order, created_at`.
- **Search**: flat merged array; each row `{id, title, slug, image_url, content_type}` plus `year, genres, vote_average` for movie/series. Up to 3×limit rows for `type=all`.
- **Person**: `{name, profile_url, profile_image, tmdb_person_id, movies[], series[], epg[]}`.
- `markers[].marker_type` ∈ `intro_start | intro_end | credits_start | ad_cue`.

### Image URLs
Mixed and un-normalised: locally processed art is root-relative (`/uploads/vod/123/poster_poster.webp`, `ImageService.php:113`), TMDB art is absolute (`https://image.tmdb.org/t/p/...`, `MetadataService.php:18`). Client must prefix relative paths with the site origin. Subtitle `file_path` is likewise root-relative (`/uploads/.../*.vtt`), served as a static file without auth.

## 4. Playback and encryption

- **Live**: `channels.stream_url` is returned verbatim (`ContentApiService.php:304`). No proxy, no token. Whatever the upstream provider serves is what the app plays.
- **VOD**: `stream_url` is a persisted absolute URL `{vod_servers.public_url|url}/content/{contentId}/master.m3u8` (`VodServerController.php:675-680`, `VodServerService.php:355-364`). The VOD server serves `/content/*` with **no authentication** (`vod-server/src/web_routes.c:176-192`).
- **Encryption**: when DRM is enabled on the VOD server, FFmpeg writes standard HLS AES-128 `#EXT-X-KEY` tags (`vod-server/src/packager.c:224-236, 398-403`). The key URI is `{key_server_url}/api/v1/drm/key/{contentId}` (or `/api/drm/key/{contentId}` relative to the VOD server) (`vod-server/src/drm.c:637-647`). IV = key id.
- **Key endpoint auth**: `GET /api/v1/drm/key/{contentId}` is registered **without** middleware, deliberately (`public/api/index.php:141-143`), and the VOD-side `/api/drm/key/*` and `/api/drm/license` are also exempt from the API-key check (`vod-server/src/http_server.c:495-506`). So key fetches need **no header, no query token, no cookie**. Any HLS-capable player fetches keys natively.
- **ClearKey license**: `GET|POST /api/v1/drm/license?content_id=&server_id=` requires Bearer and returns `{"keys":[{"kty":"oct","kid":"…","k":"…"}],"type":"temporary"}`. Only used by EME/DASH players (the web Shaka player attaches Bearer via a request filter, `app.js:4233-4250`). Not needed for HLS.
- `drm` object on movie/episode detail: `null` or `{scheme:"cenc"|"cbcs", key_id, license_url}` (absolute, built from `general.site_url`).
- Web player: Shaka 4.16.17; manifest/segment requests carry no auth.

## 5. Server-driven UI (`src/Controllers/Api/AppController.php`, `ContentApiService.php:974-1275`)

- `{platform}` must be `web|mobile|tv|stb` else 400 `INVALID_PLATFORM`. **No fallback to another platform anywhere.**
- `GET /app/config/{platform}` → `{data:{navigation, pages:[…], layout}, meta:{platform}}`; any part may be `null`/`[]`, never 404.
- `GET /app/layout/{platform}?id=` → default published layout for the platform (`is_default=1 AND status='published'`), or by id (any status). 404 `NOT_FOUND` "No default published layout for platform: mobile" when none. `meta.version = md5(updated_at)`.
- `GET /app/navigation/{platform}?position=main` → `{id, platform, position, settings{style, show_icons, show_labels, max_items}, items[{id, label, icon, target ∈ page|url|deeplink, url, sort_order, page_slug, page_type, layout_id}]}`; 404 when missing.
- `GET /app/pages/{platform}` → `[{id, name, slug, page_type, icon, layout_id, is_system, sort_order}]`.
- Layout shape: `{id, name, platform, status, updated_at, sections[{id, section_type, title, settings{}, sort_order, is_active, items[{id, content_type, content_id, settings, sort_order, content{…}}]}]}`.
- `items[].content` by type: movie `{id,title,slug,year,genres,runtime,vote_average,poster_url,backdrop_url,stream_url,synopsis}`; series same minus runtime/stream_url; channel `{id,name,slug,logo_url,stream_url,is_hd}`; category `{id,name,slug,type,icon}`; custom `{title,image_url,link_url}`.
- **Only `content_row` and `channel_grid` with `settings.source ≠ curated` are auto-filled server-side** (`ContentApiService.php:1064-1184`; sources `latest|popular|top_rated|featured|category`, `content_type` `movie|series|mixed`, `max_items`, `category_id`). Auto items have `id:0` and `settings:[]`.
- All other section types arrive with `items: []` and must be filled by the client (web: `app.js:470-502`): `continue_watching` → `/auth/continue-watching`; `category_grid` → `/categories`; `recommended_for_you|because_you_watched|trending_now|top_picks|hidden_gems` → `/recommendations` set types `for_you|because_watched|trending|top_picks|hidden_gems`; `live_now`/`epg_schedule` → `/epg` (web player does not render these two); `hero_slideshow`, `spotlight`, `banner`, `text_divider`, `packages_list` use curated items or settings.
- Seed data (`database/migrations/011`, `018`): mobile **pages** `home, movies, series, live, categories, search, watchlist, settings, subscribe, profile` and a mobile **main navigation** with style `bottom_tab`, `max_items:5`. **No mobile layout is seeded.**
- `GET /manifest?platform=mobile` → `{data:{channels, movies, series, categories, epg: {version, count, updated_at}, layouts:{mobile:{version,count,updated_at}}, navigation:{mobile:{version, updated_at}}}}`. Version = `md5(count:max_updated)` (movies/series also include cast counts). A platform key is omitted when there is no data. Web player polls this every 30 s and busts caches on change (`app.js checkForUpdates`).
- **No branding endpoint**: `site_name`/`site_logo` are server-rendered only; there is no theme/colour setting.

## 6. EPG (`src/Controllers/Api/EpgController.php`, `ContentApiService::getEpg()`)

| Path | Params | Response |
|---|---|---|
| `GET /epg` | `channel_id?`, `date?` (`YYYY-MM-DD`), `limit=500 (≤2000)` | with `channel_id`: flat programme array; without: `[{channel_id, channel_name, programmes[…]}]`; `meta:{total, date, timezone:"UTC"}` |
| `GET /epg/{channelId}` | `date?` (limit fixed 200) | flat array |
| `GET /epg/programme-info` | `title` (required) | cached metadata or `{data:null, meta:{source:"none"}}` |

Programme: `{id, channel_id, title, description, start_time, end_time, category, channel_name}`. Times are emitted as `YYYY-MM-DDTHH:MM:SSZ` by string substitution of the DB value (`EpgController.php:123-131`); they are UTC only if MySQL's time zone is UTC. Window without `date`: from 3 h ago to 24 h ahead; with `date`: that calendar day. No dedicated now/next endpoint — derive from the guide, or use `now_playing`/`next_up` on `/channels/{id}`. Errors degrade to `data:[]` with `meta.message`.

## 7. Ads (public, no JWT) — `src/Controllers/Api/AdController.php`

| Path | Method | Params | Response |
|---|---|---|---|
| `/ads/serve` | GET | `zone_type ∈ pre_roll|mid_roll|banner|text_scroller`, `zone_slug?, channel_id?, content_type?, content_id?, category_id?, package_id?, platform?, user_id?, geo?, limit (≤10, default 3)` | `{success, source:"direct"|"waterfall_vast"|"fallback_vast", pod?, ads[], count}` or `{success, source, vast_url, ads:[], count:0}` |
| `/ads/impression` | POST form | `campaign_id*, creative_id*, placement_id, zone_id, user_id, session_id, platform, channel_id, content_type, content_id, revenue` | `{success:true, impression_id}` |
| `/ads/event` | POST form | `impression_id, campaign_id*, creative_id*, event_type*, user_id, session_id` | `{success:true, event_id}` |
| `/ads/breaks` | GET | `content_type=movie, content_id, duration` | `{success, breaks[{position, type:"pre_roll"|"mid_roll", label, source, marker_id?}], count}` |
| `/ads/overlay-settings` | GET | – | `{success, settings:{banner_enabled, banner_initial_delay, banner_display_duration, banner_repeat_interval, scroller_enabled, scroller_initial_delay, scroller_repeat_interval}}` |

Ad object: `id, campaign_id, placement_id, type, zone, scroll_text, scroll_speed, text_color, bg_color, bg_opacity, font_size, image_url, image_width, image_height, banner_position, click_url, click_target, video_url, vast_tag_url, video_duration, skip_after, companion_banner_url, midroll_offset_type, midroll_offset_value, alt_text, ab_variant_id` (+ `companions[]` for video). Event types used by the web player: `complete`, `skip`, `click`. There is no `/api/v1/ads/vast` (only `/admin/ads/api/vast`).

## 8. Recommendations and analytics (Bearer) — `src/Controllers/Api/RecommendationController.php`

- `GET /recommendations?type=movie|series` → `{data:[{id, set_type, title, reason, priority, items[{…movie/series columns…, content_type, match_percent, recommendation_reason}]}]}`. `set_type` ∈ `for_you, because_watched, trending, top_picks, hidden_gems, new_releases, evening_plan`. May generate the profile synchronously (slow first call).
- `GET /recommendations/profile` → taste profile row or `{data:null}`.
- `POST /analytics/event` `{event_type, content_type, content_id, page, metadata, session_id, platform}` → `{success:true}`; 422 for unknown `event_type`. Valid types (`AnalyticsService.php:16-54`): `watch_start, watch_complete, watch_abandon, watch_pause, watch_resume, watch_seek, watch_rewind, binge_session, episode_nav, page_view, page_exit, search, search_no_results, detail_view, detail_dismiss, card_hover, watchlist_add, watchlist_remove, rating, trailer_view, share, skip_intro, skip_credits, cc_toggle, language_select, fullscreen_enter, session_start, session_end`.
- `POST /analytics/batch` `{events[] (≤50), session_id, platform}` → `{success, recorded}`.
- `POST /analytics/qoe` `{events[{event_type ∈ startup|buffer_start|buffer_end|playback_error|quality_switch|quality_report, content_type, content_id, metadata}], session_id, platform}`.
- `POST /analytics/impressions` `{impressions[{content_type, content_id, section_type, section_id, position, was_clicked, dwell_ms}], session_id, platform}`.
- `POST /analytics/share` `{content_type, content_id, share_method, platform}`.

The `platform` field is free text on analytics and ads calls; the dev flavour sends `mobile-dev` so test traffic can be filtered.

## 9. Live verification

_To be filled in once the test account is verified (see PLAN.md task "Live API verification")._
