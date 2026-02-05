<?php

namespace CariIPTV\Services;

use CariIPTV\Core\Database;

class DashboardService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * All available widget definitions
     */
    public static function getAvailableWidgets(): array
    {
        return [
            // Content
            'channels'       => ['name' => 'Channels',        'icon' => 'lucide-tv',            'category' => 'Content',     'size' => 'small'],
            'movies'         => ['name' => 'Movies',          'icon' => 'lucide-film',           'category' => 'Content',     'size' => 'small'],
            'series'         => ['name' => 'TV Shows',        'icon' => 'lucide-clapperboard',   'category' => 'Content',     'size' => 'small'],
            'categories'     => ['name' => 'Categories',      'icon' => 'lucide-folder',         'category' => 'Content',     'size' => 'small'],
            'epg_sources'    => ['name' => 'EPG Sources',     'icon' => 'lucide-calendar',       'category' => 'Content',     'size' => 'small'],

            // Subscribers & Packages
            'subscribers'    => ['name' => 'Subscribers',     'icon' => 'lucide-users',          'category' => 'Subscribers', 'size' => 'small'],
            'packages'       => ['name' => 'Packages',        'icon' => 'lucide-package',        'category' => 'Subscribers', 'size' => 'small'],
            'content_groups' => ['name' => 'Content Groups',  'icon' => 'lucide-layers',         'category' => 'Subscribers', 'size' => 'small'],

            // Advertising
            'ad_campaigns'   => ['name' => 'Ad Campaigns',    'icon' => 'lucide-megaphone',      'category' => 'Advertising', 'size' => 'small'],

            // System
            'admin_users'    => ['name' => 'Admin Users',     'icon' => 'lucide-shield',         'category' => 'System',      'size' => 'small'],
            'disk_space'     => ['name' => 'Disk Space',      'icon' => 'lucide-hard-drive',     'category' => 'System',      'size' => 'small'],
            'memory_usage'   => ['name' => 'Memory Usage',    'icon' => 'lucide-cpu',            'category' => 'System',      'size' => 'small'],
            'system_info'    => ['name' => 'System Info',     'icon' => 'lucide-server',         'category' => 'System',      'size' => 'medium'],

            // Full-width
            'content_chart'    => ['name' => 'Content Overview',   'icon' => 'lucide-bar-chart-2',  'category' => 'Charts',  'size' => 'full'],
            'recent_activity'  => ['name' => 'Recent Activity',    'icon' => 'lucide-history',       'category' => 'Activity','size' => 'full'],
        ];
    }

    /**
     * Default widgets for new users
     */
    public static function getDefaultWidgets(): array
    {
        return ['channels', 'movies', 'series', 'subscribers', 'packages', 'disk_space', 'memory_usage', 'content_chart'];
    }

    /**
     * Get widget preferences for a user
     */
    public function getUserWidgets(int $userId): array
    {
        $settings = new SettingsService();
        $stored = $settings->get('dashboard_widgets_' . $userId, null, 'dashboard');

        if ($stored === null) {
            return self::getDefaultWidgets();
        }

        if (is_string($stored)) {
            $decoded = json_decode($stored, true);
            return is_array($decoded) ? $decoded : self::getDefaultWidgets();
        }

        return is_array($stored) ? $stored : self::getDefaultWidgets();
    }

    /**
     * Save widget preferences for a user
     */
    public function saveUserWidgets(int $userId, array $widgetKeys): void
    {
        $settings = new SettingsService();
        $settings->set('dashboard_widgets_' . $userId, json_encode($widgetKeys), 'dashboard');
    }

    /**
     * Get data for a specific widget
     */
    public function getWidgetData(string $key): array
    {
        $method = 'widget_' . $key;
        if (method_exists($this, $method)) {
            try {
                return $this->$method();
            } catch (\Throwable $e) {
                return ['error' => $e->getMessage()];
            }
        }
        return ['error' => 'Unknown widget'];
    }

    /**
     * Get data for multiple widgets
     */
    public function getMultipleWidgetData(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->getWidgetData($key);
        }
        return $result;
    }

    // ==========================================
    // WIDGET DATA METHODS
    // ==========================================

    private function widget_channels(): array
    {
        $row = $this->safeQuery("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active FROM channels");
        return [
            'value' => (int) ($row['total'] ?? 0),
            'sub'   => ((int) ($row['active'] ?? 0)) . ' active',
            'color' => '#6366f1',
        ];
    }

    private function widget_movies(): array
    {
        $row = $this->safeQuery("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published FROM movies");
        return [
            'value' => (int) ($row['total'] ?? 0),
            'sub'   => ((int) ($row['published'] ?? 0)) . ' published',
            'color' => '#ec4899',
        ];
    }

    private function widget_series(): array
    {
        $row = $this->safeQuery("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published FROM series");
        return [
            'value' => (int) ($row['total'] ?? 0),
            'sub'   => ((int) ($row['published'] ?? 0)) . ' published',
            'color' => '#f59e0b',
        ];
    }

    private function widget_categories(): array
    {
        $row = $this->safeQuery("SELECT COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active FROM categories");
        return [
            'value' => (int) ($row['total'] ?? 0),
            'sub'   => ((int) ($row['active'] ?? 0)) . ' active',
            'color' => '#22c55e',
        ];
    }

    private function widget_epg_sources(): array
    {
        $row = $this->safeQuery("SELECT COUNT(*) as total FROM epg_sources");
        return [
            'value' => (int) ($row['total'] ?? 0),
            'sub'   => 'sources configured',
            'color' => '#06b6d4',
        ];
    }

    private function widget_subscribers(): array
    {
        $row = $this->safeQuery("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active FROM subscribers");
        return [
            'value' => (int) ($row['total'] ?? 0),
            'sub'   => ((int) ($row['active'] ?? 0)) . ' active',
            'color' => '#3b82f6',
        ];
    }

    private function widget_packages(): array
    {
        $row = $this->safeQuery("SELECT COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active FROM packages");
        return [
            'value' => (int) ($row['total'] ?? 0),
            'sub'   => ((int) ($row['active'] ?? 0)) . ' active',
            'color' => '#8b5cf6',
        ];
    }

    private function widget_content_groups(): array
    {
        $row = $this->safeQuery("SELECT COUNT(*) as total FROM content_groups");
        return [
            'value' => (int) ($row['total'] ?? 0),
            'sub'   => 'groups defined',
            'color' => '#64748b',
        ];
    }

    private function widget_ad_campaigns(): array
    {
        $row = $this->safeQuery("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active FROM ad_campaigns");
        return [
            'value' => (int) ($row['total'] ?? 0),
            'sub'   => ((int) ($row['active'] ?? 0)) . ' active',
            'color' => '#f59e0b',
        ];
    }

    private function widget_admin_users(): array
    {
        $row = $this->safeQuery("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active FROM admin_users");
        return [
            'value' => (int) ($row['total'] ?? 0),
            'sub'   => ((int) ($row['active'] ?? 0)) . ' active',
            'color' => '#ef4444',
        ];
    }

    private function widget_disk_space(): array
    {
        $path = '/';
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if ($total === false || $free === false) {
            return ['value' => 'N/A', 'sub' => 'Cannot read disk', 'color' => '#64748b'];
        }

        $used = $total - $free;
        $pct = round(($used / $total) * 100, 1);

        return [
            'value'   => $pct . '%',
            'sub'     => $this->formatBytes($used) . ' / ' . $this->formatBytes($total),
            'color'   => $pct > 90 ? '#ef4444' : ($pct > 75 ? '#f59e0b' : '#22c55e'),
            'gauge'   => $pct,
        ];
    }

    private function widget_memory_usage(): array
    {
        $mem = $this->getMemoryInfo();
        if (!$mem) {
            return ['value' => 'N/A', 'sub' => 'Cannot read memory', 'color' => '#64748b'];
        }

        $pct = round(($mem['used'] / $mem['total']) * 100, 1);

        return [
            'value'   => $pct . '%',
            'sub'     => $this->formatBytes($mem['used']) . ' / ' . $this->formatBytes($mem['total']),
            'color'   => $pct > 90 ? '#ef4444' : ($pct > 75 ? '#f59e0b' : '#22c55e'),
            'gauge'   => $pct,
        ];
    }

    private function widget_system_info(): array
    {
        return [
            'type'  => 'info_list',
            'items' => [
                ['label' => 'PHP Version',     'value' => PHP_VERSION],
                ['label' => 'Server OS',       'value' => php_uname('s') . ' ' . php_uname('r')],
                ['label' => 'Server Software', 'value' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI'],
                ['label' => 'Database',        'value' => $this->getMySQLVersion()],
                ['label' => 'CPU Load',        'value' => $this->getCpuLoad()],
                ['label' => 'Uptime',          'value' => $this->getUptime()],
            ],
            'color' => '#3b82f6',
        ];
    }

    private function widget_content_chart(): array
    {
        $channels = (int) ($this->safeQuery("SELECT COUNT(*) as c FROM channels")['c'] ?? 0);
        $movies = (int) ($this->safeQuery("SELECT COUNT(*) as c FROM movies")['c'] ?? 0);
        $series = (int) ($this->safeQuery("SELECT COUNT(*) as c FROM series")['c'] ?? 0);
        $categories = (int) ($this->safeQuery("SELECT COUNT(*) as c FROM categories")['c'] ?? 0);

        return [
            'type' => 'chart',
            'chart_type' => 'bar',
            'labels' => ['Channels', 'Movies', 'TV Shows', 'Categories'],
            'data' => [$channels, $movies, $series, $categories],
            'colors' => ['#6366f1', '#ec4899', '#f59e0b', '#22c55e'],
        ];
    }

    private function widget_recent_activity(): array
    {
        $rows = [];
        try {
            $rows = $this->db->fetchAll(
                "SELECT al.*, au.first_name, au.last_name
                 FROM admin_activity_log al
                 LEFT JOIN admin_users au ON al.admin_id = au.id
                 ORDER BY al.created_at DESC
                 LIMIT 10"
            );
        } catch (\Throwable $e) {
            // Table may not exist
        }

        return [
            'type'  => 'activity_list',
            'items' => $rows,
        ];
    }

    // ==========================================
    // HELPERS
    // ==========================================

    /**
     * Safe query that returns empty array on failure (missing table etc.)
     */
    private function safeQuery(string $sql, array $params = []): array
    {
        try {
            $result = $this->db->fetch($sql, $params);
            return $result ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function formatBytes(float $bytes, int $precision = 1): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    private function getMemoryInfo(): ?array
    {
        if (!is_readable('/proc/meminfo')) {
            return null;
        }

        $meminfo = @file_get_contents('/proc/meminfo');
        if (!$meminfo) {
            return null;
        }

        $total = 0;
        $available = 0;

        if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $m)) {
            $total = (int) $m[1] * 1024;
        }
        if (preg_match('/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $m)) {
            $available = (int) $m[1] * 1024;
        }

        if ($total === 0) {
            return null;
        }

        return [
            'total'     => $total,
            'available' => $available,
            'used'      => $total - $available,
        ];
    }

    private function getMySQLVersion(): string
    {
        try {
            $row = $this->db->fetch("SELECT VERSION() as v");
            return 'MySQL ' . ($row['v'] ?? 'Unknown');
        } catch (\Throwable $e) {
            return 'Unknown';
        }
    }

    private function getCpuLoad(): string
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load ? number_format($load[0], 2) . ' / ' . number_format($load[1], 2) . ' / ' . number_format($load[2], 2) : 'N/A';
        }
        return 'N/A';
    }

    private function getUptime(): string
    {
        if (is_readable('/proc/uptime')) {
            $uptime = @file_get_contents('/proc/uptime');
            if ($uptime) {
                $seconds = (int) explode(' ', $uptime)[0];
                $days = floor($seconds / 86400);
                $hours = floor(($seconds % 86400) / 3600);
                $mins = floor(($seconds % 3600) / 60);
                return ($days > 0 ? $days . 'd ' : '') . $hours . 'h ' . $mins . 'm';
            }
        }
        return 'N/A';
    }
}
