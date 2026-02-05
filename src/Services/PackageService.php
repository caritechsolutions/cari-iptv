<?php

namespace CariIPTV\Services;

use CariIPTV\Core\Database;

class PackageService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ==========================================
    // CONTENT GROUPS
    // ==========================================

    public function getContentGroups(): array
    {
        return $this->db->fetchAll(
            "SELECT cg.*, cg.content_count as item_count
             FROM content_groups cg
             ORDER BY cg.sort_order ASC, cg.name ASC"
        );
    }

    public function getContentGroup(int $id): ?array
    {
        $group = $this->db->fetch("SELECT * FROM content_groups WHERE id = ?", [$id]);
        if ($group) {
            $group['items'] = $this->getContentGroupItems($id);
        }
        return $group;
    }

    public function getContentGroupItems(int $groupId): array
    {
        $items = $this->db->fetchAll(
            "SELECT cgi.* FROM content_group_items cgi
             WHERE cgi.group_id = ?
             ORDER BY cgi.content_type, cgi.sort_order",
            [$groupId]
        );

        // Resolve content names
        foreach ($items as &$item) {
            $item['name'] = $this->resolveContentName($item['content_type'], $item['content_id']);
        }

        return $items;
    }

    private function resolveContentName(string $type, int $id): string
    {
        switch ($type) {
            case 'channel':
                $row = $this->db->fetch("SELECT name FROM channels WHERE id = ?", [$id]);
                return $row['name'] ?? 'Unknown Channel';
            case 'movie':
                $row = $this->db->fetch("SELECT title FROM movies WHERE id = ?", [$id]);
                return $row['title'] ?? 'Unknown Movie';
            case 'series':
                $row = $this->db->fetch("SELECT title FROM series WHERE id = ?", [$id]);
                return $row['title'] ?? 'Unknown Series';
            case 'category':
                $row = $this->db->fetch("SELECT name FROM categories WHERE id = ?", [$id]);
                return $row['name'] ?? 'Unknown Category';
            default:
                return 'Unknown';
        }
    }

    public function createContentGroup(array $data): int
    {
        $slug = $this->generateSlug($data['name'], 'content_groups');

        $this->db->execute(
            "INSERT INTO content_groups (name, slug, description, icon, color, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $data['name'],
                $slug,
                $data['description'] ?? null,
                $data['icon'] ?? 'lucide-layers',
                $data['color'] ?? '#6366f1',
                (int) ($data['sort_order'] ?? 0),
            ]
        );

        return $this->db->lastInsertId();
    }

    public function updateContentGroup(int $id, array $data): bool
    {
        $this->db->execute(
            "UPDATE content_groups SET name = ?, description = ?, icon = ?, color = ?, sort_order = ? WHERE id = ?",
            [
                $data['name'],
                $data['description'] ?? null,
                $data['icon'] ?? 'lucide-layers',
                $data['color'] ?? '#6366f1',
                (int) ($data['sort_order'] ?? 0),
                $id,
            ]
        );
        return true;
    }

    public function deleteContentGroup(int $id): bool
    {
        $this->db->execute("DELETE FROM content_groups WHERE id = ?", [$id]);
        return true;
    }

    public function addContentToGroup(int $groupId, string $contentType, int $contentId): bool
    {
        // Check if already exists
        $existing = $this->db->fetch(
            "SELECT id FROM content_group_items WHERE group_id = ? AND content_type = ? AND content_id = ?",
            [$groupId, $contentType, $contentId]
        );
        if ($existing) {
            return false;
        }

        $this->db->execute(
            "INSERT INTO content_group_items (group_id, content_type, content_id) VALUES (?, ?, ?)",
            [$groupId, $contentType, $contentId]
        );
        $this->updateGroupContentCount($groupId);
        return true;
    }

    public function removeContentFromGroup(int $groupId, int $itemId): bool
    {
        $this->db->execute(
            "DELETE FROM content_group_items WHERE id = ? AND group_id = ?",
            [$itemId, $groupId]
        );
        $this->updateGroupContentCount($groupId);
        return true;
    }

    private function updateGroupContentCount(int $groupId): void
    {
        $this->db->execute(
            "UPDATE content_groups SET content_count = (SELECT COUNT(*) FROM content_group_items WHERE group_id = ?) WHERE id = ?",
            [$groupId, $groupId]
        );
    }

    /**
     * Search content for adding to groups
     */
    public function searchContent(string $type, string $query): array
    {
        $search = '%' . $query . '%';
        switch ($type) {
            case 'channel':
                return $this->db->fetchAll(
                    "SELECT id, name, logo_url as image FROM channels WHERE name LIKE ? ORDER BY name LIMIT 20",
                    [$search]
                );
            case 'movie':
                return $this->db->fetchAll(
                    "SELECT id, title as name, poster_url as image FROM movies WHERE title LIKE ? ORDER BY title LIMIT 20",
                    [$search]
                );
            case 'series':
                return $this->db->fetchAll(
                    "SELECT id, title as name, poster_url as image FROM series WHERE title LIKE ? ORDER BY title LIMIT 20",
                    [$search]
                );
            case 'category':
                return $this->db->fetchAll(
                    "SELECT id, name, NULL as image FROM categories WHERE name LIKE ? ORDER BY name LIMIT 20",
                    [$search]
                );
            default:
                return [];
        }
    }

    // ==========================================
    // PACKAGES
    // ==========================================

    public function getPackages(): array
    {
        $packages = $this->db->fetchAll(
            "SELECT p.*,
                    GROUP_CONCAT(DISTINCT cg.name ORDER BY cg.name SEPARATOR ', ') as group_names
             FROM packages p
             LEFT JOIN package_content_groups pcg ON p.id = pcg.package_id
             LEFT JOIN content_groups cg ON pcg.group_id = cg.id
             GROUP BY p.id
             ORDER BY p.sort_order ASC, p.name ASC"
        );

        foreach ($packages as &$pkg) {
            $pkg['features'] = $pkg['features'] ? json_decode($pkg['features'], true) : [];
            $pkg['geo_countries'] = $pkg['geo_countries'] ? json_decode($pkg['geo_countries'], true) : [];
            $pkg['platforms'] = $pkg['platforms'] ? json_decode($pkg['platforms'], true) : [];
        }

        return $packages;
    }

    public function getPackage(int $id): ?array
    {
        $pkg = $this->db->fetch("SELECT * FROM packages WHERE id = ?", [$id]);
        if (!$pkg) {
            return null;
        }

        $pkg['features'] = $pkg['features'] ? json_decode($pkg['features'], true) : [];
        $pkg['geo_countries'] = $pkg['geo_countries'] ? json_decode($pkg['geo_countries'], true) : [];
        $pkg['platforms'] = $pkg['platforms'] ? json_decode($pkg['platforms'], true) : [];

        // Get assigned content groups
        $pkg['content_groups'] = $this->db->fetchAll(
            "SELECT cg.id, cg.name FROM content_groups cg
             INNER JOIN package_content_groups pcg ON cg.id = pcg.group_id
             WHERE pcg.package_id = ?
             ORDER BY cg.name",
            [$id]
        );
        $pkg['content_group_ids'] = array_column($pkg['content_groups'], 'id');

        return $pkg;
    }

    public function createPackage(array $data): int
    {
        $slug = $this->generateSlug($data['name'], 'packages');
        $groupIds = $data['content_group_ids'] ?? [];

        $this->db->execute(
            "INSERT INTO packages (name, slug, description, icon, color, price, currency, billing_period,
                tax_rate, tax_inclusive, trial_days, max_connections, geo_countries, platforms,
                features, is_free, is_active, is_featured, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['name'],
                $slug,
                $data['description'] ?? null,
                $data['icon'] ?? 'lucide-package',
                $data['color'] ?? '#6366f1',
                (float) ($data['price'] ?? 0),
                $data['currency'] ?? 'USD',
                $data['billing_period'] ?? 'monthly',
                (float) ($data['tax_rate'] ?? 0),
                (int) ($data['tax_inclusive'] ?? 0),
                (int) ($data['trial_days'] ?? 0),
                (int) ($data['max_connections'] ?? 1),
                !empty($data['geo_countries']) ? json_encode($data['geo_countries']) : null,
                !empty($data['platforms']) ? json_encode($data['platforms']) : null,
                !empty($data['features']) ? json_encode($data['features']) : null,
                (int) ($data['is_free'] ?? 0),
                (int) ($data['is_active'] ?? 1),
                (int) ($data['is_featured'] ?? 0),
                (int) ($data['sort_order'] ?? 0),
            ]
        );

        $packageId = $this->db->lastInsertId();
        $this->syncPackageGroups($packageId, $groupIds);

        return $packageId;
    }

    public function updatePackage(int $id, array $data): bool
    {
        $groupIds = $data['content_group_ids'] ?? [];

        $this->db->execute(
            "UPDATE packages SET name = ?, description = ?, icon = ?, color = ?,
                price = ?, currency = ?, billing_period = ?,
                tax_rate = ?, tax_inclusive = ?, trial_days = ?, max_connections = ?,
                geo_countries = ?, platforms = ?, features = ?,
                is_free = ?, is_active = ?, is_featured = ?, sort_order = ?
             WHERE id = ?",
            [
                $data['name'],
                $data['description'] ?? null,
                $data['icon'] ?? 'lucide-package',
                $data['color'] ?? '#6366f1',
                (float) ($data['price'] ?? 0),
                $data['currency'] ?? 'USD',
                $data['billing_period'] ?? 'monthly',
                (float) ($data['tax_rate'] ?? 0),
                (int) ($data['tax_inclusive'] ?? 0),
                (int) ($data['trial_days'] ?? 0),
                (int) ($data['max_connections'] ?? 1),
                !empty($data['geo_countries']) ? json_encode($data['geo_countries']) : null,
                !empty($data['platforms']) ? json_encode($data['platforms']) : null,
                !empty($data['features']) ? json_encode($data['features']) : null,
                (int) ($data['is_free'] ?? 0),
                (int) ($data['is_active'] ?? 1),
                (int) ($data['is_featured'] ?? 0),
                (int) ($data['sort_order'] ?? 0),
                $id,
            ]
        );

        $this->syncPackageGroups($id, $groupIds);

        return true;
    }

    public function deletePackage(int $id): bool
    {
        $this->db->execute("DELETE FROM packages WHERE id = ?", [$id]);
        return true;
    }

    private function syncPackageGroups(int $packageId, array $groupIds): void
    {
        $this->db->execute("DELETE FROM package_content_groups WHERE package_id = ?", [$packageId]);
        foreach ($groupIds as $gid) {
            $gid = (int) $gid;
            if ($gid > 0) {
                $this->db->execute(
                    "INSERT IGNORE INTO package_content_groups (package_id, group_id) VALUES (?, ?)",
                    [$packageId, $gid]
                );
            }
        }
    }

    // ==========================================
    // HELPERS
    // ==========================================

    private function generateSlug(string $name, string $table): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        $base = $slug;
        $counter = 1;
        while ($this->db->fetch("SELECT id FROM `{$table}` WHERE slug = ?", [$slug])) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function getStatistics(): array
    {
        $pkgStats = $this->db->fetch(
            "SELECT COUNT(*) as total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN is_free = 1 THEN 1 ELSE 0 END) as free_count
             FROM packages"
        );
        $groupCount = $this->db->fetch("SELECT COUNT(*) as total FROM content_groups");

        return [
            'packages_total' => (int) ($pkgStats['total'] ?? 0),
            'packages_active' => (int) ($pkgStats['active'] ?? 0),
            'packages_free' => (int) ($pkgStats['free_count'] ?? 0),
            'groups_total' => (int) ($groupCount['total'] ?? 0),
        ];
    }

    /**
     * Available currencies (Caribbean focus)
     */
    public static function getCurrencies(): array
    {
        return [
            'USD' => 'US Dollar ($)',
            'EUR' => 'Euro (€)',
            'GBP' => 'British Pound (£)',
            'XCD' => 'Eastern Caribbean Dollar (EC$)',
            'TTD' => 'Trinidad & Tobago Dollar (TT$)',
            'JMD' => 'Jamaican Dollar (J$)',
            'BBD' => 'Barbadian Dollar (Bds$)',
            'GYD' => 'Guyanese Dollar (G$)',
            'SRD' => 'Surinamese Dollar (SRD)',
            'HTG' => 'Haitian Gourde (G)',
            'BZD' => 'Belize Dollar (BZ$)',
            'BSD' => 'Bahamian Dollar (B$)',
            'KYD' => 'Cayman Islands Dollar (CI$)',
            'BMD' => 'Bermudian Dollar (BD$)',
            'ANG' => 'Netherlands Antillean Guilder (NAƒ)',
            'AWG' => 'Aruban Florin (Afl)',
            'DOP' => 'Dominican Peso (RD$)',
            'CUP' => 'Cuban Peso (₱)',
            'CAD' => 'Canadian Dollar (C$)',
            'MXN' => 'Mexican Peso (MX$)',
            'BRL' => 'Brazilian Real (R$)',
        ];
    }

    public static function getBillingPeriods(): array
    {
        return [
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly (3 months)',
            'semi_annual' => 'Semi-Annual (6 months)',
            'annual' => 'Annual (12 months)',
        ];
    }
}
