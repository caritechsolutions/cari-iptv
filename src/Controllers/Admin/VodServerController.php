<?php
/**
 * CARI-IPTV VOD Server Controller
 * Manages VOD Server integration - status, content, jobs, transcoding
 */

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Response;
use CariIPTV\Core\Session;
use CariIPTV\Services\VodServerService;
use CariIPTV\Services\SettingsService;

class VodServerController
{
    private VodServerService $vodService;
    private SettingsService $settings;

    public function __construct()
    {
        $this->vodService = new VodServerService();
        $this->settings = new SettingsService();
    }

    /**
     * Main VOD Server page
     */
    public function index(): void
    {
        $vodSettings = $this->settings->getGroup('vod_server');

        Response::view('admin/vod-server/index', [
            'title' => 'VOD Server',
            'vodSettings' => $vodSettings,
            'isConfigured' => $this->vodService->isConfigured(),
            'isEnabled' => $this->vodService->isEnabled(),
        ]);
    }

    /**
     * AJAX: Get server status
     */
    public function status(): void
    {
        try {
            if (!$this->vodService->isConfigured()) {
                $this->sendJson(['success' => false, 'error' => 'VOD Server not configured']);
                return;
            }

            $status = $this->vodService->getStatus();
            $this->sendJson(['success' => true, 'status' => $status]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Get server configuration (profiles, etc.)
     */
    public function config(): void
    {
        try {
            $config = $this->vodService->getConfig();
            $this->sendJson(['success' => true, 'config' => $config]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: List content items from VOD server
     */
    public function content(): void
    {
        try {
            $filters = [
                'status' => $_GET['status'] ?? null,
                'search' => $_GET['search'] ?? null,
                'limit'  => (int)($_GET['limit'] ?? 25),
                'offset' => (int)($_GET['offset'] ?? 0),
            ];

            $result = $this->vodService->getContent($filters);
            $this->sendJson(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Get single content detail
     */
    public function contentDetail(int $id): void
    {
        try {
            $contentId = $_GET['content_id'] ?? '';
            if (empty($contentId)) {
                $this->sendJson(['success' => false, 'error' => 'Content ID required']);
                return;
            }

            $detail = $this->vodService->getContentDetail($contentId);
            $this->sendJson(['success' => true, 'content' => $detail]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: List jobs
     */
    public function jobs(): void
    {
        try {
            $filters = [
                'status' => $_GET['status'] ?? null,
                'limit'  => (int)($_GET['limit'] ?? 25),
                'offset' => (int)($_GET['offset'] ?? 0),
            ];

            $result = $this->vodService->getJobs($filters);
            $this->sendJson(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Get single job detail
     */
    public function jobDetail(): void
    {
        try {
            $jobId = (int)($_GET['job_id'] ?? 0);
            if ($jobId <= 0) {
                $this->sendJson(['success' => false, 'error' => 'Job ID required']);
                return;
            }

            $detail = $this->vodService->getJob($jobId);
            $this->sendJson(['success' => true, 'job' => $detail]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Submit a new transcode job
     */
    public function submitJob(): void
    {
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        try {
            $contentId  = trim($_POST['content_id'] ?? '');
            $sourcePath = trim($_POST['source_path'] ?? '');
            $title      = trim($_POST['title'] ?? '');
            $profile    = trim($_POST['profile'] ?? 'standard');
            $priority   = (int)($_POST['priority'] ?? 5);
            $sourceType = trim($_POST['source_type'] ?? 'file');

            if (empty($contentId)) {
                $this->sendJson(['success' => false, 'error' => 'Content ID is required']);
                return;
            }
            if (empty($sourcePath)) {
                $this->sendJson(['success' => false, 'error' => 'Source path is required']);
                return;
            }

            $result = $this->vodService->submitJob($contentId, $sourcePath, [
                'title'       => $title ?: $contentId,
                'profile'     => $profile,
                'priority'    => max(1, min(10, $priority)),
                'source_type' => $sourceType,
            ]);

            $this->sendJson([
                'success' => true,
                'message' => 'Transcode job submitted successfully',
                'job'     => $result['job'] ?? $result,
            ]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Cancel a job
     */
    public function cancelJob(int $id): void
    {
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        try {
            $result = $this->vodService->cancelJob($id);
            $this->sendJson([
                'success' => true,
                'message' => 'Job cancelled successfully',
            ]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Delete content from VOD server
     */
    public function deleteContent(): void
    {
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        try {
            $contentId = trim($_POST['content_id'] ?? '');
            if (empty($contentId)) {
                $this->sendJson(['success' => false, 'error' => 'Content ID required']);
                return;
            }

            $result = $this->vodService->deleteContent($contentId);
            $this->sendJson([
                'success' => true,
                'message' => 'Content deleted from VOD Server',
            ]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Browse files on VOD server
     */
    public function browseFiles(): void
    {
        try {
            $path = $_GET['path'] ?? '/';
            $result = $this->vodService->browse($path);
            $this->sendJson(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Test connection to VOD server
     */
    public function testConnection(): void
    {
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $url = trim($_POST['url'] ?? '');
        $apiKey = trim($_POST['api_key'] ?? '');

        if (empty($url)) {
            $this->sendJson(['success' => false, 'error' => 'URL is required']);
            return;
        }

        $result = $this->vodService->testConnection($url, $apiKey);
        $this->sendJson($result);
    }

    /**
     * AJAX: Save VOD server settings
     */
    public function saveSettings(): void
    {
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $url     = trim($_POST['vod_server_url'] ?? '');
        $apiKey  = trim($_POST['vod_server_api_key'] ?? '');
        $enabled = !empty($_POST['vod_server_enabled']);

        if ($enabled && empty($url)) {
            $this->sendJson(['success' => false, 'error' => 'URL is required when enabling VOD Server']);
            return;
        }

        $this->settings->setMany([
            'vod_server_url'     => $url,
            'vod_server_api_key' => $apiKey,
            'vod_server_enabled' => $enabled ? '1' : '0',
        ], 'vod_server');

        $this->sendJson([
            'success' => true,
            'message' => 'VOD Server settings saved successfully',
        ]);
    }

    /**
     * Send JSON response
     */
    private function sendJson(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
