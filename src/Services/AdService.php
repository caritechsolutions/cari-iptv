<?php
/**
 * CARI-IPTV Ad Service
 * Business logic for advertising campaign management and ad serving
 */

namespace CariIPTV\Services;

use CariIPTV\Core\Database;

class AdService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =========================================
    // Campaign CRUD
    // =========================================

    /**
     * Get campaigns with filters and pagination
     */
    public function getCampaigns(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = '(c.name LIKE ? OR c.advertiser LIKE ?)';
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['status'])) {
            $where[] = 'c.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['type'])) {
            $where[] = 'EXISTS (SELECT 1 FROM ad_creatives ac WHERE ac.campaign_id = c.id AND ac.type = ?)';
            $params[] = $filters['type'];
        }

        $whereClause = implode(' AND ', $where);

        // Count
        $total = (int) $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM ad_campaigns c WHERE {$whereClause}",
            $params
        )['cnt'];

        // Pagination
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        $sortColumn = $filters['sort'] ?? 'created_at';
        $sortDir = strtoupper($filters['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $allowedSorts = ['name', 'status', 'priority', 'start_date', 'created_at', 'total_impressions', 'total_clicks'];
        if (!in_array($sortColumn, $allowedSorts)) {
            $sortColumn = 'created_at';
        }

        $sql = "
            SELECT c.*,
                   (SELECT COUNT(*) FROM ad_creatives ac WHERE ac.campaign_id = c.id) as creative_count,
                   (SELECT COUNT(*) FROM ad_placements ap WHERE ap.campaign_id = c.id) as placement_count,
                   (SELECT GROUP_CONCAT(DISTINCT ac2.type) FROM ad_creatives ac2 WHERE ac2.campaign_id = c.id) as creative_types
            FROM ad_campaigns c
            WHERE {$whereClause}
            ORDER BY c.{$sortColumn} {$sortDir}
            LIMIT {$perPage} OFFSET {$offset}
        ";

        $data = $this->db->fetchAll($sql, $params);

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }

    /**
     * Get a single campaign with related data
     */
    public function getCampaign(int $id): ?array
    {
        $campaign = $this->db->fetch("SELECT * FROM ad_campaigns WHERE id = ?", [$id]);
        if (!$campaign) return null;

        $campaign['creatives'] = $this->db->fetchAll(
            "SELECT * FROM ad_creatives WHERE campaign_id = ? ORDER BY type, name",
            [$id]
        );

        $campaign['placements'] = $this->db->fetchAll(
            "SELECT ap.*, az.name as zone_name, az.slug as zone_slug, az.zone_type,
                    ac.name as creative_name, ac.type as creative_type
             FROM ad_placements ap
             JOIN ad_zones az ON ap.zone_id = az.id
             JOIN ad_creatives ac ON ap.creative_id = ac.id
             WHERE ap.campaign_id = ?
             ORDER BY ap.priority, ap.created_at",
            [$id]
        );

        // Load targeting rules for each placement
        foreach ($campaign['placements'] as &$placement) {
            $placement['targeting_rules'] = $this->db->fetchAll(
                "SELECT * FROM ad_targeting_rules WHERE placement_id = ?",
                [$placement['id']]
            );
            foreach ($placement['targeting_rules'] as &$rule) {
                $rule['rule_value'] = json_decode($rule['rule_value'], true);
            }
        }

        return $campaign;
    }

    /**
     * Create a new campaign
     */
    public function createCampaign(array $data): int
    {
        $this->db->execute(
            "INSERT INTO ad_campaigns (name, advertiser, description, status, priority,
             start_date, end_date, daily_budget, total_budget,
             daily_impressions_cap, total_impressions_cap, frequency_cap)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['name'],
                $data['advertiser'] ?? null,
                $data['description'] ?? null,
                $data['status'] ?? 'draft',
                $data['priority'] ?? 5,
                $data['start_date'] ?: null,
                $data['end_date'] ?: null,
                $data['daily_budget'] ?: null,
                $data['total_budget'] ?: null,
                $data['daily_impressions_cap'] ?: null,
                $data['total_impressions_cap'] ?: null,
                $data['frequency_cap'] ?: null,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a campaign
     */
    public function updateCampaign(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        $updatable = [
            'name', 'advertiser', 'description', 'status', 'priority',
            'start_date', 'end_date', 'daily_budget', 'total_budget',
            'daily_impressions_cap', 'total_impressions_cap', 'frequency_cap'
        ];

        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $value = $data[$field];
                // Convert empty strings to null for nullable fields
                if ($value === '' && in_array($field, ['advertiser', 'description', 'start_date', 'end_date', 'daily_budget', 'total_budget', 'daily_impressions_cap', 'total_impressions_cap', 'frequency_cap'])) {
                    $value = null;
                }
                $params[] = $value;
            }
        }

        if (empty($fields)) return false;

        $params[] = $id;
        $this->db->execute(
            "UPDATE ad_campaigns SET " . implode(', ', $fields) . " WHERE id = ?",
            $params
        );

        return true;
    }

    /**
     * Delete a campaign (cascades to creatives, placements, targeting)
     */
    public function deleteCampaign(int $id): bool
    {
        $this->db->execute("DELETE FROM ad_campaigns WHERE id = ?", [$id]);
        return true;
    }

    /**
     * Toggle campaign status between active/paused
     */
    public function toggleCampaignStatus(int $id): ?string
    {
        $campaign = $this->db->fetch("SELECT status FROM ad_campaigns WHERE id = ?", [$id]);
        if (!$campaign) return null;

        $newStatus = $campaign['status'] === 'active' ? 'paused' : 'active';
        $this->db->execute("UPDATE ad_campaigns SET status = ? WHERE id = ?", [$newStatus, $id]);

        return $newStatus;
    }

    // =========================================
    // Creative CRUD
    // =========================================

    /**
     * Get a single creative
     */
    public function getCreative(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM ad_creatives WHERE id = ?", [$id]);
    }

    /**
     * Create a creative
     */
    public function createCreative(int $campaignId, array $data): int
    {
        $this->db->execute(
            "INSERT INTO ad_creatives (campaign_id, name, type, status,
             scroll_text, scroll_speed, text_color, bg_color, bg_opacity,
             image_url, image_width, image_height, banner_position, click_url, click_target,
             video_url, vast_tag_url, video_duration, skip_after, companion_banner_url,
             midroll_offset_type, midroll_offset_value,
             alt_text, weight)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $campaignId,
                $data['name'],
                $data['type'],
                $data['status'] ?? 'draft',
                $data['scroll_text'] ?? null,
                $data['scroll_speed'] ?? 'normal',
                $data['text_color'] ?? '#FFFFFF',
                $data['bg_color'] ?? '#000000',
                $data['bg_opacity'] ?? 0.80,
                $data['image_url'] ?? null,
                $data['image_width'] ?: null,
                $data['image_height'] ?: null,
                $data['banner_position'] ?? 'bottom',
                $data['click_url'] ?? null,
                $data['click_target'] ?? '_blank',
                $data['video_url'] ?? null,
                $data['vast_tag_url'] ?? null,
                $data['video_duration'] ?: null,
                $data['skip_after'] ?: null,
                $data['companion_banner_url'] ?? null,
                $data['midroll_offset_type'] ?? 'percent',
                $data['midroll_offset_value'] ?? null,
                $data['alt_text'] ?? null,
                $data['weight'] ?? 100,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a creative
     */
    public function updateCreative(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        $updatable = [
            'name', 'type', 'status',
            'scroll_text', 'scroll_speed', 'text_color', 'bg_color', 'bg_opacity',
            'image_url', 'image_width', 'image_height', 'banner_position', 'click_url', 'click_target',
            'video_url', 'vast_tag_url', 'video_duration', 'skip_after', 'companion_banner_url',
            'midroll_offset_type', 'midroll_offset_value',
            'alt_text', 'weight'
        ];

        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $value = $data[$field];
                if ($value === '' && !in_array($field, ['name', 'type', 'status'])) {
                    $value = null;
                }
                $params[] = $value;
            }
        }

        if (empty($fields)) return false;

        $params[] = $id;
        $this->db->execute(
            "UPDATE ad_creatives SET " . implode(', ', $fields) . " WHERE id = ?",
            $params
        );

        return true;
    }

    /**
     * Delete a creative
     */
    public function deleteCreative(int $id): bool
    {
        $this->db->execute("DELETE FROM ad_creatives WHERE id = ?", [$id]);
        return true;
    }

    // =========================================
    // Zones
    // =========================================

    /**
     * Get all ad zones
     */
    public function getZones(): array
    {
        $zones = $this->db->fetchAll("SELECT * FROM ad_zones ORDER BY zone_type, name");
        foreach ($zones as &$zone) {
            if ($zone['default_settings']) {
                $zone['default_settings'] = json_decode($zone['default_settings'], true);
            }
        }
        return $zones;
    }

    /**
     * Get zones by type
     */
    public function getZonesByType(string $type): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM ad_zones WHERE zone_type = ? AND is_active = 1 ORDER BY name",
            [$type]
        );
    }

    /**
     * Get a single zone
     */
    public function getZone(int $id): ?array
    {
        $zone = $this->db->fetch("SELECT * FROM ad_zones WHERE id = ?", [$id]);
        if ($zone && $zone['default_settings']) {
            $zone['default_settings'] = json_decode($zone['default_settings'], true);
        }
        return $zone;
    }

    /**
     * Create a zone
     */
    public function createZone(array $data): int
    {
        $this->db->execute(
            "INSERT INTO ad_zones (name, slug, zone_type, description, default_settings, is_active)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $data['name'],
                $data['slug'],
                $data['zone_type'],
                $data['description'] ?? null,
                !empty($data['default_settings']) ? json_encode($data['default_settings']) : null,
                $data['is_active'] ?? 1,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a zone
     */
    public function updateZone(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        if (isset($data['name'])) { $fields[] = 'name = ?'; $params[] = $data['name']; }
        if (isset($data['slug'])) { $fields[] = 'slug = ?'; $params[] = $data['slug']; }
        if (isset($data['zone_type'])) { $fields[] = 'zone_type = ?'; $params[] = $data['zone_type']; }
        if (isset($data['description'])) { $fields[] = 'description = ?'; $params[] = $data['description'] ?: null; }
        if (isset($data['default_settings'])) {
            $fields[] = 'default_settings = ?';
            $params[] = is_array($data['default_settings']) ? json_encode($data['default_settings']) : $data['default_settings'];
        }
        if (isset($data['is_active'])) { $fields[] = 'is_active = ?'; $params[] = (int) $data['is_active']; }

        if (empty($fields)) return false;

        $params[] = $id;
        $this->db->execute(
            "UPDATE ad_zones SET " . implode(', ', $fields) . " WHERE id = ?",
            $params
        );

        return true;
    }

    /**
     * Toggle zone active status
     */
    public function toggleZone(int $id): bool
    {
        $this->db->execute(
            "UPDATE ad_zones SET is_active = NOT is_active WHERE id = ?",
            [$id]
        );
        return true;
    }

    /**
     * Delete a zone
     */
    public function deleteZone(int $id): bool
    {
        $this->db->execute("DELETE FROM ad_zones WHERE id = ?", [$id]);
        return true;
    }

    // =========================================
    // Placements & Targeting
    // =========================================

    /**
     * Create a placement with targeting rules
     */
    public function createPlacement(array $data, array $targetingRules = []): int
    {
        $this->db->execute(
            "INSERT INTO ad_placements (campaign_id, creative_id, zone_id, status, priority, start_date, end_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $data['campaign_id'],
                $data['creative_id'],
                $data['zone_id'],
                $data['status'] ?? 'active',
                $data['priority'] ?? 5,
                $data['start_date'] ?: null,
                $data['end_date'] ?: null,
            ]
        );

        $placementId = (int) $this->db->lastInsertId();

        // Add targeting rules
        foreach ($targetingRules as $rule) {
            $this->addTargetingRule($placementId, $rule);
        }

        return $placementId;
    }

    /**
     * Update a placement
     */
    public function updatePlacement(int $id, array $data): bool
    {
        $fields = [];
        $params = [];

        $updatable = ['creative_id', 'zone_id', 'status', 'priority', 'start_date', 'end_date'];
        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $value = $data[$field];
                if ($value === '' && in_array($field, ['start_date', 'end_date'])) {
                    $value = null;
                }
                $params[] = $value;
            }
        }

        if (empty($fields)) return false;

        $params[] = $id;
        $this->db->execute(
            "UPDATE ad_placements SET " . implode(', ', $fields) . " WHERE id = ?",
            $params
        );

        return true;
    }

    /**
     * Delete a placement (cascades targeting rules)
     */
    public function deletePlacement(int $id): bool
    {
        $this->db->execute("DELETE FROM ad_placements WHERE id = ?", [$id]);
        return true;
    }

    /**
     * Add a targeting rule to a placement
     */
    public function addTargetingRule(int $placementId, array $rule): int
    {
        $this->db->execute(
            "INSERT INTO ad_targeting_rules (placement_id, rule_type, rule_operator, rule_value)
             VALUES (?, ?, ?, ?)",
            [
                $placementId,
                $rule['rule_type'],
                $rule['rule_operator'] ?? 'include',
                json_encode($rule['rule_value']),
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Delete a targeting rule
     */
    public function deleteTargetingRule(int $ruleId): bool
    {
        $this->db->execute("DELETE FROM ad_targeting_rules WHERE id = ?", [$ruleId]);
        return true;
    }

    /**
     * Replace all targeting rules for a placement
     */
    public function replaceTargetingRules(int $placementId, array $rules): void
    {
        $this->db->execute("DELETE FROM ad_targeting_rules WHERE placement_id = ?", [$placementId]);
        foreach ($rules as $rule) {
            $this->addTargetingRule($placementId, $rule);
        }
    }

    // =========================================
    // Ad Serving API
    // =========================================

    /**
     * Get ads to serve for a given context
     * This is the main ad decision engine
     */
    public function getAdsForContext(array $context): array
    {
        $zoneType = $context['zone_type'] ?? null;      // text_scroller, banner, pre_roll, mid_roll
        $zoneSlug = $context['zone_slug'] ?? null;
        $channelId = $context['channel_id'] ?? null;
        $contentType = $context['content_type'] ?? null; // live, vod, series
        $contentId = $context['content_id'] ?? null;
        $categoryId = $context['category_id'] ?? null;
        $packageId = $context['package_id'] ?? null;
        $platform = $context['platform'] ?? null;        // web, mobile, tv, stb
        $userId = $context['user_id'] ?? null;
        $limit = $context['limit'] ?? 5;

        $now = date('Y-m-d H:i:s');

        // Base query: active campaigns with active creatives in matching zones
        $sql = "
            SELECT
                ac.*, ap.id as placement_id, ap.priority as placement_priority,
                az.slug as zone_slug, az.zone_type,
                camp.name as campaign_name, camp.frequency_cap,
                camp.priority as campaign_priority
            FROM ad_placements ap
            JOIN ad_campaigns camp ON ap.campaign_id = camp.id
            JOIN ad_creatives ac ON ap.creative_id = ac.id
            JOIN ad_zones az ON ap.zone_id = az.id
            WHERE camp.status = 'active'
              AND ac.status = 'active'
              AND ap.status = 'active'
              AND az.is_active = 1
              AND (camp.start_date IS NULL OR camp.start_date <= ?)
              AND (camp.end_date IS NULL OR camp.end_date >= ?)
              AND (ap.start_date IS NULL OR ap.start_date <= ?)
              AND (ap.end_date IS NULL OR ap.end_date >= ?)
              AND (camp.total_impressions_cap IS NULL OR camp.total_impressions < camp.total_impressions_cap)
        ";

        $params = [$now, $now, $now, $now];

        // Filter by zone type
        if ($zoneType) {
            $sql .= " AND az.zone_type = ?";
            $params[] = $zoneType;
        }

        // Filter by zone slug
        if ($zoneSlug) {
            $sql .= " AND az.slug = ?";
            $params[] = $zoneSlug;
        }

        $sql .= " ORDER BY camp.priority ASC, ap.priority ASC, ac.weight DESC";
        $sql .= " LIMIT " . (int) ($limit * 3); // Fetch extra for targeting filter

        $candidates = $this->db->fetchAll($sql, $params);

        // Apply targeting rules
        $matched = [];
        foreach ($candidates as $candidate) {
            $rules = $this->db->fetchAll(
                "SELECT * FROM ad_targeting_rules WHERE placement_id = ?",
                [$candidate['placement_id']]
            );

            if ($this->matchesTargetingRules($rules, $context)) {
                // Check frequency cap
                if ($userId && $candidate['frequency_cap']) {
                    $todayCount = $this->getUserImpressionCount($userId, $candidate['campaign_id']);
                    if ($todayCount >= $candidate['frequency_cap']) {
                        continue;
                    }
                }

                $matched[] = $candidate;
                if (count($matched) >= $limit) break;
            }
        }

        return $matched;
    }

    /**
     * Check if a context matches targeting rules
     */
    private function matchesTargetingRules(array $rules, array $context): bool
    {
        if (empty($rules)) return true; // No rules = match all

        foreach ($rules as $rule) {
            $values = json_decode($rule['rule_value'], true);
            if (!is_array($values)) continue;

            $isInclude = $rule['rule_operator'] === 'include';
            $matches = false;

            switch ($rule['rule_type']) {
                case 'package':
                    $packageId = $context['package_id'] ?? null;
                    $matches = $packageId && in_array($packageId, $values);
                    break;

                case 'channel':
                    $channelId = $context['channel_id'] ?? null;
                    $matches = $channelId && in_array($channelId, $values);
                    break;

                case 'category':
                    $categoryId = $context['category_id'] ?? null;
                    $matches = $categoryId && in_array($categoryId, $values);
                    break;

                case 'content_type':
                    $contentType = $context['content_type'] ?? null;
                    $matches = $contentType && in_array($contentType, $values);
                    break;

                case 'platform':
                    $platform = $context['platform'] ?? null;
                    $matches = $platform && in_array($platform, $values);
                    break;

                case 'geo':
                    $geo = $context['geo'] ?? null;
                    $matches = $geo && in_array($geo, $values);
                    break;

                case 'schedule':
                    // Values: ["HH:MM-HH:MM", ...] e.g., ["06:00-12:00", "18:00-23:59"]
                    $currentTime = date('H:i');
                    foreach ($values as $timeRange) {
                        $parts = explode('-', $timeRange);
                        if (count($parts) === 2) {
                            if ($currentTime >= trim($parts[0]) && $currentTime <= trim($parts[1])) {
                                $matches = true;
                                break;
                            }
                        }
                    }
                    break;
            }

            // Include rule: must match at least one value
            if ($isInclude && !$matches) return false;
            // Exclude rule: must NOT match any value
            if (!$isInclude && $matches) return false;
        }

        return true;
    }

    /**
     * Get user impression count for today
     */
    private function getUserImpressionCount(int $userId, int $campaignId): int
    {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM ad_impressions
             WHERE user_id = ? AND campaign_id = ? AND DATE(created_at) = CURDATE()",
            [$userId, $campaignId]
        );
        return (int) ($result['cnt'] ?? 0);
    }

    /**
     * Record an impression
     */
    public function recordImpression(array $data): int
    {
        $this->db->execute(
            "INSERT INTO ad_impressions (campaign_id, creative_id, placement_id, zone_id,
             user_id, session_id, ip_address, user_agent, platform,
             channel_id, content_type, content_id, revenue)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['campaign_id'],
                $data['creative_id'],
                $data['placement_id'] ?? null,
                $data['zone_id'] ?? null,
                $data['user_id'] ?? null,
                $data['session_id'] ?? null,
                $data['ip_address'] ?? null,
                $data['user_agent'] ?? null,
                $data['platform'] ?? null,
                $data['channel_id'] ?? null,
                $data['content_type'] ?? null,
                $data['content_id'] ?? null,
                $data['revenue'] ?? 0,
            ]
        );

        $impressionId = (int) $this->db->lastInsertId();

        // Update counters
        $this->db->execute(
            "UPDATE ad_campaigns SET total_impressions = total_impressions + 1 WHERE id = ?",
            [$data['campaign_id']]
        );
        $this->db->execute(
            "UPDATE ad_creatives SET impressions = impressions + 1 WHERE id = ?",
            [$data['creative_id']]
        );

        return $impressionId;
    }

    /**
     * Record an ad event (click, complete, skip, etc.)
     */
    public function recordEvent(array $data): int
    {
        $this->db->execute(
            "INSERT INTO ad_events (impression_id, campaign_id, creative_id, event_type,
             user_id, session_id, ip_address, metadata)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['impression_id'] ?? null,
                $data['campaign_id'],
                $data['creative_id'],
                $data['event_type'],
                $data['user_id'] ?? null,
                $data['session_id'] ?? null,
                $data['ip_address'] ?? null,
                !empty($data['metadata']) ? json_encode($data['metadata']) : null,
            ]
        );

        $eventId = (int) $this->db->lastInsertId();

        // Update click counter if click event
        if ($data['event_type'] === 'click') {
            $this->db->execute(
                "UPDATE ad_campaigns SET total_clicks = total_clicks + 1 WHERE id = ?",
                [$data['campaign_id']]
            );
            $this->db->execute(
                "UPDATE ad_creatives SET clicks = clicks + 1 WHERE id = ?",
                [$data['creative_id']]
            );
        }

        return $eventId;
    }

    // =========================================
    // Statistics & Reporting
    // =========================================

    /**
     * Get dashboard statistics
     */
    public function getStatistics(): array
    {
        $stats = $this->db->fetch("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(total_impressions) as total_impressions,
                SUM(total_clicks) as total_clicks,
                SUM(total_spend) as total_spend
            FROM ad_campaigns
        ");

        // Today's stats
        $today = $this->db->fetch("
            SELECT
                COUNT(*) as impressions_today,
                (SELECT COUNT(*) FROM ad_events WHERE event_type = 'click' AND DATE(created_at) = CURDATE()) as clicks_today
            FROM ad_impressions
            WHERE DATE(created_at) = CURDATE()
        ");

        return [
            'total' => (int) ($stats['total'] ?? 0),
            'active' => (int) ($stats['active'] ?? 0),
            'paused' => (int) ($stats['paused'] ?? 0),
            'draft' => (int) ($stats['draft'] ?? 0),
            'completed' => (int) ($stats['completed'] ?? 0),
            'total_impressions' => (int) ($stats['total_impressions'] ?? 0),
            'total_clicks' => (int) ($stats['total_clicks'] ?? 0),
            'total_spend' => (float) ($stats['total_spend'] ?? 0),
            'impressions_today' => (int) ($today['impressions_today'] ?? 0),
            'clicks_today' => (int) ($today['clicks_today'] ?? 0),
            'ctr' => ($stats['total_impressions'] ?? 0) > 0
                ? round(($stats['total_clicks'] / $stats['total_impressions']) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get campaign performance report
     */
    public function getCampaignReport(int $campaignId, string $startDate = null, string $endDate = null): array
    {
        $where = ['campaign_id = ?'];
        $params = [$campaignId];

        if ($startDate) {
            $where[] = 'DATE(created_at) >= ?';
            $params[] = $startDate;
        }
        if ($endDate) {
            $where[] = 'DATE(created_at) <= ?';
            $params[] = $endDate;
        }

        $whereClause = implode(' AND ', $where);

        // Daily breakdown
        $daily = $this->db->fetchAll(
            "SELECT DATE(created_at) as date,
                    COUNT(*) as impressions,
                    SUM(revenue) as revenue
             FROM ad_impressions
             WHERE {$whereClause}
             GROUP BY DATE(created_at)
             ORDER BY date",
            $params
        );

        // Click data
        $clicks = $this->db->fetchAll(
            "SELECT DATE(created_at) as date, COUNT(*) as clicks
             FROM ad_events
             WHERE campaign_id = ? AND event_type = 'click'
             " . ($startDate ? "AND DATE(created_at) >= '{$startDate}'" : "") . "
             " . ($endDate ? "AND DATE(created_at) <= '{$endDate}'" : "") . "
             GROUP BY DATE(created_at)
             ORDER BY date",
            [$campaignId]
        );

        // Creative breakdown
        $byCreative = $this->db->fetchAll(
            "SELECT ac.name, ac.type, ac.impressions, ac.clicks,
                    CASE WHEN ac.impressions > 0 THEN ROUND((ac.clicks / ac.impressions) * 100, 2) ELSE 0 END as ctr
             FROM ad_creatives ac
             WHERE ac.campaign_id = ?
             ORDER BY ac.impressions DESC",
            [$campaignId]
        );

        // Zone breakdown
        $byZone = $this->db->fetchAll(
            "SELECT az.name as zone_name, az.zone_type,
                    COUNT(*) as impressions,
                    SUM(ai.revenue) as revenue
             FROM ad_impressions ai
             JOIN ad_zones az ON ai.zone_id = az.id
             WHERE ai.campaign_id = ?
             GROUP BY az.id
             ORDER BY impressions DESC",
            [$campaignId]
        );

        return [
            'daily' => $daily,
            'clicks' => $clicks,
            'by_creative' => $byCreative,
            'by_zone' => $byZone,
        ];
    }

    /**
     * Get overall report across all campaigns
     */
    public function getOverallReport(string $startDate = null, string $endDate = null): array
    {
        $where = ['1=1'];
        $params = [];

        if ($startDate) {
            $where[] = 'DATE(created_at) >= ?';
            $params[] = $startDate;
        }
        if ($endDate) {
            $where[] = 'DATE(created_at) <= ?';
            $params[] = $endDate;
        }

        $whereClause = implode(' AND ', $where);

        $daily = $this->db->fetchAll(
            "SELECT DATE(created_at) as date,
                    COUNT(*) as impressions,
                    SUM(revenue) as revenue
             FROM ad_impressions
             WHERE {$whereClause}
             GROUP BY DATE(created_at)
             ORDER BY date DESC
             LIMIT 30",
            $params
        );

        $byCampaign = $this->db->fetchAll(
            "SELECT c.id, c.name, c.status, c.total_impressions, c.total_clicks, c.total_spend,
                    CASE WHEN c.total_impressions > 0 THEN ROUND((c.total_clicks / c.total_impressions) * 100, 2) ELSE 0 END as ctr
             FROM ad_campaigns c
             WHERE c.status IN ('active', 'paused', 'completed')
             ORDER BY c.total_impressions DESC
             LIMIT 20"
        );

        $byType = $this->db->fetchAll(
            "SELECT ac.type, SUM(ac.impressions) as impressions, SUM(ac.clicks) as clicks
             FROM ad_creatives ac
             GROUP BY ac.type"
        );

        return [
            'daily' => $daily,
            'by_campaign' => $byCampaign,
            'by_type' => $byType,
        ];
    }

    // =========================================
    // Helpers
    // =========================================

    /**
     * Get channels for targeting picker
     */
    public function getChannels(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name FROM channels WHERE is_active = 1 ORDER BY name"
        );
    }

    /**
     * Get categories for targeting picker
     */
    public function getCategories(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, type FROM categories WHERE is_active = 1 ORDER BY type, name"
        );
    }

    /**
     * Bulk update campaign status
     */
    public function bulkUpdateStatus(array $ids, string $status): int
    {
        if (empty($ids)) return 0;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$status], $ids);

        $this->db->execute(
            "UPDATE ad_campaigns SET status = ? WHERE id IN ({$placeholders})",
            $params
        );

        return count($ids);
    }

    /**
     * Get ad type display info
     */
    public static function getAdTypes(): array
    {
        return [
            'text_scroller' => ['name' => 'Text Scroller', 'icon' => 'lucide-text', 'color' => 'info'],
            'banner' => ['name' => 'Banner Image', 'icon' => 'lucide-image', 'color' => 'success'],
            'pre_roll' => ['name' => 'Pre-Roll Video', 'icon' => 'lucide-play-circle', 'color' => 'warning'],
            'mid_roll' => ['name' => 'Mid-Roll Video', 'icon' => 'lucide-scissors', 'color' => 'danger'],
        ];
    }

    /**
     * Get targeting rule type display info
     */
    public static function getTargetingTypes(): array
    {
        return [
            'package' => ['name' => 'Package/Plan', 'description' => 'Show ads based on user subscription plan'],
            'channel' => ['name' => 'Channel', 'description' => 'Target specific TV channels'],
            'category' => ['name' => 'Category', 'description' => 'Target content categories/genres'],
            'content_type' => ['name' => 'Content Type', 'description' => 'Target live TV, VOD, or series'],
            'platform' => ['name' => 'Platform', 'description' => 'Target web, mobile, TV, or STB'],
            'geo' => ['name' => 'Geography', 'description' => 'Target by country or region'],
            'schedule' => ['name' => 'Schedule', 'description' => 'Show ads during specific hours'],
        ];
    }
}
