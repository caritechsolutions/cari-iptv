<?php
/**
 * CARI-IPTV Channel Management Controller
 */

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Database;
use CariIPTV\Core\Response;
use CariIPTV\Core\Session;
use CariIPTV\Services\AdminAuthService;
use CariIPTV\Services\ChannelService;
use CariIPTV\Services\MetadataService;
use CariIPTV\Services\AIService;
use CariIPTV\Services\ImageService;
use CariIPTV\Services\IptvOrgService;

class ChannelController
{
    private Database $db;
    private AdminAuthService $auth;
    private ChannelService $channelService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = new AdminAuthService();
        $this->channelService = new ChannelService();
    }

    /**
     * List all channels
     */
    public function index(): void
    {
        $filters = [
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? '',
            'published' => $_GET['published'] ?? '',
            'category_id' => $_GET['category_id'] ?? '',
            'package_id' => $_GET['package_id'] ?? '',
            'server_id' => $_GET['server_id'] ?? '',
            'sort' => $_GET['sort'] ?? 'sort_order',
            'dir' => $_GET['dir'] ?? 'ASC',
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? 25,
        ];

        $result = $this->channelService->getChannels($filters);
        $categories = $this->channelService->getCategories();
        $packages = $this->channelService->getPackages();
        $servers = $this->channelService->getStreamingServers();
        $stats = $this->channelService->getStatistics();

        Response::view('admin/channels/index', [
            'pageTitle' => 'Channels',
            'channels' => $result['data'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total_pages' => $result['total_pages'],
            ],
            'filters' => $filters,
            'categories' => $categories,
            'packages' => $packages,
            'servers' => $servers,
            'stats' => $stats,
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Show create channel form
     */
    public function create(): void
    {
        $categories = $this->channelService->getCategories();
        $packages = $this->channelService->getPackages();
        $servers = $this->channelService->getStreamingServers();
        $contentOwners = $this->channelService->getContentOwners();

        Response::view('admin/channels/form', [
            'pageTitle' => 'Add Channel',
            'channel' => null,
            'categories' => $categories,
            'packages' => $packages,
            'servers' => $servers,
            'contentOwners' => $contentOwners,
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Store new channel
     */
    public function store(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect('/admin/channels/create');
            return;
        }

        $data = $this->validateChannelData($_POST);

        if (!empty($data['errors'])) {
            Session::flash('error', implode(' ', $data['errors']));
            Response::redirect('/admin/channels/create');
            return;
        }

        // Remove validation errors key before database insert
        unset($data['errors']);

        // Handle logo upload
        if (!empty($_FILES['logo']['name'])) {
            $logoUrl = $this->channelService->uploadLogo($_FILES['logo'], 'logo');
            if ($logoUrl) {
                $data['logo_url'] = $logoUrl;
            }
        } elseif (!empty($_POST['logo_url_external'])) {
            // Handle external logo URL (from Fanart.tv search)
            $externalLogo = $this->processExternalLogo($_POST['logo_url_external']);
            if ($externalLogo) {
                $data['logo_url'] = $externalLogo;
            }
        }

        // Handle landscape logo upload
        if (!empty($_FILES['logo_landscape']['name'])) {
            $landscapeUrl = $this->channelService->uploadLogo($_FILES['logo_landscape'], 'landscape');
            if ($landscapeUrl) {
                $data['logo_landscape_url'] = $landscapeUrl;
            }
        } elseif (!empty($_POST['logo_landscape_url_external'])) {
            // Handle external landscape logo URL
            $externalLandscape = $this->processExternalLogo($_POST['logo_landscape_url_external'], 'landscape');
            if ($externalLandscape) {
                $data['logo_landscape_url'] = $externalLandscape;
            }
        }

        // Handle description
        if (isset($_POST['description'])) {
            $data['description'] = trim($_POST['description']);
        }

        try {
            $channelId = $this->channelService->createChannel($data);

            // Log activity
            $this->auth->logActivity(
                $this->auth->id(),
                'create',
                'channels',
                'channel',
                $channelId,
                ['name' => $data['name']]
            );

            Session::flash('success', 'Channel created successfully.');
            Response::redirect('/admin/channels');
        } catch (\Exception $e) {
            Session::flash('error', 'Failed to create channel: ' . $e->getMessage());
            Response::redirect('/admin/channels/create');
        }
    }

    /**
     * Show edit channel form
     */
    public function edit(int $id): void
    {
        $channel = $this->channelService->getChannel($id);

        if (!$channel) {
            Session::flash('error', 'Channel not found.');
            Response::redirect('/admin/channels');
            return;
        }

        $categories = $this->channelService->getCategories();
        $packages = $this->channelService->getPackages();
        $servers = $this->channelService->getStreamingServers();
        $contentOwners = $this->channelService->getContentOwners();

        Response::view('admin/channels/form', [
            'pageTitle' => 'Edit Channel',
            'channel' => $channel,
            'categories' => $categories,
            'packages' => $packages,
            'servers' => $servers,
            'contentOwners' => $contentOwners,
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Update channel
     */
    public function update(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request. Please try again.');
            Response::redirect("/admin/channels/{$id}/edit");
            return;
        }

        $channel = $this->channelService->getChannel($id);
        if (!$channel) {
            Session::flash('error', 'Channel not found.');
            Response::redirect('/admin/channels');
            return;
        }

        $data = $this->validateChannelData($_POST, $id);

        if (!empty($data['errors'])) {
            Session::flash('error', implode(' ', $data['errors']));
            Response::redirect("/admin/channels/{$id}/edit");
            return;
        }

        // Remove validation errors key before database update
        unset($data['errors']);

        // Handle logo upload
        if (!empty($_FILES['logo']['name'])) {
            $logoUrl = $this->channelService->uploadLogo($_FILES['logo'], 'logo');
            if ($logoUrl) {
                // Delete old logo
                if (!empty($channel['logo_url'])) {
                    $this->channelService->deleteLogo($channel['logo_url']);
                }
                $data['logo_url'] = $logoUrl;
            }
        } elseif (!empty($_POST['logo_url_external'])) {
            // Handle external logo URL (from Fanart.tv search)
            $externalLogo = $this->processExternalLogo($_POST['logo_url_external']);
            if ($externalLogo) {
                // Delete old logo
                if (!empty($channel['logo_url'])) {
                    $this->channelService->deleteLogo($channel['logo_url']);
                }
                $data['logo_url'] = $externalLogo;
            }
        }

        // Handle landscape logo upload
        if (!empty($_FILES['logo_landscape']['name'])) {
            $landscapeUrl = $this->channelService->uploadLogo($_FILES['logo_landscape'], 'landscape');
            if ($landscapeUrl) {
                // Delete old landscape logo
                if (!empty($channel['logo_landscape_url'])) {
                    $this->channelService->deleteLogo($channel['logo_landscape_url']);
                }
                $data['logo_landscape_url'] = $landscapeUrl;
            }
        } elseif (!empty($_POST['logo_landscape_url_external'])) {
            // Handle external landscape logo URL
            $externalLandscape = $this->processExternalLogo($_POST['logo_landscape_url_external'], 'landscape');
            if ($externalLandscape) {
                // Delete old landscape logo
                if (!empty($channel['logo_landscape_url'])) {
                    $this->channelService->deleteLogo($channel['logo_landscape_url']);
                }
                $data['logo_landscape_url'] = $externalLandscape;
            }
        }

        // Handle description
        if (isset($_POST['description'])) {
            $data['description'] = trim($_POST['description']);
        }

        try {
            $this->channelService->updateChannel($id, $data);

            // Log activity
            $this->auth->logActivity(
                $this->auth->id(),
                'update',
                'channels',
                'channel',
                $id,
                ['name' => $data['name']]
            );

            Session::flash('success', 'Channel updated successfully.');
            Response::redirect('/admin/channels');
        } catch (\Exception $e) {
            Session::flash('error', 'Failed to update channel: ' . $e->getMessage());
            Response::redirect("/admin/channels/{$id}/edit");
        }
    }

    /**
     * Delete channel
     */
    public function delete(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request.');
            Response::redirect('/admin/channels');
            return;
        }

        $channel = $this->channelService->getChannel($id);
        if (!$channel) {
            Session::flash('error', 'Channel not found.');
            Response::redirect('/admin/channels');
            return;
        }

        // Delete logos
        if (!empty($channel['logo_url'])) {
            $this->channelService->deleteLogo($channel['logo_url']);
        }
        if (!empty($channel['logo_landscape_url'])) {
            $this->channelService->deleteLogo($channel['logo_landscape_url']);
        }

        $this->channelService->deleteChannel($id);

        // Log activity
        $this->auth->logActivity(
            $this->auth->id(),
            'delete',
            'channels',
            'channel',
            $id,
            ['name' => $channel['name']]
        );

        Session::flash('success', 'Channel deleted successfully.');
        Response::redirect('/admin/channels');
    }

    /**
     * Toggle channel status
     */
    public function toggleStatus(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request.');
            Response::redirect('/admin/channels');
            return;
        }

        $channel = $this->channelService->getChannel($id);
        if (!$channel) {
            Session::flash('error', 'Channel not found.');
            Response::redirect('/admin/channels');
            return;
        }

        $this->channelService->toggleStatus($id);

        $statusText = $channel['is_active'] ? 'deactivated' : 'activated';
        Session::flash('success', "Channel {$statusText} successfully.");
        Response::redirect('/admin/channels');
    }

    /**
     * Bulk actions
     */
    public function bulkAction(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Invalid request.');
            Response::redirect('/admin/channels');
            return;
        }

        $action = $_POST['action'] ?? '';
        $ids = $_POST['channel_ids'] ?? [];

        if (empty($ids)) {
            Session::flash('error', 'No channels selected.');
            Response::redirect('/admin/channels');
            return;
        }

        $ids = array_map('intval', $ids);

        switch ($action) {
            case 'activate':
                $count = $this->channelService->bulkUpdateStatus($ids, true);
                Session::flash('success', "{$count} channel(s) activated.");
                break;

            case 'deactivate':
                $count = $this->channelService->bulkUpdateStatus($ids, false);
                Session::flash('success', "{$count} channel(s) deactivated.");
                break;

            case 'delete':
                $count = 0;
                foreach ($ids as $id) {
                    $channel = $this->channelService->getChannel($id);
                    if ($channel) {
                        if (!empty($channel['logo_url'])) {
                            $this->channelService->deleteLogo($channel['logo_url']);
                        }
                        if (!empty($channel['logo_landscape_url'])) {
                            $this->channelService->deleteLogo($channel['logo_landscape_url']);
                        }
                        $this->channelService->deleteChannel($id);
                        $count++;
                    }
                }
                Session::flash('success', "{$count} channel(s) deleted.");
                break;

            default:
                Session::flash('error', 'Invalid action.');
        }

        Response::redirect('/admin/channels');
    }

    /**
     * Remove logo
     */
    public function removeLogo(int $id): void
    {
        $type = $_GET['type'] ?? 'logo';

        $channel = $this->channelService->getChannel($id);
        if (!$channel) {
            Response::json(['success' => false, 'message' => 'Channel not found']);
            return;
        }

        $field = $type === 'landscape' ? 'logo_landscape_url' : 'logo_url';

        if (!empty($channel[$field])) {
            $this->channelService->deleteLogo($channel[$field]);
            $this->channelService->updateChannel($id, [$field => null]);
        }

        Response::json(['success' => true]);
    }

    /**
     * Validate channel data
     */
    private function validateChannelData(array $data, ?int $channelId = null): array
    {
        $errors = [];
        $validated = [];

        // Name (required)
        $validated['name'] = trim($data['name'] ?? '');
        if (empty($validated['name'])) {
            $errors[] = 'Channel name is required.';
        }

        // Key code (optional, will be auto-generated)
        $validated['key_code'] = trim($data['key_code'] ?? '');
        if (!empty($validated['key_code'])) {
            // Check uniqueness
            $sql = "SELECT id FROM channels WHERE key_code = ?";
            $params = [$validated['key_code']];
            if ($channelId) {
                $sql .= " AND id != ?";
                $params[] = $channelId;
            }
            $existing = $this->db->fetch($sql, $params);
            if ($existing) {
                $errors[] = 'Key code already in use.';
            }
        }

        // Stream URL (required)
        $validated['stream_url'] = trim($data['stream_url'] ?? '');
        if (empty($validated['stream_url'])) {
            $errors[] = 'Stream URL is required.';
        }

        // Backup stream URL (optional)
        $validated['stream_url_backup'] = trim($data['stream_url_backup'] ?? '') ?: null;

        // Channel number
        $validated['channel_number'] = !empty($data['channel_number']) ? (int) $data['channel_number'] : null;

        // Streaming server
        $validated['streaming_server_id'] = !empty($data['streaming_server_id']) ? (int) $data['streaming_server_id'] : null;

        // Content owner
        $validated['content_owner_id'] = !empty($data['content_owner_id']) ? (int) $data['content_owner_id'] : null;

        // EPG channel ID
        $validated['epg_channel_id'] = trim($data['epg_channel_id'] ?? '') ?: null;

        // External ID
        $validated['external_id'] = trim($data['external_id'] ?? '') ?: null;

        // Country
        $validated['country'] = trim($data['country'] ?? '') ?: null;

        // Language
        $validated['language'] = trim($data['language'] ?? '') ?: null;

        // Sort order
        $validated['sort_order'] = (int) ($data['sort_order'] ?? 0);

        // Boolean flags
        $validated['is_active'] = isset($data['is_active']) ? 1 : 0;
        $validated['is_published'] = isset($data['is_published']) ? 1 : 0;
        $validated['is_hd'] = isset($data['is_hd']) ? 1 : 0;
        $validated['is_4k'] = isset($data['is_4k']) ? 1 : 0;
        $validated['available_without_purchase'] = isset($data['available_without_purchase']) ? 1 : 0;
        $validated['show_to_demo_users'] = isset($data['show_to_demo_users']) ? 1 : 0;

        // Age limit
        $validAgeLimits = ['0+', '7+', '12+', '16+', '18+'];
        $validated['age_limit'] = in_array($data['age_limit'] ?? '', $validAgeLimits) ? $data['age_limit'] : '0+';

        // OS platforms
        $validated['os_platforms'] = $data['os_platforms'] ?? ['all'];
        if (!is_array($validated['os_platforms'])) {
            $validated['os_platforms'] = ['all'];
        }

        // Catchup settings
        $validated['catchup_days'] = max(0, (int) ($data['catchup_days'] ?? 0));
        $validated['catchup_period_type'] = in_array($data['catchup_period_type'] ?? '', ['days', 'hours'])
            ? $data['catchup_period_type']
            : 'days';

        // Categories (array of IDs)
        $validated['categories'] = [];
        if (!empty($data['categories']) && is_array($data['categories'])) {
            $validated['categories'] = array_map('intval', $data['categories']);
        }

        // Primary category
        $validated['primary_category'] = !empty($data['primary_category']) ? (int) $data['primary_category'] : null;

        // Packages (array of IDs)
        $validated['packages'] = [];
        if (!empty($data['packages']) && is_array($data['packages'])) {
            $validated['packages'] = array_map('intval', $data['packages']);
        }

        $validated['errors'] = $errors;
        return $validated;
    }

    /**
     * Search for channel logos from Fanart.tv
     */
    public function searchLogos(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $query = trim($input['query'] ?? '');

        if (empty($query)) {
            Response::json(['success' => false, 'error' => 'Search query is required']);
            return;
        }

        try {
            $metadataService = new MetadataService();
            $logos = $metadataService->searchNetworkLogos($query);

            Response::json([
                'success' => true,
                'logos' => $logos
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate channel description using AI
     */
    public function generateDescription(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);

        $name = trim($input['name'] ?? '');
        $category = trim($input['category'] ?? '');
        $country = trim($input['country'] ?? '');
        $language = trim($input['language'] ?? '');

        if (empty($name)) {
            Response::json(['success' => false, 'error' => 'Channel name is required']);
            return;
        }

        try {
            $aiService = new AIService();

            $context = [
                'category' => $category,
                'country' => $country,
                'language' => $language,
            ];

            $description = $aiService->generateChannelDescription($name, $context);

            if ($description) {
                Response::json([
                    'success' => true,
                    'description' => $description
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'error' => 'Failed to generate description. Please check AI settings.'
                ]);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Process external logo URL (download and save)
     */
    private function processExternalLogo(string $url, string $type = 'logo'): ?string
    {
        if (empty($url)) {
            return null;
        }

        try {
            $imageService = new ImageService();
            $result = $imageService->processFromUrl($url, 'channel', null, $type);

            if ($result && $result['success'] && !empty($result['variants'])) {
                // Return the medium size variant, or landscape for landscape type
                if ($type === 'landscape' && isset($result['variants']['landscape'])) {
                    return $result['variants']['landscape'];
                }
                return $result['variants']['medium'] ?? $result['variants']['large'] ?? array_values($result['variants'])[0] ?? null;
            }
        } catch (\Exception $e) {
            // Log error but don't fail
            error_log('Failed to process external logo: ' . $e->getMessage());
        }

        return null;
    }

    // ========================================================================
    // IPTV-ORG IMPORT
    // ========================================================================

    /**
     * Search iptv-org channels (AJAX)
     */
    public function searchIptvOrg(): void
    {
        $service = new IptvOrgService();

        $filters = [
            'search' => $_POST['search'] ?? '',
            'country' => $_POST['country'] ?? '',
            'category' => $_POST['category'] ?? '',
            'limit' => 100,
        ];

        $result = $service->searchChannels($filters);
        Response::json($result);
    }

    /**
     * Get iptv-org countries list (AJAX)
     */
    public function iptvOrgCountries(): void
    {
        $service = new IptvOrgService();
        Response::json([
            'success' => true,
            'countries' => $service->getCountries(),
            'categories' => $service->getCategories(),
        ]);
    }

    /**
     * Import selected iptv-org channels (AJAX)
     */
    public function importIptvOrg(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            Response::json(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $channelsJson = $_POST['channels'] ?? '[]';
        $channels = json_decode($channelsJson, true);

        if (empty($channels) || !is_array($channels)) {
            Response::json(['success' => false, 'message' => 'No channels selected']);
            return;
        }

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($channels as $ch) {
            if (empty($ch['name']) || empty($ch['stream_url'])) {
                $errors++;
                continue;
            }

            // Check if channel already exists by name or stream URL
            $existing = $this->db->fetch(
                "SELECT id FROM channels WHERE name = ? OR stream_url = ?",
                [$ch['name'], $ch['stream_url']]
            );

            if ($existing) {
                $skipped++;
                continue;
            }

            try {
                $slug = $this->channelService->generateSlug($ch['name']);
                $keyCode = $this->channelService->generateKeyCode();

                $data = [
                    'name' => $ch['name'],
                    'slug' => $slug,
                    'key_code' => $keyCode,
                    'stream_url' => $ch['stream_url'],
                    'country' => $ch['country'] ?? null,
                    'language' => $ch['language'] ?? null,
                    'epg_channel_id' => $ch['iptv_org_id'] ?? null,
                    'external_id' => $ch['iptv_org_id'] ?? null,
                    'is_active' => 1,
                    'is_published' => 0,
                    'sort_order' => 0,
                ];

                // Set quality flags
                $quality = strtolower($ch['quality'] ?? '');
                if (str_contains($quality, '4k') || str_contains($quality, '2160')) {
                    $data['is_4k'] = 1;
                    $data['is_hd'] = 1;
                } elseif (str_contains($quality, '1080') || str_contains($quality, '720')) {
                    $data['is_hd'] = 1;
                }

                $channelId = $this->channelService->createChannel($data);

                // Process logo if available
                if (!empty($ch['logo_url'])) {
                    $localLogo = $this->processExternalLogo($ch['logo_url'], 'logo');
                    if ($localLogo) {
                        $this->channelService->updateChannel($channelId, ['logo_url' => $localLogo]);
                    }
                }

                // Map categories
                if (!empty($ch['categories'])) {
                    $categoryIds = [];
                    foreach ($ch['categories'] as $catName) {
                        $cat = $this->db->fetch(
                            "SELECT id FROM categories WHERE LOWER(name) = LOWER(?) AND type = 'live'",
                            [$catName]
                        );
                        if ($cat) {
                            $categoryIds[] = $cat['id'];
                        }
                    }
                    if (!empty($categoryIds)) {
                        $this->channelService->saveChannelCategories($channelId, $categoryIds, $categoryIds[0]);
                    }
                }

                $imported++;
            } catch (\Exception $e) {
                error_log('IPTV-org import error: ' . $e->getMessage());
                $errors++;
            }
        }

        $this->auth->logActivity(
            $this->auth->id(),
            'import',
            'channels',
            'iptv-org',
            null,
            ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors]
        );

        $message = "{$imported} channel(s) imported.";
        if ($skipped > 0) $message .= " {$skipped} already existed.";
        if ($errors > 0) $message .= " {$errors} error(s).";

        Response::json([
            'success' => true,
            'message' => $message,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }
}
