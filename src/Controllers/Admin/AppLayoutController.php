<?php
/**
 * CARI-IPTV App Layout Controller
 * Admin panel for building app home screen layouts
 */

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Database;
use CariIPTV\Core\Response;
use CariIPTV\Core\Session;
use CariIPTV\Services\AdminAuthService;
use CariIPTV\Services\AppLayoutService;

class AppLayoutController
{
    private Database $db;
    private AdminAuthService $auth;
    private AppLayoutService $layoutService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = new AdminAuthService();
        $this->layoutService = new AppLayoutService();
    }

    /**
     * Layout listing page
     */
    public function index(): void
    {
        $platform = $_GET['platform'] ?? null;
        $filters = [];
        if ($platform) {
            $filters['platform'] = $platform;
        }

        $layouts = $this->layoutService->getLayouts($filters);
        $stats = $this->layoutService->getStatistics();

        Response::view('admin/app-layout/index', [
            'pageTitle' => 'App Layout',
            'layouts' => $layouts,
            'stats' => $stats,
            'activePlatform' => $platform,
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Create a new layout (AJAX)
     */
    public function store(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $platform = $_POST['platform'] ?? '';

        if (empty($name)) {
            $this->sendJson(['success' => false, 'message' => 'Layout name is required']);
            return;
        }

        $validPlatforms = ['web', 'mobile', 'tv', 'stb'];
        if (!in_array($platform, $validPlatforms)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid platform']);
            return;
        }

        try {
            $id = $this->layoutService->createLayout([
                'name' => $name,
                'platform' => $platform,
                'status' => 'draft',
                'created_by' => $this->auth->id(),
                'updated_by' => $this->auth->id(),
            ]);

            $this->sendJson(['success' => true, 'message' => 'Layout created', 'id' => $id]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'message' => 'Failed to create layout']);
        }
    }

    /**
     * Delete a layout (AJAX)
     */
    public function delete(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $layout = $this->layoutService->getLayout($id);
        if (!$layout) {
            $this->sendJson(['success' => false, 'message' => 'Layout not found']);
            return;
        }

        if ($layout['status'] === 'published') {
            $this->sendJson(['success' => false, 'message' => 'Cannot delete a published layout. Archive it first.']);
            return;
        }

        $this->layoutService->deleteLayout($id);
        $this->sendJson(['success' => true, 'message' => 'Layout deleted']);
    }

    /**
     * Duplicate a layout (AJAX)
     */
    public function duplicate(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $newId = $this->layoutService->duplicateLayout($id, $this->auth->id());
        if ($newId) {
            $this->sendJson(['success' => true, 'message' => 'Layout duplicated', 'id' => $newId]);
        } else {
            $this->sendJson(['success' => false, 'message' => 'Failed to duplicate layout']);
        }
    }

    /**
     * Publish a layout (AJAX)
     */
    public function publish(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $result = $this->layoutService->publishLayout($id, $this->auth->id());
        if ($result) {
            $this->sendJson(['success' => true, 'message' => 'Layout published and set as default']);
        } else {
            $this->sendJson(['success' => false, 'message' => 'Failed to publish layout']);
        }
    }

    /**
     * Update layout details (AJAX)
     */
    public function update(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $layout = $this->layoutService->getLayout($id);
        if (!$layout) {
            $this->sendJson(['success' => false, 'message' => 'Layout not found']);
            return;
        }

        $data = ['updated_by' => $this->auth->id()];
        if (isset($_POST['name'])) $data['name'] = trim($_POST['name']);
        if (isset($_POST['status'])) $data['status'] = $_POST['status'];
        if (isset($_POST['schedule_start'])) $data['schedule_start'] = $_POST['schedule_start'] ?: null;
        if (isset($_POST['schedule_end'])) $data['schedule_end'] = $_POST['schedule_end'] ?: null;

        $this->layoutService->updateLayout($id, $data);
        $this->sendJson(['success' => true, 'message' => 'Layout updated']);
    }

    /**
     * Builder page for a specific layout
     */
    public function builder(int $id): void
    {
        $layout = $this->layoutService->getLayout($id);
        if (!$layout) {
            Session::setFlash('error', 'Layout not found');
            Response::redirect('/admin/app-layout');
            return;
        }

        $sections = $this->layoutService->getSections($id);
        $sectionTypes = $this->layoutService->getSectionTypes();

        // Resolve items for each section
        foreach ($sections as &$section) {
            $typeInfo = $sectionTypes[$section['section_type']] ?? null;
            if ($typeInfo && $typeInfo['supports_items']) {
                $items = $this->layoutService->getItems($section['id']);
                $section['items'] = $this->layoutService->resolveItems($items);
            } else {
                $section['items'] = [];
            }
        }

        Response::view('admin/app-layout/builder', [
            'pageTitle' => 'Edit Layout: ' . $layout['name'],
            'appLayout' => $layout,
            'sections' => $sections,
            'sectionTypes' => $sectionTypes,
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    // ========================================================================
    // SECTION ENDPOINTS (AJAX)
    // ========================================================================

    /**
     * Add a section to a layout
     */
    public function addSection(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $sectionType = $_POST['section_type'] ?? '';
        $types = $this->layoutService->getSectionTypes();

        if (!isset($types[$sectionType])) {
            $this->sendJson(['success' => false, 'message' => 'Invalid section type']);
            return;
        }

        $typeDef = $types[$sectionType];

        // Check max per layout
        $existing = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM app_layout_sections WHERE layout_id = ? AND section_type = ?",
            [$id, $sectionType]
        );
        if ((int) $existing >= $typeDef['max_per_layout']) {
            $this->sendJson(['success' => false, 'message' => "Maximum {$typeDef['max_per_layout']} {$typeDef['name']} section(s) per layout"]);
            return;
        }

        $sectionId = $this->layoutService->addSection($id, [
            'section_type' => $sectionType,
            'title' => $_POST['title'] ?? $typeDef['name'],
            'settings' => $typeDef['default_settings'],
        ]);

        $this->sendJson([
            'success' => true,
            'message' => "{$typeDef['name']} section added",
            'section_id' => $sectionId,
        ]);
    }

    /**
     * Update a section's settings
     */
    public function updateSection(int $id, int $sectionId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $section = $this->layoutService->getSection($sectionId);
        if (!$section || (int) $section['layout_id'] !== $id) {
            $this->sendJson(['success' => false, 'message' => 'Section not found']);
            return;
        }

        $data = [];
        if (isset($_POST['title'])) $data['title'] = trim($_POST['title']);
        if (isset($_POST['is_active'])) $data['is_active'] = (int) $_POST['is_active'];

        if (isset($_POST['settings'])) {
            $settings = $_POST['settings'];
            if (is_string($settings)) {
                $settings = json_decode($settings, true);
            }
            $data['settings'] = $settings;
        }

        $this->layoutService->updateSection($sectionId, $data);
        $this->sendJson(['success' => true, 'message' => 'Section updated']);
    }

    /**
     * Delete a section
     */
    public function deleteSection(int $id, int $sectionId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $section = $this->layoutService->getSection($sectionId);
        if (!$section || (int) $section['layout_id'] !== $id) {
            $this->sendJson(['success' => false, 'message' => 'Section not found']);
            return;
        }

        $this->layoutService->deleteSection($sectionId);
        $this->sendJson(['success' => true, 'message' => 'Section deleted']);
    }

    /**
     * Reorder sections
     */
    public function reorderSections(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $order = $_POST['order'] ?? [];
        if (is_string($order)) {
            $order = json_decode($order, true);
        }

        if (!is_array($order) || empty($order)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid order data']);
            return;
        }

        $this->layoutService->reorderSections($id, $order);
        $this->sendJson(['success' => true, 'message' => 'Order updated']);
    }

    // ========================================================================
    // ITEM ENDPOINTS (AJAX)
    // ========================================================================

    /**
     * Add an item to a section
     */
    public function addItem(int $id, int $sectionId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $section = $this->layoutService->getSection($sectionId);
        if (!$section || (int) $section['layout_id'] !== $id) {
            $this->sendJson(['success' => false, 'message' => 'Section not found']);
            return;
        }

        $contentType = $_POST['content_type'] ?? '';
        $contentId = !empty($_POST['content_id']) ? (int) $_POST['content_id'] : null;

        $validTypes = ['movie', 'series', 'channel', 'category', 'custom'];
        if (!in_array($contentType, $validTypes)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid content type']);
            return;
        }

        $itemId = $this->layoutService->addItem($sectionId, [
            'content_type' => $contentType,
            'content_id' => $contentId,
            'settings' => isset($_POST['settings']) ? json_decode($_POST['settings'], true) : null,
        ]);

        $this->sendJson(['success' => true, 'message' => 'Item added', 'item_id' => $itemId]);
    }

    /**
     * Remove an item from a section
     */
    public function removeItem(int $id, int $sectionId, int $itemId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $this->layoutService->removeItem($itemId);
        $this->sendJson(['success' => true, 'message' => 'Item removed']);
    }

    /**
     * Reorder items within a section
     */
    public function reorderItems(int $id, int $sectionId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $order = $_POST['order'] ?? [];
        if (is_string($order)) {
            $order = json_decode($order, true);
        }

        if (!is_array($order)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid order data']);
            return;
        }

        $this->layoutService->reorderItems($sectionId, $order);
        $this->sendJson(['success' => true, 'message' => 'Item order updated']);
    }

    // ========================================================================
    // PAGES
    // ========================================================================

    /**
     * Pages & Navigation management page
     */
    public function pages(): void
    {
        $platform = $_GET['platform'] ?? 'web';
        $validPlatforms = ['web', 'mobile', 'tv', 'stb'];
        if (!in_array($platform, $validPlatforms)) $platform = 'web';

        $pages = $this->layoutService->getPages($platform);
        $pageTypes = $this->layoutService->getPageTypes();
        $navigation = $this->layoutService->getNavigationMenus($platform);

        // Get available layouts for linking
        $availableLayouts = $this->layoutService->getLayouts([
            'platform' => $platform,
        ]);

        Response::view('admin/app-layout/pages', [
            'pageTitle' => 'Pages & Navigation',
            'pages' => $pages,
            'pageTypes' => $pageTypes,
            'navigation' => $navigation,
            'availableLayouts' => $availableLayouts,
            'activePlatform' => $platform,
            'user' => $this->auth->user(),
            'csrf' => Session::csrf(),
        ], 'admin');
    }

    /**
     * Create a new page (AJAX)
     */
    public function storePage(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $pageType = $_POST['page_type'] ?? '';
        $platform = $_POST['platform'] ?? '';

        if (empty($name) || empty($slug)) {
            $this->sendJson(['success' => false, 'message' => 'Name and slug are required']);
            return;
        }

        $validTypes = $this->layoutService->getPageTypes();
        if (!isset($validTypes[$pageType])) {
            $this->sendJson(['success' => false, 'message' => 'Invalid page type']);
            return;
        }

        $validPlatforms = ['web', 'mobile', 'tv', 'stb'];
        if (!in_array($platform, $validPlatforms)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid platform']);
            return;
        }

        try {
            $id = $this->layoutService->createPage([
                'name' => $name,
                'slug' => $slug,
                'page_type' => $pageType,
                'platform' => $platform,
                'icon' => $_POST['icon'] ?? $validTypes[$pageType]['icon'],
                'layout_id' => !empty($_POST['layout_id']) ? (int) $_POST['layout_id'] : null,
            ]);

            $this->sendJson(['success' => true, 'message' => 'Page created', 'id' => $id]);
        } catch (\Exception $e) {
            $this->sendJson(['success' => false, 'message' => 'Failed to create page']);
        }
    }

    /**
     * Update a page (AJAX)
     */
    public function updatePage(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $page = $this->layoutService->getPage($id);
        if (!$page) {
            $this->sendJson(['success' => false, 'message' => 'Page not found']);
            return;
        }

        $data = [];
        if (isset($_POST['name'])) $data['name'] = trim($_POST['name']);
        if (isset($_POST['slug'])) $data['slug'] = trim($_POST['slug']);
        if (isset($_POST['icon'])) $data['icon'] = $_POST['icon'];
        if (array_key_exists('layout_id', $_POST)) {
            $data['layout_id'] = !empty($_POST['layout_id']) ? (int) $_POST['layout_id'] : null;
        }
        if (isset($_POST['is_active'])) $data['is_active'] = (int) $_POST['is_active'];

        $this->layoutService->updatePage($id, $data);
        $this->sendJson(['success' => true, 'message' => 'Page updated']);
    }

    /**
     * Delete a page (AJAX)
     */
    public function deletePage(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $result = $this->layoutService->deletePage($id);
        if ($result) {
            $this->sendJson(['success' => true, 'message' => 'Page deleted']);
        } else {
            $this->sendJson(['success' => false, 'message' => 'Cannot delete system pages']);
        }
    }

    /**
     * Reorder pages (AJAX)
     */
    public function reorderPages(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $platform = $_POST['platform'] ?? '';
        $order = $_POST['order'] ?? [];
        if (is_string($order)) $order = json_decode($order, true);

        if (empty($platform) || !is_array($order)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid data']);
            return;
        }

        $this->layoutService->reorderPages($platform, $order);
        $this->sendJson(['success' => true, 'message' => 'Page order updated']);
    }

    // ========================================================================
    // NAVIGATION
    // ========================================================================

    /**
     * Save navigation settings (AJAX)
     */
    public function saveNavigation(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $platform = $_POST['platform'] ?? '';
        $position = $_POST['position'] ?? 'main';

        $validPlatforms = ['web', 'mobile', 'tv', 'stb'];
        $validPositions = ['main', 'footer', 'sidebar', 'top'];

        if (!in_array($platform, $validPlatforms) || !in_array($position, $validPositions)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid platform or position']);
            return;
        }

        $settings = $_POST['settings'] ?? '{}';
        if (is_string($settings)) $settings = json_decode($settings, true);

        $navId = $this->layoutService->saveNavigation($platform, $position, [
            'name' => $_POST['name'] ?? 'Navigation',
            'settings' => $settings,
            'is_active' => (int) ($_POST['is_active'] ?? 1),
        ]);

        $this->sendJson(['success' => true, 'message' => 'Navigation saved', 'id' => $navId]);
    }

    /**
     * Add navigation item (AJAX)
     */
    public function addNavItem(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $navigationId = (int) ($_POST['navigation_id'] ?? 0);
        if (!$navigationId) {
            $this->sendJson(['success' => false, 'message' => 'Navigation menu required']);
            return;
        }

        $label = trim($_POST['label'] ?? '');
        if (empty($label)) {
            $this->sendJson(['success' => false, 'message' => 'Label is required']);
            return;
        }

        $target = $_POST['target'] ?? 'page';
        $data = [
            'label' => $label,
            'icon' => $_POST['icon'] ?? null,
            'target' => $target,
        ];

        if ($target === 'page') {
            $data['page_id'] = !empty($_POST['page_id']) ? (int) $_POST['page_id'] : null;
        } else {
            $data['url'] = $_POST['url'] ?? '';
        }

        $itemId = $this->layoutService->addNavigationItem($navigationId, $data);
        $this->sendJson(['success' => true, 'message' => 'Navigation item added', 'item_id' => $itemId]);
    }

    /**
     * Update navigation item (AJAX)
     */
    public function updateNavItem(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $data = [];
        if (isset($_POST['label'])) $data['label'] = trim($_POST['label']);
        if (isset($_POST['icon'])) $data['icon'] = $_POST['icon'];
        if (isset($_POST['target'])) $data['target'] = $_POST['target'];
        if (array_key_exists('page_id', $_POST)) {
            $data['page_id'] = !empty($_POST['page_id']) ? (int) $_POST['page_id'] : null;
        }
        if (array_key_exists('url', $_POST)) $data['url'] = $_POST['url'];
        if (isset($_POST['is_active'])) $data['is_active'] = (int) $_POST['is_active'];

        $this->layoutService->updateNavigationItem($id, $data);
        $this->sendJson(['success' => true, 'message' => 'Navigation item updated']);
    }

    /**
     * Remove navigation item (AJAX)
     */
    public function removeNavItem(int $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $this->layoutService->removeNavigationItem($id);
        $this->sendJson(['success' => true, 'message' => 'Navigation item removed']);
    }

    /**
     * Reorder navigation items (AJAX)
     */
    public function reorderNavItems(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::validateCsrf($token)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid request']);
            return;
        }

        $navigationId = (int) ($_POST['navigation_id'] ?? 0);
        $order = $_POST['order'] ?? [];
        if (is_string($order)) $order = json_decode($order, true);

        if (!$navigationId || !is_array($order)) {
            $this->sendJson(['success' => false, 'message' => 'Invalid data']);
            return;
        }

        $this->layoutService->reorderNavigationItems($navigationId, $order);
        $this->sendJson(['success' => true, 'message' => 'Order updated']);
    }

    // ========================================================================
    // CONTENT SEARCH (AJAX)
    // ========================================================================

    /**
     * Search content for the picker
     */
    public function searchContent(): void
    {
        $type = $_GET['type'] ?? 'movie';
        $query = $_GET['q'] ?? '';

        $results = $this->layoutService->searchContent($type, $query);
        $this->sendJson(['success' => true, 'results' => $results]);
    }

    // ========================================================================
    // HELPERS
    // ========================================================================

    private function sendJson(array $data): void
    {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
