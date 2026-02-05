<?php
/**
 * CARI-IPTV Subscriber Auth API Controller
 * Handles login, token refresh, logout, and profile
 */

namespace CariIPTV\Controllers\Api;

use CariIPTV\Services\SubscriberAuthService;

class AuthController extends BaseApiController
{
    private SubscriberAuthService $auth;

    public function __construct()
    {
        $this->auth = new SubscriberAuthService();
    }

    /**
     * POST /api/v1/auth/register
     * Body: { first_name, last_name, email, password, password_confirm, phone?, country?, birthday?, device_type? }
     */
    public function register(): void
    {
        $input = $this->getJsonInput();

        $result = $this->auth->register([
            'first_name' => $input['first_name'] ?? '',
            'last_name' => $input['last_name'] ?? '',
            'email' => $input['email'] ?? '',
            'password' => $input['password'] ?? '',
            'password_confirm' => $input['password_confirm'] ?? '',
            'phone' => $input['phone'] ?? '',
            'country' => $input['country'] ?? '',
            'birthday' => $input['birthday'] ?? '',
            'device_info' => [
                'device_name' => $input['device_name'] ?? null,
                'device_type' => $input['device_type'] ?? 'web',
            ],
        ]);

        if (!$result['success']) {
            $this->error($result['error'], 422, 'VALIDATION_ERROR');
            return;
        }

        header('Cache-Control: no-store');
        $this->json([
            'data' => [
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'],
                'expires_in' => $result['expires_in'],
                'token_type' => $result['token_type'],
                'user' => $result['user'],
            ],
        ], 201);
    }

    /**
     * POST /api/v1/auth/login
     * Body: { identity, password, device_name?, device_type? }
     */
    public function login(): void
    {
        $input = $this->getJsonInput();

        $identity = trim($input['identity'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($identity) || empty($password)) {
            $this->error('Username/email and password are required', 400, 'VALIDATION_ERROR');
            return;
        }

        $result = $this->auth->login($identity, $password, [
            'device_name' => $input['device_name'] ?? null,
            'device_type' => $input['device_type'] ?? 'web',
        ]);

        if (!$result['success']) {
            $this->error($result['error'], 401, 'AUTH_FAILED');
            return;
        }

        header('Cache-Control: no-store');
        $this->json([
            'data' => [
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'],
                'expires_in' => $result['expires_in'],
                'token_type' => $result['token_type'],
                'user' => $result['user'],
            ],
        ], 200);
    }

    /**
     * POST /api/v1/auth/refresh
     * Body: { refresh_token }
     */
    public function refresh(): void
    {
        $input = $this->getJsonInput();
        $refreshToken = $input['refresh_token'] ?? '';

        if (empty($refreshToken)) {
            $this->error('Refresh token is required', 400, 'VALIDATION_ERROR');
            return;
        }

        $result = $this->auth->refresh($refreshToken);

        if (!$result['success']) {
            $this->error($result['error'], 401, 'TOKEN_EXPIRED');
            return;
        }

        header('Cache-Control: no-store');
        $this->json([
            'data' => [
                'access_token' => $result['access_token'],
                'expires_in' => $result['expires_in'],
                'token_type' => $result['token_type'],
            ],
        ], 200);
    }

    /**
     * POST /api/v1/auth/logout
     * Body: { refresh_token }
     */
    public function logout(): void
    {
        $input = $this->getJsonInput();
        $refreshToken = $input['refresh_token'] ?? '';

        if (!empty($refreshToken)) {
            $this->auth->logout($refreshToken);
        }

        $this->json(['data' => ['message' => 'Logged out']], 200);
    }

    /**
     * GET /api/v1/auth/me
     * Requires: Authorization: Bearer {access_token}
     */
    public function me(): void
    {
        $subscriberId = $this->auth->validateRequest();

        if (!$subscriberId) {
            $this->error('Unauthorized', 401, 'UNAUTHORIZED');
            return;
        }

        $profile = $this->auth->getProfile($subscriberId);

        if (!$profile) {
            $this->error('Account not found or inactive', 403, 'ACCOUNT_INACTIVE');
            return;
        }

        $this->json(['data' => $profile], 200);
    }

    // =========================================================================
    // WATCH HISTORY & WATCHLIST
    // =========================================================================

    /**
     * POST /api/v1/auth/watch-progress
     * Body: { content_type, content_id, progress, duration }
     */
    public function updateWatchProgress(): void
    {
        $subscriberId = $this->auth->validateRequest();
        if (!$subscriberId) {
            $this->error('Unauthorized', 401, 'UNAUTHORIZED');
            return;
        }

        $input = $this->getJsonInput();
        $this->auth->updateWatchProgress(
            $subscriberId,
            $input['content_type'] ?? 'movie',
            (int) ($input['content_id'] ?? 0),
            (int) ($input['progress'] ?? 0),
            (int) ($input['duration'] ?? 0)
        );

        $this->json(['data' => ['saved' => true]], 200);
    }

    /**
     * GET /api/v1/auth/continue-watching
     */
    public function continueWatching(): void
    {
        $subscriberId = $this->auth->validateRequest();
        if (!$subscriberId) {
            $this->error('Unauthorized', 401, 'UNAUTHORIZED');
            return;
        }

        $items = $this->auth->getContinueWatching($subscriberId);
        $this->json(['data' => $items], 200);
    }

    /**
     * POST /api/v1/auth/watchlist/toggle
     * Body: { content_type, content_id }
     */
    public function toggleWatchlist(): void
    {
        $subscriberId = $this->auth->validateRequest();
        if (!$subscriberId) {
            $this->error('Unauthorized', 401, 'UNAUTHORIZED');
            return;
        }

        $input = $this->getJsonInput();
        $added = $this->auth->toggleWatchlist(
            $subscriberId,
            $input['content_type'] ?? 'movie',
            (int) ($input['content_id'] ?? 0)
        );

        $this->json(['data' => ['in_watchlist' => $added]], 200);
    }

    /**
     * GET /api/v1/auth/watchlist
     */
    public function getWatchlist(): void
    {
        $subscriberId = $this->auth->validateRequest();
        if (!$subscriberId) {
            $this->error('Unauthorized', 401, 'UNAUTHORIZED');
            return;
        }

        $items = $this->auth->getWatchlist($subscriberId);
        $this->json(['data' => $items], 200);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
