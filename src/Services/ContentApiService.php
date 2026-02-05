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
            error_log('ContentAPI query error: ' . $e->getMessage());
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

        // Movies version
        $row = $this->safeQuery(fn() => $this->db->fetch(
            "SELECT COUNT(*) as cnt, MAX(updated_at) as latest FROM movies WHERE status = 'published'"
        ), ['cnt' => 0, 'latest' => null]);
        $manifest['movies'] = [
            'version' => md5(($row['cnt'] ?? 0) . ':' . ($row['latest'] ?? '')),
            'count' => (int)($row['cnt'] ?? 0),
            'updated_at' => $row['latest'] ?? null,
        ];

        // Series version
        $row = $this->safeQuery(fn() => $this->db->fetch(
            "SELECT COUNT(*) as cnt, MAX(updated_at) as latest FROM series WHERE status = 'published'"
        ), ['cnt' => 0, 'latest' => null]);
        $manifest['series'] = [
            'version' => md5(($row['cnt'] ?? 0) . ':' . ($row['latest'] ?? '')),
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

        // Layout versions per platform
        $platforms = $platform ? [$platform] : ['web', 'mobile', 'tv', 'stb'];
        $manifest['layouts'] = [];
        foreach ($platforms as $p) {
            $row = $this->safeQuery(fn() => $this->db->fetch(
                "SELECT id, updated_at FROM app_layouts WHERE platform = ? AND is_default = 1 AND status = 'published' LIMIT 1",
                [$p]
            ));
            if ($row) {
                $manifest['layouts'][$p] = [
                    'version' => md5($row['id'] . ':' . $row['updated_at']),
                    'layout_id' => (int)$row['id'],
                    'updated_at' => $row['updated_at'],
                ];
            }
        }

        // Navigation versions per platform
        $manifest['navigation'] = [];
        foreach ($platforms as $p) {
            $row = $this->safeQuery(fn() => $this->db->fetch(
                "SELECT MAX(n.updated_at) as latest
                 FROM app_navigation n WHERE n.platform = ?",
                [$p]
            ));
            if ($row && $row['latest']) {
                $manifest['navigation'][$p] = [
                    'version' => md5($p . ':' . $row['latest']),
                    'updated_at' => $row['latest'],
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

        $channels = $this->db->fetchAll(
            "SELECT c.id, c.name, c.slug, c.logo_url, c.stream_url,
                    c.epg_channel_id, c.category_id, c.country,
                    c.is_hd, c.channel_number, c.sort_order, c.updated_at,
                    cat.name as category_name
             FROM channels c
             LEFT JOIN categories cat ON c.category_id = cat.id
             WHERE {$whereStr}
             ORDER BY c.sort_order ASC, c.name ASC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

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
        $channel = $this->db->fetch(
            "SELECT c.*, cat.name as category_name
             FROM channels c
             LEFT JOIN categories cat ON c.category_id = cat.id
             WHERE c.id = ? AND c.is_active = 1",
            [$id]
        );

        if (!$channel) {
            return null;
        }

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

        $movies = $this->db->fetchAll(
            "SELECT m.id, m.title, m.slug, m.year, m.genres, m.runtime,
                    m.vote_average, m.poster_url, m.backdrop_url,
                    m.stream_url, m.is_featured,
                    m.category_id, m.updated_at,
                    cat.name as category_name
             FROM movies m
             LEFT JOIN categories cat ON m.category_id = cat.id
             WHERE {$whereStr}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

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
        $movie = $this->db->fetch(
            "SELECT m.*, cat.name as category_name
             FROM movies m
             LEFT JOIN categories cat ON m.category_id = cat.id
             WHERE m.id = ? AND m.status = 'published'",
            [$id]
        );

        if (!$movie) {
            return null;
        }

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

        // Cast
        $movie['cast'] = $this->safeQuery(fn() => $this->db->fetchAll(
            "SELECT name, character_name, profile_url, role, sort_order
             FROM movie_cast WHERE movie_id = ? ORDER BY sort_order ASC LIMIT 20",
            [$id]
        ), []);

        return $movie;
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

        // Use cached counts from series table (number_of_seasons, number_of_episodes)
        $series = $this->db->fetchAll(
            "SELECT s.id, s.title, s.slug, s.year, s.genres, s.synopsis,
                    s.vote_average, s.poster_url, s.backdrop_url,
                    s.is_featured, s.category_id, s.updated_at,
                    s.number_of_seasons as season_count,
                    s.number_of_episodes as episode_count,
                    cat.name as category_name
             FROM series s
             LEFT JOIN categories cat ON s.category_id = cat.id
             WHERE {$whereStr}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

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
        $show = $this->db->fetch(
            "SELECT s.*, cat.name as category_name
             FROM series s
             LEFT JOIN categories cat ON s.category_id = cat.id
             WHERE s.id = ? AND s.status = 'published'",
            [$id]
        );

        if (!$show) {
            return null;
        }

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

        return $show;
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
        $where = ["p.end_time > NOW()"];
        $params = [];

        if (!empty($filters['channel_id'])) {
            $where[] = "p.channel_id = ?";
            $params[] = $filters['channel_id'];
        }

        if (!empty($filters['date'])) {
            $where[] = "DATE(p.start_time) = ?";
            $params[] = $filters['date'];
        }

        // Default: next 24 hours
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

        // Get sections
        $layout['sections'] = $this->db->fetchAll(
            "SELECT id, section_type, title, settings, sort_order, is_active
             FROM app_layout_sections
             WHERE layout_id = ? AND is_active = 1
             ORDER BY sort_order ASC",
            [$layout['id']]
        );

        // Get items for each section and decode settings
        foreach ($layout['sections'] as &$section) {
            $section['settings'] = json_decode($section['settings'] ?? '{}', true);

            $section['items'] = $this->db->fetchAll(
                "SELECT i.id, i.content_type, i.content_id, i.settings, i.sort_order
                 FROM app_layout_items i
                 WHERE i.section_id = ?
                 ORDER BY i.sort_order ASC",
                [$section['id']]
            );

            // Resolve content for each item
            foreach ($section['items'] as &$item) {
                $item['settings'] = json_decode($item['settings'] ?? '{}', true);
                $item['content'] = $this->resolveContentItem($item['content_type'], $item['content_id'], $item['settings']);
            }
        }

        return $layout;
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
                 FROM movies WHERE id = ? AND status IN ('draft', 'published')",
                [$contentId]
            )),
            'series' => $this->safeQuery(fn() => $this->db->fetch(
                "SELECT id, title, slug, year, genres, vote_average,
                        poster_url, backdrop_url, synopsis
                 FROM series WHERE id = ? AND status IN ('draft', 'published')",
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
}
