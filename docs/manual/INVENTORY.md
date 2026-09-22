# CARI-IPTV Manual: Phase 0 Inventory

Purpose: a map of what the platform is, what an admin can do with it, and what is missing, before the user manual is written. Everything below was read from the code, scripts and migrations in this repository at commit `2ce1a25` (version 1.0.0). Where existing docs (README.md, CLAUDE.md) disagree with the code, the code wins and the discrepancy is listed in section 9.

The manual will be a user manual: setup at a high level, then day-to-day admin work (channels, EPG, movies, series, packages, subscribers, layouts, ads) and the subscriber-facing player. Deep technical internals will be left out or moved to an appendix.

---

## 1. Components found in the repository

| Component | What it is | Where in the repo | Status |
|---|---|---|---|
| Middleware and admin panel | PHP 8 web app: admin control panel, subscriber accounts, content API | `public/`, `src/`, `templates/`, `database/migrations/` | Present, main product |
| Web player | Browser app for subscribers (desktop, tablet, phone, smart-TV browser) | `templates/player/`, `public/assets/js/player/`, `public/assets/css/player.css` | Present |
| VOD server | Separate C daemon that transcodes uploaded video into adaptive HLS/DASH and serves it | `vod-server/` | Present, own installer |
| EPG fetcher | Scheduled script that pulls programme guides (XMLTV or DVB EIT) | `scripts/epg-fetch.php` | Present, cron installed by installer |
| Recommendation job | Nightly script that builds AI taste profiles and recommendation rows | `cron/generate-recommendations.php` | Present |
| Installer / updater | Shell scripts for the PHP platform and for the VOD server | `install.sh`, `update.sh`, `vod-server/scripts/` | Present |
| Headend / RIST | Not in this repository. Zero references to RIST, SRT, multicast ingest or "headend" | none | **Missing, see section 10** |
| Flutter mobile app | Not in this repository. No Flutter, Android, iOS, APK, or app-store material. README lists "Android/iOS Apps" as a future phase | none | **Missing, see section 10** |
| GitHub Actions | No `.github/` directory. No CI, no build pipelines | none | **Missing, see section 10** |
| TSDuck | Third-party tool installed by the installer, used only to read EIT programme guides from DVB transport streams | `packages/README.md`, `install.sh` | Optional helper |

### How they connect

```
Subscriber browser ──HTTPS──▶ Nginx + PHP (port 80 by default)
                               ├─ /            web player
                               ├─ /admin       admin control panel
                               └─ /api/v1      content and account API (JWT)
                                      │
                                      ├─ MySQL (local)
                                      ├─ VOD server API (port 8090, X-API-Key)  ──▶ transcoded HLS at /content/<id>/master.m3u8
                                      ├─ TMDB, Fanart.tv, YouTube, OpenSubtitles, IPTV-org (internet, API keys in Settings)
                                      ├─ Ollama (local, port 11434) or OpenAI / Anthropic (AI features)
                                      └─ SMTP server (verification emails, admin password resets)

Live channels: the platform stores a stream URL per channel. It does not ingest, transcode or relay live video.
EPG: cron every 5 min ──▶ scripts/epg-fetch.php ──▶ XMLTV URL / uploaded file / DVB EIT via TSDuck
```

### Ports and services

| Port | Service | Set by |
|---|---|---|
| 80 | Nginx, PHP platform (admin, player, API). HTTP only, no TLS configured by installer | `install.sh --port=` |
| 8090 | VOD server API and its own web GUI | `/etc/vod-server/vod-server.conf` |
| 443 / 80 | VOD server HTTPS and Let's Encrypt challenge, only if enabled in its config | `vod-server.conf` `[ssl]`, `[acme]` |
| 3306 | MySQL, local only | installer |
| 11434 | Ollama local AI, local only | installer |

The main installer opens no firewall ports and installs no TLS certificate. The VOD server installer does install certbot and documents an HTTPS procedure.

---

## 2. Installation and update scripts (operator view)

| Script | Purpose | Key facts |
|---|---|---|
| `install.sh` | Fresh install of the PHP platform | Ubuntu/Debian/RHEL family, run as root. Installs Nginx, PHP 8.2-FPM, MySQL, Ollama (downloads about 9 GB of AI models), TSDuck. Non-interactive. Flags: `--db-pass`, `--admin-pass`, `--admin-email`, `--install-dir`, `--port`. Writes `/var/www/cari-iptv/INSTALL_CREDENTIALS.txt` and `.env`. Creates admin user `admin` (super_admin). Installs demo packages, channels and a demo movie. Writes cron file `/etc/cron.d/cari-iptv`. |
| `update.sh` | Update an existing install | Flags: `--backup` (strongly needed, rollback is impossible without it), `--backup-dir`, `--branch`, `--install-dir`. Keeps last 5 backups. Overwrites `public/`, `src/`, `templates/`; preserves `.env`. Runs new migrations. Re-writes the cron file. "Maintenance mode" writes a page but nothing serves it. |
| `vod-server/scripts/install.sh` | Fresh install of the VOD server | Builds FFmpeg 8.0.1 from source (slow), installs MP4Box, builds the daemon, creates user and systemd service, generates and prints the API key once, installs certbot. Config at `/etc/vod-server/vod-server.conf` is never overwritten. |
| `vod-server/scripts/update.sh` | Update the VOD server | Pauses jobs, rebuilds, health-checks, auto-rolls back to `vod-server.bak` on failure. Config and library untouched. |

Branch pins: all four scripts are hard-coded to branch `claude/fix-opensubtitle-connection-TvyOH` (`install.sh:1377`, `update.sh:19`, `vod-server/scripts/install.sh:215`, `vod-server/scripts/update.sh:10`). The README one-liners point at `main`.

Cron jobs installed (identical in install and update):

```
*/5 * * * *  epg-fetch.php                       (fetch EPG sources that are due)
0 2  * * *   epg-fetch.php --cleanup --cleanup-days=2
0 3  * * *   generate-recommendations.php
```

Logs: `storage/logs/php-error.log`, `storage/logs/epg.log`, `storage/logs/recommendations.log`, `/var/log/nginx/cari-iptv-*.log`, `/var/log/vod-server/vod-server.log`. No log rotation is configured for `storage/logs`.

---

## 3. Admin panel: every page and its purpose

Sidebar as rendered (`templates/layouts/admin.php`). All items show to every admin role; nothing is hidden by role.

| Menu | Page | URL | What it is for |
|---|---|---|---|
| MAIN | Dashboard | `/admin` | Customisable panels: counts of channels, movies, shows, subscribers, packages, disk, memory, content chart, recent admin activity |
| | Analytics | `/admin/analytics` | KPIs, viewing charts, top content, churn-risk table, AI business report and AI chat (needs AI configured) |
| CONTENT | Channels | `/admin/channels` | Live TV channel list, add/edit, IPTV-org import, bulk activate/deactivate/delete |
| | Movies | `/admin/movies` | Movie library, TMDB import, free-content browser, trailers, subtitles, VOD transcode, content markers |
| | TV Shows | `/admin/series` | Series, seasons, episodes; TMDB import; per-episode stream URL, subtitles, VOD transcode, markers |
| | Categories | `/admin/categories` | Genres for live, movies and series; one level of nesting; drag ordering |
| | EPG | `/admin/epg` | EPG sources (XMLTV URL, XMLTV file, DVB EIT), channel mapping, auto-map, fetch now, programme guide viewer, TMDB metadata enrichment |
| | App Layout | `/admin/app-layout` | Home-screen layouts per platform (Web, Mobile, Smart TV, STB), visual builder with 17 section types, publish/draft |
| | Pages & Nav | `/admin/app-layout/pages` | Which pages exist per platform and the navigation menu items |
| ADVERTISING | Campaigns | `/admin/ads` | Ad campaigns with creatives (text scroller, banner, pre-roll, mid-roll), placements and targeting rules |
| | Ad Zones | `/admin/ads/zones` | Where ads may appear; floor CPM; fallback VAST |
| | Ad Reports | `/admin/ads/reports` | Impressions, clicks, CTR, spend per campaign |
| | Ad Pods | `/admin/ads/pods` | Multi-ad commercial breaks |
| | Waterfall | `/admin/ads/waterfall` | Fallback chains when a zone is unfilled |
| | A/B Tests | `/admin/ads/ab-tests` | Lists tests; **no way to create one from the UI** |
| | Forecast | `/admin/ads/forecast` | AI revenue forecast |
| SUBSCRIBERS | Subscribers | `/admin/subscribers` | Customer accounts: create, edit, status, packages, groups, password set, parental PIN, adult toggle |
| | Packages | `/admin/packages` | Subscription plans: price, currency, billing period, trial, limits, content groups |
| | Content Groups | `/admin/packages?tab=groups` | Bundles of channels/movies/series/categories that packages grant |
| SYSTEM | Activity Log | `/admin/activity` | **Dead link: no route exists (404).** Activity is only visible on the Dashboard panel |
| User menu | My Profile | `/admin/profile` | Own name, email, avatar, password |
| | Admin Users | `/admin/admins` | Staff accounts, roles, reset password, force password change |
| | Settings | `/admin/settings` | Six tabs: General, Email, Integrations, AI, Images, Advertising |

Not reachable: `templates/admin/vod-server/index.php` is a complete VOD Server page (dashboard, transcode, jobs, content library, server manager with Public URL) but `/admin/vod-server` only redirects to Settings > Integrations. VOD servers are managed from the small card on that tab; transcodes are started from the movie form and the episode VOD modal.

Login facts: username or email; 5 failed attempts lock the account for 15 minutes; "Keep me logged in" lasts 30 days; admin forgot-password by email exists (needs SMTP).

Roles: viewer, support, manager, admin, super_admin. **Permissions are not enforced anywhere.** Every active admin can open every page and perform every action. The permission checkboxes on the Admin User form are saved but never read.

---

## 4. Settings (all six tabs)

| Tab | Settings | Notes |
|---|---|---|
| General | Site Name (default CARI-IPTV), Site Logo (PNG/SVG/JPEG/GIF/WebP, 1 MB), Site URL, Admin Email | Site Name and Logo are the **only** player branding controls. Site URL is used in verification emails and stream links. Colours are not configurable |
| Email | Enable SMTP, Host, Port (587), Encryption (TLS/SSL/None), Username, Password, From Email, From Name; Send Test Email | Required for subscriber email verification and admin password reset. **Without SMTP, self-registered subscribers can never log in** (no error is shown) |
| Integrations | VOD Servers (Name, Server URL, API Key, Default, Active, Notes, Test Connection); Fanart.tv API key; TMDB API key + Auto-fetch; YouTube API key; OpenSubtitles API key, username, password, preferred languages, auto-fetch | The VOD server modal here has **no Public URL field**, and saving it clears any Public URL. Public URL is therefore unsettable from the UI today |
| AI | Enable AI, Provider (Ollama local / OpenAI / Anthropic), Ollama URL and model, OpenAI key and model, Anthropic key and model, Test | Used by channel descriptions, ad copy, DALL-E banner images, analytics report and chat, recommendations |
| Images | Auto-optimize, Keep originals, WebP quality (85) | |
| Advertising | Banner overlay initial delay 30 s, display 15 s, repeat 300 s, enabled; text scroller initial delay 15 s, repeat 300 s, enabled | Overlay ad timing in the player |

Settings seeded by the installer but with no screen: timezone (America/Jamaica), default language, platform name/logo/support email, security max login attempts and session timeout, registration enabled. The security values are ignored at runtime; real values live in `src/Config/app.php`.

---

## 5. Content workflows (as they actually work)

### Channels
- Required: Name and Stream URL. Everything else optional. Stream URL is not validated; HLS `.m3u8` is the first-class format.
- Logo: upload, paste a URL, or Search Logos (Fanart.tv). Converted to WebP.
- Bulk onboarding: **Import from IPTV-org** only (search by country/category, pick channels, import). **There is no M3U playlist import.**
- Visibility: only **Active** matters. The **Published** checkbox is cosmetic; the player ignores it. Backup Stream URL is stored but never used.
- Age limit, OS platforms, Available Without Purchase, Show to Demo Users, Content Owner, Streaming Server are stored as labels only; nothing acts on them.
- The **Include in Packages** checkboxes on the channel form write to a table that entitlement checks never read. Use Content Groups instead.
- Adult flag exists in the database but has no control on any form.

### Categories
- Types: Live TV, Movies, TV Shows. Type cannot be changed after creation. One level of parent/child. Delete refuses if in use or has children. TMDB import auto-creates movie/series genres.

### EPG
- Source types: XMLTV URL (gzip ok), XMLTV file upload (manual only, cron skips it), DVB EIT from a multicast/stream address (needs TSDuck).
- Per source: Active, Auto Refresh, interval (30 min to 24 h), Source Timezone (all times stored as UTC).
- Mapping: every EPG channel discovered is listed unmapped. Programmes for unmapped channels are discarded, so first fetch imports nothing until mapped. Map manually or **Auto-Map** (exact name, then first partial match: review results).
- XMLTV re-import wipes that source's history; EIT keeps the last 7 days.
- After each import, titles are queued for TMDB enrichment (artwork, synopsis, cast) if a TMDB key is set.

### Movies
- Only Title required. Normal path: Search TMDB on the form, click a result: fills metadata, cast (15 actors + directors), poster/backdrop (WebP), trailers, genre categories. Imports arrive as **Draft**; must be set to **Published** to appear.
- Browse Free Content: Internet Archive and YouTube Creative Commons.
- Artwork: Fanart.tv picker (posters, backdrops, clear logos). Trailers: YouTube search or TMDB.
- Subtitles: upload (.srt/.vtt/.ass, 5 MB, converted to VTT), OpenSubtitles search/download (needs username + password), extract from source file (text tracks only).
- Playback source: paste a Stream URL, **or** use the VOD Transcode card (pick server, profile, upload file). Transcode completion **overwrites** the Stream URL with the VOD server's HLS link.
- Content Markers: Intro Start, Intro End, Credits Start, Ad Cue, set on a scrub bar. Used by the player (skip intro, next episode) and by mid-roll ads.
- Fields with no form control: adult flag, writers, country, release date, production companies.

### TV Shows
- Same as movies at show level, then Seasons page (Import Seasons from TMDB, Add Season) and Episodes page (per-episode stream URL, subtitles, VOD & Markers modal). Season and show trailers.

### VOD server (what the admin touches)
- In Settings > Integrations: Name, Server URL (internal, e.g. `http://<ip>:8090`), API Key (from `/etc/vod-server/vod-server.conf`), Test Connection.
- Transcode profiles are created and edited on the **VOD server's own web GUI** (port 8090), not in the admin panel. Shipped profiles: standard (H.264 360p to 1080p, default), high (HEVC), low (H.264 up to 720p), hevc_4k, av1.
- Job states: pending, downloading, processing, packaging, complete, failed, paused, cancelled. Shown on the movie form as Ready / Processing / Failed.
- Encoding facts for the manual's "encoding requirements" section: the software path forces 8-bit `yuv420p`; H.264 profile is libx264's default (High) and is not set explicitly; there is **no HDR tone-mapping, no 10-bit handling, no aspect-ratio preservation and no upscale guard**. HDR or 10-bit masters and non-16:9 sources must be pre-converted before upload. Hardware encoders (NVENC/VAAPI) do not force a pixel format at all.
- The VOD server web GUI has: Dashboard, Content, Jobs, Uploads, Cluster, Profiles, DRM, Settings.

### Packages and entitlements
- Package fields: name, colour, description, free flag, price, currency (21, Caribbean-focused), billing period, tax rate and inclusive flag, trial days, simultaneous streams, video quality, catch-up days, DVR hours, ad-free, content groups, platforms, countries, active, featured, sort order.
- Content is granted **only** via Content Groups (bundles of channels, movies, series, categories) linked to packages.
- Fresh install: no packages, four empty content groups. Until at least one package exists, the player shows everything as locked.
- **Server-side enforcement: none.** The API returns the stream URL for every active channel and every published movie to any logged-in subscriber. The padlock is drawn by the player only. Adult filtering is also client-side only. Geo, platform, stream-count, tax and quality limits on packages are stored but not enforced.
- No payment processor. Paid packages are granted by staff on the subscriber form. Subscriptions never auto-expire; the expiry date is only respected by entitlement queries.

### App Layout and Pages
- Layouts per platform (Web, Mobile, Smart TV, STB), status Draft / Published / Archived, one default per platform. Builder: drag sections, 17 section types (hero slideshow, content row, live now, TV guide, promo banner, category grid, channel grid, continue watching, spotlight, divider, packages list, recommended for you, because you watched, trending now, top picks, hidden gems).
- Content items: from library, from TMDB (imports as draft), or uploaded image with link.
- Pages & Navigation: per platform, page list (13 page types) with optional layout, and navigation items (page, URL, deep link) with icon picker.
- The web player reads Web layouts and navigation at runtime. Mobile/TV/STB layouts are served by the API but no such client exists in this repo.

### Advertising
- Campaign > Ads (creatives) > Placements (ad × zone × targeting rules: package, channel, category, content type, platform, schedule).
- Ad types: Text Scroller (with AI copy, speed, font size, colours), Banner (upload, URL or DALL-E 3), Pre-roll and Mid-roll video (upload, URL or VAST tag; mid-roll by percentage, seconds or ad cue marker).
- Zones (10 seeded), Pods, Waterfall chains, Forecast, Reports. A/B Tests cannot be created from the UI.
- Ad-serving endpoints are public (no login), so impression counts can be inflated externally.

---

## 6. Subscribers and the player

### Registration and login
- Sign-up page `/register`: First Name, Last Name, Email, Phone, Date of Birth, Country, Password (min 8), Confirm. Username is generated from the email.
- Email verification link is sent by SMTP. Token never expires. No email configured means no verification and no login; staff must tick "Email Verified" on the subscriber.
- Login `/login` with username or email. Stay signed in up to 30 days of inactivity.
- **No subscriber password reset.** No "Forgot password" link on the player login. Staff set a new password in Admin > Subscribers and tell the customer.
- Device limit: per-subscriber "Max Concurrent Connections" (default 1). Package "Simultaneous Streams" is not consulted. Enforcement is soft: the oldest device is silently signed out. **No device list and no revoke button** anywhere in admin.
- Statuses active / inactive / suspended / expired plus Disabled: all non-active states block login identically. A suspended viewer can keep watching for up to 1 hour.
- Account deletion: admin only, hard delete, irreversible. No self-service deletion.
- Parental PIN: 4 digits, stored in plain text, visible and editable by staff. Adult content toggle on subscriber profile page and on the admin form.

### Player screens
`/` Home (layout-driven), `/movies`, `/series`, `/series/:id`, `/live` (with EPG grid and programme reminders), `/search`, `/my-list`, `/categories`, `/person/:id`, `/subscribe` (free and trial self-service; paid returns "payment required"), `/profile` (account, subscriptions with Cancel, parental controls, sign out), `/watch/:type/:id`. There is no separate Settings screen.

Player engine: Shaka Player 4.16.17 from the jsDelivr CDN (HLS and DASH). Google Fonts also from CDN. Subtitles, ratings (1 to 5 stars, community average), continue watching (90% = completed, resume prompt), skip intro / next episode via markers, ads (pre/mid/post-roll, pods, banners, scrollers, VAST), recommendations (nightly, up to 100 subscribers per run). Sidebar on desktop, bottom tab bar on phones, top bar if the navigation style is set to top_bar.

Branding: Site Name and Site Logo only. Colours are hard-coded in `public/assets/css/player.css` and any edit is lost on update.

---

## 7. Public API summary (for the appendix)

Base `/api/v1`. JSON. Bearer JWT (1 h access, 30 day refresh).

- Public: `POST auth/register, auth/login, auth/refresh, auth/logout, auth/resend-verification`, `GET auth/verify-email/{token}`, all `ads/*`, `GET drm/key/{contentId}`.
- Token required: `auth/me`, watch-progress, continue-watching, watchlist, entitlements, subscribe/unsubscribe, update-profile, rate; `manifest`, `channels`, `movies`, `series`, `episodes`, `categories`, `person`, `search`; `epg`; `app/config|layout|navigation|pages/{platform}`; `drm/license`; `analytics/*`; `recommendations`.
- Security notes for section 9 of the manual: no rate limiting; access tokens are not revoked on suspension until expiry; DRM raw key endpoint is deliberately public (HLS cannot send headers), so DRM protects transport and disk but is not access control; entitlements are not enforced server-side.

---

## 8. Screenshots

The container has Chromium and Playwright but no MySQL. To capture real admin screenshots I would need to install MariaDB or MySQL locally, load the schema from `install.sh` plus the 35 migrations, create an admin user, and serve with `php -S`. This is feasible. Otherwise every page will get a precise description and a `[SCREENSHOT: page]` placeholder. See question 5.

---

## 9. Code vs docs discrepancies (for the appendix)

| Doc says | Code does |
|---|---|
| README: install from `main` | Scripts pin branch `claude/fix-opensubtitle-connection-TvyOH` |
| CLAUDE.md: `install.sh` branch at line ~1357 | Now line 1377 |
| CLAUDE.md: `/admin/vod-server` is the VOD Server page; VOD profile create/update/delete routes proxied to the server | `/admin/vod-server` redirects to Settings; profiles are read-only in the admin (managed on the VOD GUI) |
| CLAUDE.md: 10 section types | 17 section types |
| CLAUDE.md: ad serving at `/admin/ads/api/*` | Player uses public `/api/v1/ads/*` (admin routes still exist) |
| CLAUDE.md: roles gate access | Permissions are never enforced |
| CLAUDE.md and README: packages control access | Client-side padlock only |
| Migration 024 comment: "seed default VOD server settings" | Creates the table only |
| Migration 004 seeds `metadata.fanart_api_key`, `auto_fetch_logos`, `image.generate_sizes` | UI reads `fanart_tv_api_key`, `auto_optimize`; the seeded keys are dead |
| Sidebar: Activity Log | No route (404) |
| Channel form: "Published: channel is visible to users" | Player ignores Published; only Active matters |
| Channel form: Include in Packages | Not read by entitlements; Content Groups are |
| Migration 019 name: "seed default packages" | Seeds four empty content groups, no packages |
| Player login wording "Invalid or expired verification link" | Tokens never expire |
| Subscriber form "Max Concurrent Connections" vs package "Simultaneous Streams" | Only the subscriber value is enforced, softly |

---

## 10. Things I cannot determine from the repo (please answer)

1. **Headend / RIST.** Nothing in this repo. Is there a separate headend product (Caricoder2 is referenced only as a download mirror for TSDuck packages)? Should the manual cover it, and if so from what source? Or should the manual say "live sources are external stream URLs supplied by your headend"?
2. **Flutter mobile app.** Not in this repo and no build workflows exist. Is it in another repository? If yes, can you give me access or the repo name so I can document branding, building, signing and store submission from its code? If not, section 7 of the manual will state that the platform currently ships a responsive web player only and the mobile chapter will be a placeholder.
3. **GitHub Actions test builds.** None exist here. Same question as above: are they in the app repo?
4. **Branch to document.** The scripts pin `claude/fix-opensubtitle-connection-TvyOH`. Should the manual's install commands use `main`, that branch, or a placeholder `<BRANCH>` with a note?
5. **Screenshots.** May I install MariaDB (about 200 MB) in this container to run the admin panel and capture real screenshots? If not, I will use described placeholders.
6. **PDF tooling.** May I install pandoc via apt (and a PDF engine, either LibreOffice which is already present, or wkhtmltopdf)? Plan: Markdown to DOCX with pandoc, DOCX to PDF with LibreOffice, so you get an editable .docx and a PDF with cover, TOC and page numbers.
7. **Operator brand placeholders.** I will use `<OPERATOR>`, `<ADMIN_DOMAIN>`, `<PLAYER_DOMAIN>`, `<VOD_DOMAIN>`, `<ADMIN_PASSWORD>`, `<VOD_API_KEY>`. Any others you want?
8. **Known gaps in the manual.** Several findings above are product gaps (no subscriber password reset, no device management, entitlements not enforced, Public URL unsettable, dead Activity Log link, permissions decorative). Do you want these stated plainly in the manual as "current limitations", or kept in an internal appendix only?
9. **Depth on advertising.** The ad system is large (campaigns, zones, pods, waterfall, A/B, forecast). For a user manual, should I cover the basic path fully (campaign, ad, placement, zone, report) and only summarise pods/waterfall/forecast?
