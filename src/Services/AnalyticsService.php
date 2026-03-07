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
            $success = $this->recordEvent(
                $subscriberId,
                $event['event_type'] ?? '',
                $event['content_type'] ?? null,
                isset($event['content_id']) ? (int) $event['content_id'] : null,
                $event['page'] ?? null,
                $event['metadata'] ?? null,
                $sessionId ?? ($event['session_id'] ?? null),
                $platform ?? ($event['platform'] ?? null)
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
}
