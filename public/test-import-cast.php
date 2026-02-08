<?php
/**
 * Test script: Verify TMDB import saves cast correctly
 * Run: php /var/www/cari-iptv/public/test-import-cast.php
 * Delete after: rm /var/www/cari-iptv/public/test-import-cast.php
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/src/Config/app.php';

// Manual autoloader
spl_autoload_register(function ($class) {
    $prefix = 'CariIPTV\\';
    $baseDir = BASE_PATH . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use CariIPTV\Core\Database;
use CariIPTV\Services\MetadataService;
use CariIPTV\Services\MovieService;

echo "=== TEST IMPORT CAST FLOW ===\n\n";

$db = Database::getInstance();

// Step 1: Check TMDB API
echo "Step 1: Fetching movie details from TMDB for 'Gladiator' (tmdb:98)...\n";
$metadata = new MetadataService();
$details = $metadata->getMovieDetails(98); // Gladiator

if (!$details) {
    die("[FAIL] MetadataService::getMovieDetails returned null\n");
}

echo "  Title: {$details['title']}\n";
echo "  Cast count in details: " . count($details['cast'] ?? []) . "\n";
echo "  Directors: " . implode(', ', $details['directors'] ?? []) . "\n";
echo "  Directors_detail count: " . count($details['directors_detail'] ?? []) . "\n";

if (empty($details['cast'])) {
    echo "\n[FAIL] No cast in MetadataService response! The formatMovieDetails bug persists.\n";
    echo "  Full details keys: " . implode(', ', array_keys($details)) . "\n";
    exit(1);
}

echo "\n  First 3 cast members:\n";
foreach (array_slice($details['cast'], 0, 3) as $i => $p) {
    echo "    [{$i}] {$p['name']} as {$p['character']}, profile: " . ($p['profile'] ? 'yes' : 'null') . ", tmdb_person_id: {$p['tmdb_person_id']}\n";
}

// Step 2: Check if movie already exists
echo "\nStep 2: Checking if Gladiator exists in DB...\n";
$existing = $db->fetch("SELECT id FROM movies WHERE tmdb_id = 98");
if ($existing) {
    echo "  Movie already exists with ID: {$existing['id']}\n";
    echo "  Checking existing cast...\n";
    $castCount = $db->fetch("SELECT COUNT(*) as cnt FROM movie_cast WHERE movie_id = ?", [$existing['id']]);
    echo "  Existing cast rows: {$castCount['cnt']}\n";

    if ((int)$castCount['cnt'] > 0) {
        echo "  [OK] Cast already exists. Testing backfill skip...\n";
        $movieService = new MovieService();
        $movieService->backfillCast((int)$existing['id'], $details);
        echo "  backfillCast completed (should have skipped since cast exists)\n";
    } else {
        echo "  [INFO] No cast — testing backfillCast...\n";
        $movieService = new MovieService();
        $movieService->backfillCast((int)$existing['id'], $details);
        $castCount = $db->fetch("SELECT COUNT(*) as cnt FROM movie_cast WHERE movie_id = ?", [$existing['id']]);
        echo "  After backfill: {$castCount['cnt']} cast rows\n";
    }
} else {
    echo "  Movie not in DB. Testing full importFromTmdb...\n";
    $movieService = new MovieService();
    $movieId = $movieService->importFromTmdb($details);
    echo "  Movie created with ID: {$movieId}\n";

    $castCount = $db->fetch("SELECT COUNT(*) as cnt FROM movie_cast WHERE movie_id = ?", [$movieId]);
    echo "  Cast rows saved: {$castCount['cnt']}\n";

    if ((int)$castCount['cnt'] > 0) {
        echo "\n  [SUCCESS] Cast was saved!\n";
        $castSample = $db->fetchAll("SELECT name, character_name, role FROM movie_cast WHERE movie_id = ? LIMIT 5", [$movieId]);
        foreach ($castSample as $c) {
            echo "    - {$c['name']} as {$c['character_name']} ({$c['role']})\n";
        }
    } else {
        echo "\n  [FAIL] Cast was NOT saved! Check error logs.\n";
    }

    // Clean up test movie
    echo "\n  Cleaning up test movie (ID: {$movieId})...\n";
    $db->delete('movie_cast', 'movie_id = ?', [$movieId]);
    $db->delete('movie_categories', 'movie_id = ?', [$movieId]);
    $db->delete('movies', 'id = ?', [$movieId]);
    echo "  Cleaned up.\n";
}

echo "\nStep 3: Check error log for MovieService entries...\n";
$logFile = BASE_PATH . '/storage/logs/php-error.log';
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    $movieServiceLines = array_filter($lines, fn($l) => str_contains($l, '[MovieService]'));
    $recent = array_slice($movieServiceLines, -10);
    foreach ($recent as $line) {
        echo "  {$line}\n";
    }
} else {
    echo "  Log file not found at {$logFile}\n";
}

echo "\nDone! Delete this file: rm /var/www/cari-iptv/public/test-import-cast.php\n";
