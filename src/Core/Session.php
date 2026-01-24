<?php
/**
 * CARI-IPTV Session Management
 */

namespace CariIPTV\Core;

class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $config = require dirname(__DIR__) . '/Config/app.php';
        $sessionConfig = $config['session'];

        ini_set('session.cookie_lifetime', $sessionConfig['lifetime']);
        ini_set('session.gc_maxlifetime', $sessionConfig['lifetime']);

        session_set_cookie_params([
            'lifetime' => $sessionConfig['lifetime'],
            'path' => $sessionConfig['path'],
            'domain' => $sessionConfig['domain'] ?? '',
            'secure' => $sessionConfig['secure'],
            'httponly' => $sessionConfig['httponly'],
            'samesite' => $sessionConfig['samesite'],
        ]);

        session_name('CARI_SESSION');
        session_start();
        self::$started = true;

        // Regenerate session ID periodically for security
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        } elseif (time() - $_SESSION['_created'] > 1800) {
            // Regenerate every 30 minutes
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function clear(): void
    {
        self::start();
        $_SESSION = [];
    }

    public static function destroy(): void
    {
        self::start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        self::$started = false;
    }

    public static function flash(string $key, mixed $value): void
    {
        self::set('_flash.' . $key, $value);
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $flashKey = '_flash.' . $key;
        $value = self::get($flashKey, $default);
        self::remove($flashKey);
        return $value;
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }

    /**
     * Generate or validate CSRF token
     */
    public static function csrf(): string
    {
        self::start();

        if (!isset($_SESSION['_csrf_token']) || !isset($_SESSION['_csrf_time']) ||
            (time() - $_SESSION['_csrf_time']) > 3600) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['_csrf_time'] = time();
        }

        return $_SESSION['_csrf_token'];
    }

    public static function validateCsrf(string $token): bool
    {
        self::start();
        return isset($_SESSION['_csrf_token']) &&
               hash_equals($_SESSION['_csrf_token'], $token);
    }
}
