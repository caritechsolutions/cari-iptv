<?php
/**
 * CARI-IPTV Recommendation Generator Cron Job
 * Run daily: php /var/www/cari-iptv/cron/generate-recommendations.php
 *
 * Processes all active subscribers who need profile/recommendation refresh.
 * Generates AI taste profiles and pre-computes recommendation sets.
 */

define('BASE_PATH', dirname(__DIR__));

// Load environment
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
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
    $relativeClass = substr($class, strlen($prefix));
    $file = BASE_PATH . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use CariIPTV\Services\RecommendationService;
use CariIPTV\Services\AnalyticsService;

$startTime = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Starting recommendation generation...\n";

try {
    $service = new RecommendationService();
    $results = $service->processAllSubscribers(100); // Process up to 100 subscribers per run

    $elapsed = round(microtime(true) - $startTime, 2);
    echo "[" . date('Y-m-d H:i:s') . "] Completed in {$elapsed}s\n";
    echo "  Subscribers processed: {$results['processed']}\n";
    echo "  Profiles generated:    {$results['profiles']}\n";
    echo "  Recommendation sets:   {$results['recommendations']}\n";
    echo "  Errors:                {$results['errors']}\n";

    // Prune old events (keep 90 days of raw data)
    $analytics = new AnalyticsService();
    $pruned = $analytics->pruneEvents(90);
    echo "  Events pruned:         {$pruned}\n";
} catch (\Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
