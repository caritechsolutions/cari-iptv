<?php
/**
 * CARI-IPTV VOD Server Controller
 * Manages VOD Server integration - multi-server, status, content, jobs, transcoding
 */

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Response;
use CariIPTV\Core\Session;
use CariIPTV\Services\VodServerService;

class VodServerController
{
    private VodServerService $vodService;

    public function __construct()
    {
        $this->vodService = new VodServerService();
    }

    /**
     * Redirect to settings integrations tab (VOD servers are managed there now)
     */
    public function index(): void
    {
        Response::redirect('/admin/settings?tab=integrations');
    }

    /* ==============================================================
     * Server CRUD (AJAX)
     * ============================================================== */

    public function listServers(): void
    {
        $this->sendJson(['success' => true, 'servers' => $this->vodService->getServers()]);
    }

    public function addServer(): void
    {
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $name   = trim($_POST['name'] ?? '');
        $url    = trim($_POST['url'] ?? '');
        $apiKey = trim($_POST['api_key'] ?? '');

        if (empty($name) || empty($url)) {
            $this->sendJson(['success' => false, 'error' => 'Name and URL are required']);
            return;
        }

        try {
            $id = $this->vodService->createServer([
                'name'       => $name,
                'url'        => $url,
                'api_key'    => $apiKey,
                'is_default' => !empty($_POST['is_default']),
                'notes'      => trim($_POST['notes'] ?? ''),
            ]);
            $this->sendJson(['success' => true, 'message' => 'Server added', 'id' => $id]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function updateServerRecord(int $id): void
    {
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $url  = trim($_POST['url'] ?? '');

        if (empty($name) || empty($url)) {
            $this->sendJson(['success' => false, 'error' => 'Name and URL are required']);
            return;
        }

        try {
            $this->vodService->updateServer($id, [
                'name'       => $name,
                'url'        => $url,
                'api_key'    => trim($_POST['api_key'] ?? ''),
                'is_active'  => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1,
                'is_default' => !empty($_POST['is_default']),
                'notes'      => trim($_POST['notes'] ?? ''),
            ]);
            $this->sendJson(['success' => true, 'message' => 'Server updated']);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function deleteServer(int $id): void
    {
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        try {
            $this->vodService->deleteServer($id);
            $this->sendJson(['success' => true, 'message' => 'Server removed']);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /* ==============================================================
     * Server API proxy (all require ?server_id= param)
     * ============================================================== */

    private function getServerId(): int
    {
        return (int)($_GET['server_id'] ?? $_POST['server_id'] ?? 0);
    }

    public function status(): void
    {
        try {
            $sid = $this->getServerId();
            if ($sid <= 0) {
                $this->sendJson(['success' => false, 'error' => 'No server selected']);
                return;
            }
            $status = $this->vodService->getStatus($sid);
            $this->sendJson(['success' => true, 'status' => $status]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function config(): void
    {
        try {
            $sid = $this->getServerId();
            if ($sid <= 0) { $this->sendJson(['success' => false, 'error' => 'No server selected']); return; }
            $config = $this->vodService->getConfig($sid);
            $this->sendJson(['success' => true, 'config' => $config]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function content(): void
    {
        try {
            $sid = $this->getServerId();
            if ($sid <= 0) { $this->sendJson(['success' => false, 'error' => 'No server selected']); return; }
            $result = $this->vodService->getContent($sid, [
                'status' => $_GET['status'] ?? null,
                'search' => $_GET['search'] ?? null,
                'limit'  => (int)($_GET['limit'] ?? 25),
                'offset' => (int)($_GET['offset'] ?? 0),
            ]);
            $this->sendJson(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function deleteContent(): void
    {
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }
        try {
            $sid = (int)($_POST['server_id'] ?? 0);
            $contentId = trim($_POST['content_id'] ?? '');
            if ($sid <= 0 || empty($contentId)) {
                $this->sendJson(['success' => false, 'error' => 'Server ID and Content ID required']);
                return;
            }
            $this->vodService->deleteContentItem($sid, $contentId);
            $this->sendJson(['success' => true, 'message' => 'Content deleted']);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function jobs(): void
    {
        try {
            $sid = $this->getServerId();
            if ($sid <= 0) { $this->sendJson(['success' => false, 'error' => 'No server selected']); return; }
            $result = $this->vodService->getJobs($sid, [
                'status' => $_GET['status'] ?? null,
                'limit'  => (int)($_GET['limit'] ?? 25),
                'offset' => (int)($_GET['offset'] ?? 0),
            ]);
            $this->sendJson(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function jobDetail(): void
    {
        try {
            $sid = $this->getServerId();
            $jobId = (int)($_GET['job_id'] ?? 0);
            if ($sid <= 0 || $jobId <= 0) {
                $this->sendJson(['success' => false, 'error' => 'Server ID and Job ID required']);
                return;
            }
            $detail = $this->vodService->getJob($sid, $jobId);
            $this->sendJson(['success' => true, 'job' => $detail]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function submitJob(): void
    {
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }
        try {
            $sid = (int)($_POST['server_id'] ?? 0);
            $contentId  = trim($_POST['content_id'] ?? '');
            $sourcePath = trim($_POST['source_path'] ?? '');

            if ($sid <= 0) { $this->sendJson(['success' => false, 'error' => 'No server selected']); return; }
            if (empty($contentId)) { $this->sendJson(['success' => false, 'error' => 'Content ID is required']); return; }
            if (empty($sourcePath)) { $this->sendJson(['success' => false, 'error' => 'Source path is required']); return; }

            $result = $this->vodService->submitJob($sid, $contentId, $sourcePath, [
                'title'       => trim($_POST['title'] ?? '') ?: $contentId,
                'profile'     => trim($_POST['profile'] ?? 'standard'),
                'priority'    => max(1, min(10, (int)($_POST['priority'] ?? 5))),
                'source_type' => trim($_POST['source_type'] ?? 'file'),
            ]);

            $this->sendJson(['success' => true, 'message' => 'Job submitted', 'job' => $result['job'] ?? $result]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function cancelJob(int $id): void
    {
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }
        try {
            $sid = (int)($_POST['server_id'] ?? 0);
            if ($sid <= 0) { $this->sendJson(['success' => false, 'error' => 'No server selected']); return; }
            $this->vodService->cancelJob($sid, $id);
            $this->sendJson(['success' => true, 'message' => 'Job cancelled']);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function browseFiles(): void
    {
        try {
            $sid = $this->getServerId();
            if ($sid <= 0) { $this->sendJson(['success' => false, 'error' => 'No server selected']); return; }
            $result = $this->vodService->browse($sid, $_GET['path'] ?? '/');
            $this->sendJson(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function testConnection(): void
    {
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }
        $url = trim($_POST['url'] ?? '');
        $apiKey = trim($_POST['api_key'] ?? '');
        if (empty($url)) { $this->sendJson(['success' => false, 'error' => 'URL is required']); return; }
        $this->sendJson($this->vodService->testConnection($url, $apiKey));
    }

    /**
     * Upload a video file from the user's computer for VOD transcoding.
     * Saves to public/uploads/vod-source/ and returns a URL the VOD server can download.
     */
    public function uploadSource(): void
    {
        // Extend execution time for large uploads
        @ini_set('max_execution_time', '600');
        @ini_set('max_input_time', '600');

        // Detect if PHP dropped the request body due to post_max_size being exceeded.
        // When this happens, both $_POST and $_FILES will be empty.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
            $maxSize = ini_get('post_max_size');
            $this->sendJson([
                'success' => false,
                'error' => "File too large. Server limit is {$maxSize}. Ask your administrator to increase upload_max_filesize and post_max_size in PHP config."
            ]);
            return;
        }

        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        if (empty($_FILES['video_file']['tmp_name']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
            $errCode = $_FILES['video_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            $maxSize = ini_get('upload_max_filesize');
            $errMsgs = [
                UPLOAD_ERR_INI_SIZE   => "File exceeds the server upload limit ({$maxSize}). Increase upload_max_filesize in PHP config.",
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form upload limit',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE    => 'No file was selected',
                UPLOAD_ERR_NO_TMP_DIR => 'Server temp directory missing',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            ];
            $this->sendJson(['success' => false, 'error' => $errMsgs[$errCode] ?? 'Upload failed (code ' . $errCode . ')']);
            return;
        }

        $file = $_FILES['video_file'];
        $allowedExts = ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'ts', 'm2ts', 'mpg', 'mpeg', 'm4v', 'ogg', '3gp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts)) {
            $this->sendJson(['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowedExts)]);
            return;
        }

        // Create upload directory
        $uploadDir = dirname(__DIR__, 3) . '/public/uploads/vod-source';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }
        if (!is_writable($uploadDir)) {
            $this->sendJson(['success' => false, 'error' => 'Upload directory is not writable']);
            return;
        }

        // Generate unique filename
        $filename = 'vod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $this->sendJson(['success' => false, 'error' => 'Failed to save uploaded file']);
            return;
        }

        // Build the download URL that the VOD server can reach
        $settings = new \CariIPTV\Services\SettingsService();
        $siteUrl = rtrim($settings->get('site_url', '', 'general'), '/');

        // If site_url not configured, try to construct from server info
        if (empty($siteUrl)) {
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $siteUrl = $proto . '://' . $host;
        }

        $downloadUrl = $siteUrl . '/uploads/vod-source/' . $filename;

        $this->sendJson([
            'success'      => true,
            'message'      => 'File uploaded successfully',
            'filename'     => $file['name'],
            'size'         => $file['size'],
            'download_url' => $downloadUrl,
            'local_path'   => '/uploads/vod-source/' . $filename,
        ]);
    }

    private function sendJson(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
