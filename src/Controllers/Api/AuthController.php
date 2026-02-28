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
     * Body: { first_name, last_name, email, password, password_confirm, phone?, country?, birthday? }
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
        ]);

        if (!$result['success']) {
            $this->error($result['error'], 422, 'VALIDATION_ERROR');
            return;
        }

        $this->json([
            'data' => [
                'requires_verification' => true,
                'message' => $result['message'],
            ],
        ], 201);
    }

    /**
     * GET /api/v1/auth/verify-email/{token}
     */
    public function verifyEmail(string $token): void
    {
        $result = $this->auth->verifyEmail($token);

        if (!$result['success']) {
            $this->error($result['error'], 400, 'VERIFICATION_FAILED');
            return;
        }

        $this->json(['data' => ['message' => $result['message']]], 200);
    }

    /**
     * POST /api/v1/auth/resend-verification
     * Body: { email }
     */
    public function resendVerification(): void
    {
        $input = $this->getJsonInput();
        $email = trim($input['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid email address is required', 400, 'VALIDATION_ERROR');
            return;
        }

        $result = $this->auth->resendVerification($email);

        $this->json(['data' => ['message' => $result['message']]], 200);
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
            $code = !empty($result['needs_verification']) ? 'EMAIL_NOT_VERIFIED' : 'AUTH_FAILED';
            $response = ['error' => ['message' => $result['error'], 'code' => $code]];
            if (!empty($result['needs_verification'])) {
                $response['error']['needs_verification'] = true;
                $response['error']['email'] = $result['email'];
            }
            $this->json($response, 401);
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
     * GET /api/v1/auth/watch-progress?content_type=movie&content_id=123
     * Returns progress for a single content item
     */
    public function getWatchProgress(): void
    {
        $subscriberId = $this->auth->validateRequest();
        if (!$subscriberId) {
            $this->error('Unauthorized', 401, 'UNAUTHORIZED');
            return;
        }

        $contentType = $_GET['content_type'] ?? 'movie';
        $contentId = (int) ($_GET['content_id'] ?? 0);

        if ($contentId <= 0) {
            $this->error('content_id is required', 400, 'VALIDATION_ERROR');
            return;
        }

        $progress = $this->auth->getWatchProgress($subscriberId, $contentType, $contentId);
        $this->json(['data' => $progress], 200);
    }

    /**
     * GET /api/v1/auth/watch-progress/batch?content_type=episode&ids=1,2,3
     * Returns progress for multiple content items
     */
    public function getBatchWatchProgress(): void
    {
        $subscriberId = $this->auth->validateRequest();
        if (!$subscriberId) {
            $this->error('Unauthorized', 401, 'UNAUTHORIZED');
            return;
        }

        $contentType = $_GET['content_type'] ?? 'episode';
        $idsStr = $_GET['ids'] ?? '';

        if (empty($idsStr)) {
            $this->json(['data' => []], 200);
            return;
        }

        $ids = array_filter(array_map('intval', explode(',', $idsStr)), fn($id) => $id > 0);
        if (empty($ids)) {
            $this->json(['data' => []], 200);
            return;
        }

        // Limit to 100 IDs per request
        $ids = array_slice($ids, 0, 100);

        $progress = $this->auth->getBatchWatchProgress($subscriberId, $contentType, $ids);
        $this->json(['data' => $progress], 200);
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
    // SUBSCRIPTIONS
    // =========================================================================

    /**
     * POST /api/v1/auth/subscribe
     * Body: { package_id }
     * Subscribe the authenticated user to a package (free or trial)
     */
    public function subscribe(): void
    {
        $subscriberId = $this->auth->validateRequest();
        if (!$subscriberId) {
            $this->error('Unauthorized', 401, 'UNAUTHORIZED');
            return;
        }

        $input = $this->getJsonInput();
        $packageId = (int) ($input['package_id'] ?? 0);

        if ($packageId <= 0) {
            $this->error('Package ID is required', 400, 'VALIDATION_ERROR');
            return;
        }

        $result = $this->auth->subscribe($subscriberId, $packageId);

        if (!$result['success']) {
            $status = !empty($result['requires_payment']) ? 402 : 422;
            $code = !empty($result['requires_payment']) ? 'PAYMENT_REQUIRED' : 'SUBSCRIPTION_ERROR';
            $this->error($result['error'], $status, $code);
            return;
        }

        $this->json(['data' => $result], 200);
    }

    /**
     * POST /api/v1/auth/unsubscribe
     * Body: { package_id }
     * Cancel the authenticated user's subscription to a package
     */
    public function unsubscribe(): void
    {
        $subscriberId = $this->auth->validateRequest();
        if (!$subscriberId) {
            $this->error('Unauthorized', 401, 'UNAUTHORIZED');
            return;
        }

        $input = $this->getJsonInput();
        $packageId = (int) ($input['package_id'] ?? 0);

        if ($packageId <= 0) {
            $this->error('Package ID is required', 400, 'VALIDATION_ERROR');
            return;
        }

        $result = $this->auth->unsubscribe($subscriberId, $packageId);

        if (!$result['success']) {
            $this->error($result['error'], 422, 'SUBSCRIPTION_ERROR');
            return;
        }

        $this->json(['data' => $result], 200);
    }

    /**
     * POST /api/v1/auth/update-profile
     * Body: { adult_enabled?, parental_pin? }
     */
    public function updateProfile(): void
    {
        $subscriberId = $this->auth->validateRequest();
        if (!$subscriberId) {
            $this->error('Unauthorized', 401, 'UNAUTHORIZED');
            return;
        }

        $input = $this->getJsonInput();
        $data = [];

        if (array_key_exists('adult_enabled', $input)) {
            $data['adult_enabled'] = (bool) $input['adult_enabled'];
        }
        if (array_key_exists('parental_pin', $input)) {
            $data['parental_pin'] = $input['parental_pin'];
        }

        $result = $this->auth->updateProfile($subscriberId, $data);

        if (!$result['success']) {
            $this->error($result['error'], 422, 'VALIDATION_ERROR');
            return;
        }

        $this->json(['data' => $result], 200);
    }

    // =========================================================================
    // ENTITLEMENTS
    // =========================================================================

    /**
     * GET /api/v1/auth/entitlements
     * Returns the subscriber's entitled content based on their active packages
     */
    public function entitlements(): void
    {
        $subscriberId = $this->auth->validateRequest();
        if (!$subscriberId) {
            $this->error('Unauthorized', 401, 'UNAUTHORIZED');
            return;
        }

        $service = new \CariIPTV\Services\ContentApiService();
        $data = $service->getSubscriberEntitlements($subscriberId);

        // Also get subscriber's adult_enabled status
        $profile = $this->auth->getProfile($subscriberId);
        $data['adult_enabled'] = (bool) ($profile['adult_enabled'] ?? false);

        $this->json(['data' => $data], 200);
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
