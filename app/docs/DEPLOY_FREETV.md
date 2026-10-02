# Deploy runbook — Free TV install (appcms, `/var/www/cari-iptv`)

Target: the IPTV backend only (PHP, MySQL, Nginx). The VOD server is separate and not touched. Branch: `claude/intelligent-knuth-xzkkd7`, which since the merge of `claude/fix-opensubtitle-connection-TvyOH` contains everything the install runs today plus the `backend:` commits (password reset, account deletion, public pages, `/api/health`, billing switch, entitlement fields on search and continue-watching, verification-email logging and `email_sent`).

## 0. What `update.sh` does to a running install (read before running)

The install's own `/var/www/cari-iptv/update.sh` (the one on their branch) and ours behave the same; ours is copied over theirs during the run. `--branch=` overrides the script's built-in default for that run only. The script runs with `set -e` and an `ERR` trap that calls `rollback`.

| Step | What happens | Notes |
|---|---|---|
| Pre-flight | Must be root; `INSTALL_DIR` and its `.env` must exist; web user detected from the owner of `public/index.php` (root → `www-data`). | |
| Backup (`--backup` only) | `rsync` of the whole install to `/var/backups/cari-iptv/backup_<ts>/files/` (logs, cache, sessions excluded) and `mysqldump` of the database to `database.sql` (a dump failure only warns). Keeps the last 5 backups. | **Without `--backup` the ERR trap has nothing to restore**: a failure mid-run leaves a half-updated tree. Always pass `--backup`. |
| Maintenance mode | Writes `public/maintenance.html` and a `.maintenance` flag. | The PHP app does not read the flag, so the site stays live during the copy. |
| Download | `git clone --depth 1 --branch <branch>` of the repo into a temp dir. | Needs `git` and outbound HTTPS to github.com. |
| Copy | `rsync -a` (no `--delete`) of `public/` (keeping `.htaccess`), `src/`, `templates/`, `scripts/`, `cron/`; **`database/migrations/` is deleted and replaced** by the branch's files; `database/schema.sql`, `composer.json`, `install.sh`, `update.sh` copied to the root; `version.txt` written. | `public/uploads/` is only merged (the repo holds a `.gitkeep`), so uploaded logos and posters stay. `.env` is never touched. |
| Migrations | Creates `_migrations` if missing; runs every `database/migrations/*.sql` not recorded there, in name order, recording each only on success; a failed migration is logged and skipped (retried next run). Before that it has one heuristic: if `settings` lacks a `group` column it **drops the `settings` table**; if `settings` exists but is empty it un-records the settings migration so it re-runs. | On this install `settings` has `group` and is populated (SMTP works), so the heuristic is a no-op. Beyond pending migrations the script does nothing to the database. Pending here: `036` (new table `subscriber_password_resets`, column `subscribers.deleted_at`), idempotent. |
| Permissions | `chown -R` to the web user and `chmod -R 755` on the whole install, `775` on `storage/` and `public/uploads/`; creates `packages/`. | `chmod -R 755` makes `.env` world-readable on the box (pre-existing behaviour, not changed by us). |
| Ollama | If `ollama` is not installed: `curl https://ollama.com/install.sh \| sh`, then enable/start the service and **pull five models (roughly 9 GB)**. If already installed: start the service and pull any missing models. | Biggest risk on a production box: disk, time, and an installer failure trips the ERR trap. Check `command -v ollama` and free disk first. There is no flag to skip it; if you do not want Ollama on this server, comment the `install_ollama` line in `main()` of the script copy you run. |
| TSDuck | If `tsp` works, nothing. If `tsp` is missing or broken: installs `libcurl4 libpcsclite1 libedit2`, downloads the Ubuntu-matching `.deb` (first from the **Caricoder2** GitHub repo, then TSDuck releases, both with certificate checks disabled), `dpkg -i`, and `apt-get install -f -y` on dependency trouble. On Ubuntu 20.04 it skips. Download failures only warn. | Caricoder is used only as a download mirror for that package; nothing else of Caricoder is installed or configured. |
| Cron | Overwrites `/etc/cron.d/cari-iptv` with the EPG fetch (every 5 min), EPG cleanup (02:00) and recommendations (03:00) jobs. | Any manual edits to that file are lost. |
| Cache | Empties `storage/cache/`, resets OPcache, restarts `php*-fpm`. | |
| Services | Edits **every** `php.ini` it finds (fpm, cli, apache2 for 8.1 to 8.3) to `upload_max_filesize 12G`, `post_max_size 12G`, `max_execution_time 600`, `max_input_time 600`; edits the site's Nginx config and `/etc/nginx/nginx.conf` (`client_max_body_size 12G`, fastcgi timeouts 600, `client_body_timeout 600s`); restarts PHP-FPM; `nginx -t && systemctl reload nginx`. | These edits persist even if the run later rolls back. If `nginx -t` fails the ERR trap fires after the files are already in place. |
| Rollback (on error, `--backup` only) | `rsync` the backed-up files over the install and restore `database.sql`, then clear maintenance mode. | Restores files and database only, not php.ini, Nginx, cron or packages. |

Can it break a working install? The copy and migrations are safe for this install. The risks are the side jobs: Ollama (disk and an external installer), TSDuck (`dpkg`/`apt-get -f`), and the php.ini/Nginx rewrites. Read the pre-checks below for each.

## 1. Pre-checks (on appcms, as root)

```bash
cd /var/www/cari-iptv
cat version.txt 2>/dev/null; git -C . log -1 2>/dev/null || true     # what runs today (version.txt may be absent)
df -h / /var /var/backups                                            # need: backup = size of the install + DB dump; Ollama adds ~9 GB if not installed
du -sh /var/www/cari-iptv public/uploads storage                      # size of what the backup copies
command -v ollama && ollama list | head                               # installed? models present?  (absent → the script installs it, see §0)
command -v tsp && tsp --version                                       # installed and working? (absent → the script installs it)
php -v | head -1; systemctl is-active php8.3-fpm php8.2-fpm php8.1-fpm nginx mysql 2>/dev/null
mysql -u "$(grep ^DB_USERNAME= .env | cut -d= -f2)" -p"$(grep ^DB_PASSWORD= .env | cut -d= -f2)" "$(grep ^DB_DATABASE= .env | cut -d= -f2)" -e \
  "SELECT filename FROM _migrations ORDER BY filename DESC LIMIT 3; \
   SELECT \`group\`,\`key\`,IF(\`key\` IN ('password','username'),'***',\`value\`) v FROM settings WHERE (\`group\`='smtp' AND \`key\` IN ('enabled','host','from_email','port','encryption')) OR (\`group\`='general' AND \`key\` IN ('site_url','site_name'));"
```

Required before running:

- `settings` shows `smtp.enabled = 1`, a `host` and a `from_email`. Otherwise reset and verification mails are logged, not sent (Admin → Settings → Email; use the test-send button).
- `general.site_url` = `https://freetvapp.freetv.ng` (no trailing slash). Empty means links are built from the request and, behind the TLS proxy, come out as `http://`.
- Free disk: at least the install size plus the dump for the backup, plus 10 GB if Ollama is not installed and you let the script install it.
- Decide on Ollama. If this server should not run it, copy the script and comment out `install_ollama` in `main()` before running (see §2, alternative).
- A maintenance window: the site stays up during the copy, but PHP-FPM restarts and Nginx reloads at the end.

## 2. Command

Run the install's own script with our branch, with a backup:

```bash
cd /var/www/cari-iptv
sudo bash update.sh --install-dir=/var/www/cari-iptv --backup --branch=claude/intelligent-knuth-xzkkd7 2>&1 | tee /var/log/cari-iptv-update-$(date +%F-%H%M).log
```

Alternative without Ollama (same script, one line commented):

```bash
curl -sSL "https://raw.githubusercontent.com/caritechsolutions/cari-iptv/claude/intelligent-knuth-xzkkd7/update.sh?$(date +%s)" -o /root/update-freetv.sh
sed -i 's/^    install_ollama$/    # install_ollama   # skipped on appcms/' /root/update-freetv.sh
sudo bash /root/update-freetv.sh --install-dir=/var/www/cari-iptv --backup --branch=claude/intelligent-knuth-xzkkd7 2>&1 | tee /var/log/cari-iptv-update-$(date +%F-%H%M).log
```

Note: after the run the install's `update.sh` is ours, whose built-in default branch is still `claude/fix-opensubtitle-connection-TvyOH`. Keep passing `--branch=` on every future run.

Watch the log for: `Running migration: 036_...` followed by `Migration completed`, `Restarted php8.x-fpm`, `Reloaded nginx`, and the completion banner. Any `Migration failed` or `Update failed! Attempting rollback` line means stop and read §4.

## 3. Post-checks

```bash
# 1. Health route (new on this branch) and version
curl -sS https://freetvapp.freetv.ng/api/health; echo
cat /var/www/cari-iptv/version.txt

# 2. Migration 036 applied
mysql ... -e "SELECT filename, executed_at FROM _migrations WHERE filename LIKE '036%'; SHOW TABLES LIKE 'subscriber_password_resets'; SHOW COLUMNS FROM subscribers LIKE 'deleted_at';"

# 3. Forgot password (generic 200 whatever the address; a real address gets a mail with an https:// link)
curl -sS -X POST https://freetvapp.freetv.ng/api/v1/auth/forgot-password -H 'Content-Type: application/json' -d '{"email":"<a real subscriber address>"}'; echo
#    open the emailed /reset-password/<token> page, set a new password, sign in with it

# 4. Register from the app (or curl) with a throwaway address: the 201 must carry email_sent
curl -sS -X POST https://freetvapp.freetv.ng/api/v1/auth/register -H 'Content-Type: application/json' \
  -d '{"first_name":"Test","last_name":"Deploy","email":"<throwaway>","password":"<8+ chars>","password_confirm":"<same>"}'; echo
#    expect {"data":{"requires_verification":true,"email_sent":true,...}}; if false, read the reason:
grep "Verification email to" /var/www/cari-iptv/storage/logs/php-error.log | tail -3     # or the PHP-FPM error log

# 5. Search gating fields (sign in first; token from /auth/login)
curl -sS "https://freetvapp.freetv.ng/api/v1/search?q=tv" -H "Authorization: Bearer <token>" | python3 -c 'import sys,json; r=json.load(sys.stdin)["data"]; print([(x["title"], x.get("is_restricted"), x.get("category_id")) for x in r][:5])'
#    every row must carry is_restricted (true/false); in the app a restricted channel from search now shows the padlock and is refused

# 6. Public pages and the web player
curl -sS -o /dev/null -w "%{http_code} /forgot-password\n" https://freetvapp.freetv.ng/forgot-password
curl -sS -o /dev/null -w "%{http_code} /privacy\n"         https://freetvapp.freetv.ng/privacy
curl -sS -o /dev/null -w "%{http_code} /delete-account\n"  https://freetvapp.freetv.ng/delete-account
#    open the web player, play a channel, confirm the banner/text-scroller ads still show (their six fixes are in this branch)

# 7. Admin → Settings → Mobile App tab exists; leave "Show billing" unticked unless billing should appear in the app
# 8. Admin → Settings → General: site_url still set; Email: test-send works
```

## 4. Rollback

The backup path is printed at the end of the run (`Backup Location:`), e.g. `/var/backups/cari-iptv/backup_<ts>/`.

```bash
B=/var/backups/cari-iptv/backup_<ts>
cd /var/www/cari-iptv
rsync -a "$B/files/" /var/www/cari-iptv/                     # code, templates, migrations back to the previous state (uploads untouched: they were never removed)
mysql ... < "$B/database.sql"                                 # only if the migration must be undone; 036 adds a table and a nullable column, so leaving it in place is harmless
chown -R www-data:www-data /var/www/cari-iptv
systemctl restart php8.*-fpm; systemctl reload nginx
```

Not undone by the rollback: the php.ini and Nginx edits (12G upload limits, 600 s timeouts), `/etc/cron.d/cari-iptv`, and any Ollama or TSDuck packages the run installed. Revert those by hand if they matter.

## 5. After a successful deploy

- The app's config now returns `features`, so the Mobile App switch in Admin → Settings controls billing in the Free TV app within five minutes of a change.
- Replace the placeholder text in `templates/player/privacy.php` with the client's policy.
- Entitlement fields on search and continue-watching, verification logging and `email_sent` are live; the `API_GAPS.md` entries for them close for this install.
