<?php
/**
 * CARI-IPTV VOD Server Service
 * Manages VOD server records and communicates with the VOD Server API
 */

namespace CariIPTV\Services;

use CariIPTV\Core\Database;

class VodServerService
{
    private Database $db;
    private int $timeout = 30;
    private static bool $tableChecked = false;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTable();
    }

    /**
     * Ensure the vod_servers table exists (auto-create if migration hasn't run)
     */
    private function ensureTable(): void
    {
        if (self::$tableChecked) return;
        self::$tableChecked = true;

        try {
            $this->db->fetch("SELECT 1 FROM vod_servers LIMIT 1");
        } catch (\Exception $e) {
            $this->db->execute("
                CREATE TABLE IF NOT EXISTS `vod_servers` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(100) NOT NULL,
                    `url` VARCHAR(500) NOT NULL,
                    `api_key` VARCHAR(256) NOT NULL DEFAULT '',
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `notes` TEXT DEFAULT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_active` (`is_active`),
                    INDEX `idx_default` (`is_default`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    /* ==============================================================
     * Server CRUD (multi-server management)
     * ============================================================== */

    /**
     * Get all VOD servers
     */
    public function getServers(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM vod_servers ORDER BY sort_order ASC, name ASC"
        ) ?: [];
    }

    /**
     * Get active VOD servers only
     */
    public function getActiveServers(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM vod_servers WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
        ) ?: [];
    }

    /**
     * Get a single server by ID
     */
    public function getServer(int $id): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM vod_servers WHERE id = ?", [$id]
        ) ?: null;
    }

    /**
     * Get the default server
     */
    public function getDefaultServer(): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM vod_servers WHERE is_default = 1 AND is_active = 1 LIMIT 1"
        ) ?: $this->db->fetch(
            "SELECT * FROM vod_servers WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 1"
        ) ?: null;
    }

    /**
     * Create a new server
     */
    public function createServer(array $data): int
    {
        $count = $this->db->fetch("SELECT COUNT(*) as cnt FROM vod_servers")['cnt'] ?? 0;
        $isDefault = !empty($data['is_default']) || $count == 0;

        if ($isDefault) {
            $this->db->execute("UPDATE vod_servers SET is_default = 0");
        }

        $this->db->insert('vod_servers', [
            'name'       => $data['name'],
            'url'        => rtrim($data['url'], '/'),
            'api_key'    => $data['api_key'] ?? '',
            'is_active'  => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'is_default' => $isDefault ? 1 : 0,
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'notes'      => $data['notes'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update an existing server
     */
    public function updateServer(int $id, array $data): void
    {
        if (!empty($data['is_default'])) {
            $this->db->execute("UPDATE vod_servers SET is_default = 0");
        }

        $this->db->update('vod_servers', [
            'name'       => $data['name'],
            'url'        => rtrim($data['url'], '/'),
            'api_key'    => $data['api_key'] ?? '',
            'is_active'  => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'is_default' => !empty($data['is_default']) ? 1 : 0,
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'notes'      => $data['notes'] ?? null,
        ], 'id = ?', [$id]);
    }

    /**
     * Delete a server
     */
    public function deleteServer(int $id): void
    {
        $this->db->execute("DELETE FROM vod_servers WHERE id = ?", [$id]);
    }

    /* ==============================================================
     * API Communication (per-server)
     * ============================================================== */

    public function getStatus(int $serverId): array
    {
        $server = $this->requireServer($serverId);
        return $this->request($server, 'GET', '/api/status');
    }

    public function getConfig(int $serverId): array
    {
        $server = $this->requireServer($serverId);
        return $this->request($server, 'GET', '/api/config');
    }

    public function getContent(int $serverId, array $filters = []): array
    {
        $server = $this->requireServer($serverId);
        $query = http_build_query(array_filter([
            'status' => $filters['status'] ?? null,
            'search' => $filters['search'] ?? null,
            'limit'  => $filters['limit'] ?? 50,
            'offset' => $filters['offset'] ?? 0,
        ], fn($v) => $v !== null));
        return $this->request($server, 'GET', '/api/content' . ($query ? "?{$query}" : ''));
    }

    public function getContentDetail(int $serverId, string $contentId): array
    {
        $server = $this->requireServer($serverId);
        return $this->request($server, 'GET', '/api/content/' . urlencode($contentId));
    }

    public function deleteContentItem(int $serverId, string $contentId): array
    {
        $server = $this->requireServer($serverId);
        return $this->request($server, 'DELETE', '/api/content/' . urlencode($contentId));
    }

    public function submitJob(int $serverId, string $contentId, string $sourcePath, array $options = []): array
    {
        $server = $this->requireServer($serverId);
        return $this->request($server, 'POST', '/api/jobs', [
            'content_id'  => $contentId,
            'source_path' => $sourcePath,
            'title'       => $options['title'] ?? $contentId,
            'source_type' => $options['source_type'] ?? 'file',
            'profile'     => $options['profile'] ?? 'standard',
            'priority'    => $options['priority'] ?? 5,
        ]);
    }

    public function getJobs(int $serverId, array $filters = []): array
    {
        $server = $this->requireServer($serverId);
        $query = http_build_query(array_filter([
            'status' => $filters['status'] ?? null,
            'limit'  => $filters['limit'] ?? 50,
            'offset' => $filters['offset'] ?? 0,
        ], fn($v) => $v !== null));
        return $this->request($server, 'GET', '/api/jobs' . ($query ? "?{$query}" : ''));
    }

    public function getJob(int $serverId, int $jobId): array
    {
        $server = $this->requireServer($serverId);
        return $this->request($server, 'GET', '/api/jobs/' . $jobId);
    }

    public function cancelJob(int $serverId, int $jobId): array
    {
        $server = $this->requireServer($serverId);
        return $this->request($server, 'DELETE', '/api/jobs/' . $jobId);
    }

    public function browse(int $serverId, string $path = '/'): array
    {
        $server = $this->requireServer($serverId);
        return $this->request($server, 'GET', '/api/browse?path=' . urlencode($path));
    }

    /**
     * Test connection to a VOD server
     * First checks /api/status (public), then verifies API key via /api/config (authenticated)
     */
    public function testConnection(?string $url = null, ?string $apiKey = null, ?int $serverId = null): array
    {
        if ($serverId) {
            $server = $this->getServer($serverId);
            if (!$server) return ['success' => false, 'error' => 'Server not found'];
        } elseif ($url) {
            $server = ['url' => rtrim($url, '/'), 'api_key' => $apiKey ?? ''];
        } else {
            return ['success' => false, 'error' => 'URL or server ID required'];
        }

        try {
            // Step 1: Check basic connectivity via public /api/status
            $result = $this->request($server, 'GET', '/api/status');
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Cannot connect: ' . $e->getMessage()];
        }

        try {
            // Step 2: Verify API key by calling an authenticated endpoint
            $this->request($server, 'GET', '/api/config');
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Unauthorized') !== false || stripos($msg, 'API key') !== false || stripos($msg, '401') !== false) {
                return ['success' => false, 'error' => 'Server reachable but API key is invalid or missing. Check your API key.'];
            }
            return ['success' => false, 'error' => 'Auth check failed: ' . $msg];
        }

        return [
            'success'       => true,
            'version'       => $result['version'] ?? 'Unknown',
            'node_name'     => $result['node_name'] ?? 'Unknown',
            'uptime'        => $result['uptime'] ?? 'Unknown',
            'content_count' => $result['content_count'] ?? 0,
            'active_jobs'   => $result['active_jobs'] ?? 0,
        ];
    }

    public function getStreamUrl(int $serverId, string $contentId, string $format = 'hls'): string
    {
        $server = $this->getServer($serverId);
        if (!$server) return '';
        $ext = $format === 'dash' ? 'manifest.mpd' : 'master.m3u8';
        return $server['url'] . '/content/' . urlencode($contentId) . '/' . $ext;
    }

    /**
     * Upload a file to the VOD server via streaming PUT/POST
     * Proxies the local temp file to VOD server's /api/upload endpoint
     */
    public function uploadFile(int $serverId, string $localPath, string $filename): array
    {
        $server = $this->requireServer($serverId);
        $baseUrl = $server['url'] ?? '';
        $apiKey  = $server['api_key'] ?? '';

        if (empty($baseUrl)) {
            throw new \RuntimeException('VOD Server URL not configured');
        }

        $fileSize = filesize($localPath);
        $fh = fopen($localPath, 'rb');
        if (!$fh) {
            throw new \RuntimeException('Cannot open local file for reading');
        }

        $url = $baseUrl . '/api/upload?filename=' . urlencode($filename);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_INFILE         => $fh,
            CURLOPT_INFILESIZE     => $fileSize,
            CURLOPT_TIMEOUT        => 0,          // No timeout for large files
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER     => array_filter([
                'Content-Type: application/octet-stream',
                'Content-Length: ' . $fileSize,
                'Transfer-Encoding:',  // Disable chunked
                !empty($apiKey) ? 'X-API-Key: ' . $apiKey : null,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        fclose($fh);

        if ($errno) throw new \RuntimeException('Upload failed: ' . $error);
        if ($httpCode >= 400) {
            $data = json_decode($response, true);
            throw new \RuntimeException($data['error'] ?? 'Upload failed (HTTP ' . $httpCode . ')');
        }

        $data = json_decode($response, true);
        if (!$data) {
            throw new \RuntimeException('Invalid response from VOD server');
        }

        return $data;
    }

    /**
     * Check if content_id already exists on a VOD server
     */
    public function contentExists(int $serverId, string $contentId): bool
    {
        try {
            $server = $this->requireServer($serverId);
            $result = $this->request($server, 'GET', '/api/content/' . urlencode($contentId));
            return !empty($result['content_id']) || !empty($result['content']);
        } catch (\Exception $e) {
            // 404 = not found = doesn't exist
            return false;
        }
    }

    /* ==============================================================
     * Internal helpers
     * ============================================================== */

    private function requireServer(int $serverId): array
    {
        $server = $this->getServer($serverId);
        if (!$server) throw new \RuntimeException('VOD Server not found');
        return $server;
    }

    private function request(array $server, string $method, string $path, ?array $body = null): array
    {
        $baseUrl = $server['url'] ?? '';
        $apiKey  = $server['api_key'] ?? '';

        if (empty($baseUrl)) {
            throw new \RuntimeException('VOD Server URL not configured');
        }

        $url = $baseUrl . $path;
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
        if (!empty($apiKey)) {
            $headers[] = 'X-API-Key: ' . $apiKey;
        }

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
                break;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno) throw new \RuntimeException('Connection failed: ' . $error);
        if ($httpCode === 0) throw new \RuntimeException('Could not connect to VOD Server at ' . $baseUrl);

        // Try to parse JSON response — strip any stray bytes
        $clean = $response;
        if ($clean !== null && $clean !== '') {
            // Remove BOM, null bytes, and control characters that break json_decode
            $clean = ltrim($clean, "\xEF\xBB\xBF");
            $clean = trim($clean);
            // Find the JSON object/array boundaries in case of surrounding garbage
            $start = strpos($clean, '{');
            if ($start === false) $start = strpos($clean, '[');
            if ($start !== false) {
                $clean = substr($clean, $start);
            }
        }

        $data = json_decode($clean, true);

        // Check for HTTP errors
        if ($httpCode >= 400) {
            $errMsg = $data['error'] ?? $data['message'] ?? 'HTTP ' . $httpCode;
            throw new \RuntimeException($errMsg);
        }

        // For 2xx (200, 201, 204, etc.) — return parsed data or success placeholder
        if ($httpCode >= 200 && $httpCode < 300) {
            return $data ?? ['success' => true];
        }

        return $data ?? [];
    }
}
