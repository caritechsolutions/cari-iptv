<?php
/**
 * CARI-IPTV Category Service
 * Business logic for managing content categories
 */

namespace CariIPTV\Services;

use CariIPTV\Core\Database;

class CategoryService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get all categories with optional filters
     */
    public function getCategories(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['type'])) {
            $where[] = 'c.type = ?';
            $params[] = $filters['type'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(c.name LIKE ? OR c.slug LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[] = 'c.is_active = ?';
            $params[] = (int) $filters['is_active'];
        }

        if (!empty($filters['parent_id'])) {
            $where[] = 'c.parent_id = ?';
            $params[] = (int) $filters['parent_id'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT c.*,
                    p.name as parent_name,
                    (SELECT COUNT(*) FROM channel_categories cc WHERE cc.category_id = c.id) as channel_count,
                    (SELECT COUNT(*) FROM movie_categories mc WHERE mc.category_id = c.id) as movie_count,
                    (SELECT COUNT(*) FROM series_categories sc WHERE sc.category_id = c.id) as series_count
                FROM categories c
                LEFT JOIN categories p ON c.parent_id = p.id
                {$whereClause}
                ORDER BY c.type ASC, c.sort_order ASC, c.name ASC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get a single category by ID
     */
    public function getCategory(int $id): ?array
    {
        return $this->db->fetch(
            "SELECT c.*, p.name as parent_name
             FROM categories c
             LEFT JOIN categories p ON c.parent_id = p.id
             WHERE c.id = ?",
            [$id]
        );
    }

    /**
     * Create a new category
     */
    public function createCategory(array $data): int
    {
        $slug = $this->generateSlug($data['name'], $data['type'] ?? 'vod');

        return $this->db->insert('categories', [
            'name' => $data['name'],
            'slug' => $slug,
            'type' => $data['type'] ?? 'vod',
            'parent_id' => !empty($data['parent_id']) ? (int) $data['parent_id'] : null,
            'icon' => $data['icon'] ?? null,
            'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : $this->getNextSortOrder($data['type'] ?? 'vod'),
        ]);
    }

    /**
     * Update a category
     */
    public function updateCategory(int $id, array $data): bool
    {
        $update = [];

        if (isset($data['name'])) {
            $update['name'] = $data['name'];
            // Regenerate slug if name changes
            $category = $this->getCategory($id);
            if ($category) {
                $update['slug'] = $this->generateSlug($data['name'], $category['type'], $id);
            }
        }

        if (isset($data['type'])) $update['type'] = $data['type'];
        if (isset($data['parent_id'])) $update['parent_id'] = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;
        if (isset($data['icon'])) $update['icon'] = $data['icon'] ?: null;
        if (isset($data['is_active'])) $update['is_active'] = (int) $data['is_active'];
        if (isset($data['sort_order'])) $update['sort_order'] = (int) $data['sort_order'];

        if (empty($update)) {
            return false;
        }

        $this->db->update('categories', $update, 'id = ?', [$id]);
        return true;
    }

    /**
     * Delete a category
     */
    public function deleteCategory(int $id): array
    {
        $category = $this->getCategory($id);
        if (!$category) {
            return ['success' => false, 'message' => 'Category not found'];
        }

        // Check for child categories
        $children = $this->db->fetch(
            "SELECT COUNT(*) as count FROM categories WHERE parent_id = ?",
            [$id]
        );
        if ($children['count'] > 0) {
            return ['success' => false, 'message' => 'Cannot delete category with subcategories. Remove subcategories first.'];
        }

        // Check usage counts
        $channelCount = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM channel_categories WHERE category_id = ?",
            [$id]
        )['count'];

        $movieCount = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM movie_categories WHERE category_id = ?",
            [$id]
        )['count'];

        $seriesCount = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM series_categories WHERE category_id = ?",
            [$id]
        )['count'];

        $totalUsage = $channelCount + $movieCount + $seriesCount;

        if ($totalUsage > 0) {
            return [
                'success' => false,
                'message' => "Cannot delete category assigned to {$totalUsage} item(s). Unassign first."
            ];
        }

        $this->db->delete('categories', 'id = ?', [$id]);
        return ['success' => true, 'message' => 'Category deleted successfully'];
    }

    /**
     * Toggle active status
     */
    public function toggleActive(int $id): bool
    {
        $category = $this->getCategory($id);
        if (!$category) {
            return false;
        }

        $newStatus = $category['is_active'] ? 0 : 1;
        $this->db->update('categories', ['is_active' => $newStatus], 'id = ?', [$id]);
        return true;
    }

    /**
     * Update sort order for multiple categories
     */
    public function updateSortOrder(array $order): void
    {
        foreach ($order as $position => $categoryId) {
            $this->db->update('categories', ['sort_order' => (int) $position], 'id = ?', [(int) $categoryId]);
        }
    }

    /**
     * Get statistics
     */
    public function getStatistics(): array
    {
        $stats = [];

        $stats['total'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM categories"
        )['count'];

        $stats['active'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM categories WHERE is_active = 1"
        )['count'];

        $stats['live'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM categories WHERE type = 'live'"
        )['count'];

        $stats['vod'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM categories WHERE type = 'vod'"
        )['count'];

        $stats['series'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as count FROM categories WHERE type = 'series'"
        )['count'];

        return $stats;
    }

    /**
     * Get parent categories for a given type (for dropdown)
     */
    public function getParentCategories(string $type): array
    {
        return $this->db->fetchAll(
            "SELECT id, name FROM categories WHERE type = ? AND parent_id IS NULL ORDER BY sort_order, name",
            [$type]
        );
    }

    /**
     * Generate a unique slug
     */
    private function generateSlug(string $name, string $type, ?int $excludeId = null): string
    {
        $base = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

        // Add type suffix to avoid cross-type conflicts
        if ($type === 'series') {
            $base .= '-series';
        } elseif ($type === 'live') {
            $base .= '-live';
        }

        $slug = $base;
        $counter = 1;

        while (true) {
            $params = [$slug];
            $excludeClause = '';
            if ($excludeId) {
                $excludeClause = ' AND id != ?';
                $params[] = $excludeId;
            }

            $existing = $this->db->fetch(
                "SELECT id FROM categories WHERE slug = ?{$excludeClause}",
                $params
            );

            if (!$existing) {
                break;
            }

            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get next sort order for a type
     */
    private function getNextSortOrder(string $type): int
    {
        $result = $this->db->fetch(
            "SELECT MAX(sort_order) as max_order FROM categories WHERE type = ?",
            [$type]
        );
        return ($result['max_order'] ?? 0) + 1;
    }
}
