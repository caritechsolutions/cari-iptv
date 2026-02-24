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
                'public_url' => trim($_POST['public_url'] ?? ''),
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
                'public_url' => trim($_POST['public_url'] ?? ''),
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
        error_log('[VOD UploadSource] === Request received ===');
        error_log('[VOD UploadSource] POST keys: ' . implode(', ', array_keys($_POST)));
        error_log('[VOD UploadSource] FILES keys: ' . implode(', ', array_keys($_FILES)));

        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            error_log('[VOD UploadSource] CSRF validation failed');
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $serverId  = (int)($_POST['server_id'] ?? 0);
        $contentId = trim($_POST['content_id'] ?? '');
        $movieId   = (int)($_POST['movie_id'] ?? 0);
        $title     = trim($_POST['title'] ?? '');
        $profile   = trim($_POST['profile'] ?? 'standard');
        $overwrite = ($_POST['overwrite'] ?? '') === '1';
        error_log("[VOD UploadSource] serverId=$serverId contentId=$contentId entityType=" . ($_POST['entity_type'] ?? '') . " file=" . ($_FILES['video_file']['name'] ?? 'N/A') . " size=" . ($_FILES['video_file']['size'] ?? 0));

        // Generic entity support (episodes, etc.) — falls back to movie_id for backward compat
        $entityType = trim($_POST['entity_type'] ?? '');
        $entityId   = (int)($_POST['entity_id'] ?? 0);
        if (!$entityType && $movieId > 0) {
            $entityType = 'movie';
            $entityId = $movieId;
        }

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

            error_log('[VOD Upload] entityType=' . $entityType . ' entityId=' . $entityId . ' serverId=' . $serverId . ' jobId=' . $jobId . ' contentId=' . $contentId);

            // Save job reference on the entity record (fail gracefully if columns don't exist yet)
            if ($entityId > 0 && $jobId > 0) {
                try {
                    $table = $entityType === 'episode' ? 'series_episodes' : 'movies';
                    $this->db->execute(
                        "UPDATE {$table} SET vod_server_id = ?, vod_job_id = ?, vod_content_id = ?, vod_status = 'pending', vod_progress = 0, vod_error = NULL WHERE id = ?",
                        [$serverId, $jobId, $contentId, $entityId]
                    );
                    error_log('[VOD Upload] DB save SUCCESS for ' . $entityType . ' ' . $entityId . ' jobId=' . $jobId);
                } catch (\Exception $dbErr) {
                    error_log('[VOD Upload] DB save FAILED: ' . $dbErr->getMessage());
                }
            } else {
                error_log('[VOD Upload] SKIPPED DB save — entityType=' . $entityType . ' entityId=' . $entityId . ' jobId=' . $jobId);
            }

            $this->sendJson([
                'success'     => true,
                'message'     => 'File uploaded and transcode job submitted',
                'upload_path' => $uploadResult['path'],
                'job'         => $job,
                'job_id'      => $jobId,
                'server_id'   => $serverId,
                'movie_id'    => $movieId,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
            ]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin/vod-server/submit-direct-job
     * Called after a direct-to-VOD-server upload completes (browser → VOD server).
     * Submits the transcode job via the VOD API and saves the job reference on the DB entity.
     */
    public function submitDirectUploadJob(): void
    {
        error_log('[VOD DirectJob] === Request received === POST: ' . json_encode(array_diff_key($_POST, ['csrf_token' => 1])));

        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            error_log('[VOD DirectJob] CSRF validation failed');
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $serverId   = (int)($_POST['server_id'] ?? 0);
        $contentId  = trim($_POST['content_id'] ?? '');
        $uploadPath = trim($_POST['upload_path'] ?? '');
        $title      = trim($_POST['title'] ?? '');
        $profile    = trim($_POST['profile'] ?? 'standard');
        $entityType = trim($_POST['entity_type'] ?? 'movie');
        $entityId   = (int)($_POST['entity_id'] ?? 0);
        $overwrite  = ($_POST['overwrite'] ?? '') === '1';

        if ($serverId <= 0) {
            $this->sendJson(['success' => false, 'error' => 'No VOD server selected']);
            return;
        }
        if (empty($contentId) || empty($uploadPath)) {
            $this->sendJson(['success' => false, 'error' => 'content_id and upload_path are required']);
            return;
        }

        try {
            // Check for existing content (unless overwrite confirmed)
            if (!$overwrite && $this->vodService->contentExists($serverId, $contentId)) {
                $this->sendJson([
                    'success' => false,
                    'error'   => 'duplicate',
                    'message' => 'Content "' . $contentId . '" already exists on this VOD server.',
                ]);
                return;
            }

            // Submit transcode job
            $jobResult = $this->vodService->submitJob($serverId, $contentId, $uploadPath, [
                'title'       => $title ?: $contentId,
                'profile'     => $profile,
                'priority'    => 5,
                'source_type' => 'file',
            ]);

            $job = $jobResult['job'] ?? $jobResult;
            $jobId = (int)($job['id'] ?? 0);

            // If job ID not in response, look it up
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
                    error_log('[VOD SubmitJob] Job lookup failed: ' . $lookupErr->getMessage());
                }
            }

            // Save job reference on entity record
            if ($entityId > 0 && $jobId > 0) {
                try {
                    $table = $entityType === 'episode' ? 'series_episodes' : 'movies';
                    $this->db->execute(
                        "UPDATE {$table} SET vod_server_id = ?, vod_job_id = ?, vod_content_id = ?, vod_status = 'pending', vod_progress = 0, vod_error = NULL WHERE id = ?",
                        [$serverId, $jobId, $contentId, $entityId]
                    );
                } catch (\Exception $dbErr) {
                    error_log('[VOD SubmitJob] DB save failed: ' . $dbErr->getMessage());
                }
            }

            $this->sendJson([
                'success'     => true,
                'message'     => 'Transcode job submitted',
                'job'         => $job,
                'job_id'      => $jobId,
                'server_id'   => $serverId,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
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

            // Generic entity support — falls back to movie_id for backward compat
            $entityType = trim($_GET['entity_type'] ?? '');
            $entityId   = (int)($_GET['entity_id'] ?? 0);
            if (!$entityType && $movieId > 0) {
                $entityType = 'movie';
                $entityId = $movieId;
            }

            if ($sid <= 0 || $jobId <= 0) {
                $this->sendJson(['success' => false, 'error' => 'Server ID and Job ID required']);
                return;
            }
            $detail = $this->vodService->getJob($sid, $jobId);
            $job = $detail['job'] ?? $detail;
            $status = $job['status'] ?? '';
            $progress = (float)($job['progress'] ?? 0);
            $errorMsg = $job['error_msg'] ?? null;

            // Persist status to entity record (fail gracefully if columns don't exist yet)
            if ($entityId > 0) {
                $table = $entityType === 'episode' ? 'series_episodes' : 'movies';
                try {
                    if ($status === 'complete') {
                        // Transcode done — write the stream URL (HLS) and content_id
                        $server = $this->vodService->getServer($sid);
                        $contentId = $job['content_id'] ?? ($entityType . '-' . $entityId);
                        $baseUrl = !empty($server['public_url']) ? $server['public_url'] : ($server['url'] ?? '');
                        $hlsUrl = $baseUrl ? ($baseUrl . '/content/' . urlencode($contentId) . '/master.m3u8') : '';

                        $this->db->execute(
                            "UPDATE {$table} SET vod_status = 'complete', vod_progress = 100, vod_error = NULL, stream_url = ?, vod_content_id = ? WHERE id = ?",
                            [$hlsUrl, $contentId, $entityId]
                        );
                        $job['stream_url'] = $hlsUrl;
                    } elseif ($status === 'failed') {
                        $this->db->execute(
                            "UPDATE {$table} SET vod_status = 'failed', vod_progress = ?, vod_error = ? WHERE id = ?",
                            [$progress, $errorMsg, $entityId]
                        );
                    } elseif ($status === 'cancelled') {
                        $this->db->execute(
                            "UPDATE {$table} SET vod_status = 'cancelled', vod_job_id = NULL, vod_error = 'Cancelled' WHERE id = ?",
                            [$entityId]
                        );
                    } else {
                        // In progress — update progress
                        $this->db->execute(
                            "UPDATE {$table} SET vod_status = ?, vod_progress = ? WHERE id = ?",
                            [$status, $progress, $entityId]
                        );
                    }
                } catch (\Exception $dbErr) {
                    error_log('VOD status poll save failed: ' . $dbErr->getMessage());
                    if ($status === 'complete') {
                        $server = $this->vodService->getServer($sid);
                        $contentId = $job['content_id'] ?? ($entityType . '-' . $entityId);
                        $baseUrl = !empty($server['public_url']) ? $server['public_url'] : ($server['url'] ?? '');
                        $job['stream_url'] = $baseUrl ? ($baseUrl . '/content/' . urlencode($contentId) . '/master.m3u8') : '';
                    }
                }
            }

            $this->sendJson(['success' => true, 'job' => $job]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin/vod-server/movie-vod-delete
     * Delete VOD content from the server AND clear all VOD fields on the entity record.
     * Supports movies (movie_id) and episodes (entity_type=episode, entity_id).
     * Expects: server_id, content_id, movie_id|entity_id, csrf_token
     */
    public function movieVodDelete(): void
    {
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $serverId  = (int)($_POST['server_id'] ?? 0);
        $contentId = trim($_POST['content_id'] ?? '');
        $movieId   = (int)($_POST['movie_id'] ?? 0);

        // Generic entity support
        $entityType = trim($_POST['entity_type'] ?? '');
        $entityId   = (int)($_POST['entity_id'] ?? 0);
        if (!$entityType && $movieId > 0) {
            $entityType = 'movie';
            $entityId = $movieId;
        }

        if ($entityId <= 0) {
            $this->sendJson(['success' => false, 'error' => 'Entity ID required']);
            return;
        }

        $vodDeleted = false;

        // 1. Try to delete from VOD server (non-fatal — content may already be gone)
        if ($serverId > 0 && !empty($contentId)) {
            try {
                $this->vodService->deleteContentItem($serverId, $contentId);
                $vodDeleted = true;
            } catch (\Exception $e) {
                error_log('[VOD Delete] Server delete failed (may not exist): ' . $e->getMessage());
            }
        }

        // 2. Also cancel any active job for this content
        $vodJobId = (int)($_POST['job_id'] ?? 0);
        if ($serverId > 0 && $vodJobId > 0) {
            try {
                $this->vodService->cancelJob($serverId, $vodJobId);
            } catch (\Exception $e) {
                error_log('[VOD Delete] Job cancel failed (may not exist): ' . $e->getMessage());
            }
        }

        // 3. Clear all VOD fields on the entity record
        try {
            $table = $entityType === 'episode' ? 'series_episodes' : 'movies';
            $this->db->execute(
                "UPDATE {$table} SET vod_server_id = NULL, vod_job_id = NULL, vod_content_id = NULL, vod_status = NULL, vod_progress = 0, vod_error = NULL, stream_url = NULL WHERE id = ?",
                [$entityId]
            );
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => 'Failed to update record: ' . $e->getMessage()]);
            return;
        }

        $this->sendJson([
            'success' => true,
            'message' => $vodDeleted
                ? 'Content deleted from VOD server and record cleared'
                : 'VOD data cleared' . ($serverId > 0 ? ' (content may already be removed from server)' : ''),
        ]);
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

    /* ==============================================================
     * Profiles (read-only — manage on VOD Server GUI)
     * ============================================================== */

    public function getProfiles(): void
    {
        try {
            $sid = $this->getServerId();
            if ($sid <= 0) { $this->sendJson(['success' => false, 'error' => 'No server selected']); return; }
            $result = $this->vodService->getProfiles($sid);
            $this->sendJson(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /* ==============================================================
     * DRM Key Management
     * ============================================================== */

    /**
     * GET /admin/vod-server/drm/keys?server_id=X
     * List all DRM keys on the selected VOD server
     */
    public function drmKeys(): void
    {
        try {
            $sid = $this->getServerId();
            if ($sid <= 0) {
                $this->sendJson(['success' => false, 'error' => 'No server selected']);
                return;
            }
            $result = $this->vodService->getDrmKeys($sid);
            $this->sendJson(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin/vod-server/drm/generate
     * Generate a new DRM key for a content ID
     * Expects: server_id, content_id, scheme (optional, default 'cenc'), csrf_token
     */
    public function drmKeyGenerate(): void
    {
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        try {
            $sid = (int)($_POST['server_id'] ?? 0);
            $contentId = trim($_POST['content_id'] ?? '');
            $scheme = trim($_POST['scheme'] ?? 'cenc');

            if ($sid <= 0) {
                $this->sendJson(['success' => false, 'error' => 'No server selected']);
                return;
            }
            if (empty($contentId)) {
                $this->sendJson(['success' => false, 'error' => 'Content ID is required']);
                return;
            }

            $result = $this->vodService->generateDrmKey($sid, $contentId, $scheme);
            $this->sendJson(['success' => true, 'message' => 'DRM key generated', 'data' => $result]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin/vod-server/drm/delete
     * Delete a DRM key for a content ID
     * Expects: server_id, content_id, csrf_token
     */
    public function drmKeyDelete(): void
    {
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        try {
            $sid = (int)($_POST['server_id'] ?? 0);
            $contentId = trim($_POST['content_id'] ?? '');

            if ($sid <= 0) {
                $this->sendJson(['success' => false, 'error' => 'No server selected']);
                return;
            }
            if (empty($contentId)) {
                $this->sendJson(['success' => false, 'error' => 'Content ID is required']);
                return;
            }

            $this->vodService->deleteDrmKey($sid, $contentId);
            $this->sendJson(['success' => true, 'message' => 'DRM key deleted']);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /* ==============================================================
     * Content Markers (intro, credits, ad cue points)
     * ============================================================== */

    /**
     * GET /admin/vod-server/markers?content_type=movie&content_id=123
     */
    public function getMarkers(): void
    {
        $contentType = trim($_GET['content_type'] ?? '');
        $contentId   = (int)($_GET['content_id'] ?? 0);

        if (!in_array($contentType, ['movie', 'episode']) || $contentId <= 0) {
            $this->sendJson(['success' => false, 'error' => 'content_type and content_id required']);
            return;
        }

        $markers = $this->db->fetchAll(
            "SELECT * FROM content_markers WHERE content_type = ? AND content_id = ? ORDER BY position_seconds ASC",
            [$contentType, $contentId]
        ) ?: [];

        $this->sendJson(['success' => true, 'markers' => $markers]);
    }

    /**
     * POST /admin/vod-server/markers/save
     * Body: content_type, content_id, marker_type, position_seconds, label?, csrf_token
     */
    public function saveMarker(): void
    {
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $contentType = trim($_POST['content_type'] ?? '');
        $contentId   = (int)($_POST['content_id'] ?? 0);
        $markerType  = trim($_POST['marker_type'] ?? '');
        $position    = (float)($_POST['position_seconds'] ?? 0);
        $label       = trim($_POST['label'] ?? '');

        $validTypes = ['intro_start', 'intro_end', 'credits_start', 'ad_cue'];
        if (!in_array($contentType, ['movie', 'episode']) || $contentId <= 0) {
            $this->sendJson(['success' => false, 'error' => 'content_type and content_id required']);
            return;
        }
        if (!in_array($markerType, $validTypes)) {
            $this->sendJson(['success' => false, 'error' => 'Invalid marker_type. Valid: ' . implode(', ', $validTypes)]);
            return;
        }

        // For intro_start, intro_end, credits_start — only one per content item (upsert)
        // For ad_cue — multiple allowed
        if ($markerType !== 'ad_cue') {
            $existing = $this->db->fetch(
                "SELECT id FROM content_markers WHERE content_type = ? AND content_id = ? AND marker_type = ?",
                [$contentType, $contentId, $markerType]
            );
            if ($existing) {
                $this->db->execute(
                    "UPDATE content_markers SET position_seconds = ?, label = ?, updated_at = NOW() WHERE id = ?",
                    [$position, $label ?: null, $existing['id']]
                );
                $this->sendJson(['success' => true, 'id' => (int)$existing['id'], 'updated' => true]);
                return;
            }
        }

        $this->db->execute(
            "INSERT INTO content_markers (content_type, content_id, marker_type, position_seconds, label) VALUES (?, ?, ?, ?, ?)",
            [$contentType, $contentId, $markerType, $position, $label ?: null]
        );

        $this->sendJson(['success' => true, 'id' => (int)$this->db->lastInsertId(), 'updated' => false]);
    }

    /**
     * POST /admin/vod-server/markers/delete
     * Body: id, csrf_token
     */
    public function deleteMarker(): void
    {
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $this->sendJson(['success' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->sendJson(['success' => false, 'error' => 'Marker ID required']);
            return;
        }

        $this->db->execute("DELETE FROM content_markers WHERE id = ?", [$id]);
        $this->sendJson(['success' => true]);
    }

    private function sendJson(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
