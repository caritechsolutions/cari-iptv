<?php
/**
 * CARI-IPTV Admin Portal Entry Point
 */

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__, 2) . '/storage/logs/php-error.log');

// Define base path
define('BASE_PATH', dirname(__DIR__, 2));

// Load environment variables from .env file
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

// Use statements
use CariIPTV\Core\Router;
use CariIPTV\Core\Session;
use CariIPTV\Middleware\AdminAuthMiddleware;
use CariIPTV\Controllers\Admin\AuthController;
use CariIPTV\Controllers\Admin\DashboardController;
use CariIPTV\Controllers\Admin\ProfileController;
use CariIPTV\Controllers\Admin\AdminUserController;
use CariIPTV\Controllers\Admin\SettingsController;
use CariIPTV\Controllers\Admin\ChannelController;

// Initialize session
Session::start();

// Create router
$router = new Router();

// Register middleware
$router->addMiddleware('auth', [AdminAuthMiddleware::class, 'handle']);
$router->addMiddleware('guest', [AdminAuthMiddleware::class, 'guest']);

// Guest routes (login, forgot password)
$router->group(['prefix' => 'admin', 'middleware' => ['guest']], function ($router) {
    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
    $router->post('/forgot-password', [AuthController::class, 'forgotPassword']);
    $router->get('/reset-password/{token}', [AuthController::class, 'showResetPassword']);
    $router->post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Authenticated admin routes
$router->group(['prefix' => 'admin', 'middleware' => ['auth']], function ($router) {
    // Dashboard
    $router->get('/', [DashboardController::class, 'index']);
    $router->get('/dashboard', [DashboardController::class, 'index']);

    // Logout (no CSRF for GET logout is intentional for simplicity)
    $router->get('/logout', [AuthController::class, 'logout']);

    // Profile
    $router->get('/profile', [ProfileController::class, 'index']);
    $router->post('/profile/update', [ProfileController::class, 'update']);
    $router->post('/profile/password', [ProfileController::class, 'changePassword']);
    $router->post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    $router->get('/profile/avatar/remove', [ProfileController::class, 'removeAvatar']);

    // Admin Users Management
    $router->get('/admins', [AdminUserController::class, 'index']);
    $router->get('/admins/create', [AdminUserController::class, 'create']);
    $router->post('/admins/store', [AdminUserController::class, 'store']);
    $router->get('/admins/{id}/edit', [AdminUserController::class, 'edit']);
    $router->post('/admins/{id}/update', [AdminUserController::class, 'update']);
    $router->post('/admins/{id}/delete', [AdminUserController::class, 'delete']);
    $router->post('/admins/{id}/toggle-status', [AdminUserController::class, 'toggleStatus']);
    $router->post('/admins/{id}/reset-password', [AdminUserController::class, 'resetPassword']);

    // Settings
    $router->get('/settings', [SettingsController::class, 'index']);
    $router->post('/settings/general', [SettingsController::class, 'updateGeneral']);
    $router->post('/settings/smtp', [SettingsController::class, 'updateSmtp']);
    $router->post('/settings/test-email', [SettingsController::class, 'testEmail']);

    // Channel Management
    $router->get('/channels', [ChannelController::class, 'index']);
    $router->get('/channels/create', [ChannelController::class, 'create']);
    $router->post('/channels/store', [ChannelController::class, 'store']);
    $router->get('/channels/{id}/edit', [ChannelController::class, 'edit']);
    $router->post('/channels/{id}/update', [ChannelController::class, 'update']);
    $router->post('/channels/{id}/delete', [ChannelController::class, 'delete']);
    $router->post('/channels/{id}/toggle-status', [ChannelController::class, 'toggleStatus']);
    $router->get('/channels/{id}/remove-logo', [ChannelController::class, 'removeLogo']);
    $router->post('/channels/bulk', [ChannelController::class, 'bulkAction']);

    // TODO: Add more routes as we build out the admin panel
    // $router->get('/vod', [VodController::class, 'index']);
    // etc.
});

// Dispatch the request
$router->dispatch();
