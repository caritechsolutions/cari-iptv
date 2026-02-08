<?php
/**
 * Cast Diagnostic Script
 * Run: php /var/www/cari-iptv/public/cast-diag.php
 * Or visit: http://your-server/cast-diag.php
 * DELETE THIS FILE after debugging!
 */

header('Content-Type: text/plain; charset=utf-8');

// Load env
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value, " \t\n\r\0\x0B\"'"));
    }
}

$config = require dirname(__DIR__) . '/src/Config/database.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    echo "=== CARI-IPTV CAST DIAGNOSTIC ===\n\n";
    echo "[OK] Database connected: {$config['database']}\n\n";
} catch (Exception $e) {
    die("[FAIL] DB connection: " . $e->getMessage() . "\n");
}

// 1. Check tables exist
echo "--- TABLE CHECK ---\n";
$tables = ['movies', 'movie_cast', 'movie_trailers', 'series', 'series_cast', 'content_group_items', 'packages'];
foreach ($tables as $t) {
    try {
        $pdo->query("SELECT 1 FROM `$t` LIMIT 1");
        $cnt = $pdo->query("SELECT COUNT(*) as c FROM `$t`")->fetch()['c'];
        echo "[OK]   $t — $cnt rows\n";
    } catch (Exception $e) {
        echo "[MISS] $t — TABLE DOES NOT EXIST\n";
    }
}

// 2. Check movie_cast columns
echo "\n--- MOVIE_CAST COLUMNS ---\n";
try {
    $cols = $pdo->query("DESCRIBE movie_cast")->fetchAll();
    foreach ($cols as $col) {
        echo "  {$col['Field']} ({$col['Type']}) {$col['Null']} default={$col['Default']}\n";
    }
} catch (Exception $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
}

// 3. Check series_cast columns
echo "\n--- SERIES_CAST COLUMNS ---\n";
try {
    $cols = $pdo->query("DESCRIBE series_cast")->fetchAll();
    foreach ($cols as $col) {
        echo "  {$col['Field']} ({$col['Type']}) {$col['Null']} default={$col['Default']}\n";
    }
} catch (Exception $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
}

// 4. Sample cast data
echo "\n--- SAMPLE MOVIE CAST (first 5 rows) ---\n";
try {
    $rows = $pdo->query("SELECT mc.id, mc.movie_id, mc.name, mc.character_name, mc.profile_url, mc.tmdb_person_id, m.title as movie_title FROM movie_cast mc LEFT JOIN movies m ON mc.movie_id = m.id ORDER BY mc.id LIMIT 5")->fetchAll();
    if (empty($rows)) {
        echo "[EMPTY] No cast data in movie_cast table!\n";
    } else {
        foreach ($rows as $r) {
            echo "  #{$r['id']} movie_id={$r['movie_id']} ({$r['movie_title']}) — {$r['name']} as {$r['character_name']} tmdb={$r['tmdb_person_id']}\n";
        }
    }
} catch (Exception $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
}

// 5. Sample series cast
echo "\n--- SAMPLE SERIES CAST (first 5 rows) ---\n";
try {
    $rows = $pdo->query("SELECT sc.id, sc.series_id, sc.name, sc.character_name, sc.profile_url, sc.tmdb_person_id, s.title as series_title FROM series_cast sc LEFT JOIN series s ON sc.series_id = s.id ORDER BY sc.id LIMIT 5")->fetchAll();
    if (empty($rows)) {
        echo "[EMPTY] No cast data in series_cast table!\n";
    } else {
        foreach ($rows as $r) {
            echo "  #{$r['id']} series_id={$r['series_id']} ({$r['series_title']}) — {$r['name']} as {$r['character_name']} tmdb={$r['tmdb_person_id']}\n";
        }
    }
} catch (Exception $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
}

// 6. Check published movies that HAVE cast
echo "\n--- PUBLISHED MOVIES WITH CAST ---\n";
try {
    $rows = $pdo->query("SELECT m.id, m.title, m.status, COUNT(mc.id) as cast_count FROM movies m LEFT JOIN movie_cast mc ON m.id = mc.movie_id WHERE m.status = 'published' GROUP BY m.id ORDER BY cast_count DESC LIMIT 10")->fetchAll();
    if (empty($rows)) {
        echo "[EMPTY] No published movies found!\n";
    } else {
        foreach ($rows as $r) {
            $icon = $r['cast_count'] > 0 ? '[HAS CAST]' : '[NO CAST] ';
            echo "  $icon movie #{$r['id']} \"{$r['title']}\" — {$r['cast_count']} cast members\n";
        }
    }
} catch (Exception $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
}

// 7. Test the actual API query that getMovie uses
echo "\n--- TEST getMovie QUERY (first published movie) ---\n";
try {
    $movie = $pdo->query("SELECT m.id, m.title FROM movies m WHERE m.status = 'published' LIMIT 1")->fetch();
    if (!$movie) {
        echo "[EMPTY] No published movies.\n";
    } else {
        $id = $movie['id'];
        echo "Testing movie #{$id} \"{$movie['title']}\"...\n";

        // Test the full query with content_group_items
        try {
            $pdo->query("SELECT m.*, (SELECT 1 FROM content_group_items cgi WHERE cgi.content_type = 'movie' AND cgi.content_id = m.id LIMIT 1) IS NOT NULL as is_restricted FROM movies m WHERE m.id = $id")->fetch();
            echo "  [OK] Full query with content_group_items works\n";
        } catch (Exception $e) {
            echo "  [FAIL] Full query fails: " . $e->getMessage() . "\n";
        }

        // Test cast query
        try {
            $cast = $pdo->query("SELECT * FROM movie_cast WHERE movie_id = $id ORDER BY sort_order ASC LIMIT 20")->fetchAll();
            echo "  [OK] Cast query works — " . count($cast) . " cast members found\n";
            if (!empty($cast)) {
                echo "  First cast: {$cast[0]['name']} as {$cast[0]['character_name']}\n";
            }
        } catch (Exception $e) {
            echo "  [FAIL] Cast query fails: " . $e->getMessage() . "\n";
        }
    }
} catch (Exception $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
}

// 8. Check migrations
echo "\n--- MIGRATIONS RUN ---\n";
try {
    $rows = $pdo->query("SELECT migration_file, applied_at FROM _migrations ORDER BY applied_at")->fetchAll();
    foreach ($rows as $r) {
        echo "  {$r['migration_file']} — {$r['applied_at']}\n";
    }
} catch (Exception $e) {
    echo "[INFO] No _migrations table (migrations may not be tracked)\n";
}

echo "\n=== DONE ===\n";
