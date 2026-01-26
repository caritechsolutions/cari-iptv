<?php
/**
 * CARI-IPTV Metadata Service
 * Fetches metadata from external APIs (Fanart.tv, TMDB)
 */

namespace CariIPTV\Services;

class MetadataService
{
    private SettingsService $settings;
    private array $config;

    // API endpoints
    private const FANART_TV_API = 'https://webservice.fanart.tv/v3';
    private const TMDB_API = 'https://api.themoviedb.org/3';
    private const TMDB_IMAGE_BASE = 'https://image.tmdb.org/t/p';

    public function __construct()
    {
        $this->settings = new SettingsService();
        $this->loadConfig();
    }

    /**
     * Load metadata configuration from settings
     */
    private function loadConfig(): void
    {
        $this->config = [
            'fanart_tv' => [
                'api_key' => $this->settings->get('fanart_tv_api_key', '', 'metadata'),
            ],
            'tmdb' => [
                'api_key' => $this->settings->get('tmdb_api_key', '', 'metadata'),
            ],
        ];
    }

    /**
     * Check if Fanart.tv is configured
     */
    public function isFanartConfigured(): bool
    {
        return !empty($this->config['fanart_tv']['api_key']);
    }

    /**
     * Check if TMDB is configured
     */
    public function isTmdbConfigured(): bool
    {
        return !empty($this->config['tmdb']['api_key']);
    }

    /**
     * Test Fanart.tv connection
     */
    public function testFanartConnection(): bool
    {
        if (!$this->isFanartConfigured()) {
            return false;
        }

        try {
            // Test with a known TV network ID (HBO = 49)
            $url = self::FANART_TV_API . '/tv/49?api_key=' . $this->config['fanart_tv']['api_key'];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Test TMDB connection
     */
    public function testTmdbConnection(): bool
    {
        if (!$this->isTmdbConfigured()) {
            return false;
        }

        try {
            $url = self::TMDB_API . '/configuration?api_key=' . $this->config['tmdb']['api_key'];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Search for TV network logos on Fanart.tv
     */
    public function searchNetworkLogos(string $query): array
    {
        if (!$this->isFanartConfigured()) {
            return ['error' => 'Fanart.tv API key not configured'];
        }

        // First search TMDB for network ID
        $networks = $this->searchTmdbNetworks($query);

        if (empty($networks)) {
            return ['results' => [], 'message' => 'No networks found'];
        }

        $results = [];
        foreach ($networks as $network) {
            $logos = $this->getNetworkLogos($network['id']);
            if (!empty($logos)) {
                $results[] = [
                    'id' => $network['id'],
                    'name' => $network['name'],
                    'origin_country' => $network['origin_country'] ?? '',
                    'logos' => $logos,
                ];
            }
        }

        return ['results' => $results];
    }

    /**
     * Search TMDB for TV networks
     */
    private function searchTmdbNetworks(string $query): array
    {
        if (!$this->isTmdbConfigured()) {
            return [];
        }

        try {
            $url = self::TMDB_API . '/search/company?' . http_build_query([
                'api_key' => $this->config['tmdb']['api_key'],
                'query' => $query,
            ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                return array_slice($data['results'] ?? [], 0, 10);
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get network logos from Fanart.tv
     */
    public function getNetworkLogos(int $networkId): array
    {
        if (!$this->isFanartConfigured()) {
            return [];
        }

        try {
            $url = self::FANART_TV_API . '/tv/' . $networkId . '?api_key=' . $this->config['fanart_tv']['api_key'];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);

                $logos = [];

                // HD TV logos
                if (!empty($data['hdtvlogo'])) {
                    foreach (array_slice($data['hdtvlogo'], 0, 5) as $logo) {
                        $logos[] = [
                            'url' => $logo['url'],
                            'type' => 'hdtvlogo',
                            'lang' => $logo['lang'] ?? 'en',
                        ];
                    }
                }

                // Clear logos
                if (!empty($data['hdclearart'])) {
                    foreach (array_slice($data['hdclearart'], 0, 3) as $logo) {
                        $logos[] = [
                            'url' => $logo['url'],
                            'type' => 'clearart',
                            'lang' => $logo['lang'] ?? 'en',
                        ];
                    }
                }

                // TV thumbs
                if (!empty($data['tvthumb'])) {
                    foreach (array_slice($data['tvthumb'], 0, 3) as $logo) {
                        $logos[] = [
                            'url' => $logo['url'],
                            'type' => 'tvthumb',
                            'lang' => $logo['lang'] ?? 'en',
                        ];
                    }
                }

                return $logos;
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Search TMDB for movies
     */
    public function searchMovies(string $query, int $year = null): array
    {
        if (!$this->isTmdbConfigured()) {
            return ['error' => 'TMDB API key not configured'];
        }

        try {
            $params = [
                'api_key' => $this->config['tmdb']['api_key'],
                'query' => $query,
                'include_adult' => false,
            ];

            if ($year) {
                $params['year'] = $year;
            }

            $url = self::TMDB_API . '/search/movie?' . http_build_query($params);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                return $this->formatMovieResults($data['results'] ?? []);
            }

            return ['error' => 'API request failed'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Search TMDB for TV shows
     */
    public function searchTVShows(string $query, int $year = null): array
    {
        if (!$this->isTmdbConfigured()) {
            return ['error' => 'TMDB API key not configured'];
        }

        try {
            $params = [
                'api_key' => $this->config['tmdb']['api_key'],
                'query' => $query,
            ];

            if ($year) {
                $params['first_air_date_year'] = $year;
            }

            $url = self::TMDB_API . '/search/tv?' . http_build_query($params);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                return $this->formatTVResults($data['results'] ?? []);
            }

            return ['error' => 'API request failed'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get movie details from TMDB
     */
    public function getMovieDetails(int $tmdbId): ?array
    {
        if (!$this->isTmdbConfigured()) {
            return null;
        }

        try {
            $url = self::TMDB_API . '/movie/' . $tmdbId . '?' . http_build_query([
                'api_key' => $this->config['tmdb']['api_key'],
                'append_to_response' => 'credits,images',
            ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                return $this->formatMovieDetails($data);
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get TV show details from TMDB
     */
    public function getTVShowDetails(int $tmdbId): ?array
    {
        if (!$this->isTmdbConfigured()) {
            return null;
        }

        try {
            $url = self::TMDB_API . '/tv/' . $tmdbId . '?' . http_build_query([
                'api_key' => $this->config['tmdb']['api_key'],
                'append_to_response' => 'credits,images',
            ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                return $this->formatTVShowDetails($data);
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Format movie search results
     */
    private function formatMovieResults(array $results): array
    {
        $formatted = [];
        foreach (array_slice($results, 0, 10) as $movie) {
            $formatted[] = [
                'id' => $movie['id'],
                'title' => $movie['title'],
                'original_title' => $movie['original_title'],
                'release_date' => $movie['release_date'] ?? '',
                'year' => !empty($movie['release_date']) ? substr($movie['release_date'], 0, 4) : '',
                'overview' => $movie['overview'],
                'poster' => $movie['poster_path'] ? self::TMDB_IMAGE_BASE . '/w342' . $movie['poster_path'] : null,
                'backdrop' => $movie['backdrop_path'] ? self::TMDB_IMAGE_BASE . '/w780' . $movie['backdrop_path'] : null,
                'vote_average' => $movie['vote_average'],
            ];
        }
        return ['results' => $formatted];
    }

    /**
     * Format TV search results
     */
    private function formatTVResults(array $results): array
    {
        $formatted = [];
        foreach (array_slice($results, 0, 10) as $show) {
            $formatted[] = [
                'id' => $show['id'],
                'name' => $show['name'],
                'original_name' => $show['original_name'],
                'first_air_date' => $show['first_air_date'] ?? '',
                'year' => !empty($show['first_air_date']) ? substr($show['first_air_date'], 0, 4) : '',
                'overview' => $show['overview'],
                'poster' => $show['poster_path'] ? self::TMDB_IMAGE_BASE . '/w342' . $show['poster_path'] : null,
                'backdrop' => $show['backdrop_path'] ? self::TMDB_IMAGE_BASE . '/w780' . $show['backdrop_path'] : null,
                'vote_average' => $show['vote_average'],
            ];
        }
        return ['results' => $formatted];
    }

    /**
     * Format movie details
     */
    private function formatMovieDetails(array $data): array
    {
        $cast = [];
        if (!empty($data['credits']['cast'])) {
            foreach (array_slice($data['credits']['cast'], 0, 10) as $member) {
                $cast[] = [
                    'name' => $member['name'],
                    'character' => $member['character'],
                    'profile' => $member['profile_path'] ? self::TMDB_IMAGE_BASE . '/w185' . $member['profile_path'] : null,
                ];
            }
        }

        $directors = [];
        if (!empty($data['credits']['crew'])) {
            foreach ($data['credits']['crew'] as $member) {
                if ($member['job'] === 'Director') {
                    $directors[] = $member['name'];
                }
            }
        }

        $genres = array_column($data['genres'] ?? [], 'name');

        return [
            'id' => $data['id'],
            'title' => $data['title'],
            'original_title' => $data['original_title'],
            'tagline' => $data['tagline'] ?? '',
            'overview' => $data['overview'],
            'release_date' => $data['release_date'] ?? '',
            'year' => !empty($data['release_date']) ? substr($data['release_date'], 0, 4) : '',
            'runtime' => $data['runtime'] ?? 0,
            'genres' => $genres,
            'poster' => $data['poster_path'] ? self::TMDB_IMAGE_BASE . '/w500' . $data['poster_path'] : null,
            'backdrop' => $data['backdrop_path'] ? self::TMDB_IMAGE_BASE . '/w1280' . $data['backdrop_path'] : null,
            'vote_average' => $data['vote_average'] ?? 0,
            'cast' => $cast,
            'directors' => $directors,
            'production_countries' => array_column($data['production_countries'] ?? [], 'name'),
            'spoken_languages' => array_column($data['spoken_languages'] ?? [], 'english_name'),
        ];
    }

    /**
     * Format TV show details
     */
    private function formatTVShowDetails(array $data): array
    {
        $cast = [];
        if (!empty($data['credits']['cast'])) {
            foreach (array_slice($data['credits']['cast'], 0, 10) as $member) {
                $cast[] = [
                    'name' => $member['name'],
                    'character' => $member['character'],
                    'profile' => $member['profile_path'] ? self::TMDB_IMAGE_BASE . '/w185' . $member['profile_path'] : null,
                ];
            }
        }

        $creators = array_column($data['created_by'] ?? [], 'name');
        $genres = array_column($data['genres'] ?? [], 'name');
        $networks = array_column($data['networks'] ?? [], 'name');

        return [
            'id' => $data['id'],
            'name' => $data['name'],
            'original_name' => $data['original_name'],
            'tagline' => $data['tagline'] ?? '',
            'overview' => $data['overview'],
            'first_air_date' => $data['first_air_date'] ?? '',
            'last_air_date' => $data['last_air_date'] ?? '',
            'year' => !empty($data['first_air_date']) ? substr($data['first_air_date'], 0, 4) : '',
            'status' => $data['status'] ?? '',
            'number_of_seasons' => $data['number_of_seasons'] ?? 0,
            'number_of_episodes' => $data['number_of_episodes'] ?? 0,
            'episode_run_time' => $data['episode_run_time'][0] ?? 0,
            'genres' => $genres,
            'poster' => $data['poster_path'] ? self::TMDB_IMAGE_BASE . '/w500' . $data['poster_path'] : null,
            'backdrop' => $data['backdrop_path'] ? self::TMDB_IMAGE_BASE . '/w1280' . $data['backdrop_path'] : null,
            'vote_average' => $data['vote_average'] ?? 0,
            'cast' => $cast,
            'creators' => $creators,
            'networks' => $networks,
            'origin_country' => $data['origin_country'] ?? [],
        ];
    }

    /**
     * Download image from URL and save locally
     */
    public function downloadImage(string $url, string $destination): bool
    {
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 30,
            ]);

            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && !empty($imageData)) {
                $dir = dirname($destination);
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }

                return file_put_contents($destination, $imageData) !== false;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get service status
     */
    public function getStatus(): array
    {
        return [
            'fanart_tv' => [
                'configured' => $this->isFanartConfigured(),
                'connected' => $this->isFanartConfigured() ? $this->testFanartConnection() : false,
            ],
            'tmdb' => [
                'configured' => $this->isTmdbConfigured(),
                'connected' => $this->isTmdbConfigured() ? $this->testTmdbConnection() : false,
            ],
        ];
    }
}
