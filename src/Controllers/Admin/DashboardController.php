<?php
/**
 * CARI-IPTV Admin Dashboard Controller
 */

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Response;
use CariIPTV\Core\Session;
use CariIPTV\Services\AdminAuthService;
use CariIPTV\Services\DashboardService;

class DashboardController
{
    private AdminAuthService $auth;
    private DashboardService $dashboardService;

    public function __construct()
    {
        $this->auth = new AdminAuthService();
        $this->dashboardService = new DashboardService();
    }

    /**
     * Show dashboard with dynamic widgets
     */
    public function index(): void
    {
        $user = $this->auth->user();
        $userId = (int) $user['id'];
        $activeWidgets = $this->dashboardService->getUserWidgets($userId);
        $allWidgets = DashboardService::getAvailableWidgets();
        $widgetData = $this->dashboardService->getMultipleWidgetData($activeWidgets);

        Response::view('admin/dashboard/index', [
            'pageTitle'     => 'Dashboard',
            'user'          => $user,
            'csrf'          => Session::csrf(),
            'activeWidgets' => $activeWidgets,
            'allWidgets'    => $allWidgets,
            'widgetData'    => $widgetData,
        ], 'admin');
    }

    /**
     * Add a widget to user's dashboard
     */
    public function addWidget(): void
    {
        Session::validateCsrf($_POST['_token'] ?? '');

        $widget = trim($_POST['widget'] ?? '');
        $all = DashboardService::getAvailableWidgets();

        if (!isset($all[$widget])) {
            Response::json(['success' => false, 'message' => 'Invalid widget'], 400);
            return;
        }

        $user = $this->auth->user();
        $userId = (int) $user['id'];
        $current = $this->dashboardService->getUserWidgets($userId);

        if (in_array($widget, $current)) {
            Response::json(['success' => false, 'message' => 'Widget already on dashboard'], 422);
            return;
        }

        $current[] = $widget;
        $this->dashboardService->saveUserWidgets($userId, $current);

        // Return the widget data so the frontend can render it immediately
        $data = $this->dashboardService->getWidgetData($widget);
        $meta = $all[$widget];
        Response::json(['success' => true, 'message' => 'Widget added', 'widget' => $widget, 'data' => $data, 'meta' => $meta]);
    }

    /**
     * Remove a widget from user's dashboard
     */
    public function removeWidget(): void
    {
        Session::validateCsrf($_POST['_token'] ?? '');

        $widget = trim($_POST['widget'] ?? '');
        $user = $this->auth->user();
        $userId = (int) $user['id'];
        $current = $this->dashboardService->getUserWidgets($userId);

        $current = array_values(array_filter($current, fn($w) => $w !== $widget));
        $this->dashboardService->saveUserWidgets($userId, $current);

        Response::json(['success' => true, 'message' => 'Widget removed']);
    }

    /**
     * Reorder widgets via drag-and-drop
     */
    public function reorderWidgets(): void
    {
        Session::validateCsrf($_POST['_token'] ?? '');

        $order = $_POST['order'] ?? [];
        if (!is_array($order)) {
            Response::json(['success' => false, 'message' => 'Invalid order'], 400);
            return;
        }

        $all = DashboardService::getAvailableWidgets();
        $order = array_values(array_filter($order, fn($w) => isset($all[$w])));

        $user = $this->auth->user();
        $userId = (int) $user['id'];
        $this->dashboardService->saveUserWidgets($userId, $order);

        Response::json(['success' => true, 'message' => 'Order saved']);
    }

    /**
     * Get fresh data for a single widget (AJAX)
     */
    public function widgetData(): void
    {
        $widget = trim($_GET['widget'] ?? '');
        $all = DashboardService::getAvailableWidgets();

        if (!isset($all[$widget])) {
            Response::json(['success' => false, 'message' => 'Invalid widget'], 400);
            return;
        }

        $data = $this->dashboardService->getWidgetData($widget);
        Response::json(['success' => true, 'data' => $data, 'meta' => $all[$widget]]);
    }
}
