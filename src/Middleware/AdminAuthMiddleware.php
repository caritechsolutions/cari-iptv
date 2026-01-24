<?php
/**
 * CARI-IPTV Admin Authentication Middleware
 */

namespace CariIPTV\Middleware;

use CariIPTV\Services\AdminAuthService;
use CariIPTV\Core\Response;
use CariIPTV\Core\Session;

class AdminAuthMiddleware
{
    public static function handle(): bool
    {
        $auth = new AdminAuthService();

        if (!$auth->check()) {
            // Check if this is an API request
            if (self::isApiRequest()) {
                Response::error('Unauthorized', 401);
            }

            // Store intended URL
            Session::set('admin_intended_url', $_SERVER['REQUEST_URI']);

            // Redirect to login
            Response::redirect('/admin/login');
            return false;
        }

        return true;
    }

    public static function guest(): bool
    {
        $auth = new AdminAuthService();

        if ($auth->check()) {
            Response::redirect('/admin');
            return false;
        }

        return true;
    }

    public static function permission(string $permission): bool
    {
        $auth = new AdminAuthService();

        if (!$auth->check()) {
            return self::handle();
        }

        if (!$auth->hasPermission($permission)) {
            if (self::isApiRequest()) {
                Response::error('Forbidden', 403);
            }

            Session::flash('error', 'You do not have permission to access this resource.');
            Response::redirect('/admin');
            return false;
        }

        return true;
    }

    public static function role(string $role): bool
    {
        $auth = new AdminAuthService();

        if (!$auth->check()) {
            return self::handle();
        }

        if (!$auth->hasRole($role)) {
            if (self::isApiRequest()) {
                Response::error('Forbidden', 403);
            }

            Session::flash('error', 'You do not have permission to access this resource.');
            Response::redirect('/admin');
            return false;
        }

        return true;
    }

    private static function isApiRequest(): bool
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        return str_contains($contentType, 'application/json') ||
               str_contains($accept, 'application/json') ||
               str_starts_with($_SERVER['REQUEST_URI'], '/api/');
    }
}
