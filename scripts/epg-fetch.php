#!/usr/bin/env php
<?php
/**
 * CARI-IPTV EPG Fetch Script
 *
 * Fetches EPG data from configured sources (EIT streams, XMLTV URLs).
 * Can be run from CLI or triggered by the admin panel.
 *
 * Usage:
 *   php scripts/epg-fetch.php                     # Fetch sources due for refresh (auto_refresh enabled)
 *   php scripts/epg-fetch.php --all               # Fetch ALL active sources regardless of schedule
 *   php scripts/epg-fetch.php --source-id=1       # Fetch specific source
 *   php scripts/epg-fetch.php --type=eit          # Fetch all EIT sources
 *   php scripts/epg-fetch.php --cleanup           # Remove old programme data
 *   php scripts/epg-fetch.php --cleanup-days=3    # Remove programmes older than 3 days
 *
 * Cron (runs every 5 min, only fetches sources whose refresh_interval has elapsed):
 *   See /etc/cron.d/cari-iptv (installed by install.sh / update.sh)
 */

// Prevent overlapping runs via lock file
$lockFile = sys_get_temp_dir() . '/cari_epg_fetch.lock';
$lockFp = fopen($lockFile, 'w');
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    // Another instance is running
    exit(0);
}

// Disable time limit for long-running fetches
set_time_limit(0);

// Bootstrap
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
    $baseDir = BASE_PATH . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use CariIPTV\Services\EpgService;

// Parse CLI arguments
$options = getopt('', ['source-id:', 'type:', 'all', 'cleanup', 'cleanup-days:', 'help']);

if (isset($options['help'])) {
    echo "CARI-IPTV EPG Fetch Script\n\n";
    echo "Options:\n";
    echo "  --source-id=ID    Fetch a specific source by ID\n";
    echo "  --type=TYPE       Fetch sources of type: eit, xmltv_url\n";
    echo "  --all             Fetch ALL active sources (ignore schedule)\n";
    echo "  --cleanup         Remove expired programme data\n";
    echo "  --cleanup-days=N  Remove programmes older than N days (default: 1)\n";
    echo "  --help            Show this help\n";
    exit(0);
}

$service = new EpgService();

// Handle cleanup
if (isset($options['cleanup'])) {
    $days = (int) ($options['cleanup-days'] ?? 1);
    $deleted = $service->clearOldProgrammes($days);
    logMsg("Cleanup: removed {$deleted} expired programmes (older than {$days} day(s))");
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(0);
}

// Determine which sources to fetch
$filters = ['is_active' => 1];
$forceAll = isset($options['all']);
$specificSource = isset($options['source-id']);

if ($specificSource) {
    $source = $service->getSource((int) $options['source-id']);
    if (!$source) {
        logMsg("ERROR: Source ID {$options['source-id']} not found");
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        exit(1);
    }
    $sources = [$source];
} else {
    if (isset($options['type'])) {
        $filters['type'] = $options['type'];
    }
    $sources = $service->getSources($filters);
}

if (empty($sources)) {
    logMsg("No active EPG sources found");
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(0);
}

// Filter sources based on auto_refresh and refresh_interval (unless --all or --source-id)
if (!$forceAll && !$specificSource) {
    $dueSources = [];
    foreach ($sources as $source) {
        // Skip sources that don't have auto_refresh enabled
        if (empty($source['auto_refresh'])) {
            continue;
        }

        // Check if enough time has passed since last fetch
        $interval = (int) ($source['refresh_interval'] ?? 1800); // default 30 min
        if (!empty($source['last_fetch'])) {
            $lastFetch = strtotime($source['last_fetch']);
            $elapsed = time() - $lastFetch;
            if ($elapsed < $interval) {
                continue; // Not due yet
            }
        }

        $dueSources[] = $source;
    }
    $sources = $dueSources;
}

if (empty($sources)) {
    // No sources due for refresh — this is normal for cron runs, exit silently
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(0);
}

logMsg("Starting EPG fetch for " . count($sources) . " source(s)");

$totalProgrammes = 0;
$totalChannels = 0;
$errors = 0;

foreach ($sources as $source) {
    logMsg("Processing: {$source['name']} (type: {$source['type']}, id: {$source['id']})");

    switch ($source['type']) {
        case 'eit':
            $result = fetchEit($service, $source);
            break;

        case 'xmltv_url':
            $result = fetchXmltvUrl($service, $source);
            break;

        case 'xmltv_file':
            logMsg("  Skipping file-based source (use admin panel to upload)");
            continue 2;

        default:
            logMsg("  Unknown source type: {$source['type']}");
            $errors++;
            continue 2;
    }

    if ($result['success']) {
        $totalProgrammes += $result['programmes'];
        $totalChannels += $result['channels'];
        logMsg("  OK: {$result['programmes']} programmes, {$result['channels']} channels");
    } else {
        $errors++;
        logMsg("  ERROR: {$result['message']}");
    }
}

logMsg("Finished. Total: {$totalProgrammes} programmes, {$totalChannels} channels, {$errors} error(s)");

// Release lock
flock($lockFp, LOCK_UN);
fclose($lockFp);

// ============================================================================

function fetchEit(EpgService $service, array $source): array
{
    logMsg("  Extracting EIT from {$source['source_url']}:{$source['source_port']} " .
           "(PID: {$source['eit_pid']}, timeout: {$source['capture_timeout']}s)");

    $service->extractEit($source);

    $updated = $service->getSource($source['id']);
    return [
        'success' => $updated['last_status'] === 'success',
        'message' => $updated['last_message'] ?? 'Unknown error',
        'programmes' => (int) ($updated['programme_count'] ?? 0),
        'channels' => (int) ($updated['channel_count'] ?? 0),
    ];
}

function fetchXmltvUrl(EpgService $service, array $source): array
{
    $url = $source['source_url'];
    if (empty($url)) {
        $service->updateSourceStatus($source['id'], 'error', 'No URL configured');
        return ['success' => false, 'message' => 'No URL configured', 'programmes' => 0, 'channels' => 0];
    }

    logMsg("  Downloading XMLTV from: {$url}");
    $service->updateSourceStatus($source['id'], 'running', 'Downloading XMLTV...');

    $tmpFile = tempnam(sys_get_temp_dir(), 'xmltv_');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_USERAGENT => 'CARI-IPTV/1.0',
    ]);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($data)) {
        $service->updateSourceStatus($source['id'], 'error', "Download failed (HTTP {$httpCode})");
        unlink($tmpFile);
        return ['success' => false, 'message' => "Download failed (HTTP {$httpCode})", 'programmes' => 0, 'channels' => 0];
    }

    // Handle gzipped content
    if (str_ends_with($url, '.gz') || str_starts_with($data, "\x1f\x8b")) {
        $data = gzdecode($data);
    }

    file_put_contents($tmpFile, $data);
    logMsg("  Downloaded " . number_format(strlen($data)) . " bytes");

    $result = $service->importXmltvFile($source['id'], $tmpFile);
    unlink($tmpFile);

    if ($result['success']) {
        $service->updateSourceStatus($source['id'], 'success',
            "Imported {$result['programmes']} programmes for {$result['channels']} channels");
    } else {
        $service->updateSourceStatus($source['id'], 'error', $result['message']);
    }

    return $result;
}

function logMsg(string $msg): void
{
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] {$msg}\n";
}
