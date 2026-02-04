<?php
/**
 * CARI-IPTV JWT Service
 * Pure PHP JWT implementation using HMAC-SHA256
 */

namespace CariIPTV\Services;

class JwtService
{
    private string $secret;
    private int $accessTtl;
    private int $refreshTtl;

    public function __construct()
    {
        $this->secret = $this->getSecret();
        $this->accessTtl = 3600;        // 1 hour
        $this->refreshTtl = 30 * 86400; // 30 days
    }

    /**
     * Create an access token for a subscriber
     */
    public function createAccessToken(array $subscriber): string
    {
        $now = time();

        $payload = [
            'sub' => (int) $subscriber['id'],
            'type' => 'access',
            'username' => $subscriber['username'] ?? '',
            'email' => $subscriber['email'] ?? '',
            'iat' => $now,
            'exp' => $now + $this->accessTtl,
            'iss' => 'cari-iptv',
        ];

        return $this->encode($payload);
    }

    /**
     * Create a refresh token (random string, stored hashed in DB)
     */
    public function createRefreshToken(): array
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + $this->refreshTtl);

        return [
            'token' => $token,
            'hash' => $hash,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Validate and decode an access token
     * Returns the payload or null if invalid
     */
    public function validateAccessToken(string $token): ?array
    {
        $payload = $this->decode($token);

        if (!$payload) {
            return null;
        }

        if (($payload['type'] ?? '') !== 'access') {
            return null;
        }

        if (($payload['exp'] ?? 0) < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Hash a refresh token for database lookup
     */
    public function hashRefreshToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Get access token TTL in seconds
     */
    public function getAccessTtl(): int
    {
        return $this->accessTtl;
    }

    // =========================================================================
    // INTERNAL JWT ENCODING/DECODING
    // =========================================================================

    private function encode(array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ]));

        $body = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$body", $this->secret, true)
        );

        return "$header.$body.$signature";
    }

    private function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $body, $signature] = $parts;

        // Verify signature
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$body", $this->secret, true)
        );

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($body), true);
        if (!is_array($payload)) {
            return null;
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    private function getSecret(): string
    {
        // Try env first, then settings, then generate a fallback
        $secret = getenv('JWT_SECRET') ?: ($_ENV['JWT_SECRET'] ?? '');

        if (empty($secret)) {
            try {
                $settings = new SettingsService();
                $secret = $settings->get('jwt_secret', '', 'security');

                if (empty($secret)) {
                    // Generate and save a secret
                    $secret = bin2hex(random_bytes(32));
                    $settings->set('jwt_secret', $secret, 'security');
                }
            } catch (\Throwable $e) {
                // Fallback to a deterministic secret based on app path
                $secret = hash('sha256', 'cari-iptv-' . dirname(__DIR__, 2));
            }
        }

        return $secret;
    }
}
