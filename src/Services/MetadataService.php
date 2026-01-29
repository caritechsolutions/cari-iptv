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
    private ?array $channelLogosDb = null;

    // API endpoints
    private const FANART_TV_API = 'https://webservice.fanart.tv/v3';
    private const TMDB_API = 'https://api.themoviedb.org/3';
    private const TMDB_IMAGE_BASE = 'https://image.tmdb.org/t/p';
    private const YOUTUBE_API = 'https://www.googleapis.com/youtube/v3';

    // Channel logos API (jaruba/channel-logos)
    private const CHANNEL_LOGOS_API = 'https://jaruba.github.io/channel-logos/logo_paths.json';
    private const CHANNEL_LOGOS_BASE = 'https://jaruba.github.io/channel-logos/export';

    // Logo variations available
    private const LOGO_VARIATIONS = [
        'transparent-white' => 'Transparent (White)',
        'transparent-color' => 'Transparent (Color)',
        '212c39-white' => 'Dark Background',
        '282c34-white' => 'Dark Gray Background',
        'fff-color' => 'White Background',
    ];

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
            'youtube' => [
                'api_key' => $this->settings->get('youtube_api_key', '', 'metadata'),
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
     * Check if YouTube API is configured
     */
    public function isYoutubeConfigured(): bool
    {
        return !empty($this->config['youtube']['api_key']);
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
     * Test YouTube Data API connection
     */
    public function testYoutubeConnection(): bool
    {
        if (!$this->isYoutubeConfigured()) {
            return false;
        }

        try {
            $url = self::YOUTUBE_API . '/videos?part=snippet&chart=mostPopular&maxResults=1&key=' . $this->config['youtube']['api_key'];

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
     * Search for channel logos from jaruba/channel-logos repository
     */
    public function searchNetworkLogos(string $query): array
    {
        // Load the channel logos database
        $logosDb = $this->loadChannelLogosDb();

        if (empty($logosDb)) {
            return [];
        }

        $query = strtolower(trim($query));
        $results = [];

        // Search through the database
        foreach ($logosDb as $channelName => $logoPath) {
            $normalizedName = strtolower($channelName);

            // Check if query matches channel name
            if (strpos($normalizedName, $query) !== false ||
                strpos($query, $normalizedName) !== false ||
                $this->fuzzyMatch($query, $normalizedName)) {

                // Add multiple variations of the logo
                $results[] = [
                    'name' => $channelName,
                    'url' => self::CHANNEL_LOGOS_BASE . '/transparent-white' . $logoPath,
                    'type' => 'Transparent (White)',
                ];

                $results[] = [
                    'name' => $channelName,
                    'url' => self::CHANNEL_LOGOS_BASE . '/transparent-color' . $logoPath,
                    'type' => 'Transparent (Color)',
                ];

                $results[] = [
                    'name' => $channelName,
                    'url' => self::CHANNEL_LOGOS_BASE . '/212c39-white' . $logoPath,
                    'type' => 'Dark Background',
                ];

                // Limit results to prevent overwhelming the UI
                if (count($results) >= 30) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Load the channel logos database from the API
     */
    private function loadChannelLogosDb(): array
    {
        // Return cached version if available
        if ($this->channelLogosDb !== null) {
            return $this->channelLogosDb;
        }

        // Check for local cache file
        $cacheFile = BASE_PATH . '/storage/cache/channel_logos.json';
        $cacheMaxAge = 86400; // 24 hours

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheMaxAge) {
            $cached = file_get_contents($cacheFile);
            $this->channelLogosDb = json_decode($cached, true) ?: [];
            return $this->channelLogosDb;
        }

        // Fetch from API
        try {
            $ch = curl_init(self::CHANNEL_LOGOS_API);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && !empty($response)) {
                $data = json_decode($response, true);

                if (!empty($data)) {
                    // Cache the response
                    $cacheDir = dirname($cacheFile);
                    if (!is_dir($cacheDir)) {
                        mkdir($cacheDir, 0775, true);
                    }
                    file_put_contents($cacheFile, $response);

                    $this->channelLogosDb = $data;
                    return $this->channelLogosDb;
                }
            }
        } catch (\Exception $e) {
            error_log('Failed to load channel logos database: ' . $e->getMessage());
        }

        $this->channelLogosDb = [];
        return $this->channelLogosDb;
    }

    /**
     * Simple fuzzy matching for channel names
     */
    private function fuzzyMatch(string $query, string $channelName): bool
    {
        // Remove common suffixes/prefixes for better matching
        $cleanQuery = preg_replace('/\s*(hd|tv|channel|network|uk|us|eu)$/i', '', $query);
        $cleanName = preg_replace('/\s*(hd|tv|channel|network|uk|us|eu)$/i', '', $channelName);

        // Check if cleaned versions match
        if (!empty($cleanQuery) && strpos($cleanName, $cleanQuery) !== false) {
            return true;
        }

        // Calculate similarity
        similar_text($query, $channelName, $percent);
        return $percent > 70;
    }

    /**
     * Search for TV show logos on Fanart.tv (keeping for TV shows)
     */
    public function searchTVShowLogos(string $query): array
    {
        if (!$this->isFanartConfigured()) {
            return ['error' => 'Fanart.tv API key not configured'];
        }

        // First search TMDB for TV show
        $shows = $this->searchTVShows($query);

        if (empty($shows['results'])) {
            return ['results' => [], 'message' => 'No TV shows found'];
        }

        $results = [];
        foreach ($shows['results'] as $show) {
            $logos = $this->getTVShowLogos($show['id']);
            if (!empty($logos)) {
                $results[] = [
                    'id' => $show['id'],
                    'name' => $show['name'],
                    'year' => $show['year'] ?? '',
                    'logos' => $logos,
                ];
            }
        }

        return ['results' => $results];
    }

    /**
     * Get TV show logos from Fanart.tv
     */
    public function getTVShowLogos(int $tmdbId): array
    {
        if (!$this->isFanartConfigured()) {
            return [];
        }

        try {
            $url = self::FANART_TV_API . '/tv/' . $tmdbId . '?api_key=' . $this->config['fanart_tv']['api_key'];

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
                if (!empty($data['clearlogo'])) {
                    foreach (array_slice($data['clearlogo'], 0, 3) as $logo) {
                        $logos[] = [
                            'url' => $logo['url'],
                            'type' => 'clearlogo',
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
            'youtube' => [
                'configured' => $this->isYoutubeConfigured(),
                'connected' => $this->isYoutubeConfigured() ? $this->testYoutubeConnection() : false,
            ],
        ];
    }

    /**
     * Search YouTube for trailers
     */
    public function searchYoutubeTrailers(string $movieTitle, ?int $year = null): array
    {
        if (!$this->isYoutubeConfigured()) {
            return ['error' => 'YouTube API key not configured'];
        }

        try {
            $query = $movieTitle . ($year ? " {$year}" : '') . ' official trailer';

            $params = [
                'part' => 'snippet',
                'q' => $query,
                'type' => 'video',
                'videoCategoryId' => '1', // Film & Animation
                'maxResults' => 10,
                'key' => $this->config['youtube']['api_key'],
            ];

            $url = self::YOUTUBE_API . '/search?' . http_build_query($params);

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
                return $this->formatYoutubeResults($data['items'] ?? []);
            }

            return ['error' => 'API request failed'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Search YouTube for Creative Commons (royalty-free) content
     */
    public function searchYoutubeCreativeCommons(string $query, string $type = 'movie'): array
    {
        if (!$this->isYoutubeConfigured()) {
            return ['error' => 'YouTube API key not configured'];
        }

        try {
            $searchQuery = $query;
            if ($type === 'movie') {
                $searchQuery .= ' full movie';
            }

            $params = [
                'part' => 'snippet',
                'q' => $searchQuery,
                'type' => 'video',
                'videoLicense' => 'creativeCommon',
                'videoDuration' => $type === 'movie' ? 'long' : 'any', // long = > 20 minutes
                'maxResults' => 20,
                'key' => $this->config['youtube']['api_key'],
            ];

            $url = self::YOUTUBE_API . '/search?' . http_build_query($params);

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
                $results = $this->formatYoutubeResults($data['items'] ?? []);

                // Get video details for duration
                if (!empty($results['results'])) {
                    $videoIds = array_column($results['results'], 'video_id');
                    $details = $this->getYoutubeVideoDetails($videoIds);

                    // Merge duration info
                    foreach ($results['results'] as &$video) {
                        if (isset($details[$video['video_id']])) {
                            $video['duration'] = $details[$video['video_id']]['duration'];
                            $video['duration_formatted'] = $details[$video['video_id']]['duration_formatted'];
                        }
                    }
                }

                return $results;
            }

            return ['error' => 'API request failed'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get YouTube video details (for duration, etc.)
     */
    public function getYoutubeVideoDetails(array $videoIds): array
    {
        if (!$this->isYoutubeConfigured() || empty($videoIds)) {
            return [];
        }

        try {
            $params = [
                'part' => 'contentDetails,snippet',
                'id' => implode(',', array_slice($videoIds, 0, 50)),
                'key' => $this->config['youtube']['api_key'],
            ];

            $url = self::YOUTUBE_API . '/videos?' . http_build_query($params);

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
                $details = [];

                foreach ($data['items'] ?? [] as $video) {
                    $duration = $this->parseYoutubeDuration($video['contentDetails']['duration'] ?? 'PT0S');
                    $details[$video['id']] = [
                        'duration' => $duration,
                        'duration_formatted' => $this->formatDuration($duration),
                    ];
                }

                return $details;
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Format YouTube search results
     */
    private function formatYoutubeResults(array $items): array
    {
        $formatted = [];
        foreach ($items as $item) {
            $videoId = $item['id']['videoId'] ?? null;
            if (!$videoId) continue;

            $formatted[] = [
                'video_id' => $videoId,
                'title' => $item['snippet']['title'],
                'description' => $item['snippet']['description'],
                'thumbnail' => $item['snippet']['thumbnails']['high']['url'] ?? $item['snippet']['thumbnails']['default']['url'],
                'channel' => $item['snippet']['channelTitle'],
                'published_at' => $item['snippet']['publishedAt'],
                'url' => 'https://www.youtube.com/watch?v=' . $videoId,
                'embed_url' => 'https://www.youtube.com/embed/' . $videoId,
            ];
        }
        return ['results' => $formatted];
    }

    /**
     * Parse YouTube ISO 8601 duration to seconds
     */
    private function parseYoutubeDuration(string $duration): int
    {
        $interval = new \DateInterval($duration);
        return ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    }

    /**
     * Format seconds to human readable duration
     */
    private function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }
        return sprintf('%d:%02d', $minutes, $secs);
    }

    /**
     * Get movie videos from TMDB (including trailers)
     */
    public function getMovieVideos(int $tmdbId): array
    {
        if (!$this->isTmdbConfigured()) {
            return [];
        }

        try {
            $url = self::TMDB_API . '/movie/' . $tmdbId . '/videos?api_key=' . $this->config['tmdb']['api_key'];

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
                $videos = [];

                foreach ($data['results'] ?? [] as $video) {
                    if ($video['site'] === 'YouTube') {
                        $videos[] = [
                            'key' => $video['key'],
                            'name' => $video['name'],
                            'type' => $video['type'], // Trailer, Teaser, Clip, etc.
                            'url' => 'https://www.youtube.com/watch?v=' . $video['key'],
                            'embed_url' => 'https://www.youtube.com/embed/' . $video['key'],
                        ];
                    }
                }

                return $videos;
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get movie artwork from Fanart.tv
     */
    public function getMovieArtwork(int $tmdbId): array
    {
        if (!$this->isFanartConfigured()) {
            return [];
        }

        try {
            $url = self::FANART_TV_API . '/movies/' . $tmdbId . '?api_key=' . $this->config['fanart_tv']['api_key'];

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

                $artwork = [
                    'posters' => [],
                    'backdrops' => [],
                    'logos' => [],
                    'discs' => [],
                    'banners' => [],
                ];

                // Movie posters
                if (!empty($data['movieposter'])) {
                    foreach (array_slice($data['movieposter'], 0, 10) as $img) {
                        $artwork['posters'][] = [
                            'url' => $img['url'],
                            'lang' => $img['lang'] ?? 'en',
                        ];
                    }
                }

                // Movie backgrounds
                if (!empty($data['moviebackground'])) {
                    foreach (array_slice($data['moviebackground'], 0, 10) as $img) {
                        $artwork['backdrops'][] = [
                            'url' => $img['url'],
                            'lang' => $img['lang'] ?? 'en',
                        ];
                    }
                }

                // HD Movie clearart/logos
                if (!empty($data['hdmovielogo'])) {
                    foreach (array_slice($data['hdmovielogo'], 0, 5) as $img) {
                        $artwork['logos'][] = [
                            'url' => $img['url'],
                            'lang' => $img['lang'] ?? 'en',
                        ];
                    }
                } elseif (!empty($data['movielogo'])) {
                    foreach (array_slice($data['movielogo'], 0, 5) as $img) {
                        $artwork['logos'][] = [
                            'url' => $img['url'],
                            'lang' => $img['lang'] ?? 'en',
                        ];
                    }
                }

                // Movie disc art
                if (!empty($data['moviedisc'])) {
                    foreach (array_slice($data['moviedisc'], 0, 5) as $img) {
                        $artwork['discs'][] = [
                            'url' => $img['url'],
                            'disc_type' => $img['disc_type'] ?? 'dvd',
                        ];
                    }
                }

                // Movie banners
                if (!empty($data['moviebanner'])) {
                    foreach (array_slice($data['moviebanner'], 0, 5) as $img) {
                        $artwork['banners'][] = [
                            'url' => $img['url'],
                            'lang' => $img['lang'] ?? 'en',
                        ];
                    }
                }

                return $artwork;
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Search Internet Archive for public domain movies
     * https://archive.org/advancedsearch.php
     */
    public function searchInternetArchive(string $query, string $type = 'movie'): array
    {
        try {
            // Build search query - keep it simple for better results
            // Just search for the term in movies mediatype
            $searchQuery = trim($query);
            if (empty($searchQuery)) {
                return ['results' => []];
            }

            // Build URL manually because http_build_query doesn't handle repeated params well
            $fields = ['identifier', 'title', 'description', 'year', 'runtime', 'creator', 'subject'];
            $fieldParams = implode('&', array_map(fn($f) => 'fl[]=' . $f, $fields));

            // Simple query: search term + movies mediatype
            $encodedQuery = rawurlencode("({$searchQuery}) AND mediatype:(movies)");

            $url = "https://archive.org/advancedsearch.php?q={$encodedQuery}&{$fieldParams}&sort[]=downloads+desc&rows=25&page=1&output=json";

            error_log("Internet Archive URL: " . $url);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_USERAGENT => 'CARI-IPTV/1.0',
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                error_log("Internet Archive cURL error: " . $curlError);
                return ['error' => 'Connection failed: ' . $curlError];
            }

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("Internet Archive JSON error: " . json_last_error_msg());
                    return ['error' => 'Invalid response format'];
                }
                $docs = $data['response']['docs'] ?? [];
                error_log("Internet Archive found " . count($docs) . " results");
                return $this->formatInternetArchiveResults($docs);
            }

            error_log("Internet Archive HTTP error: " . $httpCode);
            return ['error' => 'API request failed with status ' . $httpCode];
        } catch (\Exception $e) {
            error_log("Internet Archive exception: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Format Internet Archive search results
     */
    private function formatInternetArchiveResults(array $items): array
    {
        $formatted = [];
        foreach ($items as $item) {
            $identifier = $item['identifier'] ?? '';
            if (empty($identifier)) continue;

            // Parse runtime (can be like "1:30:00" or "90 min")
            $runtime = $item['runtime'] ?? '';
            $durationSeconds = $this->parseArchiveRuntime($runtime);

            $formatted[] = [
                'source' => 'internet_archive',
                'video_id' => $identifier,
                'title' => $item['title'] ?? $identifier,
                'description' => is_array($item['description'] ?? null)
                    ? ($item['description'][0] ?? '')
                    : ($item['description'] ?? ''),
                'thumbnail' => "https://archive.org/services/img/{$identifier}",
                'channel' => is_array($item['creator'] ?? null)
                    ? ($item['creator'][0] ?? 'Internet Archive')
                    : ($item['creator'] ?? 'Internet Archive'),
                'year' => $item['year'] ?? null,
                'duration' => $durationSeconds,
                'duration_formatted' => $durationSeconds > 0 ? $this->formatDuration($durationSeconds) : '',
                'url' => "https://archive.org/details/{$identifier}",
                'embed_url' => "https://archive.org/embed/{$identifier}",
                'stream_url' => "https://archive.org/download/{$identifier}/{$identifier}.mp4",
            ];
        }
        return ['results' => $formatted];
    }

    /**
     * Parse Internet Archive runtime to seconds
     */
    private function parseArchiveRuntime(string $runtime): int
    {
        if (empty($runtime)) return 0;

        // Format: "1:30:00" or "90:00" or "90 min"
        if (preg_match('/(\d+):(\d+):(\d+)/', $runtime, $matches)) {
            return (int)$matches[1] * 3600 + (int)$matches[2] * 60 + (int)$matches[3];
        }
        if (preg_match('/(\d+):(\d+)/', $runtime, $matches)) {
            return (int)$matches[1] * 60 + (int)$matches[2];
        }
        if (preg_match('/(\d+)\s*min/i', $runtime, $matches)) {
            return (int)$matches[1] * 60;
        }
        return 0;
    }

    /**
     * Get details for a specific Internet Archive item
     */
    public function getInternetArchiveDetails(string $identifier): ?array
    {
        try {
            $url = "https://archive.org/metadata/{$identifier}";

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_USERAGENT => 'CARI-IPTV/1.0',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                $metadata = $data['metadata'] ?? [];
                $files = $data['files'] ?? [];

                // Find the best video file
                $videoFile = null;
                $preferredFormats = ['mp4', 'mpeg4', 'h.264', 'ogv'];
                foreach ($files as $file) {
                    $format = strtolower($file['format'] ?? '');
                    if (str_contains($format, 'mp4') || str_contains($format, 'mpeg4') || str_contains($format, 'h.264')) {
                        $videoFile = $file;
                        break;
                    }
                }

                return [
                    'identifier' => $identifier,
                    'title' => $metadata['title'] ?? $identifier,
                    'description' => is_array($metadata['description'] ?? null)
                        ? implode("\n", $metadata['description'])
                        : ($metadata['description'] ?? ''),
                    'year' => $metadata['year'] ?? $metadata['date'] ?? null,
                    'creator' => is_array($metadata['creator'] ?? null)
                        ? implode(', ', $metadata['creator'])
                        : ($metadata['creator'] ?? ''),
                    'runtime' => $metadata['runtime'] ?? '',
                    'thumbnail' => "https://archive.org/services/img/{$identifier}",
                    'stream_url' => $videoFile
                        ? "https://archive.org/download/{$identifier}/" . urlencode($videoFile['name'])
                        : "https://archive.org/download/{$identifier}/{$identifier}.mp4",
                    'embed_url' => "https://archive.org/embed/{$identifier}",
                    'license' => $metadata['licenseurl'] ?? 'Public Domain',
                ];
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
