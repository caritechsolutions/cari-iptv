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

    // =========================================
    // Ad Waterfall / Fallback Chains
    // =========================================

    public function getWaterfallChains(int $zoneId = null): array
    {
        $sql = "SELECT wc.*, az.name as zone_name, az.slug as zone_slug, az.zone_type
                FROM ad_waterfall_chains wc
                JOIN ad_zones az ON wc.zone_id = az.id";
        $params = [];

        if ($zoneId) {
            $sql .= " WHERE wc.zone_id = ?";
            $params[] = $zoneId;
        }

        $sql .= " ORDER BY az.name, wc.name";
        $chains = $this->db->fetchAll($sql, $params);

        foreach ($chains as &$chain) {
            $chain['steps'] = $this->db->fetchAll(
                "SELECT ws.*, c.name as campaign_name
                 FROM ad_waterfall_steps ws
                 LEFT JOIN ad_campaigns c ON ws.campaign_id = c.id
                 WHERE ws.chain_id = ?
                 ORDER BY ws.step_order",
                [$chain['id']]
            );
        }

        return $chains;
    }

    public function getWaterfallChain(int $id): ?array
    {
        $chain = $this->db->fetch(
            "SELECT wc.*, az.name as zone_name, az.slug as zone_slug, az.zone_type
             FROM ad_waterfall_chains wc
             JOIN ad_zones az ON wc.zone_id = az.id
             WHERE wc.id = ?",
            [$id]
        );
        if (!$chain) return null;

        $chain['steps'] = $this->db->fetchAll(
            "SELECT ws.*, c.name as campaign_name
             FROM ad_waterfall_steps ws
             LEFT JOIN ad_campaigns c ON ws.campaign_id = c.id
             WHERE ws.chain_id = ?
             ORDER BY ws.step_order",
            [$chain['id']]
        );

        return $chain;
    }

    public function createWaterfallChain(array $data): int
    {
        $this->db->execute(
            "INSERT INTO ad_waterfall_chains (zone_id, name, is_active) VALUES (?, ?, ?)",
            [$data['zone_id'], $data['name'], $data['is_active'] ?? 1]
        );
        return (int) $this->db->lastInsertId();
    }

    public function updateWaterfallChain(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        foreach (['name', 'zone_id', 'is_active'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = ?";
                $params[] = $data[$f];
            }
        }
        if (empty($fields)) return false;
        $params[] = $id;
        $this->db->execute("UPDATE ad_waterfall_chains SET " . implode(', ', $fields) . " WHERE id = ?", $params);
        return true;
    }

    public function deleteWaterfallChain(int $id): bool
    {
        $this->db->execute("DELETE FROM ad_waterfall_chains WHERE id = ?", [$id]);
        return true;
    }

    public function saveWaterfallSteps(int $chainId, array $steps): void
    {
        $this->db->execute("DELETE FROM ad_waterfall_steps WHERE chain_id = ?", [$chainId]);
        foreach ($steps as $i => $step) {
            $this->db->execute(
                "INSERT INTO ad_waterfall_steps (chain_id, step_order, source_type, campaign_id, vast_tag_url, timeout_ms, floor_cpm, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $chainId,
                    $i + 1,
                    $step['source_type'] ?? 'direct',
                    !empty($step['campaign_id']) ? (int) $step['campaign_id'] : null,
                    $step['vast_tag_url'] ?? null,
                    $step['timeout_ms'] ?? 3000,
                    !empty($step['floor_cpm']) ? (float) $step['floor_cpm'] : null,
                    $step['is_active'] ?? 1,
                ]
            );
        }
    }

    /**
     * Execute waterfall for a zone — try each step in order until one fills
     */
    public function executeWaterfall(int $zoneId, array $context): array
    {
        $chain = $this->db->fetch(
            "SELECT * FROM ad_waterfall_chains WHERE zone_id = ? AND is_active = 1 ORDER BY id LIMIT 1",
            [$zoneId]
        );
        if (!$chain) return [];

        $steps = $this->db->fetchAll(
            "SELECT * FROM ad_waterfall_steps WHERE chain_id = ? AND is_active = 1 ORDER BY step_order",
            [$chain['id']]
        );

        foreach ($steps as $step) {
            switch ($step['source_type']) {
                case 'direct':
                case 'campaign':
                    $ctx = $context;
                    if ($step['campaign_id']) {
                        $ctx['campaign_id'] = $step['campaign_id'];
                    }
                    $ads = $this->getAdsForContext($ctx);
                    if (!empty($ads)) {
                        return ['source' => $step['source_type'], 'step_id' => $step['id'], 'ads' => $ads];
                    }
                    break;

                case 'vast_tag':
                    if ($step['vast_tag_url']) {
                        return [
                            'source' => 'vast_tag',
                            'step_id' => $step['id'],
                            'vast_url' => $step['vast_tag_url'],
                            'ads' => [],
                        ];
                    }
                    break;

                case 'house':
                    $ctx = $context;
                    $ctx['house_only'] = true;
                    $ads = $this->getAdsForContext($ctx);
                    if (!empty($ads)) {
                        return ['source' => 'house', 'step_id' => $step['id'], 'ads' => $ads];
                    }
                    break;
            }
        }

        return [];
    }

    // =========================================
    // A/B Testing
    // =========================================

    public function getAbTests(int $campaignId = null): array
    {
        $sql = "SELECT t.*, c.name as campaign_name
                FROM ad_ab_tests t
                JOIN ad_campaigns c ON t.campaign_id = c.id";
        $params = [];

        if ($campaignId) {
            $sql .= " WHERE t.campaign_id = ?";
            $params[] = $campaignId;
        }
        $sql .= " ORDER BY t.created_at DESC";

        $tests = $this->db->fetchAll($sql, $params);

        foreach ($tests as &$test) {
            $test['variants'] = $this->db->fetchAll(
                "SELECT v.*, ac.name as creative_name, ac.type as creative_type
                 FROM ad_ab_test_variants v
                 JOIN ad_creatives ac ON v.creative_id = ac.id
                 WHERE v.test_id = ?
                 ORDER BY v.is_control DESC, v.id",
                [$test['id']]
            );
        }

        return $tests;
    }

    public function getAbTest(int $id): ?array
    {
        $test = $this->db->fetch(
            "SELECT t.*, c.name as campaign_name
             FROM ad_ab_tests t
             JOIN ad_campaigns c ON t.campaign_id = c.id
             WHERE t.id = ?",
            [$id]
        );
        if (!$test) return null;

        $test['variants'] = $this->db->fetchAll(
            "SELECT v.*, ac.name as creative_name, ac.type as creative_type
             FROM ad_ab_test_variants v
             JOIN ad_creatives ac ON v.creative_id = ac.id
             WHERE v.test_id = ?
             ORDER BY v.is_control DESC, v.id",
            [$test['id']]
        );

        return $test;
    }

    public function createAbTest(int $campaignId, array $data): int
    {
        $this->db->execute(
            "INSERT INTO ad_ab_tests (campaign_id, name, status, confidence_threshold, min_impressions, metric)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $campaignId,
                $data['name'],
                $data['status'] ?? 'draft',
                $data['confidence_threshold'] ?? 95.00,
                $data['min_impressions'] ?? 1000,
                $data['metric'] ?? 'ctr',
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    public function addAbTestVariant(int $testId, array $data): int
    {
        $this->db->execute(
            "INSERT INTO ad_ab_test_variants (test_id, creative_id, traffic_weight, is_control)
             VALUES (?, ?, ?, ?)",
            [
                $testId,
                $data['creative_id'],
                $data['traffic_weight'] ?? 50,
                $data['is_control'] ?? 0,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    public function updateAbTest(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        foreach (['name', 'status', 'confidence_threshold', 'min_impressions', 'metric', 'winner_creative_id'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = ?";
                $params[] = $data[$f];
            }
        }
        if (isset($data['status']) && $data['status'] === 'running' && empty($data['started_at'])) {
            $fields[] = "started_at = NOW()";
        }
        if (isset($data['status']) && $data['status'] === 'completed') {
            $fields[] = "completed_at = NOW()";
        }
        if (empty($fields)) return false;
        $params[] = $id;
        $this->db->execute("UPDATE ad_ab_tests SET " . implode(', ', $fields) . " WHERE id = ?", $params);
        return true;
    }

    public function deleteAbTest(int $id): bool
    {
        $this->db->execute("DELETE FROM ad_ab_tests WHERE id = ?", [$id]);
        return true;
    }

    /**
     * Pick a creative variant based on A/B test traffic weights
     */
    public function pickAbTestVariant(int $testId): ?array
    {
        $test = $this->db->fetch("SELECT * FROM ad_ab_tests WHERE id = ? AND status = 'running'", [$testId]);
        if (!$test) return null;

        $variants = $this->db->fetchAll(
            "SELECT * FROM ad_ab_test_variants WHERE test_id = ?",
            [$testId]
        );
        if (empty($variants)) return null;

        // Weighted random selection
        $totalWeight = array_sum(array_column($variants, 'traffic_weight'));
        $rand = mt_rand(1, max(1, $totalWeight));
        $cumulative = 0;

        foreach ($variants as $variant) {
            $cumulative += $variant['traffic_weight'];
            if ($rand <= $cumulative) {
                return $variant;
            }
        }

        return $variants[0];
    }

    /**
     * Record A/B test impression/event and auto-evaluate winner
     */
    public function recordAbTestEvent(int $testId, int $variantId, string $eventType): void
    {
        $field = match ($eventType) {
            'impression' => 'impressions',
            'click' => 'clicks',
            'complete' => 'completions',
            'conversion' => 'conversions',
            default => null,
        };
        if (!$field) return;

        $this->db->execute(
            "UPDATE ad_ab_test_variants SET {$field} = {$field} + 1 WHERE id = ?",
            [$variantId]
        );

        // Check if test should auto-complete
        $this->evaluateAbTest($testId);
    }

    /**
     * Evaluate A/B test statistical significance
     */
    public function evaluateAbTest(int $testId): ?array
    {
        $test = $this->db->fetch("SELECT * FROM ad_ab_tests WHERE id = ? AND status = 'running'", [$testId]);
        if (!$test) return null;

        $variants = $this->db->fetchAll(
            "SELECT * FROM ad_ab_test_variants WHERE test_id = ? ORDER BY is_control DESC",
            [$testId]
        );
        if (count($variants) < 2) return null;

        // Check min impressions
        $allReady = true;
        foreach ($variants as $v) {
            if ($v['impressions'] < $test['min_impressions']) {
                $allReady = false;
                break;
            }
        }
        if (!$allReady) return ['status' => 'collecting', 'variants' => $variants];

        // Calculate rates based on metric
        $rates = [];
        foreach ($variants as $v) {
            $rate = match ($test['metric']) {
                'ctr' => $v['impressions'] > 0 ? ($v['clicks'] / $v['impressions']) * 100 : 0,
                'completion_rate' => $v['impressions'] > 0 ? ($v['completions'] / $v['impressions']) * 100 : 0,
                'conversion_rate' => $v['clicks'] > 0 ? ($v['conversions'] / $v['clicks']) * 100 : 0,
                default => 0,
            };
            $rates[$v['id']] = ['variant' => $v, 'rate' => $rate];
        }

        // Simple z-test for proportions between best and second-best
        uasort($rates, fn($a, $b) => $b['rate'] <=> $a['rate']);
        $sorted = array_values($rates);
        $best = $sorted[0];
        $second = $sorted[1];

        $n1 = $best['variant']['impressions'];
        $n2 = $second['variant']['impressions'];
        $p1 = $best['rate'] / 100;
        $p2 = $second['rate'] / 100;
        $pPooled = ($p1 * $n1 + $p2 * $n2) / ($n1 + $n2);

        $se = sqrt($pPooled * (1 - $pPooled) * (1 / $n1 + 1 / $n2));
        $zScore = $se > 0 ? abs($p1 - $p2) / $se : 0;

        // z-score thresholds: 90%=1.645, 95%=1.96, 99%=2.576
        $threshold = match (true) {
            $test['confidence_threshold'] >= 99 => 2.576,
            $test['confidence_threshold'] >= 95 => 1.96,
            default => 1.645,
        };

        $isSignificant = $zScore >= $threshold;
        $confidence = min(99.9, $this->zScoreToConfidence($zScore));

        $result = [
            'status' => $isSignificant ? 'significant' : 'collecting',
            'winner_id' => $isSignificant ? $best['variant']['id'] : null,
            'winner_creative_id' => $isSignificant ? $best['variant']['creative_id'] : null,
            'z_score' => round($zScore, 3),
            'confidence' => round($confidence, 1),
            'rates' => $rates,
            'variants' => $variants,
        ];

        // Auto-complete if significant
        if ($isSignificant) {
            $this->updateAbTest($testId, [
                'status' => 'completed',
                'winner_creative_id' => $best['variant']['creative_id'],
            ]);
        }

        return $result;
    }

    private function zScoreToConfidence(float $z): float
    {
        // Approximation of normal CDF
        $t = 1.0 / (1.0 + 0.2316419 * abs($z));
        $d = 0.3989422804 * exp(-$z * $z / 2);
        $p = $d * $t * (0.3193815 + $t * (-0.3565638 + $t * (1.781478 + $t * (-1.821256 + $t * 1.330274))));
        if ($z > 0) $p = 1 - $p;
        return (1 - 2 * $p) * 100;
    }

    // =========================================
    // Ad Pods / Ad Breaks
    // =========================================

    public function getAdPods(int $zoneId = null): array
    {
        $sql = "SELECT p.*, az.name as zone_name, az.slug as zone_slug, az.zone_type
                FROM ad_pods p
                JOIN ad_zones az ON p.zone_id = az.id";
        $params = [];
        if ($zoneId) {
            $sql .= " WHERE p.zone_id = ?";
            $params[] = $zoneId;
        }
        $sql .= " ORDER BY p.pod_type, p.name";
        return $this->db->fetchAll($sql, $params);
    }

    public function getAdPod(int $id): ?array
    {
        return $this->db->fetch(
            "SELECT p.*, az.name as zone_name, az.slug as zone_slug, az.zone_type
             FROM ad_pods p
             JOIN ad_zones az ON p.zone_id = az.id
             WHERE p.id = ?",
            [$id]
        );
    }

    public function createAdPod(array $data): int
    {
        $this->db->execute(
            "INSERT INTO ad_pods (zone_id, name, max_ads, max_duration_seconds, min_content_duration,
             separation_seconds, allow_competitor_ads, pod_type, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['zone_id'],
                $data['name'],
                $data['max_ads'] ?? 3,
                $data['max_duration_seconds'] ?? 90,
                $data['min_content_duration'] ?: null,
                $data['separation_seconds'] ?? 300,
                $data['allow_competitor_ads'] ?? 0,
                $data['pod_type'] ?? 'pre_roll',
                $data['is_active'] ?? 1,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    public function updateAdPod(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        $updatable = ['zone_id', 'name', 'max_ads', 'max_duration_seconds', 'min_content_duration',
                       'separation_seconds', 'allow_competitor_ads', 'pod_type', 'is_active'];
        foreach ($updatable as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = ?";
                $params[] = $data[$f] === '' ? null : $data[$f];
            }
        }
        if (empty($fields)) return false;
        $params[] = $id;
        $this->db->execute("UPDATE ad_pods SET " . implode(', ', $fields) . " WHERE id = ?", $params);
        return true;
    }

    public function deleteAdPod(int $id): bool
    {
        $this->db->execute("DELETE FROM ad_pods WHERE id = ?", [$id]);
        return true;
    }

    /**
     * Build an ad pod — fill a break with multiple ads respecting constraints
     * Integrates with content_markers cue points when midroll_offset_type = 'cue'
     */
    public function buildAdPod(array $context): array
    {
        $zoneId = $context['zone_id'] ?? null;
        $zoneType = $context['zone_type'] ?? 'pre_roll';

        // Find pod config for this zone
        $podConfig = null;
        if ($zoneId) {
            $podConfig = $this->db->fetch(
                "SELECT * FROM ad_pods WHERE zone_id = ? AND is_active = 1 AND pod_type = ? LIMIT 1",
                [$zoneId, $zoneType]
            );
        }
        if (!$podConfig) {
            $podConfig = $this->db->fetch(
                "SELECT p.* FROM ad_pods p
                 JOIN ad_zones az ON p.zone_id = az.id
                 WHERE az.zone_type = ? AND p.is_active = 1 AND p.pod_type = ?
                 LIMIT 1",
                [$zoneType, $zoneType]
            );
        }

        $maxAds = $podConfig ? (int) $podConfig['max_ads'] : 3;
        $maxDuration = $podConfig ? (int) $podConfig['max_duration_seconds'] : 90;
        $allowCompetitors = $podConfig ? (bool) $podConfig['allow_competitor_ads'] : false;

        // Get more candidates than needed
        $ctx = $context;
        $ctx['limit'] = $maxAds * 3;
        $candidates = $this->getAdsForContext($ctx);

        $pod = [];
        $totalDuration = 0;
        $advertiserCategories = [];

        foreach ($candidates as $ad) {
            if (count($pod) >= $maxAds) break;

            $adDuration = (int) ($ad['video_duration'] ?? 15);
            if ($totalDuration + $adDuration > $maxDuration) continue;

            // Competitor separation
            if (!$allowCompetitors && !empty($ad['advertiser_category'])) {
                $cat = strtolower($ad['advertiser_category']);
                if (isset($advertiserCategories[$cat])) continue;
                $advertiserCategories[$cat] = true;
            }

            $pod[] = $ad;
            $totalDuration += $adDuration;
        }

        return [
            'pod_id' => $podConfig['id'] ?? null,
            'ads' => $pod,
            'total_duration' => $totalDuration,
            'ad_count' => count($pod),
            'max_ads' => $maxAds,
        ];
    }

    /**
     * Get ad break schedule for VOD content using cue points
     * Returns list of break positions with pod configs
     */
    public function getAdBreakSchedule(string $contentType, int $contentId, float $contentDuration): array
    {
        // Get cue points from content_markers
        $cuePoints = $this->db->fetchAll(
            "SELECT * FROM content_markers
             WHERE content_type = ? AND content_id = ? AND marker_type = 'ad_cue'
             ORDER BY position_seconds",
            [$contentType, $contentId]
        );

        $breaks = [];

        // Add pre-roll break at position 0
        $breaks[] = [
            'position' => 0,
            'type' => 'pre_roll',
            'label' => 'Pre-Roll',
            'source' => 'auto',
        ];

        // Add mid-roll breaks at each cue point
        foreach ($cuePoints as $cue) {
            $pos = (float) $cue['position_seconds'];
            if ($pos > 0 && $pos < $contentDuration - 30) {
                $breaks[] = [
                    'position' => $pos,
                    'type' => 'mid_roll',
                    'label' => $cue['label'] ?: 'Ad Break',
                    'source' => 'cue_point',
                    'marker_id' => $cue['id'],
                ];
            }
        }

        // If no cue points, use percentage/time-based mid-rolls from active creatives
        if (empty($cuePoints) && $contentDuration >= 600) {
            $midRolls = $this->db->fetchAll(
                "SELECT DISTINCT ac.midroll_offset_type, ac.midroll_offset_value
                 FROM ad_creatives ac
                 JOIN ad_campaigns camp ON ac.campaign_id = camp.id
                 WHERE ac.type = 'mid_roll' AND ac.status = 'active'
                   AND camp.status = 'active'
                   AND ac.midroll_offset_type IN ('seconds','percent')
                 ORDER BY ac.midroll_offset_value"
            );

            $usedPositions = [];
            foreach ($midRolls as $mr) {
                $pos = $mr['midroll_offset_type'] === 'percent'
                    ? ($contentDuration * (float) $mr['midroll_offset_value'] / 100)
                    : (float) $mr['midroll_offset_value'];

                // Ensure min 5min gap between breaks
                $tooClose = false;
                foreach ($usedPositions as $used) {
                    if (abs($pos - $used) < 300) {
                        $tooClose = true;
                        break;
                    }
                }

                if (!$tooClose && $pos > 30 && $pos < $contentDuration - 30) {
                    $breaks[] = [
                        'position' => round($pos, 1),
                        'type' => 'mid_roll',
                        'label' => 'Ad Break',
                        'source' => 'auto_' . $mr['midroll_offset_type'],
                    ];
                    $usedPositions[] = $pos;
                }
            }
        }

        usort($breaks, fn($a, $b) => $a['position'] <=> $b['position']);

        return $breaks;
    }

    // =========================================
    // Conversion Tracking
    // =========================================

    public function getConversionGoals(int $campaignId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM ad_conversion_goals WHERE campaign_id = ? ORDER BY name",
            [$campaignId]
        );
    }

    public function createConversionGoal(int $campaignId, array $data): int
    {
        $this->db->execute(
            "INSERT INTO ad_conversion_goals (campaign_id, name, goal_type, tracking_url, pixel_code, value, attribution_window_hours, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $campaignId,
                $data['name'],
                $data['goal_type'] ?? 'page_visit',
                $data['tracking_url'] ?? null,
                $data['pixel_code'] ?? null,
                !empty($data['value']) ? (float) $data['value'] : null,
                $data['attribution_window_hours'] ?? 720,
                $data['is_active'] ?? 1,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    public function updateConversionGoal(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        foreach (['name', 'goal_type', 'tracking_url', 'pixel_code', 'value', 'attribution_window_hours', 'is_active'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = ?";
                $params[] = $data[$f] === '' ? null : $data[$f];
            }
        }
        if (empty($fields)) return false;
        $params[] = $id;
        $this->db->execute("UPDATE ad_conversion_goals SET " . implode(', ', $fields) . " WHERE id = ?", $params);
        return true;
    }

    public function deleteConversionGoal(int $id): bool
    {
        $this->db->execute("DELETE FROM ad_conversion_goals WHERE id = ?", [$id]);
        return true;
    }

    /**
     * Record a conversion — find the last ad interaction within attribution window
     */
    public function recordConversion(string $pixelCode, array $data): ?int
    {
        $goal = $this->db->fetch(
            "SELECT * FROM ad_conversion_goals WHERE pixel_code = ? AND is_active = 1",
            [$pixelCode]
        );
        if (!$goal) return null;

        $windowHours = (int) $goal['attribution_window_hours'];
        $userId = $data['user_id'] ?? null;
        $sessionId = $data['session_id'] ?? null;

        // Find last-click attribution
        $lastClick = null;
        if ($userId) {
            $lastClick = $this->db->fetch(
                "SELECT ae.*, ai.campaign_id as imp_campaign_id, ai.creative_id as imp_creative_id
                 FROM ad_events ae
                 JOIN ad_impressions ai ON ae.impression_id = ai.id
                 WHERE ae.event_type = 'click'
                   AND ae.user_id = ?
                   AND ae.created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                 ORDER BY ae.created_at DESC LIMIT 1",
                [$userId, $windowHours]
            );
        }

        // Fallback: last-view attribution
        $lastView = null;
        if (!$lastClick && $userId) {
            $lastView = $this->db->fetch(
                "SELECT * FROM ad_impressions
                 WHERE user_id = ? AND campaign_id = ?
                   AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                 ORDER BY created_at DESC LIMIT 1",
                [$userId, $goal['campaign_id'], $windowHours]
            );
        }

        $this->db->execute(
            "INSERT INTO ad_conversions (goal_id, campaign_id, creative_id, impression_id, click_event_id,
             user_id, session_id, ip_address, conversion_value, attribution_type, metadata)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $goal['id'],
                $goal['campaign_id'],
                $lastClick ? ($lastClick['imp_creative_id'] ?? null) : ($lastView['creative_id'] ?? null),
                $lastView['id'] ?? ($lastClick['impression_id'] ?? null),
                $lastClick['id'] ?? null,
                $userId,
                $sessionId,
                $data['ip_address'] ?? null,
                $data['conversion_value'] ?? $goal['value'],
                $lastClick ? 'last_click' : ($lastView ? 'last_view' : 'last_click'),
                !empty($data['metadata']) ? json_encode($data['metadata']) : null,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function getConversionStats(int $campaignId): array
    {
        $stats = $this->db->fetch(
            "SELECT COUNT(*) as total_conversions,
                    SUM(conversion_value) as total_value,
                    COUNT(DISTINCT user_id) as unique_converters
             FROM ad_conversions WHERE campaign_id = ?",
            [$campaignId]
        );

        $byGoal = $this->db->fetchAll(
            "SELECT cg.name, cg.goal_type, COUNT(c.id) as conversions, SUM(c.conversion_value) as value
             FROM ad_conversion_goals cg
             LEFT JOIN ad_conversions c ON c.goal_id = cg.id
             WHERE cg.campaign_id = ?
             GROUP BY cg.id
             ORDER BY conversions DESC",
            [$campaignId]
        );

        $byAttribution = $this->db->fetchAll(
            "SELECT attribution_type, COUNT(*) as count, SUM(conversion_value) as value
             FROM ad_conversions WHERE campaign_id = ?
             GROUP BY attribution_type",
            [$campaignId]
        );

        return [
            'summary' => $stats,
            'by_goal' => $byGoal,
            'by_attribution' => $byAttribution,
        ];
    }

    // =========================================
    // Companion Ad Sync
    // =========================================

    /**
     * Get companion ads for a video creative
     * Returns linked banner creatives to display alongside video ads
     */
    public function getCompanionAds(int $creativeId): array
    {
        $creative = $this->getCreative($creativeId);
        if (!$creative) return [];

        $companions = [];

        // Check companion_creative_id (explicit link)
        if (!empty($creative['companion_creative_id'])) {
            $companion = $this->getCreative((int) $creative['companion_creative_id']);
            if ($companion && $companion['type'] === 'banner') {
                $companions[] = $companion;
            }
        }

        // Check companion_banner_url (legacy simple URL)
        if (empty($companions) && !empty($creative['companion_banner_url'])) {
            $companions[] = [
                'type' => 'banner',
                'image_url' => $creative['companion_banner_url'],
                'banner_position' => 'overlay_bottom',
                'click_url' => $creative['click_url'],
                'click_target' => $creative['click_target'] ?? '_blank',
            ];
        }

        return $companions;
    }

    // =========================================
    // Dayparting
    // =========================================

    public function getDaypartSchedules(int $campaignId): array
    {
        $schedules = $this->db->fetchAll(
            "SELECT * FROM ad_daypart_schedules WHERE campaign_id = ? ORDER BY name",
            [$campaignId]
        );
        foreach ($schedules as &$s) {
            $s['schedule_grid'] = json_decode($s['schedule_grid'], true);
        }
        return $schedules;
    }

    public function createDaypartSchedule(int $campaignId, array $data): int
    {
        $grid = is_array($data['schedule_grid']) ? json_encode($data['schedule_grid']) : $data['schedule_grid'];
        $this->db->execute(
            "INSERT INTO ad_daypart_schedules (campaign_id, name, priority_multiplier, budget_multiplier, schedule_grid, is_active)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $campaignId,
                $data['name'] ?? 'Default Schedule',
                $data['priority_multiplier'] ?? 1.00,
                $data['budget_multiplier'] ?? 1.00,
                $grid,
                $data['is_active'] ?? 1,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    public function updateDaypartSchedule(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        foreach (['name', 'priority_multiplier', 'budget_multiplier', 'is_active'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = ?";
                $params[] = $data[$f];
            }
        }
        if (isset($data['schedule_grid'])) {
            $fields[] = "schedule_grid = ?";
            $params[] = is_array($data['schedule_grid']) ? json_encode($data['schedule_grid']) : $data['schedule_grid'];
        }
        if (empty($fields)) return false;
        $params[] = $id;
        $this->db->execute("UPDATE ad_daypart_schedules SET " . implode(', ', $fields) . " WHERE id = ?", $params);
        return true;
    }

    public function deleteDaypartSchedule(int $id): bool
    {
        $this->db->execute("DELETE FROM ad_daypart_schedules WHERE id = ?", [$id]);
        return true;
    }

    /**
     * Check if current time matches a campaign's daypart schedule
     * Returns priority/budget multipliers if active, null if blocked
     */
    public function checkDaypartSchedule(int $campaignId): ?array
    {
        $schedules = $this->db->fetchAll(
            "SELECT * FROM ad_daypart_schedules WHERE campaign_id = ? AND is_active = 1",
            [$campaignId]
        );
        if (empty($schedules)) return ['priority_multiplier' => 1.0, 'budget_multiplier' => 1.0];

        $dayMap = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $dow = $dayMap[(int) date('N') - 1];
        $hour = (int) date('G');

        foreach ($schedules as $schedule) {
            $grid = json_decode($schedule['schedule_grid'], true);
            if (!$grid || !isset($grid[$dow])) continue;

            $hourSlots = $grid[$dow];
            if (isset($hourSlots[$hour]) && $hourSlots[$hour]) {
                return [
                    'priority_multiplier' => (float) $schedule['priority_multiplier'],
                    'budget_multiplier' => (float) $schedule['budget_multiplier'],
                ];
            }
        }

        // No schedule matches = blocked
        return null;
    }

    // =========================================
    // Floor Prices (RTB Lite)
    // =========================================

    public function updateZoneFloorPrice(int $zoneId, ?float $floorCpm, ?string $fallbackVastUrl): bool
    {
        $this->db->execute(
            "UPDATE ad_zones SET floor_cpm = ?, fallback_vast_url = ? WHERE id = ?",
            [$floorCpm, $fallbackVastUrl ?: null, $zoneId]
        );
        return true;
    }

    /**
     * Enhanced ad serving with waterfall, dayparting, floor prices, A/B testing, and pods
     */
    public function serveAdsEnhanced(array $context): array
    {
        $zoneType = $context['zone_type'] ?? null;
        $zoneSlug = $context['zone_slug'] ?? null;

        // Look up zone info
        $zone = null;
        if ($zoneSlug) {
            $zone = $this->db->fetch("SELECT * FROM ad_zones WHERE slug = ? AND is_active = 1", [$zoneSlug]);
        } elseif ($zoneType) {
            $zone = $this->db->fetch("SELECT * FROM ad_zones WHERE zone_type = ? AND is_active = 1 LIMIT 1", [$zoneType]);
        }

        // Try direct fill first
        $ads = $this->getAdsForContextEnhanced($context);

        // Check floor price
        if ($zone && !empty($zone['floor_cpm'])) {
            // Filter out ads below floor (simplified — in production you'd have actual CPM bids)
            // For now, all direct-sold ads pass the floor
        }

        // If no direct fill, try waterfall
        if (empty($ads) && $zone) {
            $waterfallResult = $this->executeWaterfall($zone['id'], $context);
            if (!empty($waterfallResult['ads'])) {
                $ads = $waterfallResult['ads'];
            } elseif (!empty($waterfallResult['vast_url'])) {
                return [
                    'source' => 'waterfall_vast',
                    'vast_url' => $waterfallResult['vast_url'],
                    'ads' => [],
                ];
            }
        }

        // If still empty, try zone fallback VAST
        if (empty($ads) && $zone && !empty($zone['fallback_vast_url'])) {
            return [
                'source' => 'fallback_vast',
                'vast_url' => $zone['fallback_vast_url'],
                'ads' => [],
            ];
        }

        // Build pod if video ad type
        if (in_array($zoneType, ['pre_roll', 'mid_roll']) && !empty($ads)) {
            $podContext = array_merge($context, ['zone_id' => $zone['id'] ?? null]);
            $pod = $this->buildAdPod($podContext);
            if (!empty($pod['ads'])) {
                return [
                    'source' => 'direct',
                    'pod' => $pod,
                    'ads' => $pod['ads'],
                ];
            }
        }

        return [
            'source' => 'direct',
            'ads' => $ads,
        ];
    }

    /**
     * Enhanced getAdsForContext with dayparting and A/B test support
     */
    private function getAdsForContextEnhanced(array $context): array
    {
        $ads = $this->getAdsForContext($context);

        // Apply dayparting filter
        $filtered = [];
        foreach ($ads as $ad) {
            $daypart = $this->checkDaypartSchedule((int) $ad['campaign_id']);
            if ($daypart === null) continue; // Blocked by daypart

            // Apply priority multiplier
            $ad['effective_priority'] = (int) $ad['campaign_priority'] * $daypart['priority_multiplier'];
            $filtered[] = $ad;
        }

        // Re-sort by effective priority
        usort($filtered, fn($a, $b) => ($a['effective_priority'] ?? 99) <=> ($b['effective_priority'] ?? 99));

        // Apply A/B test variant selection
        foreach ($filtered as &$ad) {
            if (!empty($ad['ab_test_id'])) {
                $variant = $this->pickAbTestVariant((int) $ad['ab_test_id']);
                if ($variant) {
                    $ad['ab_variant_id'] = $variant['id'];
                    // Swap creative if variant selects a different one
                    if ($variant['creative_id'] != $ad['id']) {
                        $altCreative = $this->getCreative((int) $variant['creative_id']);
                        if ($altCreative) {
                            $ad = array_merge($ad, $altCreative);
                            $ad['ab_variant_id'] = $variant['id'];
                        }
                    }
                }
            }
        }

        return $filtered;
    }

    // =========================================
    // Revenue Forecasting
    // =========================================

    /**
     * Get historical ad performance data for AI forecasting
     */
    public function getHistoricalPerformance(int $days = 90): array
    {
        $daily = $this->db->fetchAll(
            "SELECT DATE(created_at) as date,
                    COUNT(*) as impressions,
                    SUM(revenue) as revenue
             FROM ad_impressions
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(created_at)
             ORDER BY date",
            [$days]
        );

        $byZoneType = $this->db->fetchAll(
            "SELECT az.zone_type,
                    COUNT(*) as impressions,
                    SUM(ai.revenue) as revenue
             FROM ad_impressions ai
             JOIN ad_zones az ON ai.zone_id = az.id
             WHERE ai.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY az.zone_type",
            [$days]
        );

        $activeCampaigns = (int) $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM ad_campaigns WHERE status = 'active'"
        )['cnt'];

        $fillRate = $this->db->fetch(
            "SELECT
                (SELECT COUNT(*) FROM ad_impressions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as filled,
                0 as requests"
        );

        return [
            'daily' => $daily,
            'by_zone_type' => $byZoneType,
            'active_campaigns' => $activeCampaigns,
            'avg_daily_impressions' => count($daily) > 0 ? round(array_sum(array_column($daily, 'impressions')) / count($daily)) : 0,
            'avg_daily_revenue' => count($daily) > 0 ? round(array_sum(array_column($daily, 'revenue')) / count($daily), 2) : 0,
        ];
    }

    /**
     * Save AI-generated forecast
     */
    public function saveForecast(array $data): int
    {
        // Upsert by date + zone_type
        $existing = $this->db->fetch(
            "SELECT id FROM ad_revenue_forecasts WHERE forecast_date = ? AND zone_type <=> ?",
            [$data['forecast_date'], $data['zone_type'] ?? null]
        );

        if ($existing) {
            $this->db->execute(
                "UPDATE ad_revenue_forecasts SET predicted_impressions = ?, predicted_revenue = ?,
                 predicted_fill_rate = ?, confidence_level = ?, ai_analysis = ?, model_version = ?
                 WHERE id = ?",
                [
                    $data['predicted_impressions'] ?? 0,
                    $data['predicted_revenue'] ?? 0,
                    $data['predicted_fill_rate'] ?? null,
                    $data['confidence_level'] ?? null,
                    $data['ai_analysis'] ?? null,
                    $data['model_version'] ?? null,
                    $existing['id'],
                ]
            );
            return (int) $existing['id'];
        }

        $this->db->execute(
            "INSERT INTO ad_revenue_forecasts (forecast_date, zone_type, predicted_impressions, predicted_revenue,
             predicted_fill_rate, confidence_level, ai_analysis, model_version)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['forecast_date'],
                $data['zone_type'] ?? null,
                $data['predicted_impressions'] ?? 0,
                $data['predicted_revenue'] ?? 0,
                $data['predicted_fill_rate'] ?? null,
                $data['confidence_level'] ?? null,
                $data['ai_analysis'] ?? null,
                $data['model_version'] ?? null,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    public function getForecasts(int $days = 30): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM ad_revenue_forecasts
             WHERE forecast_date >= CURDATE() AND forecast_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY forecast_date, zone_type",
            [$days]
        );
    }

    /**
     * Update forecast actuals for past dates
     */
    public function updateForecastActuals(): void
    {
        $forecasts = $this->db->fetchAll(
            "SELECT id, forecast_date, zone_type FROM ad_revenue_forecasts
             WHERE forecast_date < CURDATE() AND actual_impressions IS NULL"
        );

        foreach ($forecasts as $f) {
            $actual = $this->db->fetch(
                "SELECT COUNT(*) as impressions, COALESCE(SUM(revenue), 0) as revenue
                 FROM ad_impressions ai
                 " . ($f['zone_type'] ? "JOIN ad_zones az ON ai.zone_id = az.id AND az.zone_type = ?" : "") . "
                 WHERE DATE(ai.created_at) = ?",
                $f['zone_type'] ? [$f['zone_type'], $f['forecast_date']] : [$f['forecast_date']]
            );

            if ($actual) {
                $this->db->execute(
                    "UPDATE ad_revenue_forecasts SET actual_impressions = ?, actual_revenue = ? WHERE id = ?",
                    [$actual['impressions'], $actual['revenue'], $f['id']]
                );
            }
        }
    }
}
