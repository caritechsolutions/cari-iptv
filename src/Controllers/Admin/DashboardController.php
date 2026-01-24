<?php
/**
 * CARI-IPTV Admin Dashboard Controller
 */

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Database;
use CariIPTV\Core\Response;
use CariIPTV\Core\Session;
use CariIPTV\Services\AdminAuthService;

class DashboardController
{
    private Database $db;
    private AdminAuthService $auth;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auth = new AdminAuthService();
    }

    /**
     * Show dashboard
     */
    public function index(): void
    {
        $stats = $this->getStats();
        $recentActivity = $this->getRecentActivity();
        $activeStreams = $this->getActiveStreams();
        $popularContent = $this->getPopularContent();
        $recentUsers = $this->getRecentUsers();
        $chartData = $this->getChartData();

        Response::view('admin/dashboard/index', [
            'pageTitle' => 'Dashboard',
            'stats' => $stats,
            'recentActivity' => $recentActivity,
            'activeStreams' => $activeStreams,
            'popularContent' => $popularContent,
            'recentUsers' => $recentUsers,
            'chartData' => $chartData,
            'user' => $this->auth->user(),
        ], 'admin');
    }

    /**
     * Get platform statistics
     */
    private function getStats(): array
    {
        // Total subscribers
        $totalUsers = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM users"
        );

        // Active subscribers
        $activeUsers = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM users WHERE status = 'active'"
        );

        // Total channels
        $totalChannels = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM channels WHERE is_active = 1"
        );

        // Total VOD
        $totalVod = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM vod_assets WHERE is_active = 1"
        );

        // Active subscriptions
        $activeSubscriptions = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM subscriptions WHERE status = 'active'"
        );

        // Current active streams (last 5 minutes)
        $activeStreams = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM stream_sessions
             WHERE ended_at IS NULL AND last_heartbeat > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
        );

        // Today's views
        $todayViews = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM analytics_events
             WHERE event_type = 'play' AND DATE(created_at) = CURDATE()"
        );

        // Revenue this month (based on active subscriptions)
        $monthlyRevenue = $this->db->fetchColumn(
            "SELECT COALESCE(SUM(p.price), 0) FROM subscriptions s
             INNER JOIN packages p ON s.package_id = p.id
             WHERE s.status = 'active'
             AND MONTH(s.start_date) = MONTH(CURDATE())
             AND YEAR(s.start_date) = YEAR(CURDATE())"
        );

        // Calculate growth (new users this month vs last month)
        $newUsersThisMonth = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM users
             WHERE MONTH(created_at) = MONTH(CURDATE())
             AND YEAR(created_at) = YEAR(CURDATE())"
        );

        $newUsersLastMonth = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM users
             WHERE MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
             AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))"
        );

        $userGrowth = $newUsersLastMonth > 0
            ? round((($newUsersThisMonth - $newUsersLastMonth) / $newUsersLastMonth) * 100, 1)
            : ($newUsersThisMonth > 0 ? 100 : 0);

        return [
            'total_users' => (int) $totalUsers,
            'active_users' => (int) $activeUsers,
            'total_channels' => (int) $totalChannels,
            'total_vod' => (int) $totalVod,
            'active_subscriptions' => (int) $activeSubscriptions,
            'active_streams' => (int) $activeStreams,
            'today_views' => (int) $todayViews,
            'monthly_revenue' => (float) $monthlyRevenue,
            'user_growth' => $userGrowth,
            'new_users_month' => (int) $newUsersThisMonth,
        ];
    }

    /**
     * Get recent admin activity
     */
    private function getRecentActivity(): array
    {
        return $this->db->fetchAll(
            "SELECT al.*, au.username, au.first_name, au.last_name
             FROM admin_activity_log al
             LEFT JOIN admin_users au ON al.admin_user_id = au.id
             WHERE al.action != 'login_failed'
             ORDER BY al.created_at DESC
             LIMIT 10"
        );
    }

    /**
     * Get active streams
     */
    private function getActiveStreams(): array
    {
        return $this->db->fetchAll(
            "SELECT ss.*, u.email, u.first_name, u.last_name,
                    CASE
                        WHEN ss.content_type = 'channel' THEN (SELECT name FROM channels WHERE id = ss.content_id)
                        WHEN ss.content_type = 'vod' THEN (SELECT title FROM vod_assets WHERE id = ss.content_id)
                        ELSE 'Unknown'
                    END as content_name
             FROM stream_sessions ss
             INNER JOIN users u ON ss.user_id = u.id
             WHERE ss.ended_at IS NULL
             AND ss.last_heartbeat > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
             ORDER BY ss.started_at DESC
             LIMIT 10"
        );
    }

    /**
     * Get popular content (last 7 days)
     */
    private function getPopularContent(): array
    {
        return $this->db->fetchAll(
            "SELECT
                ae.content_type,
                ae.content_id,
                COUNT(*) as view_count,
                CASE
                    WHEN ae.content_type = 'channel' THEN (SELECT name FROM channels WHERE id = ae.content_id)
                    WHEN ae.content_type = 'vod' THEN (SELECT title FROM vod_assets WHERE id = ae.content_id)
                    ELSE 'Unknown'
                END as content_name
             FROM analytics_events ae
             WHERE ae.event_type = 'play'
             AND ae.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY ae.content_type, ae.content_id
             ORDER BY view_count DESC
             LIMIT 10"
        );
    }

    /**
     * Get recent users
     */
    private function getRecentUsers(): array
    {
        return $this->db->fetchAll(
            "SELECT u.*, s.status as subscription_status, p.name as package_name
             FROM users u
             LEFT JOIN subscriptions s ON u.id = s.user_id AND s.status = 'active'
             LEFT JOIN packages p ON s.package_id = p.id
             ORDER BY u.created_at DESC
             LIMIT 5"
        );
    }

    /**
     * Get chart data for the last 7 days
     */
    private function getChartData(): array
    {
        $days = [];
        $views = [];
        $newUsers = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $days[] = date('M j', strtotime($date));

            // Views per day
            $dayViews = $this->db->fetchColumn(
                "SELECT COUNT(*) FROM analytics_events
                 WHERE event_type = 'play' AND DATE(created_at) = ?",
                [$date]
            );
            $views[] = (int) $dayViews;

            // New users per day
            $dayUsers = $this->db->fetchColumn(
                "SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?",
                [$date]
            );
            $newUsers[] = (int) $dayUsers;
        }

        return [
            'labels' => $days,
            'views' => $views,
            'new_users' => $newUsers,
        ];
    }
}
