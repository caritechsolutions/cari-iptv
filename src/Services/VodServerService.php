<?php
/**
 * CARI-IPTV VOD Server Service
 * HTTP client for communicating with the VOD Server API
 */

namespace CariIPTV\Services;

class VodServerService
{
    private string $baseUrl;
    private string $apiKey;
    private bool $enabled;
    private int $timeout;

    public function __construct()
    {
        $settings = new SettingsService();
        $this->baseUrl = rtrim($settings->get('vod_server_url', '', 'vod_server'), '/');
        $this->apiKey = $settings->get('vod_server_api_key', '', 'vod_server');
        $this->enabled = (bool) $settings->get('vod_server_enabled', false, 'vod_server');
        $this->timeout = (int) $settings->get('vod_server_timeout', 30, 'vod_server');
    }

    /**
     * Check if VOD server integration is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->apiKey);
    }

    /**
     * Check if VOD server integration is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->isConfigured();
    }

    /**
     * Get server status
     */
    public function getStatus(): array
    {
        return $this->request('GET', '/api/status');
    }

    /**
     * Get server configuration
     */
    public function getConfig(): array
    {
        return $this->request('GET', '/api/config');
    }

    /**
     * List content in the VOD server library
     */
    public function getContent(array $filters = []): array
    {
        $query = http_build_query(array_filter([
            'status' => $filters['status'] ?? null,
            'search' => $filters['search'] ?? null,
            'limit'  => $filters['limit'] ?? 50,
            'offset' => $filters['offset'] ?? 0,
        ], fn($v) => $v !== null));

        return $this->request('GET', '/api/content' . ($query ? "?{$query}" : ''));
    }

    /**
     * Get single content item details
     */
    public function getContentDetail(string $contentId): array
    {
        return $this->request('GET', '/api/content/' . urlencode($contentId));
    }

    /**
     * Delete content from VOD server
     */
    public function deleteContent(string $contentId): array
    {
        return $this->request('DELETE', '/api/content/' . urlencode($contentId));
    }

    /**
     * Submit a new transcode job
     */
    public function submitJob(string $contentId, string $sourcePath, array $options = []): array
    {
        $body = [
            'content_id'  => $contentId,
            'source_path' => $sourcePath,
            'title'       => $options['title'] ?? $contentId,
            'source_type' => $options['source_type'] ?? 'file',
            'profile'     => $options['profile'] ?? 'standard',
            'priority'    => $options['priority'] ?? 5,
        ];

        if (!empty($options['callback_url'])) {
            $body['callback_url'] = $options['callback_url'];
        }

        if (!empty($options['metadata'])) {
            $body['metadata'] = $options['metadata'];
        }

        return $this->request('POST', '/api/jobs', $body);
    }

    /**
     * List transcode jobs
     */
    public function getJobs(array $filters = []): array
    {
        $query = http_build_query(array_filter([
            'status' => $filters['status'] ?? null,
            'limit'  => $filters['limit'] ?? 50,
            'offset' => $filters['offset'] ?? 0,
        ], fn($v) => $v !== null));

        return $this->request('GET', '/api/jobs' . ($query ? "?{$query}" : ''));
    }

    /**
     * Get single job details
     */
    public function getJob(int $jobId): array
    {
        return $this->request('GET', '/api/jobs/' . $jobId);
    }

    /**
     * Cancel/delete a job
     */
    public function cancelJob(int $jobId): array
    {
        return $this->request('DELETE', '/api/jobs/' . $jobId);
    }

    /**
     * Browse filesystem on the VOD server
     */
    public function browse(string $path = '/'): array
    {
        return $this->request('GET', '/api/browse?path=' . urlencode($path));
    }

    /**
     * Test connection to the VOD server
     */
    public function testConnection(?string $url = null, ?string $apiKey = null): array
    {
        $origUrl = $this->baseUrl;
        $origKey = $this->apiKey;

        if ($url !== null) $this->baseUrl = rtrim($url, '/');
        if ($apiKey !== null) $this->apiKey = $apiKey;

        try {
            $result = $this->request('GET', '/api/status');
            $this->baseUrl = $origUrl;
            $this->apiKey = $origKey;

            return [
                'success' => true,
                'version' => $result['version'] ?? 'Unknown',
                'node_name' => $result['node_name'] ?? 'Unknown',
                'uptime' => $result['uptime'] ?? 'Unknown',
                'content_count' => $result['content_count'] ?? 0,
                'active_jobs' => $result['active_jobs'] ?? 0,
            ];
        } catch (\Exception $e) {
            $this->baseUrl = $origUrl;
            $this->apiKey = $origKey;

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build the HLS stream URL for a content item
     */
    public function getStreamUrl(string $contentId, string $format = 'hls'): string
    {
        $ext = $format === 'dash' ? 'manifest.mpd' : 'master.m3u8';
        return $this->baseUrl . '/content/' . urlencode($contentId) . '/' . $ext;
    }

    /**
     * Make an HTTP request to the VOD Server API
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        if (empty($this->baseUrl)) {
            throw new \RuntimeException('VOD Server URL not configured');
        }

        $url = $this->baseUrl . $path;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $headers = ['Content-Type: application/json'];
        if (!empty($this->apiKey)) {
            $headers[] = 'X-API-Key: ' . $this->apiKey;
        }

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($body !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($body !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
                }
                break;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno) {
            throw new \RuntimeException('Connection failed: ' . $error);
        }

        if ($httpCode === 0) {
            throw new \RuntimeException('Could not connect to VOD Server at ' . $this->baseUrl);
        }

        $data = json_decode($response, true);
        if ($data === null && !empty($response)) {
            throw new \RuntimeException('Invalid response from VOD Server (HTTP ' . $httpCode . ')');
        }

        if ($httpCode >= 400) {
            $msg = $data['error'] ?? $data['message'] ?? 'HTTP ' . $httpCode;
            throw new \RuntimeException($msg);
        }

        return $data ?? [];
    }
}
