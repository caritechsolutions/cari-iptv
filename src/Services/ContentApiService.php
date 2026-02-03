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

        // Channels version
        $row = $this->db->fetch(
            "SELECT COUNT(*) as cnt, MAX(updated_at) as latest FROM channels WHERE status = 'active'"
        );
        $manifest['channels'] = [
            'version' => md5(($row['cnt'] ?? 0) . ':' . ($row['latest'] ?? '')),
            'count' => (int)($row['cnt'] ?? 0),
            'updated_at' => $row['latest'] ?? null,
        ];

        // Movies version
        $row = $this->db->fetch(
            "SELECT COUNT(*) as cnt, MAX(updated_at) as latest FROM movies WHERE status = 'published'"
        );
        $manifest['movies'] = [
            'version' => md5(($row['cnt'] ?? 0) . ':' . ($row['latest'] ?? '')),
            'count' => (int)($row['cnt'] ?? 0),
            'updated_at' => $row['latest'] ?? null,
        ];

        // Series version
        $row = $this->db->fetch(
            "SELECT COUNT(*) as cnt, MAX(updated_at) as latest FROM series WHERE status = 'published'"
        );
        $manifest['series'] = [
            'version' => md5(($row['cnt'] ?? 0) . ':' . ($row['latest'] ?? '')),
            'count' => (int)($row['cnt'] ?? 0),
            'updated_at' => $row['latest'] ?? null,
        ];

        // Categories version
        $row = $this->db->fetch(
            "SELECT COUNT(*) as cnt, MAX(updated_at) as latest FROM categories WHERE is_active = 1"
        );
        $manifest['categories'] = [
            'version' => md5(($row['cnt'] ?? 0) . ':' . ($row['latest'] ?? '')),
            'count' => (int)($row['cnt'] ?? 0),
            'updated_at' => $row['latest'] ?? null,
        ];

        // EPG version (programmes table)
        try {
            $row = $this->db->fetch(
                "SELECT COUNT(*) as cnt, MAX(updated_at) as latest FROM epg_programmes WHERE end_time > NOW()"
            );
            $manifest['epg'] = [
                'version' => md5(($row['cnt'] ?? 0) . ':' . ($row['latest'] ?? '')),
                'count' => (int)($row['cnt'] ?? 0),
                'updated_at' => $row['latest'] ?? null,
            ];
        } catch (\Throwable $e) {
            $manifest['epg'] = ['version' => 'none', 'count' => 0, 'updated_at' => null];
        }

        // Layout versions per platform
        $platforms = $platform ? [$platform] : ['web', 'mobile', 'tv', 'stb'];
        $manifest['layouts'] = [];
        foreach ($platforms as $p) {
            try {
                $row = $this->db->fetch(
                    "SELECT id, updated_at FROM app_layouts WHERE platform = ? AND is_default = 1 AND status = 'published' LIMIT 1",
                    [$p]
                );
                if ($row) {
                    $manifest['layouts'][$p] = [
                        'version' => md5($row['id'] . ':' . $row['updated_at']),
                        'layout_id' => (int)$row['id'],
                        'updated_at' => $row['updated_at'],
                    ];
                }
            } catch (\Throwable $e) {
                // Table may not exist yet
            }
        }

        // Navigation versions per platform
        $manifest['navigation'] = [];
        foreach ($platforms as $p) {
            try {
                $row = $this->db->fetch(
                    "SELECT MAX(n.updated_at) as latest,
                            (SELECT MAX(ni.updated_at) FROM app_navigation_items ni
                             JOIN app_navigation nav ON ni.navigation_id = nav.id
                             WHERE nav.platform = ?) as items_latest
                     FROM app_navigation n WHERE n.platform = ?",
                    [$p, $p]
                );
                $combined = max($row['latest'] ?? '', $row['items_latest'] ?? '');
                if ($combined) {
                    $manifest['navigation'][$p] = [
                        'version' => md5($p . ':' . $combined),
                        'updated_at' => $combined,
                    ];
                }
            } catch (\Throwable $e) {
                // Table may not exist yet
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
        $where = ["c.status = 'active'"];
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
        $orderBy = $filters['sort'] ?? 'c.sort_order ASC, c.name ASC';

        $limit = min((int)($filters['limit'] ?? 500), 1000);
        $offset = max((int)($filters['offset'] ?? 0), 0);

        $channels = $this->db->fetchAll(
            "SELECT c.id, c.name, c.slug, c.logo_url, c.stream_url, c.stream_type,
                    c.epg_channel_id, c.category_id, c.country_code,
                    c.is_hd, c.sort_order, c.updated_at,
                    cat.name as category_name
             FROM channels c
             LEFT JOIN categories cat ON c.category_id = cat.id
             WHERE {$whereStr}
             ORDER BY {$orderBy}
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
             WHERE c.id = ? AND c.status = 'active'",
            [$id]
        );

        if (!$channel) {
            return null;
        }

        // Get current EPG programme
        try {
            $channel['now_playing'] = $this->db->fetch(
                "SELECT title, description, start_time, end_time
                 FROM epg_programmes
                 WHERE channel_id = ? AND start_time <= NOW() AND end_time > NOW()
                 LIMIT 1",
                [$id]
            );
            $channel['next_up'] = $this->db->fetch(
                "SELECT title, description, start_time, end_time
                 FROM epg_programmes
                 WHERE channel_id = ? AND start_time > NOW()
                 ORDER BY start_time ASC LIMIT 1",
                [$id]
            );
        } catch (\Throwable $e) {
            $channel['now_playing'] = null;
            $channel['next_up'] = null;
        }

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
            $where[] = "m.genre LIKE ?";
            $params[] = '%' . $filters['genre'] . '%';
        }

        if (!empty($filters['year'])) {
            $where[] = "m.year = ?";
            $params[] = $filters['year'];
        }

        $whereStr = implode(' AND ', $where);

        // Sort options
        $sortMap = [
            'latest' => 'm.created_at DESC',
            'title' => 'm.title ASC',
            'year' => 'm.year DESC, m.title ASC',
            'rating' => 'm.tmdb_rating DESC',
            'popular' => 'm.tmdb_popularity DESC',
        ];
        $orderBy = $sortMap[$filters['sort'] ?? 'latest'] ?? 'm.created_at DESC';

        $limit = min((int)($filters['limit'] ?? 50), 200);
        $offset = max((int)($filters['offset'] ?? 0), 0);

        $movies = $this->db->fetchAll(
            "SELECT m.id, m.title, m.slug, m.year, m.genre, m.runtime,
                    m.tmdb_rating, m.tmdb_popularity, m.poster_url, m.backdrop_url,
                    m.stream_url, m.stream_type, m.is_featured,
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

        // Trailers
        try {
            $movie['trailers'] = $this->db->fetchAll(
                "SELECT id, title, youtube_id, youtube_url, is_primary
                 FROM movie_trailers WHERE movie_id = ? ORDER BY is_primary DESC, sort_order ASC",
                [$id]
            );
        } catch (\Throwable $e) {
            $movie['trailers'] = [];
        }

        // Artwork
        try {
            $movie['artwork'] = $this->db->fetchAll(
                "SELECT id, type, url, language, is_primary
                 FROM movie_artwork WHERE movie_id = ? ORDER BY is_primary DESC",
                [$id]
            );
        } catch (\Throwable $e) {
            $movie['artwork'] = [];
        }

        // Cast
        try {
            $movie['cast'] = $this->db->fetchAll(
                "SELECT name, character_name, profile_path, role, sort_order
                 FROM movie_cast WHERE movie_id = ? ORDER BY sort_order ASC LIMIT 20",
                [$id]
            );
        } catch (\Throwable $e) {
            $movie['cast'] = [];
        }

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
            $where[] = "s.genre LIKE ?";
            $params[] = '%' . $filters['genre'] . '%';
        }

        $whereStr = implode(' AND ', $where);

        $sortMap = [
            'latest' => 's.created_at DESC',
            'title' => 's.title ASC',
            'year' => 's.year DESC, s.title ASC',
            'rating' => 's.tmdb_rating DESC',
            'popular' => 's.tmdb_popularity DESC',
        ];
        $orderBy = $sortMap[$filters['sort'] ?? 'latest'] ?? 's.created_at DESC';

        $limit = min((int)($filters['limit'] ?? 50), 200);
        $offset = max((int)($filters['offset'] ?? 0), 0);

        $series = $this->db->fetchAll(
            "SELECT s.id, s.title, s.slug, s.year, s.genre, s.synopsis,
                    s.tmdb_rating, s.tmdb_popularity, s.poster_url, s.backdrop_url,
                    s.is_featured, s.category_id, s.updated_at,
                    cat.name as category_name,
                    (SELECT COUNT(*) FROM seasons WHERE series_id = s.id) as season_count,
                    (SELECT COUNT(*) FROM episodes e JOIN seasons sn ON e.season_id = sn.id WHERE sn.series_id = s.id) as episode_count
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

        // Seasons with episode counts
        $show['seasons'] = $this->db->fetchAll(
            "SELECT sn.*,
                    (SELECT COUNT(*) FROM episodes WHERE season_id = sn.id) as episode_count
             FROM seasons sn
             WHERE sn.series_id = ?
             ORDER BY sn.season_number ASC",
            [$id]
        );

        // Episodes per season
        foreach ($show['seasons'] as &$season) {
            $season['episodes'] = $this->db->fetchAll(
                "SELECT id, title, episode_number, synopsis, runtime,
                        stream_url, stream_type, still_path, air_date
                 FROM episodes
                 WHERE season_id = ?
                 ORDER BY episode_number ASC",
                [$season['id']]
            );
        }

        // Trailers (series-level)
        try {
            $show['trailers'] = $this->db->fetchAll(
                "SELECT id, title, youtube_id, youtube_url, is_primary
                 FROM movie_trailers WHERE movie_id = ? ORDER BY is_primary DESC, sort_order ASC",
                [$id]
            );
        } catch (\Throwable $e) {
            $show['trailers'] = [];
        }

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

        if (!empty($filters['since'])) {
            $where[] = "c.updated_at > ?";
            $params[] = $filters['since'];
        }

        $whereStr = implode(' AND ', $where);

        return $this->db->fetchAll(
            "SELECT c.id, c.name, c.slug, c.type, c.icon, c.sort_order, c.updated_at
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

        return $this->db->fetchAll(
            "SELECT p.id, p.channel_id, p.title, p.description,
                    p.start_time, p.end_time, p.category, p.icon,
                    c.name as channel_name
             FROM epg_programmes p
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
        $layout = $this->db->fetch(
            "SELECT id, name, platform, status, updated_at
             FROM app_layouts
             WHERE platform = ? AND is_default = 1 AND status = 'published'
             LIMIT 1",
            [$platform]
        );

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

        switch ($type) {
            case 'movie':
                return $this->db->fetch(
                    "SELECT id, title, slug, year, genre, runtime, tmdb_rating,
                            poster_url, backdrop_url, stream_url, synopsis
                     FROM movies WHERE id = ? AND status = 'published'",
                    [$contentId]
                );

            case 'series':
                return $this->db->fetch(
                    "SELECT id, title, slug, year, genre, tmdb_rating,
                            poster_url, backdrop_url, synopsis
                     FROM series WHERE id = ? AND status = 'published'",
                    [$contentId]
                );

            case 'channel':
                return $this->db->fetch(
                    "SELECT id, name, slug, logo_url, stream_url, stream_type, is_hd
                     FROM channels WHERE id = ? AND status = 'active'",
                    [$contentId]
                );

            case 'category':
                return $this->db->fetch(
                    "SELECT id, name, slug, type, icon
                     FROM categories WHERE id = ? AND is_active = 1",
                    [$contentId]
                );

            case 'custom':
                return [
                    'title' => $settings['title'] ?? null,
                    'image_url' => $settings['image_url'] ?? null,
                    'link_url' => $settings['link_url'] ?? null,
                ];

            default:
                return null;
        }
    }

    // =========================================================================
    // NAVIGATION
    // =========================================================================

    public function getNavigation(string $platform, string $position = 'main'): ?array
    {
        $nav = $this->db->fetch(
            "SELECT id, platform, position, settings
             FROM app_navigation
             WHERE platform = ? AND position = ?
             LIMIT 1",
            [$platform, $position]
        );

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
        return $this->db->fetchAll(
            "SELECT id, name, slug, page_type, icon, layout_id, is_system, sort_order
             FROM app_pages
             WHERE platform = ? AND status = 'active'
             ORDER BY sort_order ASC",
            [$platform]
        );
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
            $channels = $this->db->fetchAll(
                "SELECT id, name as title, slug, logo_url as image_url, 'channel' as content_type
                 FROM channels
                 WHERE status = 'active' AND (name LIKE ? OR slug LIKE ?)
                 ORDER BY name ASC LIMIT ?",
                [$searchTerm, $searchTerm, $limit]
            );
            $results = array_merge($results, $channels);
        }

        if ($types === 'all' || $types === 'movie') {
            $movies = $this->db->fetchAll(
                "SELECT id, title, slug, poster_url as image_url, year, genre, tmdb_rating, 'movie' as content_type
                 FROM movies
                 WHERE status = 'published' AND (title LIKE ? OR slug LIKE ? OR synopsis LIKE ?)
                 ORDER BY tmdb_popularity DESC LIMIT ?",
                [$searchTerm, $searchTerm, $searchTerm, $limit]
            );
            $results = array_merge($results, $movies);
        }

        if ($types === 'all' || $types === 'series') {
            $series = $this->db->fetchAll(
                "SELECT id, title, slug, poster_url as image_url, year, genre, tmdb_rating, 'series' as content_type
                 FROM series
                 WHERE status = 'published' AND (title LIKE ? OR slug LIKE ? OR synopsis LIKE ?)
                 ORDER BY tmdb_popularity DESC LIMIT ?",
                [$searchTerm, $searchTerm, $searchTerm, $limit]
            );
            $results = array_merge($results, $series);
        }

        return $results;
    }
}
