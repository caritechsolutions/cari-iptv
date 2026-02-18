<?php
/**
 * CARI-IPTV VOD Server Controller
 * Manages VOD Server integration - multi-server, status, content, jobs, transcoding
 */

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Database;
use CariIPTV\Core\Response;
use CariIPTV\Core\Session;
use CariIPTV\Services\VodServerService;

class VodServerController
{
    private VodServerService $vodService;
    private Database $db;

    public function __construct()
    {
        $this->vodService = new VodServerService();
        $this->db = Database::getInstance();
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

    /**
     * POST /admin/vod-server/upload-source
     * Receives file from browser, streams it to the selected VOD server,
     * then auto-submits a transcode job. Saves job reference on movie record.
     */
    public function uploadSource(): void
    {
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $serverId  = (int)($_POST['server_id'] ?? 0);
        $contentId = trim($_POST['content_id'] ?? '');
        $movieId   = (int)($_POST['movie_id'] ?? 0);
        $title     = trim($_POST['title'] ?? '');
        $profile   = trim($_POST['profile'] ?? 'standard');
        $overwrite = ($_POST['overwrite'] ?? '') === '1';

        if ($serverId <= 0) {
            $this->sendJson(['success' => false, 'error' => 'No VOD server selected']);
            return;
        }
        if (empty($contentId)) {
            $this->sendJson(['success' => false, 'error' => 'Content ID is required']);
            return;
        }

        // Check for uploaded file
        if (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
            $errCode = $_FILES['video_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            $errMsg = match($errCode) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large. Check server upload_max_filesize.',
                UPLOAD_ERR_NO_FILE => 'No file uploaded',
                UPLOAD_ERR_PARTIAL => 'Upload was interrupted',
                default => 'Upload error (code ' . $errCode . ')',
            };
            $this->sendJson(['success' => false, 'error' => $errMsg]);
            return;
        }

        $file = $_FILES['video_file'];
        $filename = basename($file['name']);
        $tmpPath = $file['tmp_name'];

        try {
            // Check for existing content (unless overwrite confirmed)
            if (!$overwrite && $this->vodService->contentExists($serverId, $contentId)) {
                $this->sendJson([
                    'success' => false,
                    'error' => 'duplicate',
                    'message' => 'Content "' . $contentId . '" already exists on this VOD server.',
                ]);
                return;
            }

            // Stream file to VOD server
            $uploadResult = $this->vodService->uploadFile($serverId, $tmpPath, $filename);

            if (empty($uploadResult['path'])) {
                $this->sendJson(['success' => false, 'error' => 'Upload succeeded but no path returned']);
                return;
            }

            // Auto-submit transcode job
            $jobResult = $this->vodService->submitJob($serverId, $contentId, $uploadResult['path'], [
                'title'       => $title ?: $contentId,
                'profile'     => $profile,
                'priority'    => 5,
                'source_type' => 'file',
            ]);

            // The VOD server may return the job inline or just {"success":true}.
            // Try to extract the job ID from the response first.
            $job = $jobResult['job'] ?? $jobResult;
            $jobId = (int)($job['id'] ?? 0);

            // If the submit response didn't include the job ID, query the jobs
            // list and find the one matching our content_id (most recent first).
            if ($jobId <= 0) {
                try {
                    $jobsList = $this->vodService->getJobs($serverId, ['limit' => 20]);
                    $items = $jobsList['items'] ?? $jobsList;
                    if (is_array($items)) {
                        foreach ($items as $item) {
                            if (($item['content_id'] ?? '') === $contentId) {
                                $job = $item;
                                $jobId = (int)($item['id'] ?? 0);
                                break;
                            }
                        }
                    }
                } catch (\Exception $lookupErr) {
                    error_log('[VOD Upload] Job lookup failed: ' . $lookupErr->getMessage());
                }
            }

            error_log('[VOD Upload] movieId=' . $movieId . ' serverId=' . $serverId . ' jobId=' . $jobId . ' contentId=' . $contentId);

            // Save job reference on the movie record (fail gracefully if columns don't exist yet)
            if ($movieId > 0 && $jobId > 0) {
                try {
                    $this->db->execute(
                        "UPDATE movies SET vod_server_id = ?, vod_job_id = ?, vod_status = 'pending', vod_progress = 0, vod_error = NULL WHERE id = ?",
                        [$serverId, $jobId, $movieId]
                    );
                    error_log('[VOD Upload] DB save SUCCESS for movie ' . $movieId . ' jobId=' . $jobId);
                } catch (\Exception $dbErr) {
                    error_log('[VOD Upload] DB save FAILED: ' . $dbErr->getMessage());
                }
            } else {
                error_log('[VOD Upload] SKIPPED DB save — movieId=' . $movieId . ' jobId=' . $jobId);
            }

            $this->sendJson([
                'success'    => true,
                'message'    => 'File uploaded and transcode job submitted',
                'upload_path' => $uploadResult['path'],
                'job'        => $job,
                'job_id'     => $jobId,
                'server_id'  => $serverId,
                'movie_id'   => $movieId,
            ]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin/vod-server/check-content?server_id=X&content_id=Y
     * Check if content already exists on the VOD server
     */
    public function checkContent(): void
    {
        $serverId  = (int)($_GET['server_id'] ?? 0);
        $contentId = trim($_GET['content_id'] ?? '');

        if ($serverId <= 0 || empty($contentId)) {
            $this->sendJson(['success' => true, 'exists' => false]);
            return;
        }

        try {
            $exists = $this->vodService->contentExists($serverId, $contentId);
            $this->sendJson(['success' => true, 'exists' => $exists]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => true, 'exists' => false]);
        }
    }

    /**
     * GET /admin/vod-server/job-status?server_id=X&job_id=Y&movie_id=Z
     * Poll a specific job's status. Updates the movie record with progress.
     * When complete, writes the stream_url on the movie.
     */
    public function jobStatus(): void
    {
        try {
            $sid = (int)($_GET['server_id'] ?? 0);
            $jobId = (int)($_GET['job_id'] ?? 0);
            $movieId = (int)($_GET['movie_id'] ?? 0);
            if ($sid <= 0 || $jobId <= 0) {
                $this->sendJson(['success' => false, 'error' => 'Server ID and Job ID required']);
                return;
            }
            $detail = $this->vodService->getJob($sid, $jobId);
            $job = $detail['job'] ?? $detail;
            $status = $job['status'] ?? '';
            $progress = (float)($job['progress'] ?? 0);
            $errorMsg = $job['error_msg'] ?? null;

            // Persist status to movie record (fail gracefully if columns don't exist yet)
            if ($movieId > 0) {
                try {
                    if ($status === 'complete') {
                        // Transcode done — write the stream URL
                        $server = $this->vodService->getServer($sid);
                        $contentId = $job['content_id'] ?? ('movie-' . $movieId);
                        $hlsUrl = $server ? ($server['url'] . '/content/' . urlencode($contentId) . '/master.m3u8') : '';

                        $this->db->execute(
                            "UPDATE movies SET vod_status = 'complete', vod_progress = 100, vod_error = NULL, stream_url = ? WHERE id = ?",
                            [$hlsUrl, $movieId]
                        );
                        $job['stream_url'] = $hlsUrl;
                    } elseif ($status === 'failed') {
                        $this->db->execute(
                            "UPDATE movies SET vod_status = 'failed', vod_progress = ?, vod_error = ? WHERE id = ?",
                            [$progress, $errorMsg, $movieId]
                        );
                    } elseif ($status === 'cancelled') {
                        $this->db->execute(
                            "UPDATE movies SET vod_status = 'cancelled', vod_job_id = NULL, vod_error = 'Cancelled' WHERE id = ?",
                            [$movieId]
                        );
                    } else {
                        // In progress — update progress
                        $this->db->execute(
                            "UPDATE movies SET vod_status = ?, vod_progress = ? WHERE id = ?",
                            [$status, $progress, $movieId]
                        );
                    }
                } catch (\Exception $dbErr) {
                    // Migration may not have run yet — non-fatal
                    error_log('VOD status poll save failed: ' . $dbErr->getMessage());
                    // Still compute stream_url for the JS response
                    if ($status === 'complete') {
                        $server = $this->vodService->getServer($sid);
                        $contentId = $job['content_id'] ?? ('movie-' . $movieId);
                        $job['stream_url'] = $server ? ($server['url'] . '/content/' . urlencode($contentId) . '/master.m3u8') : '';
                    }
                }
            }

            $this->sendJson(['success' => true, 'job' => $job]);
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

    private function sendJson(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
