<?php
/**
 * CARI-IPTV Content API v1 Entry Point
 *
 * Public API for player/frontend consumption.
 * All responses are JSON with CORS headers.
 *
 * Versioning Strategy:
 *   GET /api/v1/manifest returns version hashes per content type.
 *   Players cache these hashes and only refetch content when hashes change.
 *   Individual endpoints support ?since=TIMESTAMP for incremental sync.
 *   ETag headers enable HTTP 304 Not Modified responses.
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__, 2) . '/storage/logs/php-error.log');

// Define base path
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

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
        if (!isset($_ENV[$key])) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
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
use CariIPTV\Controllers\Api\ContentController;
use CariIPTV\Controllers\Api\AppController;
use CariIPTV\Controllers\Api\EpgController;
use CariIPTV\Controllers\Api\AuthController;
use CariIPTV\Middleware\ApiAuthMiddleware;

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, If-None-Match');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

// Create router
$router = new Router();

// Register API auth middleware
$router->addMiddleware('api_auth', [ApiAuthMiddleware::class, 'handle']);

// =========================================================================
// API v1 Routes
// =========================================================================

$router->group(['prefix' => 'api/v1'], function ($router) {

    // ----- Authentication (public) -----
    $router->post('/auth/register', [AuthController::class, 'register']);
    $router->get('/auth/verify-email/{token}', [AuthController::class, 'verifyEmail']);
    $router->post('/auth/resend-verification', [AuthController::class, 'resendVerification']);
    $router->post('/auth/login', [AuthController::class, 'login']);
    $router->post('/auth/refresh', [AuthController::class, 'refresh']);
    $router->post('/auth/logout', [AuthController::class, 'logout']);

    // ----- Authenticated user endpoints -----
    $router->get('/auth/me', [AuthController::class, 'me'], ['api_auth']);
    $router->post('/auth/watch-progress', [AuthController::class, 'updateWatchProgress'], ['api_auth']);
    $router->get('/auth/continue-watching', [AuthController::class, 'continueWatching'], ['api_auth']);
    $router->post('/auth/watchlist/toggle', [AuthController::class, 'toggleWatchlist'], ['api_auth']);
    $router->get('/auth/watchlist', [AuthController::class, 'getWatchlist'], ['api_auth']);
    $router->get('/auth/entitlements', [AuthController::class, 'entitlements'], ['api_auth']);

    // ----- Content (protected — requires Bearer token) -----

    // Manifest - version hashes for all content (players check this first)
    $router->get('/manifest', [ContentController::class, 'manifest'], ['api_auth']);

    // Channels
    $router->get('/channels', [ContentController::class, 'channels'], ['api_auth']);
    $router->get('/channels/{id}', [ContentController::class, 'channel'], ['api_auth']);

    // Movies
    $router->get('/movies', [ContentController::class, 'movies'], ['api_auth']);
    $router->get('/movies/featured', [ContentController::class, 'moviesFeatured'], ['api_auth']);
    $router->get('/movies/{id}', [ContentController::class, 'movie'], ['api_auth']);

    // Series / TV Shows
    $router->get('/series', [ContentController::class, 'seriesList'], ['api_auth']);
    $router->get('/series/{id}', [ContentController::class, 'seriesDetail'], ['api_auth']);

    // Episodes
    $router->get('/episodes/{id}', [ContentController::class, 'episode'], ['api_auth']);

    // Categories
    $router->get('/categories', [ContentController::class, 'categories'], ['api_auth']);

    // Search
    $router->get('/search', [ContentController::class, 'search'], ['api_auth']);

    // EPG (Electronic Programme Guide)
    $router->get('/epg', [EpgController::class, 'index'], ['api_auth']);
    $router->get('/epg/{channelId}', [EpgController::class, 'channel'], ['api_auth']);

    // App Configuration (layouts, navigation, pages)
    $router->get('/app/config/{platform}', [AppController::class, 'config'], ['api_auth']);
    $router->get('/app/layout/{platform}', [AppController::class, 'layout'], ['api_auth']);
    $router->get('/app/navigation/{platform}', [AppController::class, 'navigation'], ['api_auth']);
    $router->get('/app/pages/{platform}', [AppController::class, 'pages'], ['api_auth']);
});

// Custom 404 for API
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$hasMatch = false;

// Try to dispatch
ob_start();
try {
    $router->dispatch();
    $hasMatch = true;
} catch (\Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');

    $response = [
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'An internal error occurred.',
        ],
    ];

    // Include details in development
    if (getenv('APP_DEBUG') === 'true') {
        $response['error']['detail'] = $e->getMessage();
        $response['error']['file'] = $e->getFile() . ':' . $e->getLine();
    }

    echo json_encode($response);
    exit;
}

$output = ob_get_clean();

// If router didn't output anything (404 fell through to HTML error), send JSON 404
if (empty($output) || http_response_code() === 404) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode([
        'error' => [
            'code' => 'NOT_FOUND',
            'message' => 'API endpoint not found: ' . $uri,
        ],
    ]);
    exit;
}

echo $output;
