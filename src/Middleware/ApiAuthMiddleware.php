<?php
/**
 * CARI-IPTV API Authentication Middleware
 * Validates JWT Bearer tokens on protected API endpoints
 */

namespace CariIPTV\Middleware;

use CariIPTV\Services\JwtService;

class ApiAuthMiddleware
{
    /**
     * Validate the request has a valid Bearer token
     * Returns false to halt request, or sets subscriber ID for downstream use
     */
    public static function handle(): bool
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!str_starts_with($header, 'Bearer ')) {
            self::unauthorized('Authentication required');
            return false;
        }

        $token = substr($header, 7);
        $jwt = new JwtService();
        $payload = $jwt->validateAccessToken($token);

        if (!$payload || empty($payload['sub'])) {
            self::unauthorized('Invalid or expired token');
            return false;
        }

        // Store subscriber ID for controllers to use
        $_REQUEST['__subscriber_id'] = (int) $payload['sub'];

        return true;
    }

    private static function unauthorized(string $message): void
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('WWW-Authenticate: Bearer');
        echo json_encode([
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => $message,
            ],
        ]);
        exit;
    }
}
