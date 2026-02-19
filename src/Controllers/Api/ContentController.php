<?php
/**
 * CARI-IPTV Content API Controller
 * Public endpoints for channels, movies, series, categories, search
 */

namespace CariIPTV\Controllers\Api;

use CariIPTV\Services\ContentApiService;
use CariIPTV\Services\VodServerService;

class ContentController extends BaseApiController
{
    private ContentApiService $service;

    public function __construct()
    {
        $this->service = new ContentApiService();
    }

    // =========================================================================
    // MANIFEST
    // =========================================================================

    /**
     * GET /api/v1/manifest
     * Returns version hashes for all content types.
     * Player compares these with cached versions to determine what to refresh.
     */
    public function manifest(): void
    {
        $platform = $this->query('platform');
        $manifest = $this->service->getManifest($platform);

        // Short cache - players should check manifest frequently
        header('Cache-Control: public, max-age=30, stale-while-revalidate=60');

        $this->ok($manifest, [
            'api_version' => '1.0',
        ]);
    }

    // =========================================================================
    // CHANNELS
    // =========================================================================

    /**
     * GET /api/v1/channels
     * ?category_id=5&since=2025-01-01T00:00:00Z&limit=500&offset=0
     */
    public function channels(): void
    {
        $filters = $this->queryFilters([
            'category_id' => null,
            'since' => null,
            'limit' => 500,
            'offset' => 0,
        ]);

        $result = $this->service->getChannels($filters);
        $version = $this->service->getContentVersion('channels');

        $this->ok($result['items'], [
            'version' => $version,
            'total' => $result['total'],
            'limit' => $result['limit'],
            'offset' => $result['offset'],
        ]);
    }

    /**
     * GET /api/v1/channels/{id}
     */
    public function channel(string $id): void
    {
        $channel = $this->service->getChannel((int)$id);

        if (!$channel) {
            $this->notFound('Channel not found');
        }

        $this->ok($channel, [
            'version' => md5($channel['updated_at'] ?? ''),
        ]);
    }

    // =========================================================================
    // MOVIES
    // =========================================================================

    /**
     * GET /api/v1/movies
     * ?category_id=3&featured=1&genre=Action&year=2024&sort=latest&since=...&limit=50&offset=0
     */
    public function movies(): void
    {
        $filters = $this->queryFilters([
            'category_id' => null,
            'featured' => null,
            'genre' => null,
            'year' => null,
            'sort' => 'latest',
            'since' => null,
            'limit' => 50,
            'offset' => 0,
        ]);

        $result = $this->service->getMovies($filters);
        $version = $this->service->getContentVersion('movies');

        $this->ok($result['items'], [
            'version' => $version,
            'total' => $result['total'],
            'limit' => $result['limit'],
            'offset' => $result['offset'],
        ]);
    }

    /**
     * GET /api/v1/movies/featured
     */
    public function moviesFeatured(): void
    {
        $result = $this->service->getMovies(['featured' => true, 'limit' => 20]);

        $this->ok($result['items'], [
            'total' => $result['total'],
        ]);
    }

    /**
     * GET /api/v1/movies/{id}
     */
    public function movie(string $id): void
    {
        $movie = $this->service->getMovie((int)$id);

        if (!$movie) {
            $this->notFound('Movie not found');
        }

        $this->ok($movie, [
            'version' => md5($movie['updated_at'] ?? ''),
        ]);
    }

    // =========================================================================
    // SERIES
    // =========================================================================

    /**
     * GET /api/v1/series
     * ?category_id=3&featured=1&genre=Drama&sort=latest&since=...&limit=50&offset=0
     */
    public function seriesList(): void
    {
        $filters = $this->queryFilters([
            'category_id' => null,
            'featured' => null,
            'genre' => null,
            'sort' => 'latest',
            'since' => null,
            'limit' => 50,
            'offset' => 0,
        ]);

        $result = $this->service->getSeries($filters);
        $version = $this->service->getContentVersion('series');

        $this->ok($result['items'], [
            'version' => $version,
            'total' => $result['total'],
            'limit' => $result['limit'],
            'offset' => $result['offset'],
        ]);
    }

    /**
     * GET /api/v1/series/{id}
     */
    public function seriesDetail(string $id): void
    {
        $show = $this->service->getSeriesDetail((int)$id);

        if (!$show) {
            $this->notFound('Series not found');
        }

        $this->ok($show, [
            'version' => md5($show['updated_at'] ?? ''),
        ]);
    }

    /**
     * GET /api/v1/episodes/{id}
     */
    public function episode(string $id): void
    {
        $episode = $this->service->getEpisode((int)$id);

        if (!$episode) {
            $this->notFound('Episode not found');
        }

        $this->ok($episode);
    }

    // =========================================================================
    // DRM LICENSE PROXY
    // =========================================================================

    /**
     * GET /api/v1/drm/license
     * Proxies ClearKey license requests to the VOD server.
     * Player calls this instead of hitting the VOD server directly.
     * ?content_id=movie-123&server_id=1
     */
    public function drmLicense(): void
    {
        $contentId = $this->query('content_id');
        $serverId  = (int)$this->query('server_id', 0);

        if (empty($contentId) || !$serverId) {
            $this->error('content_id and server_id are required', 400);
        }

        try {
            $vodService = new VodServerService();
            $server = $vodService->getServer($serverId);
            if (!$server) {
                $this->error('VOD server not found', 404);
            }

            // Proxy to VOD server's ClearKey license endpoint
            $vodUrl = rtrim($server['url'], '/') . '/api/drm/license?content_id=' . urlencode($contentId);
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $vodUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER     => [
                    'X-API-Key: ' . ($server['api_key'] ?? ''),
                    'Content-Type: application/json',
                ],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);

            // If this is a POST (EME standard sends POST with kids array),
            // forward the request body
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $body = file_get_contents('php://input');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                // Change URL to license endpoint without content_id param
                $vodUrl = rtrim($server['url'], '/') . '/api/drm/license';
                curl_setopt($ch, CURLOPT_URL, $vodUrl);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                $this->error('VOD server unreachable: ' . $curlErr, 502);
            }

            // Forward the response directly (ClearKey license is JSON)
            http_response_code($httpCode ?: 200);
            header('Content-Type: application/json; charset=utf-8');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Cache-Control: no-cache');
            echo $response;
            exit;
        } catch (\Throwable $e) {
            error_log('DRM license proxy error: ' . $e->getMessage());
            $this->error('License request failed', 500);
        }
    }

    // =========================================================================
    // CATEGORIES
    // =========================================================================

    /**
     * GET /api/v1/categories
     * ?type=live|vod|series&since=...
     */
    public function categories(): void
    {
        $filters = $this->queryFilters([
            'type' => null,
            'since' => null,
        ]);

        $categories = $this->service->getCategories($filters);
        $version = $this->service->getContentVersion('categories');

        $this->ok($categories, [
            'version' => $version,
            'total' => count($categories),
        ]);
    }

    // =========================================================================
    // SEARCH
    // =========================================================================

    /**
     * GET /api/v1/person/{tmdbPersonId}
     */
    public function person(string $tmdbPersonId): void
    {
        $person = $this->service->getPerson((int)$tmdbPersonId);

        if (!$person) {
            $this->notFound('Person not found');
        }

        $this->ok($person);
    }

    /**
     * GET /api/v1/search
     * ?q=batman&type=all|movie|series|channel&limit=20
     */
    public function search(): void
    {
        $query = trim($this->query('q', ''));

        if (strlen($query) < 2) {
            $this->error('Search query must be at least 2 characters', 400, 'QUERY_TOO_SHORT');
            return;
        }

        $filters = $this->queryFilters([
            'type' => 'all',
            'limit' => 20,
        ]);

        $results = $this->service->search($query, $filters);

        $this->ok($results, [
            'query' => $query,
            'total' => count($results),
        ]);
    }
}
