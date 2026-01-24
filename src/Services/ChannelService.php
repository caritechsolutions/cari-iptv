<?php
/**
 * CARI-IPTV Channel Service
 * Business logic for channel management
 */

namespace CariIPTV\Services;

use CariIPTV\Core\Database;

class ChannelService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get all channels with filters and pagination
     */
    public function getChannels(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        // Search filter
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = '(c.name LIKE ? OR c.key_code LIKE ? OR c.external_id LIKE ?)';
            $params = array_merge($params, [$search, $search, $search]);
        }

        // Status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $where[] = 'c.is_active = ?';
            $params[] = (int) $filters['status'];
        }

        // Published filter
        if (isset($filters['published']) && $filters['published'] !== '') {
            $where[] = 'c.is_published = ?';
            $params[] = (int) $filters['published'];
        }

        // Category filter
        if (!empty($filters['category_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM channel_categories cc WHERE cc.channel_id = c.id AND cc.category_id = ?)';
            $params[] = (int) $filters['category_id'];
        }

        // Package filter
        if (!empty($filters['package_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM package_channels pc WHERE pc.channel_id = c.id AND pc.package_id = ?)';
            $params[] = (int) $filters['package_id'];
        }

        // Server filter
        if (!empty($filters['server_id'])) {
            $where[] = 'c.streaming_server_id = ?';
            $params[] = (int) $filters['server_id'];
        }

        $whereClause = implode(' AND ', $where);

        // Sorting
        $sortColumn = $filters['sort'] ?? 'sort_order';
        $sortDir = strtoupper($filters['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        $allowedSorts = ['name', 'key_code', 'channel_number', 'sort_order', 'created_at', 'is_active', 'is_published'];
        if (!in_array($sortColumn, $allowedSorts)) {
            $sortColumn = 'sort_order';
        }

        // Count total
        $totalSql = "SELECT COUNT(*) FROM channels c WHERE {$whereClause}";
        $total = (int) $this->db->fetch($totalSql, $params)['COUNT(*)'];

        // Pagination
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        // Fetch channels with related data
        $sql = "
            SELECT
                c.*,
                ss.name as server_name,
                ss.type as server_type,
                co.name as content_owner_name,
                GROUP_CONCAT(DISTINCT cat.name ORDER BY cc.is_primary DESC, cat.name SEPARATOR ', ') as category_names,
                GROUP_CONCAT(DISTINCT pkg.name ORDER BY pkg.name SEPARATOR ', ') as package_names
            FROM channels c
            LEFT JOIN streaming_servers ss ON c.streaming_server_id = ss.id
            LEFT JOIN content_owners co ON c.content_owner_id = co.id
            LEFT JOIN channel_categories cc ON c.id = cc.channel_id
            LEFT JOIN categories cat ON cc.category_id = cat.id
            LEFT JOIN package_channels pc ON c.id = pc.channel_id
            LEFT JOIN packages pkg ON pc.package_id = pkg.id
            WHERE {$whereClause}
            GROUP BY c.id
            ORDER BY c.{$sortColumn} {$sortDir}
            LIMIT {$perPage} OFFSET {$offset}
        ";

        $channels = $this->db->fetchAll($sql, $params);

        return [
            'data' => $channels,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }

    /**
     * Get a single channel by ID
     */
    public function getChannel(int $id): ?array
    {
        $channel = $this->db->fetch(
            "SELECT c.*, ss.name as server_name, co.name as content_owner_name
             FROM channels c
             LEFT JOIN streaming_servers ss ON c.streaming_server_id = ss.id
             LEFT JOIN content_owners co ON c.content_owner_id = co.id
             WHERE c.id = ?",
            [$id]
        );

        if ($channel) {
            // Get channel categories
            $channel['categories'] = $this->db->fetchAll(
                "SELECT cc.category_id, cc.is_primary, cat.name
                 FROM channel_categories cc
                 JOIN categories cat ON cc.category_id = cat.id
                 WHERE cc.channel_id = ?
                 ORDER BY cc.is_primary DESC, cat.name",
                [$id]
            );

            // Get channel packages
            $channel['packages'] = $this->db->fetchAll(
                "SELECT pc.package_id, pkg.name
                 FROM package_channels pc
                 JOIN packages pkg ON pc.package_id = pkg.id
                 WHERE pc.channel_id = ?
                 ORDER BY pkg.name",
                [$id]
            );

            // Decode JSON fields
            if ($channel['os_platforms']) {
                $channel['os_platforms'] = json_decode($channel['os_platforms'], true);
            }
        }

        return $channel;
    }

    /**
     * Create a new channel
     */
    public function createChannel(array $data): int
    {
        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['name']);
        }

        // Generate key_code if not provided
        if (empty($data['key_code'])) {
            $data['key_code'] = $this->generateKeyCode();
        }

        // Encode JSON fields
        if (isset($data['os_platforms']) && is_array($data['os_platforms'])) {
            $data['os_platforms'] = json_encode($data['os_platforms']);
        }

        // Extract categories and packages
        $categories = $data['categories'] ?? [];
        $packages = $data['packages'] ?? [];
        $primaryCategory = $data['primary_category'] ?? null;

        unset($data['categories'], $data['packages'], $data['primary_category']);

        // Set legacy category_id from primary category
        if ($primaryCategory) {
            $data['category_id'] = $primaryCategory;
        } elseif (!empty($categories)) {
            $data['category_id'] = $categories[0];
        }

        // Insert channel
        $channelId = $this->db->insert('channels', $data);

        // Save categories
        $this->saveChannelCategories($channelId, $categories, $primaryCategory);

        // Save packages
        $this->saveChannelPackages($channelId, $packages);

        return $channelId;
    }

    /**
     * Update an existing channel
     */
    public function updateChannel(int $id, array $data): bool
    {
        // Generate slug if name changed and slug not provided
        if (!empty($data['name']) && empty($data['slug'])) {
            $existing = $this->db->fetch("SELECT slug, name FROM channels WHERE id = ?", [$id]);
            if ($existing && $existing['name'] !== $data['name']) {
                $data['slug'] = $this->generateSlug($data['name'], $id);
            }
        }

        // Encode JSON fields
        if (isset($data['os_platforms']) && is_array($data['os_platforms'])) {
            $data['os_platforms'] = json_encode($data['os_platforms']);
        }

        // Extract categories and packages
        $categories = $data['categories'] ?? null;
        $packages = $data['packages'] ?? null;
        $primaryCategory = $data['primary_category'] ?? null;

        unset($data['categories'], $data['packages'], $data['primary_category']);

        // Set legacy category_id from primary category
        if ($primaryCategory) {
            $data['category_id'] = $primaryCategory;
        } elseif ($categories !== null && !empty($categories)) {
            $data['category_id'] = $categories[0];
        }

        // Update channel
        $this->db->update('channels', $data, 'id = ?', [$id]);

        // Update categories if provided
        if ($categories !== null) {
            $this->saveChannelCategories($id, $categories, $primaryCategory);
        }

        // Update packages if provided
        if ($packages !== null) {
            $this->saveChannelPackages($id, $packages);
        }

        return true;
    }

    /**
     * Delete a channel
     */
    public function deleteChannel(int $id): bool
    {
        // Delete related records first (cascade should handle this, but be explicit)
        $this->db->delete('channel_categories', 'channel_id = ?', [$id]);
        $this->db->delete('package_channels', 'channel_id = ?', [$id]);

        return $this->db->delete('channels', 'id = ?', [$id]) > 0;
    }

    /**
     * Toggle channel status
     */
    public function toggleStatus(int $id): bool
    {
        $channel = $this->db->fetch("SELECT is_active FROM channels WHERE id = ?", [$id]);
        if (!$channel) {
            return false;
        }

        $newStatus = $channel['is_active'] ? 0 : 1;
        $this->db->update('channels', ['is_active' => $newStatus], 'id = ?', [$id]);

        return true;
    }

    /**
     * Toggle channel published status
     */
    public function togglePublished(int $id): bool
    {
        $channel = $this->db->fetch("SELECT is_published FROM channels WHERE id = ?", [$id]);
        if (!$channel) {
            return false;
        }

        $newStatus = $channel['is_published'] ? 0 : 1;
        $this->db->update('channels', ['is_published' => $newStatus], 'id = ?', [$id]);

        return true;
    }

    /**
     * Bulk update channel status
     */
    public function bulkUpdateStatus(array $ids, bool $active): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE channels SET is_active = ? WHERE id IN ({$placeholders})";

        return $this->db->execute($sql, array_merge([$active ? 1 : 0], $ids));
    }

    /**
     * Get all categories for channels (type = 'live')
     */
    public function getCategories(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, slug, parent_id, is_active
             FROM categories
             WHERE type = 'live' AND is_active = 1
             ORDER BY sort_order, name"
        );
    }

    /**
     * Get all packages
     */
    public function getPackages(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, slug, is_active
             FROM packages
             WHERE is_active = 1
             ORDER BY sort_order, name"
        );
    }

    /**
     * Get all streaming servers
     */
    public function getStreamingServers(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, url, type, is_active
             FROM streaming_servers
             ORDER BY name"
        );
    }

    /**
     * Get all content owners
     */
    public function getContentOwners(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, slug, is_active
             FROM content_owners
             ORDER BY name"
        );
    }

    /**
     * Save channel categories
     */
    private function saveChannelCategories(int $channelId, array $categoryIds, ?int $primaryCategoryId = null): void
    {
        // Delete existing
        $this->db->delete('channel_categories', 'channel_id = ?', [$channelId]);

        // Insert new
        $sortOrder = 0;
        foreach ($categoryIds as $catId) {
            $this->db->insert('channel_categories', [
                'channel_id' => $channelId,
                'category_id' => (int) $catId,
                'is_primary' => ($catId == $primaryCategoryId) ? 1 : 0,
                'sort_order' => $sortOrder++,
            ]);
        }
    }

    /**
     * Save channel packages
     */
    private function saveChannelPackages(int $channelId, array $packageIds): void
    {
        // Delete existing
        $this->db->delete('package_channels', 'channel_id = ?', [$channelId]);

        // Insert new
        foreach ($packageIds as $pkgId) {
            $this->db->insert('package_channels', [
                'channel_id' => $channelId,
                'package_id' => (int) $pkgId,
            ]);
        }
    }

    /**
     * Generate unique slug
     */
    private function generateSlug(string $name, ?int $excludeId = null): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        $baseSlug = $slug;
        $counter = 1;

        while (true) {
            $sql = "SELECT id FROM channels WHERE slug = ?";
            $params = [$slug];

            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }

            $existing = $this->db->fetch($sql, $params);
            if (!$existing) {
                break;
            }

            $slug = $baseSlug . '-' . $counter++;
        }

        return $slug;
    }

    /**
     * Generate unique key code
     */
    private function generateKeyCode(): string
    {
        // Get the max numeric key_code
        $result = $this->db->fetch(
            "SELECT MAX(CAST(key_code AS UNSIGNED)) as max_code FROM channels WHERE key_code REGEXP '^[0-9]+$'"
        );

        $maxCode = $result['max_code'] ?? 0;
        return str_pad($maxCode + 1, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Handle logo upload
     */
    public function uploadLogo(array $file, string $type = 'logo'): ?string
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($file['type'], $allowedTypes)) {
            return null;
        }

        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            return null;
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $type . '_' . uniqid() . '_' . time() . '.' . $extension;

        $uploadDir = BASE_PATH . '/public/uploads/channels/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $destination = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return '/uploads/channels/' . $filename;
        }

        return null;
    }

    /**
     * Delete logo file
     */
    public function deleteLogo(string $logoUrl): bool
    {
        if (empty($logoUrl) || strpos($logoUrl, '/uploads/channels/') === false) {
            return false;
        }

        $filePath = BASE_PATH . '/public' . $logoUrl;
        if (file_exists($filePath)) {
            return unlink($filePath);
        }

        return false;
    }

    /**
     * Get channel statistics
     */
    public function getStatistics(): array
    {
        $stats = [];

        // Total channels
        $stats['total'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM channels"
        )['count'];

        // Active channels
        $stats['active'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM channels WHERE is_active = 1"
        )['count'];

        // Published channels
        $stats['published'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM channels WHERE is_published = 1"
        )['count'];

        // HD channels
        $stats['hd'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM channels WHERE is_hd = 1"
        )['count'];

        // 4K channels
        $stats['4k'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM channels WHERE is_4k = 1"
        )['count'];

        // With catchup
        $stats['catchup'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM channels WHERE catchup_days > 0"
        )['count'];

        return $stats;
    }
}
