# Backend changes made for the mobile app

All changes follow the existing MVC/routing/migration patterns. Nothing has been deployed.
Commits are prefixed `backend:` and are separate from `/app` commits.

## 1. Subscriber password reset

| Item | Detail |
|---|---|
| Migration | `database/migrations/036_add_subscriber_password_resets_and_account_deletion.sql` — creates `subscriber_password_resets` (hashed token, 1-hour expiry, single use, cascade on subscriber delete) and adds `subscribers.deleted_at` (idempotent) |
| Service | `SubscriberAuthService::requestPasswordReset()`, `isPasswordResetTokenValid()`, `resetPassword()` |
| API | `POST /api/v1/auth/forgot-password` `{email}` → always `200 {data:{message}}` (no enumeration; `400 VALIDATION_ERROR` only for a malformed email) |
| API | `GET /api/v1/auth/reset-password/{token}` → `{data:{valid:bool}}` |
| API | `POST /api/v1/auth/reset-password` `{token,password,password_confirm}` → `200 {data:{message}}`; `400 INVALID_TOKEN`; `422 VALIDATION_ERROR` |
| Web page | `GET /reset-password/{token}` (`templates/player/reset-password.php`) — target of the emailed link; calls the two API endpoints above |
| Web page | `GET /forgot-password` (`templates/player/forgot-password.php`) — requests the reset email; linked from the web login page ("Forgot password?" next to "Remember me") |
| Email | Reuses `EmailService::sendPasswordReset()` (existing `password-reset` template). Link is `{general.site_url}/reset-password/{token}`. If SMTP is not configured the request still returns 200 and the failure is written to the PHP error log. |
| Behaviour | A successful reset revokes every refresh token for the subscriber (all devices are signed out). Previous unused tokens are invalidated when a new one is requested. |

## 2. Account deletion

| Item | Detail |
|---|---|
| Service | `SubscriberAuthService::deleteAccount(int $subscriberId, string $password)` |
| API | `POST /api/v1/auth/delete-account` (Bearer) `{password}` → `200 {data:{deleted:true,message}}`; `403 AUTH_FAILED` wrong password; `400 VALIDATION_ERROR` missing password |
| Web page | `GET /delete-account` (`templates/player/delete-account.php`) — public page for users without the app: signs in via `/auth/login`, then calls `/auth/delete-account` with the same password. This is the URL to enter in Play Console → Data safety → "Account deletion URL". |

### What is deleted (hard delete, by `subscriber_id`)
`subscriber_tokens`, `subscriber_password_resets`, `subscriber_watch_history`, `subscriber_watchlist`, `subscriber_ratings`, `subscriber_events`, `subscriber_profiles`, `recommendation_sets` (+ `recommendation_items` via FK cascade), `subscriber_qoe_events`, `subscriber_sessions`, `content_impressions`, `subscriber_engagement_scores`, `binge_sessions`, `content_shares`, `subscriber_group_members`. Tables that are missing on an installation (optional migrations) are skipped.

### What is anonymised (row kept, personal fields cleared)
- `subscribers`: `username` → `deleted_{id}`; `email`, `password`, `first_name`, `last_name`, `phone`, `avatar`, `birthday`, `country`, `city`, `address`, `zip_code`, `external_id`, `notes`, `parental_pin`, `email_verification_token` → NULL; `email_verified=0`, `status='inactive'`, `is_disabled=1`, `deleted_at=NOW()`. The login query requires `is_disabled=0`, so the account can never sign in again.
- `ad_impressions`, `ad_events`, `ad_conversions`: `user_id` → NULL.

### What is retained and why
- **The anonymised `subscribers` row** — keeps the primary key so historical aggregate counts (dashboard totals, retention cohorts) and advertiser reporting rows remain referentially valid. It holds no personal data.
- **`subscriber_subscriptions` rows** — billing/entitlement history for accounting and dispute resolution. They reference only the anonymised id; `payment_reference` is an external processor id, not personal data. If your retention policy requires it, purge rows older than N years with a scheduled job (not included).
- **Aggregate/statistical tables** without a per-user key (`trending_content`, `retention_cohorts` counts) are untouched. `movies.community_rating` / `series.community_rating` are recalculated for every item the subscriber had rated.

## 3. Public pages

| Route | Template | Purpose |
|---|---|---|
| `GET /forgot-password` | `templates/player/forgot-password.php` | Request a password reset email |
| `GET /privacy` (alias `/privacy-policy`) | `templates/player/privacy.php` | Privacy policy with **placeholder** text marked `[LIKE THIS]`; includes a `#deletion` section and a `#terms` section. The app links to `/privacy` and `/privacy#terms`. |
| `GET /delete-account` | `templates/player/delete-account.php` | Account deletion without the app (see above) |
| `GET /reset-password/{token}` | `templates/player/reset-password.php` | Password reset link target |

Routes are registered in `public/index.php` (web pages) and `public/api/index.php` (API). Controller methods: `PlayerController::resetPassword()`, `deleteAccount()`, `privacy()`; `Api\AuthController::forgotPassword()`, `checkResetToken()`, `resetPassword()`, `deleteAccount()`.

## Files changed
```
database/migrations/036_add_subscriber_password_resets_and_account_deletion.sql   (new)
src/Services/SubscriberAuthService.php                                              (methods added)
src/Controllers/Api/AuthController.php                                              (endpoints added)
src/Controllers/Player/PlayerController.php                                         (page methods added)
public/api/index.php                                                                (4 routes)
public/index.php                                                                    (3 routes)
templates/player/reset-password.php  templates/player/delete-account.php  templates/player/privacy.php  templates/player/forgot-password.php  (new)
templates/player/login.php                                                          ("Forgot password?" link)
```

## Deployment steps (not performed)
1. Merge the `backend:` commits to the branch your `update.sh` pulls from (or set `BRANCH=` in `update.sh`/`install.sh` per `CLAUDE.md`).
2. Run the update script as usual; it copies `src/`, `public/`, `templates/`, `database/migrations/` and applies pending migrations from the `_migrations` table. Migration `036` is idempotent and safe to re-run.
3. Confirm SMTP is configured in Admin → Settings → Email, otherwise reset emails are logged instead of sent.
4. Confirm `general.site_url` in Admin → Settings is the public HTTPS URL (used in the reset link).
5. Smoke test: `POST /api/v1/auth/forgot-password`, open the emailed `/reset-password/{token}` page, sign in with the new password; open `/privacy` and `/delete-account`.
6. Replace the placeholder text in `templates/player/privacy.php`.

## Not changed (needs your decision)
- `install.sh` / `update.sh` `BRANCH=` values were not touched (CLAUDE.md says to update them before pushing; you asked not to deploy).
- Subscription and billing retention period is not enforced by code.

## 4. VOD server: force 8-bit H.264 High output (`vod:` commit)

**Why.** A 10-bit source (e.g. Black Sails S1E3) was packaged as H.264 **High 10** (`avc1.6E0028`): phone hardware decoders reject it (`MediaCodecVideoRenderer error … format_supported=NO_UNSUPPORTED_TYPE`). In `vod-server/src/transcoder.c` the `-pix_fmt yuv420p` guard only applied when `hwaccel` was `none`; `-profile:v`/`-level:v` were never set; the GUI offered `qsv`, which the transcoder did not implement, so that setting silently meant "software libx264 with no 8-bit guard". The HLS packaging step is `-c copy`, so whatever the encoder produced went straight to players.

**What changed (`vod-server/src/transcoder.c`, `transcoder.h`).**

| # | Change |
|---|---|
| 1 | `-pix_fmt yuv420p` on every H.264/HEVC/AV1 rendition for software and NVENC; the VAAPI path already converts to `nv12` (8-bit) in the filter graph |
| 2 | H.264: `-profile:v high` plus a per-rendition `-level:v` (level_idc): 3.0 ≤ 360p, 3.1 ≤ 480p and 720p ≤ 30 fps, 3.2 720p > 30 fps, 4.0 1080p ≤ 30 fps, 4.2 1080p > 30 fps, 5.1 / 5.2 for 2160p (fps from the source probe) |
| 3 | HEVC profiles (`libx265`, `hevc_nvenc`, `hevc_vaapi`): `-profile:v main -tag:v hvc1` (8-bit Main, never Main 10). Level left automatic |
| 4 | `hwaccel` values other than `nvenc` / `vaapi` / `none` (including `qsv`) encode in software **with** the guards, and log a warning at start-up and per job |
| 5 | The source probe now records `pix_fmt`, bit depth (`bits_per_raw_sample`, else derived from the pix_fmt name) and profile; the job log prints them and warns for >8-bit sources; each job logs its "Encode policy" line |

Profiles in `vod-server.conf` need no change. Nothing has been deployed.

**Build and deploy (VOD server, as root).** Since section 6 both VOD scripts pull from `claude/intelligent-knuth-xzkkd7`, so `update.sh` picks this up. Manual build from this branch:

```bash
# 1. build
git clone --depth 1 --branch claude/intelligent-knuth-xzkkd7 https://github.com/caritechsolutions/cari-iptv.git /tmp/cari-src
cd /tmp/cari-src/vod-server && mkdir -p build && cd build
cmake .. -DCMAKE_BUILD_TYPE=Release && make -j"$(nproc)"
./vod-server --version

# 2. swap the binary (keeps a rollback copy), restart
systemctl stop vod-server
cp /usr/local/bin/vod-server /usr/local/bin/vod-server.bak
install -m 0755 ./vod-server /usr/local/bin/vod-server
systemctl start vod-server
journalctl -u vod-server -n 30 --no-pager      # expect "Transcoder initialized: ... hwaccel=<value> (effective: ...)"

# 3. verify with one job, then inspect the output
#    submit any title (admin → VOD section → Upload & Transcode, or POST /api/jobs);
#    the job log shows "Probed ... (High 10, yuv420p10le, 10-bit)" and "Encode policy: ... 8-bit yuv420p H.264 High"
ffprobe -v error -select_streams v:0 -show_entries stream=codec_name,profile,pix_fmt,level \
        -of default=noprint_wrappers=1 /var/lib/vod-server/library/<content_id>/1080p.mp4
#    expected: codec_name=h264  profile=High  pix_fmt=yuv420p  level=40

# rollback if needed
systemctl stop vod-server && cp /usr/local/bin/vod-server.bak /usr/local/bin/vod-server && systemctl start vod-server
```

Also set **Settings → Hardware acceleration** in the VOD GUI to a value the server really has (`none`, `nvenc`, `vaapi`); `qsv` now falls back to software with a warning instead of silently.

**Re-package procedure (existing titles).**

1. Find affected titles (read-only; the script parses `ffprobe` key=value output and probes the rendition MP4s the packager copied from, falling back to `stream_*.m3u8`):
   ```bash
   scp vod-server/tools/audit_pixfmt.sh root@vod1:/root/
   sudo bash /root/audit_pixfmt.sh            # or: sudo bash audit_pixfmt.sh /path/to/library
   ```
   Every rendition that is not `yuv420p` (or whose profile is High 10 / Main 10 / 4:2:2 / 4:4:4) is printed with `REPACKAGE`, followed by the list of titles.
2. For each listed title, remove the old library folder so stale segments do not survive the overwrite (`storage_move_dir` falls back to copy-over when the folder exists): VOD GUI → Content → Delete, or `curl -X DELETE -H "X-API-Key: …" http://vod1:8090/api/content/<content_id>`.
3. Re-submit the transcode with the same `content_id` from the IPTV admin: **Movies → edit → VOD section → "Overwrite & Re-transcode"**; **Series → Episodes → Transcode → "Upload & Transcode"**. Both go through `POST /admin/vod-server/jobs/submit` → `POST /api/jobs` `{content_id, source_path, profile, source_type}`; the source file (or URL) must still be available because the VOD server does not keep sources after a job.
4. Re-run the audit script; the list should be empty. The app now reports decoder failures as `playback_error` QoE events with `codec` (e.g. `avc1.6E0028`) and the content id, so any title missed by the audit shows up in analytics.

Files: `vod-server/src/transcoder.c`, `vod-server/src/transcoder.h`, `vod-server/tools/audit_pixfmt.sh` (+ `test_audit_pixfmt.sh`, run with `bash vod-server/tools/test_audit_pixfmt.sh`).

## 5. VOD server: CODECS on every master playlist variant (`vod:` commit)

**Why.** `master.m3u8` carried only `BANDWIDTH`, `RESOLUTION` and `NAME`. Without `CODECS` ExoPlayer/AVPlayer cannot check decoder support before choosing a variant, so adaptive selection climbed into a High 10 rendition and failed at the first decoded segment. With `CODECS="avc1.6E0028,mp4a.40.2"` the player excludes that variant by itself, and the mobile app can match failures by exact codec string instead of the resolution rule.

**What changed (`vod-server/src/packager.c`).** Before the master lines are written, every rendition MP4 is probed (`ffprobe -show_streams -show_data`) and the RFC 6381 string is built from the file's own decoder configuration, never from assumed values:

| Codec | Source bytes | String |
|---|---|---|
| H.264 | `avcC`: profile_idc, constraint flags byte, level_idc | `avc1.PPCCLL` e.g. `avc1.640028` (High 4.0), `avc1.6E0028` (High 10 4.0) |
| HEVC | `hvcC`: profile space/tier/idc, compatibility flags (bit-reversed hex), level, constraint bytes (trailing zeros dropped) | `hvc1.1.6.L120.90` |
| AV1 | `av1C`: seq_profile, seq_level_idx, tier, bit depth | `av01.0.08M.08` |
| Audio | `audio.m4a` codec_name + profile: AAC LC → `mp4a.40.2`, HE-AAC → `mp4a.40.5`, HE-AACv2 → `mp4a.40.29`, Main → `mp4a.40.1`; mp3 `mp4a.40.34`, ac3 `ac-3`, eac3 `ec-3`, opus, flac | appended after the video string |

If any rendition (or the audio) cannot be derived, `CODECS` is omitted from the whole master and a warning is logged; a wrong string would make players skip good renditions. Each generated line is logged: `Job N master line: #EXT-X-STREAM-INF:BANDWIDTH=…,CODECS="…" -> stream_720p.m3u8`.

**Deploy.** Same build/swap steps as section 4 (both changes are in the same binary). Verification after one job:

```bash
grep -n CODECS /var/lib/vod-server/library/<content_id>/master.m3u8
journalctl -u vod-server --no-pager | grep "master line"
#   expected after the 8-bit fix: CODECS="avc1.6400xx,mp4a.40.2" on every line
```

Existing titles keep their old master until re-packaged (section 4 procedure); the app's fallback handles them with the resolution rule in the meantime.

## 6. VOD server 1.1.1 on vod1: scripts, PID file, pending re-packages (`vod:` commit)

**State.** vod1 runs 1.1.1 built from this branch and swapped in by hand; the previous binary is `/usr/local/bin/vod-server.bak`.

**Branch pins.** `vod-server/scripts/update.sh` (`BRANCH=`) and `vod-server/scripts/install.sh` (`BRANCH="${BRANCH:-...}"`) now point at `claude/intelligent-knuth-xzkkd7`. The IPTV backend scripts (`install.sh` / `update.sh` in the repo root) are unchanged.

**What `update.sh` does on vod1** (read before running; everything as root):
1. Installs cmake / gcc / make and the dev libraries only if missing (apt or dnf/yum).
2. Installs certbot via snap or pip only if missing; writes `/etc/letsencrypt/renewal-hooks/deploy/vod-server.sh` if absent; **rewrites `/etc/sudoers.d/vod-server`** every run (systemd-run, systemctl daemon-reload / restart, the renew timer, and `sed -i` on the config, for the `vod-server` user).
3. Clones the branch (depth 1) to a temp dir and reads the new version from `CMakeLists.txt`.
4. Pauses transcode jobs with SIGUSR1 (main PID from systemd, PID file as fallback) and stops the service.
5. Copies the current binary to `vod-server.bak` (this overwrites the 1.1.0 backup you kept; copy it elsewhere first if you want to keep it).
6. Builds; on a cmake or make failure restores the backup and starts the service again.
7. Installs the binary, replaces the Web GUI under `/usr/local/share/vod-server/www/`, creates `/var/lib/vod-server/acme/...` and chowns only that acme directory, installs the systemd unit and runs `daemon-reload`.
8. Starts the service, checks `/api/status` on port 8090, and restores the backup binary if the service does not come up.

It does **not** touch `/var/lib/vod-server/library`, `/var/lib/vod-server/vod-server.db`, or `/etc/vod-server/vod-server.conf`. The only database activity is the server's own startup migration, which the deployed 1.1.1 has already run.

`install.sh` is for fresh machines: it also builds FFmpeg 8 from source when the installed one is older, creates the user and directories, runs `chown -R vod-server:vod-server /var/lib/vod-server` (ownership only, no deletion), keeps an existing config, and generates an API key only when the placeholder is still there. Do not run it on vod1; use `update.sh`.

**PID file.** `Cannot write PID file: /var/run/vod-server.pid (Permission denied)` at start-up: `/run` is root-only and the service runs as `vod-server`. The unit now sets `RuntimeDirectory=vod-server`, so systemd creates `/run/vod-server` owned by the service user at every start, and the default `pid_file` (config template and built-in default) is `/run/vod-server/vod-server.pid`. The message is a `log_error` only; the server keeps running without the file, so this is cosmetic until the change lands.

vod1 keeps its existing config file, so after the next `update.sh` (which installs the new unit) the one-line change is:

```
sudo sed -i 's|^pid_file = .*|pid_file = /run/vod-server/vod-server.pid|' /etc/vod-server/vod-server.conf && sudo systemctl restart vod-server
```

To apply it before running `update.sh`, also install the unit by hand: `sudo install -m 644 vod-server/scripts/vod-server.service /etc/systemd/system/vod-server.service && sudo systemctl daemon-reload` (from a checkout of this branch), then the line above. Verify with `journalctl -u vod-server -n 20` (no PID-file error) and `cat /run/vod-server/vod-server.pid`.

**Pending re-packages.** Black Sails episodes with content ids **759 to 766** (season 1, the eight titles the audit found with High 10 720p/1080p renditions) are still awaiting the section 4 re-package procedure. The step is blocked on locating their source files: the VOD server does not keep sources after a job, and the re-submit needs the original file or URL. Until then the app plays them through the quality fallback (360p) and their masters carry no CODECS.

## 7. Mobile billing feature switch (`backend:` commit)

**Setting.** Admin → Settings → new **Mobile App** tab → "Show billing in the mobile app" (`mobile_billing_enabled`, settings group `app`, stored `'1'`/`'0'` like the advertising switches, default off when the row does not exist). Handler `SettingsController::updateApp()`, route `POST /admin/settings/app`, CSRF-checked and logged like the other settings forms.

**API.** `GET /api/v1/app/config/{platform}` gains `features: {billing: bool}` next to `navigation`, `pages` and `layout`, read from the setting on every request (`(bool)(int)` of the stored value). The endpoint keeps its `Cache-Control: public, max-age=300`, so a flip reaches clients within five minutes plus the app's own manifest poll.

**App behaviour** (see `app/docs/WHITE_LABEL.md` for the brand override): off hides the packages page and navigation item, package rows in Profile, `packages_list` layout sections, activate/cancel actions, and turns the 402 message into "Not available."; on shows everything as before. Entitlement locks are unchanged.

**Files.** `src/Controllers/Admin/SettingsController.php`, `src/Controllers/Api/AppController.php`, `public/admin/index.php`, `templates/admin/settings/index.php`. No migration: the `settings` table is a key-value store and the row is created on first save.

**Deploy.** Part of the IPTV backend, not the VOD server: the root `update.sh` copies `src/`, `public/`, `templates/`. Not deployed.

