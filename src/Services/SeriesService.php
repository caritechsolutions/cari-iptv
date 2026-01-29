<?php
/**
 * CARI-IPTV Series (TV Shows) Service
 * Business logic for TV show management including seasons and episodes
 */

namespace CariIPTV\Services;

use CariIPTV\Core\Database;
use CariIPTV\Services\ImageService;

class SeriesService
{
    private Database $db;
    private ImageService $imageService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->imageService = new ImageService();
    }

    // ========================================================================
    // TV SHOW CRUD
    // ========================================================================

    /**
     * Get all series with filters and pagination
     */
    public function getSeries(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = '(s.title LIKE ? OR s.original_title LIKE ? OR s.creators LIKE ?)';
            $params = array_merge($params, [$search, $search, $search]);
        }

        if (!empty($filters['status'])) {
            $where[] = 's.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['year'])) {
            $where[] = 's.year = ?';
            $params[] = (int) $filters['year'];
        }

        if (!empty($filters['category_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM series_categories sc WHERE sc.series_id = s.id AND sc.category_id = ?)';
            $params[] = (int) $filters['category_id'];
        }

        if (isset($filters['featured']) && $filters['featured'] !== '') {
            $where[] = 's.is_featured = ?';
            $params[] = (int) $filters['featured'];
        }

        if (!empty($filters['source'])) {
            $where[] = 's.source = ?';
            $params[] = $filters['source'];
        }

        $whereClause = implode(' AND ', $where);

        $sortColumn = $filters['sort'] ?? 'created_at';
        $sortDir = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $allowedSorts = ['title', 'year', 'created_at', 'views', 'vote_average', 'number_of_seasons'];
        if (!in_array($sortColumn, $allowedSorts)) {
            $sortColumn = 'created_at';
        }

        $totalSql = "SELECT COUNT(*) FROM series s WHERE {$whereClause}";
        $total = (int) $this->db->fetch($totalSql, $params)['COUNT(*)'];

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT
                s.*,
                GROUP_CONCAT(DISTINCT cat.name ORDER BY sc.is_primary DESC, cat.name SEPARATOR ', ') as category_names
            FROM series s
            LEFT JOIN series_categories sc ON s.id = sc.series_id
            LEFT JOIN categories cat ON sc.category_id = cat.id
            WHERE {$whereClause}
            GROUP BY s.id
            ORDER BY s.{$sortColumn} {$sortDir}
            LIMIT {$perPage} OFFSET {$offset}
        ";

        $shows = $this->db->fetchAll($sql, $params);

        foreach ($shows as &$show) {
            if ($show['genres']) {
                $show['genres'] = json_decode($show['genres'], true);
            }
        }

        return [
            'data' => $shows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }

    /**
     * Get a single series by ID with all related data
     */
    public function getShow(int $id): ?array
    {
        $show = $this->db->fetch("SELECT * FROM series WHERE id = ?", [$id]);

        if ($show) {
            if ($show['genres']) {
                $show['genres'] = json_decode($show['genres'], true);
            }

            $show['categories'] = $this->db->fetchAll(
                "SELECT sc.category_id, sc.is_primary, cat.name, cat.slug
                 FROM series_categories sc
                 JOIN categories cat ON sc.category_id = cat.id
                 WHERE sc.series_id = ?
                 ORDER BY sc.is_primary DESC, cat.name",
                [$id]
            );

            $show['seasons'] = $this->db->fetchAll(
                "SELECT ss.*,
                    (SELECT COUNT(*) FROM series_episodes se WHERE se.season_id = ss.id) as actual_episode_count,
                    (SELECT COUNT(*) FROM series_trailers st WHERE st.season_id = ss.id) as trailer_count
                 FROM series_seasons ss
                 WHERE ss.series_id = ?
                 ORDER BY ss.season_number ASC",
                [$id]
            );

            $show['artwork'] = $this->db->fetchAll(
                "SELECT * FROM series_artwork WHERE series_id = ? ORDER BY type, is_primary DESC",
                [$id]
            );

            $show['cast'] = $this->db->fetchAll(
                "SELECT * FROM series_cast WHERE series_id = ? ORDER BY role, sort_order ASC",
                [$id]
            );

            $show['trailers'] = $this->db->fetchAll(
                "SELECT * FROM series_trailers WHERE series_id = ? AND season_id IS NULL ORDER BY is_primary DESC, sort_order ASC",
                [$id]
            );
        }

        return $show;
    }

    /**
     * Create a new series
     */
    public function createShow(array $data): int
    {
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['title']);
        }

        if (isset($data['genres']) && is_array($data['genres'])) {
            $data['genres'] = json_encode($data['genres']);
        }

        $categories = $data['categories'] ?? [];
        $primaryCategory = $data['primary_category'] ?? null;
        $cast = $data['cast'] ?? [];

        unset($data['categories'], $data['primary_category'], $data['cast']);

        if ($primaryCategory) {
            $data['category_id'] = $primaryCategory;
        } elseif (!empty($categories)) {
            $data['category_id'] = $categories[0];
        }

        $showId = $this->db->insert('series', $data);

        if (!empty($categories)) {
            $this->saveSeriesCategories($showId, $categories, $primaryCategory);
        }

        if (!empty($cast)) {
            $this->saveSeriesCast($showId, $cast);
        }

        return $showId;
    }

    /**
     * Update an existing series
     */
    public function updateShow(int $id, array $data): bool
    {
        if (!empty($data['title']) && empty($data['slug'])) {
            $existing = $this->db->fetch("SELECT slug, title FROM series WHERE id = ?", [$id]);
            if ($existing && $existing['title'] !== $data['title']) {
                $data['slug'] = $this->generateSlug($data['title'], $id);
            }
        }

        if (isset($data['genres']) && is_array($data['genres'])) {
            $data['genres'] = json_encode($data['genres']);
        }

        $categories = $data['categories'] ?? null;
        $primaryCategory = $data['primary_category'] ?? null;
        $cast = $data['cast'] ?? null;

        unset($data['categories'], $data['primary_category'], $data['cast']);

        if ($primaryCategory) {
            $data['category_id'] = $primaryCategory;
        } elseif ($categories !== null && !empty($categories)) {
            $data['category_id'] = $categories[0];
        }

        $this->db->update('series', $data, 'id = ?', [$id]);

        if ($categories !== null) {
            $this->saveSeriesCategories($id, $categories, $primaryCategory);
        }

        if ($cast !== null) {
            $this->saveSeriesCast($id, $cast);
        }

        return true;
    }

    /**
     * Delete a series and all related data
     */
    public function deleteShow(int $id): bool
    {
        $this->db->delete('series_categories', 'series_id = ?', [$id]);
        $this->db->delete('series_trailers', 'series_id = ?', [$id]);
        $this->db->delete('series_artwork', 'series_id = ?', [$id]);
        $this->db->delete('series_cast', 'series_id = ?', [$id]);
        $this->db->delete('series_episodes', 'series_id = ?', [$id]);
        $this->db->delete('series_seasons', 'series_id = ?', [$id]);

        return $this->db->delete('series', 'id = ?', [$id]) > 0;
    }

    /**
     * Toggle series featured status
     */
    public function toggleFeatured(int $id): bool
    {
        $show = $this->db->fetch("SELECT is_featured FROM series WHERE id = ?", [$id]);
        if (!$show) {
            return false;
        }

        $newStatus = $show['is_featured'] ? 0 : 1;
        $this->db->update('series', ['is_featured' => $newStatus], 'id = ?', [$id]);

        return true;
    }

    /**
     * Update series status
     */
    public function updateStatus(int $id, string $status): bool
    {
        $allowedStatuses = ['draft', 'published', 'archived'];
        if (!in_array($status, $allowedStatuses)) {
            return false;
        }

        $this->db->update('series', ['status' => $status], 'id = ?', [$id]);
        return true;
    }

    /**
     * Bulk update series status
     */
    public function bulkUpdateStatus(array $ids, string $status): int
    {
        if (empty($ids)) {
            return 0;
        }

        $allowedStatuses = ['draft', 'published', 'archived'];
        if (!in_array($status, $allowedStatuses)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE series SET status = ? WHERE id IN ({$placeholders})";

        return $this->db->query($sql, array_merge([$status], $ids))->rowCount();
    }

    // ========================================================================
    // SEASONS
    // ========================================================================

    /**
     * Get all seasons for a series
     */
    public function getSeasons(int $seriesId): array
    {
        return $this->db->fetchAll(
            "SELECT ss.*,
                (SELECT COUNT(*) FROM series_episodes se WHERE se.season_id = ss.id) as actual_episode_count,
                (SELECT COUNT(*) FROM series_trailers st WHERE st.season_id = ss.id) as trailer_count
             FROM series_seasons ss
             WHERE ss.series_id = ?
             ORDER BY ss.season_number ASC",
            [$seriesId]
        );
    }

    /**
     * Get a single season
     */
    public function getSeason(int $seasonId): ?array
    {
        $season = $this->db->fetch("SELECT * FROM series_seasons WHERE id = ?", [$seasonId]);

        if ($season) {
            $season['episodes'] = $this->db->fetchAll(
                "SELECT * FROM series_episodes WHERE season_id = ? ORDER BY episode_number ASC",
                [$seasonId]
            );

            $season['trailers'] = $this->db->fetchAll(
                "SELECT * FROM series_trailers WHERE season_id = ? ORDER BY is_primary DESC, sort_order ASC",
                [$seasonId]
            );
        }

        return $season;
    }

    /**
     * Create a season
     */
    public function createSeason(int $seriesId, array $data): int
    {
        $data['series_id'] = $seriesId;
        $seasonId = $this->db->insert('series_seasons', $data);
        $this->updateSeasonCount($seriesId);
        return $seasonId;
    }

    /**
     * Update a season
     */
    public function updateSeason(int $seasonId, array $data): bool
    {
        $this->db->update('series_seasons', $data, 'id = ?', [$seasonId]);
        return true;
    }

    /**
     * Delete a season and its episodes
     */
    public function deleteSeason(int $seasonId): bool
    {
        $season = $this->db->fetch("SELECT series_id FROM series_seasons WHERE id = ?", [$seasonId]);
        if (!$season) {
            return false;
        }

        $this->db->delete('series_trailers', 'season_id = ?', [$seasonId]);
        $this->db->delete('series_episodes', 'season_id = ?', [$seasonId]);
        $result = $this->db->delete('series_seasons', 'id = ?', [$seasonId]) > 0;

        if ($result) {
            $this->updateSeasonCount($season['series_id']);
            $this->updateEpisodeCount($season['series_id']);
        }

        return $result;
    }

    // ========================================================================
    // EPISODES
    // ========================================================================

    /**
     * Get all episodes for a season
     */
    public function getEpisodes(int $seasonId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM series_episodes WHERE season_id = ? ORDER BY episode_number ASC",
            [$seasonId]
        );
    }

    /**
     * Get a single episode
     */
    public function getEpisode(int $episodeId): ?array
    {
        return $this->db->fetch("SELECT * FROM series_episodes WHERE id = ?", [$episodeId]);
    }

    /**
     * Create an episode
     */
    public function createEpisode(int $seriesId, int $seasonId, array $data): int
    {
        $data['series_id'] = $seriesId;
        $data['season_id'] = $seasonId;
        $episodeId = $this->db->insert('series_episodes', $data);

        $this->updateEpisodeCount($seriesId);
        $this->updateSeasonEpisodeCount($seasonId);

        return $episodeId;
    }

    /**
     * Update an episode
     */
    public function updateEpisode(int $episodeId, array $data): bool
    {
        $this->db->update('series_episodes', $data, 'id = ?', [$episodeId]);
        return true;
    }

    /**
     * Delete an episode
     */
    public function deleteEpisode(int $episodeId): bool
    {
        $episode = $this->db->fetch("SELECT series_id, season_id FROM series_episodes WHERE id = ?", [$episodeId]);
        if (!$episode) {
            return false;
        }

        $result = $this->db->delete('series_episodes', 'id = ?', [$episodeId]) > 0;

        if ($result) {
            $this->updateEpisodeCount($episode['series_id']);
            $this->updateSeasonEpisodeCount($episode['season_id']);
        }

        return $result;
    }

    // ========================================================================
    // TRAILERS (per season)
    // ========================================================================

    /**
     * Add trailer to a season (or show-level if seasonId is null)
     */
    public function addTrailer(int $seriesId, ?int $seasonId, array $trailer): int
    {
        $trailer['series_id'] = $seriesId;
        $trailer['season_id'] = $seasonId;
        return $this->db->insert('series_trailers', $trailer);
    }

    /**
     * Remove a trailer
     */
    public function removeTrailer(int $trailerId): bool
    {
        return $this->db->delete('series_trailers', 'id = ?', [$trailerId]) > 0;
    }

    /**
     * Get trailers for a season
     */
    public function getSeasonTrailers(int $seasonId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM series_trailers WHERE season_id = ? ORDER BY is_primary DESC, sort_order ASC",
            [$seasonId]
        );
    }

    // ========================================================================
    // CATEGORIES
    // ========================================================================

    /**
     * Get categories for series (type = 'series')
     */
    public function getCategories(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, slug, parent_id, is_active
             FROM categories
             WHERE type = 'series' AND is_active = 1
             ORDER BY sort_order, name"
        );
    }

    /**
     * Get or create category by name
     */
    public function getOrCreateCategory(string $name): int
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-') . '-series';

        $existing = $this->db->fetch(
            "SELECT id FROM categories WHERE slug = ? AND type = 'series'",
            [$slug]
        );

        if ($existing) {
            return (int) $existing['id'];
        }

        return $this->db->insert('categories', [
            'name' => $name,
            'slug' => $slug,
            'type' => 'series',
            'is_active' => 1,
            'sort_order' => 99,
        ]);
    }

    // ========================================================================
    // IMAGE PROCESSING
    // ========================================================================

    /**
     * Process series images (poster, backdrop) and convert to WebP
     */
    public function processImages(int $seriesId, array $data): array
    {
        $processed = [];

        if (!empty($data['poster_url']) && str_starts_with($data['poster_url'], 'http')) {
            $result = $this->imageService->processFromUrl(
                $data['poster_url'],
                'vod',
                $seriesId,
                'poster'
            );
            if ($result['success']) {
                $processed['poster_url'] = $result['variants']['poster'] ?? $result['base_path'] . '_poster.webp';
            }
        }

        if (!empty($data['backdrop_url']) && str_starts_with($data['backdrop_url'], 'http')) {
            $result = $this->imageService->processFromUrl(
                $data['backdrop_url'],
                'vod',
                $seriesId,
                'backdrop'
            );
            if ($result['success']) {
                $processed['backdrop_url'] = $result['variants']['backdrop'] ?? $result['base_path'] . '_backdrop.webp';
            }
        }

        if (!empty($processed)) {
            $this->db->update('series', $processed, 'id = ?', [$seriesId]);
        }

        return $processed;
    }

    // ========================================================================
    // TMDB IMPORT
    // ========================================================================

    /**
     * Check if series exists by TMDB ID
     */
    public function findByTmdbId(int $tmdbId): ?array
    {
        return $this->db->fetch("SELECT * FROM series WHERE tmdb_id = ?", [$tmdbId]);
    }

    /**
     * Import series from TMDB metadata
     */
    public function importFromTmdb(array $tmdbData): int
    {
        $showData = [
            'tmdb_id' => $tmdbData['id'],
            'title' => $tmdbData['name'],
            'original_title' => $tmdbData['original_name'] ?? null,
            'tagline' => $tmdbData['tagline'] ?? null,
            'synopsis' => $tmdbData['overview'] ?? null,
            'year' => !empty($tmdbData['first_air_date']) ? (int) substr($tmdbData['first_air_date'], 0, 4) : null,
            'first_air_date' => $tmdbData['first_air_date'] ?? null,
            'last_air_date' => $tmdbData['last_air_date'] ?? null,
            'show_status' => $tmdbData['status'] ?? null,
            'episode_run_time' => $tmdbData['episode_run_time'] ?? null,
            'vote_average' => $tmdbData['vote_average'] ?? null,
            'genres' => $tmdbData['genres'] ?? [],
            'creators' => !empty($tmdbData['creators']) ? implode(', ', $tmdbData['creators']) : null,
            'networks' => !empty($tmdbData['networks']) ? implode(', ', $tmdbData['networks']) : null,
            'poster_url' => $tmdbData['poster'] ?? null,
            'backdrop_url' => $tmdbData['backdrop'] ?? null,
            'number_of_seasons' => $tmdbData['number_of_seasons'] ?? 0,
            'number_of_episodes' => $tmdbData['number_of_episodes'] ?? 0,
            'source' => 'tmdb',
            'status' => 'draft',
        ];

        $showId = $this->createShow($showData);

        // Import cast
        if (!empty($tmdbData['cast'])) {
            $cast = [];
            foreach (array_slice($tmdbData['cast'], 0, 15) as $person) {
                $cast[] = [
                    'name' => $person['name'],
                    'character_name' => $person['character'] ?? null,
                    'role' => 'actor',
                    'profile_url' => $person['profile'] ?? null,
                ];
            }
            $this->saveSeriesCast($showId, $cast);
        }

        // Auto-create categories from genres
        if (!empty($tmdbData['genres'])) {
            $categoryIds = [];
            foreach ($tmdbData['genres'] as $genreName) {
                $categoryIds[] = $this->getOrCreateCategory($genreName);
            }
            if (!empty($categoryIds)) {
                $this->saveSeriesCategories($showId, $categoryIds, $categoryIds[0]);
            }
        }

        return $showId;
    }

    /**
     * Import seasons from TMDB for a series
     */
    public function importSeasonsFromTmdb(int $seriesId, int $tmdbId, array $seasonDetails): int
    {
        $seasonId = $this->createSeason($seriesId, [
            'tmdb_id' => $seasonDetails['id'] ?? null,
            'season_number' => $seasonDetails['season_number'],
            'name' => $seasonDetails['name'] ?? 'Season ' . $seasonDetails['season_number'],
            'overview' => $seasonDetails['overview'] ?? null,
            'poster_url' => $seasonDetails['poster'] ?? null,
            'air_date' => $seasonDetails['air_date'] ?? null,
            'episode_count' => count($seasonDetails['episodes'] ?? []),
        ]);

        // Import episodes
        foreach ($seasonDetails['episodes'] ?? [] as $ep) {
            $this->createEpisode($seriesId, $seasonId, [
                'tmdb_id' => $ep['id'] ?? null,
                'episode_number' => $ep['episode_number'],
                'name' => $ep['name'] ?? null,
                'overview' => $ep['overview'] ?? null,
                'air_date' => $ep['air_date'] ?? null,
                'runtime' => $ep['runtime'] ?? null,
                'still_url' => $ep['still'] ?? null,
                'vote_average' => $ep['vote_average'] ?? null,
            ]);
        }

        return $seasonId;
    }

    // ========================================================================
    // STATISTICS
    // ========================================================================

    /**
     * Get series statistics
     */
    public function getStatistics(): array
    {
        $stats = [];

        $stats['total'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM series"
        )['count'];

        $stats['published'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM series WHERE status = 'published'"
        )['count'];

        $stats['draft'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM series WHERE status = 'draft'"
        )['count'];

        $stats['featured'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM series WHERE is_featured = 1"
        )['count'];

        $stats['from_tmdb'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM series WHERE source = 'tmdb'"
        )['count'];

        $stats['total_seasons'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM series_seasons"
        )['count'];

        $stats['total_episodes'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM series_episodes"
        )['count'];

        return $stats;
    }

    /**
     * Get distinct years from series
     */
    public function getYears(): array
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT year FROM series WHERE year IS NOT NULL ORDER BY year DESC"
        );
    }

    // ========================================================================
    // PRIVATE HELPERS
    // ========================================================================

    private function saveSeriesCategories(int $seriesId, array $categoryIds, ?int $primaryCategoryId = null): void
    {
        $this->db->delete('series_categories', 'series_id = ?', [$seriesId]);

        $sortOrder = 0;
        foreach ($categoryIds as $catId) {
            $this->db->insert('series_categories', [
                'series_id' => $seriesId,
                'category_id' => (int) $catId,
                'is_primary' => ($catId == $primaryCategoryId) ? 1 : 0,
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    private function saveSeriesCast(int $seriesId, array $castList): void
    {
        $this->db->delete('series_cast', 'series_id = ?', [$seriesId]);

        $sortOrder = 0;
        foreach ($castList as $person) {
            $this->db->insert('series_cast', [
                'series_id' => $seriesId,
                'name' => $person['name'],
                'character_name' => $person['character_name'] ?? null,
                'role' => $person['role'] ?? 'actor',
                'profile_url' => $person['profile_url'] ?? null,
                'tmdb_person_id' => $person['tmdb_person_id'] ?? null,
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    private function generateSlug(string $title, ?int $excludeId = null): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        $baseSlug = $slug;
        $counter = 1;

        while (true) {
            $sql = "SELECT id FROM series WHERE slug = ?";
            $params = [$slug];

            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }

            $existing = $this->db->fetch($sql, $params);
            if (!$existing) {
                break;
            }

            $slug = $baseSlug . '-' . $counter++;
        }

        return $slug;
    }

    private function updateSeasonCount(int $seriesId): void
    {
        $count = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM series_seasons WHERE series_id = ?",
            [$seriesId]
        )['count'];

        $this->db->update('series', ['number_of_seasons' => $count], 'id = ?', [$seriesId]);
    }

    private function updateEpisodeCount(int $seriesId): void
    {
        $count = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM series_episodes WHERE series_id = ?",
            [$seriesId]
        )['count'];

        $this->db->update('series', ['number_of_episodes' => $count], 'id = ?', [$seriesId]);
    }

    private function updateSeasonEpisodeCount(int $seasonId): void
    {
        $count = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM series_episodes WHERE season_id = ?",
            [$seasonId]
        )['count'];

        $this->db->update('series_seasons', ['episode_count' => $count], 'id = ?', [$seasonId]);
    }
}
