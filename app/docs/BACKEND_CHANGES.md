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
templates/player/reset-password.php  templates/player/delete-account.php  templates/player/privacy.php  (new)
```

## Deployment steps (not performed)
1. Merge the `backend:` commits to the branch your `update.sh` pulls from (or set `BRANCH=` in `update.sh`/`install.sh` per `CLAUDE.md`).
2. Run the update script as usual; it copies `src/`, `public/`, `templates/`, `database/migrations/` and applies pending migrations from the `_migrations` table. Migration `036` is idempotent and safe to re-run.
3. Confirm SMTP is configured in Admin → Settings → Email, otherwise reset emails are logged instead of sent.
4. Confirm `general.site_url` in Admin → Settings is the public HTTPS URL (used in the reset link).
5. Smoke test: `POST /api/v1/auth/forgot-password`, open the emailed `/reset-password/{token}` page, sign in with the new password; open `/privacy` and `/delete-account`.
6. Replace the placeholder text in `templates/player/privacy.php`.

## Not changed (needs your decision)
- The web player's login page has no "Forgot password?" link yet (the app has the flow). Adding the link and a small `/forgot-password` web page is a one-template change if you want it.
- `install.sh` / `update.sh` `BRANCH=` values were not touched (CLAUDE.md says to update them before pushing; you asked not to deploy).
- Subscription and billing retention period is not enforced by code.
