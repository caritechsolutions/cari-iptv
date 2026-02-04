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
     * Get continue watching list
     */
    public function getContinueWatching(int $subscriberId, int $limit = 20): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM subscriber_watch_history
             WHERE subscriber_id = ? AND completed = 0 AND progress_seconds > 0
             ORDER BY last_watched_at DESC LIMIT ?",
            [$subscriberId, $limit]
        ) ?: [];
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
