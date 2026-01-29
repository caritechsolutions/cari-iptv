<?php
/**
 * CARI-IPTV Category Management Controller
 */

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Database;
use CariIPTV\Core\Response;
use CariIPTV\Core\Session;
use CariIPTV\Services\AdminAuthService;
use CariIPTV\Services\CategoryService;

class CategoryController
{
    private Database $db;
    private AdminAuthService $auth;
    private CategoryService $categoryService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = new AdminAuthService();
        $this->categoryService = new CategoryService();
    }

    /**
     * List all categories
     */
    public function index(): void
    {
        $filters = [
            'search' => $_GET['search'] ?? '',
            'type' => $_GET['type'] ?? '',
            'is_active' => $_GET['is_active'] ?? '',
        ];

        $categories = $this->categoryService->getCategories($filters);
        $stats = $this->categoryService->getStatistics();

        Response::view('admin/categories/index', [
            'pageTitle' => 'Categories',
            'categories' => $categories,
            'stats' => $stats,
            'filters' => $filters,
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Store a new category (AJAX)
     */
    public function store(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? 'vod';
        $parentId = $_POST['parent_id'] ?? null;
        $icon = trim($_POST['icon'] ?? '');
        $isActive = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;
        $sortOrder = $_POST['sort_order'] ?? null;

        if (empty($name)) {
            $this->sendJson(['success' => false, 'message' => 'Category name is required']);
            return;
        }

        if (!in_array($type, ['live', 'vod', 'series'])) {
            $this->sendJson(['success' => false, 'message' => 'Invalid category type']);
            return;
        }

        try {
            $data = [
                'name' => $name,
                'type' => $type,
                'parent_id' => $parentId,
                'icon' => $icon,
                'is_active' => $isActive,
            ];

            if ($sortOrder !== null) {
                $data['sort_order'] = (int) $sortOrder;
            }

            $id = $this->categoryService->createCategory($data);

            $this->sendJson([
                'success' => true,
                'message' => 'Category created successfully',
                'id' => $id,
            ]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Update a category (AJAX)
     */
    public function update(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $category = $this->categoryService->getCategory($id);
        if (!$category) {
            $this->sendJson(['success' => false, 'message' => 'Category not found']);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            $this->sendJson(['success' => false, 'message' => 'Category name is required']);
            return;
        }

        try {
            $data = [
                'name' => $name,
                'parent_id' => $_POST['parent_id'] ?? null,
                'icon' => trim($_POST['icon'] ?? ''),
                'is_active' => isset($_POST['is_active']) ? (int) $_POST['is_active'] : $category['is_active'],
            ];

            if (isset($_POST['sort_order'])) {
                $data['sort_order'] = (int) $_POST['sort_order'];
            }

            $this->categoryService->updateCategory($id, $data);

            $this->sendJson([
                'success' => true,
                'message' => 'Category updated successfully',
            ]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Delete a category (AJAX)
     */
    public function delete(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $result = $this->categoryService->deleteCategory($id);
        $this->sendJson($result);
    }

    /**
     * Toggle active status (AJAX)
     */
    public function toggleActive(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $success = $this->categoryService->toggleActive($id);
        $this->sendJson([
            'success' => $success,
            'message' => $success ? 'Status updated' : 'Category not found',
        ]);
    }

    /**
     * Update sort order (AJAX)
     */
    public function updateOrder(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $order = json_decode($_POST['order'] ?? '[]', true);
        if (empty($order)) {
            $this->sendJson(['success' => false, 'message' => 'No order data provided']);
            return;
        }

        $this->categoryService->updateSortOrder($order);
        $this->sendJson(['success' => true, 'message' => 'Sort order updated']);
    }

    /**
     * Send JSON response
     */
    private function sendJson(array $data): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
