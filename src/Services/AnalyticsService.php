<?php
/**
 * CARI-IPTV Analytics Service
 * Collects and queries behavioral events for the recommendation engine.
 */

namespace CariIPTV\Services;

use CariIPTV\Core\Database;

class AnalyticsService
{
    private Database $db;

    // Valid event types grouped by category
    private const EVENT_TYPES = [
        'watch' => [
            'watch_start',      // Started watching content
            'watch_complete',   // Finished watching (>90%)
            'watch_abandon',    // Stopped watching (< 50%)
            'watch_pause',      // Paused playback
            'watch_resume',     // Resumed playback
            'watch_seek',       // Seeked within content
            'watch_rewind',     // Rewound (replayed a section)
            'binge_session',    // Watched multiple episodes consecutively
            'episode_nav',      // Episode navigation (auto_play / manual_next / episode_select)
        ],
        'navigation' => [
            'page_view',        // Visited a page
            'page_exit',        // Left a page (with dwell time in metadata)
            'search',           // Performed a search
            'search_no_results',// Search with zero results
        ],
        'interaction' => [
            'detail_view',      // Opened detail modal/page
            'detail_dismiss',   // Closed detail without playing
            'card_hover',       // Hovered over a content card (batched)
            'watchlist_add',    // Added to watchlist
            'watchlist_remove', // Removed from watchlist
            'rating',           // Rated content
            'trailer_view',     // Watched a trailer
            'share',            // Shared content
        ],
        'preference' => [
            'skip_intro',       // Skipped intro
            'skip_credits',     // Skipped credits
            'cc_toggle',        // Toggled closed captions
            'language_select',  // Selected audio/subtitle language
            'fullscreen_enter', // Entered fullscreen
        ],
        'session' => [
            'session_start',    // Session started (includes device info)
            'session_end',      // Session ended
        ],
    ];

    // Valid QoE event types
    private const QOE_TYPES = [
        'startup',          // Playback startup latency
        'buffer_start',     // Buffering began
        'buffer_end',       // Buffering ended
        'playback_error',   // Playback error occurred
        'quality_switch',   // Rendition/quality changed
        'quality_report',   // Periodic quality metrics
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Record a single behavioral event
     */
    public function recordEvent(
        int $subscriberId,
        string $eventType,
        ?string $contentType = null,
        ?int $contentId = null,
        ?string $page = null,
        ?array $metadata = null,
        ?string $sessionId = null,
        ?string $platform = null
    ): bool {
        $category = $this->getCategoryForEvent($eventType);
        if (!$category) {
            return false; // Unknown event type
        }

        $this->db->execute(
            "INSERT INTO subscriber_events
                (subscriber_id, event_type, event_category, content_type, content_id, page, metadata, session_id, platform, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $subscriberId,
                $eventType,
                $category,
                $contentType,
                $contentId,
                $page,
                $metadata ? json_encode($metadata) : null,
                $sessionId,
                $platform,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );

        return true;
    }

    /**
     * Record a batch of events (for buffered client sends)
     */
    public function recordBatch(int $subscriberId, array $events, ?string $sessionId = null, ?string $platform = null): int
    {
        $recorded = 0;
        foreach ($events as $event) {
            $eventType = $event['event_type'] ?? '';
            $sid = $sessionId ?? ($event['session_id'] ?? null);
            $plat = $platform ?? ($event['platform'] ?? null);

            // Handle session tracking side-effects
            if ($eventType === 'session_start' && $sid) {
                $this->upsertSession($subscriberId, $sid, $plat, $event['metadata'] ?? null);
            } elseif ($eventType === 'session_end' && $sid) {
                $this->finalizeSession($sid);
            }

            $success = $this->recordEvent(
                $subscriberId,
                $eventType,
                $event['content_type'] ?? null,
                isset($event['content_id']) ? (int) $event['content_id'] : null,
                $event['page'] ?? null,
                $event['metadata'] ?? null,
                $sid,
                $plat
            );
            if ($success) $recorded++;
        }
        return $recorded;
    }

    /**
     * Get viewing stats for a subscriber (used for profile generation)
     */
    public function getSubscriberStats(int $subscriberId): array
    {
        // Total watch time
        $watchTime = $this->db->fetch(
            "SELECT SUM(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.duration'))) as total_seconds
             FROM subscriber_events
             WHERE subscriber_id = ? AND event_type = 'watch_complete'",
            [$subscriberId]
        );

        // Genre breakdown from watched content
        $genres = $this->db->fetchAll(
            "SELECT c.name as genre, COUNT(*) as watch_count
             FROM subscriber_events e
             JOIN movies m ON e.content_type = 'movie' AND e.content_id = m.id
             JOIN categories c ON m.category_id = c.id
             WHERE e.subscriber_id = ? AND e.event_type IN ('watch_start', 'watch_complete')
             GROUP BY c.id, c.name
             ORDER BY watch_count DESC
             LIMIT 20",
            [$subscriberId]
        );

        // Series genres
        $seriesGenres = $this->db->fetchAll(
            "SELECT c.name as genre, COUNT(*) as watch_count
             FROM subscriber_events e
             JOIN series s ON e.content_type IN ('series', 'episode') AND e.content_id = s.id
             JOIN categories c ON s.category_id = c.id
             WHERE e.subscriber_id = ? AND e.event_type IN ('watch_start', 'watch_complete')
             GROUP BY c.id, c.name
             ORDER BY watch_count DESC
             LIMIT 20",
            [$subscriberId]
        );

        // Viewing time patterns (hour of day)
        $timePatterns = $this->db->fetchAll(
            "SELECT HOUR(created_at) as hour, COUNT(*) as event_count
             FROM subscriber_events
             WHERE subscriber_id = ? AND event_type = 'watch_start'
               AND created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
             GROUP BY HOUR(created_at)
             ORDER BY event_count DESC",
            [$subscriberId]
        );

        // Day of week patterns
        $dayPatterns = $this->db->fetchAll(
            "SELECT DAYOFWEEK(created_at) as day_of_week, COUNT(*) as event_count
             FROM subscriber_events
             WHERE subscriber_id = ? AND event_type = 'watch_start'
               AND created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
             GROUP BY DAYOFWEEK(created_at)
             ORDER BY event_count DESC",
            [$subscriberId]
        );

        // Most watched actors (from movie_cast)
        $actors = $this->db->fetchAll(
            "SELECT mc.name, mc.tmdb_person_id, COUNT(*) as appearances
             FROM subscriber_events e
             JOIN movie_cast mc ON e.content_type = 'movie' AND mc.movie_id = e.content_id AND mc.role = 'actor'
             WHERE e.subscriber_id = ? AND e.event_type IN ('watch_start', 'watch_complete')
             GROUP BY mc.name, mc.tmdb_person_id
             ORDER BY appearances DESC
             LIMIT 20",
            [$subscriberId]
        );

        // Content completion rate
        $completionRate = $this->db->fetch(
            "SELECT
                COUNT(CASE WHEN event_type = 'watch_complete' THEN 1 END) as completed,
                COUNT(CASE WHEN event_type = 'watch_start' THEN 1 END) as started
             FROM subscriber_events
             WHERE subscriber_id = ? AND event_type IN ('watch_start', 'watch_complete')
               AND created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)",
            [$subscriberId]
        );

        // Average session length (page views per session)
        $sessionStats = $this->db->fetch(
            "SELECT COUNT(DISTINCT session_id) as sessions, COUNT(*) as total_events
             FROM subscriber_events
             WHERE subscriber_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$subscriberId]
        );

        // Recent searches
        $searches = $this->db->fetchAll(
            "SELECT JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.query')) as query, created_at
             FROM subscriber_events
             WHERE subscriber_id = ? AND event_type IN ('search', 'search_no_results')
             ORDER BY created_at DESC
             LIMIT 20",
            [$subscriberId]
        );

        return [
            'total_watch_seconds' => (int) ($watchTime['total_seconds'] ?? 0),
            'genres' => array_merge($genres, $seriesGenres),
            'time_patterns' => $timePatterns,
            'day_patterns' => $dayPatterns,
            'favorite_actors' => $actors,
            'completion_rate' => $completionRate,
            'session_stats' => $sessionStats,
            'recent_searches' => $searches,
        ];
    }

    /**
     * Get trending content based on recent viewing activity
     */
    public function computeTrending(?string $region = null): array
    {
        // Movies trending in last 24 hours
        $movies = $this->db->fetchAll(
            "SELECT e.content_id, COUNT(*) as views_24h,
                    m.title, m.poster_url, m.vote_average, m.year
             FROM subscriber_events e
             JOIN movies m ON e.content_id = m.id AND m.status = 'published'
             WHERE e.event_type IN ('watch_start', 'watch_complete')
               AND e.content_type = 'movie'
               AND e.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY e.content_id, m.title, m.poster_url, m.vote_average, m.year
             ORDER BY views_24h DESC
             LIMIT 20"
        );

        // Series trending
        $series = $this->db->fetchAll(
            "SELECT e.content_id, COUNT(*) as views_24h,
                    s.title, s.poster_url, s.vote_average, s.year
             FROM subscriber_events e
             JOIN series s ON e.content_id = s.id AND s.status = 'published'
             WHERE e.event_type IN ('watch_start', 'watch_complete')
               AND e.content_type IN ('series', 'episode')
               AND e.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY e.content_id, s.title, s.poster_url, s.vote_average, s.year
             ORDER BY views_24h DESC
             LIMIT 20"
        );

        return ['movies' => $movies, 'series' => $series];
    }

    /**
     * Prune old raw events (keep aggregated stats, remove raw events older than retention period)
     */
    public function pruneEvents(int $retentionDays = 90): int
    {
        $result = $this->db->execute(
            "DELETE FROM subscriber_events WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$retentionDays]
        );
        return $result;
    }

    /**
     * Get valid event types list (for validation)
     */
    public function getValidEventTypes(): array
    {
        return self::EVENT_TYPES;
    }

    /**
     * Look up category for an event type
     */
    private function getCategoryForEvent(string $eventType): ?string
    {
        foreach (self::EVENT_TYPES as $category => $types) {
            if (in_array($eventType, $types, true)) {
                return $category;
            }
        }
        return null;
    }

    // =========================================================================
    // QoE (Quality of Experience)
    // =========================================================================

    /**
     * Record a batch of QoE events
     */
    public function recordQoeBatch(int $subscriberId, array $events, ?string $sessionId = null, ?string $platform = null): int
    {
        $recorded = 0;
        foreach ($events as $event) {
            $type = $event['event_type'] ?? '';
            if (!in_array($type, self::QOE_TYPES, true)) continue;

            $this->db->execute(
                "INSERT INTO subscriber_qoe_events
                    (subscriber_id, content_type, content_id, event_type, metadata, session_id, platform)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $subscriberId,
                    $event['content_type'] ?? null,
                    isset($event['content_id']) ? (int) $event['content_id'] : null,
                    $type,
                    isset($event['metadata']) ? json_encode($event['metadata']) : null,
                    $sessionId,
                    $platform,
                ]
            );
            $recorded++;
        }
        return $recorded;
    }

    /**
     * Get QoE summary stats for dashboard (last 30 days)
     */
    public function getQoeStats(): array
    {
        // Average startup time
        $startup = $this->db->fetch(
            "SELECT AVG(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.startup_ms'))) as avg_startup_ms,
                    COUNT(*) as total_starts,
                    MIN(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.startup_ms'))) as min_startup_ms,
                    MAX(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.startup_ms'))) as max_startup_ms
             FROM subscriber_qoe_events
             WHERE event_type = 'startup' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Buffering stats
        $buffering = $this->db->fetch(
            "SELECT COUNT(*) as buffer_events,
                    AVG(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.buffer_duration_ms'))) as avg_buffer_ms,
                    COUNT(DISTINCT subscriber_id) as affected_users,
                    COUNT(DISTINCT session_id) as affected_sessions
             FROM subscriber_qoe_events
             WHERE event_type = 'buffer_end' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Error count and breakdown
        $errors = $this->db->fetchAll(
            "SELECT JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.error_code')) as error_code,
                    COUNT(*) as count
             FROM subscriber_qoe_events
             WHERE event_type = 'playback_error' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY error_code
             ORDER BY count DESC
             LIMIT 10"
        );

        $totalErrors = $this->db->fetch(
            "SELECT COUNT(*) as c FROM subscriber_qoe_events
             WHERE event_type = 'playback_error' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Quality distribution (from quality_report events)
        $qualityDist = $this->db->fetchAll(
            "SELECT JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.resolution')) as resolution,
                    COUNT(*) as count
             FROM subscriber_qoe_events
             WHERE event_type = 'quality_report'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND JSON_EXTRACT(metadata, '$.resolution') IS NOT NULL
             GROUP BY resolution
             ORDER BY count DESC
             LIMIT 10"
        );

        // QoE trend (daily buffer rate)
        $qoeTrend = $this->db->fetchAll(
            "SELECT DATE(created_at) as day,
                    SUM(event_type = 'buffer_end') as buffer_events,
                    SUM(event_type = 'playback_error') as error_events,
                    SUM(event_type = 'startup') as startup_events,
                    AVG(CASE WHEN event_type = 'startup'
                        THEN JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.startup_ms'))
                        ELSE NULL END) as avg_startup_ms
             FROM subscriber_qoe_events
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY day ORDER BY day"
        );

        return [
            'startup' => $startup ?: ['avg_startup_ms' => 0, 'total_starts' => 0, 'min_startup_ms' => 0, 'max_startup_ms' => 0],
            'buffering' => $buffering ?: ['buffer_events' => 0, 'avg_buffer_ms' => 0, 'affected_users' => 0, 'affected_sessions' => 0],
            'errors' => $errors,
            'total_errors' => (int) ($totalErrors['c'] ?? 0),
            'quality_distribution' => $qualityDist,
            'trend' => $qoeTrend,
        ];
    }

    // =========================================================================
    // Content Impressions
    // =========================================================================

    /**
     * Record a batch of content impressions
     */
    public function recordImpressions(int $subscriberId, array $impressions, ?string $sessionId = null, ?string $platform = null): int
    {
        $recorded = 0;
        foreach ($impressions as $imp) {
            if (empty($imp['content_type']) || empty($imp['content_id'])) continue;

            $this->db->execute(
                "INSERT INTO content_impressions
                    (subscriber_id, content_type, content_id, section_type, section_id, position, was_clicked, dwell_ms, session_id, platform)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $subscriberId,
                    $imp['content_type'],
                    (int) $imp['content_id'],
                    $imp['section_type'] ?? null,
                    isset($imp['section_id']) ? (int) $imp['section_id'] : null,
                    isset($imp['position']) ? (int) $imp['position'] : null,
                    (int) ($imp['was_clicked'] ?? 0),
                    isset($imp['dwell_ms']) ? (int) $imp['dwell_ms'] : null,
                    $sessionId,
                    $platform,
                ]
            );
            $recorded++;
        }
        return $recorded;
    }

    /**
     * Get impression stats for dashboard (CTR per section type)
     */
    public function getImpressionStats(): array
    {
        // CTR by section type
        $sectionCtr = $this->db->fetchAll(
            "SELECT section_type,
                    COUNT(*) as impressions,
                    SUM(was_clicked) as clicks,
                    ROUND(SUM(was_clicked) / COUNT(*) * 100, 2) as ctr,
                    ROUND(AVG(dwell_ms)) as avg_dwell_ms
             FROM content_impressions
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND section_type IS NOT NULL
             GROUP BY section_type
             ORDER BY impressions DESC"
        );

        // Most seen but not clicked (discovery gap)
        $discoveryGap = $this->db->fetchAll(
            "SELECT ci.content_type, ci.content_id,
                    COALESCE(m.title, s.title, ch.name) as title,
                    COUNT(*) as impressions,
                    SUM(ci.was_clicked) as clicks,
                    ROUND(SUM(ci.was_clicked) / COUNT(*) * 100, 2) as ctr
             FROM content_impressions ci
             LEFT JOIN movies m ON ci.content_type = 'movie' AND ci.content_id = m.id
             LEFT JOIN series s ON ci.content_type = 'series' AND ci.content_id = s.id
             LEFT JOIN channels ch ON ci.content_type = 'channel' AND ci.content_id = ch.id
             WHERE ci.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY ci.content_type, ci.content_id, title
             HAVING impressions >= 10
             ORDER BY ctr ASC, impressions DESC
             LIMIT 10"
        );

        // Position effectiveness (do users click items in position 0 more?)
        $positionCtr = $this->db->fetchAll(
            "SELECT position,
                    COUNT(*) as impressions,
                    SUM(was_clicked) as clicks,
                    ROUND(SUM(was_clicked) / COUNT(*) * 100, 2) as ctr
             FROM content_impressions
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND position IS NOT NULL
             GROUP BY position
             ORDER BY position
             LIMIT 20"
        );

        return [
            'section_ctr' => $sectionCtr,
            'discovery_gap' => $discoveryGap,
            'position_ctr' => $positionCtr,
        ];
    }

    // =========================================================================
    // Content Shares
    // =========================================================================

    /**
     * Record a content share
     */
    public function recordShare(int $subscriberId, string $contentType, int $contentId, ?string $shareMethod = null, ?string $platform = null): bool
    {
        $this->db->execute(
            "INSERT INTO content_shares (subscriber_id, content_type, content_id, share_method, platform) VALUES (?, ?, ?, ?, ?)",
            [$subscriberId, $contentType, $contentId, $shareMethod, $platform]
        );
        return true;
    }

    /**
     * Get sharing stats for dashboard
     */
    public function getShareStats(): array
    {
        $byMethod = $this->db->fetchAll(
            "SELECT share_method, COUNT(*) as count
             FROM content_shares
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY share_method ORDER BY count DESC"
        );

        $topShared = $this->db->fetchAll(
            "SELECT cs.content_type, cs.content_id,
                    COALESCE(m.title, s.title, ch.name) as title,
                    COUNT(*) as shares
             FROM content_shares cs
             LEFT JOIN movies m ON cs.content_type = 'movie' AND cs.content_id = m.id
             LEFT JOIN series s ON cs.content_type = 'series' AND cs.content_id = s.id
             LEFT JOIN channels ch ON cs.content_type = 'channel' AND cs.content_id = ch.id
             WHERE cs.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY cs.content_type, cs.content_id, title
             ORDER BY shares DESC
             LIMIT 10"
        );

        return ['by_method' => $byMethod, 'top_shared' => $topShared];
    }

    // =========================================================================
    // Session Analytics & Device Info
    // =========================================================================

    /**
     * Record or update a session from session_start/session_end events
     */
    public function upsertSession(int $subscriberId, string $sessionId, ?string $platform = null, ?array $deviceMeta = null): void
    {
        // Check if session exists
        $existing = $this->db->fetch(
            "SELECT id FROM subscriber_sessions WHERE session_id = ?",
            [$sessionId]
        );

        if (!$existing) {
            $this->db->execute(
                "INSERT INTO subscriber_sessions
                    (subscriber_id, session_id, platform, device_type, browser, os, screen_resolution, connection_type, ip_address, referrer)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $subscriberId,
                    $sessionId,
                    $platform,
                    $deviceMeta['device_type'] ?? null,
                    $deviceMeta['browser'] ?? null,
                    $deviceMeta['os'] ?? null,
                    $deviceMeta['screen_resolution'] ?? null,
                    $deviceMeta['connection_type'] ?? null,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $deviceMeta['referrer'] ?? null,
                ]
            );
        }
    }

    /**
     * Finalize a session (update end time, counts)
     */
    public function finalizeSession(string $sessionId): void
    {
        $this->db->execute(
            "UPDATE subscriber_sessions SET
                ended_at = NOW(),
                duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW()),
                page_views = (SELECT COUNT(*) FROM subscriber_events WHERE session_id = ? AND event_type = 'page_view'),
                watch_starts = (SELECT COUNT(*) FROM subscriber_events WHERE session_id = ? AND event_type = 'watch_start'),
                search_count = (SELECT COUNT(*) FROM subscriber_events WHERE session_id = ? AND event_type IN ('search','search_no_results')),
                is_bounce = CASE WHEN TIMESTAMPDIFF(SECOND, started_at, NOW()) < 30
                    AND (SELECT COUNT(*) FROM subscriber_events WHERE session_id = ? AND event_type = 'page_view') <= 1
                    THEN 1 ELSE 0 END
             WHERE session_id = ?",
            [$sessionId, $sessionId, $sessionId, $sessionId, $sessionId]
        );
    }

    /**
     * Get session analytics for dashboard
     */
    public function getSessionStats(): array
    {
        // Session summary
        $summary = $this->db->fetch(
            "SELECT COUNT(*) as total_sessions,
                    COUNT(DISTINCT subscriber_id) as unique_users,
                    ROUND(AVG(duration_seconds)) as avg_duration_seconds,
                    ROUND(AVG(page_views), 1) as avg_page_views,
                    ROUND(AVG(watch_starts), 1) as avg_watch_starts,
                    SUM(is_bounce) as bounce_sessions,
                    ROUND(SUM(is_bounce) / NULLIF(COUNT(*), 0) * 100, 1) as bounce_rate
             FROM subscriber_sessions
             WHERE started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Device breakdown
        $devices = $this->db->fetchAll(
            "SELECT COALESCE(device_type, 'unknown') as device_type, COUNT(*) as sessions
             FROM subscriber_sessions
             WHERE started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY device_type ORDER BY sessions DESC"
        );

        // Browser breakdown
        $browsers = $this->db->fetchAll(
            "SELECT COALESCE(browser, 'unknown') as browser, COUNT(*) as sessions
             FROM subscriber_sessions
             WHERE started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY browser ORDER BY sessions DESC
             LIMIT 10"
        );

        // OS breakdown
        $osBreakdown = $this->db->fetchAll(
            "SELECT COALESCE(os, 'unknown') as os, COUNT(*) as sessions
             FROM subscriber_sessions
             WHERE started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY os ORDER BY sessions DESC
             LIMIT 10"
        );

        // Connection type breakdown
        $connections = $this->db->fetchAll(
            "SELECT COALESCE(connection_type, 'unknown') as connection_type, COUNT(*) as sessions
             FROM subscriber_sessions
             WHERE started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY connection_type ORDER BY sessions DESC"
        );

        // Screen resolutions
        $screens = $this->db->fetchAll(
            "SELECT COALESCE(screen_resolution, 'unknown') as resolution, COUNT(*) as sessions
             FROM subscriber_sessions
             WHERE started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY resolution ORDER BY sessions DESC
             LIMIT 10"
        );

        return [
            'summary' => $summary ?: ['total_sessions' => 0, 'unique_users' => 0, 'avg_duration_seconds' => 0, 'avg_page_views' => 0, 'avg_watch_starts' => 0, 'bounce_sessions' => 0, 'bounce_rate' => 0],
            'devices' => $devices,
            'browsers' => $browsers,
            'os' => $osBreakdown,
            'connections' => $connections,
            'screens' => $screens,
        ];
    }

    // =========================================================================
    // Engagement Scores & Churn Prediction
    // =========================================================================

    /**
     * Compute engagement scores for all active subscribers.
     * Call this daily via cron.
     */
    public function computeEngagementScores(): int
    {
        $subscribers = $this->db->fetchAll(
            "SELECT id FROM subscribers WHERE status = 'active'"
        );

        $computed = 0;
        foreach ($subscribers as $sub) {
            $this->computeEngagementForSubscriber((int) $sub['id']);
            $computed++;
        }
        return $computed;
    }

    /**
     * Compute engagement score for a single subscriber
     */
    public function computeEngagementForSubscriber(int $subscriberId): void
    {
        // Session frequency (7d and 30d)
        $sessions = $this->db->fetch(
            "SELECT
                SUM(started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as sessions_7d,
                SUM(started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as sessions_30d
             FROM subscriber_sessions WHERE subscriber_id = ?",
            [$subscriberId]
        );
        $sessions7d = (int) ($sessions['sessions_7d'] ?? 0);
        $sessions30d = (int) ($sessions['sessions_30d'] ?? 0);

        // Watch hours (7d and 30d)
        $watchTime = $this->db->fetch(
            "SELECT
                COALESCE(SUM(CASE WHEN last_watched_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN progress_seconds ELSE 0 END), 0) / 3600 as hours_7d,
                COALESCE(SUM(CASE WHEN last_watched_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN progress_seconds ELSE 0 END), 0) / 3600 as hours_30d
             FROM subscriber_watch_history WHERE subscriber_id = ?",
            [$subscriberId]
        );
        $watchHours7d = round((float) ($watchTime['hours_7d'] ?? 0), 2);
        $watchHours30d = round((float) ($watchTime['hours_30d'] ?? 0), 2);

        // Unique titles watched (30d)
        $uniqueTitles = $this->db->fetch(
            "SELECT COUNT(DISTINCT CONCAT(content_type, ':', content_id)) as c
             FROM subscriber_events
             WHERE subscriber_id = ? AND event_type = 'watch_start'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$subscriberId]
        );
        $uniqueTitles30d = (int) ($uniqueTitles['c'] ?? 0);

        // Days since last activity
        $lastActivity = $this->db->fetch(
            "SELECT MAX(created_at) as last_event FROM subscriber_events WHERE subscriber_id = ?",
            [$subscriberId]
        );
        $daysSince = 999;
        if ($lastActivity && $lastActivity['last_event']) {
            $daysSince = max(0, (int) ((time() - strtotime($lastActivity['last_event'])) / 86400));
        }

        // Features used
        $features = $this->db->fetchAll(
            "SELECT DISTINCT event_type FROM subscriber_events
             WHERE subscriber_id = ? AND event_type IN ('search','watchlist_add','rating','share','cc_toggle','skip_intro')
               AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
            [$subscriberId]
        );
        $featureMap = ['search' => 'search', 'watchlist_add' => 'watchlist', 'rating' => 'rating', 'share' => 'share', 'cc_toggle' => 'subtitle', 'skip_intro' => 'skip_intro'];
        $featuresUsed = [];
        foreach ($features as $f) {
            $key = $featureMap[$f['event_type']] ?? $f['event_type'];
            if (!in_array($key, $featuresUsed)) $featuresUsed[] = $key;
        }

        // Compute component scores (0-100)
        $frequencyScore = min(100, ($sessions7d / 7) * 100); // Daily visitor = 100
        $depthScore = min(100, ($watchHours7d / 10) * 100); // 10+ hrs/week = 100
        $breadthScore = min(100, ($uniqueTitles30d / 20) * 100); // 20+ titles = 100
        $recencyScore = max(0, 100 - ($daysSince * 10)); // Drops 10 per day of inactivity

        // Composite score (weighted average)
        $score = round(
            $frequencyScore * 0.30 +
            $depthScore * 0.25 +
            $breadthScore * 0.20 +
            $recencyScore * 0.25,
            2
        );

        // Churn risk assessment
        $churnRisk = 'low';
        if ($daysSince >= 30 || $score < 10) {
            $churnRisk = 'critical';
        } elseif ($daysSince >= 14 || $score < 25) {
            $churnRisk = 'high';
        } elseif ($daysSince >= 7 || $score < 50) {
            $churnRisk = 'medium';
        }

        // Upsert engagement score
        $this->db->execute(
            "INSERT INTO subscriber_engagement_scores
                (subscriber_id, score, frequency_score, depth_score, breadth_score, recency_score,
                 churn_risk, days_since_last_activity, sessions_7d, sessions_30d,
                 watch_hours_7d, watch_hours_30d, unique_titles_30d, features_used, computed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                score = VALUES(score), frequency_score = VALUES(frequency_score),
                depth_score = VALUES(depth_score), breadth_score = VALUES(breadth_score),
                recency_score = VALUES(recency_score), churn_risk = VALUES(churn_risk),
                days_since_last_activity = VALUES(days_since_last_activity),
                sessions_7d = VALUES(sessions_7d), sessions_30d = VALUES(sessions_30d),
                watch_hours_7d = VALUES(watch_hours_7d), watch_hours_30d = VALUES(watch_hours_30d),
                unique_titles_30d = VALUES(unique_titles_30d), features_used = VALUES(features_used),
                computed_at = NOW()",
            [
                $subscriberId,
                $score,
                round($frequencyScore, 2),
                round($depthScore, 2),
                round($breadthScore, 2),
                round($recencyScore, 2),
                $churnRisk,
                $daysSince,
                $sessions7d,
                $sessions30d,
                $watchHours7d,
                $watchHours30d,
                $uniqueTitles30d,
                json_encode($featuresUsed),
            ]
        );
    }

    /**
     * Get engagement overview for dashboard
     */
    public function getEngagementOverview(): array
    {
        $distribution = $this->db->fetchAll(
            "SELECT churn_risk, COUNT(*) as count, ROUND(AVG(score), 1) as avg_score
             FROM subscriber_engagement_scores
             GROUP BY churn_risk
             ORDER BY FIELD(churn_risk, 'low', 'medium', 'high', 'critical')"
        );

        $scoreDistribution = $this->db->fetchAll(
            "SELECT
                CASE
                    WHEN score >= 80 THEN 'Power Users (80-100)'
                    WHEN score >= 60 THEN 'Engaged (60-79)'
                    WHEN score >= 40 THEN 'Casual (40-59)'
                    WHEN score >= 20 THEN 'At Risk (20-39)'
                    ELSE 'Inactive (0-19)'
                END as segment,
                COUNT(*) as count,
                ROUND(AVG(score), 1) as avg_score,
                ROUND(AVG(watch_hours_30d), 1) as avg_watch_hours
             FROM subscriber_engagement_scores
             GROUP BY segment
             ORDER BY avg_score DESC"
        );

        $topEngaged = $this->db->fetchAll(
            "SELECT e.subscriber_id, s.username, s.email, e.score, e.sessions_7d,
                    e.watch_hours_7d, e.unique_titles_30d, e.churn_risk
             FROM subscriber_engagement_scores e
             JOIN subscribers s ON e.subscriber_id = s.id
             ORDER BY e.score DESC
             LIMIT 10"
        );

        $atRisk = $this->db->fetchAll(
            "SELECT e.subscriber_id, s.username, s.email, e.score, e.days_since_last_activity,
                    e.sessions_30d, e.watch_hours_30d, e.churn_risk
             FROM subscriber_engagement_scores e
             JOIN subscribers s ON e.subscriber_id = s.id
             WHERE e.churn_risk IN ('high', 'critical')
             ORDER BY e.days_since_last_activity DESC
             LIMIT 15"
        );

        $avgScore = $this->db->fetch(
            "SELECT ROUND(AVG(score), 1) as avg_score FROM subscriber_engagement_scores"
        );

        return [
            'churn_distribution' => $distribution,
            'score_segments' => $scoreDistribution,
            'top_engaged' => $topEngaged,
            'at_risk' => $atRisk,
            'avg_score' => (float) ($avgScore['avg_score'] ?? 0),
        ];
    }

    // =========================================================================
    // Retention Cohorts
    // =========================================================================

    /**
     * Compute retention cohorts. Call monthly via cron.
     */
    public function computeRetentionCohorts(): int
    {
        // Get all monthly cohorts (subscribers grouped by signup month)
        $cohorts = $this->db->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as cohort_month, COUNT(*) as cohort_size
             FROM subscribers
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY cohort_month
             ORDER BY cohort_month"
        );

        $computed = 0;
        foreach ($cohorts as $cohort) {
            $cohortMonth = $cohort['cohort_month'];
            $cohortSize = (int) $cohort['cohort_size'];

            // For each month after signup, check how many are still active
            for ($monthsAfter = 0; $monthsAfter <= 12; $monthsAfter++) {
                $targetMonth = date('Y-m', strtotime($cohortMonth . '-01 +' . $monthsAfter . ' months'));
                if ($targetMonth > date('Y-m')) break; // Don't compute future months

                // Count subscribers from this cohort who had any watch activity in the target month
                $active = $this->db->fetch(
                    "SELECT COUNT(DISTINCT e.subscriber_id) as active_count
                     FROM subscriber_events e
                     JOIN subscribers s ON e.subscriber_id = s.id
                     WHERE DATE_FORMAT(s.created_at, '%Y-%m') = ?
                       AND DATE_FORMAT(e.created_at, '%Y-%m') = ?
                       AND e.event_type = 'watch_start'",
                    [$cohortMonth, $targetMonth]
                );
                $activeCount = (int) ($active['active_count'] ?? 0);
                $retentionRate = $cohortSize > 0 ? round(($activeCount / $cohortSize) * 100, 2) : 0;

                $this->db->execute(
                    "INSERT INTO retention_cohorts
                        (cohort_month, months_after, cohort_size, active_count, retention_rate, computed_at)
                     VALUES (?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE
                        cohort_size = VALUES(cohort_size), active_count = VALUES(active_count),
                        retention_rate = VALUES(retention_rate), computed_at = NOW()",
                    [$cohortMonth, $monthsAfter, $cohortSize, $activeCount, $retentionRate]
                );
                $computed++;
            }
        }
        return $computed;
    }

    /**
     * Get retention cohort data for dashboard
     */
    public function getRetentionCohorts(): array
    {
        return $this->db->fetchAll(
            "SELECT cohort_month, months_after, cohort_size, active_count, retention_rate
             FROM retention_cohorts
             WHERE cohort_month >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 12 MONTH), '%Y-%m')
             ORDER BY cohort_month, months_after"
        );
    }

    // =========================================================================
    // Binge Sessions
    // =========================================================================

    /**
     * Get binge watching stats for dashboard
     */
    public function getBingeStats(): array
    {
        // From binge_session events in subscriber_events
        $bingeEvents = $this->db->fetchAll(
            "SELECT s.title as series_title,
                    COUNT(*) as binge_count,
                    AVG(JSON_UNQUOTE(JSON_EXTRACT(e.metadata, '$.episodes_watched'))) as avg_episodes,
                    AVG(JSON_UNQUOTE(JSON_EXTRACT(e.metadata, '$.duration_minutes'))) as avg_duration_min
             FROM subscriber_events e
             JOIN series s ON e.content_id = s.id
             WHERE e.event_type = 'binge_session'
               AND e.content_type = 'series'
               AND e.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY s.id, s.title
             ORDER BY binge_count DESC
             LIMIT 10"
        );

        $bingeSummary = $this->db->fetch(
            "SELECT COUNT(*) as total_binges,
                    COUNT(DISTINCT subscriber_id) as unique_bingers,
                    AVG(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.episodes_watched'))) as avg_episodes_per_binge,
                    AVG(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.duration_minutes'))) as avg_binge_duration_min
             FROM subscriber_events
             WHERE event_type = 'binge_session'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        return [
            'top_binged_series' => $bingeEvents,
            'summary' => $bingeSummary ?: ['total_binges' => 0, 'unique_bingers' => 0, 'avg_episodes_per_binge' => 0, 'avg_binge_duration_min' => 0],
        ];
    }

    // =========================================================================
    // Concurrent Viewers (Real-time estimate)
    // =========================================================================

    /**
     * Get current concurrent viewers (viewers active in last 5 minutes)
     */
    public function getConcurrentViewers(): array
    {
        $total = $this->db->fetch(
            "SELECT COUNT(DISTINCT subscriber_id) as concurrent
             FROM subscriber_events
             WHERE event_type = 'watch_start'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
        );

        $byType = $this->db->fetchAll(
            "SELECT content_type, COUNT(DISTINCT subscriber_id) as concurrent
             FROM subscriber_events
             WHERE event_type = 'watch_start'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
             GROUP BY content_type"
        );

        return [
            'total' => (int) ($total['concurrent'] ?? 0),
            'by_type' => $byType,
        ];
    }

    // =========================================================================
    // Pruning for new tables
    // =========================================================================

    /**
     * Prune old QoE events
     */
    public function pruneQoeEvents(int $retentionDays = 30): int
    {
        return $this->db->execute(
            "DELETE FROM subscriber_qoe_events WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$retentionDays]
        );
    }

    /**
     * Prune old impressions
     */
    public function pruneImpressions(int $retentionDays = 60): int
    {
        return $this->db->execute(
            "DELETE FROM content_impressions WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$retentionDays]
        );
    }

    /**
     * Prune old sessions
     */
    public function pruneSessions(int $retentionDays = 90): int
    {
        return $this->db->execute(
            "DELETE FROM subscriber_sessions WHERE started_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$retentionDays]
        );
    }
}
