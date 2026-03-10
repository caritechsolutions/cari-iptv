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
            'packages' => $this->adService->getPackages(),
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
            'packages' => $this->adService->getPackages(),
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
    public function addCreative(int $id): void
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
            $creativeId = $this->adService->createCreative($id, $data);
            Response::json(['success' => true, 'creative_id' => $creativeId]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Update creative (AJAX)
     */
    public function updateCreative(int $id, int $creativeId): void
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
    public function deleteCreative(int $id, int $creativeId): void
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
    public function addPlacement(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $data = [
            'campaign_id' => $id,
            'creative_id' => (int) ($_POST['creative_id'] ?? 0),
            'zone_id' => (int) ($_POST['zone_id'] ?? 0),
            'status' => $_POST['status'] ?? 'active',
            'priority' => (int) ($_POST['priority'] ?? 5),
            'start_date' => $_POST['start_date'] ?? null,
            'end_date' => $_POST['end_date'] ?? null,
        ];

        if (!$data['creative_id'] || !$data['zone_id']) {
            Response::json(['success' => false, 'message' => 'Ad and zone are required']);
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
    public function updatePlacement(int $id, int $placementId): void
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
    public function deletePlacement(int $id, int $placementId): void
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
        $settings = new \CariIPTV\Services\SettingsService();
        $baseUrl = rtrim($settings->get('site_url', '', 'general') ?: getenv('APP_URL') ?: 'http://localhost', '/');

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
    // AI Generation & File Uploads
    // =========================================

    /**
     * Generate ad text with AI (AJAX)
     */
    public function generateAdText(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $prompt = trim($_POST['prompt'] ?? '');
        $adType = $_POST['ad_type'] ?? 'text_scroller';
        $context = [
            'advertiser' => $_POST['advertiser'] ?? '',
            'campaign_name' => $_POST['campaign_name'] ?? '',
        ];

        if (empty($prompt)) {
            Response::json(['success' => false, 'message' => 'Please enter a prompt']);
            return;
        }

        $aiService = new \CariIPTV\Services\AIService();

        if (!$aiService->isAvailable()) {
            Response::json(['success' => false, 'message' => 'AI is not configured. Go to Settings > AI to set up a provider.']);
            return;
        }

        $text = $aiService->generateAdText($prompt, $adType, $context);

        if ($text) {
            Response::json(['success' => true, 'text' => trim($text)]);
        } else {
            Response::json(['success' => false, 'message' => 'AI generation failed. Check your AI provider settings.']);
        }
    }

    /**
     * Generate banner image with AI / DALL-E 3 (AJAX)
     */
    public function generateAdImage(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $prompt = trim($_POST['prompt'] ?? '');
        $size = $_POST['size'] ?? '1792x1024';
        $campaignId = (int) ($_POST['campaign_id'] ?? 0);

        if (empty($prompt)) {
            Response::json(['success' => false, 'message' => 'Please enter an image description']);
            return;
        }

        $aiService = new \CariIPTV\Services\AIService();
        $result = $aiService->generateImage($prompt, ['size' => $size, 'quality' => 'standard']);

        if (!$result || !$result['success']) {
            Response::json(['success' => false, 'message' => $result['error'] ?? 'Image generation failed']);
            return;
        }

        // Download and process through ImageService for WebP conversion
        $imageService = new \CariIPTV\Services\ImageService();
        $entityId = $campaignId ?: ('ai_' . time());
        $processed = $imageService->processFromUrl($result['url'], 'ad', $entityId, 'banner');

        if (!$processed['success']) {
            // Return the raw DALL-E URL if processing fails
            Response::json([
                'success' => true,
                'image_url' => $result['url'],
                'revised_prompt' => $result['revised_prompt'],
                'local' => false,
            ]);
            return;
        }

        $imageUrl = $processed['variants']['full']
            ?? $processed['variants']['banner_leaderboard']
            ?? $processed['variants']['banner_square']
            ?? $processed['base_path'] . '_full.webp';

        Response::json([
            'success' => true,
            'image_url' => $imageUrl,
            'variants' => $processed['variants'],
            'revised_prompt' => $result['revised_prompt'],
            'local' => true,
        ]);
    }

    /**
     * Upload banner image (AJAX)
     */
    public function uploadAdImage(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            Response::json(['success' => false, 'message' => 'No image file uploaded']);
            return;
        }

        $campaignId = (int) ($_POST['campaign_id'] ?? 0);
        $entityId = $campaignId ?: ('upload_' . time());

        $imageService = new \CariIPTV\Services\ImageService();
        $result = $imageService->processUpload($_FILES['image'], 'ad', $entityId, 'banner');

        if (!$result['success']) {
            Response::json(['success' => false, 'message' => $result['error'] ?? 'Image processing failed']);
            return;
        }

        $imageUrl = $result['variants']['full']
            ?? $result['variants']['banner_leaderboard']
            ?? $result['variants']['banner_square']
            ?? $result['base_path'] . '_full.webp';

        // Get dimensions of the processed image
        $fullPath = defined('BASE_PATH') ? BASE_PATH . '/public' . $imageUrl : null;
        $width = null;
        $height = null;
        if ($fullPath && file_exists($fullPath)) {
            $info = getimagesize($fullPath);
            if ($info) {
                $width = $info[0];
                $height = $info[1];
            }
        }

        Response::json([
            'success' => true,
            'image_url' => $imageUrl,
            'variants' => $result['variants'],
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * Upload video file for pre-roll/mid-roll ads (AJAX)
     */
    public function uploadAdVideo(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        if (empty($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
            Response::json(['success' => false, 'message' => 'No video file uploaded']);
            return;
        }

        $file = $_FILES['video'];

        // Validate file size (max 100MB)
        $maxSize = 100 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            Response::json(['success' => false, 'message' => 'Video file exceeds maximum size of 100MB']);
            return;
        }

        // Validate MIME type
        $allowedTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            Response::json(['success' => false, 'message' => 'Invalid video format. Allowed: MP4, WebM, OGG, MOV']);
            return;
        }

        $campaignId = (int) ($_POST['campaign_id'] ?? 0);
        $entityId = $campaignId ?: ('video_' . time());

        // Create upload directory
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $uploadDir = $basePath . '/public/uploads/ad/' . $entityId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        // Determine extension
        $ext = match ($mimeType) {
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogg',
            'video/quicktime' => 'mov',
            default => 'mp4',
        };

        $filename = 'video_' . time() . '.' . $ext;
        $destPath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            Response::json(['success' => false, 'message' => 'Failed to save video file']);
            return;
        }

        $videoUrl = '/uploads/ad/' . $entityId . '/' . $filename;

        Response::json([
            'success' => true,
            'video_url' => $videoUrl,
            'filename' => $filename,
            'size' => $file['size'],
            'mime_type' => $mimeType,
        ]);
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
            ? $post['status'] : 'active';

        // Type-specific fields
        $data['scroll_text'] = trim($post['scroll_text'] ?? '') ?: null;
        $data['scroll_speed'] = in_array($post['scroll_speed'] ?? '', ['slow', 'normal', 'fast'])
            ? $post['scroll_speed'] : 'normal';
        $data['text_color'] = $post['text_color'] ?? '#FFFFFF';
        $data['bg_color'] = $post['bg_color'] ?? '#000000';
        $data['bg_opacity'] = isset($post['bg_opacity']) ? max(0, min(1, (float) $post['bg_opacity'])) : 0.80;
        $data['font_size'] = in_array($post['font_size'] ?? '', ['small', 'medium', 'large', 'xlarge'])
            ? $post['font_size'] : 'medium';

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

    // =========================================
    // Waterfall Chains
    // =========================================

    public function waterfallIndex(): void
    {
        $chains = $this->adService->getWaterfallChains();
        $zones = $this->adService->getZones();
        $campaigns = $this->db->fetchAll("SELECT id, name FROM ad_campaigns WHERE status IN ('active','paused') ORDER BY name");

        Response::view('admin/ads/waterfall', [
            'pageTitle' => 'Ad Waterfall Chains',
            'chains' => $chains,
            'zones' => $zones,
            'campaigns' => $campaigns,
        ]);
    }

    public function waterfallStore(): void
    {
        Session::validateCsrf($_POST['csrf_token'] ?? '');

        $name = trim($_POST['name'] ?? '');
        $zoneId = (int) ($_POST['zone_id'] ?? 0);

        if (!$name || !$zoneId) {
            Response::json(['success' => false, 'message' => 'Name and zone are required.']);
            return;
        }

        $chainId = $this->adService->createWaterfallChain([
            'zone_id' => $zoneId,
            'name' => $name,
            'is_active' => (int) ($_POST['is_active'] ?? 1),
        ]);

        // Save steps
        $steps = json_decode($_POST['steps'] ?? '[]', true);
        if (!empty($steps)) {
            $this->adService->saveWaterfallSteps($chainId, $steps);
        }

        Response::json(['success' => true, 'id' => $chainId]);
    }

    public function waterfallUpdate(int $id): void
    {
        Session::validateCsrf($_POST['csrf_token'] ?? '');

        $data = [];
        if (isset($_POST['name'])) $data['name'] = trim($_POST['name']);
        if (isset($_POST['zone_id'])) $data['zone_id'] = (int) $_POST['zone_id'];
        if (isset($_POST['is_active'])) $data['is_active'] = (int) $_POST['is_active'];

        $this->adService->updateWaterfallChain($id, $data);

        $steps = json_decode($_POST['steps'] ?? '', true);
        if (is_array($steps)) {
            $this->adService->saveWaterfallSteps($id, $steps);
        }

        Response::json(['success' => true]);
    }

    public function waterfallDelete(int $id): void
    {
        Session::validateCsrf($_POST['csrf_token'] ?? '');
        $this->adService->deleteWaterfallChain($id);
        Response::json(['success' => true]);
    }

    // =========================================
    // A/B Tests
    // =========================================

    public function abTestIndex(): void
    {
        $tests = $this->adService->getAbTests();
        Response::view('admin/ads/ab-tests', [
            'pageTitle' => 'A/B Tests',
            'tests' => $tests,
        ]);
    }

    public function abTestStore(int $campaignId): void
    {
        Session::validateCsrf($_POST['csrf_token'] ?? '');

        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            Response::json(['success' => false, 'message' => 'Test name is required.']);
            return;
        }

        $testId = $this->adService->createAbTest($campaignId, [
            'name' => $name,
            'confidence_threshold' => (float) ($_POST['confidence_threshold'] ?? 95),
            'min_impressions' => (int) ($_POST['min_impressions'] ?? 1000),
            'metric' => in_array($_POST['metric'] ?? '', ['ctr', 'completion_rate', 'conversion_rate'])
                ? $_POST['metric'] : 'ctr',
        ]);

        // Add variants
        $variants = json_decode($_POST['variants'] ?? '[]', true);
        foreach ($variants as $v) {
            $this->adService->addAbTestVariant($testId, [
                'creative_id' => (int) ($v['creative_id'] ?? 0),
                'traffic_weight' => (int) ($v['traffic_weight'] ?? 50),
                'is_control' => (int) ($v['is_control'] ?? 0),
            ]);
        }

        Response::json(['success' => true, 'id' => $testId]);
    }

    public function abTestUpdate(int $id): void
    {
        Session::validateCsrf($_POST['csrf_token'] ?? '');

        $data = [];
        if (isset($_POST['name'])) $data['name'] = trim($_POST['name']);
        if (isset($_POST['status'])) $data['status'] = $_POST['status'];
        if (isset($_POST['confidence_threshold'])) $data['confidence_threshold'] = (float) $_POST['confidence_threshold'];
        if (isset($_POST['min_impressions'])) $data['min_impressions'] = (int) $_POST['min_impressions'];
        if (isset($_POST['metric'])) $data['metric'] = $_POST['metric'];

        $this->adService->updateAbTest($id, $data);
        Response::json(['success' => true]);
    }

    public function abTestDelete(int $id): void
    {
        Session::validateCsrf($_POST['csrf_token'] ?? '');
        $this->adService->deleteAbTest($id);
        Response::json(['success' => true]);
    }

    public function abTestEvaluate(int $id): void
    {
        $result = $this->adService->evaluateAbTest($id);
        Response::json(['success' => true, 'result' => $result]);
    }

    // =========================================
    // Ad Pods
    // =========================================

    public function podIndex(): void
    {
        $pods = $this->adService->getAdPods();
        $zones = $this->adService->getZones();

        Response::view('admin/ads/pods', [
            'pageTitle' => 'Ad Pods & Breaks',
            'pods' => $pods,
            'zones' => $zones,
        ]);
    }

    public function podStore(): void
    {
        Session::validateCsrf($_POST['csrf_token'] ?? '');

        $name = trim($_POST['name'] ?? '');
        $zoneId = (int) ($_POST['zone_id'] ?? 0);

        if (!$name || !$zoneId) {
            Response::json(['success' => false, 'message' => 'Name and zone are required.']);
            return;
        }

        $id = $this->adService->createAdPod([
            'zone_id' => $zoneId,
            'name' => $name,
            'max_ads' => (int) ($_POST['max_ads'] ?? 3),
            'max_duration_seconds' => (int) ($_POST['max_duration_seconds'] ?? 90),
            'min_content_duration' => !empty($_POST['min_content_duration']) ? (int) $_POST['min_content_duration'] : null,
            'separation_seconds' => (int) ($_POST['separation_seconds'] ?? 300),
            'allow_competitor_ads' => (int) ($_POST['allow_competitor_ads'] ?? 0),
            'pod_type' => in_array($_POST['pod_type'] ?? '', ['pre_roll', 'mid_roll', 'post_roll'])
                ? $_POST['pod_type'] : 'pre_roll',
            'is_active' => (int) ($_POST['is_active'] ?? 1),
        ]);

        Response::json(['success' => true, 'id' => $id]);
    }

    public function podUpdate(int $id): void
    {
        Session::validateCsrf($_POST['csrf_token'] ?? '');
        $data = $_POST;
        unset($data['csrf_token']);
        $this->adService->updateAdPod($id, $data);
        Response::json(['success' => true]);
    }

    public function podDelete(int $id): void
    {
        Session::validateCsrf($_POST['csrf_token'] ?? '');
        $this->adService->deleteAdPod($id);
        Response::json(['success' => true]);
    }

    // =========================================
    // Conversion Goals
    // =========================================

    public function conversionGoalStore(int $campaignId): void
    {
        Session::validateCsrf($_POST['csrf_token'] ?? '');

        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            Response::json(['success' => false, 'message' => 'Goal name is required.']);
            return;
        }

        $id = $this->adService->createConversionGoal($campaignId, [
            'name' => $name,
            'goal_type' => in_array($_POST['goal_type'] ?? '', ['page_visit', 'signup', 'purchase', 'custom'])
                ? $_POST['goal_type'] : 'page_visit',
            'tracking_url' => trim($_POST['tracking_url'] ?? '') ?: null,
            'pixel_code' => trim($_POST['pixel_code'] ?? '') ?: null,
            'value' => !empty($_POST['value']) ? (float) $_POST['value'] : null,
            'attribution_window_hours' => (int) ($_POST['attribution_window_hours'] ?? 720),
        ]);

        Response::json(['success' => true, 'id' => $id]);
    }

    public function conversionGoalUpdate(int $campaignId, int $goalId): void
    {
        Session::validateCsrf($_POST['csrf_token'] ?? '');
        $data = [];
        foreach (['name', 'goal_type', 'tracking_url', 'pixel_code', 'value', 'attribution_window_hours', 'is_active'] as $f) {
            if (isset($_POST[$f])) $data[$f] = $_POST[$f];
        }
        $this->adService->updateConversionGoal($goalId, $data);
        Response::json(['success' => true]);
    }

    public function conversionGoalDelete(int $campaignId, int $goalId): void
    {
        Session::validateCsrf($_POST['csrf_token'] ?? '');
        $this->adService->deleteConversionGoal($goalId);
        Response::json(['success' => true]);
    }

    public function trackConversion(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $pixelCode = $input['pixel_code'] ?? '';
        if (!$pixelCode) {
            Response::json(['success' => false, 'message' => 'Missing pixel_code']);
            return;
        }

        $id = $this->adService->recordConversion($pixelCode, [
            'user_id' => !empty($input['user_id']) ? (int) $input['user_id'] : null,
            'session_id' => $input['session_id'] ?? session_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'conversion_value' => !empty($input['value']) ? (float) $input['value'] : null,
            'metadata' => $input['metadata'] ?? null,
        ]);

        Response::json(['success' => $id !== null, 'conversion_id' => $id]);
    }

    public function conversionStats(int $campaignId): void
    {
        $stats = $this->adService->getConversionStats($campaignId);
        Response::json(['success' => true, 'stats' => $stats]);
    }

    // =========================================
    // Daypart Schedules
    // =========================================

    public function daypartStore(int $campaignId): void
    {
        Session::validateCsrf($_POST['csrf_token'] ?? '');

        $grid = json_decode($_POST['schedule_grid'] ?? '{}', true);
        if (empty($grid)) {
            Response::json(['success' => false, 'message' => 'Schedule grid is required.']);
            return;
        }

        $id = $this->adService->createDaypartSchedule($campaignId, [
            'name' => trim($_POST['name'] ?? 'Default Schedule'),
            'priority_multiplier' => (float) ($_POST['priority_multiplier'] ?? 1.0),
            'budget_multiplier' => (float) ($_POST['budget_multiplier'] ?? 1.0),
            'schedule_grid' => $grid,
        ]);

        Response::json(['success' => true, 'id' => $id]);
    }

    public function daypartUpdate(int $campaignId, int $scheduleId): void
    {
        Session::validateCsrf($_POST['csrf_token'] ?? '');
        $data = [];
        if (isset($_POST['name'])) $data['name'] = trim($_POST['name']);
        if (isset($_POST['priority_multiplier'])) $data['priority_multiplier'] = (float) $_POST['priority_multiplier'];
        if (isset($_POST['budget_multiplier'])) $data['budget_multiplier'] = (float) $_POST['budget_multiplier'];
        if (isset($_POST['schedule_grid'])) $data['schedule_grid'] = json_decode($_POST['schedule_grid'], true);
        if (isset($_POST['is_active'])) $data['is_active'] = (int) $_POST['is_active'];

        $this->adService->updateDaypartSchedule($scheduleId, $data);
        Response::json(['success' => true]);
    }

    public function daypartDelete(int $campaignId, int $scheduleId): void
    {
        Session::validateCsrf($_POST['csrf_token'] ?? '');
        $this->adService->deleteDaypartSchedule($scheduleId);
        Response::json(['success' => true]);
    }

    // =========================================
    // Floor Prices
    // =========================================

    public function updateFloorPrice(int $zoneId): void
    {
        Session::validateCsrf($_POST['csrf_token'] ?? '');
        $floorCpm = !empty($_POST['floor_cpm']) ? (float) $_POST['floor_cpm'] : null;
        $fallbackVast = trim($_POST['fallback_vast_url'] ?? '') ?: null;
        $this->adService->updateZoneFloorPrice($zoneId, $floorCpm, $fallbackVast);
        Response::json(['success' => true]);
    }

    // =========================================
    // Ad Serve Diagnostics
    // =========================================

    /**
     * Diagnostic endpoint to debug why ads aren't serving.
     * GET /admin/ads/api/debug?zone_type=text_scroller
     */
    public function serveDebug(): void
    {
        $zoneType = $_GET['zone_type'] ?? null;
        $now = date('Y-m-d H:i:s');

        $debug = [];

        // 1. Check zones
        $zones = $this->db->fetchAll(
            "SELECT id, name, slug, zone_type, is_active FROM ad_zones" .
            ($zoneType ? " WHERE zone_type = ?" : ""),
            $zoneType ? [$zoneType] : []
        );
        $debug['zones'] = $zones;
        $activeZones = array_filter($zones, fn($z) => $z['is_active']);
        $debug['zones_active'] = count($activeZones);
        if (empty($activeZones)) {
            $debug['problem'] = 'No active zones found' . ($zoneType ? " for type '$zoneType'" : '');
        }

        // 2. Check campaigns
        $campaigns = $this->db->fetchAll("SELECT id, name, status, start_date, end_date, total_impressions, total_impressions_cap FROM ad_campaigns");
        $debug['campaigns_total'] = count($campaigns);
        $activeCampaigns = array_filter($campaigns, function ($c) use ($now) {
            return $c['status'] === 'active'
                && ($c['start_date'] === null || $c['start_date'] <= $now)
                && ($c['end_date'] === null || $c['end_date'] >= $now)
                && ($c['total_impressions_cap'] === null || $c['total_impressions'] < $c['total_impressions_cap']);
        });
        $debug['campaigns_active_and_in_date'] = count($activeCampaigns);
        foreach ($campaigns as $c) {
            $issues = [];
            if ($c['status'] !== 'active') $issues[] = "status is '{$c['status']}' (need 'active')";
            if ($c['start_date'] && $c['start_date'] > $now) $issues[] = "start_date ({$c['start_date']}) is in the future";
            if ($c['end_date'] && $c['end_date'] < $now) $issues[] = "end_date ({$c['end_date']}) is in the past";
            if ($c['total_impressions_cap'] && $c['total_impressions'] >= $c['total_impressions_cap']) $issues[] = "impression cap reached ({$c['total_impressions']}/{$c['total_impressions_cap']})";
            if ($issues) $debug['campaign_issues'][$c['name'] . " (#{$c['id']})"] = $issues;
        }

        // 3. Check creatives
        $creatives = $this->db->fetchAll("SELECT id, campaign_id, name, type, status FROM ad_creatives");
        $debug['creatives_total'] = count($creatives);
        $activeCreatives = array_filter($creatives, fn($c) => $c['status'] === 'active');
        $debug['creatives_active'] = count($activeCreatives);
        $draftCreatives = array_filter($creatives, fn($c) => $c['status'] === 'draft');
        if (count($draftCreatives) > 0) {
            $debug['creatives_in_draft'] = count($draftCreatives);
            $debug['draft_creative_names'] = array_map(fn($c) => $c['name'] . " (#{$c['id']}, type: {$c['type']})", array_values($draftCreatives));
            if (empty($activeCreatives)) {
                $debug['problem'] = 'All creatives are in DRAFT status. Change them to ACTIVE in the campaign editor.';
            }
        }

        // 4. Check placements
        $placements = $this->db->fetchAll(
            "SELECT ap.id, ap.status, ap.campaign_id, ap.creative_id, ap.zone_id,
                    camp.name as campaign_name, ac.name as creative_name, ac.status as creative_status,
                    az.name as zone_name, az.zone_type, az.is_active as zone_active
             FROM ad_placements ap
             JOIN ad_campaigns camp ON ap.campaign_id = camp.id
             JOIN ad_creatives ac ON ap.creative_id = ac.id
             JOIN ad_zones az ON ap.zone_id = az.id"
        );
        $debug['placements_total'] = count($placements);
        foreach ($placements as $p) {
            $issues = [];
            if ($p['status'] !== 'active') $issues[] = "placement status is '{$p['status']}'";
            if ($p['creative_status'] !== 'active') $issues[] = "creative '{$p['creative_name']}' status is '{$p['creative_status']}' (need 'active')";
            if (!$p['zone_active']) $issues[] = "zone '{$p['zone_name']}' is inactive";
            if ($issues) {
                $debug['placement_issues'][] = [
                    'placement_id' => $p['id'],
                    'campaign' => $p['campaign_name'],
                    'creative' => $p['creative_name'],
                    'zone' => $p['zone_name'] . " ({$p['zone_type']})",
                    'issues' => $issues,
                ];
            }
        }

        // 5. Quick fix suggestion
        if (!empty($draftCreatives)) {
            $debug['fix_sql'] = "UPDATE ad_creatives SET status = 'active' WHERE status = 'draft';";
        }

        // 6. Run the ACTUAL serve query to see what comes back
        $serveSql = "
            SELECT
                ac.id, ac.name, ac.type, ac.status as creative_status,
                ap.id as placement_id, ap.status as placement_status,
                ap.start_date as placement_start, ap.end_date as placement_end,
                az.slug as zone_slug, az.zone_type, az.is_active as zone_active,
                camp.name as campaign_name, camp.status as campaign_status,
                camp.start_date as camp_start, camp.end_date as camp_end,
                camp.total_impressions, camp.total_impressions_cap
            FROM ad_placements ap
            JOIN ad_campaigns camp ON ap.campaign_id = camp.id
            JOIN ad_creatives ac ON ap.creative_id = ac.id
            JOIN ad_zones az ON ap.zone_id = az.id
        ";
        $debug['raw_join_all'] = $this->db->fetchAll($serveSql);

        // 7. Run with filters (same as getAdsForContext)
        $filterSql = $serveSql . "
            WHERE camp.status = 'active'
              AND ac.status = 'active'
              AND ap.status = 'active'
              AND az.is_active = 1
              AND (camp.start_date IS NULL OR camp.start_date <= ?)
              AND (camp.end_date IS NULL OR camp.end_date >= ?)
              AND (ap.start_date IS NULL OR ap.start_date <= ?)
              AND (ap.end_date IS NULL OR ap.end_date >= ?)
              AND (camp.total_impressions_cap IS NULL OR camp.total_impressions < camp.total_impressions_cap)
        ";
        $params = [$now, $now, $now, $now];
        if ($zoneType) {
            $filterSql .= " AND az.zone_type = ?";
            $params[] = $zoneType;
        }
        $debug['filtered_results'] = $this->db->fetchAll($filterSql, $params);

        // 8. Test each condition individually to find the failing one
        if (empty($debug['filtered_results']) && !empty($debug['raw_join_all'])) {
            $row = $debug['raw_join_all'][0];
            $debug['condition_check'] = [
                'camp_status_active' => $row['campaign_status'] === 'active',
                'creative_status_active' => $row['creative_status'] === 'active',
                'placement_status_active' => $row['placement_status'] === 'active',
                'zone_active' => (bool) $row['zone_active'],
                'camp_start_ok' => $row['camp_start'] === null || $row['camp_start'] <= $now,
                'camp_end_ok' => $row['camp_end'] === null || $row['camp_end'] >= $now,
                'camp_start_raw' => $row['camp_start'],
                'camp_end_raw' => $row['camp_end'],
                'placement_start_ok' => $row['placement_start'] === null || $row['placement_start'] <= $now,
                'placement_end_ok' => $row['placement_end'] === null || $row['placement_end'] >= $now,
                'impressions_cap_ok' => $row['total_impressions_cap'] === null || (int) $row['total_impressions'] < (int) $row['total_impressions_cap'],
                'zone_type_match' => $zoneType ? $row['zone_type'] === $zoneType : true,
                'now' => $now,
            ];
        }

        // 9. Check daypart schedules (these can silently block all ads)
        $campaignIds = array_unique(array_column($campaigns, 'id'));
        foreach ($campaignIds as $cid) {
            try {
                $daypartSchedules = $this->db->fetchAll(
                    "SELECT * FROM ad_daypart_schedules WHERE campaign_id = ? AND is_active = 1",
                    [$cid]
                );
                if (!empty($daypartSchedules)) {
                    $dayMap = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                    $dow = $dayMap[(int) date('N') - 1];
                    $hour = (int) date('G');
                    $debug['daypart_check'] = [
                        'campaign_id' => $cid,
                        'schedules_found' => count($daypartSchedules),
                        'current_day' => $dow,
                        'current_hour' => $hour,
                        'blocked' => true,
                    ];
                    foreach ($daypartSchedules as $sched) {
                        $grid = json_decode($sched['schedule_grid'], true);
                        if ($grid && isset($grid[$dow][$hour]) && $grid[$dow][$hour]) {
                            $debug['daypart_check']['blocked'] = false;
                            break;
                        }
                    }
                    if ($debug['daypart_check']['blocked']) {
                        $debug['problem'] = "Daypart schedule is BLOCKING ads for campaign #$cid at $dow hour $hour. Delete or edit the daypart schedule.";
                    }
                }
            } catch (\Throwable $e) {
                $debug['daypart_error'] = $e->getMessage();
            }
        }

        // 10. Check targeting rules on all placements
        $allPlacements = $this->db->fetchAll("SELECT ap.id, ap.campaign_id, ap.creative_id, ap.zone_id FROM ad_placements ap WHERE ap.status = 'active'");
        $debug['targeting_rules'] = [];
        foreach ($allPlacements as $pl) {
            $rules = $this->db->fetchAll("SELECT * FROM ad_targeting_rules WHERE placement_id = ?", [$pl['id']]);
            if (!empty($rules)) {
                $debug['targeting_rules']['placement_' . $pl['id']] = $rules;
            }
        }
        if (empty($debug['targeting_rules'])) {
            $debug['targeting_rules'] = 'none (all placements have no targeting rules)';
        }

        // 11. Test getAdsForContext directly (basic, no daypart/AB)
        try {
            $basicAds = $this->adService->getAdsForContext([
                'zone_type' => $zoneType,
                'platform' => 'web',
                'limit' => 5,
            ]);
            $debug['basic_serve_count'] = count($basicAds);
            if (!empty($basicAds)) {
                $debug['basic_serve_first'] = [
                    'id' => $basicAds[0]['id'] ?? null,
                    'type' => $basicAds[0]['type'] ?? null,
                    'campaign_id' => $basicAds[0]['campaign_id'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            $debug['basic_serve_error'] = $e->getMessage();
        }

        // 12. Test enhanced serve result
        try {
            $serveResult = $this->adService->serveAdsEnhanced([
                'zone_type' => $zoneType,
                'platform' => 'web',
                'limit' => 5,
            ]);
            $debug['serve_result_count'] = count($serveResult['ads'] ?? []);
            $debug['serve_source'] = $serveResult['source'] ?? 'unknown';
        } catch (\Throwable $e) {
            $debug['serve_error'] = $e->getMessage();
        }

        Response::json(['success' => true, 'debug' => $debug, 'server_time' => $now]);
    }

    // =========================================
    // Enhanced Serve (with waterfall, pods, dayparting)
    // =========================================

    public function serveEnhanced(): void
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
            Response::json([
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
                'tracking' => [
                    'impression_url' => '/api/v1/ads/impression',
                    'event_url' => '/api/v1/ads/event',
                ],
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

        Response::json([
            'success' => true,
            'source' => $result['source'] ?? 'direct',
            'pod' => $result['pod'] ?? null,
            'ads' => $formatted,
            'count' => count($formatted),
        ]);
    }

    /**
     * Get ad break schedule for VOD content (uses cue points)
     */
    public function adBreakSchedule(): void
    {
        $contentType = $_GET['content_type'] ?? 'movie';
        $contentId = (int) ($_GET['content_id'] ?? 0);
        $duration = (float) ($_GET['duration'] ?? 0);

        if (!$contentId || !$duration) {
            Response::json(['success' => false, 'message' => 'content_id and duration required']);
            return;
        }

        $schedule = $this->adService->getAdBreakSchedule($contentType, $contentId, $duration);
        Response::json(['success' => true, 'breaks' => $schedule]);
    }

    // =========================================
    // Revenue Forecasting
    // =========================================

    public function forecastIndex(): void
    {
        $forecasts = $this->adService->getForecasts(30);
        $historical = $this->adService->getHistoricalPerformance(90);

        // Update actuals for past forecasts
        $this->adService->updateForecastActuals();

        Response::view('admin/ads/forecast', [
            'pageTitle' => 'Revenue Forecast',
            'forecasts' => $forecasts,
            'historical' => $historical,
        ]);
    }

    public function generateForecast(): void
    {
        Session::validateCsrf($_POST['csrf_token'] ?? '');

        $historical = $this->adService->getHistoricalPerformance(90);

        // Build AI prompt
        $prompt = "You are an ad revenue forecasting analyst for CARI-IPTV.\n\n";
        $prompt .= "Historical Performance (last 90 days):\n";
        $prompt .= "- Active campaigns: {$historical['active_campaigns']}\n";
        $prompt .= "- Avg daily impressions: {$historical['avg_daily_impressions']}\n";
        $prompt .= "- Avg daily revenue: \${$historical['avg_daily_revenue']}\n\n";

        if (!empty($historical['daily'])) {
            $prompt .= "Daily data (last 30 entries):\n";
            foreach (array_slice($historical['daily'], -30) as $d) {
                $prompt .= "  {$d['date']}: {$d['impressions']} impressions, \${$d['revenue']} revenue\n";
            }
        }

        if (!empty($historical['by_zone_type'])) {
            $prompt .= "\nBy zone type:\n";
            foreach ($historical['by_zone_type'] as $z) {
                $prompt .= "  {$z['zone_type']}: {$z['impressions']} impressions, \${$z['revenue']} revenue\n";
            }
        }

        $prompt .= "\nGenerate a 14-day revenue forecast. Return ONLY valid JSON:\n";
        $prompt .= '{"forecasts":[{"date":"YYYY-MM-DD","impressions":N,"revenue":N.NN,"fill_rate":N.N,"confidence":N.N}],"analysis":"Brief narrative analysis"}';

        try {
            $aiService = new \CariIPTV\Services\AIService();
            $response = $aiService->chat($prompt);

            // Parse JSON from AI response
            $jsonMatch = [];
            if (preg_match('/\{[\s\S]*"forecasts"[\s\S]*\}/', $response, $jsonMatch)) {
                $parsed = json_decode($jsonMatch[0], true);
                if ($parsed && !empty($parsed['forecasts'])) {
                    foreach ($parsed['forecasts'] as $f) {
                        $this->adService->saveForecast([
                            'forecast_date' => $f['date'],
                            'zone_type' => null,
                            'predicted_impressions' => (int) ($f['impressions'] ?? 0),
                            'predicted_revenue' => (float) ($f['revenue'] ?? 0),
                            'predicted_fill_rate' => $f['fill_rate'] ?? null,
                            'confidence_level' => $f['confidence'] ?? null,
                            'ai_analysis' => $parsed['analysis'] ?? null,
                        ]);
                    }

                    Response::json([
                        'success' => true,
                        'forecasts' => $parsed['forecasts'],
                        'analysis' => $parsed['analysis'] ?? '',
                    ]);
                    return;
                }
            }

            Response::json(['success' => false, 'message' => 'AI response could not be parsed.', 'raw' => $response]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'AI service error: ' . $e->getMessage()]);
        }
    }
}
