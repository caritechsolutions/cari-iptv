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

        include BASE_PATH . '/templates/player/app.php';
    }
}
