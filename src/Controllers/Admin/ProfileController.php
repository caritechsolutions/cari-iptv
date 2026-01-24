<?php
/**
 * CARI-IPTV Admin Profile Controller
 */

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Database;
use CariIPTV\Core\Response;
use CariIPTV\Core\Session;
use CariIPTV\Services\AdminAuthService;

class ProfileController
{
    private Database $db;
    private AdminAuthService $auth;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = new AdminAuthService();
    }

    /**
     * Show profile page
     */
    public function index(): void
    {
        $user = $this->auth->user();

        if (!$user) {
            Response::redirect('/admin/login');
            return;
        }

        Response::view('admin/profile/index', [
            'pageTitle' => 'My Profile',
            'user' => $user,
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Update profile info
     */
    public function update(): void
    {
        // Validate CSRF
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/profile');
            return;
        }

        $userId = $this->auth->id();
        if (!$userId) {
            Response::redirect('/admin/login');
            return;
        }

        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        // Validate
        $errors = [];

        if (empty($firstName)) {
            $errors[] = 'First name is required.';
        }

        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            // Check if email is already taken by another user
            $existing = $this->db->fetch(
                "SELECT id FROM admin_users WHERE email = ? AND id != ?",
                [$email, $userId]
            );
            if ($existing) {
                $errors[] = 'This email is already in use.';
            }
        }

        if (!empty($errors)) {
            Session::flash('error', implode(' ', $errors));
            Response::redirect('/admin/profile');
            return;
        }

        // Update user
        $this->db->update('admin_users', [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
        ], 'id = ?', [$userId]);

        // Update session
        Session::set('admin_name', trim($firstName . ' ' . $lastName));

        // Log activity
        $this->auth->logActivity($userId, 'profile_update', 'profile');

        Session::flash('success', 'Profile updated successfully.');
        Response::redirect('/admin/profile');
    }

    /**
     * Change password
     */
    public function changePassword(): void
    {
        // Validate CSRF
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/profile');
            return;
        }

        $userId = $this->auth->id();
        if (!$userId) {
            Response::redirect('/admin/login');
            return;
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validate
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            Session::flash('error', 'All password fields are required.');
            Response::redirect('/admin/profile');
            return;
        }

        if ($newPassword !== $confirmPassword) {
            Session::flash('error', 'New passwords do not match.');
            Response::redirect('/admin/profile');
            return;
        }

        if (strlen($newPassword) < 8) {
            Session::flash('error', 'Password must be at least 8 characters.');
            Response::redirect('/admin/profile');
            return;
        }

        // Use auth service to change password
        $result = $this->auth->changePassword($userId, $currentPassword, $newPassword);

        if (!$result['success']) {
            Session::flash('error', $result['message']);
        } else {
            // Clear force password change flag
            $this->db->update('admin_users', [
                'force_password_change' => 0,
            ], 'id = ?', [$userId]);
            Session::remove('force_password_change');
            Session::flash('success', $result['message']);
        }

        Response::redirect('/admin/profile');
    }

    /**
     * Upload avatar
     */
    public function uploadAvatar(): void
    {
        // Validate CSRF
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/profile');
            return;
        }

        $userId = $this->auth->id();
        if (!$userId) {
            Response::redirect('/admin/login');
            return;
        }

        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Please select an image to upload.');
            Response::redirect('/admin/profile');
            return;
        }

        $file = $_FILES['avatar'];

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            Session::flash('error', 'Invalid file type. Please upload a JPG, PNG, GIF, or WebP image.');
            Response::redirect('/admin/profile');
            return;
        }

        // Validate file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            Session::flash('error', 'File is too large. Maximum size is 2MB.');
            Response::redirect('/admin/profile');
            return;
        }

        // Create avatars directory if it doesn't exist
        $avatarDir = dirname(__DIR__, 3) . '/public/assets/images/avatars';
        if (!is_dir($avatarDir)) {
            mkdir($avatarDir, 0755, true);
        }

        // Generate unique filename
        $extension = match($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $filename = 'admin_' . $userId . '_' . time() . '.' . $extension;
        $filepath = $avatarDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            Session::flash('error', 'Failed to save image. Please try again.');
            Response::redirect('/admin/profile');
            return;
        }

        // Delete old avatar if exists
        $user = $this->auth->user();
        if ($user && $user['avatar']) {
            $oldAvatar = dirname(__DIR__, 3) . '/public' . $user['avatar'];
            if (file_exists($oldAvatar) && strpos($user['avatar'], '/assets/images/avatars/') !== false) {
                unlink($oldAvatar);
            }
        }

        // Update database
        $avatarUrl = '/assets/images/avatars/' . $filename;
        $this->db->update('admin_users', [
            'avatar' => $avatarUrl,
        ], 'id = ?', [$userId]);

        // Log activity
        $this->auth->logActivity($userId, 'avatar_update', 'profile');

        Session::flash('success', 'Avatar updated successfully.');
        Response::redirect('/admin/profile');
    }

    /**
     * Remove avatar
     */
    public function removeAvatar(): void
    {
        $userId = $this->auth->id();
        if (!$userId) {
            Response::redirect('/admin/login');
            return;
        }

        // Get current avatar
        $user = $this->auth->user();
        if ($user && $user['avatar']) {
            $avatarPath = dirname(__DIR__, 3) . '/public' . $user['avatar'];
            if (file_exists($avatarPath) && strpos($user['avatar'], '/assets/images/avatars/') !== false) {
                unlink($avatarPath);
            }
        }

        // Update database
        $this->db->update('admin_users', [
            'avatar' => null,
        ], 'id = ?', [$userId]);

        // Log activity
        $this->auth->logActivity($userId, 'avatar_remove', 'profile');

        Session::flash('success', 'Avatar removed.');
        Response::redirect('/admin/profile');
    }
}
