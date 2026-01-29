<?php
/**
 * CARI-IPTV TV Shows (Series) Management Controller
 */

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Database;
use CariIPTV\Core\Response;
use CariIPTV\Core\Session;
use CariIPTV\Services\AdminAuthService;
use CariIPTV\Services\SeriesService;
use CariIPTV\Services\MetadataService;

class SeriesController
{
    private Database $db;
    private AdminAuthService $auth;
    private SeriesService $seriesService;
    private MetadataService $metadataService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = new AdminAuthService();
        $this->seriesService = new SeriesService();
        $this->metadataService = new MetadataService();
    }

    // ========================================================================
    // TV SHOW LIST & CRUD
    // ========================================================================

    /**
     * List all TV shows
     */
    public function index(): void
    {
        $filters = [
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? '',
            'year' => $_GET['year'] ?? '',
            'category_id' => $_GET['category_id'] ?? '',
            'featured' => $_GET['featured'] ?? '',
            'source' => $_GET['source'] ?? '',
            'sort' => $_GET['sort'] ?? 'created_at',
            'dir' => $_GET['dir'] ?? 'DESC',
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? 25,
        ];

        $result = $this->seriesService->getSeries($filters);
        $categories = $this->seriesService->getCategories();
        $years = $this->seriesService->getYears();
        $stats = $this->seriesService->getStatistics();

        Response::view('admin/series/index', [
            'pageTitle' => 'TV Shows',
            'shows' => $result['data'],
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
     * Show create TV show form
     */
    public function create(): void
    {
        $categories = $this->seriesService->getCategories();

        Response::view('admin/series/form', [
            'pageTitle' => 'Add TV Show',
            'show' => null,
            'categories' => $categories,
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Store new TV show
     */
    public function store(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/series/create');
            return;
        }

        $data = $this->validateShowData($_POST);

        if (!empty($data['errors'])) {
            Session::flash('error', implode(' ', $data['errors']));
            Response::redirect('/admin/series/create');
            return;
        }

        unset($data['errors']);

        try {
            $showId = $this->seriesService->createShow($data);

            $this->seriesService->processImages($showId, $data);

            $this->auth->logActivity(
                $this->auth->id(),
                'create',
                'series',
                'series',
                $showId,
                ['title' => $data['title']]
            );

            Session::flash('success', 'TV show created successfully.');
            Response::redirect('/admin/series/' . $showId . '/edit');
        } catch (\Exception $e) {
            Session::flash('error', 'Failed to create TV show: ' . $e->getMessage());
            Response::redirect('/admin/series/create');
        }
    }

    /**
     * Show edit TV show form
     */
    public function edit(int $id): void
    {
        $show = $this->seriesService->getShow($id);

        if (!$show) {
            Session::flash('error', 'TV show not found.');
            Response::redirect('/admin/series');
            return;
        }

        $categories = $this->seriesService->getCategories();

        Response::view('admin/series/form', [
            'pageTitle' => 'Edit TV Show',
            'show' => $show,
            'categories' => $categories,
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Update TV show
     */
    public function update(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/series/' . $id . '/edit');
            return;
        }

        $show = $this->seriesService->getShow($id);
        if (!$show) {
            Session::flash('error', 'TV show not found.');
            Response::redirect('/admin/series');
            return;
        }

        $data = $this->validateShowData($_POST, $id);

        if (!empty($data['errors'])) {
            Session::flash('error', implode(' ', $data['errors']));
            Response::redirect('/admin/series/' . $id . '/edit');
            return;
        }

        unset($data['errors']);

        try {
            $this->seriesService->updateShow($id, $data);
            $this->seriesService->processImages($id, $data);

            $this->auth->logActivity(
                $this->auth->id(),
                'update',
                'series',
                'series',
                $id,
                ['title' => $data['title'] ?? $show['title']]
            );

            Session::flash('success', 'TV show updated successfully.');
            Response::redirect('/admin/series/' . $id . '/edit');
        } catch (\Exception $e) {
            Session::flash('error', 'Failed to update TV show: ' . $e->getMessage());
            Response::redirect('/admin/series/' . $id . '/edit');
        }
    }

    /**
     * Delete TV show
     */
    public function delete(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/series');
            return;
        }

        $show = $this->seriesService->getShow($id);
        if (!$show) {
            Session::flash('error', 'TV show not found.');
            Response::redirect('/admin/series');
            return;
        }

        try {
            $this->seriesService->deleteShow($id);

            $this->auth->logActivity(
                $this->auth->id(),
                'delete',
                'series',
                'series',
                $id,
                ['title' => $show['title']]
            );

            Session::flash('success', 'TV show deleted successfully.');
        } catch (\Exception $e) {
            Session::flash('error', 'Failed to delete TV show: ' . $e->getMessage());
        }

        Response::redirect('/admin/series');
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

        if ($this->seriesService->toggleFeatured($id)) {
            Response::json(['success' => true]);
        } else {
            Response::json(['success' => false, 'message' => 'TV show not found']);
        }
    }

    /**
     * Update show status
     */
    public function updateStatus(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $status = $_POST['status'] ?? '';
        if ($this->seriesService->updateStatus($id, $status)) {
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
            Response::redirect('/admin/series');
            return;
        }

        $action = $_POST['action'] ?? '';
        $ids = $_POST['ids'] ?? [];

        if (empty($ids)) {
            Session::flash('warning', 'No TV shows selected.');
            Response::redirect('/admin/series');
            return;
        }

        $ids = array_map('intval', $ids);

        switch ($action) {
            case 'publish':
                $count = $this->seriesService->bulkUpdateStatus($ids, 'published');
                Session::flash('success', "{$count} TV show(s) published.");
                break;

            case 'draft':
                $count = $this->seriesService->bulkUpdateStatus($ids, 'draft');
                Session::flash('success', "{$count} TV show(s) set to draft.");
                break;

            case 'archive':
                $count = $this->seriesService->bulkUpdateStatus($ids, 'archived');
                Session::flash('success', "{$count} TV show(s) archived.");
                break;

            case 'delete':
                $count = 0;
                foreach ($ids as $id) {
                    if ($this->seriesService->deleteShow($id)) {
                        $count++;
                    }
                }
                Session::flash('success', "{$count} TV show(s) deleted.");
                break;

            default:
                Session::flash('error', 'Invalid action.');
        }

        Response::redirect('/admin/series');
    }

    // ========================================================================
    // SEASONS MANAGEMENT
    // ========================================================================

    /**
     * View seasons for a TV show
     */
    public function seasons(int $id): void
    {
        $show = $this->seriesService->getShow($id);
        if (!$show) {
            Session::flash('error', 'TV show not found.');
            Response::redirect('/admin/series');
            return;
        }

        Response::view('admin/series/seasons', [
            'pageTitle' => 'Seasons - ' . $show['title'],
            'show' => $show,
            'seasons' => $show['seasons'],
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Add a season (manual)
     */
    public function addSeason(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $seasonNumber = (int) ($_POST['season_number'] ?? 1);
        $name = trim($_POST['name'] ?? 'Season ' . $seasonNumber);
        $overview = trim($_POST['overview'] ?? '');
        $posterUrl = trim($_POST['poster_url'] ?? '');

        try {
            $seasonId = $this->seriesService->createSeason($id, [
                'season_number' => $seasonNumber,
                'name' => $name,
                'overview' => $overview ?: null,
                'poster_url' => $posterUrl ?: null,
            ]);

            $this->sendJson(['success' => true, 'season_id' => $seasonId, 'message' => 'Season added successfully']);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Update a season
     */
    public function updateSeason(int $id, int $seasonId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $data = [];
        if (isset($_POST['name'])) $data['name'] = trim($_POST['name']);
        if (isset($_POST['overview'])) $data['overview'] = trim($_POST['overview']) ?: null;
        if (isset($_POST['poster_url'])) $data['poster_url'] = trim($_POST['poster_url']) ?: null;

        try {
            $this->seriesService->updateSeason($seasonId, $data);
            $this->sendJson(['success' => true, 'message' => 'Season updated']);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Delete a season
     */
    public function deleteSeason(int $id, int $seasonId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        if ($this->seriesService->deleteSeason($seasonId)) {
            $this->sendJson(['success' => true, 'message' => 'Season deleted']);
        } else {
            $this->sendJson(['success' => false, 'message' => 'Season not found']);
        }
    }

    /**
     * Import seasons from TMDB
     */
    public function importSeasons(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $show = $this->seriesService->getShow($id);
        if (!$show || empty($show['tmdb_id'])) {
            $this->sendJson(['success' => false, 'message' => 'Show not found or no TMDB ID']);
            return;
        }

        $seasonNumber = (int) ($_POST['season_number'] ?? 0);

        try {
            $tmdbId = (int) $show['tmdb_id'];

            if ($seasonNumber > 0) {
                // Import single season
                $seasonDetails = $this->metadataService->getTVSeasonDetails($tmdbId, $seasonNumber);
                if (!$seasonDetails) {
                    $this->sendJson(['success' => false, 'message' => 'Season not found on TMDB']);
                    return;
                }

                $seasonId = $this->seriesService->importSeasonsFromTmdb($id, $tmdbId, $seasonDetails);

                $this->sendJson([
                    'success' => true,
                    'message' => "Season {$seasonNumber} imported with " . count($seasonDetails['episodes'] ?? []) . " episodes",
                    'season_id' => $seasonId,
                ]);
            } else {
                // Import all seasons
                $details = $this->metadataService->getTVShowDetails($tmdbId);
                if (!$details) {
                    $this->sendJson(['success' => false, 'message' => 'Failed to fetch show details']);
                    return;
                }

                $importedCount = 0;
                $totalSeasons = $details['number_of_seasons'] ?? 0;

                for ($s = 1; $s <= $totalSeasons; $s++) {
                    // Check if season already exists
                    $existing = $this->db->fetch(
                        "SELECT id FROM series_seasons WHERE series_id = ? AND season_number = ?",
                        [$id, $s]
                    );
                    if ($existing) continue;

                    $seasonDetails = $this->metadataService->getTVSeasonDetails($tmdbId, $s);
                    if ($seasonDetails) {
                        $this->seriesService->importSeasonsFromTmdb($id, $tmdbId, $seasonDetails);
                        $importedCount++;
                    }
                }

                $this->auth->logActivity(
                    $this->auth->id(),
                    'import',
                    'series',
                    'seasons',
                    $id,
                    ['title' => $show['title'], 'seasons_imported' => $importedCount]
                );

                $this->sendJson([
                    'success' => true,
                    'message' => "{$importedCount} season(s) imported from TMDB",
                ]);
            }
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ========================================================================
    // EPISODES MANAGEMENT
    // ========================================================================

    /**
     * View episodes for a season
     */
    public function episodes(int $id, int $seasonId): void
    {
        $show = $this->seriesService->getShow($id);
        if (!$show) {
            Session::flash('error', 'TV show not found.');
            Response::redirect('/admin/series');
            return;
        }

        $season = $this->seriesService->getSeason($seasonId);
        if (!$season) {
            Session::flash('error', 'Season not found.');
            Response::redirect('/admin/series/' . $id . '/seasons');
            return;
        }

        Response::view('admin/series/episodes', [
            'pageTitle' => $season['name'] . ' - ' . $show['title'],
            'show' => $show,
            'season' => $season,
            'episodes' => $season['episodes'],
            'trailers' => $season['trailers'],
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Add an episode
     */
    public function addEpisode(int $id, int $seasonId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $data = [
            'episode_number' => (int) ($_POST['episode_number'] ?? 1),
            'name' => trim($_POST['name'] ?? ''),
            'overview' => trim($_POST['overview'] ?? '') ?: null,
            'air_date' => !empty($_POST['air_date']) ? $_POST['air_date'] : null,
            'runtime' => !empty($_POST['runtime']) ? (int) $_POST['runtime'] : null,
            'still_url' => trim($_POST['still_url'] ?? '') ?: null,
            'stream_url' => trim($_POST['stream_url'] ?? '') ?: null,
            'stream_url_backup' => trim($_POST['stream_url_backup'] ?? '') ?: null,
        ];

        try {
            $episodeId = $this->seriesService->createEpisode($id, $seasonId, $data);
            $this->sendJson(['success' => true, 'episode_id' => $episodeId, 'message' => 'Episode added']);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Update an episode
     */
    public function updateEpisode(int $id, int $seasonId, int $episodeId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $data = [];
        if (isset($_POST['episode_number'])) $data['episode_number'] = (int) $_POST['episode_number'];
        if (isset($_POST['name'])) $data['name'] = trim($_POST['name']);
        if (isset($_POST['overview'])) $data['overview'] = trim($_POST['overview']) ?: null;
        if (isset($_POST['air_date'])) $data['air_date'] = !empty($_POST['air_date']) ? $_POST['air_date'] : null;
        if (isset($_POST['runtime'])) $data['runtime'] = !empty($_POST['runtime']) ? (int) $_POST['runtime'] : null;
        if (isset($_POST['still_url'])) $data['still_url'] = trim($_POST['still_url']) ?: null;
        if (isset($_POST['stream_url'])) $data['stream_url'] = trim($_POST['stream_url']) ?: null;
        if (isset($_POST['stream_url_backup'])) $data['stream_url_backup'] = trim($_POST['stream_url_backup']) ?: null;

        try {
            $this->seriesService->updateEpisode($episodeId, $data);
            $this->sendJson(['success' => true, 'message' => 'Episode updated']);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Delete an episode
     */
    public function deleteEpisode(int $id, int $seasonId, int $episodeId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        if ($this->seriesService->deleteEpisode($episodeId)) {
            $this->sendJson(['success' => true, 'message' => 'Episode deleted']);
        } else {
            $this->sendJson(['success' => false, 'message' => 'Episode not found']);
        }
    }

    // ========================================================================
    // TRAILERS (per season)
    // ========================================================================

    /**
     * Add trailer to a season
     */
    public function addTrailer(int $id, int $seasonId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
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
            $this->sendJson(['success' => false, 'message' => 'Trailer URL required']);
            return;
        }

        try {
            $trailerId = $this->seriesService->addTrailer($id, $seasonId, $trailer);
            $this->sendJson(['success' => true, 'trailer_id' => $trailerId]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Remove trailer
     */
    public function removeTrailer(int $id, int $seasonId, int $trailerId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        if ($this->seriesService->removeTrailer($trailerId)) {
            $this->sendJson(['success' => true]);
        } else {
            $this->sendJson(['success' => false, 'message' => 'Trailer not found']);
        }
    }

    // ========================================================================
    // METADATA SEARCH (TMDB, Fanart.tv, YouTube)
    // ========================================================================

    /**
     * Search TMDB for TV shows
     */
    public function searchTmdb(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $query = trim($_POST['query'] ?? '');
        $year = !empty($_POST['year']) ? (int) $_POST['year'] : null;

        if (empty($query)) {
            $this->sendJson(['success' => false, 'message' => 'Search query required']);
            return;
        }

        $results = $this->metadataService->searchTVShows($query, $year);

        if (isset($results['error'])) {
            $this->sendJson(['success' => false, 'message' => $results['error']]);
            return;
        }

        $this->sendJson(['success' => true, 'results' => $results['results'] ?? []]);
    }

    /**
     * Get TMDB TV show details
     */
    public function getTmdbDetails(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $tmdbId = (int) ($_POST['tmdb_id'] ?? 0);

        if (!$tmdbId) {
            $this->sendJson(['success' => false, 'message' => 'TMDB ID required']);
            return;
        }

        $details = $this->metadataService->getTVShowDetails($tmdbId);

        if (!$details) {
            $this->sendJson(['success' => false, 'message' => 'TV show not found']);
            return;
        }

        $videos = $this->metadataService->getTVShowVideos($tmdbId);

        $this->sendJson([
            'success' => true,
            'show' => $details,
            'trailers' => $videos,
        ]);
    }

    /**
     * Search Fanart.tv for TV show artwork
     */
    public function searchFanart(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $tmdbId = (int) ($_POST['tmdb_id'] ?? 0);

        if (!$tmdbId) {
            $this->sendJson(['success' => false, 'message' => 'TMDB ID required']);
            return;
        }

        $artwork = $this->metadataService->getTVShowArtwork($tmdbId);

        $this->sendJson([
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
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $title = trim($_POST['title'] ?? '');
        $year = !empty($_POST['year']) ? (int) $_POST['year'] : null;

        if (empty($title)) {
            $this->sendJson(['success' => false, 'message' => 'Title required']);
            return;
        }

        $results = $this->metadataService->searchYoutubeTrailers($title, $year);

        if (isset($results['error'])) {
            $this->sendJson(['success' => false, 'message' => $results['error']]);
            return;
        }

        $this->sendJson(['success' => true, 'results' => $results['results'] ?? []]);
    }

    /**
     * Import TV show from TMDB
     */
    public function importFromTmdb(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $tmdbId = (int) ($_POST['tmdb_id'] ?? 0);

        if (!$tmdbId) {
            $this->sendJson(['success' => false, 'message' => 'TMDB ID required']);
            return;
        }

        $existing = $this->seriesService->findByTmdbId($tmdbId);
        if ($existing) {
            $this->sendJson([
                'success' => false,
                'message' => 'TV show already exists',
                'show_id' => $existing['id'],
            ]);
            return;
        }

        $details = $this->metadataService->getTVShowDetails($tmdbId);
        if (!$details) {
            $this->sendJson(['success' => false, 'message' => 'Failed to fetch TV show details']);
            return;
        }

        try {
            $showId = $this->seriesService->importFromTmdb($details);

            $this->seriesService->processImages($showId, [
                'poster_url' => $details['poster'] ?? null,
                'backdrop_url' => $details['backdrop'] ?? null,
            ]);

            // Import trailers from TMDB
            $videos = $this->metadataService->getTVShowVideos($tmdbId);
            foreach ($videos as $video) {
                if (in_array($video['type'], ['Trailer', 'Teaser'])) {
                    $this->seriesService->addTrailer($showId, null, [
                        'name' => $video['name'],
                        'type' => strtolower($video['type']),
                        'url' => $video['url'],
                        'video_key' => $video['key'],
                        'source' => 'tmdb',
                        'is_primary' => $video['type'] === 'Trailer' ? 1 : 0,
                    ]);
                }
            }

            $this->auth->logActivity(
                $this->auth->id(),
                'import',
                'series',
                'series',
                $showId,
                ['title' => $details['name'], 'source' => 'tmdb']
            );

            $this->sendJson([
                'success' => true,
                'message' => 'TV show imported successfully',
                'show_id' => $showId,
            ]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Process images for a show
     */
    public function processShowImages(int $id): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $show = $this->seriesService->getShow($id);
        if (!$show) {
            $this->sendJson(['success' => false, 'message' => 'TV show not found']);
            return;
        }

        try {
            $processed = [];

            if (!empty($show['poster_url']) && str_starts_with($show['poster_url'], 'http')) {
                $processed = $this->seriesService->processImages($id, [
                    'poster_url' => $show['poster_url'],
                ]);
            }

            $this->sendJson([
                'success' => true,
                'message' => 'Images processed',
                'processed' => $processed,
            ]);
        } catch (\Throwable $e) {
            $this->sendJson([
                'success' => false,
                'message' => 'Image processing failed: ' . $e->getMessage(),
            ]);
        }
    }

    // ========================================================================
    // PRIVATE HELPERS
    // ========================================================================

    /**
     * Send clean JSON response
     */
    private function sendJson(array $data): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Validate TV show form data
     */
    private function validateShowData(array $post, ?int $excludeId = null): array
    {
        $data = [];
        $errors = [];

        $title = trim($post['title'] ?? '');
        if (empty($title)) {
            $errors[] = 'Title is required.';
        } elseif (strlen($title) > 255) {
            $errors[] = 'Title cannot exceed 255 characters.';
        } else {
            $data['title'] = $title;
        }

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
            if ($year >= 1928 && $year <= date('Y') + 5) {
                $data['year'] = $year;
            }
        }

        if (isset($post['first_air_date']) && $post['first_air_date'] !== '') {
            $data['first_air_date'] = $post['first_air_date'];
        }

        if (isset($post['rating'])) {
            $data['rating'] = trim($post['rating']) ?: null;
        }

        if (isset($post['episode_run_time']) && $post['episode_run_time'] !== '') {
            $data['episode_run_time'] = (int) $post['episode_run_time'];
        }

        if (isset($post['show_status'])) {
            $data['show_status'] = trim($post['show_status']) ?: null;
        }

        if (isset($post['creators'])) {
            $data['creators'] = trim($post['creators']) ?: null;
        }

        if (isset($post['networks'])) {
            $data['networks'] = trim($post['networks']) ?: null;
        }

        if (isset($post['language'])) {
            $data['language'] = trim($post['language']) ?: 'en';
        }

        if (isset($post['country'])) {
            $data['country'] = trim($post['country']) ?: null;
        }

        if (isset($post['genres'])) {
            if (is_array($post['genres'])) {
                $data['genres'] = $post['genres'];
            } elseif (is_string($post['genres']) && !empty($post['genres'])) {
                $data['genres'] = array_map('trim', explode(',', $post['genres']));
            }
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

        $data['is_featured'] = isset($post['is_featured']) ? 1 : 0;

        // TMDB ID
        if (isset($post['tmdb_id']) && $post['tmdb_id'] !== '') {
            $data['tmdb_id'] = (int) $post['tmdb_id'];
        }

        $data['errors'] = $errors;
        return $data;
    }
}
