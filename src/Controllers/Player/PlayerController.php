<?php
/**
 * CARI-IPTV Player Controller
 * Serves the web player SPA shell and login page
 */

namespace CariIPTV\Controllers\Player;

class PlayerController
{
    /**
     * Serve the login page
     */
    public function login(): void
    {
        $siteName = 'CARI-IPTV';
        $siteLogo = '';

        try {
            $settings = new \CariIPTV\Services\SettingsService();
            $siteName = $settings->get('site_name', 'CARI-IPTV', 'general');
            $siteLogo = $settings->get('site_logo', '', 'general');
        } catch (\Throwable $e) {
            // Use defaults
        }

        include BASE_PATH . '/templates/player/login.php';
    }

    /**
     * Serve the registration page
     */
    public function register(): void
    {
        $siteName = 'CARI-IPTV';
        $siteLogo = '';

        try {
            $settings = new \CariIPTV\Services\SettingsService();
            $siteName = $settings->get('site_name', 'CARI-IPTV', 'general');
            $siteLogo = $settings->get('site_logo', '', 'general');
        } catch (\Throwable $e) {
            // Use defaults
        }

        include BASE_PATH . '/templates/player/register.php';
    }

    /**
     * Serve the email verification page
     */
    public function verifyEmail(string $token): void
    {
        $siteName = 'CARI-IPTV';
        $siteLogo = '';

        try {
            $settings = new \CariIPTV\Services\SettingsService();
            $siteName = $settings->get('site_name', 'CARI-IPTV', 'general');
            $siteLogo = $settings->get('site_logo', '', 'general');
        } catch (\Throwable $e) {
            // Use defaults
        }

        include BASE_PATH . '/templates/player/verify-email.php';
    }

    /**
     * Serve the main SPA app shell
     * All client-side routing happens in JavaScript
     */
    public function app(): void
    {
        $siteName = 'CARI-IPTV';
        $siteLogo = '';

        try {
            $settings = new \CariIPTV\Services\SettingsService();
            $siteName = $settings->get('site_name', 'CARI-IPTV', 'general');
            $siteLogo = $settings->get('site_logo', '', 'general');
        } catch (\Throwable $e) {
            // Use defaults
        }

        // Compute asset version from file modification times for cache busting.
        // When any player asset is updated (deploy/update.sh), the hash changes
        // and browsers fetch the new files instead of serving stale cache.
        $assetFiles = [
            BASE_PATH . '/public/assets/css/player.css',
            BASE_PATH . '/public/assets/js/player/api.js',
            BASE_PATH . '/public/assets/js/player/ui.js',
            BASE_PATH . '/public/assets/js/player/tracker.js',
            BASE_PATH . '/public/assets/js/player/router.js',
            BASE_PATH . '/public/assets/js/player/app.js',
        ];
        $mtimes = array_map(fn($f) => @filemtime($f) ?: 0, $assetFiles);
        $assetVersion = substr(md5(implode(':', $mtimes)), 0, 10);

        // Never cache the HTML shell — browser must always get fresh HTML
        // so it picks up new ?v= query strings when assets change
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');

        include BASE_PATH . '/templates/player/app.php';
    }
}
