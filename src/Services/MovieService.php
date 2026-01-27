<?php
/**
 * CARI-IPTV Movie Service
 * Business logic for movie management
 */

namespace CariIPTV\Services;

use CariIPTV\Core\Database;

class MovieService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get all movies with filters and pagination
     */
    public function getMovies(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        // Search filter
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = '(m.title LIKE ? OR m.original_title LIKE ? OR m.director LIKE ?)';
            $params = array_merge($params, [$search, $search, $search]);
        }

        // Status filter
        if (!empty($filters['status'])) {
            $where[] = 'm.status = ?';
            $params[] = $filters['status'];
        }

        // Year filter
        if (!empty($filters['year'])) {
            $where[] = 'm.year = ?';
            $params[] = (int) $filters['year'];
        }

        // Category filter
        if (!empty($filters['category_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM movie_categories mc WHERE mc.movie_id = m.id AND mc.category_id = ?)';
            $params[] = (int) $filters['category_id'];
        }

        // Featured filter
        if (isset($filters['featured']) && $filters['featured'] !== '') {
            $where[] = 'm.is_featured = ?';
            $params[] = (int) $filters['featured'];
        }

        // Free content filter
        if (isset($filters['is_free']) && $filters['is_free'] !== '') {
            $where[] = 'm.is_free = ?';
            $params[] = (int) $filters['is_free'];
        }

        // Source filter
        if (!empty($filters['source'])) {
            $where[] = 'm.source = ?';
            $params[] = $filters['source'];
        }

        $whereClause = implode(' AND ', $where);

        // Sorting
        $sortColumn = $filters['sort'] ?? 'created_at';
        $sortDir = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $allowedSorts = ['title', 'year', 'rating', 'created_at', 'views', 'vote_average'];
        if (!in_array($sortColumn, $allowedSorts)) {
            $sortColumn = 'created_at';
        }

        // Count total
        $totalSql = "SELECT COUNT(*) FROM movies m WHERE {$whereClause}";
        $total = (int) $this->db->fetch($totalSql, $params)['COUNT(*)'];

        // Pagination
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        // Fetch movies with related data
        $sql = "
            SELECT
                m.*,
                GROUP_CONCAT(DISTINCT cat.name ORDER BY mc.is_primary DESC, cat.name SEPARATOR ', ') as category_names,
                (SELECT COUNT(*) FROM movie_trailers mt WHERE mt.movie_id = m.id) as trailer_count
            FROM movies m
            LEFT JOIN movie_categories mc ON m.id = mc.movie_id
            LEFT JOIN categories cat ON mc.category_id = cat.id
            WHERE {$whereClause}
            GROUP BY m.id
            ORDER BY m.{$sortColumn} {$sortDir}
            LIMIT {$perPage} OFFSET {$offset}
        ";

        $movies = $this->db->fetchAll($sql, $params);

        // Decode JSON fields
        foreach ($movies as &$movie) {
            if ($movie['genres']) {
                $movie['genres'] = json_decode($movie['genres'], true);
            }
        }

        return [
            'data' => $movies,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }

    /**
     * Get a single movie by ID with all related data
     */
    public function getMovie(int $id): ?array
    {
        $movie = $this->db->fetch(
            "SELECT m.*
             FROM movies m
             WHERE m.id = ?",
            [$id]
        );

        if ($movie) {
            // Decode JSON fields
            if ($movie['genres']) {
                $movie['genres'] = json_decode($movie['genres'], true);
            }

            // Get movie categories
            $movie['categories'] = $this->db->fetchAll(
                "SELECT mc.category_id, mc.is_primary, cat.name, cat.slug
                 FROM movie_categories mc
                 JOIN categories cat ON mc.category_id = cat.id
                 WHERE mc.movie_id = ?
                 ORDER BY mc.is_primary DESC, cat.name",
                [$id]
            );

            // Get movie trailers
            $movie['trailers'] = $this->db->fetchAll(
                "SELECT * FROM movie_trailers
                 WHERE movie_id = ?
                 ORDER BY is_primary DESC, sort_order ASC",
                [$id]
            );

            // Get movie artwork
            $movie['artwork'] = $this->db->fetchAll(
                "SELECT * FROM movie_artwork
                 WHERE movie_id = ?
                 ORDER BY type, is_primary DESC",
                [$id]
            );

            // Get movie cast
            $movie['cast'] = $this->db->fetchAll(
                "SELECT * FROM movie_cast
                 WHERE movie_id = ?
                 ORDER BY role, sort_order ASC",
                [$id]
            );
        }

        return $movie;
    }

    /**
     * Create a new movie
     */
    public function createMovie(array $data): int
    {
        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['title']);
        }

        // Encode JSON fields
        if (isset($data['genres']) && is_array($data['genres'])) {
            $data['genres'] = json_encode($data['genres']);
        }

        // Extract related data
        $categories = $data['categories'] ?? [];
        $primaryCategory = $data['primary_category'] ?? null;
        $trailers = $data['trailers'] ?? [];
        $artwork = $data['artwork'] ?? [];
        $cast = $data['cast'] ?? [];

        unset($data['categories'], $data['primary_category'], $data['trailers'], $data['artwork'], $data['cast']);

        // Set legacy category_id from primary category
        if ($primaryCategory) {
            $data['category_id'] = $primaryCategory;
        } elseif (!empty($categories)) {
            $data['category_id'] = $categories[0];
        }

        // Insert movie
        $movieId = $this->db->insert('movies', $data);

        // Save categories
        if (!empty($categories)) {
            $this->saveMovieCategories($movieId, $categories, $primaryCategory);
        }

        // Save trailers
        if (!empty($trailers)) {
            $this->saveMovieTrailers($movieId, $trailers);
        }

        // Save artwork
        if (!empty($artwork)) {
            $this->saveMovieArtwork($movieId, $artwork);
        }

        // Save cast
        if (!empty($cast)) {
            $this->saveMovieCast($movieId, $cast);
        }

        return $movieId;
    }

    /**
     * Update an existing movie
     */
    public function updateMovie(int $id, array $data): bool
    {
        // Generate slug if title changed and slug not provided
        if (!empty($data['title']) && empty($data['slug'])) {
            $existing = $this->db->fetch("SELECT slug, title FROM movies WHERE id = ?", [$id]);
            if ($existing && $existing['title'] !== $data['title']) {
                $data['slug'] = $this->generateSlug($data['title'], $id);
            }
        }

        // Encode JSON fields
        if (isset($data['genres']) && is_array($data['genres'])) {
            $data['genres'] = json_encode($data['genres']);
        }

        // Extract related data
        $categories = $data['categories'] ?? null;
        $primaryCategory = $data['primary_category'] ?? null;
        $trailers = $data['trailers'] ?? null;
        $artwork = $data['artwork'] ?? null;
        $cast = $data['cast'] ?? null;

        unset($data['categories'], $data['primary_category'], $data['trailers'], $data['artwork'], $data['cast']);

        // Set legacy category_id from primary category
        if ($primaryCategory) {
            $data['category_id'] = $primaryCategory;
        } elseif ($categories !== null && !empty($categories)) {
            $data['category_id'] = $categories[0];
        }

        // Update movie
        $this->db->update('movies', $data, 'id = ?', [$id]);

        // Update categories if provided
        if ($categories !== null) {
            $this->saveMovieCategories($id, $categories, $primaryCategory);
        }

        // Update trailers if provided
        if ($trailers !== null) {
            $this->saveMovieTrailers($id, $trailers);
        }

        // Update artwork if provided
        if ($artwork !== null) {
            $this->saveMovieArtwork($id, $artwork);
        }

        // Update cast if provided
        if ($cast !== null) {
            $this->saveMovieCast($id, $cast);
        }

        return true;
    }

    /**
     * Delete a movie
     */
    public function deleteMovie(int $id): bool
    {
        // Delete related records (cascade should handle this, but be explicit)
        $this->db->delete('movie_categories', 'movie_id = ?', [$id]);
        $this->db->delete('movie_trailers', 'movie_id = ?', [$id]);
        $this->db->delete('movie_artwork', 'movie_id = ?', [$id]);
        $this->db->delete('movie_cast', 'movie_id = ?', [$id]);

        return $this->db->delete('movies', 'id = ?', [$id]) > 0;
    }

    /**
     * Toggle movie featured status
     */
    public function toggleFeatured(int $id): bool
    {
        $movie = $this->db->fetch("SELECT is_featured FROM movies WHERE id = ?", [$id]);
        if (!$movie) {
            return false;
        }

        $newStatus = $movie['is_featured'] ? 0 : 1;
        $this->db->update('movies', ['is_featured' => $newStatus], 'id = ?', [$id]);

        return true;
    }

    /**
     * Update movie status
     */
    public function updateStatus(int $id, string $status): bool
    {
        $allowedStatuses = ['draft', 'published', 'archived'];
        if (!in_array($status, $allowedStatuses)) {
            return false;
        }

        $this->db->update('movies', ['status' => $status], 'id = ?', [$id]);
        return true;
    }

    /**
     * Bulk update movie status
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
        $sql = "UPDATE movies SET status = ? WHERE id IN ({$placeholders})";

        return $this->db->execute($sql, array_merge([$status], $ids));
    }

    /**
     * Get all categories for movies (type = 'vod')
     */
    public function getCategories(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, slug, parent_id, is_active
             FROM categories
             WHERE type = 'vod' AND is_active = 1
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
        $slug = trim($slug, '-');

        $existing = $this->db->fetch(
            "SELECT id FROM categories WHERE slug = ? AND type = 'vod'",
            [$slug]
        );

        if ($existing) {
            return (int) $existing['id'];
        }

        // Create new category
        return $this->db->insert('categories', [
            'name' => $name,
            'slug' => $slug,
            'type' => 'vod',
            'is_active' => 1,
            'sort_order' => 99,
        ]);
    }

    /**
     * Add trailer to movie
     */
    public function addTrailer(int $movieId, array $trailer): int
    {
        $trailer['movie_id'] = $movieId;
        return $this->db->insert('movie_trailers', $trailer);
    }

    /**
     * Remove trailer from movie
     */
    public function removeTrailer(int $trailerId): bool
    {
        return $this->db->delete('movie_trailers', 'id = ?', [$trailerId]) > 0;
    }

    /**
     * Add artwork to movie
     */
    public function addArtwork(int $movieId, array $art): int
    {
        $art['movie_id'] = $movieId;
        return $this->db->insert('movie_artwork', $art);
    }

    /**
     * Remove artwork from movie
     */
    public function removeArtwork(int $artworkId): bool
    {
        return $this->db->delete('movie_artwork', 'id = ?', [$artworkId]) > 0;
    }

    /**
     * Save movie categories
     */
    private function saveMovieCategories(int $movieId, array $categoryIds, ?int $primaryCategoryId = null): void
    {
        // Delete existing
        $this->db->delete('movie_categories', 'movie_id = ?', [$movieId]);

        // Insert new
        $sortOrder = 0;
        foreach ($categoryIds as $catId) {
            $this->db->insert('movie_categories', [
                'movie_id' => $movieId,
                'category_id' => (int) $catId,
                'is_primary' => ($catId == $primaryCategoryId) ? 1 : 0,
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    /**
     * Save movie trailers
     */
    private function saveMovieTrailers(int $movieId, array $trailers): void
    {
        // Delete existing
        $this->db->delete('movie_trailers', 'movie_id = ?', [$movieId]);

        // Insert new
        $sortOrder = 0;
        foreach ($trailers as $trailer) {
            $this->db->insert('movie_trailers', [
                'movie_id' => $movieId,
                'name' => $trailer['name'] ?? null,
                'type' => $trailer['type'] ?? 'trailer',
                'url' => $trailer['url'],
                'video_key' => $trailer['video_key'] ?? null,
                'source' => $trailer['source'] ?? 'manual',
                'is_primary' => $trailer['is_primary'] ?? 0,
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    /**
     * Save movie artwork
     */
    private function saveMovieArtwork(int $movieId, array $artworkList): void
    {
        // Delete existing
        $this->db->delete('movie_artwork', 'movie_id = ?', [$movieId]);

        // Insert new
        foreach ($artworkList as $art) {
            $this->db->insert('movie_artwork', [
                'movie_id' => $movieId,
                'type' => $art['type'],
                'url' => $art['url'],
                'source' => $art['source'] ?? 'manual',
                'language' => $art['language'] ?? 'en',
                'is_primary' => $art['is_primary'] ?? 0,
            ]);
        }
    }

    /**
     * Save movie cast
     */
    private function saveMovieCast(int $movieId, array $castList): void
    {
        // Delete existing
        $this->db->delete('movie_cast', 'movie_id = ?', [$movieId]);

        // Insert new
        $sortOrder = 0;
        foreach ($castList as $person) {
            $this->db->insert('movie_cast', [
                'movie_id' => $movieId,
                'name' => $person['name'],
                'character_name' => $person['character_name'] ?? null,
                'role' => $person['role'] ?? 'actor',
                'profile_url' => $person['profile_url'] ?? null,
                'tmdb_person_id' => $person['tmdb_person_id'] ?? null,
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    /**
     * Generate unique slug
     */
    private function generateSlug(string $title, ?int $excludeId = null): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        $baseSlug = $slug;
        $counter = 1;

        while (true) {
            $sql = "SELECT id FROM movies WHERE slug = ?";
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

    /**
     * Check if movie exists by TMDB ID
     */
    public function findByTmdbId(int $tmdbId): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM movies WHERE tmdb_id = ?",
            [$tmdbId]
        );
    }

    /**
     * Import movie from TMDB metadata
     */
    public function importFromTmdb(array $tmdbData): int
    {
        // Map TMDB data to movie fields
        $movieData = [
            'tmdb_id' => $tmdbData['id'],
            'title' => $tmdbData['title'],
            'original_title' => $tmdbData['original_title'] ?? null,
            'tagline' => $tmdbData['tagline'] ?? null,
            'synopsis' => $tmdbData['overview'] ?? null,
            'year' => !empty($tmdbData['release_date']) ? (int) substr($tmdbData['release_date'], 0, 4) : null,
            'release_date' => $tmdbData['release_date'] ?? null,
            'runtime' => $tmdbData['runtime'] ?? null,
            'vote_average' => $tmdbData['vote_average'] ?? null,
            'genres' => $tmdbData['genres'] ?? [],
            'director' => !empty($tmdbData['directors']) ? implode(', ', $tmdbData['directors']) : null,
            'poster_url' => $tmdbData['poster'] ?? null,
            'backdrop_url' => $tmdbData['backdrop'] ?? null,
            'source' => 'tmdb',
            'status' => 'draft',
        ];

        // Create movie
        $movieId = $this->createMovie($movieData);

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
            $this->saveMovieCast($movieId, $cast);
        }

        // Auto-create categories from genres
        if (!empty($tmdbData['genres'])) {
            $categoryIds = [];
            foreach ($tmdbData['genres'] as $genreName) {
                $categoryIds[] = $this->getOrCreateCategory($genreName);
            }
            if (!empty($categoryIds)) {
                $this->saveMovieCategories($movieId, $categoryIds, $categoryIds[0]);
            }
        }

        return $movieId;
    }

    /**
     * Get movie statistics
     */
    public function getStatistics(): array
    {
        $stats = [];

        // Total movies
        $stats['total'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM movies"
        )['count'];

        // By status
        $stats['published'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM movies WHERE status = 'published'"
        )['count'];

        $stats['draft'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM movies WHERE status = 'draft'"
        )['count'];

        // Featured movies
        $stats['featured'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM movies WHERE is_featured = 1"
        )['count'];

        // Free content
        $stats['free'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM movies WHERE is_free = 1"
        )['count'];

        // By source
        $stats['from_tmdb'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM movies WHERE source = 'tmdb'"
        )['count'];

        $stats['from_youtube'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM movies WHERE source = 'youtube_cc'"
        )['count'];

        return $stats;
    }

    /**
     * Get distinct years from movies
     */
    public function getYears(): array
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT year FROM movies WHERE year IS NOT NULL ORDER BY year DESC"
        );
    }
}
