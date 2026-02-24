<?php
/**
 * CARI-IPTV Content API Service
 * Provides content data and versioning for player/frontend consumption
 */

namespace CariIPTV\Services;

use CariIPTV\Core\Database;

class ContentApiService
{
    private Database $db;

    private const CURRENCY_SYMBOLS = [
        'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'XCD' => 'EC$',
        'TTD' => 'TT$', 'JMD' => 'J$', 'BBD' => 'Bds$', 'GYD' => 'G$',
        'SRD' => 'SRD', 'HTG' => 'G', 'BZD' => 'BZ$', 'BSD' => 'B$',
        'KYD' => 'CI$', 'BMD' => 'BD$', 'ANG' => 'NAƒ', 'AWG' => 'Afl',
        'DOP' => 'RD$', 'CUP' => '₱', 'CAD' => 'C$', 'MXN' => 'MX$', 'BRL' => 'R$',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Safe query wrapper - returns default on failure
     */
    private function safeQuery(callable $fn, mixed $default = null): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            error_log('ContentAPI query error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $default;
        }
    }

    // =========================================================================
    // MANIFEST / VERSIONING
    // =========================================================================

    /**
     * Get content version manifest
     * Players use this to determine what has changed since last sync
     */
    public function getManifest(?string $platform = null): array
    {
        $manifest = [];

        // Channels version (is_active, not status)
        $row = $this->safeQuery(fn() => $this->db->fetch(
            "SELECT COUNT(*) as cnt, MAX(updated_at) as latest FROM channels WHERE is_active = 1"
        ), ['cnt' => 0, 'latest' => null]);
        $manifest['channels'] = [
            'version' => md5(($row['cnt'] ?? 0) . ':' . ($row['latest'] ?? '')),
            'count' => (int)($row['cnt'] ?? 0),
            'updated_at' => $row['latest'] ?? null,
        ];

        // Movies version (includes cast changes)
        $row = $this->safeQuery(fn() => $this->db->fetch(
            "SELECT COUNT(*) as cnt, MAX(updated_at) as latest FROM movies WHERE status = 'published'"
        ), ['cnt' => 0, 'latest' => null]);
        $castRow = $this->safeQuery(fn() => $this->db->fetch(
            "SELECT COUNT(*) as cnt, MAX(created_at) as latest FROM movie_cast"
        ), ['cnt' => 0, 'latest' => null]);
        $manifest['movies'] = [
            'version' => md5(($row['cnt'] ?? 0) . ':' . ($row['latest'] ?? '') . ':' . ($castRow['cnt'] ?? 0) . ':' . ($castRow['latest'] ?? '')),
            'count' => (int)($row['cnt'] ?? 0),
            'updated_at' => $row['latest'] ?? null,
        ];

        // Series version (includes cast changes)
        $row = $this->safeQuery(fn() => $this->db->fetch(
            "SELECT COUNT(*) as cnt, MAX(updated_at) as latest FROM series WHERE status = 'published'"
        ), ['cnt' => 0, 'latest' => null]);
        $castRow = $this->safeQuery(fn() => $this->db->fetch(
            "SELECT COUNT(*) as cnt, MAX(created_at) as latest FROM series_cast"
        ), ['cnt' => 0, 'latest' => null]);
        $manifest['series'] = [
            'version' => md5(($row['cnt'] ?? 0) . ':' . ($row['latest'] ?? '') . ':' . ($castRow['cnt'] ?? 0) . ':' . ($castRow['latest'] ?? '')),
            'count' => (int)($row['cnt'] ?? 0),
            'updated_at' => $row['latest'] ?? null,
        ];

        // Categories version (no updated_at column, use created_at)
        $row = $this->safeQuery(fn() => $this->db->fetch(
            "SELECT COUNT(*) as cnt, MAX(created_at) as latest FROM categories WHERE is_active = 1"
        ), ['cnt' => 0, 'latest' => null]);
        $manifest['categories'] = [
            'version' => md5(($row['cnt'] ?? 0) . ':' . ($row['latest'] ?? '')),
            'count' => (int)($row['cnt'] ?? 0),
            'updated_at' => $row['latest'] ?? null,
        ];

        // EPG version (table is epg_programs, no updated_at)
        $row = $this->safeQuery(fn() => $this->db->fetch(
            "SELECT COUNT(*) as cnt, MAX(created_at) as latest FROM epg_programs WHERE end_time > NOW()"
        ), ['cnt' => 0, 'latest' => null]);
        $manifest['epg'] = [
            'version' => md5(($row['cnt'] ?? 0) . ':' . ($row['latest'] ?? '')),
            'count' => (int)($row['cnt'] ?? 0),
            'updated_at' => $row['latest'] ?? null,
        ];

        // Layout versions per platform — tracks ALL published layouts, not just default
        $platforms = $platform ? [$platform] : ['web', 'mobile', 'tv', 'stb'];
        $manifest['layouts'] = [];
        foreach ($platforms as $p) {
            $row = $this->safeQuery(fn() => $this->db->fetch(
                "SELECT COUNT(*) as cnt, MAX(updated_at) as latest FROM app_layouts WHERE platform = ? AND status = 'published'",
                [$p]
            ), ['cnt' => 0, 'latest' => null]);
            if ($row && $row['latest']) {
                $manifest['layouts'][$p] = [
                    'version' => md5(($row['cnt'] ?? 0) . ':' . ($row['latest'] ?? '')),
                    'count' => (int)($row['cnt'] ?? 0),
                    'updated_at' => $row['latest'],
                ];
            }
        }

        // Navigation versions per platform — includes nav settings, items, and linked pages
        $manifest['navigation'] = [];
        foreach ($platforms as $p) {
            $row = $this->safeQuery(fn() => $this->db->fetch(
                "SELECT
                    COUNT(ni.id) as item_count,
                    MAX(n.updated_at) as nav_latest,
                    MAX(ni.created_at) as item_latest,
                    MAX(p.updated_at) as page_latest
                 FROM app_navigation n
                 LEFT JOIN app_navigation_items ni ON ni.navigation_id = n.id
                 LEFT JOIN app_pages p ON p.platform = n.platform AND p.is_active = 1
                 WHERE n.platform = ?",
                [$p]
            ));
            if ($row && ($row['nav_latest'] || $row['item_latest'])) {
                $manifest['navigation'][$p] = [
                    'version' => md5(
                        ($row['item_count'] ?? 0) . ':' .
                        ($row['nav_latest'] ?? '') . ':' .
                        ($row['item_latest'] ?? '') . ':' .
                        ($row['page_latest'] ?? '')
                    ),
                    'updated_at' => max(
                        $row['nav_latest'] ?? '',
                        $row['item_latest'] ?? '',
                        $row['page_latest'] ?? ''
                    ),
                ];
            }
        }

        return $manifest;
    }

    /**
     * Compute a version hash for a specific content type
     */
    public function getContentVersion(string $type): string
    {
        $manifest = $this->getManifest();
        return $manifest[$type]['version'] ?? 'unknown';
    }

    // =========================================================================
    // ENTITLEMENTS & ACCESS CONTROL
    // =========================================================================

    /**
     * Get subscriber's entitlements - content they have access to via their packages
     */
    public function getSubscriberEntitlements(int $subscriberId): array
    {
        // Get all content IDs the subscriber is entitled to via their active packages
        $items = $this->db->fetchAll(
            "SELECT DISTINCT cgi.content_type, cgi.content_id
             FROM subscriber_subscriptions ss
             INNER JOIN package_content_groups pcg ON ss.package_id = pcg.package_id
             INNER JOIN content_group_items cgi ON pcg.group_id = cgi.group_id
             WHERE ss.subscriber_id = ?
               AND ss.status IN ('active', 'trial')
               AND (ss.expires_at IS NULL OR ss.expires_at > NOW())",
            [$subscriberId]
        );

        // Use plural keys to match content_type + 's'
        $entitlements = [
            'movies' => [],
            'series' => [],
            'channels' => [],
            'categories' => [],
        ];

        foreach ($items as $item) {
            $type = $item['content_type'] . 's'; // movie -> movies
            if ($type === 'seriess') $type = 'series'; // fix double s
            if (isset($entitlements[$type])) {
                $entitlements[$type][] = (int) $item['content_id'];
            }
        }

        // Get subscriber's active package IDs
        $activePackageIds = $this->db->fetchAll(
            "SELECT package_id FROM subscriber_subscriptions
             WHERE subscriber_id = ?
               AND status IN ('active', 'trial')
               AND (expires_at IS NULL OR expires_at > NOW())",
            [$subscriberId]
        );
        $activeIds = array_column($activePackageIds, 'package_id');

        // Get ALL available packages (for subscription page display)
        // Use SELECT * to handle schema variations across installs
        $allPackages = $this->db->fetchAll(
            "SELECT p.*
             FROM packages p
             WHERE p.is_active = 1
             ORDER BY p.is_featured DESC, p.sort_order, p.price ASC"
        );

        // Parse features JSON and mark subscribed packages with safe defaults
        foreach ($allPackages as &$pkg) {
            $features = json_decode($pkg['features'] ?? '[]', true);
            $pkg['features'] = is_array($features) ? $features : [];
            $pkg['is_subscribed'] = in_array((int)$pkg['id'], $activeIds);
            $pkg['billing_period'] = $pkg['billing_period'] ?? 'monthly';
            $pkg['trial_days'] = (int) ($pkg['trial_days'] ?? 0);
            $pkg['is_adult'] = (bool) ($pkg['is_adult'] ?? false);
            $pkg['is_free'] = (bool) ($pkg['is_free'] ?? false);
            $pkg['is_featured'] = (bool) ($pkg['is_featured'] ?? false);
            $currencySymbol = self::CURRENCY_SYMBOLS[$pkg['currency'] ?? 'USD'] ?? '$';
            $pkg['price_display'] = ($pkg['is_free'] || (float)$pkg['price'] === 0.0)
                ? 'Free'
                : $currencySymbol . number_format((float)$pkg['price'], 2);
        }

        return array_merge($entitlements, [
            'packages' => $allPackages,
            'has_subscription' => !empty($activeIds),
        ]);
    }

    /**
     * Check if content is restricted (belongs to any content group)
     * If not in any group, it's free/unrestricted
     */
    public function isContentRestricted(string $contentType, int $contentId): bool
    {
        $result = $this->db->fetch(
            "SELECT 1 FROM content_group_items WHERE content_type = ? AND content_id = ? LIMIT 1",
            [$contentType, $contentId]
        );
        return $result !== null;
    }

    /**
     * Get packages that include specific content
     */
    public function getPackagesForContent(string $contentType, int $contentId): array
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT p.id, p.name, p.slug, p.price, p.currency, p.is_free, p.is_featured
             FROM packages p
             INNER JOIN package_content_groups pcg ON p.id = pcg.package_id
             INNER JOIN content_group_items cgi ON pcg.group_id = cgi.group_id
             WHERE cgi.content_type = ? AND cgi.content_id = ?
               AND p.is_active = 1
             ORDER BY p.is_featured DESC, p.sort_order, p.price ASC",
            [$contentType, $contentId]
        );
    }

    // =========================================================================
    // CHANNELS
    // =========================================================================

    public function getChannels(array $filters = []): array
    {
        $where = ["c.is_active = 1"];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[] = "c.category_id = ?";
            $params[] = $filters['category_id'];
        }

        if (!empty($filters['since'])) {
            $where[] = "c.updated_at > ?";
            $params[] = $filters['since'];
        }

        $whereStr = implode(' AND ', $where);

        $limit = min((int)($filters['limit'] ?? 500), 1000);
        $offset = max((int)($filters['offset'] ?? 0), 0);

        // Try with content restriction check; fall back without it
        $channels = $this->safeQuery(fn() => $this->db->fetchAll(
            "SELECT c.id, c.name, c.slug, c.logo_url, c.stream_url,
                    c.epg_channel_id, c.category_id, c.country,
                    c.is_hd, c.is_adult, c.channel_number, c.sort_order, c.updated_at,
                    cat.name as category_name,
                    (SELECT 1 FROM content_group_items cgi WHERE cgi.content_type = 'channel' AND cgi.content_id = c.id LIMIT 1) IS NOT NULL as is_restricted
             FROM channels c
             LEFT JOIN categories cat ON c.category_id = cat.id
             WHERE {$whereStr}
             ORDER BY c.sort_order ASC, c.name ASC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        ));

        if ($channels === null) {
            $channels = $this->safeQuery(fn() => $this->db->fetchAll(
                "SELECT c.*, cat.name as category_name
                 FROM channels c
                 LEFT JOIN categories cat ON c.category_id = cat.id
                 WHERE {$whereStr}
                 ORDER BY c.sort_order ASC, c.name ASC
                 LIMIT {$limit} OFFSET {$offset}",
                $params
            ), []);
        }

        foreach ($channels as &$channel) {
            $channel['is_restricted'] = (bool) ($channel['is_restricted'] ?? false);
            $channel['content_type'] = 'channel';
        }

        $total = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM channels c WHERE {$whereStr}",
            $params
        );

        return [
            'items' => $channels,
            'total' => (int)($total['cnt'] ?? 0),
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public function getChannel(int $id): ?array
    {
        // Try with content restriction check; fall back without it
        $channel = $this->safeQuery(fn() => $this->db->fetch(
            "SELECT c.*, cat.name as category_name,
                    (SELECT 1 FROM content_group_items cgi WHERE cgi.content_type = 'channel' AND cgi.content_id = c.id LIMIT 1) IS NOT NULL as is_restricted
             FROM channels c
             LEFT JOIN categories cat ON c.category_id = cat.id
             WHERE c.id = ? AND c.is_active = 1",
            [$id]
        ));

        if (!$channel) {
            $channel = $this->db->fetch(
                "SELECT c.*, cat.name as category_name
                 FROM channels c
                 LEFT JOIN categories cat ON c.category_id = cat.id
                 WHERE c.id = ? AND c.is_active = 1",
                [$id]
            );
        }

        if (!$channel) {
            return null;
        }

        $channel['is_restricted'] = (bool) ($channel['is_restricted'] ?? false);
        $channel['content_type'] = 'channel';

        // Get current EPG programme (table is epg_programs)
        $channel['now_playing'] = $this->safeQuery(fn() => $this->db->fetch(
            "SELECT title, description, start_time, end_time
             FROM epg_programs
             WHERE channel_id = ? AND start_time <= NOW() AND end_time > NOW()
             LIMIT 1",
            [$id]
        ));

        $channel['next_up'] = $this->safeQuery(fn() => $this->db->fetch(
            "SELECT title, description, start_time, end_time
             FROM epg_programs
             WHERE channel_id = ? AND start_time > NOW()
             ORDER BY start_time ASC LIMIT 1",
            [$id]
        ));

        return $channel;
    }

    // =========================================================================
    // MOVIES
    // =========================================================================

    public function getMovies(array $filters = []): array
    {
        $where = ["m.status = 'published'"];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[] = "m.category_id = ?";
            $params[] = $filters['category_id'];
        }

        if (!empty($filters['featured'])) {
            $where[] = "m.is_featured = 1";
        }

        if (!empty($filters['since'])) {
            $where[] = "m.updated_at > ?";
            $params[] = $filters['since'];
        }

        if (!empty($filters['genre'])) {
            // genres is a JSON column
            $where[] = "m.genres LIKE ?";
            $params[] = '%' . $filters['genre'] . '%';
        }

        if (!empty($filters['year'])) {
            $where[] = "m.year = ?";
            $params[] = $filters['year'];
        }

        $whereStr = implode(' AND ', $where);

        // Sort options (vote_average, not tmdb_rating)
        $sortMap = [
            'latest' => 'm.created_at DESC',
            'title' => 'm.title ASC',
            'year' => 'm.year DESC, m.title ASC',
            'rating' => 'm.vote_average DESC',
            'popular' => 'm.views DESC',
        ];
        $orderBy = $sortMap[$filters['sort'] ?? 'latest'] ?? 'm.created_at DESC';

        $limit = min((int)($filters['limit'] ?? 50), 200);
        $offset = max((int)($filters['offset'] ?? 0), 0);

        // Try with content restriction check; fall back without it
        $movies = $this->safeQuery(fn() => $this->db->fetchAll(
            "SELECT m.id, m.title, m.slug, m.year, m.genres, m.runtime,
                    m.vote_average, m.poster_url, m.backdrop_url,
                    m.stream_url, m.is_featured, m.is_adult,
                    m.category_id, m.updated_at,
                    cat.name as category_name,
                    (SELECT 1 FROM content_group_items cgi WHERE cgi.content_type = 'movie' AND cgi.content_id = m.id LIMIT 1) IS NOT NULL as is_restricted
             FROM movies m
             LEFT JOIN categories cat ON m.category_id = cat.id
             WHERE {$whereStr}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params
        ));

        if ($movies === null) {
            // Minimal fallback without content_group_items or optional columns
            $movies = $this->safeQuery(fn() => $this->db->fetchAll(
                "SELECT m.*, cat.name as category_name
                 FROM movies m
                 LEFT JOIN categories cat ON m.category_id = cat.id
                 WHERE {$whereStr}
                 ORDER BY {$orderBy}
                 LIMIT {$limit} OFFSET {$offset}",
                $params
            ), []);
        }

        foreach ($movies as &$movie) {
            $movie['is_restricted'] = (bool) ($movie['is_restricted'] ?? false);
            $movie['content_type'] = 'movie';
        }

        $total = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM movies m WHERE {$whereStr}",
            $params
        );

        return [
            'items' => $movies,
            'total' => (int)($total['cnt'] ?? 0),
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public function getMovie(int $id): ?array
    {
        // Try with content restriction check first; fall back without it
        // if content_group_items table doesn't exist yet (migration 014)
        $movie = $this->safeQuery(fn() => $this->db->fetch(
            "SELECT m.*, cat.name as category_name,
                    (SELECT 1 FROM content_group_items cgi WHERE cgi.content_type = 'movie' AND cgi.content_id = m.id LIMIT 1) IS NOT NULL as is_restricted
             FROM movies m
             LEFT JOIN categories cat ON m.category_id = cat.id
             WHERE m.id = ? AND m.status = 'published'",
            [$id]
        ));

        if (!$movie) {
            // Fallback: query without content_group_items subquery
            $movie = $this->db->fetch(
                "SELECT m.*, cat.name as category_name
                 FROM movies m
                 LEFT JOIN categories cat ON m.category_id = cat.id
                 WHERE m.id = ? AND m.status = 'published'",
                [$id]
            );
        }

        if (!$movie) {
            return null;
        }

        $movie['is_restricted'] = (bool) ($movie['is_restricted'] ?? false);
        $movie['content_type'] = 'movie';

        // DRM info — if movie has a VOD server, check for DRM key
        $movie['drm'] = $this->getDrmInfo($movie);

        // Trailers (video_key, url - not youtube_id, youtube_url)
        $movie['trailers'] = $this->safeQuery(fn() => $this->db->fetchAll(
            "SELECT id, name as title, video_key, url, is_primary
             FROM movie_trailers WHERE movie_id = ? ORDER BY is_primary DESC, sort_order ASC",
            [$id]
        ), []);

        // Artwork
        $movie['artwork'] = $this->safeQuery(fn() => $this->db->fetchAll(
            "SELECT id, type, url, language, is_primary
             FROM movie_artwork WHERE movie_id = ? ORDER BY is_primary DESC",
            [$id]
        ), []);

        // Cast (SELECT * to handle schema variations - profile_image added in migration 020)
        $movie['cast'] = $this->safeQuery(fn() => $this->db->fetchAll(
            "SELECT * FROM movie_cast WHERE movie_id = ? ORDER BY sort_order ASC LIMIT 20",
            [$id]
        ), []);

        return $movie;
    }

    // =========================================================================
    // DRM
    // =========================================================================

    /**
     * Get DRM info for a content item that has a VOD server.
     * Returns null if no DRM key exists, or a config object for the player.
     */
    private function getDrmInfo(array $item): ?array
    {
        $serverId = (int)($item['vod_server_id'] ?? 0);
        if (!$serverId || empty($item['stream_url'])) {
            return null;
        }

        try {
            $vodService = new VodServerService();
            $server = $vodService->getServer($serverId);
            if (!$server) return null;

            // Use stored content_id if available, otherwise fall back to movie-{id}
            $contentId = !empty($item['vod_content_id'])
                ? $item['vod_content_id']
                : 'movie-' . ($item['id'] ?? 0);

            // Check if a DRM key exists for this content
            $keyInfo = $vodService->getDrmKey($serverId, $contentId);
            if (empty($keyInfo) || !empty($keyInfo['error'])) {
                return null;
            }

            // Build player-facing DRM config
            // The license URL points to our middleware proxy (not the VOD server directly)
            // Prefer site_url from DB settings (admin GUI), fall back to APP_URL env var
            $settings = new SettingsService();
            $appUrl = rtrim($settings->get('site_url', '', 'general') ?: getenv('APP_URL') ?: '', '/');
            return [
                'scheme'      => $keyInfo['scheme'] ?? 'cenc',
                'key_id'      => $keyInfo['kid'] ?? '',
                'license_url' => $appUrl . '/api/v1/drm/license?content_id=' . urlencode($contentId)
                                        . '&server_id=' . $serverId,
            ];
        } catch (\Throwable $e) {
            // DRM lookup failure is non-fatal — content plays without DRM
            error_log('DRM info lookup failed for item ' . ($item['id'] ?? '?') . ': ' . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    // SERIES
    // =========================================================================

    public function getSeries(array $filters = []): array
    {
        $where = ["s.status = 'published'"];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[] = "s.category_id = ?";
            $params[] = $filters['category_id'];
        }

        if (!empty($filters['featured'])) {
            $where[] = "s.is_featured = 1";
        }

        if (!empty($filters['since'])) {
            $where[] = "s.updated_at > ?";
            $params[] = $filters['since'];
        }

        if (!empty($filters['genre'])) {
            // genres is a JSON column
            $where[] = "s.genres LIKE ?";
            $params[] = '%' . $filters['genre'] . '%';
        }

        $whereStr = implode(' AND ', $where);

        // Sort options (vote_average, not tmdb_rating)
        $sortMap = [
            'latest' => 's.created_at DESC',
            'title' => 's.title ASC',
            'year' => 's.year DESC, s.title ASC',
            'rating' => 's.vote_average DESC',
            'popular' => 's.views DESC',
        ];
        $orderBy = $sortMap[$filters['sort'] ?? 'latest'] ?? 's.created_at DESC';

        $limit = min((int)($filters['limit'] ?? 50), 200);
        $offset = max((int)($filters['offset'] ?? 0), 0);

        // Try with content restriction check; fall back without it
        $series = $this->safeQuery(fn() => $this->db->fetchAll(
            "SELECT s.id, s.title, s.slug, s.year, s.genres, s.synopsis,
                    s.vote_average, s.poster_url, s.backdrop_url,
                    s.is_featured, s.is_adult, s.category_id, s.updated_at,
                    s.number_of_seasons as season_count,
                    s.number_of_episodes as episode_count,
                    cat.name as category_name,
                    (SELECT 1 FROM content_group_items cgi WHERE cgi.content_type = 'series' AND cgi.content_id = s.id LIMIT 1) IS NOT NULL as is_restricted
             FROM series s
             LEFT JOIN categories cat ON s.category_id = cat.id
             WHERE {$whereStr}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params
        ));

        if ($series === null) {
            $series = $this->safeQuery(fn() => $this->db->fetchAll(
                "SELECT s.*, cat.name as category_name
                 FROM series s
                 LEFT JOIN categories cat ON s.category_id = cat.id
                 WHERE {$whereStr}
                 ORDER BY {$orderBy}
                 LIMIT {$limit} OFFSET {$offset}",
                $params
            ), []);
        }

        foreach ($series as &$show) {
            $show['is_restricted'] = (bool) ($show['is_restricted'] ?? false);
            $show['content_type'] = 'series';
        }

        $total = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM series s WHERE {$whereStr}",
            $params
        );

        return [
            'items' => $series,
            'total' => (int)($total['cnt'] ?? 0),
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public function getSeriesDetail(int $id): ?array
    {
        // Try with content restriction check first; fall back without it
        // if content_group_items table doesn't exist yet (migration 014)
        $show = $this->safeQuery(fn() => $this->db->fetch(
            "SELECT s.*, cat.name as category_name,
                    (SELECT 1 FROM content_group_items cgi WHERE cgi.content_type = 'series' AND cgi.content_id = s.id LIMIT 1) IS NOT NULL as is_restricted
             FROM series s
             LEFT JOIN categories cat ON s.category_id = cat.id
             WHERE s.id = ? AND s.status = 'published'",
            [$id]
        ));

        if (!$show) {
            // Fallback: query without content_group_items subquery
            $show = $this->db->fetch(
                "SELECT s.*, cat.name as category_name
                 FROM series s
                 LEFT JOIN categories cat ON s.category_id = cat.id
                 WHERE s.id = ? AND s.status = 'published'",
                [$id]
            );
        }

        if (!$show) {
            return null;
        }

        $show['is_restricted'] = (bool) ($show['is_restricted'] ?? false);
        $show['content_type'] = 'series';

        // Seasons (table is series_seasons)
        $show['seasons'] = $this->safeQuery(fn() => $this->db->fetchAll(
            "SELECT sn.*, sn.episode_count
             FROM series_seasons sn
             WHERE sn.series_id = ?
             ORDER BY sn.season_number ASC",
            [$id]
        ), []);

        // Episodes per season (table is series_episodes)
        foreach ($show['seasons'] as &$season) {
            $season['episodes'] = $this->safeQuery(fn() => $this->db->fetchAll(
                "SELECT id, name as title, episode_number, overview as synopsis, runtime,
                        stream_url, still_url, air_date, vote_average
                 FROM series_episodes
                 WHERE season_id = ?
                 ORDER BY episode_number ASC",
                [$season['id']]
            ), []);
        }

        // Trailers (series-level, table is series_trailers)
        $show['trailers'] = $this->safeQuery(fn() => $this->db->fetchAll(
            "SELECT id, name as title, video_key, url, is_primary
             FROM series_trailers WHERE series_id = ? ORDER BY is_primary DESC, sort_order ASC",
            [$id]
        ), []);

        // Cast (SELECT * to handle schema variations - profile_image added in migration 020)
        $show['cast'] = $this->safeQuery(fn() => $this->db->fetchAll(
            "SELECT * FROM series_cast WHERE series_id = ? ORDER BY sort_order ASC LIMIT 20",
            [$id]
        ), []);

        return $show;
    }

    /**
     * Get a single episode by ID (for the player)
     */
    public function getEpisode(int $id): ?array
    {
        $episode = $this->db->fetch(
            "SELECT e.id, e.series_id, e.season_id, e.episode_number, e.name as title,
                    e.overview as synopsis, e.runtime, e.stream_url, e.still_url,
                    e.air_date, e.vote_average,
                    s.title as series_title, s.poster_url as series_poster_url,
                    sn.season_number, sn.name as season_name
             FROM series_episodes e
             JOIN series s ON e.series_id = s.id
             JOIN series_seasons sn ON e.season_id = sn.id
             WHERE e.id = ? AND s.status = 'published'",
            [$id]
        );

        return $episode ?: null;
    }

    // =========================================================================
    // CATEGORIES
    // =========================================================================

    public function getCategories(array $filters = []): array
    {
        $where = ["c.is_active = 1"];
        $params = [];

        if (!empty($filters['type'])) {
            $where[] = "c.type = ?";
            $params[] = $filters['type'];
        }

        // Categories has no updated_at, use created_at for since filter
        if (!empty($filters['since'])) {
            $where[] = "c.created_at > ?";
            $params[] = $filters['since'];
        }

        $whereStr = implode(' AND ', $where);

        return $this->db->fetchAll(
            "SELECT c.id, c.name, c.slug, c.type, c.icon, c.sort_order, c.created_at
             FROM categories c
             WHERE {$whereStr}
             ORDER BY c.sort_order ASC, c.name ASC",
            $params
        );
    }

    // =========================================================================
    // EPG
    // =========================================================================

    public function getEpg(array $filters = []): array
    {
        // Include programmes from 3 hours ago so the TV guide can show past/current slots
        $where = ["p.end_time > DATE_SUB(NOW(), INTERVAL 3 HOUR)"];
        $params = [];

        if (!empty($filters['channel_id'])) {
            $where[] = "p.channel_id = ?";
            $params[] = $filters['channel_id'];
        }

        if (!empty($filters['date'])) {
            $where[] = "DATE(p.start_time) = ?";
            $params[] = $filters['date'];
        }

        // Default: 3 hours ago to 24 hours ahead
        if (empty($filters['date'])) {
            $where[] = "p.start_time < DATE_ADD(NOW(), INTERVAL 24 HOUR)";
        }

        $whereStr = implode(' AND ', $where);

        $limit = min((int)($filters['limit'] ?? 500), 2000);

        // Table is epg_programs (not epg_programmes)
        return $this->db->fetchAll(
            "SELECT p.id, p.channel_id, p.title, p.description,
                    p.start_time, p.end_time, p.category,
                    c.name as channel_name
             FROM epg_programs p
             JOIN channels c ON p.channel_id = c.id
             WHERE {$whereStr}
             ORDER BY p.channel_id ASC, p.start_time ASC
             LIMIT {$limit}",
            $params
        );
    }

    // =========================================================================
    // APP LAYOUT
    // =========================================================================

    /**
     * Get a specific layout by ID (used when page has a linked layout_id).
     * No status filter — if admin linked it to a page, serve it regardless of status.
     */
    public function getLayoutById(int $id): ?array
    {
        $layout = $this->safeQuery(fn() => $this->db->fetch(
            "SELECT id, name, platform, status, updated_at
             FROM app_layouts
             WHERE id = ?
             LIMIT 1",
            [$id]
        ));

        if (!$layout) {
            error_log("[ContentAPI] getLayoutById({$id}): no layout row found");
            return null;
        }

        try {
            return $this->loadLayoutSections($layout);
        } catch (\Throwable $e) {
            error_log("[ContentAPI] getLayoutById({$id}): loadLayoutSections failed: " . $e->getMessage());
            // Return layout with empty sections rather than failing entirely
            $layout['sections'] = [];
            return $layout;
        }
    }

    public function getLayout(string $platform): ?array
    {
        $layout = $this->safeQuery(fn() => $this->db->fetch(
            "SELECT id, name, platform, status, updated_at
             FROM app_layouts
             WHERE platform = ? AND is_default = 1 AND status = 'published'
             LIMIT 1",
            [$platform]
        ));

        if (!$layout) {
            return null;
        }

        try {
            return $this->loadLayoutSections($layout);
        } catch (\Throwable $e) {
            error_log("[ContentAPI] getLayout({$platform}): loadLayoutSections failed: " . $e->getMessage());
            $layout['sections'] = [];
            return $layout;
        }
    }

    /**
     * Load sections and items for a layout
     */
    private function loadLayoutSections(array $layout): array
    {
        $layout['sections'] = $this->db->fetchAll(
            "SELECT id, section_type, title, settings, sort_order, is_active
             FROM app_layout_sections
             WHERE layout_id = ? AND is_active = 1
             ORDER BY sort_order ASC",
            [$layout['id']]
        );

        foreach ($layout['sections'] as &$section) {
            $section['settings'] = json_decode($section['settings'] ?? '{}', true);
            $source = $section['settings']['source'] ?? 'curated';

            // For non-curated sources, auto-populate items from the database
            if ($source !== 'curated' && in_array($section['section_type'], ['content_row', 'channel_grid'])) {
                $section['items'] = $this->getAutoItems($section['section_type'], $section['settings']);
            } else {
                $section['items'] = $this->db->fetchAll(
                    "SELECT i.id, i.content_type, i.content_id, i.settings, i.sort_order
                     FROM app_layout_items i
                     WHERE i.section_id = ?
                     ORDER BY i.sort_order ASC",
                    [$section['id']]
                );

                foreach ($section['items'] as &$item) {
                    $item['settings'] = json_decode($item['settings'] ?? '{}', true);
                    $item['content'] = $this->resolveContentItem($item['content_type'], $item['content_id'], $item['settings']);
                }
            }
        }

        return $layout;
    }

    /**
     * Auto-populate items for content_row / channel_grid based on source settings
     */
    private function getAutoItems(string $sectionType, array $settings): array
    {
        $source = $settings['source'] ?? 'curated';
        $maxItems = (int) ($settings['max_items'] ?? 20);
        $contentType = $settings['content_type'] ?? 'movie';
        $categoryId = !empty($settings['category_id']) ? (int) $settings['category_id'] : null;

        if ($sectionType === 'channel_grid') {
            return $this->getAutoChannelItems($source, $maxItems, $categoryId);
        }

        $items = [];
        if ($contentType === 'mixed') {
            $half = (int) ceil($maxItems / 2);
            $movies = $this->getAutoContentItems('movie', $source, $half, $categoryId);
            $series = $this->getAutoContentItems('series', $source, $half, $categoryId);
            $items = array_merge($movies, $series);
            $items = array_slice($items, 0, $maxItems);
        } elseif ($contentType === 'series') {
            $items = $this->getAutoContentItems('series', $source, $maxItems, $categoryId);
        } else {
            $items = $this->getAutoContentItems('movie', $source, $maxItems, $categoryId);
        }

        return $items;
    }

    private function getAutoContentItems(string $type, string $source, int $limit, ?int $categoryId): array
    {
        $table = $type === 'series' ? 'series' : 'movies';
        $where = "status = 'published'";
        $params = [];
        $order = 'created_at DESC';

        if ($categoryId) {
            $where .= " AND category_id = ?";
            $params[] = $categoryId;
        }

        switch ($source) {
            case 'latest':
                $order = 'created_at DESC';
                break;
            case 'popular':
                $order = 'views DESC, created_at DESC';
                break;
            case 'top_rated':
                $order = 'vote_average DESC, created_at DESC';
                break;
            case 'featured':
                $where .= " AND is_featured = 1";
                $order = 'updated_at DESC';
                break;
            case 'category':
                if (!$categoryId) return [];
                $order = 'title ASC';
                break;
        }

        $titleCol = 'title';
        $extraCols = $table === 'movies'
            ? 'slug, genres, runtime, vote_average, poster_url, backdrop_url, stream_url, synopsis'
            : 'slug, genres, vote_average, poster_url, backdrop_url, synopsis';

        $rows = $this->safeQuery(fn() => $this->db->fetchAll(
            "SELECT id, {$titleCol}, year, {$extraCols}
             FROM {$table} WHERE {$where}
             ORDER BY {$order} LIMIT {$limit}",
            $params
        ), []);

        $items = [];
        foreach ($rows as $i => $row) {
            $items[] = [
                'id' => 0,
                'content_type' => $type,
                'content_id' => $row['id'],
                'settings' => [],
                'sort_order' => $i,
                'content' => $row,
            ];
        }

        return $items;
    }

    private function getAutoChannelItems(string $source, int $limit, ?int $categoryId): array
    {
        $where = "c.is_active = 1";
        $params = [];
        $join = '';

        if ($categoryId) {
            $join = " JOIN channel_categories cc ON c.id = cc.channel_id AND cc.category_id = ?";
            $params[] = $categoryId;
        }

        if ($source === 'category' && !$categoryId) return [];

        $rows = $this->safeQuery(fn() => $this->db->fetchAll(
            "SELECT c.id, c.name, c.slug, c.logo_url, c.stream_url, c.is_hd
             FROM channels c {$join}
             WHERE {$where}
             ORDER BY c.channel_number ASC LIMIT {$limit}",
            $params
        ), []);

        $items = [];
        foreach ($rows as $i => $row) {
            $items[] = [
                'id' => 0,
                'content_type' => 'channel',
                'content_id' => $row['id'],
                'settings' => [],
                'sort_order' => $i,
                'content' => $row,
            ];
        }

        return $items;
    }

    /**
     * Resolve a layout content item to its actual data
     */
    private function resolveContentItem(string $type, ?int $contentId, array $settings): ?array
    {
        if (!$contentId && $type !== 'custom') {
            return null;
        }

        return match ($type) {
            'movie' => $this->safeQuery(fn() => $this->db->fetch(
                "SELECT id, title, slug, year, genres, runtime, vote_average,
                        poster_url, backdrop_url, stream_url, synopsis
                 FROM movies WHERE id = ? AND status = 'published'",
                [$contentId]
            )),
            'series' => $this->safeQuery(fn() => $this->db->fetch(
                "SELECT id, title, slug, year, genres, vote_average,
                        poster_url, backdrop_url, synopsis
                 FROM series WHERE id = ? AND status = 'published'",
                [$contentId]
            )),
            'channel' => $this->safeQuery(fn() => $this->db->fetch(
                "SELECT id, name, slug, logo_url, stream_url, is_hd
                 FROM channels WHERE id = ? AND is_active = 1",
                [$contentId]
            )),
            'category' => $this->safeQuery(fn() => $this->db->fetch(
                "SELECT id, name, slug, type, icon
                 FROM categories WHERE id = ? AND is_active = 1",
                [$contentId]
            )),
            'custom' => [
                'title' => $settings['title'] ?? null,
                'image_url' => $settings['image_url'] ?? null,
                'link_url' => $settings['link_url'] ?? null,
            ],
            default => null,
        };
    }

    // =========================================================================
    // NAVIGATION
    // =========================================================================

    public function getNavigation(string $platform, string $position = 'main'): ?array
    {
        $nav = $this->safeQuery(fn() => $this->db->fetch(
            "SELECT id, platform, position, settings
             FROM app_navigation
             WHERE platform = ? AND position = ?
             LIMIT 1",
            [$platform, $position]
        ));

        if (!$nav) {
            return null;
        }

        $nav['settings'] = json_decode($nav['settings'] ?? '{}', true);

        $nav['items'] = $this->db->fetchAll(
            "SELECT ni.id, ni.label, ni.icon, ni.target, ni.url, ni.sort_order,
                    p.slug as page_slug, p.page_type, p.layout_id
             FROM app_navigation_items ni
             LEFT JOIN app_pages p ON ni.page_id = p.id
             WHERE ni.navigation_id = ?
             ORDER BY ni.sort_order ASC",
            [$nav['id']]
        );

        return $nav;
    }

    public function getPages(string $platform): array
    {
        // app_pages uses is_active, not status
        return $this->safeQuery(fn() => $this->db->fetchAll(
            "SELECT id, name, slug, page_type, icon, layout_id, is_system, sort_order
             FROM app_pages
             WHERE platform = ? AND is_active = 1
             ORDER BY sort_order ASC",
            [$platform]
        ), []);
    }

    // =========================================================================
    // SEARCH
    // =========================================================================

    public function search(string $query, array $filters = []): array
    {
        $results = [];
        $searchTerm = '%' . $query . '%';
        $types = $filters['type'] ?? 'all';
        $limit = min((int)($filters['limit'] ?? 20), 50);

        if ($types === 'all' || $types === 'channel') {
            $channels = $this->safeQuery(fn() => $this->db->fetchAll(
                "SELECT id, name as title, slug, logo_url as image_url, 'channel' as content_type
                 FROM channels
                 WHERE is_active = 1 AND (name LIKE ? OR slug LIKE ?)
                 ORDER BY name ASC LIMIT ?",
                [$searchTerm, $searchTerm, $limit]
            ), []);
            $results = array_merge($results, $channels);
        }

        if ($types === 'all' || $types === 'movie') {
            $movies = $this->safeQuery(fn() => $this->db->fetchAll(
                "SELECT id, title, slug, poster_url as image_url, year, genres, vote_average, 'movie' as content_type
                 FROM movies
                 WHERE status = 'published' AND (title LIKE ? OR slug LIKE ? OR synopsis LIKE ?)
                 ORDER BY vote_average DESC LIMIT ?",
                [$searchTerm, $searchTerm, $searchTerm, $limit]
            ), []);
            $results = array_merge($results, $movies);
        }

        if ($types === 'all' || $types === 'series') {
            $series = $this->safeQuery(fn() => $this->db->fetchAll(
                "SELECT id, title, slug, poster_url as image_url, year, genres, vote_average, 'series' as content_type
                 FROM series
                 WHERE status = 'published' AND (title LIKE ? OR slug LIKE ? OR synopsis LIKE ?)
                 ORDER BY vote_average DESC LIMIT ?",
                [$searchTerm, $searchTerm, $searchTerm, $limit]
            ), []);
            $results = array_merge($results, $series);
        }

        return $results;
    }

    // =========================================================================
    // PERSON / CAST
    // =========================================================================

    /**
     * Get person details and all content they appear in on the platform
     * Looks up by tmdb_person_id across both movie_cast and series_cast
     */
    public function getPerson(int $tmdbPersonId): ?array
    {
        // Get person info from either cast table (SELECT * for schema compatibility)
        $person = $this->safeQuery(fn() => $this->db->fetch(
            "SELECT * FROM movie_cast WHERE tmdb_person_id = ? LIMIT 1",
            [$tmdbPersonId]
        ));

        if (!$person) {
            $person = $this->safeQuery(fn() => $this->db->fetch(
                "SELECT * FROM series_cast WHERE tmdb_person_id = ? LIMIT 1",
                [$tmdbPersonId]
            ));
        }

        // Also check EPG cast if not found in movie/series cast
        if (!$person) {
            $person = $this->safeQuery(fn() => $this->db->fetch(
                "SELECT name, tmdb_person_id, profile_url, profile_image FROM epg_programme_cast WHERE tmdb_person_id = ? LIMIT 1",
                [$tmdbPersonId]
            ));
        }

        if (!$person) {
            return null;
        }

        // Get movies they appear in
        $movies = $this->safeQuery(fn() => $this->db->fetchAll(
            "SELECT m.id, m.title, m.slug, m.poster_url, m.year, m.vote_average,
                    mc.character_name, mc.role, 'movie' as content_type
             FROM movie_cast mc
             JOIN movies m ON mc.movie_id = m.id
             WHERE mc.tmdb_person_id = ? AND m.status = 'published'
             ORDER BY m.year DESC",
            [$tmdbPersonId]
        ), []);

        // Get series they appear in
        $series = $this->safeQuery(fn() => $this->db->fetchAll(
            "SELECT s.id, s.title, s.slug, s.poster_url, s.year, s.vote_average,
                    sc.character_name, sc.role, 'series' as content_type
             FROM series_cast sc
             JOIN series s ON sc.series_id = s.id
             WHERE sc.tmdb_person_id = ? AND s.status = 'published'
             ORDER BY s.year DESC",
            [$tmdbPersonId]
        ), []);

        // Get upcoming EPG appearances via cached metadata
        $epgAppearances = $this->safeQuery(fn() => $this->db->fetchAll(
            "SELECT p.title, p.start_time, p.end_time, p.channel_id,
                    c.name AS channel_name, c.logo_url AS channel_logo,
                    em.poster_local, em.poster_url, em.media_type, em.year
             FROM epg_programme_cast ec
             JOIN epg_programme_metadata em ON ec.metadata_id = em.id
             JOIN epg_programs p ON LOWER(TRIM(p.title)) = em.title_normalised
             JOIN channels c ON p.channel_id = c.id
             WHERE ec.tmdb_person_id = ?
               AND p.end_time > NOW()
             ORDER BY p.start_time ASC
             LIMIT 50",
            [$tmdbPersonId]
        ), []);

        // Deduplicate by title + start_time (same programme on different sources)
        $seen = [];
        $epg = [];
        foreach ($epgAppearances as $row) {
            $key = $row['title'] . '|' . $row['start_time'] . '|' . $row['channel_id'];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $epg[] = [
                'title' => $row['title'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'channel_id' => (int) $row['channel_id'],
                'channel_name' => $row['channel_name'],
                'channel_logo' => $row['channel_logo'],
                'poster' => $row['poster_local'] ?: $row['poster_url'],
                'media_type' => $row['media_type'],
                'year' => $row['year'],
            ];
        }

        return [
            'name' => $person['name'],
            'profile_url' => $person['profile_url'] ?? null,
            'profile_image' => $person['profile_image'] ?? null,
            'tmdb_person_id' => (int) ($person['tmdb_person_id'] ?? 0),
            'movies' => $movies,
            'series' => $series,
            'epg' => $epg,
        ];
    }
}
