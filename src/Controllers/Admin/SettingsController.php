<?php
/**
 * CARI-IPTV Settings Controller
 */

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Database;
use CariIPTV\Core\Response;
use CariIPTV\Core\Session;
use CariIPTV\Services\AdminAuthService;
use CariIPTV\Services\SettingsService;
use CariIPTV\Services\EmailService;
use CariIPTV\Services\AIService;
use CariIPTV\Services\MetadataService;
use CariIPTV\Services\ImageService;

class SettingsController
{
    private Database $db;
    private AdminAuthService $auth;
    private SettingsService $settings;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = new AdminAuthService();
        $this->settings = new SettingsService();
    }

    /**
     * Show settings page
     */
    public function index(): void
    {
        $allSettings = $this->settings->getAll();

        // Get integration status
        $aiService = new AIService();
        $metadataService = new MetadataService();

        $integrationStatus = [
            'ai' => $aiService->getStatus(),
            'metadata' => $metadataService->getStatus(),
            'image' => [
                'webp_supported' => ImageService::isWebPSupported(),
            ],
        ];

        Response::view('admin/settings/index', [
            'pageTitle' => 'Settings',
            'settings' => $allSettings,
            'integrationStatus' => $integrationStatus,
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Update general settings
     */
    public function updateGeneral(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/settings');
            return;
        }

        // Handle logo upload
        $siteLogo = $this->settings->get('site_logo', '', 'general');

        // Check if removing logo
        if (!empty($_POST['remove_logo'])) {
            // Delete old logo file if exists
            if (!empty($siteLogo)) {
                $oldPath = dirname(__DIR__, 3) . '/public' . $siteLogo;
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }
            $siteLogo = '';
        }

        // Handle new logo upload
        if (!empty($_FILES['site_logo']['tmp_name']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = $this->handleLogoUpload($_FILES['site_logo']);
            if ($uploadResult['success']) {
                // Delete old logo if exists
                if (!empty($siteLogo)) {
                    $oldPath = dirname(__DIR__, 3) . '/public' . $siteLogo;
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                $siteLogo = $uploadResult['path'];
            } else {
                Session::flash('error', $uploadResult['error']);
                Response::redirect('/admin/settings');
                return;
            }
        }

        $this->settings->setMany([
            'site_name' => trim($_POST['site_name'] ?? 'CARI-IPTV'),
            'site_url' => trim($_POST['site_url'] ?? ''),
            'admin_email' => trim($_POST['admin_email'] ?? ''),
            'site_logo' => $siteLogo,
        ], 'general');

        $this->auth->logActivity($this->auth->id(), 'settings_update', 'settings', null, null, null, ['group' => 'general']);

        Session::flash('success', 'General settings updated successfully.');
        Response::redirect('/admin/settings');
    }

    /**
     * Handle logo file upload
     */
    private function handleLogoUpload(array $file): array
    {
        $allowedTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml', 'image/webp'];
        $maxSize = 1024 * 1024; // 1MB

        // Validate file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            return ['success' => false, 'error' => 'Invalid file type. Allowed: PNG, JPG, GIF, SVG, WebP'];
        }

        // Validate file size
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'File too large. Maximum size is 1MB'];
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
        $filename = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

        // Use same path structure as avatars which works
        $uploadDir = dirname(__DIR__, 3) . '/public/uploads';
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0775, true)) {
                // Log the actual error
                $error = error_get_last();
                return ['success' => false, 'error' => 'Failed to create upload directory: ' . ($error['message'] ?? 'Unknown error')];
            }
        }

        // Check if directory is writable
        if (!is_writable($uploadDir)) {
            return ['success' => false, 'error' => 'Upload directory is not writable: ' . $uploadDir];
        }

        $destination = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $error = error_get_last();
            return ['success' => false, 'error' => 'Failed to save file: ' . ($error['message'] ?? 'Unknown error') . ' (dest: ' . $destination . ')'];
        }

        return ['success' => true, 'path' => '/uploads/' . $filename];
    }

    /**
     * Update SMTP settings
     */
    public function updateSmtp(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/settings');
            return;
        }

        // Get current password if not provided (to preserve it)
        $currentPassword = $this->settings->get('password', '', 'smtp');
        $newPassword = $_POST['smtp_password'] ?? '';

        // Only update password if a new one is provided
        $password = !empty($newPassword) ? $newPassword : $currentPassword;

        $this->settings->setMany([
            'enabled' => isset($_POST['smtp_enabled']) ? '1' : '0',
            'host' => trim($_POST['smtp_host'] ?? ''),
            'port' => (int) ($_POST['smtp_port'] ?? 587),
            'encryption' => $_POST['smtp_encryption'] ?? 'tls',
            'username' => trim($_POST['smtp_username'] ?? ''),
            'password' => $password,
            'from_email' => trim($_POST['smtp_from_email'] ?? ''),
            'from_name' => trim($_POST['smtp_from_name'] ?? 'CARI-IPTV'),
        ], 'smtp');

        $this->auth->logActivity($this->auth->id(), 'settings_update', 'settings', null, null, null, ['group' => 'smtp']);

        Session::flash('success', 'SMTP settings updated successfully.');
        Response::redirect('/admin/settings');
    }

    /**
     * Send test email
     */
    public function testEmail(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/settings');
            return;
        }

        $testEmail = trim($_POST['test_email'] ?? '');

        if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please enter a valid email address.');
            Response::redirect('/admin/settings');
            return;
        }

        $emailService = new EmailService();

        if (!$emailService->isConfigured()) {
            Session::flash('error', 'SMTP is not configured. Please save your SMTP settings first and ensure SMTP is enabled.');
            Response::redirect('/admin/settings');
            return;
        }

        if ($emailService->sendTest($testEmail)) {
            Session::flash('success', "Test email sent successfully to {$testEmail}!");
        } else {
            Session::flash('error', 'Failed to send test email: ' . $emailService->getLastError());
        }

        Response::redirect('/admin/settings');
    }

    /**
     * Update AI settings
     */
    public function updateAI(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/settings');
            return;
        }

        // Get current API keys to preserve if not provided
        $currentOpenAIKey = $this->settings->get('openai_api_key', '', 'ai');
        $currentAnthropicKey = $this->settings->get('anthropic_api_key', '', 'ai');

        $newOpenAIKey = trim($_POST['openai_api_key'] ?? '');
        $newAnthropicKey = trim($_POST['anthropic_api_key'] ?? '');

        $this->settings->setMany([
            'provider' => $_POST['ai_provider'] ?? 'ollama',
            'ollama_url' => trim($_POST['ollama_url'] ?? 'http://localhost:11434'),
            'ollama_model' => trim($_POST['ollama_model'] ?? 'llama3.2:1b'),
            'openai_api_key' => !empty($newOpenAIKey) ? $newOpenAIKey : $currentOpenAIKey,
            'openai_model' => trim($_POST['openai_model'] ?? 'gpt-4o-mini'),
            'anthropic_api_key' => !empty($newAnthropicKey) ? $newAnthropicKey : $currentAnthropicKey,
            'anthropic_model' => trim($_POST['anthropic_model'] ?? 'claude-3-haiku-20240307'),
            'ai_enabled' => isset($_POST['ai_enabled']) ? '1' : '0',
        ], 'ai');

        $this->auth->logActivity($this->auth->id(), 'settings_update', 'settings', null, null, null, ['group' => 'ai']);

        Session::flash('success', 'AI settings updated successfully.');
        Response::redirect('/admin/settings');
    }

    /**
     * Update Metadata API settings
     */
    public function updateMetadata(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/settings');
            return;
        }

        // Get current API keys to preserve if not provided
        $currentFanartKey = $this->settings->get('fanart_tv_api_key', '', 'metadata');
        $currentTmdbKey = $this->settings->get('tmdb_api_key', '', 'metadata');

        $newFanartKey = trim($_POST['fanart_tv_api_key'] ?? '');
        $newTmdbKey = trim($_POST['tmdb_api_key'] ?? '');

        $this->settings->setMany([
            'fanart_tv_api_key' => !empty($newFanartKey) ? $newFanartKey : $currentFanartKey,
            'tmdb_api_key' => !empty($newTmdbKey) ? $newTmdbKey : $currentTmdbKey,
            'auto_fetch_metadata' => isset($_POST['auto_fetch_metadata']) ? '1' : '0',
            'cache_metadata' => isset($_POST['cache_metadata']) ? '1' : '0',
        ], 'metadata');

        $this->auth->logActivity($this->auth->id(), 'settings_update', 'settings', null, null, null, ['group' => 'metadata']);

        Session::flash('success', 'Metadata API settings updated successfully.');
        Response::redirect('/admin/settings');
    }

    /**
     * Test AI connection
     */
    public function testAI(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $aiService = new AIService();

        if (!$aiService->isAvailable()) {
            Response::json([
                'success' => false,
                'message' => 'AI service is not available. Check your configuration.',
            ]);
            return;
        }

        // Test with a simple prompt
        $result = $aiService->complete('Say "Hello, I am working!" in exactly those words.');

        if ($result) {
            Response::json([
                'success' => true,
                'message' => 'AI connection successful!',
                'provider' => $aiService->getProviderName(),
                'response' => $result,
            ]);
        } else {
            Response::json([
                'success' => false,
                'message' => 'AI service responded but failed to generate text.',
            ]);
        }
    }

    /**
     * Test Ollama connection
     */
    public function testOllama(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $aiService = new AIService();
        $connected = $aiService->testOllamaConnection();

        if ($connected) {
            $models = $aiService->getOllamaModels();
            Response::json([
                'success' => true,
                'message' => 'Ollama connection successful!',
                'models' => array_column($models, 'name'),
            ]);
        } else {
            Response::json([
                'success' => false,
                'message' => 'Could not connect to Ollama. Make sure it is running.',
            ]);
        }
    }

    /**
     * Test Fanart.tv connection
     */
    public function testFanart(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $metadataService = new MetadataService();

        if (!$metadataService->isFanartConfigured()) {
            Response::json([
                'success' => false,
                'message' => 'Fanart.tv API key not configured.',
            ]);
            return;
        }

        $connected = $metadataService->testFanartConnection();

        Response::json([
            'success' => $connected,
            'message' => $connected ? 'Fanart.tv connection successful!' : 'Connection failed. Check your API key.',
        ]);
    }

    /**
     * Test TMDB connection
     */
    public function testTmdb(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $metadataService = new MetadataService();

        if (!$metadataService->isTmdbConfigured()) {
            Response::json([
                'success' => false,
                'message' => 'TMDB API key not configured.',
            ]);
            return;
        }

        $connected = $metadataService->testTmdbConnection();

        Response::json([
            'success' => $connected,
            'message' => $connected ? 'TMDB connection successful!' : 'Connection failed. Check your API key.',
        ]);
    }

    /**
     * Update YouTube API settings
     */
    public function updateYoutube(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/settings');
            return;
        }

        // Get current API key to preserve if not provided
        $currentYoutubeKey = $this->settings->get('youtube_api_key', '', 'metadata');
        $newYoutubeKey = trim($_POST['youtube_api_key'] ?? '');

        $this->settings->setMany([
            'youtube_api_key' => !empty($newYoutubeKey) ? $newYoutubeKey : $currentYoutubeKey,
        ], 'metadata');

        $this->auth->logActivity($this->auth->id(), 'settings_update', 'settings', null, null, null, ['group' => 'youtube']);

        Session::flash('success', 'YouTube API settings updated successfully.');
        Response::redirect('/admin/settings');
    }

    /**
     * Test YouTube Data API connection
     */
    public function testYoutube(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $metadataService = new MetadataService();

        if (!$metadataService->isYoutubeConfigured()) {
            Response::json([
                'success' => false,
                'message' => 'YouTube API key not configured.',
            ]);
            return;
        }

        $connected = $metadataService->testYoutubeConnection();

        Response::json([
            'success' => $connected,
            'message' => $connected ? 'YouTube Data API connection successful!' : 'Connection failed. Check your API key.',
        ]);
    }

    /**
     * Update Image settings
     */
    public function updateImage(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/settings');
            return;
        }

        $this->settings->setMany([
            'webp_quality' => max(1, min(100, (int) ($_POST['webp_quality'] ?? 85))),
            'keep_originals' => isset($_POST['keep_originals']) ? '1' : '0',
            'auto_optimize' => isset($_POST['auto_optimize']) ? '1' : '0',
        ], 'image');

        $this->auth->logActivity($this->auth->id(), 'settings_update', 'settings', null, null, null, ['group' => 'image']);

        Session::flash('success', 'Image settings updated successfully.');
        Response::redirect('/admin/settings');
    }
}
