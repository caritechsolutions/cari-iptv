<?php
/**
 * Populate cast data from TMDB for all movies and series
 * Run: php /var/www/cari-iptv/public/populate-cast.php
 * DELETE THIS FILE after use!
 */

header('Content-Type: text/plain; charset=utf-8');
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Load env
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value, " \t\n\r\0\x0B\"'"));
    }
}

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'CariIPTV\\';
    $baseDir = BASE_PATH . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require $file;
});

use CariIPTV\Core\Database;
use CariIPTV\Services\SettingsService;

$db = Database::getInstance();

// Check TMDB API key
$settings = new SettingsService();
$tmdbKey = $settings->get('tmdb_api_key', '', 'api_keys');
if (empty($tmdbKey)) {
    die("[FAIL] No TMDB API key configured. Go to Admin > Settings > API Keys and add your TMDB key.\n");
}

echo "=== POPULATE CAST FROM TMDB ===\n\n";
echo "TMDB API key: " . substr($tmdbKey, 0, 4) . "..." . substr($tmdbKey, -4) . "\n\n";

$TMDB_API = 'https://api.themoviedb.org/3';
$TMDB_IMG = 'https://image.tmdb.org/t/p';

function tmdbFetch(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) return null;
    return json_decode($resp, true);
}

// ---- MOVIES ----
echo "--- MOVIES ---\n";
$movies = $db->fetchAll("SELECT id, title, tmdb_id FROM movies WHERE tmdb_id IS NOT NULL AND tmdb_id > 0");
echo "Found " . count($movies) . " movies with TMDB IDs\n\n";

$movieCastCount = 0;
foreach ($movies as $movie) {
    $tmdbId = $movie['tmdb_id'];
    echo "  [{$movie['id']}] \"{$movie['title']}\" (tmdb:{$tmdbId}) ... ";

    // Check if already has cast
    $existing = $db->fetch("SELECT COUNT(*) as c FROM movie_cast WHERE movie_id = ?", [$movie['id']]);
    if ($existing['c'] > 0) {
        echo "already has {$existing['c']} cast, skipping\n";
        continue;
    }

    $url = "{$TMDB_API}/movie/{$tmdbId}?api_key={$tmdbKey}&append_to_response=credits";
    $data = tmdbFetch($url);

    if (!$data || empty($data['credits']['cast'])) {
        echo "no cast from TMDB\n";
        continue;
    }

    $sortOrder = 0;
    $inserted = 0;
    foreach (array_slice($data['credits']['cast'], 0, 15) as $member) {
        $db->insert('movie_cast', [
            'movie_id' => $movie['id'],
            'name' => $member['name'],
            'character_name' => $member['character'] ?? null,
            'role' => 'actor',
            'profile_url' => $member['profile_path'] ? "{$TMDB_IMG}/w185{$member['profile_path']}" : null,
            'tmdb_person_id' => $member['id'] ?? null,
            'sort_order' => $sortOrder++,
        ]);
        $inserted++;
    }

    // Also save directors as cast with role=director
    if (!empty($data['credits']['crew'])) {
        foreach ($data['credits']['crew'] as $crew) {
            if ($crew['job'] === 'Director') {
                $db->insert('movie_cast', [
                    'movie_id' => $movie['id'],
                    'name' => $crew['name'],
                    'character_name' => 'Director',
                    'role' => 'director',
                    'profile_url' => $crew['profile_path'] ? "{$TMDB_IMG}/w185{$crew['profile_path']}" : null,
                    'tmdb_person_id' => $crew['id'] ?? null,
                    'sort_order' => $sortOrder++,
                ]);
                $inserted++;
            }
        }
    }

    echo "added {$inserted} cast members\n";
    $movieCastCount += $inserted;

    usleep(250000); // 250ms delay to respect TMDB rate limits
}

// ---- MOVIES WITHOUT TMDB ID — try to search ----
echo "\n--- MOVIES WITHOUT TMDB ID (will search TMDB) ---\n";
$noTmdb = $db->fetchAll("SELECT id, title, year FROM movies WHERE tmdb_id IS NULL OR tmdb_id = 0");
echo "Found " . count($noTmdb) . " movies without TMDB IDs\n\n";

foreach ($noTmdb as $movie) {
    echo "  [{$movie['id']}] \"{$movie['title']}\" ({$movie['year']}) ... ";

    $q = urlencode($movie['title']);
    $searchUrl = "{$TMDB_API}/search/movie?api_key={$tmdbKey}&query={$q}";
    if ($movie['year']) {
        $searchUrl .= "&year={$movie['year']}";
    }
    $results = tmdbFetch($searchUrl);

    if (!$results || empty($results['results'])) {
        echo "not found on TMDB\n";
        continue;
    }

    $tmdbId = $results['results'][0]['id'];
    $tmdbTitle = $results['results'][0]['title'];
    echo "matched \"{$tmdbTitle}\" (tmdb:{$tmdbId}) ... ";

    // Save tmdb_id
    $db->execute("UPDATE movies SET tmdb_id = ? WHERE id = ?", [$tmdbId, $movie['id']]);

    // Fetch details with credits
    $url = "{$TMDB_API}/movie/{$tmdbId}?api_key={$tmdbKey}&append_to_response=credits";
    $data = tmdbFetch($url);

    if (!$data || empty($data['credits']['cast'])) {
        echo "no cast\n";
        continue;
    }

    $sortOrder = 0;
    $inserted = 0;
    foreach (array_slice($data['credits']['cast'], 0, 15) as $member) {
        $db->insert('movie_cast', [
            'movie_id' => $movie['id'],
            'name' => $member['name'],
            'character_name' => $member['character'] ?? null,
            'role' => 'actor',
            'profile_url' => $member['profile_path'] ? "{$TMDB_IMG}/w185{$member['profile_path']}" : null,
            'tmdb_person_id' => $member['id'] ?? null,
            'sort_order' => $sortOrder++,
        ]);
        $inserted++;
    }

    if (!empty($data['credits']['crew'])) {
        foreach ($data['credits']['crew'] as $crew) {
            if ($crew['job'] === 'Director') {
                $db->insert('movie_cast', [
                    'movie_id' => $movie['id'],
                    'name' => $crew['name'],
                    'character_name' => 'Director',
                    'role' => 'director',
                    'profile_url' => $crew['profile_path'] ? "{$TMDB_IMG}/w185{$crew['profile_path']}" : null,
                    'tmdb_person_id' => $crew['id'] ?? null,
                    'sort_order' => $sortOrder++,
                ]);
                $inserted++;
            }
        }
    }

    echo "added {$inserted} cast\n";
    $movieCastCount += $inserted;

    usleep(300000); // 300ms delay
}

// ---- SERIES ----
echo "\n--- SERIES ---\n";
$seriesList = $db->fetchAll("SELECT id, title, tmdb_id FROM series WHERE tmdb_id IS NOT NULL AND tmdb_id > 0");
echo "Found " . count($seriesList) . " series with TMDB IDs\n\n";

$seriesCastCount = 0;
foreach ($seriesList as $show) {
    $tmdbId = $show['tmdb_id'];
    echo "  [{$show['id']}] \"{$show['title']}\" (tmdb:{$tmdbId}) ... ";

    $existing = $db->fetch("SELECT COUNT(*) as c FROM series_cast WHERE series_id = ?", [$show['id']]);
    if ($existing['c'] > 0) {
        echo "already has {$existing['c']} cast, skipping\n";
        continue;
    }

    $url = "{$TMDB_API}/tv/{$tmdbId}?api_key={$tmdbKey}&append_to_response=credits";
    $data = tmdbFetch($url);

    if (!$data || empty($data['credits']['cast'])) {
        echo "no cast from TMDB\n";
        continue;
    }

    $sortOrder = 0;
    $inserted = 0;
    foreach (array_slice($data['credits']['cast'], 0, 15) as $member) {
        $db->insert('series_cast', [
            'series_id' => $show['id'],
            'name' => $member['name'],
            'character_name' => $member['character'] ?? null,
            'role' => 'actor',
            'profile_url' => $member['profile_path'] ? "{$TMDB_IMG}/w185{$member['profile_path']}" : null,
            'tmdb_person_id' => $member['id'] ?? null,
            'sort_order' => $sortOrder++,
        ]);
        $inserted++;
    }

    echo "added {$inserted} cast members\n";
    $seriesCastCount += $inserted;

    usleep(250000);
}

// ---- SERIES WITHOUT TMDB ID ----
echo "\n--- SERIES WITHOUT TMDB ID (will search TMDB) ---\n";
$noTmdbSeries = $db->fetchAll("SELECT id, title, year FROM series WHERE tmdb_id IS NULL OR tmdb_id = 0");
echo "Found " . count($noTmdbSeries) . " series without TMDB IDs\n\n";

foreach ($noTmdbSeries as $show) {
    echo "  [{$show['id']}] \"{$show['title']}\" ({$show['year']}) ... ";

    $q = urlencode($show['title']);
    $searchUrl = "{$TMDB_API}/search/tv?api_key={$tmdbKey}&query={$q}";
    if ($show['year']) {
        $searchUrl .= "&first_air_date_year={$show['year']}";
    }
    $results = tmdbFetch($searchUrl);

    if (!$results || empty($results['results'])) {
        echo "not found on TMDB\n";
        continue;
    }

    $tmdbId = $results['results'][0]['id'];
    $tmdbTitle = $results['results'][0]['name'];
    echo "matched \"{$tmdbTitle}\" (tmdb:{$tmdbId}) ... ";

    $db->execute("UPDATE series SET tmdb_id = ? WHERE id = ?", [$tmdbId, $show['id']]);

    $url = "{$TMDB_API}/tv/{$tmdbId}?api_key={$tmdbKey}&append_to_response=credits";
    $data = tmdbFetch($url);

    if (!$data || empty($data['credits']['cast'])) {
        echo "no cast\n";
        continue;
    }

    $sortOrder = 0;
    $inserted = 0;
    foreach (array_slice($data['credits']['cast'], 0, 15) as $member) {
        $db->insert('series_cast', [
            'series_id' => $show['id'],
            'name' => $member['name'],
            'character_name' => $member['character'] ?? null,
            'role' => 'actor',
            'profile_url' => $member['profile_path'] ? "{$TMDB_IMG}/w185{$member['profile_path']}" : null,
            'tmdb_person_id' => $member['id'] ?? null,
            'sort_order' => $sortOrder++,
        ]);
        $inserted++;
    }

    echo "added {$inserted} cast\n";
    $seriesCastCount += $inserted;

    usleep(300000);
}

echo "\n=== SUMMARY ===\n";
echo "Movie cast members added: {$movieCastCount}\n";
echo "Series cast members added: {$seriesCastCount}\n";

$totalMovie = $db->fetch("SELECT COUNT(*) as c FROM movie_cast")['c'];
$totalSeries = $db->fetch("SELECT COUNT(*) as c FROM series_cast")['c'];
echo "Total movie_cast rows: {$totalMovie}\n";
echo "Total series_cast rows: {$totalSeries}\n";
echo "\nDone! Now test cast in the player.\n";
echo "REMEMBER: Delete this file when done: rm /var/www/cari-iptv/public/populate-cast.php\n";
