<?php
/**
 * CARI-IPTV Recommendation & Analytics API Controller
 * Endpoints for event collection and recommendation retrieval.
 */

namespace CariIPTV\Controllers\Api;

use CariIPTV\Services\AnalyticsService;
use CariIPTV\Services\RecommendationService;
use CariIPTV\Services\SubscriberAuthService;

class RecommendationController extends BaseApiController
{
    private AnalyticsService $analytics;
    private RecommendationService $recommendations;
    private SubscriberAuthService $auth;

    public function __construct()
    {
        $this->analytics = new AnalyticsService();
        $this->recommendations = new RecommendationService();
        $this->auth = new SubscriberAuthService();
    }

    // =========================================================================
    // EVENT COLLECTION
    // =========================================================================

    /**
     * POST /api/v1/analytics/event
     * Record a single behavioral event
     */
    public function recordEvent(): void
    {
        $subscriberId = $this->auth->validateRequest();
        if (!$subscriberId) {
            $this->error('Unauthorized', 401, 'UNAUTHORIZED');
            return;
        }

        $input = $this->getJsonInput();

        $success = $this->analytics->recordEvent(
            $subscriberId,
            $input['event_type'] ?? '',
            $input['content_type'] ?? null,
            isset($input['content_id']) ? (int) $input['content_id'] : null,
            $input['page'] ?? null,
            $input['metadata'] ?? null,
            $input['session_id'] ?? null,
            $input['platform'] ?? null
        );

        if (!$success) {
            $this->error('Invalid event type', 422, 'VALIDATION_ERROR');
            return;
        }

        $this->json(['success' => true], 200);
    }

    /**
     * POST /api/v1/analytics/batch
     * Record a batch of events (for buffered client sends)
     */
    public function recordBatch(): void
    {
        $subscriberId = $this->auth->validateRequest();
        if (!$subscriberId) {
            $this->error('Unauthorized', 401, 'UNAUTHORIZED');
            return;
        }

        $input = $this->getJsonInput();
        $events = $input['events'] ?? [];

        if (!is_array($events) || empty($events)) {
            $this->error('events array is required', 400, 'VALIDATION_ERROR');
            return;
        }

        if (count($events) > 50) {
            $this->error('Maximum 50 events per batch', 400, 'VALIDATION_ERROR');
            return;
        }

        $recorded = $this->analytics->recordBatch(
            $subscriberId,
            $events,
            $input['session_id'] ?? null,
            $input['platform'] ?? null
        );

        $this->json(['success' => true, 'recorded' => $recorded], 200);
    }

    // =========================================================================
    // RECOMMENDATIONS
    // =========================================================================

    /**
     * GET /api/v1/recommendations
     * Get personalized recommendations for the authenticated subscriber
     */
    public function getRecommendations(): void
    {
        $subscriberId = $this->auth->validateRequest();
        if (!$subscriberId) {
            $this->error('Unauthorized', 401, 'UNAUTHORIZED');
            return;
        }

        $filterType = $this->query('type'); // 'movie' or 'series'

        // Check if we need to generate recommendations on-demand
        if ($this->recommendations->needsRefresh($subscriberId)) {
            // Generate profile + recommendations synchronously on first request
            // (subsequent requests will be served from cache, refreshed by cron)
            try {
                $this->recommendations->generateProfile($subscriberId);
                $this->recommendations->generateRecommendations($subscriberId);
            } catch (\Throwable $e) {
                error_log('On-demand recommendation generation failed for subscriber ' . $subscriberId . ': ' . $e->getMessage());
                // Continue — may still have stale recommendations
            }
        }

        $sets = $this->recommendations->getEnrichedRecommendations($subscriberId, $filterType);

        // No cache for personalized content
        header('Cache-Control: private, no-cache');
        $this->ok($sets);
    }

    /**
     * GET /api/v1/recommendations/profile
     * Get the subscriber's taste profile (for "My Profile" / debug)
     */
    public function getProfile(): void
    {
        $subscriberId = $this->auth->validateRequest();
        if (!$subscriberId) {
            $this->error('Unauthorized', 401, 'UNAUTHORIZED');
            return;
        }

        $profile = $this->recommendations->getProfile($subscriberId);
        if (!$profile) {
            $this->ok(null);
            return;
        }

        // Decode JSON fields for the response
        $profile['genre_affinities'] = json_decode($profile['genre_affinities'] ?? '{}', true);
        $profile['actor_affinities'] = json_decode($profile['actor_affinities'] ?? '[]', true);
        $profile['viewing_patterns'] = json_decode($profile['viewing_patterns'] ?? '{}', true);
        $profile['content_preferences'] = json_decode($profile['content_preferences'] ?? '{}', true);

        header('Cache-Control: private, no-cache');
        $this->ok($profile);
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
