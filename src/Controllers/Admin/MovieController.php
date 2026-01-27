<?php
/**
 * CARI-IPTV Movie Management Controller
 */

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Database;
use CariIPTV\Core\Response;
use CariIPTV\Core\Session;
use CariIPTV\Services\AdminAuthService;
use CariIPTV\Services\MovieService;
use CariIPTV\Services\MetadataService;

class MovieController
{
    private Database $db;
    private AdminAuthService $auth;
    private MovieService $movieService;
    private MetadataService $metadataService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = new AdminAuthService();
        $this->movieService = new MovieService();
        $this->metadataService = new MetadataService();
    }

    /**
     * List all movies
     */
    public function index(): void
    {
        $filters = [
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? '',
            'year' => $_GET['year'] ?? '',
            'category_id' => $_GET['category_id'] ?? '',
            'featured' => $_GET['featured'] ?? '',
            'is_free' => $_GET['is_free'] ?? '',
            'source' => $_GET['source'] ?? '',
            'sort' => $_GET['sort'] ?? 'created_at',
            'dir' => $_GET['dir'] ?? 'DESC',
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? 25,
        ];

        $result = $this->movieService->getMovies($filters);
        $categories = $this->movieService->getCategories();
        $years = $this->movieService->getYears();
        $stats = $this->movieService->getStatistics();

        Response::view('admin/movies/index', [
            'pageTitle' => 'Movies',
            'movies' => $result['data'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total_pages' => $result['total_pages'],
            ],
            'filters' => $filters,
            'categories' => $categories,
            'years' => $years,
            'stats' => $stats,
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Show create movie form
     */
    public function create(): void
    {
        $categories = $this->movieService->getCategories();

        Response::view('admin/movies/form', [
            'pageTitle' => 'Add Movie',
            'movie' => null,
            'categories' => $categories,
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Store new movie
     */
    public function store(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/movies/create');
            return;
        }

        $data = $this->validateMovieData($_POST);

        if (!empty($data['errors'])) {
            Session::flash('error', implode(' ', $data['errors']));
            Response::redirect('/admin/movies/create');
            return;
        }

        unset($data['errors']);

        try {
            $movieId = $this->movieService->createMovie($data);

            // Process images (download and convert to WebP)
            $this->movieService->processImages($movieId, $data);

            // Log activity
            $this->auth->logActivity(
                $this->auth->id(),
                'create',
                'movies',
                'movie',
                $movieId,
                ['title' => $data['title']]
            );

            Session::flash('success', 'Movie created successfully.');
            Response::redirect('/admin/movies/' . $movieId . '/edit');
        } catch (\Exception $e) {
            Session::flash('error', 'Failed to create movie: ' . $e->getMessage());
            Response::redirect('/admin/movies/create');
        }
    }

    /**
     * Show edit movie form
     */
    public function edit(int $id): void
    {
        $movie = $this->movieService->getMovie($id);

        if (!$movie) {
            Session::flash('error', 'Movie not found.');
            Response::redirect('/admin/movies');
            return;
        }

        $categories = $this->movieService->getCategories();

        Response::view('admin/movies/form', [
            'pageTitle' => 'Edit Movie',
            'movie' => $movie,
            'categories' => $categories,
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Update movie
     */
    public function update(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/movies/' . $id . '/edit');
            return;
        }

        $movie = $this->movieService->getMovie($id);
        if (!$movie) {
            Session::flash('error', 'Movie not found.');
            Response::redirect('/admin/movies');
            return;
        }

        $data = $this->validateMovieData($_POST, $id);

        if (!empty($data['errors'])) {
            Session::flash('error', implode(' ', $data['errors']));
            Response::redirect('/admin/movies/' . $id . '/edit');
            return;
        }

        unset($data['errors']);

        // Extract new trailers (they should be added, not replace existing)
        $newTrailers = $data['trailers'] ?? [];
        unset($data['trailers']);

        try {
            $this->movieService->updateMovie($id, $data);

            // Process images if URLs changed (download and convert to WebP)
            $this->movieService->processImages($id, $data);

            // Add new trailers (don't replace existing ones)
            foreach ($newTrailers as $trailer) {
                $this->movieService->addTrailer($id, $trailer);
            }

            // Log activity
            $this->auth->logActivity(
                $this->auth->id(),
                'update',
                'movies',
                'movie',
                $id,
                ['title' => $data['title'] ?? $movie['title']]
            );

            Session::flash('success', 'Movie updated successfully.');
            Response::redirect('/admin/movies/' . $id . '/edit');
        } catch (\Exception $e) {
            Session::flash('error', 'Failed to update movie: ' . $e->getMessage());
            Response::redirect('/admin/movies/' . $id . '/edit');
        }
    }

    /**
     * Delete movie
     */
    public function delete(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/movies');
            return;
        }

        $movie = $this->movieService->getMovie($id);
        if (!$movie) {
            Session::flash('error', 'Movie not found.');
            Response::redirect('/admin/movies');
            return;
        }

        try {
            $this->movieService->deleteMovie($id);

            // Log activity
            $this->auth->logActivity(
                $this->auth->id(),
                'delete',
                'movies',
                'movie',
                $id,
                ['title' => $movie['title']]
            );

            Session::flash('success', 'Movie deleted successfully.');
        } catch (\Exception $e) {
            Session::flash('error', 'Failed to delete movie: ' . $e->getMessage());
        }

        Response::redirect('/admin/movies');
    }

    /**
     * Toggle featured status
     */
    public function toggleFeatured(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        if ($this->movieService->toggleFeatured($id)) {
            Response::json(['success' => true]);
        } else {
            Response::json(['success' => false, 'message' => 'Movie not found']);
        }
    }

    /**
     * Update movie status
     */
    public function updateStatus(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $status = $_POST['status'] ?? '';
        if ($this->movieService->updateStatus($id, $status)) {
            Response::json(['success' => true]);
        } else {
            Response::json(['success' => false, 'message' => 'Invalid status']);
        }
    }

    /**
     * Bulk actions
     */
    public function bulkAction(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/movies');
            return;
        }

        $action = $_POST['action'] ?? '';
        $ids = $_POST['ids'] ?? [];

        if (empty($ids)) {
            Session::flash('warning', 'No movies selected.');
            Response::redirect('/admin/movies');
            return;
        }

        $ids = array_map('intval', $ids);

        switch ($action) {
            case 'publish':
                $count = $this->movieService->bulkUpdateStatus($ids, 'published');
                Session::flash('success', "{$count} movie(s) published.");
                break;

            case 'draft':
                $count = $this->movieService->bulkUpdateStatus($ids, 'draft');
                Session::flash('success', "{$count} movie(s) set to draft.");
                break;

            case 'archive':
                $count = $this->movieService->bulkUpdateStatus($ids, 'archived');
                Session::flash('success', "{$count} movie(s) archived.");
                break;

            case 'delete':
                $count = 0;
                foreach ($ids as $id) {
                    if ($this->movieService->deleteMovie($id)) {
                        $count++;
                    }
                }
                Session::flash('success', "{$count} movie(s) deleted.");
                break;

            default:
                Session::flash('error', 'Invalid action.');
        }

        Response::redirect('/admin/movies');
    }

    /**
     * Search TMDB for movies
     */
    public function searchTmdb(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $query = trim($_POST['query'] ?? '');
        $year = !empty($_POST['year']) ? (int) $_POST['year'] : null;

        if (empty($query)) {
            Response::json(['success' => false, 'message' => 'Search query required']);
            return;
        }

        $results = $this->metadataService->searchMovies($query, $year);

        if (isset($results['error'])) {
            Response::json(['success' => false, 'message' => $results['error']]);
            return;
        }

        Response::json(['success' => true, 'results' => $results['results'] ?? []]);
    }

    /**
     * Get TMDB movie details
     */
    public function getTmdbDetails(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $tmdbId = (int) ($_POST['tmdb_id'] ?? 0);

        if (!$tmdbId) {
            Response::json(['success' => false, 'message' => 'TMDB ID required']);
            return;
        }

        $details = $this->metadataService->getMovieDetails($tmdbId);

        if (!$details) {
            Response::json(['success' => false, 'message' => 'Movie not found']);
            return;
        }

        // Get trailers from TMDB
        $videos = $this->metadataService->getMovieVideos($tmdbId);

        Response::json([
            'success' => true,
            'movie' => $details,
            'trailers' => $videos,
        ]);
    }

    /**
     * Search Fanart.tv for movie artwork
     */
    public function searchFanart(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $tmdbId = (int) ($_POST['tmdb_id'] ?? 0);

        if (!$tmdbId) {
            Response::json(['success' => false, 'message' => 'TMDB ID required']);
            return;
        }

        $artwork = $this->metadataService->getMovieArtwork($tmdbId);

        Response::json([
            'success' => true,
            'artwork' => $artwork,
        ]);
    }

    /**
     * Search YouTube for trailers
     */
    public function searchTrailers(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $title = trim($_POST['title'] ?? '');
        $year = !empty($_POST['year']) ? (int) $_POST['year'] : null;

        if (empty($title)) {
            Response::json(['success' => false, 'message' => 'Movie title required']);
            return;
        }

        $results = $this->metadataService->searchYoutubeTrailers($title, $year);

        if (isset($results['error'])) {
            Response::json(['success' => false, 'message' => $results['error']]);
            return;
        }

        Response::json(['success' => true, 'results' => $results['results'] ?? []]);
    }

    /**
     * Add trailer to movie
     */
    public function addTrailer(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $trailer = [
            'name' => trim($_POST['name'] ?? ''),
            'type' => $_POST['type'] ?? 'trailer',
            'url' => trim($_POST['url'] ?? ''),
            'video_key' => $_POST['video_key'] ?? null,
            'source' => $_POST['source'] ?? 'manual',
            'is_primary' => (int) ($_POST['is_primary'] ?? 0),
        ];

        if (empty($trailer['url'])) {
            Response::json(['success' => false, 'message' => 'Trailer URL required']);
            return;
        }

        try {
            $trailerId = $this->movieService->addTrailer($id, $trailer);
            Response::json(['success' => true, 'trailer_id' => $trailerId]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Remove trailer from movie
     */
    public function removeTrailer(int $id, int $trailerId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        if ($this->movieService->removeTrailer($trailerId)) {
            Response::json(['success' => true]);
        } else {
            Response::json(['success' => false, 'message' => 'Trailer not found']);
        }
    }

    /**
     * Import movie from TMDB
     */
    public function importFromTmdb(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $tmdbId = (int) ($_POST['tmdb_id'] ?? 0);

        if (!$tmdbId) {
            Response::json(['success' => false, 'message' => 'TMDB ID required']);
            return;
        }

        // Check if already imported
        $existing = $this->movieService->findByTmdbId($tmdbId);
        if ($existing) {
            Response::json([
                'success' => false,
                'message' => 'Movie already exists',
                'movie_id' => $existing['id'],
            ]);
            return;
        }

        // Get TMDB details
        $details = $this->metadataService->getMovieDetails($tmdbId);
        if (!$details) {
            Response::json(['success' => false, 'message' => 'Failed to fetch movie details']);
            return;
        }

        try {
            $movieId = $this->movieService->importFromTmdb($details);

            // Process images (download and convert to WebP)
            $this->movieService->processImages($movieId, [
                'poster_url' => $details['poster'] ?? null,
                'backdrop_url' => $details['backdrop'] ?? null,
            ]);

            // Also import trailers from TMDB
            $videos = $this->metadataService->getMovieVideos($tmdbId);
            foreach ($videos as $video) {
                if (in_array($video['type'], ['Trailer', 'Teaser'])) {
                    $this->movieService->addTrailer($movieId, [
                        'name' => $video['name'],
                        'type' => strtolower($video['type']),
                        'url' => $video['url'],
                        'video_key' => $video['key'],
                        'source' => 'tmdb',
                        'is_primary' => $video['type'] === 'Trailer' ? 1 : 0,
                    ]);
                }
            }

            // Log activity
            $this->auth->logActivity(
                $this->auth->id(),
                'import',
                'movies',
                'movie',
                $movieId,
                ['title' => $details['title'], 'source' => 'tmdb']
            );

            Response::json([
                'success' => true,
                'message' => 'Movie imported successfully',
                'movie_id' => $movieId,
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Browse free content page
     */
    public function browseFreeContent(): void
    {
        Response::view('admin/movies/browse-free', [
            'pageTitle' => 'Browse Free Content',
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Search YouTube Creative Commons content
     */
    public function searchFreeContent(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $query = trim($_POST['query'] ?? '');
        $type = $_POST['type'] ?? 'movie';

        if (empty($query)) {
            Response::json(['success' => false, 'message' => 'Search query required']);
            return;
        }

        $results = $this->metadataService->searchYoutubeCreativeCommons($query, $type);

        if (isset($results['error'])) {
            Response::json(['success' => false, 'message' => $results['error']]);
            return;
        }

        Response::json(['success' => true, 'results' => $results['results'] ?? []]);
    }

    /**
     * Import free content from YouTube
     */
    public function importFreeContent(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $videoId = trim($_POST['video_id'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $thumbnail = trim($_POST['thumbnail'] ?? '');
        $duration = (int) ($_POST['duration'] ?? 0);

        if (empty($videoId) || empty($title)) {
            Response::json(['success' => false, 'message' => 'Video ID and title required']);
            return;
        }

        try {
            $movieData = [
                'title' => $title,
                'synopsis' => $description,
                'poster_url' => $thumbnail,
                'stream_url' => 'https://www.youtube.com/watch?v=' . $videoId,
                'runtime' => $duration > 0 ? round($duration / 60) : null,
                'source' => 'youtube_cc',
                'source_url' => 'https://www.youtube.com/watch?v=' . $videoId,
                'is_free' => 1,
                'status' => 'draft',
            ];

            $movieId = $this->movieService->createMovie($movieData);

            // Process thumbnail (download and convert to WebP)
            if (!empty($thumbnail)) {
                $this->movieService->processImages($movieId, [
                    'poster_url' => $thumbnail,
                ]);
            }

            // Log activity
            $this->auth->logActivity(
                $this->auth->id(),
                'import',
                'movies',
                'movie',
                $movieId,
                ['title' => $title, 'source' => 'youtube_cc']
            );

            Response::json([
                'success' => true,
                'message' => 'Free content imported successfully',
                'movie_id' => $movieId,
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Validate movie form data
     */
    private function validateMovieData(array $post, ?int $excludeId = null): array
    {
        $data = [];
        $errors = [];

        // Title (required)
        $title = trim($post['title'] ?? '');
        if (empty($title)) {
            $errors[] = 'Title is required.';
        } elseif (strlen($title) > 255) {
            $errors[] = 'Title cannot exceed 255 characters.';
        } else {
            $data['title'] = $title;
        }

        // Optional fields
        if (isset($post['original_title'])) {
            $data['original_title'] = trim($post['original_title']) ?: null;
        }

        if (isset($post['tagline'])) {
            $data['tagline'] = trim($post['tagline']) ?: null;
        }

        if (isset($post['synopsis'])) {
            $data['synopsis'] = trim($post['synopsis']) ?: null;
        }

        if (isset($post['year']) && $post['year'] !== '') {
            $year = (int) $post['year'];
            if ($year >= 1888 && $year <= date('Y') + 5) {
                $data['year'] = $year;
            }
        }

        if (isset($post['release_date']) && $post['release_date'] !== '') {
            $data['release_date'] = $post['release_date'];
        }

        if (isset($post['rating'])) {
            $data['rating'] = trim($post['rating']) ?: null;
        }

        if (isset($post['runtime']) && $post['runtime'] !== '') {
            $data['runtime'] = (int) $post['runtime'];
        }

        if (isset($post['director'])) {
            $data['director'] = trim($post['director']) ?: null;
        }

        if (isset($post['writers'])) {
            $data['writers'] = trim($post['writers']) ?: null;
        }

        if (isset($post['language'])) {
            $data['language'] = trim($post['language']) ?: 'en';
        }

        if (isset($post['country'])) {
            $data['country'] = trim($post['country']) ?: null;
        }

        // Genres (JSON array)
        if (isset($post['genres'])) {
            if (is_array($post['genres'])) {
                $data['genres'] = $post['genres'];
            } elseif (is_string($post['genres']) && !empty($post['genres'])) {
                $data['genres'] = array_map('trim', explode(',', $post['genres']));
            }
        }

        // Stream URLs
        if (isset($post['stream_url'])) {
            $data['stream_url'] = trim($post['stream_url']) ?: null;
        }

        if (isset($post['stream_url_backup'])) {
            $data['stream_url_backup'] = trim($post['stream_url_backup']) ?: null;
        }

        // Image URLs
        if (isset($post['poster_url'])) {
            $data['poster_url'] = trim($post['poster_url']) ?: null;
        }

        if (isset($post['backdrop_url'])) {
            $data['backdrop_url'] = trim($post['backdrop_url']) ?: null;
        }

        if (isset($post['logo_url'])) {
            $data['logo_url'] = trim($post['logo_url']) ?: null;
        }

        // Categories
        if (isset($post['categories'])) {
            $data['categories'] = is_array($post['categories']) ? $post['categories'] : [];
        }

        if (isset($post['primary_category'])) {
            $data['primary_category'] = (int) $post['primary_category'] ?: null;
        }

        // Status
        if (isset($post['status'])) {
            $allowedStatuses = ['draft', 'published', 'archived'];
            if (in_array($post['status'], $allowedStatuses)) {
                $data['status'] = $post['status'];
            }
        }

        // Flags
        $data['is_featured'] = isset($post['is_featured']) ? 1 : 0;
        $data['is_free'] = isset($post['is_free']) ? 1 : 0;

        // TMDB ID
        if (isset($post['tmdb_id']) && $post['tmdb_id'] !== '') {
            $data['tmdb_id'] = (int) $post['tmdb_id'];
        }

        // Process new trailers from form
        if (!empty($post['trailers_new'])) {
            $trailers = [];
            foreach ($post['trailers_new'] as $trailerJson) {
                $trailer = json_decode($trailerJson, true);
                if ($trailer && !empty($trailer['url'])) {
                    $trailers[] = $trailer;
                }
            }
            if (!empty($trailers)) {
                $data['trailers'] = $trailers;
            }
        }

        $data['errors'] = $errors;
        return $data;
    }
}
