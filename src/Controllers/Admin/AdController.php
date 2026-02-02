<?php
/**
 * CARI-IPTV Advertising Controller
 * Admin panel management for ad campaigns, creatives, zones, placements, and reporting
 */

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Database;
use CariIPTV\Core\Response;
use CariIPTV\Core\Session;
use CariIPTV\Services\AdminAuthService;
use CariIPTV\Services\AdService;

class AdController
{
    private Database $db;
    private AdminAuthService $auth;
    private AdService $adService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = new AdminAuthService();
        $this->adService = new AdService();
    }

    // =========================================
    // Campaign CRUD
    // =========================================

    /**
     * List all campaigns
     */
    public function index(): void
    {
        $filters = [
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? '',
            'type' => $_GET['type'] ?? '',
            'sort' => $_GET['sort'] ?? 'created_at',
            'dir' => $_GET['dir'] ?? 'DESC',
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? 25,
        ];

        $result = $this->adService->getCampaigns($filters);
        $stats = $this->adService->getStatistics();

        Response::view('admin/ads/index', [
            'pageTitle' => 'Advertising',
            'campaigns' => $result['data'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total_pages' => $result['total_pages'],
            ],
            'filters' => $filters,
            'stats' => $stats,
            'adTypes' => AdService::getAdTypes(),
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Show create campaign form
     */
    public function create(): void
    {
        $zones = $this->adService->getZones();

        Response::view('admin/ads/form', [
            'pageTitle' => 'Create Campaign',
            'campaign' => null,
            'zones' => $zones,
            'adTypes' => AdService::getAdTypes(),
            'targetingTypes' => AdService::getTargetingTypes(),
            'channels' => $this->adService->getChannels(),
            'categories' => $this->adService->getCategories(),
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Store new campaign
     */
    public function store(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/ads/create');
            return;
        }

        $data = $this->validateCampaignData($_POST);
        if (!empty($data['errors'])) {
            Session::flash('error', implode(' ', $data['errors']));
            Response::redirect('/admin/ads/create');
            return;
        }

        unset($data['errors']);

        try {
            $campaignId = $this->adService->createCampaign($data);

            $this->auth->logActivity(
                $this->auth->id(),
                'create',
                'advertising',
                'campaign',
                $campaignId,
                ['name' => $data['name']]
            );

            Session::flash('success', 'Campaign created successfully.');
            Response::redirect('/admin/ads/' . $campaignId . '/edit');
        } catch (\Throwable $e) {
            Session::flash('error', 'Failed to create campaign: ' . $e->getMessage());
            Response::redirect('/admin/ads/create');
        }
    }

    /**
     * Show edit campaign form
     */
    public function edit(int $id): void
    {
        $campaign = $this->adService->getCampaign($id);

        if (!$campaign) {
            Session::flash('error', 'Campaign not found.');
            Response::redirect('/admin/ads');
            return;
        }

        $zones = $this->adService->getZones();

        Response::view('admin/ads/form', [
            'pageTitle' => 'Edit Campaign',
            'campaign' => $campaign,
            'zones' => $zones,
            'adTypes' => AdService::getAdTypes(),
            'targetingTypes' => AdService::getTargetingTypes(),
            'channels' => $this->adService->getChannels(),
            'categories' => $this->adService->getCategories(),
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Update campaign
     */
    public function update(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/ads/' . $id . '/edit');
            return;
        }

        $campaign = $this->adService->getCampaign($id);
        if (!$campaign) {
            Session::flash('error', 'Campaign not found.');
            Response::redirect('/admin/ads');
            return;
        }

        $data = $this->validateCampaignData($_POST);
        if (!empty($data['errors'])) {
            Session::flash('error', implode(' ', $data['errors']));
            Response::redirect('/admin/ads/' . $id . '/edit');
            return;
        }

        unset($data['errors']);

        try {
            $this->adService->updateCampaign($id, $data);

            $this->auth->logActivity(
                $this->auth->id(),
                'update',
                'advertising',
                'campaign',
                $id,
                ['name' => $data['name'] ?? $campaign['name']]
            );

            Session::flash('success', 'Campaign updated successfully.');
            Response::redirect('/admin/ads/' . $id . '/edit');
        } catch (\Throwable $e) {
            Session::flash('error', 'Failed to update campaign: ' . $e->getMessage());
            Response::redirect('/admin/ads/' . $id . '/edit');
        }
    }

    /**
     * Delete campaign
     */
    public function delete(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/ads');
            return;
        }

        $campaign = $this->adService->getCampaign($id);
        if (!$campaign) {
            Session::flash('error', 'Campaign not found.');
            Response::redirect('/admin/ads');
            return;
        }

        try {
            $this->adService->deleteCampaign($id);

            $this->auth->logActivity(
                $this->auth->id(),
                'delete',
                'advertising',
                'campaign',
                $id,
                ['name' => $campaign['name']]
            );

            Session::flash('success', 'Campaign deleted successfully.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Failed to delete campaign: ' . $e->getMessage());
        }

        Response::redirect('/admin/ads');
    }

    /**
     * Toggle campaign status (AJAX)
     */
    public function toggleStatus(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $newStatus = $this->adService->toggleCampaignStatus($id);
        if ($newStatus) {
            Response::json(['success' => true, 'status' => $newStatus]);
        } else {
            Response::json(['success' => false, 'message' => 'Campaign not found']);
        }
    }

    /**
     * Bulk actions
     */
    public function bulkAction(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/ads');
            return;
        }

        $action = $_POST['action'] ?? '';
        $ids = $_POST['ids'] ?? [];

        if (empty($ids)) {
            Session::flash('warning', 'No campaigns selected.');
            Response::redirect('/admin/ads');
            return;
        }

        $ids = array_map('intval', $ids);

        switch ($action) {
            case 'activate':
                $count = $this->adService->bulkUpdateStatus($ids, 'active');
                Session::flash('success', "{$count} campaign(s) activated.");
                break;
            case 'pause':
                $count = $this->adService->bulkUpdateStatus($ids, 'paused');
                Session::flash('success', "{$count} campaign(s) paused.");
                break;
            case 'delete':
                $count = 0;
                foreach ($ids as $id) {
                    if ($this->adService->deleteCampaign($id)) $count++;
                }
                Session::flash('success', "{$count} campaign(s) deleted.");
                break;
            default:
                Session::flash('error', 'Invalid action.');
        }

        Response::redirect('/admin/ads');
    }

    // =========================================
    // Creative CRUD (AJAX)
    // =========================================

    /**
     * Add creative to campaign (AJAX)
     */
    public function addCreative(int $campaignId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $data = $this->validateCreativeData($_POST);
        if (!empty($data['errors'])) {
            Response::json(['success' => false, 'message' => implode(' ', $data['errors'])]);
            return;
        }
        unset($data['errors']);

        try {
            $creativeId = $this->adService->createCreative($campaignId, $data);
            Response::json(['success' => true, 'creative_id' => $creativeId]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Update creative (AJAX)
     */
    public function updateCreative(int $campaignId, int $creativeId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $data = $this->validateCreativeData($_POST);
        if (!empty($data['errors'])) {
            Response::json(['success' => false, 'message' => implode(' ', $data['errors'])]);
            return;
        }
        unset($data['errors']);

        try {
            $this->adService->updateCreative($creativeId, $data);
            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Delete creative (AJAX)
     */
    public function deleteCreative(int $campaignId, int $creativeId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        try {
            $this->adService->deleteCreative($creativeId);
            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================
    // Placements & Targeting (AJAX)
    // =========================================

    /**
     * Add placement (AJAX)
     */
    public function addPlacement(int $campaignId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $data = [
            'campaign_id' => $campaignId,
            'creative_id' => (int) ($_POST['creative_id'] ?? 0),
            'zone_id' => (int) ($_POST['zone_id'] ?? 0),
            'status' => $_POST['status'] ?? 'active',
            'priority' => (int) ($_POST['priority'] ?? 5),
            'start_date' => $_POST['start_date'] ?? null,
            'end_date' => $_POST['end_date'] ?? null,
        ];

        if (!$data['creative_id'] || !$data['zone_id']) {
            Response::json(['success' => false, 'message' => 'Creative and zone are required']);
            return;
        }

        // Parse targeting rules from POST
        $targetingRules = [];
        if (!empty($_POST['targeting_rules'])) {
            $rules = is_string($_POST['targeting_rules'])
                ? json_decode($_POST['targeting_rules'], true)
                : $_POST['targeting_rules'];

            if (is_array($rules)) {
                foreach ($rules as $rule) {
                    if (!empty($rule['rule_type']) && !empty($rule['rule_value'])) {
                        $targetingRules[] = [
                            'rule_type' => $rule['rule_type'],
                            'rule_operator' => $rule['rule_operator'] ?? 'include',
                            'rule_value' => is_array($rule['rule_value']) ? $rule['rule_value'] : [$rule['rule_value']],
                        ];
                    }
                }
            }
        }

        try {
            $placementId = $this->adService->createPlacement($data, $targetingRules);
            Response::json(['success' => true, 'placement_id' => $placementId]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Update placement (AJAX)
     */
    public function updatePlacement(int $campaignId, int $placementId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $data = [];
        if (isset($_POST['creative_id'])) $data['creative_id'] = (int) $_POST['creative_id'];
        if (isset($_POST['zone_id'])) $data['zone_id'] = (int) $_POST['zone_id'];
        if (isset($_POST['status'])) $data['status'] = $_POST['status'];
        if (isset($_POST['priority'])) $data['priority'] = (int) $_POST['priority'];
        if (isset($_POST['start_date'])) $data['start_date'] = $_POST['start_date'];
        if (isset($_POST['end_date'])) $data['end_date'] = $_POST['end_date'];

        try {
            $this->adService->updatePlacement($placementId, $data);

            // Update targeting rules if provided
            if (isset($_POST['targeting_rules'])) {
                $rules = is_string($_POST['targeting_rules'])
                    ? json_decode($_POST['targeting_rules'], true)
                    : $_POST['targeting_rules'];

                $targetingRules = [];
                if (is_array($rules)) {
                    foreach ($rules as $rule) {
                        if (!empty($rule['rule_type']) && !empty($rule['rule_value'])) {
                            $targetingRules[] = [
                                'rule_type' => $rule['rule_type'],
                                'rule_operator' => $rule['rule_operator'] ?? 'include',
                                'rule_value' => is_array($rule['rule_value']) ? $rule['rule_value'] : [$rule['rule_value']],
                            ];
                        }
                    }
                }

                $this->adService->replaceTargetingRules($placementId, $targetingRules);
            }

            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Delete placement (AJAX)
     */
    public function deletePlacement(int $campaignId, int $placementId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        try {
            $this->adService->deletePlacement($placementId);
            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================
    // Zones Management
    // =========================================

    /**
     * Zones management page
     */
    public function zones(): void
    {
        $zones = $this->adService->getZones();

        Response::view('admin/ads/zones', [
            'pageTitle' => 'Ad Zones',
            'zones' => $zones,
            'adTypes' => AdService::getAdTypes(),
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Store zone (AJAX)
     */
    public function storeZone(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => trim($_POST['slug'] ?? ''),
            'zone_type' => $_POST['zone_type'] ?? '',
            'description' => trim($_POST['description'] ?? ''),
            'is_active' => (int) ($_POST['is_active'] ?? 1),
        ];

        if (empty($data['name']) || empty($data['slug']) || empty($data['zone_type'])) {
            Response::json(['success' => false, 'message' => 'Name, slug, and type are required']);
            return;
        }

        try {
            $zoneId = $this->adService->createZone($data);
            Response::json(['success' => true, 'zone_id' => $zoneId]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Update zone (AJAX)
     */
    public function updateZone(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $data = [];
        if (isset($_POST['name'])) $data['name'] = trim($_POST['name']);
        if (isset($_POST['slug'])) $data['slug'] = trim($_POST['slug']);
        if (isset($_POST['zone_type'])) $data['zone_type'] = $_POST['zone_type'];
        if (isset($_POST['description'])) $data['description'] = trim($_POST['description']);
        if (isset($_POST['is_active'])) $data['is_active'] = (int) $_POST['is_active'];

        try {
            $this->adService->updateZone($id, $data);
            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Toggle zone (AJAX)
     */
    public function toggleZone(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $this->adService->toggleZone($id);
        Response::json(['success' => true]);
    }

    /**
     * Delete zone (AJAX)
     */
    public function deleteZone(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        try {
            $this->adService->deleteZone($id);
            Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================
    // Reports
    // =========================================

    /**
     * Reports dashboard
     */
    public function reports(): void
    {
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');

        $report = $this->adService->getOverallReport($startDate, $endDate);
        $stats = $this->adService->getStatistics();

        Response::view('admin/ads/reports', [
            'pageTitle' => 'Ad Reports',
            'report' => $report,
            'stats' => $stats,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'adTypes' => AdService::getAdTypes(),
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Get campaign report data (AJAX)
     */
    public function campaignReport(int $id): void
    {
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');

        $report = $this->adService->getCampaignReport($id, $startDate, $endDate);
        Response::json(['success' => true, 'report' => $report]);
    }

    // =========================================
    // Ad Serving API (Public endpoints)
    // =========================================

    /**
     * Serve ads for a given context
     * Called by the player/app to get ads to display
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

        $ads = $this->adService->getAdsForContext($context);

        // Format for player
        $formatted = [];
        foreach ($ads as $ad) {
            $formatted[] = [
                'id' => $ad['id'],
                'campaign_id' => $ad['campaign_id'],
                'placement_id' => $ad['placement_id'],
                'type' => $ad['type'],
                'zone' => $ad['zone_slug'],
                // Text scroller
                'scroll_text' => $ad['scroll_text'],
                'scroll_speed' => $ad['scroll_speed'],
                'text_color' => $ad['text_color'],
                'bg_color' => $ad['bg_color'],
                'bg_opacity' => $ad['bg_opacity'],
                // Banner
                'image_url' => $ad['image_url'],
                'image_width' => $ad['image_width'],
                'image_height' => $ad['image_height'],
                'banner_position' => $ad['banner_position'],
                'click_url' => $ad['click_url'],
                'click_target' => $ad['click_target'],
                // Video
                'video_url' => $ad['video_url'],
                'vast_tag_url' => $ad['vast_tag_url'],
                'video_duration' => $ad['video_duration'],
                'skip_after' => $ad['skip_after'],
                'companion_banner_url' => $ad['companion_banner_url'],
                // Mid-roll
                'midroll_offset_type' => $ad['midroll_offset_type'],
                'midroll_offset_value' => $ad['midroll_offset_value'],
                // Meta
                'alt_text' => $ad['alt_text'],
                'tracking' => [
                    'impression_url' => '/admin/ads/api/impression',
                    'click_url' => '/admin/ads/api/event',
                    'event_url' => '/admin/ads/api/event',
                ],
            ];
        }

        Response::json([
            'success' => true,
            'ads' => $formatted,
            'count' => count($formatted),
        ]);
    }

    /**
     * Record impression (AJAX from player)
     */
    public function recordImpression(): void
    {
        $data = [
            'campaign_id' => (int) ($_POST['campaign_id'] ?? 0),
            'creative_id' => (int) ($_POST['creative_id'] ?? 0),
            'placement_id' => !empty($_POST['placement_id']) ? (int) $_POST['placement_id'] : null,
            'zone_id' => !empty($_POST['zone_id']) ? (int) $_POST['zone_id'] : null,
            'user_id' => !empty($_POST['user_id']) ? (int) $_POST['user_id'] : null,
            'session_id' => $_POST['session_id'] ?? session_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'platform' => $_POST['platform'] ?? null,
            'channel_id' => !empty($_POST['channel_id']) ? (int) $_POST['channel_id'] : null,
            'content_type' => $_POST['content_type'] ?? null,
            'content_id' => !empty($_POST['content_id']) ? (int) $_POST['content_id'] : null,
            'revenue' => !empty($_POST['revenue']) ? (float) $_POST['revenue'] : 0,
        ];

        if (!$data['campaign_id'] || !$data['creative_id']) {
            Response::json(['success' => false, 'message' => 'Missing required fields']);
            return;
        }

        $impressionId = $this->adService->recordImpression($data);
        Response::json(['success' => true, 'impression_id' => $impressionId]);
    }

    /**
     * Record ad event (click, complete, skip, etc.)
     */
    public function recordEvent(): void
    {
        $data = [
            'impression_id' => !empty($_POST['impression_id']) ? (int) $_POST['impression_id'] : null,
            'campaign_id' => (int) ($_POST['campaign_id'] ?? 0),
            'creative_id' => (int) ($_POST['creative_id'] ?? 0),
            'event_type' => $_POST['event_type'] ?? '',
            'user_id' => !empty($_POST['user_id']) ? (int) $_POST['user_id'] : null,
            'session_id' => $_POST['session_id'] ?? session_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        if (!$data['campaign_id'] || !$data['creative_id'] || !$data['event_type']) {
            Response::json(['success' => false, 'message' => 'Missing required fields']);
            return;
        }

        $eventId = $this->adService->recordEvent($data);
        Response::json(['success' => true, 'event_id' => $eventId]);
    }

    // =========================================
    // VAST XML generation
    // =========================================

    /**
     * Generate VAST XML for pre-roll/mid-roll ads
     */
    public function vastXml(): void
    {
        $context = [
            'zone_type' => $_GET['zone_type'] ?? 'pre_roll',
            'zone_slug' => $_GET['zone_slug'] ?? null,
            'channel_id' => !empty($_GET['channel_id']) ? (int) $_GET['channel_id'] : null,
            'content_type' => $_GET['content_type'] ?? null,
            'content_id' => !empty($_GET['content_id']) ? (int) $_GET['content_id'] : null,
            'package_id' => !empty($_GET['package_id']) ? (int) $_GET['package_id'] : null,
            'platform' => $_GET['platform'] ?? null,
            'user_id' => !empty($_GET['user_id']) ? (int) $_GET['user_id'] : null,
            'limit' => 1,
        ];

        $ads = $this->adService->getAdsForContext($context);

        header('Content-Type: application/xml; charset=utf-8');

        if (empty($ads)) {
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<VAST version="4.2"></VAST>';
            exit;
        }

        $ad = $ads[0];
        $baseUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<VAST version="4.2">' . "\n";
        $xml .= '  <Ad id="' . $ad['id'] . '">' . "\n";
        $xml .= '    <InLine>' . "\n";
        $xml .= '      <AdSystem>CARI-IPTV Ad Server</AdSystem>' . "\n";
        $xml .= '      <AdTitle>' . htmlspecialchars($ad['name'] ?? 'Ad') . '</AdTitle>' . "\n";
        $xml .= '      <Impression><![CDATA[' . $baseUrl . '/admin/ads/api/impression?campaign_id=' . $ad['campaign_id'] . '&creative_id=' . $ad['id'] . ']]></Impression>' . "\n";

        if ($ad['video_url']) {
            $xml .= '      <Creatives>' . "\n";
            $xml .= '        <Creative>' . "\n";
            $xml .= '          <Linear' . ($ad['skip_after'] ? ' skipoffset="00:00:' . str_pad($ad['skip_after'], 2, '0', STR_PAD_LEFT) . '"' : '') . '>' . "\n";

            if ($ad['video_duration']) {
                $hours = floor($ad['video_duration'] / 3600);
                $minutes = floor(($ad['video_duration'] % 3600) / 60);
                $seconds = $ad['video_duration'] % 60;
                $xml .= '            <Duration>' . sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds) . '</Duration>' . "\n";
            }

            $xml .= '            <TrackingEvents>' . "\n";
            foreach (['start', 'firstQuartile', 'midpoint', 'thirdQuartile', 'complete'] as $event) {
                $xml .= '              <Tracking event="' . $event . '"><![CDATA[' . $baseUrl . '/admin/ads/api/event?event_type=' . $event . '&campaign_id=' . $ad['campaign_id'] . '&creative_id=' . $ad['id'] . ']]></Tracking>' . "\n";
            }
            $xml .= '            </TrackingEvents>' . "\n";

            if ($ad['click_url']) {
                $xml .= '            <VideoClicks>' . "\n";
                $xml .= '              <ClickThrough><![CDATA[' . htmlspecialchars($ad['click_url']) . ']]></ClickThrough>' . "\n";
                $xml .= '              <ClickTracking><![CDATA[' . $baseUrl . '/admin/ads/api/event?event_type=click&campaign_id=' . $ad['campaign_id'] . '&creative_id=' . $ad['id'] . ']]></ClickTracking>' . "\n";
                $xml .= '            </VideoClicks>' . "\n";
            }

            $xml .= '            <MediaFiles>' . "\n";
            $xml .= '              <MediaFile delivery="progressive" type="video/mp4" width="1920" height="1080">' . "\n";
            $xml .= '                <![CDATA[' . htmlspecialchars($ad['video_url']) . ']]>' . "\n";
            $xml .= '              </MediaFile>' . "\n";
            $xml .= '            </MediaFiles>' . "\n";

            $xml .= '          </Linear>' . "\n";
            $xml .= '        </Creative>' . "\n";

            // Companion banner
            if ($ad['companion_banner_url']) {
                $xml .= '        <Creative>' . "\n";
                $xml .= '          <CompanionAds>' . "\n";
                $xml .= '            <Companion width="728" height="90">' . "\n";
                $xml .= '              <StaticResource creativeType="image/jpeg"><![CDATA[' . htmlspecialchars($ad['companion_banner_url']) . ']]></StaticResource>' . "\n";
                $xml .= '            </Companion>' . "\n";
                $xml .= '          </CompanionAds>' . "\n";
                $xml .= '        </Creative>' . "\n";
            }

            $xml .= '      </Creatives>' . "\n";
        }

        $xml .= '    </InLine>' . "\n";
        $xml .= '  </Ad>' . "\n";
        $xml .= '</VAST>';

        echo $xml;
        exit;
    }

    // =========================================
    // Validation
    // =========================================

    private function validateCampaignData(array $post): array
    {
        $data = [];
        $errors = [];

        $name = trim($post['name'] ?? '');
        if (empty($name)) {
            $errors[] = 'Campaign name is required.';
        } elseif (strlen($name) > 255) {
            $errors[] = 'Campaign name cannot exceed 255 characters.';
        } else {
            $data['name'] = $name;
        }

        $data['advertiser'] = trim($post['advertiser'] ?? '') ?: null;
        $data['description'] = trim($post['description'] ?? '') ?: null;

        if (isset($post['status'])) {
            $allowed = ['draft', 'active', 'paused', 'completed', 'archived'];
            $data['status'] = in_array($post['status'], $allowed) ? $post['status'] : 'draft';
        }

        $data['priority'] = max(1, min(10, (int) ($post['priority'] ?? 5)));
        $data['start_date'] = !empty($post['start_date']) ? $post['start_date'] : null;
        $data['end_date'] = !empty($post['end_date']) ? $post['end_date'] : null;
        $data['daily_budget'] = !empty($post['daily_budget']) ? (float) $post['daily_budget'] : null;
        $data['total_budget'] = !empty($post['total_budget']) ? (float) $post['total_budget'] : null;
        $data['daily_impressions_cap'] = !empty($post['daily_impressions_cap']) ? (int) $post['daily_impressions_cap'] : null;
        $data['total_impressions_cap'] = !empty($post['total_impressions_cap']) ? (int) $post['total_impressions_cap'] : null;
        $data['frequency_cap'] = !empty($post['frequency_cap']) ? (int) $post['frequency_cap'] : null;

        $data['errors'] = $errors;
        return $data;
    }

    private function validateCreativeData(array $post): array
    {
        $data = [];
        $errors = [];

        $name = trim($post['name'] ?? '');
        if (empty($name)) {
            $errors[] = 'Creative name is required.';
        } else {
            $data['name'] = $name;
        }

        $type = $post['type'] ?? '';
        $allowedTypes = ['text_scroller', 'banner', 'pre_roll', 'mid_roll'];
        if (!in_array($type, $allowedTypes)) {
            $errors[] = 'Invalid creative type.';
        } else {
            $data['type'] = $type;
        }

        $data['status'] = in_array($post['status'] ?? '', ['draft', 'active', 'paused', 'archived'])
            ? $post['status'] : 'draft';

        // Type-specific fields
        $data['scroll_text'] = trim($post['scroll_text'] ?? '') ?: null;
        $data['scroll_speed'] = in_array($post['scroll_speed'] ?? '', ['slow', 'normal', 'fast'])
            ? $post['scroll_speed'] : 'normal';
        $data['text_color'] = $post['text_color'] ?? '#FFFFFF';
        $data['bg_color'] = $post['bg_color'] ?? '#000000';
        $data['bg_opacity'] = isset($post['bg_opacity']) ? max(0, min(1, (float) $post['bg_opacity'])) : 0.80;

        $data['image_url'] = trim($post['image_url'] ?? '') ?: null;
        $data['image_width'] = !empty($post['image_width']) ? (int) $post['image_width'] : null;
        $data['image_height'] = !empty($post['image_height']) ? (int) $post['image_height'] : null;
        $data['banner_position'] = in_array($post['banner_position'] ?? '', ['top', 'bottom', 'overlay_bottom', 'overlay_top', 'sidebar'])
            ? $post['banner_position'] : 'bottom';
        $data['click_url'] = trim($post['click_url'] ?? '') ?: null;
        $data['click_target'] = in_array($post['click_target'] ?? '', ['_blank', '_self', 'deeplink'])
            ? $post['click_target'] : '_blank';

        $data['video_url'] = trim($post['video_url'] ?? '') ?: null;
        $data['vast_tag_url'] = trim($post['vast_tag_url'] ?? '') ?: null;
        $data['video_duration'] = !empty($post['video_duration']) ? (int) $post['video_duration'] : null;
        $data['skip_after'] = !empty($post['skip_after']) ? (int) $post['skip_after'] : null;
        $data['companion_banner_url'] = trim($post['companion_banner_url'] ?? '') ?: null;

        $data['midroll_offset_type'] = in_array($post['midroll_offset_type'] ?? '', ['seconds', 'percent', 'cue'])
            ? $post['midroll_offset_type'] : 'percent';
        $data['midroll_offset_value'] = trim($post['midroll_offset_value'] ?? '') ?: null;

        $data['alt_text'] = trim($post['alt_text'] ?? '') ?: null;
        $data['weight'] = max(1, min(1000, (int) ($post['weight'] ?? 100)));

        $data['errors'] = $errors;
        return $data;
    }
}
