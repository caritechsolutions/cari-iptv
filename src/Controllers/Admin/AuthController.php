<?php
/**
 * CARI-IPTV Admin Auth Controller
 */

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Services\AdminAuthService;
use CariIPTV\Core\Database;
use CariIPTV\Core\Response;
use CariIPTV\Core\Session;

class AuthController
{
    private AdminAuthService $auth;
    private Database $db;

    public function __construct()
    {
        $this->auth = new AdminAuthService();
        $this->db = Database::getInstance();
    }

    /**
     * Show login form
     */
    public function showLogin(): void
    {
        $error = Session::getFlash('error');
        $success = Session::getFlash('success');
        $csrf = Session::csrf();

        Response::view('admin/auth/login', [
            'error' => $error,
            'success' => $success,
            'csrf' => $csrf,
        ]);
    }

    /**
     * Handle login submission
     */
    public function login(): void
    {
        // Validate CSRF
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/login');
            return;
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (empty($username) || empty($password)) {
            Session::flash('error', 'Please enter username and password.');
            Response::redirect('/admin/login');
            return;
        }

        $result = $this->auth->login($username, $password, $remember);

        if (!$result['success']) {
            Session::flash('error', $result['message']);
            Response::redirect('/admin/login');
            return;
        }

        // Check if user needs to change password
        $user = $this->db->fetch(
            "SELECT force_password_change FROM admin_users WHERE id = ?",
            [$result['user']['id']]
        );

        if ($user && $user['force_password_change']) {
            Session::set('force_password_change', true);
            Session::flash('warning', 'You must change your password before continuing.');
            Response::redirect('/admin/profile');
            return;
        }

        // Get intended URL or default to dashboard
        $intended = Session::get('admin_intended_url', '/admin');
        Session::remove('admin_intended_url');

        Session::flash('success', 'Welcome back, ' . $result['user']['name'] . '!');
        Response::redirect($intended);
    }

    /**
     * Handle logout
     */
    public function logout(): void
    {
        $this->auth->logout();
        Session::flash('success', 'You have been logged out.');
        Response::redirect('/admin/login');
    }

    /**
     * Show forgot password form
     */
    public function showForgotPassword(): void
    {
        Response::view('admin/auth/forgot-password', [
            'csrf' => Session::csrf(),
            'error' => Session::getFlash('error'),
            'success' => Session::getFlash('success'),
        ]);
    }

    /**
     * Handle forgot password submission
     */
    public function forgotPassword(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/forgot-password');
            return;
        }

        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please enter a valid email address.');
            Response::redirect('/admin/forgot-password');
            return;
        }

        // Find user by email
        $user = $this->db->fetch(
            "SELECT id, username, email, first_name FROM admin_users WHERE email = ? AND is_active = 1",
            [$email]
        );

        // Always show success message to prevent email enumeration
        if (!$user) {
            Session::flash('success', 'If an account exists with that email, you will receive password reset instructions.');
            Response::redirect('/admin/forgot-password');
            return;
        }

        // Generate reset token
        $resetToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Invalidate any existing tokens for this user
        $this->db->update(
            'admin_password_resets',
            ['used_at' => date('Y-m-d H:i:s')],
            'admin_user_id = ? AND used_at IS NULL',
            [$user['id']]
        );

        // Store reset token
        $this->db->insert('admin_password_resets', [
            'admin_user_id' => $user['id'],
            'token' => hash('sha256', $resetToken),
            'expires_at' => $expiresAt,
        ]);

        // Log activity
        $this->auth->logActivity(null, 'password_reset_requested', 'auth', 'admin_user', $user['id']);

        // In production, you would send an email here
        // For now, we'll display the reset link (remove in production!)
        $resetUrl = "http://{$_SERVER['HTTP_HOST']}/admin/reset-password/{$resetToken}";

        // Store reset URL in session for display (DEVELOPMENT ONLY)
        Session::flash('success', "Password reset link generated. In production, this would be emailed. Reset URL: {$resetUrl}");
        Response::redirect('/admin/forgot-password');
    }

    /**
     * Show reset password form
     */
    public function showResetPassword(string $token): void
    {
        // Verify token exists and is valid
        $reset = $this->db->fetch(
            "SELECT pr.*, au.email, au.username
             FROM admin_password_resets pr
             INNER JOIN admin_users au ON pr.admin_user_id = au.id
             WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used_at IS NULL",
            [hash('sha256', $token)]
        );

        if (!$reset) {
            Session::flash('error', 'Invalid or expired password reset link. Please request a new one.');
            Response::redirect('/admin/forgot-password');
            return;
        }

        Response::view('admin/auth/reset-password', [
            'csrf' => Session::csrf(),
            'token' => $token,
            'email' => $reset['email'],
            'error' => Session::getFlash('error'),
        ]);
    }

    /**
     * Handle reset password submission
     */
    public function resetPassword(): void
    {
        $csrfToken = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($csrfToken)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/forgot-password');
            return;
        }

        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if (empty($password) || strlen($password) < 8) {
            Session::flash('error', 'Password must be at least 8 characters.');
            Response::redirect("/admin/reset-password/{$token}");
            return;
        }

        if ($password !== $passwordConfirm) {
            Session::flash('error', 'Passwords do not match.');
            Response::redirect("/admin/reset-password/{$token}");
            return;
        }

        // Verify token
        $reset = $this->db->fetch(
            "SELECT * FROM admin_password_resets
             WHERE token = ? AND expires_at > NOW() AND used_at IS NULL",
            [hash('sha256', $token)]
        );

        if (!$reset) {
            Session::flash('error', 'Invalid or expired password reset link. Please request a new one.');
            Response::redirect('/admin/forgot-password');
            return;
        }

        // Update password
        $this->db->update('admin_users', [
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'force_password_change' => 0,
            'password_changed_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$reset['admin_user_id']]);

        // Mark token as used
        $this->db->update('admin_password_resets', [
            'used_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$reset['id']]);

        // Log activity
        $this->auth->logActivity(null, 'password_reset_completed', 'auth', 'admin_user', $reset['admin_user_id']);

        Session::flash('success', 'Your password has been reset. Please log in with your new password.');
        Response::redirect('/admin/login');
    }
}
