<?php

namespace CariIPTV\Services;

use CariIPTV\Core\Database;

class SubscriberService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ========== SUBSCRIBER CRUD ==========

    /**
     * Get paginated list of subscribers with filters
     */
    public function getSubscribers(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        // Search filter
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR s.username LIKE ? OR s.email LIKE ? OR s.phone LIKE ?)';
            $params = array_merge($params, [$search, $search, $search, $search, $search]);
        }

        // Status filter
        if (!empty($filters['status'])) {
            $where[] = 's.status = ?';
            $params[] = $filters['status'];
        }

        // Group filter
        if (!empty($filters['group_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM subscriber_group_members sgm WHERE sgm.subscriber_id = s.id AND sgm.group_id = ?)';
            $params[] = (int) $filters['group_id'];
        }

        $whereClause = implode(' AND ', $where);

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM subscribers s WHERE {$whereClause}";
        $total = (int) $this->db->fetch($countSql, $params)['total'];

        // Pagination
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        // Fetch subscribers
        $sql = "SELECT s.*,
                    GROUP_CONCAT(sg.name ORDER BY sg.name SEPARATOR ', ') as group_names,
                    GROUP_CONCAT(sg.id ORDER BY sg.name SEPARATOR ',') as group_ids
                FROM subscribers s
                LEFT JOIN subscriber_group_members sgm ON s.id = sgm.subscriber_id
                LEFT JOIN subscriber_groups sg ON sgm.group_id = sg.id
                WHERE {$whereClause}
                GROUP BY s.id
                ORDER BY s.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $subscribers = $this->db->fetchAll($sql, $params);

        return [
            'data' => $subscribers,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $total > 0 ? ceil($total / $perPage) : 1,
        ];
    }

    /**
     * Get single subscriber by ID
     */
    public function getSubscriber(int $id): ?array
    {
        $subscriber = $this->db->fetch("SELECT * FROM subscribers WHERE id = ?", [$id]);

        if ($subscriber) {
            // Fetch group memberships
            $subscriber['groups'] = $this->db->fetchAll(
                "SELECT sg.id, sg.name FROM subscriber_groups sg
                 INNER JOIN subscriber_group_members sgm ON sg.id = sgm.group_id
                 WHERE sgm.subscriber_id = ?
                 ORDER BY sg.name",
                [$id]
            );
            $subscriber['group_ids'] = array_column($subscriber['groups'], 'id');
        }

        return $subscriber;
    }

    /**
     * Create a new subscriber
     */
    public function createSubscriber(array $data): int
    {
        // Extract groups before insert
        $groupIds = $data['groups'] ?? [];
        unset($data['groups']);

        // Hash password if provided
        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        } else {
            unset($data['password']);
        }

        // Clean empty values
        foreach (['birthday'] as $field) {
            if (empty($data[$field])) {
                $data[$field] = null;
            }
        }

        $this->db->execute(
            "INSERT INTO subscribers (username, email, password, first_name, last_name, phone,
                status, is_disabled, email_verified, phone_verified, max_connections, npvr_limit,
                birthday, parental_pin, country, city, address, zip_code, external_id, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['username'] ?: null,
                $data['email'] ?: null,
                $data['password'] ?? null,
                $data['first_name'] ?? null,
                $data['last_name'] ?? null,
                $data['phone'] ?? null,
                $data['status'] ?? 'active',
                (int) ($data['is_disabled'] ?? 0),
                (int) ($data['email_verified'] ?? 0),
                (int) ($data['phone_verified'] ?? 0),
                (int) ($data['max_connections'] ?? 1),
                (int) ($data['npvr_limit'] ?? 0),
                $data['birthday'] ?? null,
                $data['parental_pin'] ?? '0000',
                $data['country'] ?? null,
                $data['city'] ?? null,
                $data['address'] ?? null,
                $data['zip_code'] ?? null,
                $data['external_id'] ?? null,
                $data['notes'] ?? null,
            ]
        );

        $subscriberId = $this->db->lastInsertId();

        // Save group memberships
        $this->syncGroups($subscriberId, $groupIds);

        return $subscriberId;
    }

    /**
     * Update an existing subscriber
     */
    public function updateSubscriber(int $id, array $data): bool
    {
        // Extract groups
        $groupIds = $data['groups'] ?? [];
        unset($data['groups']);

        // Handle password
        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        } else {
            unset($data['password']);
        }

        // Clean empty values
        foreach (['birthday'] as $field) {
            if (empty($data[$field])) {
                $data[$field] = null;
            }
        }

        // Build SET clause dynamically
        $fields = ['username', 'email', 'first_name', 'last_name', 'phone',
            'status', 'is_disabled', 'email_verified', 'phone_verified',
            'max_connections', 'npvr_limit', 'birthday', 'parental_pin',
            'country', 'city', 'address', 'zip_code', 'external_id', 'notes'];

        $sets = [];
        $params = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "`{$field}` = ?";
                if (in_array($field, ['is_disabled', 'email_verified', 'phone_verified', 'max_connections', 'npvr_limit'])) {
                    $params[] = (int) $data[$field];
                } else {
                    $params[] = $data[$field] ?: null;
                }
            }
        }

        // Include password if set
        if (isset($data['password'])) {
            $sets[] = '`password` = ?';
            $params[] = $data['password'];
        }

        if (!empty($sets)) {
            $params[] = $id;
            $this->db->execute(
                "UPDATE subscribers SET " . implode(', ', $sets) . " WHERE id = ?",
                $params
            );
        }

        // Sync group memberships
        $this->syncGroups($id, $groupIds);

        return true;
    }

    /**
     * Delete a subscriber
     */
    public function deleteSubscriber(int $id): bool
    {
        // Group members cascade-delete via FK
        $this->db->execute("DELETE FROM subscribers WHERE id = ?", [$id]);
        $this->updateAllGroupCounts();
        return true;
    }

    /**
     * Toggle subscriber status (active/inactive)
     */
    public function toggleStatus(int $id): ?string
    {
        $subscriber = $this->db->fetch("SELECT status FROM subscribers WHERE id = ?", [$id]);
        if (!$subscriber) {
            return null;
        }

        $newStatus = $subscriber['status'] === 'active' ? 'inactive' : 'active';
        $this->db->execute("UPDATE subscribers SET status = ? WHERE id = ?", [$newStatus, $id]);

        return $newStatus;
    }

    /**
     * Bulk action on subscribers
     */
    public function bulkAction(string $action, array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        switch ($action) {
            case 'activate':
                $this->db->execute(
                    "UPDATE subscribers SET status = 'active' WHERE id IN ({$placeholders})",
                    $ids
                );
                return count($ids);

            case 'deactivate':
                $this->db->execute(
                    "UPDATE subscribers SET status = 'inactive' WHERE id IN ({$placeholders})",
                    $ids
                );
                return count($ids);

            case 'suspend':
                $this->db->execute(
                    "UPDATE subscribers SET status = 'suspended' WHERE id IN ({$placeholders})",
                    $ids
                );
                return count($ids);

            case 'delete':
                $this->db->execute(
                    "DELETE FROM subscribers WHERE id IN ({$placeholders})",
                    $ids
                );
                $this->updateAllGroupCounts();
                return count($ids);

            default:
                return 0;
        }
    }

    // ========== GROUP MANAGEMENT ==========

    /**
     * Get all groups
     */
    public function getGroups(): array
    {
        return $this->db->fetchAll(
            "SELECT sg.*, COUNT(sgm.subscriber_id) as member_count
             FROM subscriber_groups sg
             LEFT JOIN subscriber_group_members sgm ON sg.id = sgm.group_id
             GROUP BY sg.id
             ORDER BY sg.name ASC"
        );
    }

    /**
     * Get a single group
     */
    public function getGroup(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM subscriber_groups WHERE id = ?", [$id]);
    }

    /**
     * Create a new group
     */
    public function createGroup(array $data): int
    {
        $this->db->execute(
            "INSERT INTO subscriber_groups (name, description, color, icon) VALUES (?, ?, ?, ?)",
            [
                $data['name'],
                $data['description'] ?? null,
                $data['color'] ?? '#6366f1',
                $data['icon'] ?? 'lucide-users',
            ]
        );

        return $this->db->lastInsertId();
    }

    /**
     * Update a group
     */
    public function updateGroup(int $id, array $data): bool
    {
        $this->db->execute(
            "UPDATE subscriber_groups SET name = ?, description = ?, color = ?, icon = ? WHERE id = ?",
            [
                $data['name'],
                $data['description'] ?? null,
                $data['color'] ?? '#6366f1',
                $data['icon'] ?? 'lucide-users',
                $id,
            ]
        );

        return true;
    }

    /**
     * Delete a group
     */
    public function deleteGroup(int $id): bool
    {
        // Members cascade-delete via FK
        $this->db->execute("DELETE FROM subscriber_groups WHERE id = ?", [$id]);
        return true;
    }

    // ========== GROUP MEMBERSHIP ==========

    /**
     * Sync a subscriber's group memberships
     */
    private function syncGroups(int $subscriberId, array $groupIds): void
    {
        // Remove existing memberships
        $this->db->execute(
            "DELETE FROM subscriber_group_members WHERE subscriber_id = ?",
            [$subscriberId]
        );

        // Add new memberships
        foreach ($groupIds as $groupId) {
            $groupId = (int) $groupId;
            if ($groupId > 0) {
                $this->db->execute(
                    "INSERT IGNORE INTO subscriber_group_members (subscriber_id, group_id) VALUES (?, ?)",
                    [$subscriberId, $groupId]
                );
            }
        }

        $this->updateAllGroupCounts();
    }

    /**
     * Recalculate subscriber_count for all groups
     */
    private function updateAllGroupCounts(): void
    {
        $this->db->execute(
            "UPDATE subscriber_groups sg
             SET sg.subscriber_count = (
                SELECT COUNT(*) FROM subscriber_group_members sgm WHERE sgm.group_id = sg.id
             )"
        );
    }

    // ========== STATISTICS ==========

    /**
     * Get subscriber statistics for the listing page
     */
    public function getStatistics(): array
    {
        $stats = $this->db->fetch(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired
             FROM subscribers"
        );

        return [
            'total' => (int) ($stats['total'] ?? 0),
            'active' => (int) ($stats['active'] ?? 0),
            'inactive' => (int) ($stats['inactive'] ?? 0),
            'suspended' => (int) ($stats['suspended'] ?? 0),
            'expired' => (int) ($stats['expired'] ?? 0),
        ];
    }
}
