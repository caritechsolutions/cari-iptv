<?php
/**
 * CARI-IPTV Main Entry Point
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
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use CariIPTV\Core\Router;
use CariIPTV\Core\Session;
use CariIPTV\Core\Response;

// Initialize session
Session::start();

// Check if this is an admin request and redirect
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($uri, '/admin')) {
    require __DIR__ . '/admin/index.php';
    exit;
}

// Create router for main site
$router = new Router();

// Home page (placeholder until we build the full frontend)
$router->get('/', function () {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>CARI-IPTV - Caribbean IPTV Platform</title>
        <style>
            :root {
                --primary: #6366f1;
                --bg-dark: #0f172a;
                --bg-card: #1e293b;
                --text-primary: #f1f5f9;
                --text-secondary: #94a3b8;
            }
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                background: var(--bg-dark);
                color: var(--text-primary);
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
                padding: 2rem;
            }
            .logo {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, var(--primary), #4f46e5);
                border-radius: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 2.5rem;
                margin-bottom: 1.5rem;
            }
            h1 { font-size: 2.5rem; margin-bottom: 0.5rem; }
            h1 span { color: var(--primary); }
            .subtitle {
                color: var(--text-secondary);
                font-size: 1.125rem;
                margin-bottom: 2rem;
            }
            .status {
                background: var(--bg-card);
                padding: 1.5rem 2rem;
                border-radius: 12px;
                margin-bottom: 2rem;
            }
            .status-label {
                color: var(--text-secondary);
                font-size: 0.875rem;
                margin-bottom: 0.5rem;
            }
            .status-value {
                color: #22c55e;
                font-weight: 600;
            }
            .links {
                display: flex;
                gap: 1rem;
                flex-wrap: wrap;
                justify-content: center;
            }
            a {
                color: var(--text-primary);
                text-decoration: none;
                padding: 0.75rem 1.5rem;
                background: var(--primary);
                border-radius: 8px;
                font-weight: 500;
                transition: all 0.2s;
            }
            a:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
            }
            a.secondary {
                background: var(--bg-card);
            }
        </style>
    </head>
    <body>
        <div class="logo">C</div>
        <h1>CARI<span>-IPTV</span></h1>
        <p class="subtitle">Caribbean IPTV & OTT Platform</p>

        <div class="status">
            <div class="status-label">Platform Status</div>
            <div class="status-value">Installation Complete</div>
        </div>

        <div class="links">
            <a href="/admin">Admin Panel</a>
            <a href="/player" class="secondary">Player Demo</a>
        </div>
    </body>
    </html>
    <?php
});

// API routes placeholder
$router->group(['prefix' => 'api'], function ($router) {
    $router->get('/health', function () {
        Response::json([
            'status' => 'ok',
            'version' => '1.0.0',
            'timestamp' => date('c'),
        ]);
    });
});

// Player placeholder
$router->get('/player', function () {
    Response::view('pages/player-demo');
});

$router->dispatch();
