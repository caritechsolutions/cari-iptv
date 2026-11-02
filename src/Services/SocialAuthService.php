<?php
/**
 * CARI-IPTV Social Sign-in (Google, Apple)
 *
 * Verifies the ID token the app or web player got from the provider, then
 * finds-or-creates the subscriber and hands back a normal session. Pure PHP:
 * the providers' JWKS are fetched over HTTPS and RS256 signatures checked
 * with OpenSSL.
 *
 * Configuration lives in the settings group "auth" (Admin → Settings →
 * Sign-in), so each operator's installation uses its own Google project and
 * Apple developer account:
 *   google_enabled, google_web_client_id, google_extra_client_ids
 *   apple_enabled, apple_team_id, apple_services_id, apple_bundle_id,
 *   apple_key_id, apple_private_key
 */

namespace CariIPTV\Services;

use CariIPTV\Core\Database;

class SocialAuthService
{
    private const GOOGLE_JWKS = 'https://www.googleapis.com/oauth2/v3/certs';
    private const GOOGLE_ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];
    private const APPLE_JWKS = 'https://appleid.apple.com/auth/keys';
    private const APPLE_ISSUER = 'https://appleid.apple.com';
    private const APPLE_TOKEN_URL = 'https://appleid.apple.com/auth/token';
    private const APPLE_REVOKE_URL = 'https://appleid.apple.com/auth/revoke';

    private Database $db;
    private SettingsService $settings;
    private SubscriberAuthService $auth;

    public function __construct(?SubscriberAuthService $auth = null)
    {
        $this->db = Database::getInstance();
        $this->settings = new SettingsService();
        $this->auth = $auth ?? new SubscriberAuthService();
    }

    // ------------------------------------------------------------------
    // Public configuration (what the apps need to show the buttons)
    // ------------------------------------------------------------------

    public function publicConfig(): array
    {
        $g = $this->settings->getGroup('auth');
        return [
            'google' => [
                'enabled' => !empty($g['google_enabled']) && !empty($g['google_web_client_id']),
                'client_id' => $g['google_web_client_id'] ?? null,
            ],
            'apple' => [
                'enabled' => !empty($g['apple_enabled']) && !empty($g['apple_services_id']) && !empty($g['apple_team_id']),
                'services_id' => $g['apple_services_id'] ?? null,
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Sign in
    // ------------------------------------------------------------------

    /**
     * @param string $provider  google | apple
     * @param string $idToken   ID token (Google) / identity token (Apple)
     * @param array  $extra     Apple: authorization_code, first_name, last_name
     *                          (Apple only sends the name on the first sign-in)
     * @return array  same shape as SubscriberAuthService::login()
     */
    public function signIn(string $provider, string $idToken, array $extra = [], array $deviceInfo = []): array
    {
        $cfg = $this->settings->getGroup('auth');

        try {
            $claims = match ($provider) {
                'google' => $this->verifyGoogle($idToken, $cfg),
                'apple' => $this->verifyApple($idToken, $cfg),
                default => throw new \RuntimeException('Unknown sign-in provider'),
            };
        } catch (\RuntimeException $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'code' => 'SOCIAL_AUTH_FAILED'];
        }

        $providerUserId = (string) ($claims['sub'] ?? '');
        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        if ($providerUserId === '') {
            return ['success' => false, 'error' => 'Token has no subject', 'code' => 'SOCIAL_AUTH_FAILED'];
        }

        // 1. Known identity → its subscriber
        $identity = $this->db->fetch(
            "SELECT * FROM subscriber_identities WHERE provider = ? AND provider_user_id = ?",
            [$provider, $providerUserId]
        );
        $subscriber = null;
        if ($identity) {
            $subscriber = $this->db->fetch("SELECT * FROM subscribers WHERE id = ? AND deleted_at IS NULL", [$identity['subscriber_id']]);
        }

        // 2. Same (verified) email already registered → link
        if (!$subscriber && $email !== '' && !empty($claims['email_verified'])) {
            $subscriber = $this->db->fetch("SELECT * FROM subscribers WHERE email = ? AND deleted_at IS NULL", [$email]);
        }

        // 3. New account
        if (!$subscriber) {
            if ($email === '') {
                return ['success' => false, 'error' => 'The provider did not share an email address for this account', 'code' => 'SOCIAL_NO_EMAIL'];
            }
            $subscriberId = $this->createSubscriber($email, $claims, $extra, $provider);
            $subscriber = $this->db->fetch("SELECT * FROM subscribers WHERE id = ?", [$subscriberId]);
        }

        if (!$subscriber || $subscriber['status'] !== 'active' || !empty($subscriber['is_disabled'])) {
            return ['success' => false, 'error' => 'This account is not active', 'code' => 'ACCOUNT_INACTIVE'];
        }

        // The provider verified the address, so a pending email verification is moot
        if (empty($subscriber['email_verified'])) {
            $this->db->execute(
                "UPDATE subscribers SET email_verified = 1, email_verified_at = NOW(), email_verification_token = NULL WHERE id = ?",
                [$subscriber['id']]
            );
            $subscriber['email_verified'] = 1;
        }

        // Record / refresh the identity
        if ($identity) {
            $this->db->execute("UPDATE subscriber_identities SET last_login_at = NOW(), email = ? WHERE id = ?", [$email ?: null, $identity['id']]);
        } else {
            $this->db->execute(
                "INSERT INTO subscriber_identities (subscriber_id, provider, provider_user_id, email, last_login_at) VALUES (?, ?, ?, ?, NOW())",
                [$subscriber['id'], $provider, $providerUserId, $email ?: null]
            );
        }

        // Apple: keep a refresh token so the account can be revoked on deletion
        if ($provider === 'apple' && !empty($extra['authorization_code'])) {
            $refresh = $this->appleExchangeCode((string) $extra['authorization_code'], $cfg);
            if ($refresh) {
                $this->db->execute(
                    "UPDATE subscriber_identities SET refresh_token = ? WHERE provider = 'apple' AND provider_user_id = ?",
                    [$refresh, $providerUserId]
                );
            }
        }

        $result = $this->auth->issueSession($subscriber, $deviceInfo);
        $result['user']['has_password'] = !empty($subscriber['password']);
        return $result;
    }

    private function createSubscriber(string $email, array $claims, array $extra, string $provider): int
    {
        $first = trim((string) ($extra['first_name'] ?? $claims['given_name'] ?? ''));
        $last = trim((string) ($extra['last_name'] ?? $claims['family_name'] ?? ''));
        if ($first === '' && $last === '') {
            $full = trim((string) ($claims['name'] ?? ''));
            if ($full !== '') {
                $parts = preg_split('/\s+/', $full, 2);
                $first = $parts[0];
                $last = $parts[1] ?? '';
            } else {
                $first = ucfirst(explode('@', $email)[0]);
            }
        }

        $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $email)[0])) ?: $provider . 'user';
        $username = $base;
        $n = 1;
        while ($this->db->fetch("SELECT id FROM subscribers WHERE username = ?", [$username])) {
            $username = $base . $n++;
        }

        $this->db->execute(
            "INSERT INTO subscribers (username, email, password, first_name, last_name, status, max_connections, email_verified, email_verified_at, avatar)
             VALUES (?, ?, NULL, ?, ?, 'active', 1, 1, NOW(), ?)",
            [$username, $email, $first, $last, $this->safeAvatar($claims['picture'] ?? null)]
        );
        return (int) $this->db->lastInsertId();
    }

    private function safeAvatar(?string $url): ?string
    {
        if (!$url || !preg_match('~^https://[a-z0-9.-]+\.(googleusercontent|ggpht)\.com/~i', $url)) return null;
        return substr($url, 0, 500);
    }

    // ------------------------------------------------------------------
    // Account deletion hook
    // ------------------------------------------------------------------

    /** Revoke Apple tokens for a subscriber (Apple requires this on deletion). */
    public function revokeAll(int $subscriberId): void
    {
        $cfg = $this->settings->getGroup('auth');
        $rows = $this->db->fetchAll(
            "SELECT refresh_token FROM subscriber_identities WHERE subscriber_id = ? AND provider = 'apple' AND refresh_token IS NOT NULL",
            [$subscriberId]
        );
        foreach ($rows as $r) {
            $this->appleRevoke($r['refresh_token'], $cfg);
        }
    }

    // ------------------------------------------------------------------
    // Google
    // ------------------------------------------------------------------

    private function verifyGoogle(string $idToken, array $cfg): array
    {
        if (empty($cfg['google_enabled'])) throw new \RuntimeException('Google sign-in is not enabled');
        $audiences = array_filter(array_map('trim', array_merge(
            [$cfg['google_web_client_id'] ?? ''],
            preg_split('/[\s,]+/', (string) ($cfg['google_extra_client_ids'] ?? ''))
        )));
        if (!$audiences) throw new \RuntimeException('Google sign-in is not configured');

        $claims = $this->verifyJwt($idToken, self::GOOGLE_JWKS, 'google');
        if (!in_array($claims['iss'] ?? '', self::GOOGLE_ISSUERS, true)) throw new \RuntimeException('Token issuer mismatch');
        if (!in_array($claims['aud'] ?? '', $audiences, true)) throw new \RuntimeException('Token was not issued for this app');
        return $claims;
    }

    // ------------------------------------------------------------------
    // Apple
    // ------------------------------------------------------------------

    private function verifyApple(string $idToken, array $cfg): array
    {
        if (empty($cfg['apple_enabled'])) throw new \RuntimeException('Sign in with Apple is not enabled');
        $audiences = array_filter([trim((string) ($cfg['apple_services_id'] ?? '')), trim((string) ($cfg['apple_bundle_id'] ?? ''))]);
        if (!$audiences) throw new \RuntimeException('Sign in with Apple is not configured');

        $claims = $this->verifyJwt($idToken, self::APPLE_JWKS, 'apple');
        if (($claims['iss'] ?? '') !== self::APPLE_ISSUER) throw new \RuntimeException('Token issuer mismatch');
        if (!in_array($claims['aud'] ?? '', $audiences, true)) throw new \RuntimeException('Token was not issued for this app');
        // Apple sends email_verified as "true"/true
        $claims['email_verified'] = filter_var($claims['email_verified'] ?? true, FILTER_VALIDATE_BOOLEAN);
        return $claims;
    }

    /** ES256 client secret Apple requires for the token and revoke endpoints. */
    private function appleClientSecret(array $cfg, string $clientId): ?string
    {
        $key = trim((string) ($cfg['apple_private_key'] ?? ''));
        $teamId = trim((string) ($cfg['apple_team_id'] ?? ''));
        $keyId = trim((string) ($cfg['apple_key_id'] ?? ''));
        if ($key === '' || $teamId === '' || $keyId === '' || $clientId === '') return null;

        $header = $this->b64url(json_encode(['alg' => 'ES256', 'kid' => $keyId]));
        $now = time();
        $payload = $this->b64url(json_encode(['iss' => $teamId, 'iat' => $now, 'exp' => $now + 3600, 'aud' => self::APPLE_ISSUER, 'sub' => $clientId]));
        $pkey = openssl_pkey_get_private($key);
        if (!$pkey) return null;
        if (!openssl_sign("{$header}.{$payload}", $der, $pkey, OPENSSL_ALGO_SHA256)) return null;
        return "{$header}.{$payload}." . $this->b64url($this->derToRaw($der, 32));
    }

    private function appleExchangeCode(string $code, array $cfg): ?string
    {
        // The code was issued to the client the user signed in through:
        // the app (bundle id) or the web (services id). Try the bundle id first.
        foreach (array_filter([trim((string) ($cfg['apple_bundle_id'] ?? '')), trim((string) ($cfg['apple_services_id'] ?? ''))]) as $clientId) {
            $secret = $this->appleClientSecret($cfg, $clientId);
            if (!$secret) continue;
            $res = $this->postForm(self::APPLE_TOKEN_URL, [
                'client_id' => $clientId, 'client_secret' => $secret,
                'code' => $code, 'grant_type' => 'authorization_code',
            ]);
            $json = $res ? json_decode($res, true) : null;
            if (!empty($json['refresh_token'])) return $json['refresh_token'];
        }
        return null;
    }

    private function appleRevoke(string $refreshToken, array $cfg): void
    {
        foreach (array_filter([trim((string) ($cfg['apple_bundle_id'] ?? '')), trim((string) ($cfg['apple_services_id'] ?? ''))]) as $clientId) {
            $secret = $this->appleClientSecret($cfg, $clientId);
            if (!$secret) continue;
            $this->postForm(self::APPLE_REVOKE_URL, [
                'client_id' => $clientId, 'client_secret' => $secret,
                'token' => $refreshToken, 'token_type_hint' => 'refresh_token',
            ]);
        }
    }

    // ------------------------------------------------------------------
    // JWT verification against a JWKS (RS256), cached for an hour
    // ------------------------------------------------------------------

    private function verifyJwt(string $jwt, string $jwksUrl, string $cacheKey): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) throw new \RuntimeException('Malformed token');
        [$h, $p, $s] = $parts;
        $header = json_decode($this->b64urlDecode($h), true) ?: [];
        $claims = json_decode($this->b64urlDecode($p), true) ?: [];
        $sig = $this->b64urlDecode($s);

        if (($header['alg'] ?? '') !== 'RS256') throw new \RuntimeException('Unsupported token algorithm');
        $kid = $header['kid'] ?? '';

        $jwk = $this->findKey($jwksUrl, $cacheKey, $kid);
        if (!$jwk) throw new \RuntimeException('Signing key not found');

        $pem = $this->jwkToPem($jwk);
        $ok = openssl_verify("{$h}.{$p}", $sig, $pem, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) throw new \RuntimeException('Token signature is invalid');

        $now = time();
        if (($claims['exp'] ?? 0) < $now - 60) throw new \RuntimeException('Token has expired');
        if (isset($claims['nbf']) && $claims['nbf'] > $now + 60) throw new \RuntimeException('Token not yet valid');
        return $claims;
    }

    private function findKey(string $url, string $cacheKey, string $kid): ?array
    {
        $keys = $this->jwks($url, $cacheKey, false);
        foreach ($keys as $k) if (($k['kid'] ?? '') === $kid) return $k;
        // Key rotated since we cached: refetch once
        $keys = $this->jwks($url, $cacheKey, true);
        foreach ($keys as $k) if (($k['kid'] ?? '') === $kid) return $k;
        return null;
    }

    private function jwks(string $url, string $cacheKey, bool $force): array
    {
        $dir = BASE_PATH . '/storage/cache';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $file = "{$dir}/jwks_{$cacheKey}.json";
        if (!$force && is_file($file) && filemtime($file) > time() - 3600) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (!empty($cached['keys'])) return $cached['keys'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_USERAGENT => 'CARI-IPTV/1.0']);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200 && $body) {
            $json = json_decode($body, true);
            if (!empty($json['keys'])) {
                @file_put_contents($file, $body);
                return $json['keys'];
            }
        }
        $cached = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        return $cached['keys'] ?? [];
    }

    /** RSA JWK (n, e) → PEM public key, without extensions. */
    private function jwkToPem(array $jwk): string
    {
        $n = $this->b64urlDecode($jwk['n'] ?? '');
        $e = $this->b64urlDecode($jwk['e'] ?? '');
        if ($n === '' || $e === '') throw new \RuntimeException('Bad signing key');

        $derInt = function (string $bin): string {
            if (ord($bin[0]) > 0x7f) $bin = "\x00" . $bin; // keep it positive
            return "\x02" . $this->derLen(strlen($bin)) . $bin;
        };
        $rsaKey = "\x30" . $this->derLen(strlen($derInt($n) . $derInt($e))) . $derInt($n) . $derInt($e);
        $algId = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00"; // rsaEncryption, NULL
        $bitStr = "\x03" . $this->derLen(strlen($rsaKey) + 1) . "\x00" . $rsaKey;
        $spki = "\x30" . $this->derLen(strlen($algId . $bitStr)) . $algId . $bitStr;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private function derLen(int $len): string
    {
        if ($len < 0x80) return chr($len);
        $bytes = ltrim(pack('N', $len), "\x00");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    /** DER ECDSA signature → raw r||s (JOSE format). */
    private function derToRaw(string $der, int $partLen): string
    {
        $pos = 2; // SEQUENCE, length (short form is enough for P-256)
        if (ord($der[1]) & 0x80) $pos = 2 + (ord($der[1]) & 0x7f);
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $pos++; // INTEGER tag
            $len = ord($der[$pos++]);
            $int = ltrim(substr($der, $pos, $len), "\x00");
            $pos += $len;
            $out .= str_pad($int, $partLen, "\x00", STR_PAD_LEFT);
        }
        return $out;
    }

    private function postForm(string $url, array $fields): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields), CURLOPT_USERAGENT => 'CARI-IPTV/1.0',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 400) error_log("[SocialAuth] {$url} returned {$code}: " . substr((string) $body, 0, 200));
        return $body === false ? null : $body;
    }

    private function b64url(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private function b64urlDecode(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
    }
}
