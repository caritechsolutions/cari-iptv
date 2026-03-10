<?php
/**
 * CARI-IPTV Public Ad Serving API Controller
 * Serves ads to the player app via /api/v1/ads/ endpoints.
 */

namespace CariIPTV\Controllers\Api;

use CariIPTV\Services\AdService;

class AdController extends BaseApiController
{
    private AdService $adService;

    public function __construct()
    {
        $this->adService = new AdService();
    }

    /**
     * Serve ads for a given context (zone type, content, platform, etc.)
     * GET /api/v1/ads/serve?zone_type=text_scroller&platform=web&...
     */
    public function serve(): void
    {
        $context = [
            'zone_type' => $_GET['zone_type'] ?? null,
            'zone_slug' => $_GET['zone_slug'] ?? null,
            'channel_id' => !empty($_GET['channel_id']) ? (int) $_GET['channel_id'] : null,
            'content_type' => $_GET['content_type'] ?? null,
            'content_id' => !empty($_GET['content_id']) ? (int) $_GET['content_id'] : null,
            'category_id' => !empty($_GET['category_id']) ? (int) $_GET['category_id'] : null,
            'package_id' => !empty($_GET['package_id']) ? (int) $_GET['package_id'] : null,
            'platform' => $_GET['platform'] ?? null,
            'user_id' => !empty($_GET['user_id']) ? (int) $_GET['user_id'] : null,
            'geo' => $_GET['geo'] ?? null,
            'limit' => !empty($_GET['limit']) ? min(10, (int) $_GET['limit']) : 3,
        ];

        $result = $this->adService->serveAdsEnhanced($context);

        // If result includes a VAST URL (from waterfall/fallback), return redirect
        if (!empty($result['vast_url'])) {
            $this->json([
                'success' => true,
                'source' => $result['source'],
                'vast_url' => $result['vast_url'],
                'ads' => [],
                'count' => 0,
            ]);
            return;
        }

        // Format ads for player
        $formatted = [];
        foreach ($result['ads'] ?? [] as $ad) {
            $entry = [
                'id' => $ad['id'],
                'campaign_id' => $ad['campaign_id'],
                'placement_id' => $ad['placement_id'] ?? null,
                'type' => $ad['type'],
                'zone' => $ad['zone_slug'] ?? null,
                'scroll_text' => $ad['scroll_text'] ?? null,
                'scroll_speed' => $ad['scroll_speed'] ?? null,
                'text_color' => $ad['text_color'] ?? null,
                'bg_color' => $ad['bg_color'] ?? null,
                'bg_opacity' => $ad['bg_opacity'] ?? null,
                'font_size' => $ad['font_size'] ?? 'medium',
                'image_url' => $ad['image_url'] ?? null,
                'image_width' => $ad['image_width'] ?? null,
                'image_height' => $ad['image_height'] ?? null,
                'banner_position' => $ad['banner_position'] ?? null,
                'click_url' => $ad['click_url'] ?? null,
                'click_target' => $ad['click_target'] ?? null,
                'video_url' => $ad['video_url'] ?? null,
                'vast_tag_url' => $ad['vast_tag_url'] ?? null,
                'video_duration' => $ad['video_duration'] ?? null,
                'skip_after' => $ad['skip_after'] ?? null,
                'companion_banner_url' => $ad['companion_banner_url'] ?? null,
                'midroll_offset_type' => $ad['midroll_offset_type'] ?? null,
                'midroll_offset_value' => $ad['midroll_offset_value'] ?? null,
                'alt_text' => $ad['alt_text'] ?? null,
                'ab_variant_id' => $ad['ab_variant_id'] ?? null,
            ];

            // Attach companion ads for video types
            if (in_array($ad['type'], ['pre_roll', 'mid_roll'])) {
                $companions = $this->adService->getCompanionAds((int) $ad['id']);
                if ($companions) {
                    $entry['companions'] = array_map(fn($c) => [
                        'type' => 'banner',
                        'image_url' => $c['image_url'],
                        'position' => $c['banner_position'] ?? 'overlay_bottom',
                        'click_url' => $c['click_url'] ?? null,
                        'click_target' => $c['click_target'] ?? '_blank',
                    ], $companions);
                }
            }

            $formatted[] = $entry;
        }

        $this->json([
            'success' => true,
            'source' => $result['source'] ?? 'direct',
            'pod' => $result['pod'] ?? null,
            'ads' => $formatted,
            'count' => count($formatted),
        ]);
    }

    /**
     * Record an ad impression
     * POST /api/v1/ads/impression
     */
    public function recordImpression(): void
    {
        $data = [
            'campaign_id' => (int) ($_POST['campaign_id'] ?? 0),
            'creative_id' => (int) ($_POST['creative_id'] ?? 0),
            'placement_id' => !empty($_POST['placement_id']) ? (int) $_POST['placement_id'] : null,
            'zone_id' => !empty($_POST['zone_id']) ? (int) $_POST['zone_id'] : null,
            'user_id' => !empty($_POST['user_id']) ? (int) $_POST['user_id'] : null,
            'session_id' => $_POST['session_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'platform' => $_POST['platform'] ?? null,
            'channel_id' => !empty($_POST['channel_id']) ? (int) $_POST['channel_id'] : null,
            'content_type' => $_POST['content_type'] ?? null,
            'content_id' => !empty($_POST['content_id']) ? (int) $_POST['content_id'] : null,
            'revenue' => !empty($_POST['revenue']) ? (float) $_POST['revenue'] : 0,
        ];

        if (!$data['campaign_id'] || !$data['creative_id']) {
            $this->json(['success' => false, 'message' => 'Missing required fields'], 400);
            return;
        }

        $impressionId = $this->adService->recordImpression($data);
        $this->json(['success' => true, 'impression_id' => $impressionId]);
    }

    /**
     * Record an ad event (click, complete, skip, etc.)
     * POST /api/v1/ads/event
     */
    public function recordEvent(): void
    {
        $data = [
            'impression_id' => !empty($_POST['impression_id']) ? (int) $_POST['impression_id'] : null,
            'campaign_id' => (int) ($_POST['campaign_id'] ?? 0),
            'creative_id' => (int) ($_POST['creative_id'] ?? 0),
            'event_type' => $_POST['event_type'] ?? '',
            'user_id' => !empty($_POST['user_id']) ? (int) $_POST['user_id'] : null,
            'session_id' => $_POST['session_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        if (!$data['campaign_id'] || !$data['creative_id'] || !$data['event_type']) {
            $this->json(['success' => false, 'message' => 'Missing required fields'], 400);
            return;
        }

        $eventId = $this->adService->recordEvent($data);
        $this->json(['success' => true, 'event_id' => $eventId]);
    }

    /**
     * Get ad break schedule for VOD content
     * GET /api/v1/ads/breaks?content_type=movie&content_id=123
     */
    public function adBreakSchedule(): void
    {
        $contentType = $_GET['content_type'] ?? 'movie';
        $contentId = (int) ($_GET['content_id'] ?? 0);
        $duration = (float) ($_GET['duration'] ?? 0);

        if (!$contentId || !$duration) {
            $this->json(['success' => true, 'breaks' => []]);
            return;
        }

        $breaks = $this->adService->getAdBreakSchedule($contentType, $contentId, $duration);

        $this->json([
            'success' => true,
            'content_type' => $contentType,
            'content_id' => $contentId,
            'breaks' => $breaks,
            'count' => count($breaks),
        ]);
    }
}
