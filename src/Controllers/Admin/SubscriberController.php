<?php

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Response;
use CariIPTV\Core\Session;
use CariIPTV\Services\AdminAuthService;
use CariIPTV\Services\SubscriberService;

class SubscriberController
{
    private AdminAuthService $auth;
    private SubscriberService $subscriberService;

    public function __construct()
    {
        $this->auth = new AdminAuthService();
        $this->subscriberService = new SubscriberService();
    }

    /**
     * Subscriber listing page with groups
     */
    public function index(): void
    {
        $filters = [
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? '',
            'group_id' => $_GET['group_id'] ?? '',
            'page' => $_GET['page'] ?? 1,
            'per_page' => $_GET['per_page'] ?? 25,
        ];

        $result = $this->subscriberService->getSubscribers($filters);
        $groups = $this->subscriberService->getGroups();
        $stats = $this->subscriberService->getStatistics();

        Response::view('admin/subscribers/index', [
            'pageTitle' => 'Subscribers',
            'subscribers' => $result['data'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total_pages' => $result['total_pages'],
            ],
            'filters' => $filters,
            'groups' => $groups,
            'stats' => $stats,
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Get subscriber data for editing (AJAX)
     */
    public function show(int $id): void
    {
        try {
            $subscriber = $this->subscriberService->getSubscriber($id);
            if (!$subscriber) {
                Response::json(['success' => false, 'message' => 'Subscriber not found'], 404);
                return;
            }

            // Don't send password hash to frontend
            unset($subscriber['password']);

            Response::json(['success' => true, 'subscriber' => $subscriber]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error loading subscriber'], 500);
        }
    }

    /**
     * Store a new subscriber (AJAX)
     */
    public function store(): void
    {
        try {
            $token = $_POST['_token'] ?? '';
            if (!Session::validateCsrf($token)) {
                Response::json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
                return;
            }

            $data = $this->extractSubscriberData($_POST);

            // Validate required fields
            if (empty($data['first_name']) && empty($data['username']) && empty($data['email'])) {
                Response::json(['success' => false, 'message' => 'At least a name, username, or email is required'], 422);
                return;
            }

            // Check username uniqueness
            if (!empty($data['username'])) {
                $existing = \CariIPTV\Core\Database::getInstance()->fetch(
                    "SELECT id FROM subscribers WHERE username = ?",
                    [$data['username']]
                );
                if ($existing) {
                    Response::json(['success' => false, 'message' => 'Username already exists'], 422);
                    return;
                }
            }

            $id = $this->subscriberService->createSubscriber($data);

            Response::json([
                'success' => true,
                'message' => 'Subscriber created successfully',
                'id' => $id,
            ]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error creating subscriber: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update a subscriber (AJAX)
     */
    public function update(int $id): void
    {
        try {
            $token = $_POST['_token'] ?? '';
            if (!Session::validateCsrf($token)) {
                Response::json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
                return;
            }

            $subscriber = $this->subscriberService->getSubscriber($id);
            if (!$subscriber) {
                Response::json(['success' => false, 'message' => 'Subscriber not found'], 404);
                return;
            }

            $data = $this->extractSubscriberData($_POST);

            // Check username uniqueness (excluding current)
            if (!empty($data['username'])) {
                $existing = \CariIPTV\Core\Database::getInstance()->fetch(
                    "SELECT id FROM subscribers WHERE username = ? AND id != ?",
                    [$data['username'], $id]
                );
                if ($existing) {
                    Response::json(['success' => false, 'message' => 'Username already exists'], 422);
                    return;
                }
            }

            $this->subscriberService->updateSubscriber($id, $data);

            Response::json([
                'success' => true,
                'message' => 'Subscriber updated successfully',
            ]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error updating subscriber: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete a subscriber (AJAX)
     */
    public function delete(int $id): void
    {
        try {
            $token = $_POST['_token'] ?? '';
            if (!Session::validateCsrf($token)) {
                Response::json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
                return;
            }

            $this->subscriberService->deleteSubscriber($id);

            Response::json([
                'success' => true,
                'message' => 'Subscriber deleted successfully',
            ]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error deleting subscriber'], 500);
        }
    }

    /**
     * Toggle subscriber active/inactive (AJAX)
     */
    public function toggleStatus(int $id): void
    {
        try {
            $newStatus = $this->subscriberService->toggleStatus($id);
            if ($newStatus === null) {
                Response::json(['success' => false, 'message' => 'Subscriber not found'], 404);
                return;
            }

            Response::json([
                'success' => true,
                'message' => 'Status updated to ' . $newStatus,
                'status' => $newStatus,
            ]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error toggling status'], 500);
        }
    }

    /**
     * Bulk action on subscribers (AJAX)
     */
    public function bulkAction(): void
    {
        try {
            $token = $_POST['_token'] ?? '';
            if (!Session::validateCsrf($token)) {
                Response::json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
                return;
            }

            $action = $_POST['action'] ?? '';
            $ids = $_POST['ids'] ?? [];

            if (empty($action) || empty($ids)) {
                Response::json(['success' => false, 'message' => 'No action or items selected'], 422);
                return;
            }

            if (is_string($ids)) {
                $ids = array_map('intval', explode(',', $ids));
            }

            $count = $this->subscriberService->bulkAction($action, $ids);

            Response::json([
                'success' => true,
                'message' => "{$count} subscriber(s) updated",
            ]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error performing bulk action'], 500);
        }
    }

    // ========== GROUP ENDPOINTS ==========

    /**
     * Store a new group (AJAX)
     */
    public function storeGroup(): void
    {
        try {
            $token = $_POST['_token'] ?? '';
            if (!Session::validateCsrf($token)) {
                Response::json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
                return;
            }

            if (empty($_POST['name'])) {
                Response::json(['success' => false, 'message' => 'Group name is required'], 422);
                return;
            }

            $id = $this->subscriberService->createGroup([
                'name' => $_POST['name'],
                'description' => $_POST['description'] ?? '',
                'color' => $_POST['color'] ?? '#6366f1',
                'icon' => $_POST['icon'] ?? 'lucide-users',
            ]);

            Response::json([
                'success' => true,
                'message' => 'Group created successfully',
                'id' => $id,
            ]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error creating group: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update a group (AJAX)
     */
    public function updateGroup(int $id): void
    {
        try {
            $token = $_POST['_token'] ?? '';
            if (!Session::validateCsrf($token)) {
                Response::json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
                return;
            }

            if (empty($_POST['name'])) {
                Response::json(['success' => false, 'message' => 'Group name is required'], 422);
                return;
            }

            $this->subscriberService->updateGroup($id, [
                'name' => $_POST['name'],
                'description' => $_POST['description'] ?? '',
                'color' => $_POST['color'] ?? '#6366f1',
                'icon' => $_POST['icon'] ?? 'lucide-users',
            ]);

            Response::json([
                'success' => true,
                'message' => 'Group updated successfully',
            ]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error updating group'], 500);
        }
    }

    /**
     * Delete a group (AJAX)
     */
    public function deleteGroup(int $id): void
    {
        try {
            $token = $_POST['_token'] ?? '';
            if (!Session::validateCsrf($token)) {
                Response::json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
                return;
            }

            $this->subscriberService->deleteGroup($id);

            Response::json([
                'success' => true,
                'message' => 'Group deleted successfully',
            ]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error deleting group'], 500);
        }
    }

    // ========== HELPERS ==========

    /**
     * Extract subscriber data from POST
     */
    private function extractSubscriberData(array $post): array
    {
        return [
            'username' => trim($post['username'] ?? ''),
            'email' => trim($post['email'] ?? ''),
            'password' => $post['password'] ?? '',
            'first_name' => trim($post['first_name'] ?? ''),
            'last_name' => trim($post['last_name'] ?? ''),
            'phone' => trim($post['phone'] ?? ''),
            'status' => $post['status'] ?? 'active',
            'is_disabled' => isset($post['is_disabled']) ? 1 : 0,
            'email_verified' => isset($post['email_verified']) ? 1 : 0,
            'phone_verified' => isset($post['phone_verified']) ? 1 : 0,
            'max_connections' => (int) ($post['max_connections'] ?? 1),
            'npvr_limit' => (int) ($post['npvr_limit'] ?? 0),
            'birthday' => $post['birthday'] ?? null,
            'parental_pin' => $post['parental_pin'] ?? '0000',
            'country' => trim($post['country'] ?? ''),
            'city' => trim($post['city'] ?? ''),
            'address' => trim($post['address'] ?? ''),
            'zip_code' => trim($post['zip_code'] ?? ''),
            'external_id' => trim($post['external_id'] ?? ''),
            'notes' => trim($post['notes'] ?? ''),
            'groups' => $post['groups'] ?? [],
        ];
    }
}
