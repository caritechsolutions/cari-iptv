<?php
/**
 * CARI-IPTV Subscriber Authentication Service
 * Handles login, token management, and profile for end-users
 */

namespace CariIPTV\Services;

use CariIPTV\Core\Database;

class SubscriberAuthService
{
    private Database $db;
    private JwtService $jwt;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->jwt = new JwtService();
    }

    /**
     * Register a new subscriber account (requires email verification)
     */
    public function register(array $data): array
    {
        $email = trim($data['email'] ?? '');
        $firstName = trim($data['first_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');
        $password = $data['password'] ?? '';
        $passwordConfirm = $data['password_confirm'] ?? '';
        $phone = trim($data['phone'] ?? '');
        $country = trim($data['country'] ?? '');
        $birthday = trim($data['birthday'] ?? '');

        // Validation
        if (empty($firstName)) {
            return ['success' => false, 'error' => 'First name is required'];
        }
        if (empty($lastName)) {
            return ['success' => false, 'error' => 'Last name is required'];
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'A valid email address is required'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters'];
        }
        if ($password !== $passwordConfirm) {
            return ['success' => false, 'error' => 'Passwords do not match'];
        }

        // Check if email already exists
        $existing = $this->db->fetch(
            "SELECT id FROM subscribers WHERE email = ?",
            [$email]
        );
        if ($existing) {
            return ['success' => false, 'error' => 'An account with this email already exists'];
        }

        // Generate username from email (prefix before @)
        $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $email)[0]));
        $username = $baseUsername;
        $counter = 1;
        while ($this->db->fetch("SELECT id FROM subscribers WHERE username = ?", [$username])) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        // Validate birthday if provided
        if (!empty($birthday)) {
            $parsedDate = \DateTime::createFromFormat('Y-m-d', $birthday);
            if (!$parsedDate) {
                $birthday = null;
            }
        } else {
            $birthday = null;
        }

        // Generate email verification token
        $verificationToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $verificationToken);

        // Create subscriber (email_verified = 0, pending verification)
        $this->db->execute(
            "INSERT INTO subscribers (username, email, password, first_name, last_name, phone, country, birthday, status, max_connections, email_verified, email_verification_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 1, 0, ?)",
            [
                $username,
                $email,
                password_hash($password, PASSWORD_BCRYPT),
                $firstName,
                $lastName,
                $phone ?: null,
                $country ?: null,
                $birthday,
                $tokenHash,
            ]
        );

        $subscriberId = $this->db->lastInsertId();
        if (!$subscriberId) {
            return ['success' => false, 'error' => 'Registration failed. Please try again.'];
        }

        // Send verification email
        $this->sendVerificationEmail($email, $firstName, $verificationToken);

        return [
            'success' => true,
            'requires_verification' => true,
            'message' => 'Account created! Please check your email to verify your account.',
        ];
    }

    /**
     * Verify subscriber email with token
     */
    public function verifyEmail(string $token): array
    {
        $tokenHash = hash('sha256', $token);

        $subscriber = $this->db->fetch(
            "SELECT id, email, first_name, username FROM subscribers
             WHERE email_verification_token = ? AND email_verified = 0",
            [$tokenHash]
        );

        if (!$subscriber) {
            return ['success' => false, 'error' => 'Invalid or expired verification link.'];
        }

        $this->db->execute(
            "UPDATE subscribers SET email_verified = 1, email_verified_at = NOW(), email_verification_token = NULL WHERE id = ?",
            [$subscriber['id']]
        );

        return [
            'success' => true,
            'message' => 'Your email has been verified. You can now sign in.',
        ];
    }

    /**
     * Resend verification email
     */
    public function resendVerification(string $email): array
    {
        $subscriber = $this->db->fetch(
            "SELECT id, first_name, username FROM subscribers WHERE email = ? AND email_verified = 0",
            [$email]
        );

        if (!$subscriber) {
            // Generic message to prevent email enumeration
            return ['success' => true, 'message' => 'If an unverified account exists with that email, a new verification link has been sent.'];
        }

        $verificationToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $verificationToken);

        $this->db->execute(
            "UPDATE subscribers SET email_verification_token = ? WHERE id = ?",
            [$tokenHash, $subscriber['id']]
        );

        $name = $subscriber['first_name'] ?: $subscriber['username'];
        $this->sendVerificationEmail($email, $name, $verificationToken);

        return [
            'success' => true,
            'message' => 'If an unverified account exists with that email, a new verification link has been sent.',
        ];
    }

    /**
     * Send verification email helper
     */
    private function sendVerificationEmail(string $email, string $name, string $verificationToken): void
    {
        $settings = new SettingsService();
        $siteUrl = $settings->get('site_url', '', 'general');
        if (empty($siteUrl)) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $siteUrl = "{$protocol}://{$_SERVER['HTTP_HOST']}";
        }
        $verifyUrl = rtrim($siteUrl, '/') . "/verify-email/{$verificationToken}";

        $emailService = new EmailService();

        if ($emailService->isConfigured()) {
            $emailService->sendEmailVerification($email, $name, $verifyUrl);
        }
    }

    /**
     * Authenticate a subscriber with username/email and password
     */
    public function login(string $identity, string $password, array $deviceInfo = []): array
    {
        // Find subscriber by username or email
        $subscriber = $this->db->fetch(
            "SELECT * FROM subscribers WHERE (username = ? OR email = ?) AND status = 'active' AND is_disabled = 0",
            [$identity, $identity]
        );

        if (!$subscriber) {
            return ['success' => false, 'error' => 'Invalid credentials'];
        }

        if (!$subscriber['password'] || !password_verify($password, $subscriber['password'])) {
            return ['success' => false, 'error' => 'Invalid credentials'];
        }

        // Check email verification
        if (empty($subscriber['email_verified']) || $subscriber['email_verified'] == 0) {
            return [
                'success' => false,
                'error' => 'Please verify your email address before signing in. Check your inbox for the activation link.',
                'needs_verification' => true,
                'email' => $subscriber['email'],
            ];
        }

        // Check max connections
        $activeTokens = $this->countActiveTokens($subscriber['id']);
        if ($activeTokens >= (int) $subscriber['max_connections']) {
            // Revoke oldest token to make room
            $this->revokeOldestToken($subscriber['id']);
        }

        // Generate tokens
        $accessToken = $this->jwt->createAccessToken($subscriber);
        $refreshData = $this->jwt->createRefreshToken();

        // Store refresh token
        $this->db->execute(
            "INSERT INTO subscriber_tokens (subscriber_id, token_hash, device_name, device_type, ip_address, user_agent, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $subscriber['id'],
                $refreshData['hash'],
                $deviceInfo['device_name'] ?? $this->detectDeviceName(),
                $deviceInfo['device_type'] ?? 'web',
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                $refreshData['expires_at'],
            ]
        );

        // Update login stats
        $this->db->execute(
            "UPDATE subscribers SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?",
            [$subscriber['id']]
        );

        return [
            'success' => true,
            'access_token' => $accessToken,
            'refresh_token' => $refreshData['token'],
            'expires_in' => $this->jwt->getAccessTtl(),
            'token_type' => 'Bearer',
            'user' => $this->formatSubscriber($subscriber),
        ];
    }

    /**
     * Refresh an access token using a refresh token
     */
    public function refresh(string $refreshToken): array
    {
        $hash = $this->jwt->hashRefreshToken($refreshToken);

        $tokenRecord = $this->db->fetch(
            "SELECT st.*, s.* FROM subscriber_tokens st
             INNER JOIN subscribers s ON st.subscriber_id = s.id
             WHERE st.token_hash = ? AND st.revoked_at IS NULL AND st.expires_at > NOW()
             AND s.status = 'active' AND s.is_disabled = 0",
            [$hash]
        );

        if (!$tokenRecord) {
            return ['success' => false, 'error' => 'Invalid or expired refresh token'];
        }

        // Update last used
        $this->db->execute(
            "UPDATE subscriber_tokens SET last_used_at = NOW() WHERE token_hash = ?",
            [$hash]
        );

        // Generate new access token
        $accessToken = $this->jwt->createAccessToken($tokenRecord);

        return [
            'success' => true,
            'access_token' => $accessToken,
            'expires_in' => $this->jwt->getAccessTtl(),
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Logout — revoke the refresh token
     */
    public function logout(string $refreshToken): bool
    {
        $hash = $this->jwt->hashRefreshToken($refreshToken);

        $this->db->execute(
            "UPDATE subscriber_tokens SET revoked_at = NOW() WHERE token_hash = ?",
            [$hash]
        );

        return true;
    }

    /**
     * Get subscriber profile from an access token
     */
    public function getProfile(int $subscriberId): ?array
    {
        $subscriber = $this->db->fetch(
            "SELECT * FROM subscribers WHERE id = ? AND status = 'active'",
            [$subscriberId]
        );

        if (!$subscriber) {
            return null;
        }

        return $this->formatSubscriber($subscriber);
    }

    /**
     * Validate an access token from the Authorization header
     * Returns subscriber ID or null
     */
    public function validateRequest(): ?int
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = substr($header, 7);
        $payload = $this->jwt->validateAccessToken($token);

        if (!$payload || empty($payload['sub'])) {
            return null;
        }

        return (int) $payload['sub'];
    }

    // =========================================================================
    // WATCH HISTORY & WATCHLIST
    // =========================================================================

    /**
     * Update watch progress
     */
    public function updateWatchProgress(int $subscriberId, string $contentType, int $contentId, int $progress, int $duration): void
    {
        $completed = ($duration > 0 && $progress >= $duration * 0.9) ? 1 : 0;

        $this->db->execute(
            "INSERT INTO subscriber_watch_history (subscriber_id, content_type, content_id, progress_seconds, duration_seconds, completed, last_watched_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE progress_seconds = VALUES(progress_seconds), duration_seconds = VALUES(duration_seconds),
             completed = VALUES(completed), last_watched_at = NOW()",
            [$subscriberId, $contentType, $contentId, $progress, $duration, $completed]
        );
    }

    /**
     * Get watch progress for a single content item
     */
    public function getWatchProgress(int $subscriberId, string $contentType, int $contentId): ?array
    {
        $row = $this->db->fetch(
            "SELECT progress_seconds, duration_seconds, completed, last_watched_at
             FROM subscriber_watch_history
             WHERE subscriber_id = ? AND content_type = ? AND content_id = ?",
            [$subscriberId, $contentType, $contentId]
        );

        return $row ?: null;
    }

    /**
     * Get batch watch progress for multiple content items of the same type
     */
    public function getBatchWatchProgress(int $subscriberId, string $contentType, array $contentIds): array
    {
        if (empty($contentIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($contentIds), '?'));
        $params = array_merge([$subscriberId, $contentType], $contentIds);

        $rows = $this->db->fetchAll(
            "SELECT content_id, progress_seconds, duration_seconds, completed
             FROM subscriber_watch_history
             WHERE subscriber_id = ? AND content_type = ? AND content_id IN ($placeholders)",
            $params
        ) ?: [];

        // Index by content_id for easy lookup
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['content_id']] = $row;
        }

        return $result;
    }

    /**
     * Get continue watching list with enriched content metadata.
     * Episodes are grouped by series — only the most recently watched episode per series is shown,
     * presented as the TV show with resume info for the last episode watched.
     */
    public function getContinueWatching(int $subscriberId, int $limit = 20): array
    {
        $items = $this->db->fetchAll(
            "SELECT wh.content_type, wh.content_id, wh.progress_seconds, wh.duration_seconds,
                    wh.completed, wh.last_watched_at
             FROM subscriber_watch_history wh
             WHERE wh.subscriber_id = ? AND wh.completed = 0 AND wh.progress_seconds > 0
             ORDER BY wh.last_watched_at DESC LIMIT ?",
            [$subscriberId, $limit * 2]
        ) ?: [];

        $result = [];
        $seenSeries = [];

        foreach ($items as $item) {
            if ($item['content_type'] === 'movie') {
                $movie = $this->db->fetch(
                    "SELECT id, title, slug, year, poster_url, backdrop_url, runtime, vote_average, stream_url
                     FROM movies WHERE id = ? AND status = 'published'",
                    [$item['content_id']]
                );
                if ($movie) {
                    $item = array_merge($item, $movie);
                } else {
                    $item['title'] = 'Unknown Movie';
                }
                $result[] = $item;
            } elseif ($item['content_type'] === 'episode') {
                $episode = $this->db->fetch(
                    "SELECT e.id as episode_id, e.name as episode_title, e.episode_number, e.still_url,
                            e.stream_url, e.runtime, e.vote_average,
                            sn.id as season_id, sn.season_number,
                            s.title, s.slug, s.year, s.poster_url, s.backdrop_url, s.id as series_id
                     FROM series_episodes e
                     LEFT JOIN series_seasons sn ON e.season_id = sn.id
                     LEFT JOIN series s ON e.series_id = s.id
                     WHERE e.id = ? AND s.status = 'published'",
                    [$item['content_id']]
                );
                if (!$episode || empty($episode['series_id'])) {
                    continue;
                }

                // Only keep the most recently watched episode per series
                $seriesId = (int) $episode['series_id'];
                if (isset($seenSeries[$seriesId])) {
                    continue;
                }
                $seenSeries[$seriesId] = true;

                // Present as a series item with resume episode info
                $item['content_type'] = 'series';
                $item['id'] = $seriesId;
                $item['title'] = $episode['title'];
                $item['slug'] = $episode['slug'] ?? '';
                $item['year'] = $episode['year'] ?? '';
                $item['poster_url'] = $episode['poster_url'] ?? '';
                $item['backdrop_url'] = $episode['backdrop_url'] ?? '';
                // Include resume episode details so player knows where to pick up
                $item['resume_episode_id'] = $episode['episode_id'];
                $item['resume_season_id'] = $episode['season_id'];
                $item['resume_season_number'] = $episode['season_number'];
                $item['resume_episode_number'] = $episode['episode_number'];
                $item['resume_episode_title'] = $episode['episode_title'];

                $result[] = $item;
            }

            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    /**
     * Toggle watchlist item
     */
    public function toggleWatchlist(int $subscriberId, string $contentType, int $contentId): bool
    {
        $exists = $this->db->fetch(
            "SELECT id FROM subscriber_watchlist WHERE subscriber_id = ? AND content_type = ? AND content_id = ?",
            [$subscriberId, $contentType, $contentId]
        );

        if ($exists) {
            $this->db->execute("DELETE FROM subscriber_watchlist WHERE id = ?", [$exists['id']]);
            return false; // removed
        }

        $this->db->execute(
            "INSERT INTO subscriber_watchlist (subscriber_id, content_type, content_id) VALUES (?, ?, ?)",
            [$subscriberId, $contentType, $contentId]
        );
        return true; // added
    }

    /**
     * Get watchlist
     */
    public function getWatchlist(int $subscriberId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM subscriber_watchlist WHERE subscriber_id = ? ORDER BY created_at DESC",
            [$subscriberId]
        ) ?: [];
    }

    // =========================================================================
    // SUBSCRIPTIONS
    // =========================================================================

    /**
     * Subscribe a subscriber to a package
     * Handles free packages (instant activation), trial packages, and paid packages
     */
    public function subscribe(int $subscriberId, int $packageId): array
    {
        // Get package details
        $package = $this->db->fetch(
            "SELECT * FROM packages WHERE id = ? AND is_active = 1",
            [$packageId]
        );

        if (!$package) {
            return ['success' => false, 'error' => 'Package not found or inactive'];
        }

        // Check if already subscribed to this package
        $existing = $this->db->fetch(
            "SELECT id, status FROM subscriber_subscriptions
             WHERE subscriber_id = ? AND package_id = ? AND status IN ('active', 'trial')",
            [$subscriberId, $packageId]
        );

        if ($existing) {
            return ['success' => false, 'error' => 'You are already subscribed to this package'];
        }

        $status = 'active';
        $expiresAt = null;
        $trialEndsAt = null;
        $paymentMethod = 'free';

        $isFree = !empty($package['is_free']) || (float) ($package['price'] ?? 0) === 0.0;
        $trialDays = (int) ($package['trial_days'] ?? 0);

        if ($isFree) {
            // Free package — activate immediately, no expiry
            $status = 'active';
            $paymentMethod = 'free';
        } elseif ($trialDays > 0) {
            // Paid package with trial — check if subscriber already used trial
            $hadTrial = $this->db->fetch(
                "SELECT 1 FROM subscriber_subscriptions WHERE subscriber_id = ? AND package_id = ?",
                [$subscriberId, $packageId]
            );

            if (!$hadTrial) {
                $status = 'trial';
                $trialEndsAt = date('Y-m-d H:i:s', strtotime("+{$trialDays} days"));
                $expiresAt = $trialEndsAt;
                $paymentMethod = 'trial';
            } else {
                // Already used trial — requires payment
                return ['success' => false, 'error' => 'Payment is required for this package. Trial has already been used.', 'requires_payment' => true];
            }
        } else {
            // Paid package, no trial — requires payment
            return ['success' => false, 'error' => 'Payment is required for this package.', 'requires_payment' => true];
        }

        // Create subscription
        $this->db->execute(
            "INSERT INTO subscriber_subscriptions
                (subscriber_id, package_id, status, started_at, expires_at, trial_ends_at, payment_method)
             VALUES (?, ?, ?, NOW(), ?, ?, ?)",
            [$subscriberId, $packageId, $status, $expiresAt, $trialEndsAt, $paymentMethod]
        );

        return [
            'success' => true,
            'status' => $status,
            'message' => $status === 'trial'
                ? "Trial started! You have {$package['trial_days']} days to try this package."
                : 'Successfully subscribed to ' . $package['name'],
        ];
    }

    /**
     * Cancel a subscriber's subscription to a package
     */
    public function unsubscribe(int $subscriberId, int $packageId): array
    {
        $subscription = $this->db->fetch(
            "SELECT id, status FROM subscriber_subscriptions
             WHERE subscriber_id = ? AND package_id = ? AND status IN ('active', 'trial')",
            [$subscriberId, $packageId]
        );

        if (!$subscription) {
            return ['success' => false, 'error' => 'No active subscription found for this package'];
        }

        $this->db->execute(
            "UPDATE subscriber_subscriptions
             SET status = 'cancelled', cancelled_at = NOW()
             WHERE id = ?",
            [$subscription['id']]
        );

        return ['success' => true, 'message' => 'Subscription cancelled successfully'];
    }

    /**
     * Update subscriber profile (adult_enabled, parental_pin)
     */
    public function updateProfile(int $subscriberId, array $data): array
    {
        $fields = [];
        $params = [];

        if (array_key_exists('adult_enabled', $data)) {
            $fields[] = 'adult_enabled = ?';
            $params[] = $data['adult_enabled'] ? 1 : 0;
        }

        if (array_key_exists('parental_pin', $data)) {
            $pin = $data['parental_pin'];
            if ($pin !== null && (!is_string($pin) || !preg_match('/^\d{4}$/', $pin))) {
                return ['success' => false, 'error' => 'PIN must be exactly 4 digits'];
            }
            $fields[] = 'parental_pin = ?';
            $params[] = $pin;
        }

        if (empty($fields)) {
            return ['success' => false, 'error' => 'No fields to update'];
        }

        $params[] = $subscriberId;
        $this->db->execute(
            "UPDATE subscribers SET " . implode(', ', $fields) . " WHERE id = ?",
            $params
        );

        return ['success' => true, 'message' => 'Profile updated'];
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function formatSubscriber(array $subscriber): array
    {
        return [
            'id' => (int) $subscriber['id'],
            'username' => $subscriber['username'],
            'email' => $subscriber['email'],
            'first_name' => $subscriber['first_name'],
            'last_name' => $subscriber['last_name'],
            'avatar' => $subscriber['avatar'],
            'max_connections' => (int) $subscriber['max_connections'],
            'parental_pin' => $subscriber['parental_pin'],
            'adult_enabled' => (bool) ($subscriber['adult_enabled'] ?? false),
        ];
    }

    private function countActiveTokens(int $subscriberId): int
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM subscriber_tokens
             WHERE subscriber_id = ? AND revoked_at IS NULL AND expires_at > NOW()",
            [$subscriberId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    private function revokeOldestToken(int $subscriberId): void
    {
        $oldest = $this->db->fetch(
            "SELECT id FROM subscriber_tokens
             WHERE subscriber_id = ? AND revoked_at IS NULL AND expires_at > NOW()
             ORDER BY created_at ASC LIMIT 1",
            [$subscriberId]
        );

        if ($oldest) {
            $this->db->execute(
                "UPDATE subscriber_tokens SET revoked_at = NOW() WHERE id = ?",
                [$oldest['id']]
            );
        }
    }

    private function detectDeviceName(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (str_contains($ua, 'Mobile')) return 'Mobile Browser';
        if (str_contains($ua, 'Chrome')) return 'Chrome';
        if (str_contains($ua, 'Firefox')) return 'Firefox';
        if (str_contains($ua, 'Safari')) return 'Safari';

        return 'Web Browser';
    }
}
