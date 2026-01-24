<?php
/**
 * CARI-IPTV Application Configuration
 */

return [
    'name' => 'CARI-IPTV',
    'version' => '1.0.0',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => getenv('APP_DEBUG') === 'true',
    'url' => getenv('APP_URL') ?: 'http://localhost',
    'timezone' => 'America/Jamaica',

    // Session configuration
    'session' => [
        'lifetime' => 7200, // 2 hours
        'path' => '/',
        'domain' => null,
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    // Security settings
    'security' => [
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes
        'password_min_length' => 8,
        'csrf_token_lifetime' => 3600,
    ],

    // Admin roles and their hierarchy (higher = more privileges)
    'admin_roles' => [
        'viewer' => 1,
        'support' => 2,
        'manager' => 3,
        'admin' => 4,
        'super_admin' => 5,
    ],

    // Pagination defaults
    'pagination' => [
        'per_page' => 25,
        'max_per_page' => 100,
    ],
];
