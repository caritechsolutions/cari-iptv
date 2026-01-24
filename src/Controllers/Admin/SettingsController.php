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

        $this->settings->setMany([
            'site_name' => trim($_POST['site_name'] ?? 'CARI-IPTV'),
            'site_url' => trim($_POST['site_url'] ?? ''),
            'admin_email' => trim($_POST['admin_email'] ?? ''),
        ], 'general');

        $this->auth->logActivity($this->auth->id(), 'settings_update', 'settings', null, null, null, ['group' => 'general']);

        Session::flash('success', 'General settings updated successfully.');
        Response::redirect('/admin/settings');
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
