<?php
/**
 * CARI-IPTV Main Entry Point
 * Routes requests to: Admin panel, Content API, or Web Player
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/storage/logs/php-error.log');

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Load environment variables
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'CariIPTV\\';
    $baseDir = BASE_PATH . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

// Get the request URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Route to admin portal
if (str_starts_with($uri, '/admin')) {
    require __DIR__ . '/admin/index.php';
    exit;
}

// Route to Content API
if (str_starts_with($uri, '/api/')) {
    require __DIR__ . '/api/index.php';
    exit;
}

// API health check endpoint
if ($uri === '/api/health') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ok',
        'version' => '1.0.0',
        'timestamp' => date('c'),
    ]);
    exit;
}

// =========================================================================
// Web Player (public-facing SPA)
// =========================================================================

$controller = new \CariIPTV\Controllers\Player\PlayerController();

// Login page
if ($uri === '/login') {
    $controller->login();
    exit;
}

// Registration page
if ($uri === '/register') {
    $controller->register();
    exit;
}

// Email verification page
if (preg_match('#^/verify-email/([a-f0-9]+)$#', $uri, $matches)) {
    $controller->verifyEmail($matches[1]);
    exit;
}

// All other paths serve the SPA app shell.
// Client-side JavaScript handles routing for:
//   /  /movies  /series  /live  /search  /my-list  /watch/:type/:id  /categories
$controller->app();
