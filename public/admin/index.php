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
use CariIPTV\Controllers\Admin\MovieController;
use CariIPTV\Controllers\Admin\SeriesController;
use CariIPTV\Controllers\Admin\CategoryController;
use CariIPTV\Controllers\Admin\EpgController;
use CariIPTV\Controllers\Admin\AppLayoutController;
use CariIPTV\Controllers\Admin\AdController;
use CariIPTV\Controllers\Admin\SubscriberController;
use CariIPTV\Controllers\Admin\PackageController;
use CariIPTV\Controllers\Admin\VodServerController;

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
    $router->post('/dashboard/widgets/add', [DashboardController::class, 'addWidget']);
    $router->post('/dashboard/widgets/remove', [DashboardController::class, 'removeWidget']);
    $router->post('/dashboard/widgets/reorder', [DashboardController::class, 'reorderWidgets']);
    $router->get('/dashboard/widgets/data', [DashboardController::class, 'widgetData']);

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
    $router->post('/settings/ai', [SettingsController::class, 'updateAI']);
    $router->post('/settings/metadata', [SettingsController::class, 'updateMetadata']);
    $router->post('/settings/image', [SettingsController::class, 'updateImage']);
    $router->post('/settings/test-ai', [SettingsController::class, 'testAI']);
    $router->post('/settings/test-ollama', [SettingsController::class, 'testOllama']);
    $router->post('/settings/test-fanart', [SettingsController::class, 'testFanart']);
    $router->post('/settings/test-tmdb', [SettingsController::class, 'testTmdb']);
    $router->post('/settings/youtube', [SettingsController::class, 'updateYoutube']);
    $router->post('/settings/test-youtube', [SettingsController::class, 'testYoutube']);

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
    $router->post('/channels/search-logos', [ChannelController::class, 'searchLogos']);
    $router->post('/channels/generate-description', [ChannelController::class, 'generateDescription']);

    // Channel IPTV-org Import
    $router->post('/channels/search-iptv-org', [ChannelController::class, 'searchIptvOrg']);
    $router->get('/channels/iptv-org-countries', [ChannelController::class, 'iptvOrgCountries']);
    $router->post('/channels/import-iptv-org', [ChannelController::class, 'importIptvOrg']);

    // Movie Management
    $router->get('/movies', [MovieController::class, 'index']);
    $router->get('/movies/create', [MovieController::class, 'create']);
    $router->post('/movies/store', [MovieController::class, 'store']);
    $router->get('/movies/browse-free', [MovieController::class, 'browseFreeContent']);
    $router->get('/movies/{id}/edit', [MovieController::class, 'edit']);
    $router->post('/movies/{id}/update', [MovieController::class, 'update']);
    $router->post('/movies/{id}/delete', [MovieController::class, 'delete']);
    $router->post('/movies/{id}/toggle-featured', [MovieController::class, 'toggleFeatured']);
    $router->post('/movies/{id}/status', [MovieController::class, 'updateStatus']);
    $router->post('/movies/bulk', [MovieController::class, 'bulkAction']);

    // Movie Metadata Search
    $router->post('/movies/search-tmdb', [MovieController::class, 'searchTmdb']);
    $router->post('/movies/tmdb-details', [MovieController::class, 'getTmdbDetails']);
    $router->post('/movies/search-fanart', [MovieController::class, 'searchFanart']);
    $router->post('/movies/search-trailers', [MovieController::class, 'searchTrailers']);
    $router->post('/movies/import-tmdb', [MovieController::class, 'importFromTmdb']);

    // Movie Trailers
    $router->post('/movies/{id}/trailers/add', [MovieController::class, 'addTrailer']);
    $router->post('/movies/{id}/trailers/{trailerId}/remove', [MovieController::class, 'removeTrailer']);

    // Free Content
    $router->post('/movies/search-free', [MovieController::class, 'searchFreeContent']);
    $router->post('/movies/import-free', [MovieController::class, 'importFreeContent']);
    $router->post('/movies/{id}/process-images', [MovieController::class, 'processMovieImages']);

    // TV Shows (Series) Management
    $router->get('/series', [SeriesController::class, 'index']);
    $router->get('/series/create', [SeriesController::class, 'create']);
    $router->post('/series/store', [SeriesController::class, 'store']);
    $router->get('/series/{id}/edit', [SeriesController::class, 'edit']);
    $router->post('/series/{id}/update', [SeriesController::class, 'update']);
    $router->post('/series/{id}/delete', [SeriesController::class, 'delete']);
    $router->post('/series/{id}/toggle-featured', [SeriesController::class, 'toggleFeatured']);
    $router->post('/series/{id}/status', [SeriesController::class, 'updateStatus']);
    $router->post('/series/bulk', [SeriesController::class, 'bulkAction']);

    // TV Shows Metadata Search
    $router->post('/series/search-tmdb', [SeriesController::class, 'searchTmdb']);
    $router->post('/series/tmdb-details', [SeriesController::class, 'getTmdbDetails']);
    $router->post('/series/search-fanart', [SeriesController::class, 'searchFanart']);
    $router->post('/series/search-trailers', [SeriesController::class, 'searchTrailers']);
    $router->post('/series/import-tmdb', [SeriesController::class, 'importFromTmdb']);
    $router->post('/series/{id}/process-images', [SeriesController::class, 'processShowImages']);

    // TV Shows Seasons
    $router->get('/series/{id}/seasons', [SeriesController::class, 'seasons']);
    $router->post('/series/{id}/seasons/add', [SeriesController::class, 'addSeason']);
    $router->post('/series/{id}/seasons/{seasonId}/update', [SeriesController::class, 'updateSeason']);
    $router->post('/series/{id}/seasons/{seasonId}/delete', [SeriesController::class, 'deleteSeason']);
    $router->post('/series/{id}/seasons/{seasonId}/fetch-tmdb', [SeriesController::class, 'fetchSeasonTmdb']);
    $router->post('/series/{id}/seasons/import', [SeriesController::class, 'importSeasons']);

    // TV Shows Episodes
    $router->get('/series/{id}/seasons/{seasonId}/episodes', [SeriesController::class, 'episodes']);
    $router->post('/series/{id}/seasons/{seasonId}/episodes/add', [SeriesController::class, 'addEpisode']);
    $router->post('/series/{id}/seasons/{seasonId}/episodes/{episodeId}/update', [SeriesController::class, 'updateEpisode']);
    $router->post('/series/{id}/seasons/{seasonId}/episodes/{episodeId}/delete', [SeriesController::class, 'deleteEpisode']);

    // TV Shows Season Trailers
    $router->post('/series/{id}/seasons/{seasonId}/trailers/add', [SeriesController::class, 'addTrailer']);
    $router->post('/series/{id}/seasons/{seasonId}/trailers/{trailerId}/remove', [SeriesController::class, 'removeTrailer']);

    // Categories Management
    $router->get('/categories', [CategoryController::class, 'index']);
    $router->post('/categories/store', [CategoryController::class, 'store']);
    $router->post('/categories/{id}/update', [CategoryController::class, 'update']);
    $router->post('/categories/{id}/delete', [CategoryController::class, 'delete']);
    $router->post('/categories/{id}/toggle-active', [CategoryController::class, 'toggleActive']);
    $router->post('/categories/update-order', [CategoryController::class, 'updateOrder']);

    // EPG Management
    $router->get('/epg', [EpgController::class, 'index']);
    $router->get('/epg/programmes', [EpgController::class, 'programmes']);
    $router->post('/epg/store', [EpgController::class, 'store']);
    $router->post('/epg/{id}/update', [EpgController::class, 'update']);
    $router->post('/epg/{id}/delete', [EpgController::class, 'delete']);
    $router->post('/epg/{id}/fetch', [EpgController::class, 'fetch']);
    $router->post('/epg/{id}/upload', [EpgController::class, 'upload']);
    $router->get('/epg/{id}/mappings', [EpgController::class, 'mappings']);
    $router->post('/epg/save-mapping', [EpgController::class, 'saveMapping']);
    $router->post('/epg/{id}/auto-map', [EpgController::class, 'autoMap']);
    $router->post('/epg/cleanup', [EpgController::class, 'cleanup']);
    $router->post('/epg/process-metadata', [EpgController::class, 'processMetadata']);

    // App Layout Management
    $router->get('/app-layout', [AppLayoutController::class, 'index']);
    $router->post('/app-layout/store', [AppLayoutController::class, 'store']);
    $router->get('/app-layout/search-content', [AppLayoutController::class, 'searchContent']);
    $router->get('/app-layout/search-tmdb', [AppLayoutController::class, 'searchTmdb']);
    $router->post('/app-layout/import-tmdb-item', [AppLayoutController::class, 'importTmdbItem']);
    $router->post('/app-layout/upload-item-image', [AppLayoutController::class, 'uploadItemImage']);
    $router->get('/app-layout/{id}/builder', [AppLayoutController::class, 'builder']);
    $router->post('/app-layout/{id}/update', [AppLayoutController::class, 'update']);
    $router->post('/app-layout/{id}/delete', [AppLayoutController::class, 'delete']);
    $router->post('/app-layout/{id}/duplicate', [AppLayoutController::class, 'duplicate']);
    $router->post('/app-layout/{id}/publish', [AppLayoutController::class, 'publish']);
    $router->post('/app-layout/{id}/unpublish', [AppLayoutController::class, 'unpublish']);

    // App Layout Sections
    $router->post('/app-layout/{id}/sections/add', [AppLayoutController::class, 'addSection']);
    $router->post('/app-layout/{id}/sections/reorder', [AppLayoutController::class, 'reorderSections']);
    $router->post('/app-layout/{id}/sections/{sectionId}/update', [AppLayoutController::class, 'updateSection']);
    $router->post('/app-layout/{id}/sections/{sectionId}/delete', [AppLayoutController::class, 'deleteSection']);

    // App Layout Section Items
    $router->post('/app-layout/{id}/sections/{sectionId}/items/add', [AppLayoutController::class, 'addItem']);
    $router->post('/app-layout/{id}/sections/{sectionId}/items/reorder', [AppLayoutController::class, 'reorderItems']);
    $router->post('/app-layout/{id}/sections/{sectionId}/items/{itemId}/remove', [AppLayoutController::class, 'removeItem']);

    // App Pages
    $router->get('/app-layout/pages', [AppLayoutController::class, 'pages']);
    $router->post('/app-layout/pages/store', [AppLayoutController::class, 'storePage']);
    $router->post('/app-layout/pages/reorder', [AppLayoutController::class, 'reorderPages']);
    $router->post('/app-layout/pages/{id}/update', [AppLayoutController::class, 'updatePage']);
    $router->post('/app-layout/pages/{id}/delete', [AppLayoutController::class, 'deletePage']);

    // App Navigation
    $router->post('/app-layout/navigation/save', [AppLayoutController::class, 'saveNavigation']);
    $router->post('/app-layout/navigation/items/add', [AppLayoutController::class, 'addNavItem']);
    $router->post('/app-layout/navigation/items/reorder', [AppLayoutController::class, 'reorderNavItems']);
    $router->post('/app-layout/navigation/items/{id}/update', [AppLayoutController::class, 'updateNavItem']);
    $router->post('/app-layout/navigation/items/{id}/remove', [AppLayoutController::class, 'removeNavItem']);

    // Advertising - Campaigns
    $router->get('/ads', [AdController::class, 'index']);
    $router->get('/ads/create', [AdController::class, 'create']);
    $router->post('/ads/store', [AdController::class, 'store']);
    $router->get('/ads/{id}/edit', [AdController::class, 'edit']);
    $router->post('/ads/{id}/update', [AdController::class, 'update']);
    $router->post('/ads/{id}/delete', [AdController::class, 'delete']);
    $router->post('/ads/{id}/toggle-status', [AdController::class, 'toggleStatus']);
    $router->post('/ads/bulk', [AdController::class, 'bulkAction']);

    // Advertising - Creatives
    $router->post('/ads/{id}/creatives/add', [AdController::class, 'addCreative']);
    $router->post('/ads/{id}/creatives/{creativeId}/update', [AdController::class, 'updateCreative']);
    $router->post('/ads/{id}/creatives/{creativeId}/delete', [AdController::class, 'deleteCreative']);

    // Advertising - Placements & Targeting
    $router->post('/ads/{id}/placements/add', [AdController::class, 'addPlacement']);
    $router->post('/ads/{id}/placements/{placementId}/update', [AdController::class, 'updatePlacement']);
    $router->post('/ads/{id}/placements/{placementId}/delete', [AdController::class, 'deletePlacement']);

    // Advertising - Zones
    $router->get('/ads/zones', [AdController::class, 'zones']);
    $router->post('/ads/zones/store', [AdController::class, 'storeZone']);
    $router->post('/ads/zones/{id}/update', [AdController::class, 'updateZone']);
    $router->post('/ads/zones/{id}/toggle', [AdController::class, 'toggleZone']);
    $router->post('/ads/zones/{id}/delete', [AdController::class, 'deleteZone']);

    // Advertising - Reports
    $router->get('/ads/reports', [AdController::class, 'reports']);
    $router->get('/ads/{id}/report', [AdController::class, 'campaignReport']);

    // Advertising - AI & Uploads
    $router->post('/ads/ai/generate-text', [AdController::class, 'generateAdText']);
    $router->post('/ads/ai/generate-image', [AdController::class, 'generateAdImage']);
    $router->post('/ads/upload/image', [AdController::class, 'uploadAdImage']);
    $router->post('/ads/upload/video', [AdController::class, 'uploadAdVideo']);

    // Advertising - Ad Serving API
    $router->get('/ads/api/serve', [AdController::class, 'serve']);
    $router->post('/ads/api/impression', [AdController::class, 'recordImpression']);
    $router->post('/ads/api/event', [AdController::class, 'recordEvent']);
    $router->get('/ads/api/vast', [AdController::class, 'vastXml']);

    // Subscriber Management
    $router->get('/subscribers', [SubscriberController::class, 'index']);
    $router->get('/subscribers/{id}/show', [SubscriberController::class, 'show']);
    $router->post('/subscribers/store', [SubscriberController::class, 'store']);
    $router->post('/subscribers/{id}/update', [SubscriberController::class, 'update']);
    $router->post('/subscribers/{id}/delete', [SubscriberController::class, 'delete']);
    $router->post('/subscribers/{id}/toggle-status', [SubscriberController::class, 'toggleStatus']);
    $router->post('/subscribers/bulk', [SubscriberController::class, 'bulkAction']);

    // Subscriber Groups
    $router->post('/subscribers/groups/store', [SubscriberController::class, 'storeGroup']);
    $router->post('/subscribers/groups/{id}/update', [SubscriberController::class, 'updateGroup']);
    $router->post('/subscribers/groups/{id}/delete', [SubscriberController::class, 'deleteGroup']);

    // Packages & Content Groups
    $router->get('/packages', [PackageController::class, 'index']);
    $router->get('/packages/search-content', [PackageController::class, 'searchContent']);
    $router->post('/packages/store', [PackageController::class, 'storePackage']);
    $router->get('/packages/{id}/show', [PackageController::class, 'showPackage']);
    $router->post('/packages/{id}/update', [PackageController::class, 'updatePackage']);
    $router->post('/packages/{id}/delete', [PackageController::class, 'deletePackage']);

    // Content Groups
    $router->post('/packages/groups/store', [PackageController::class, 'storeGroup']);
    $router->get('/packages/groups/{id}/show', [PackageController::class, 'showGroup']);
    $router->post('/packages/groups/{id}/update', [PackageController::class, 'updateGroup']);
    $router->post('/packages/groups/{id}/delete', [PackageController::class, 'deleteGroup']);
    $router->post('/packages/groups/{id}/content/add', [PackageController::class, 'addGroupContent']);
    $router->post('/packages/groups/{id}/content/{iid}/remove', [PackageController::class, 'removeGroupContent']);

    // VOD Server Integration
    $router->get('/vod-server', [VodServerController::class, 'index']);
    $router->get('/vod-server/status', [VodServerController::class, 'status']);
    $router->get('/vod-server/config', [VodServerController::class, 'config']);
    $router->get('/vod-server/content', [VodServerController::class, 'content']);
    $router->post('/vod-server/content/delete', [VodServerController::class, 'deleteContent']);
    $router->get('/vod-server/jobs', [VodServerController::class, 'jobs']);
    $router->get('/vod-server/job-detail', [VodServerController::class, 'jobDetail']);
    $router->post('/vod-server/jobs/submit', [VodServerController::class, 'submitJob']);
    $router->post('/vod-server/jobs/{id}/cancel', [VodServerController::class, 'cancelJob']);
    $router->get('/vod-server/browse', [VodServerController::class, 'browseFiles']);
    $router->post('/vod-server/test-connection', [VodServerController::class, 'testConnection']);
    $router->post('/vod-server/settings/save', [VodServerController::class, 'saveSettings']);
});

// Dispatch the request
$router->dispatch();
