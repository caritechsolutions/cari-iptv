<?php
/**
 * CARI-IPTV Admin Auth Controller
 */

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Services\AdminAuthService;
use CariIPTV\Core\Response;
use CariIPTV\Core\Session;

class AuthController
{
    private AdminAuthService $auth;

    public function __construct()
    {
        $this->auth = new AdminAuthService();
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
}
