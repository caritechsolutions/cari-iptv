<?php
/**
 * CARI-IPTV EPG Management Controller
 */

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Database;
use CariIPTV\Core\Response;
use CariIPTV\Core\Session;
use CariIPTV\Services\AdminAuthService;
use CariIPTV\Services\EpgService;

class EpgController
{
    private Database $db;
    private AdminAuthService $auth;
    private EpgService $epgService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = new AdminAuthService();
        $this->epgService = new EpgService();
    }

    /**
     * EPG management page
     */
    public function index(): void
    {
        $sources = $this->epgService->getSources();
        $stats = $this->epgService->getStatistics();

        Response::view('admin/epg/index', [
            'pageTitle' => 'EPG',
            'sources' => $sources,
            'stats' => $stats,
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Add new EPG source (AJAX)
     */
    public function store(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? '';

        if (empty($name)) {
            $this->sendJson(['success' => false, 'message' => 'Source name is required']);
            return;
        }

        $validTypes = ['eit', 'xmltv_file', 'xmltv_url'];
        if (!in_array($type, $validTypes)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid source type']);
            return;
        }

        $data = [
            'name' => $name,
            'type' => $type,
            'source_url' => trim($_POST['source_url'] ?? '') ?: null,
            'source_port' => !empty($_POST['source_port']) ? (int) $_POST['source_port'] : null,
            'eit_pid' => trim($_POST['eit_pid'] ?? '') ?: '0x12',
            'capture_timeout' => (int) ($_POST['capture_timeout'] ?? 30),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'auto_refresh' => isset($_POST['auto_refresh']) ? 1 : 0,
            'refresh_interval' => (int) ($_POST['refresh_interval'] ?? 3600),
        ];

        try {
            $id = $this->epgService->createSource($data);

            $this->auth->logActivity(
                $this->auth->id(), 'create', 'epg', 'epg_source', $id,
                ['name' => $name, 'type' => $type]
            );

            $this->sendJson(['success' => true, 'message' => 'EPG source created', 'id' => $id]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'message' => 'Failed to create source: ' . $e->getMessage()]);
        }
    }

    /**
     * Update EPG source (AJAX)
     */
    public function update(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $source = $this->epgService->getSource($id);
        if (!$source) {
            $this->sendJson(['success' => false, 'message' => 'Source not found']);
            return;
        }

        $data = [
            'name' => trim($_POST['name'] ?? $source['name']),
            'source_url' => trim($_POST['source_url'] ?? '') ?: null,
            'source_port' => !empty($_POST['source_port']) ? (int) $_POST['source_port'] : null,
            'eit_pid' => trim($_POST['eit_pid'] ?? '') ?: '0x12',
            'capture_timeout' => (int) ($_POST['capture_timeout'] ?? 30),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'auto_refresh' => isset($_POST['auto_refresh']) ? 1 : 0,
            'refresh_interval' => (int) ($_POST['refresh_interval'] ?? 3600),
        ];

        try {
            $this->epgService->updateSource($id, $data);
            $this->sendJson(['success' => true, 'message' => 'Source updated']);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'message' => 'Failed to update: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete EPG source (AJAX)
     */
    public function delete(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $source = $this->epgService->getSource($id);
        if (!$source) {
            $this->sendJson(['success' => false, 'message' => 'Source not found']);
            return;
        }

        $this->epgService->deleteSource($id);

        $this->auth->logActivity(
            $this->auth->id(), 'delete', 'epg', 'epg_source', $id,
            ['name' => $source['name']]
        );

        $this->sendJson(['success' => true, 'message' => 'Source deleted']);
    }

    /**
     * Trigger EPG fetch for a source (AJAX)
     */
    public function fetch(int $id): void
    {
        // EIT capture can take 30-120 seconds, disable PHP time limit
        set_time_limit(0);
        ignore_user_abort(true);

        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $source = $this->epgService->getSource($id);
        if (!$source) {
            $this->sendJson(['success' => false, 'message' => 'Source not found']);
            return;
        }

        if ($source['type'] === 'eit') {
            $this->epgService->extractEit($source);
        } elseif ($source['type'] === 'xmltv_url') {
            $this->fetchXmltvUrl($source);
        } elseif ($source['type'] === 'xmltv_file') {
            $this->sendJson(['success' => false, 'message' => 'Use the upload function for file-based sources']);
            return;
        }

        $updated = $this->epgService->getSource($id);

        $this->sendJson([
            'success' => $updated['last_status'] === 'success',
            'message' => $updated['last_message'] ?? 'Fetch completed',
            'programme_count' => (int) $updated['programme_count'],
            'channel_count' => (int) $updated['channel_count'],
            'last_fetch' => $updated['last_fetch'],
        ]);
    }

    /**
     * Upload XMLTV file for a source (AJAX)
     */
    public function upload(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $source = $this->epgService->getSource($id);
        if (!$source) {
            $this->sendJson(['success' => false, 'message' => 'Source not found']);
            return;
        }

        if (empty($_FILES['xmltv_file']) || $_FILES['xmltv_file']['error'] !== UPLOAD_ERR_OK) {
            $this->sendJson(['success' => false, 'message' => 'No file uploaded or upload error']);
            return;
        }

        $file = $_FILES['xmltv_file'];
        $tmpPath = $file['tmp_name'];

        // Handle .gz files
        $name = strtolower($file['name']);
        if (str_ends_with($name, '.gz')) {
            $data = file_get_contents($tmpPath);
            $decoded = gzdecode($data);
            if ($decoded === false) {
                $this->sendJson(['success' => false, 'message' => 'Failed to decompress gzip file']);
                return;
            }
            file_put_contents($tmpPath, $decoded);
        }

        // Validate it's XML
        $firstBytes = file_get_contents($tmpPath, false, null, 0, 100);
        if (!str_contains($firstBytes, '<?xml') && !str_contains($firstBytes, '<tv')) {
            $this->sendJson(['success' => false, 'message' => 'File does not appear to be valid XMLTV']);
            return;
        }

        $this->epgService->updateSourceStatus($id, 'running', 'Importing uploaded XMLTV...');

        $result = $this->epgService->importXmltvFile($id, $tmpPath);

        if ($result['success']) {
            $this->epgService->updateSourceStatus($id, 'success',
                "Imported {$result['programmes']} programmes for {$result['channels']} channels");
        } else {
            $this->epgService->updateSourceStatus($id, 'error', $result['message']);
        }

        $this->sendJson([
            'success' => $result['success'],
            'message' => $result['success']
                ? "Imported {$result['programmes']} programmes for {$result['channels']} channels"
                : ($result['message'] ?? 'Import failed'),
            'programme_count' => $result['programmes'] ?? 0,
            'channel_count' => $result['channels'] ?? 0,
        ]);
    }

    /**
     * Get channel mappings for a source (AJAX)
     */
    public function mappings(int $id): void
    {
        $source = $this->epgService->getSource($id);
        if (!$source) {
            $this->sendJson(['success' => false, 'message' => 'Source not found']);
            return;
        }

        $mappings = $this->epgService->getChannelMappings($id);

        // Get all channels for the mapping dropdown
        $channels = $this->db->fetchAll(
            "SELECT id, name FROM channels WHERE is_active = 1 ORDER BY name"
        );

        $this->sendJson([
            'success' => true,
            'source' => $source,
            'mappings' => $mappings,
            'channels' => $channels,
        ]);
    }

    /**
     * Save a single channel mapping (AJAX)
     */
    public function saveMapping(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $mappingId = (int) ($_POST['mapping_id'] ?? 0);
        $channelId = !empty($_POST['channel_id']) ? (int) $_POST['channel_id'] : null;

        if (!$mappingId) {
            $this->sendJson(['success' => false, 'message' => 'Invalid mapping ID']);
            return;
        }

        $this->epgService->mapChannel($mappingId, $channelId);
        $this->sendJson(['success' => true, 'message' => 'Mapping saved']);
    }

    /**
     * Auto-map channels for a source (AJAX)
     */
    public function autoMap(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $mapped = $this->epgService->autoMapChannels($id);
        $this->sendJson([
            'success' => true,
            'message' => "Auto-mapped {$mapped} channel(s)",
            'mapped' => $mapped,
        ]);
    }

    /**
     * Clear old programmes (AJAX)
     */
    public function cleanup(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $deleted = $this->epgService->clearOldProgrammes(1);
        $this->sendJson([
            'success' => true,
            'message' => "Removed {$deleted} expired programme(s)",
        ]);
    }

    /**
     * Get programme listings (AJAX)
     */
    public function programmes(): void
    {
        $filters = [
            'channel_id' => $_GET['channel_id'] ?? null,
            'source_id' => $_GET['source_id'] ?? null,
            'date' => $_GET['date'] ?? null,
            'now' => isset($_GET['now']) && $_GET['now'] === '1',
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? 50,
        ];

        $result = $this->epgService->getProgrammes($filters);

        // Get available channels and sources for filter dropdowns
        $channels = $this->db->fetchAll(
            "SELECT DISTINCT c.id, c.name FROM channels c
             INNER JOIN epg_programs p ON p.channel_id = c.id
             ORDER BY c.name"
        );

        $sources = $this->db->fetchAll(
            "SELECT id, name FROM epg_sources ORDER BY name"
        );

        $this->sendJson([
            'success' => true,
            'programmes' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'total_pages' => $result['total_pages'],
            'channels' => $channels,
            'sources' => $sources,
        ]);
    }

    // ========================================================================
    // HELPERS
    // ========================================================================

    private function fetchXmltvUrl(array $source): void
    {
        $url = $source['source_url'];
        if (empty($url)) {
            $this->epgService->updateSourceStatus($source['id'], 'error', 'No URL configured');
            return;
        }

        $this->epgService->updateSourceStatus($source['id'], 'running', 'Downloading XMLTV...');

        $tmpFile = tempnam(sys_get_temp_dir(), 'xmltv_');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'CARI-IPTV/1.0',
        ]);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($data)) {
            $this->epgService->updateSourceStatus($source['id'], 'error', "Download failed (HTTP {$httpCode})");
            if (file_exists($tmpFile)) unlink($tmpFile);
            return;
        }

        if (str_ends_with($url, '.gz') || str_starts_with($data, "\x1f\x8b")) {
            $data = gzdecode($data);
        }

        file_put_contents($tmpFile, $data);

        $result = $this->epgService->importXmltvFile($source['id'], $tmpFile);
        unlink($tmpFile);

        if ($result['success']) {
            $this->epgService->updateSourceStatus($source['id'], 'success',
                "Imported {$result['programmes']} programmes for {$result['channels']} channels");
        } else {
            $this->epgService->updateSourceStatus($source['id'], 'error', $result['message']);
        }
    }

    private function sendJson(array $data): void
    {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
