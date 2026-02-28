<?php
/**
 * CARI-IPTV Subtitle Service
 * Manages subtitles for movies: CRUD, OpenSubtitles API, FFmpeg extraction
 */

namespace CariIPTV\Services;

use CariIPTV\Core\Database;

class SubtitleService
{
    private Database $db;
    private SettingsService $settings;
    private array $config;

    // OpenSubtitles REST API v1
    private const OS_API_BASE = 'https://api.opensubtitles.com/api/v1';
    private const OS_USER_AGENT = 'CARI-IPTV v1.0.0';

    // Common languages for subtitle selection
    public const LANGUAGES = [
        'en' => 'English',
        'es' => 'Spanish',
        'fr' => 'French',
        'pt' => 'Portuguese',
        'de' => 'German',
        'it' => 'Italian',
        'nl' => 'Dutch',
        'ru' => 'Russian',
        'zh' => 'Chinese',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'ar' => 'Arabic',
        'hi' => 'Hindi',
        'pl' => 'Polish',
        'tr' => 'Turkish',
        'sv' => 'Swedish',
        'da' => 'Danish',
        'no' => 'Norwegian',
        'fi' => 'Finnish',
        'el' => 'Greek',
        'he' => 'Hebrew',
        'th' => 'Thai',
        'vi' => 'Vietnamese',
        'id' => 'Indonesian',
        'ms' => 'Malay',
        'ro' => 'Romanian',
        'hu' => 'Hungarian',
        'cs' => 'Czech',
        'sk' => 'Slovak',
        'uk' => 'Ukrainian',
        'bg' => 'Bulgarian',
        'hr' => 'Croatian',
        'sr' => 'Serbian',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->settings = new SettingsService();
        $this->loadConfig();
    }

    /**
     * Load subtitle configuration from settings
     */
    private function loadConfig(): void
    {
        $this->config = [
            'api_key' => $this->settings->get('opensubtitles_api_key', '', 'subtitles'),
            'username' => $this->settings->get('opensubtitles_username', '', 'subtitles'),
            'password' => $this->settings->get('opensubtitles_password', '', 'subtitles'),
            'auto_fetch' => $this->settings->get('auto_fetch_subtitles', '0', 'subtitles'),
            'preferred_languages' => $this->settings->get('preferred_languages', 'en', 'subtitles'),
            'token' => $this->settings->get('opensubtitles_token', '', 'subtitles'),
            'token_expires' => $this->settings->get('opensubtitles_token_expires', '0', 'subtitles'),
        ];
    }

    // -------------------------------------------------------------------------
    // Configuration & Status
    // -------------------------------------------------------------------------

    /**
     * Check if OpenSubtitles API is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->config['api_key']);
    }

    /**
     * Get connection status for settings page
     */
    public function getStatus(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'connected' => $this->isConfigured() && !empty($this->config['token']) && (int)$this->config['token_expires'] > time(),
        ];
    }

    /**
     * Test OpenSubtitles API connection
     */
    public function testConnection(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        // Try to get info about our API consumer
        $response = $this->apiRequest('GET', '/infos/user');
        return $response !== null && isset($response['data']);
    }

    /**
     * Get preferred languages as array
     */
    public function getPreferredLanguages(): array
    {
        $langs = $this->config['preferred_languages'] ?? 'en';
        return array_filter(array_map('trim', explode(',', $langs)));
    }

    // -------------------------------------------------------------------------
    // CRUD Operations
    // -------------------------------------------------------------------------

    /**
     * Get all subtitles for a movie
     */
    public function getMovieSubtitles(int $movieId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM movie_subtitles WHERE movie_id = ? ORDER BY is_default DESC, language_name ASC",
            [$movieId]
        );
    }

    /**
     * Get a single subtitle by ID
     */
    public function getSubtitle(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM movie_subtitles WHERE id = ?", [$id]);
    }

    /**
     * Add a subtitle record
     */
    public function addSubtitle(int $movieId, array $data): int
    {
        // If setting as default, clear existing defaults first
        if (!empty($data['is_default'])) {
            $this->db->execute(
                "UPDATE movie_subtitles SET is_default = 0 WHERE movie_id = ?",
                [$movieId]
            );
        }

        $this->db->execute(
            "INSERT INTO movie_subtitles (movie_id, language_code, language_name, source, external_id, file_path, format, is_default, is_forced)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), source = VALUES(source),
             external_id = VALUES(external_id), format = VALUES(format), is_default = VALUES(is_default),
             is_forced = VALUES(is_forced), updated_at = CURRENT_TIMESTAMP",
            [
                $movieId,
                $data['language_code'],
                $data['language_name'],
                $data['source'] ?? 'upload',
                $data['external_id'] ?? null,
                $data['file_path'],
                $data['format'] ?? 'vtt',
                $data['is_default'] ?? 0,
                $data['is_forced'] ?? 0,
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    /**
     * Delete a subtitle
     */
    public function deleteSubtitle(int $subtitleId): bool
    {
        $subtitle = $this->getSubtitle($subtitleId);
        if (!$subtitle) {
            return false;
        }

        // Delete the physical file
        if (!empty($subtitle['file_path'])) {
            $fullPath = dirname(__DIR__, 2) . '/public' . $subtitle['file_path'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        $this->db->execute("DELETE FROM movie_subtitles WHERE id = ?", [$subtitleId]);
        return true;
    }

    /**
     * Set a subtitle as default for its movie
     */
    public function setDefault(int $movieId, int $subtitleId): bool
    {
        $this->db->execute(
            "UPDATE movie_subtitles SET is_default = 0 WHERE movie_id = ?",
            [$movieId]
        );
        $this->db->execute(
            "UPDATE movie_subtitles SET is_default = 1 WHERE id = ? AND movie_id = ?",
            [$subtitleId, $movieId]
        );
        return true;
    }

    // -------------------------------------------------------------------------
    // Episode CRUD Operations
    // -------------------------------------------------------------------------

    /**
     * Get all subtitles for an episode
     */
    public function getEpisodeSubtitles(int $episodeId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM episode_subtitles WHERE episode_id = ? ORDER BY is_default DESC, language_name ASC",
            [$episodeId]
        );
    }

    /**
     * Get a single episode subtitle by ID
     */
    public function getEpisodeSubtitle(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM episode_subtitles WHERE id = ?", [$id]);
    }

    /**
     * Add an episode subtitle record
     */
    public function addEpisodeSubtitle(int $episodeId, array $data): int
    {
        if (!empty($data['is_default'])) {
            $this->db->execute(
                "UPDATE episode_subtitles SET is_default = 0 WHERE episode_id = ?",
                [$episodeId]
            );
        }

        $this->db->execute(
            "INSERT INTO episode_subtitles (episode_id, language_code, language_name, source, external_id, file_path, format, is_default, is_forced)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), source = VALUES(source),
             external_id = VALUES(external_id), format = VALUES(format), is_default = VALUES(is_default),
             is_forced = VALUES(is_forced), updated_at = CURRENT_TIMESTAMP",
            [
                $episodeId,
                $data['language_code'],
                $data['language_name'],
                $data['source'] ?? 'upload',
                $data['external_id'] ?? null,
                $data['file_path'],
                $data['format'] ?? 'vtt',
                $data['is_default'] ?? 0,
                $data['is_forced'] ?? 0,
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    /**
     * Delete an episode subtitle
     */
    public function deleteEpisodeSubtitle(int $subtitleId): bool
    {
        $subtitle = $this->getEpisodeSubtitle($subtitleId);
        if (!$subtitle) {
            return false;
        }

        if (!empty($subtitle['file_path'])) {
            $fullPath = dirname(__DIR__, 2) . '/public' . $subtitle['file_path'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        $this->db->execute("DELETE FROM episode_subtitles WHERE id = ?", [$subtitleId]);
        return true;
    }

    /**
     * Set an episode subtitle as default
     */
    public function setEpisodeDefault(int $episodeId, int $subtitleId): bool
    {
        $this->db->execute(
            "UPDATE episode_subtitles SET is_default = 0 WHERE episode_id = ?",
            [$episodeId]
        );
        $this->db->execute(
            "UPDATE episode_subtitles SET is_default = 1 WHERE id = ? AND episode_id = ?",
            [$subtitleId, $episodeId]
        );
        return true;
    }

    // -------------------------------------------------------------------------
    // Episode File Upload
    // -------------------------------------------------------------------------

    /**
     * Upload a subtitle file for an episode
     */
    public function uploadEpisodeSubtitle(array $file, int $episodeId, string $languageCode, string $languageName): array
    {
        $allowedExtensions = ['srt', 'vtt', 'ass', 'ssa'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions)) {
            return ['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowedExtensions)];
        }

        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'File too large. Maximum size is 5MB.'];
        }

        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/episodes/' . $episodeId . '/subtitles';
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0775, true)) {
                return ['success' => false, 'error' => 'Failed to create subtitle directory.'];
            }
        }

        $vttFilename = $languageCode . '.vtt';
        $vttPath = $uploadDir . '/' . $vttFilename;

        if ($extension === 'vtt') {
            if (!move_uploaded_file($file['tmp_name'], $vttPath)) {
                return ['success' => false, 'error' => 'Failed to save subtitle file.'];
            }
        } elseif ($extension === 'srt') {
            $tmpSrt = $uploadDir . '/' . $languageCode . '_tmp.srt';
            if (!move_uploaded_file($file['tmp_name'], $tmpSrt)) {
                return ['success' => false, 'error' => 'Failed to save subtitle file.'];
            }
            if (!$this->convertSrtToVtt($tmpSrt, $vttPath)) {
                @unlink($tmpSrt);
                return ['success' => false, 'error' => 'Failed to convert SRT to WebVTT.'];
            }
            @unlink($tmpSrt);
        } else {
            $tmpFile = $uploadDir . '/' . $languageCode . '_tmp.' . $extension;
            if (!move_uploaded_file($file['tmp_name'], $tmpFile)) {
                return ['success' => false, 'error' => 'Failed to save subtitle file.'];
            }
            if (!$this->convertToVttWithFfmpeg($tmpFile, $vttPath)) {
                @unlink($tmpFile);
                return ['success' => false, 'error' => 'Failed to convert subtitle to WebVTT.'];
            }
            @unlink($tmpFile);
        }

        $relativePath = '/uploads/episodes/' . $episodeId . '/subtitles/' . $vttFilename;

        return [
            'success' => true,
            'file_path' => $relativePath,
            'format' => 'vtt',
        ];
    }

    // -------------------------------------------------------------------------
    // Episode OpenSubtitles Search
    // -------------------------------------------------------------------------

    /**
     * Search OpenSubtitles for episode subtitles
     */
    public function searchEpisodeSubtitles(?int $tmdbShowId, int $seasonNumber, int $episodeNumber, ?string $title = null): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'OpenSubtitles API key not configured. Go to Settings > Integrations.'];
        }

        $params = [];

        if (!empty($tmdbShowId)) {
            $params['parent_tmdb_id'] = $tmdbShowId;
            $params['season_number'] = $seasonNumber;
            $params['episode_number'] = $episodeNumber;
        } elseif (!empty($title)) {
            $params['query'] = $title;
            $params['season_number'] = $seasonNumber;
            $params['episode_number'] = $episodeNumber;
        } else {
            return ['success' => false, 'error' => 'No search criteria provided.'];
        }

        $params['type'] = 'episode';

        $languages = $this->getPreferredLanguages();
        if (!empty($languages)) {
            $params['languages'] = implode(',', $languages);
        }

        $params['order_by'] = 'download_count';
        $params['order_direction'] = 'desc';

        $response = $this->apiRequest('GET', '/subtitles', $params);

        if ($response === null) {
            return ['success' => false, 'error' => 'Failed to connect to OpenSubtitles API.'];
        }

        if (empty($response['data'])) {
            return ['success' => true, 'results' => [], 'message' => 'No subtitles found.'];
        }

        $results = [];
        foreach ($response['data'] as $item) {
            $attrs = $item['attributes'] ?? [];
            $results[] = [
                'file_id' => $attrs['files'][0]['file_id'] ?? null,
                'language' => $attrs['language'] ?? 'unknown',
                'release' => $attrs['release'] ?? '',
                'download_count' => $attrs['download_count'] ?? 0,
                'hearing_impaired' => $attrs['hearing_impaired'] ?? false,
                'machine_translated' => $attrs['machine_translated'] ?? false,
                'uploader' => $attrs['uploader']['name'] ?? 'Unknown',
                'fps' => $attrs['fps'] ?? null,
                'feature' => $attrs['feature_details']['movie_name'] ?? ($attrs['feature_details']['title'] ?? ''),
                'year' => $attrs['feature_details']['year'] ?? '',
            ];
        }

        return ['success' => true, 'results' => $results, 'total' => $response['total_count'] ?? count($results)];
    }

    /**
     * Download a subtitle from OpenSubtitles for an episode
     */
    public function downloadEpisodeSubtitle(int $fileId, int $episodeId, string $languageCode, string $languageName): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'OpenSubtitles API not configured.'];
        }

        $response = $this->apiRequest('POST', '/download', ['file_id' => $fileId]);

        if ($response === null || empty($response['link'])) {
            return ['success' => false, 'error' => 'Failed to get download link from OpenSubtitles.'];
        }

        $downloadUrl = $response['link'];

        $ch = curl_init($downloadUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $srtContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($srtContent)) {
            return ['success' => false, 'error' => 'Failed to download subtitle file.'];
        }

        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/episodes/' . $episodeId . '/subtitles';
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0775, true)) {
                return ['success' => false, 'error' => 'Failed to create subtitle directory.'];
            }
        }

        $tmpSrt = $uploadDir . '/' . $languageCode . '_tmp.srt';
        file_put_contents($tmpSrt, $srtContent);

        $vttFilename = $languageCode . '.vtt';
        $vttPath = $uploadDir . '/' . $vttFilename;

        if (!$this->convertSrtToVtt($tmpSrt, $vttPath)) {
            @unlink($tmpSrt);
            return ['success' => false, 'error' => 'Failed to convert subtitle to WebVTT.'];
        }
        @unlink($tmpSrt);

        $relativePath = '/uploads/episodes/' . $episodeId . '/subtitles/' . $vttFilename;

        return [
            'success' => true,
            'file_path' => $relativePath,
            'format' => 'vtt',
            'remaining_downloads' => $response['remaining'] ?? null,
        ];
    }

    // -------------------------------------------------------------------------
    // File Upload
    // -------------------------------------------------------------------------

    /**
     * Upload a subtitle file
     */
    public function uploadSubtitle(array $file, int $movieId, string $languageCode, string $languageName): array
    {
        // Validate file
        $allowedExtensions = ['srt', 'vtt', 'ass', 'ssa'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions)) {
            return ['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowedExtensions)];
        }

        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'File too large. Maximum size is 5MB.'];
        }

        // Create upload directory
        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/vod/' . $movieId . '/subtitles';
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0775, true)) {
                return ['success' => false, 'error' => 'Failed to create subtitle directory.'];
            }
        }

        // Target path - always store as VTT
        $vttFilename = $languageCode . '.vtt';
        $vttPath = $uploadDir . '/' . $vttFilename;

        if ($extension === 'vtt') {
            // Direct copy
            if (!move_uploaded_file($file['tmp_name'], $vttPath)) {
                return ['success' => false, 'error' => 'Failed to save subtitle file.'];
            }
        } elseif ($extension === 'srt') {
            // Save SRT temporarily, then convert
            $tmpSrt = $uploadDir . '/' . $languageCode . '_tmp.srt';
            if (!move_uploaded_file($file['tmp_name'], $tmpSrt)) {
                return ['success' => false, 'error' => 'Failed to save subtitle file.'];
            }

            if (!$this->convertSrtToVtt($tmpSrt, $vttPath)) {
                @unlink($tmpSrt);
                return ['success' => false, 'error' => 'Failed to convert SRT to WebVTT.'];
            }
            @unlink($tmpSrt);
        } else {
            // ASS/SSA - try FFmpeg conversion
            $tmpFile = $uploadDir . '/' . $languageCode . '_tmp.' . $extension;
            if (!move_uploaded_file($file['tmp_name'], $tmpFile)) {
                return ['success' => false, 'error' => 'Failed to save subtitle file.'];
            }

            if (!$this->convertToVttWithFfmpeg($tmpFile, $vttPath)) {
                @unlink($tmpFile);
                return ['success' => false, 'error' => 'Failed to convert subtitle to WebVTT.'];
            }
            @unlink($tmpFile);
        }

        $relativePath = '/uploads/vod/' . $movieId . '/subtitles/' . $vttFilename;

        return [
            'success' => true,
            'file_path' => $relativePath,
            'format' => 'vtt',
        ];
    }

    /**
     * Convert SRT to WebVTT format (pure PHP, no ffmpeg needed)
     */
    public function convertSrtToVtt(string $srtPath, string $vttPath): bool
    {
        $content = @file_get_contents($srtPath);
        if ($content === false) {
            return false;
        }

        // Remove BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Normalize line endings
        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);

        // Convert SRT timestamps to VTT (replace commas with dots in timestamps)
        $content = preg_replace(
            '/(\d{2}:\d{2}:\d{2}),(\d{3})/',
            '$1.$2',
            $content
        );

        // Build VTT content
        $vtt = "WEBVTT\n\n" . trim($content) . "\n";

        return file_put_contents($vttPath, $vtt) !== false;
    }

    /**
     * Convert any subtitle format to VTT using FFmpeg
     */
    private function convertToVttWithFfmpeg(string $inputPath, string $outputPath): bool
    {
        $ffmpeg = $this->findFfmpeg();
        if (!$ffmpeg) {
            return false;
        }

        $cmd = sprintf(
            '%s -y -i %s -c:s webvtt %s 2>&1',
            escapeshellarg($ffmpeg),
            escapeshellarg($inputPath),
            escapeshellarg($outputPath)
        );

        exec($cmd, $output, $exitCode);
        return $exitCode === 0 && file_exists($outputPath);
    }

    // -------------------------------------------------------------------------
    // OpenSubtitles API
    // -------------------------------------------------------------------------

    /**
     * Search for subtitles on OpenSubtitles
     */
    public function searchSubtitles(int $movieId, ?string $tmdbId = null, ?string $imdbId = null, ?string $title = null): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'OpenSubtitles API key not configured. Go to Settings > Integrations.'];
        }

        $params = [];

        // Prefer TMDB/IMDB IDs for precise matching
        if (!empty($tmdbId)) {
            $params['tmdb_id'] = $tmdbId;
        } elseif (!empty($imdbId)) {
            $params['imdb_id'] = str_replace('tt', '', $imdbId);
        } elseif (!empty($title)) {
            $params['query'] = $title;
        } else {
            return ['success' => false, 'error' => 'No search criteria provided.'];
        }

        // Add language filter
        $languages = $this->getPreferredLanguages();
        if (!empty($languages)) {
            $params['languages'] = implode(',', $languages);
        }

        $params['order_by'] = 'download_count';
        $params['order_direction'] = 'desc';

        $response = $this->apiRequest('GET', '/subtitles', $params);

        if ($response === null) {
            return ['success' => false, 'error' => 'Failed to connect to OpenSubtitles API.'];
        }

        if (empty($response['data'])) {
            return ['success' => true, 'results' => [], 'message' => 'No subtitles found.'];
        }

        // Format results
        $results = [];
        foreach ($response['data'] as $item) {
            $attrs = $item['attributes'] ?? [];
            $results[] = [
                'file_id' => $attrs['files'][0]['file_id'] ?? null,
                'language' => $attrs['language'] ?? 'unknown',
                'release' => $attrs['release'] ?? '',
                'download_count' => $attrs['download_count'] ?? 0,
                'hearing_impaired' => $attrs['hearing_impaired'] ?? false,
                'machine_translated' => $attrs['machine_translated'] ?? false,
                'uploader' => $attrs['uploader']['name'] ?? 'Unknown',
                'fps' => $attrs['fps'] ?? null,
                'feature' => $attrs['feature_details']['movie_name'] ?? ($attrs['feature_details']['title'] ?? ''),
                'year' => $attrs['feature_details']['year'] ?? '',
            ];
        }

        return ['success' => true, 'results' => $results, 'total' => $response['total_count'] ?? count($results)];
    }

    /**
     * Download a subtitle from OpenSubtitles and save it
     */
    public function downloadSubtitle(int $fileId, int $movieId, string $languageCode, string $languageName): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'OpenSubtitles API not configured.'];
        }

        // Request download link
        $response = $this->apiRequest('POST', '/download', ['file_id' => $fileId]);

        if ($response === null || empty($response['link'])) {
            return ['success' => false, 'error' => 'Failed to get download link from OpenSubtitles.'];
        }

        $downloadUrl = $response['link'];

        // Download the subtitle file
        $ch = curl_init($downloadUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $srtContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($srtContent)) {
            return ['success' => false, 'error' => 'Failed to download subtitle file.'];
        }

        // Create upload directory
        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/vod/' . $movieId . '/subtitles';
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0775, true)) {
                return ['success' => false, 'error' => 'Failed to create subtitle directory.'];
            }
        }

        // Save as SRT temporarily
        $tmpSrt = $uploadDir . '/' . $languageCode . '_tmp.srt';
        file_put_contents($tmpSrt, $srtContent);

        // Convert to VTT
        $vttFilename = $languageCode . '.vtt';
        $vttPath = $uploadDir . '/' . $vttFilename;

        if (!$this->convertSrtToVtt($tmpSrt, $vttPath)) {
            @unlink($tmpSrt);
            return ['success' => false, 'error' => 'Failed to convert subtitle to WebVTT.'];
        }
        @unlink($tmpSrt);

        $relativePath = '/uploads/vod/' . $movieId . '/subtitles/' . $vttFilename;

        return [
            'success' => true,
            'file_path' => $relativePath,
            'format' => 'vtt',
            'remaining_downloads' => $response['remaining'] ?? null,
        ];
    }

    // -------------------------------------------------------------------------
    // FFmpeg Subtitle Extraction (Fallback)
    // -------------------------------------------------------------------------

    /**
     * Probe a source file for embedded subtitles
     */
    public function probeSubtitles(string $sourcePath): array
    {
        $ffprobe = $this->findFfprobe();
        if (!$ffprobe) {
            return ['success' => false, 'error' => 'ffprobe not found on this server.'];
        }

        $cmd = sprintf(
            '%s -v quiet -print_format json -show_streams -select_streams s %s 2>&1',
            escapeshellarg($ffprobe),
            escapeshellarg($sourcePath)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            return ['success' => false, 'error' => 'ffprobe failed to analyze source file.'];
        }

        $json = json_decode(implode("\n", $output), true);
        $streams = $json['streams'] ?? [];

        $tracks = [];
        foreach ($streams as $index => $stream) {
            $tags = $stream['tags'] ?? [];
            $codecName = $stream['codec_name'] ?? 'unknown';

            // Only text-based subtitles can be extracted to VTT
            $isTextBased = in_array($codecName, ['subrip', 'srt', 'ass', 'ssa', 'webvtt', 'mov_text', 'text']);

            $langCode = $tags['language'] ?? 'und';
            $langName = self::LANGUAGES[$langCode] ?? ($tags['title'] ?? ucfirst($langCode));

            $tracks[] = [
                'index' => $stream['index'] ?? $index,
                'stream_index' => $index,
                'language_code' => $langCode,
                'language_name' => $langName,
                'codec' => $codecName,
                'is_text_based' => $isTextBased,
                'title' => $tags['title'] ?? '',
                'forced' => ($stream['disposition']['forced'] ?? 0) === 1,
            ];
        }

        return ['success' => true, 'tracks' => $tracks];
    }

    /**
     * Extract embedded subtitles from a source file using FFmpeg
     */
    public function extractFromSource(string $sourcePath, int $movieId): array
    {
        // First probe for available subtitle tracks
        $probeResult = $this->probeSubtitles($sourcePath);
        if (!$probeResult['success'] || empty($probeResult['tracks'])) {
            return ['success' => false, 'error' => 'No subtitle tracks found in source file.', 'extracted' => []];
        }

        $ffmpeg = $this->findFfmpeg();
        if (!$ffmpeg) {
            return ['success' => false, 'error' => 'FFmpeg not found on this server.'];
        }

        // Create output directory
        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/vod/' . $movieId . '/subtitles';
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0775, true)) {
                return ['success' => false, 'error' => 'Failed to create subtitle directory.'];
            }
        }

        $extracted = [];
        $trackIndex = 0;

        foreach ($probeResult['tracks'] as $track) {
            if (!$track['is_text_based']) {
                $trackIndex++;
                continue;
            }

            $langCode = $track['language_code'];
            $langName = $track['language_name'];

            // Handle duplicate languages by appending index
            $filename = $langCode;
            if (isset($extracted[$langCode])) {
                $filename = $langCode . '_' . $trackIndex;
            }

            $vttPath = $uploadDir . '/' . $filename . '.vtt';
            $relativePath = '/uploads/vod/' . $movieId . '/subtitles/' . $filename . '.vtt';

            // Extract this subtitle track to VTT
            $cmd = sprintf(
                '%s -y -i %s -map 0:s:%d -c:s webvtt %s 2>&1',
                escapeshellarg($ffmpeg),
                escapeshellarg($sourcePath),
                $trackIndex,
                escapeshellarg($vttPath)
            );

            exec($cmd, $output, $exitCode);

            if ($exitCode === 0 && file_exists($vttPath)) {
                $extracted[$langCode] = [
                    'language_code' => $langCode,
                    'language_name' => $langName,
                    'file_path' => $relativePath,
                    'format' => 'vtt',
                    'source' => 'extracted',
                    'is_forced' => $track['forced'] ? 1 : 0,
                ];
            }

            $trackIndex++;
        }

        if (empty($extracted)) {
            return ['success' => false, 'error' => 'Failed to extract any subtitle tracks.', 'extracted' => []];
        }

        return ['success' => true, 'extracted' => array_values($extracted), 'count' => count($extracted)];
    }

    // -------------------------------------------------------------------------
    // OpenSubtitles API Internals
    // -------------------------------------------------------------------------

    /**
     * Make an authenticated API request to OpenSubtitles
     */
    private function apiRequest(string $method, string $endpoint, ?array $params = null): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        // Ensure we have a valid token for non-login endpoints
        if ($endpoint !== '/login') {
            $this->ensureToken();
        }

        $url = self::OS_API_BASE . $endpoint;

        // For GET requests, append params as query string
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);

        $headers = [
            'Content-Type: application/json',
            'Api-Key: ' . $this->config['api_key'],
            'User-Agent: ' . self::OS_USER_AGENT,
        ];

        // Add auth token if we have one
        $token = $this->config['token'] ?? '';
        if (!empty($token) && $endpoint !== '/login') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($params) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 401) {
            // Token expired, clear and retry once
            $this->clearToken();
            if ($endpoint !== '/login') {
                $this->ensureToken();
                return $this->apiRequestOnce($method, $url, $params);
            }
            return null;
        }

        if ($httpCode >= 400) {
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Single API request without retry logic (to avoid infinite recursion)
     */
    private function apiRequestOnce(string $method, string $url, ?array $params = null): ?array
    {
        $ch = curl_init($url);

        $headers = [
            'Content-Type: application/json',
            'Api-Key: ' . $this->config['api_key'],
            'User-Agent: ' . self::OS_USER_AGENT,
            'Authorization: Bearer ' . ($this->config['token'] ?? ''),
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($params) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Ensure we have a valid API token
     */
    private function ensureToken(): void
    {
        $token = $this->config['token'] ?? '';
        $expires = (int)($this->config['token_expires'] ?? 0);

        // Token still valid (with 5min buffer)
        if (!empty($token) && $expires > time() + 300) {
            return;
        }

        $this->login();
    }

    /**
     * Login to OpenSubtitles API to get a JWT token
     */
    private function login(): bool
    {
        $username = $this->config['username'] ?? '';
        $password = $this->config['password'] ?? '';

        if (empty($username) || empty($password)) {
            // API key only mode (search works, downloads may be limited)
            return false;
        }

        $response = $this->apiRequest('POST', '/login', [
            'username' => $username,
            'password' => $password,
        ]);

        if ($response === null || empty($response['token'])) {
            return false;
        }

        $token = $response['token'];
        $expires = time() + 86400; // 24 hours

        // Save token to settings
        $this->settings->setMany([
            'opensubtitles_token' => $token,
            'opensubtitles_token_expires' => (string)$expires,
        ], 'subtitles');

        $this->config['token'] = $token;
        $this->config['token_expires'] = (string)$expires;

        return true;
    }

    /**
     * Clear cached token
     */
    private function clearToken(): void
    {
        $this->settings->setMany([
            'opensubtitles_token' => '',
            'opensubtitles_token_expires' => '0',
        ], 'subtitles');

        $this->config['token'] = '';
        $this->config['token_expires'] = '0';
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Find FFmpeg binary path
     */
    private function findFfmpeg(): ?string
    {
        $paths = ['/usr/local/bin/ffmpeg', '/usr/bin/ffmpeg'];
        foreach ($paths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }
        // Try which
        $result = trim(shell_exec('which ffmpeg 2>/dev/null') ?? '');
        return !empty($result) ? $result : null;
    }

    /**
     * Find FFprobe binary path
     */
    private function findFfprobe(): ?string
    {
        $paths = ['/usr/local/bin/ffprobe', '/usr/bin/ffprobe'];
        foreach ($paths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }
        $result = trim(shell_exec('which ffprobe 2>/dev/null') ?? '');
        return !empty($result) ? $result : null;
    }
}
