<?php

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Database;
use CariIPTV\Core\Response;
use CariIPTV\Core\Session;
use CariIPTV\Services\AdminAuthService;
use CariIPTV\Services\PackageService;

class PackageController
{
    private AdminAuthService $auth;
    private PackageService $packageService;

    public function __construct()
    {
        $this->auth = new AdminAuthService();
        $this->packageService = new PackageService();
    }

    /**
     * Main packages page (tabbed: Content Groups | Packages)
     */
    public function index(): void
    {
        $tab = $_GET['tab'] ?? 'packages';
        $contentGroups = [];
        $packages = [];
        $stats = ['packages_total' => 0, 'packages_active' => 0, 'packages_free' => 0, 'groups_total' => 0];

        try {
            $contentGroups = $this->packageService->getContentGroups();
        } catch (\Throwable $e) {
            error_log('PackageController: getContentGroups() failed: ' . $e->getMessage());
        }

        try {
            $packages = $this->packageService->getPackages();
        } catch (\Throwable $e) {
            error_log('PackageController: getPackages() failed: ' . $e->getMessage());
        }

        try {
            $stats = $this->packageService->getStatistics();
        } catch (\Throwable $e) {
            error_log('PackageController: getStatistics() failed: ' . $e->getMessage());
        }

        Response::view('admin/packages/index', [
            'pageTitle' => 'Packages',
            'tab' => $tab,
            'contentGroups' => $contentGroups,
            'packages' => $packages,
            'stats' => $stats,
            'currencies' => PackageService::getCurrencies(),
            'billingPeriods' => PackageService::getBillingPeriods(),
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    // ========== CONTENT GROUP ENDPOINTS ==========

    public function storeGroup(): void
    {
        try {
            if (!Session::validateCsrf($_POST['_token'] ?? '')) {
                Response::json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
                return;
            }

            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                Response::json(['success' => false, 'message' => 'Group name is required'], 422);
                return;
            }

            $existing = Database::getInstance()->fetch(
                "SELECT id FROM content_groups WHERE name = ?", [$name]
            );
            if ($existing) {
                Response::json(['success' => false, 'message' => 'A content group with this name already exists'], 422);
                return;
            }

            $id = $this->packageService->createContentGroup([
                'name' => $name,
                'description' => $_POST['description'] ?? '',
                'color' => $_POST['color'] ?? '#6366f1',
            ]);

            Response::json(['success' => true, 'message' => 'Content group created', 'id' => $id]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function updateGroup(int $id): void
    {
        try {
            if (!Session::validateCsrf($_POST['_token'] ?? '')) {
                Response::json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
                return;
            }

            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                Response::json(['success' => false, 'message' => 'Group name is required'], 422);
                return;
            }

            $existing = Database::getInstance()->fetch(
                "SELECT id FROM content_groups WHERE name = ? AND id != ?", [$name, $id]
            );
            if ($existing) {
                Response::json(['success' => false, 'message' => 'A content group with this name already exists'], 422);
                return;
            }

            $this->packageService->updateContentGroup($id, [
                'name' => $name,
                'description' => $_POST['description'] ?? '',
                'color' => $_POST['color'] ?? '#6366f1',
            ]);

            Response::json(['success' => true, 'message' => 'Content group updated']);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function deleteGroup(int $id): void
    {
        try {
            if (!Session::validateCsrf($_POST['_token'] ?? '')) {
                Response::json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
                return;
            }
            $this->packageService->deleteContentGroup($id);
            Response::json(['success' => true, 'message' => 'Content group deleted']);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function showGroup(int $id): void
    {
        try {
            $group = $this->packageService->getContentGroup($id);
            if (!$group) {
                Response::json(['success' => false, 'message' => 'Group not found'], 404);
                return;
            }
            Response::json(['success' => true, 'group' => $group]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function addGroupContent(int $id): void
    {
        try {
            if (!Session::validateCsrf($_POST['_token'] ?? '')) {
                Response::json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
                return;
            }

            $contentType = $_POST['content_type'] ?? '';
            $contentId = (int) ($_POST['content_id'] ?? 0);

            if (!in_array($contentType, ['channel', 'movie', 'series', 'category']) || $contentId <= 0) {
                Response::json(['success' => false, 'message' => 'Invalid content'], 422);
                return;
            }

            $added = $this->packageService->addContentToGroup($id, $contentType, $contentId);
            if (!$added) {
                Response::json(['success' => false, 'message' => 'Content already in this group'], 422);
                return;
            }

            Response::json(['success' => true, 'message' => 'Content added to group']);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function removeGroupContent(int $id, int $iid): void
    {
        try {
            if (!Session::validateCsrf($_POST['_token'] ?? '')) {
                Response::json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
                return;
            }
            $this->packageService->removeContentFromGroup($id, $iid);
            Response::json(['success' => true, 'message' => 'Content removed from group']);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function searchContent(): void
    {
        try {
            $type = $_GET['type'] ?? 'channel';
            $query = $_GET['q'] ?? '';
            $results = $this->packageService->searchContent($type, $query);
            Response::json(['success' => true, 'results' => $results]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ========== PACKAGE ENDPOINTS ==========

    public function showPackage(int $id): void
    {
        try {
            $pkg = $this->packageService->getPackage($id);
            if (!$pkg) {
                Response::json(['success' => false, 'message' => 'Package not found'], 404);
                return;
            }
            Response::json(['success' => true, 'package' => $pkg]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function storePackage(): void
    {
        try {
            if (!Session::validateCsrf($_POST['_token'] ?? '')) {
                Response::json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
                return;
            }

            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                Response::json(['success' => false, 'message' => 'Package name is required'], 422);
                return;
            }

            $existing = Database::getInstance()->fetch(
                "SELECT id FROM packages WHERE name = ?", [$name]
            );
            if ($existing) {
                Response::json(['success' => false, 'message' => 'A package with this name already exists'], 422);
                return;
            }

            $data = $this->extractPackageData($_POST);
            $id = $this->packageService->createPackage($data);

            Response::json(['success' => true, 'message' => 'Package created', 'id' => $id]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function updatePackage(int $id): void
    {
        try {
            if (!Session::validateCsrf($_POST['_token'] ?? '')) {
                Response::json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
                return;
            }

            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                Response::json(['success' => false, 'message' => 'Package name is required'], 422);
                return;
            }

            $existing = Database::getInstance()->fetch(
                "SELECT id FROM packages WHERE name = ? AND id != ?", [$name, $id]
            );
            if ($existing) {
                Response::json(['success' => false, 'message' => 'A package with this name already exists'], 422);
                return;
            }

            $data = $this->extractPackageData($_POST);
            $this->packageService->updatePackage($id, $data);

            Response::json(['success' => true, 'message' => 'Package updated']);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function deletePackage(int $id): void
    {
        try {
            if (!Session::validateCsrf($_POST['_token'] ?? '')) {
                Response::json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
                return;
            }
            $this->packageService->deletePackage($id);
            Response::json(['success' => true, 'message' => 'Package deleted']);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ========== HELPERS ==========

    private function extractPackageData(array $post): array
    {
        $features = [];
        if (!empty($post['video_quality'])) $features['video_quality'] = $post['video_quality'];
        if (isset($post['catchup_days'])) $features['catchup_days'] = (int) $post['catchup_days'];
        if (isset($post['npvr_hours'])) $features['npvr_hours'] = (int) $post['npvr_hours'];
        $features['ad_free'] = isset($post['ad_free']) ? true : false;

        return [
            'name' => trim($post['name'] ?? ''),
            'description' => trim($post['description'] ?? ''),
            'color' => $post['color'] ?? '#6366f1',
            'price' => $post['price'] ?? 0,
            'currency' => $post['currency'] ?? 'USD',
            'billing_period' => $post['billing_period'] ?? 'monthly',
            'tax_rate' => $post['tax_rate'] ?? 0,
            'tax_inclusive' => isset($post['tax_inclusive']) ? 1 : 0,
            'trial_days' => $post['trial_days'] ?? 0,
            'max_connections' => $post['max_connections'] ?? 1,
            'geo_countries' => $post['geo_countries'] ?? [],
            'platforms' => $post['platforms'] ?? [],
            'features' => $features,
            'is_free' => isset($post['is_free']) ? 1 : 0,
            'is_active' => isset($post['is_active']) ? 1 : 0,
            'is_featured' => isset($post['is_featured']) ? 1 : 0,
            'sort_order' => $post['sort_order'] ?? 0,
            'content_group_ids' => $post['content_group_ids'] ?? [],
        ];
    }
}
