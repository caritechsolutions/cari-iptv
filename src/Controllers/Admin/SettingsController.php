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

        Response::view('admin/settings/index', [
            'pageTitle' => 'Settings',
            'settings' => $allSettings,
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

        // Ensure uploads directory exists
        $uploadDir = dirname(__DIR__, 3) . '/public/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destination = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => false, 'error' => 'Failed to save uploaded file'];
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
}
