<?php
/**
 * CARI-IPTV Content API Controller
 * Public endpoints for channels, movies, series, categories, search
 */

namespace CariIPTV\Controllers\Api;

use CariIPTV\Services\ContentApiService;

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
