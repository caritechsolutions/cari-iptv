<?php
/**
 * CARI-IPTV Analytics Controller
 * AI-powered business intelligence dashboard with interactive chat.
 */

namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Database;
use CariIPTV\Core\Response;
use CariIPTV\Core\Session;
use CariIPTV\Services\AdminAuthService;
use CariIPTV\Services\AIService;
use CariIPTV\Services\AnalyticsService;

class AnalyticsController
{
    private AdminAuthService $auth;
    private Database $db;

    public function __construct()
    {
        $this->auth = new AdminAuthService();
        $this->db = Database::getInstance();
    }

    /**
     * Show the analytics dashboard page
     */
    public function index(): void
    {
        $user = $this->auth->user();

        // Check AI availability
        $aiService = new AIService();
        $aiStatus = $aiService->getStatus();

        Response::view('admin/analytics/index', [
            'pageTitle' => 'Analytics',
            'user' => $user,
            'csrf' => Session::csrf(),
            'aiStatus' => $aiStatus,
        ], 'admin');
    }

    /**
     * Gather all platform data and return as JSON (for client-side display)
     */
    public function getData(): void
    {
        try {
            $data = $this->gatherPlatformData();
            Response::json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            error_log('[Analytics] getData() failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            Response::json(['success' => false, 'message' => 'Failed to load analytics data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Generate an AI-powered business report from platform data
     */
    public function generateReport(): void
    {
        Session::validateCsrf($_POST['_token'] ?? '');

        $aiService = new AIService();
        if (!$aiService->isAvailable()) {
            Response::json(['success' => false, 'message' => 'AI is not configured. Go to Settings > AI to set up a provider.'], 503);
            return;
        }

        // Extend PHP execution time for AI report generation
        set_time_limit(180);

        $data = $this->gatherPlatformData();
        $focusArea = trim($_POST['focus'] ?? 'general');

        $prompt = $this->buildReportPrompt($data, $focusArea);

        $report = $aiService->complete($prompt, [
            'max_tokens' => 2000,
            'temperature' => 0.6,
            'timeout' => 120,
        ]);

        if (!$report) {
            Response::json([
                'success' => false,
                'message' => 'AI failed to generate report: ' . ($aiService->getLastError() ?? 'Unknown error'),
            ], 500);
            return;
        }

        // Store in session for chat continuity
        $_SESSION['analytics_chat'] = [
            ['role' => 'system', 'content' => $this->buildSystemContext($data)],
            ['role' => 'assistant', 'content' => $report],
        ];
        $_SESSION['analytics_data'] = $data;

        Response::json([
            'success' => true,
            'report' => $report,
            'data' => $data,
        ]);
    }

    /**
     * Chat with AI about the analytics (follow-up questions)
     */
    public function chat(): void
    {
        Session::validateCsrf($_POST['_token'] ?? '');

        $message = trim($_POST['message'] ?? '');
        if (empty($message)) {
            Response::json(['success' => false, 'message' => 'Message is required.'], 400);
            return;
        }

        $aiService = new AIService();
        if (!$aiService->isAvailable()) {
            Response::json(['success' => false, 'message' => 'AI is not configured.'], 503);
            return;
        }

        // Extend PHP execution time for AI chat
        set_time_limit(120);

        // Get or create chat history
        $history = $_SESSION['analytics_chat'] ?? [];
        $data = $_SESSION['analytics_data'] ?? $this->gatherPlatformData();

        // If no prior context, build it
        if (empty($history)) {
            $history[] = ['role' => 'system', 'content' => $this->buildSystemContext($data)];
            $_SESSION['analytics_data'] = $data;
        }

        // Add user message to history
        $history[] = ['role' => 'user', 'content' => $message];

        // Build a single prompt with conversation context
        $prompt = $this->buildChatPrompt($history);

        $reply = $aiService->complete($prompt, [
            'max_tokens' => 1500,
            'temperature' => 0.6,
            'timeout' => 120,
        ]);

        if (!$reply) {
            Response::json([
                'success' => false,
                'message' => 'AI failed to respond: ' . ($aiService->getLastError() ?? 'Unknown error'),
            ], 500);
            return;
        }

        // Add assistant reply to history (keep last 20 messages to avoid token limits)
        $history[] = ['role' => 'assistant', 'content' => $reply];
        if (count($history) > 22) {
            $system = array_shift($history);
            $history = array_slice($history, -20);
            array_unshift($history, $system);
        }
        $_SESSION['analytics_chat'] = $history;

        Response::json([
            'success' => true,
            'reply' => $reply,
        ]);
    }

    /**
     * Reset the chat session
     */
    public function resetChat(): void
    {
        Session::validateCsrf($_POST['_token'] ?? '');
        unset($_SESSION['analytics_chat'], $_SESSION['analytics_data']);
        Response::json(['success' => true]);
    }

    /**
     * Safe query wrapper — returns fallback on failure instead of killing the request
     */
    private function safeQuery(callable $fn, mixed $fallback = null): mixed
    {
        try {
            return $fn() ?? $fallback;
        } catch (\Throwable $e) {
            error_log('[Analytics] Query failed: ' . $e->getMessage());
            return $fallback;
        }
    }

    /**
     * Gather comprehensive platform data for analysis
     */
    private function gatherPlatformData(): array
    {
        $data = [];

        // --- Subscriber metrics ---
        $data['subscribers'] = $this->safeQuery(
            fn() => $this->db->fetch(
                "SELECT
                    COUNT(*) as total,
                    SUM(status = 'active') as active,
                    SUM(status = 'suspended') as suspended,
                    SUM(status = 'expired') as expired,
                    SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as new_7d,
                    SUM(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_30d
                 FROM subscribers"
            ),
            ['total' => 0, 'active' => 0, 'suspended' => 0, 'expired' => 0, 'new_7d' => 0, 'new_30d' => 0]
        );

        // Subscriber growth last 12 months
        $data['subscriber_growth'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
                 FROM subscribers
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                 GROUP BY month ORDER BY month"
            ),
            []
        );

        // --- Content library ---
        $data['content'] = [
            'movies' => (int) ($this->safeQuery(fn() => $this->db->fetch("SELECT COUNT(*) as c FROM movies"), ['c' => 0])['c'] ?? 0),
            'movies_published' => (int) ($this->safeQuery(fn() => $this->db->fetch("SELECT COUNT(*) as c FROM movies WHERE status = 'published'"), ['c' => 0])['c'] ?? 0),
            'series' => (int) ($this->safeQuery(fn() => $this->db->fetch("SELECT COUNT(*) as c FROM series"), ['c' => 0])['c'] ?? 0),
            'channels' => (int) ($this->safeQuery(fn() => $this->db->fetch("SELECT COUNT(*) as c FROM channels"), ['c' => 0])['c'] ?? 0),
            'channels_active' => (int) ($this->safeQuery(fn() => $this->db->fetch("SELECT COUNT(*) as c FROM channels WHERE is_active = 1"), ['c' => 0])['c'] ?? 0),
            'categories' => (int) ($this->safeQuery(fn() => $this->db->fetch("SELECT COUNT(*) as c FROM categories"), ['c' => 0])['c'] ?? 0),
        ];

        // --- Watch activity (last 30 days) ---
        $data['watch_activity'] = $this->safeQuery(
            fn() => $this->db->fetch(
                "SELECT
                    COUNT(*) as total_events,
                    COUNT(DISTINCT subscriber_id) as unique_viewers,
                    SUM(event_type = 'watch_start') as starts,
                    SUM(event_type = 'watch_complete') as completions,
                    SUM(event_type = 'watch_abandon') as abandonments,
                    SUM(event_type = 'search') as searches,
                    SUM(event_type = 'search_no_results') as failed_searches
                 FROM subscriber_events
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            ),
            ['total_events' => 0, 'unique_viewers' => 0, 'starts' => 0, 'completions' => 0, 'abandonments' => 0, 'searches' => 0, 'failed_searches' => 0]
        );

        // --- Watch activity breakdown by content type (last 30 days) ---
        $data['watch_by_type'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT content_type,
                        COUNT(DISTINCT subscriber_id) as unique_viewers,
                        SUM(event_type = 'watch_start') as starts,
                        SUM(event_type = 'watch_complete') as completions,
                        SUM(event_type = 'watch_abandon') as abandonments
                 FROM subscriber_events
                 WHERE event_type IN ('watch_start', 'watch_complete', 'watch_abandon')
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY content_type
                 ORDER BY starts DESC"
            ),
            []
        );

        // --- Live TV total watch time from watch history ---
        $data['live_tv_stats'] = $this->safeQuery(
            fn() => $this->db->fetch(
                "SELECT COUNT(DISTINCT subscriber_id) as unique_viewers,
                        COUNT(*) as total_sessions,
                        COALESCE(SUM(progress_seconds), 0) as total_watch_seconds,
                        COALESCE(ROUND(AVG(progress_seconds)), 0) as avg_watch_seconds
                 FROM subscriber_watch_history
                 WHERE content_type = 'channel'"
            ),
            ['unique_viewers' => 0, 'total_sessions' => 0, 'total_watch_seconds' => 0, 'avg_watch_seconds' => 0]
        );

        // Daily watch events (last 30 days for trend chart)
        $data['daily_watches'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT DATE(created_at) as day, COUNT(*) as events,
                        SUM(event_type = 'watch_start') as starts,
                        SUM(event_type = 'watch_complete') as completions
                 FROM subscriber_events
                 WHERE event_type IN ('watch_start', 'watch_complete', 'watch_abandon')
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY day ORDER BY day"
            ),
            []
        );

        // --- Peak hours ---
        $data['peak_hours'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT HOUR(created_at) as hour, COUNT(*) as events
                 FROM subscriber_events
                 WHERE event_type = 'watch_start'
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY hour ORDER BY hour"
            ),
            []
        );

        // --- Day of week patterns ---
        $data['day_patterns'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT DAYOFWEEK(created_at) as dow, COUNT(*) as events
                 FROM subscriber_events
                 WHERE event_type = 'watch_start'
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY dow ORDER BY dow"
            ),
            []
        );

        // --- Top genres (via movie_categories many-to-many or direct category_id) ---
        $data['top_genres'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT c.name as genre, COUNT(*) as views
                 FROM subscriber_events e
                 JOIN movies m ON e.content_id = m.id
                 JOIN categories c ON m.category_id = c.id
                 WHERE e.content_type = 'movie'
                   AND e.event_type IN ('watch_start', 'watch_complete')
                   AND e.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY c.id, c.name
                 ORDER BY views DESC
                 LIMIT 10"
            ),
            []
        );

        // --- Top content ---
        $data['top_movies'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT m.title, COUNT(*) as views,
                        SUM(e.event_type = 'watch_complete') as completions
                 FROM subscriber_events e
                 JOIN movies m ON e.content_id = m.id
                 WHERE e.content_type = 'movie'
                   AND e.event_type IN ('watch_start', 'watch_complete')
                   AND e.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY m.id, m.title
                 ORDER BY views DESC
                 LIMIT 10"
            ),
            []
        );

        // Top channels from subscriber_events (watch_start events)
        $data['top_channels'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT ch.name as title, COUNT(*) as views
                 FROM subscriber_events e
                 JOIN channels ch ON e.content_id = ch.id
                 WHERE e.content_type = 'channel'
                   AND e.event_type = 'watch_start'
                   AND e.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY ch.id, ch.name
                 ORDER BY views DESC
                 LIMIT 10"
            ),
            []
        );

        // Channel viewing data — combine events (session counts) with watch history (duration)
        $data['channel_views'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT ch.name as title,
                        COALESCE(ev.unique_viewers, wh.unique_viewers, 0) as unique_viewers,
                        COALESCE(ev.total_sessions, wh.total_sessions, 0) as total_sessions,
                        COALESCE(wh.total_watch_seconds, 0) as total_watch_seconds,
                        COALESCE(wh.avg_watch_seconds, 0) as avg_watch_seconds,
                        GREATEST(COALESCE(ev.last_event, '1970-01-01'), COALESCE(wh.last_watched, '1970-01-01')) as last_watched
                 FROM channels ch
                 LEFT JOIN (
                     SELECT content_id,
                            COUNT(DISTINCT subscriber_id) as unique_viewers,
                            COUNT(*) as total_sessions,
                            MAX(created_at) as last_event
                     FROM subscriber_events
                     WHERE content_type = 'channel' AND event_type = 'watch_start'
                     GROUP BY content_id
                 ) ev ON ev.content_id = ch.id
                 LEFT JOIN (
                     SELECT content_id,
                            COUNT(DISTINCT subscriber_id) as unique_viewers,
                            COUNT(*) as total_sessions,
                            SUM(progress_seconds) as total_watch_seconds,
                            ROUND(AVG(progress_seconds)) as avg_watch_seconds,
                            MAX(last_watched_at) as last_watched
                     FROM subscriber_watch_history
                     WHERE content_type = 'channel'
                     GROUP BY content_id
                 ) wh ON wh.content_id = ch.id
                 WHERE ev.content_id IS NOT NULL OR wh.content_id IS NOT NULL
                 ORDER BY total_watch_seconds DESC, total_sessions DESC
                 LIMIT 20"
            ),
            []
        );

        // Top series by episode watch events
        $data['top_series'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT s.title, COUNT(*) as views,
                        SUM(e.event_type = 'watch_complete') as completions,
                        COUNT(DISTINCT e.content_id) as episodes_watched,
                        COUNT(DISTINCT e.subscriber_id) as unique_viewers
                 FROM subscriber_events e
                 JOIN series_episodes ep ON e.content_id = ep.id
                 JOIN series s ON ep.series_id = s.id
                 WHERE e.content_type = 'episode'
                   AND e.event_type IN ('watch_start', 'watch_complete')
                   AND e.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY s.id, s.title
                 ORDER BY views DESC
                 LIMIT 10"
            ),
            []
        );

        // --- Series info (always available, independent of watch events) ---
        $data['series_info'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT s.title, s.status, s.year,
                        COALESCE(c.name, 'Uncategorized') as category,
                        (SELECT COUNT(*) FROM series_episodes ep WHERE ep.series_id = s.id) as total_episodes,
                        (SELECT COUNT(*) FROM series_seasons ss WHERE ss.series_id = s.id) as total_seasons
                 FROM series s
                 LEFT JOIN categories c ON s.category_id = c.id
                 ORDER BY s.title ASC
                 LIMIT 20"
            ),
            []
        );

        // --- Channel info (always available, independent of watch events) ---
        $data['channel_info'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT ch.name,
                        CASE WHEN ch.is_active = 1 AND ch.is_published = 1 THEN 'active'
                             WHEN ch.is_active = 1 THEN 'unpublished'
                             ELSE 'inactive' END as status,
                        COALESCE(
                            (SELECT cat.name FROM channel_categories cc
                             JOIN categories cat ON cc.category_id = cat.id
                             WHERE cc.channel_id = ch.id AND cc.is_primary = 1 LIMIT 1),
                            'Uncategorized'
                        ) as category
                 FROM channels ch
                 ORDER BY ch.name ASC
                 LIMIT 20"
            ),
            []
        );

        // --- Channel status breakdown ---
        $data['channel_status'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT CASE WHEN is_active = 1 AND is_published = 1 THEN 'active'
                             WHEN is_active = 1 THEN 'unpublished'
                             ELSE 'inactive' END as status,
                        COUNT(*) as count
                 FROM channels
                 GROUP BY status"
            ),
            []
        );

        // --- Platform breakdown ---
        $data['platforms'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT COALESCE(platform, 'unknown') as platform, COUNT(*) as events
                 FROM subscriber_events
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY platform
                 ORDER BY events DESC"
            ),
            []
        );

        // --- Search terms (popular + failed) ---
        $data['top_searches'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.query')) as term,
                        COUNT(*) as count,
                        SUM(event_type = 'search_no_results') as no_results
                 FROM subscriber_events
                 WHERE event_type IN ('search', 'search_no_results')
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                   AND JSON_EXTRACT(metadata, '$.query') IS NOT NULL
                 GROUP BY term
                 ORDER BY count DESC
                 LIMIT 15"
            ),
            []
        );

        // --- Ad performance ---
        $data['ad_performance'] = $this->safeQuery(
            fn() => $this->db->fetch(
                "SELECT
                    COUNT(*) as total_impressions,
                    SUM(revenue) as total_revenue
                 FROM ad_impressions
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            ),
            ['total_impressions' => 0, 'total_revenue' => 0]
        );

        $data['ad_clicks'] = (int) ($this->safeQuery(
            fn() => $this->db->fetch(
                "SELECT COUNT(*) as c FROM ad_events
                 WHERE event_type = 'click'
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            ),
            ['c' => 0]
        )['c'] ?? 0);

        // --- Subscriber country distribution (all registered) ---
        $data['countries'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT COALESCE(country, 'Unknown') as country, COUNT(*) as count
                 FROM subscribers
                 GROUP BY country
                 ORDER BY count DESC
                 LIMIT 10"
            ),
            []
        );

        // --- Active viewer countries (subscribers who actually watched in last 30 days) ---
        $data['viewer_countries'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT COALESCE(s.country, 'Unknown') as country,
                        COUNT(DISTINCT e.subscriber_id) as viewers,
                        COUNT(*) as total_watches,
                        SUM(e.content_type = 'channel') as live_tv_watches,
                        SUM(e.content_type = 'movie') as movie_watches,
                        SUM(e.content_type = 'episode') as series_watches
                 FROM subscriber_events e
                 JOIN subscribers s ON e.subscriber_id = s.id
                 WHERE e.event_type = 'watch_start'
                   AND e.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY s.country
                 ORDER BY viewers DESC
                 LIMIT 20"
            ),
            []
        );

        // --- Per-country top channels and categories ---
        $data['country_top_channels'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT COALESCE(s.country, 'Unknown') as country,
                        ch.name as channel_name,
                        COUNT(*) as watches
                 FROM subscriber_events e
                 JOIN subscribers s ON e.subscriber_id = s.id
                 JOIN channels ch ON e.content_id = ch.id
                 WHERE e.event_type = 'watch_start'
                   AND e.content_type = 'channel'
                   AND e.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY s.country, ch.id, ch.name
                 ORDER BY s.country, watches DESC"
            ),
            []
        );

        // --- Per-country top categories (from movies + series) ---
        $data['country_top_categories'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT COALESCE(s.country, 'Unknown') as country,
                        COALESCE(c.name, 'Uncategorized') as category,
                        COUNT(*) as watches
                 FROM subscriber_events e
                 JOIN subscribers s ON e.subscriber_id = s.id
                 LEFT JOIN movies m ON e.content_type = 'movie' AND e.content_id = m.id
                 LEFT JOIN series_episodes ep ON e.content_type = 'episode' AND e.content_id = ep.id
                 LEFT JOIN series sr ON ep.series_id = sr.id
                 LEFT JOIN categories c ON c.id = COALESCE(m.category_id, sr.category_id)
                 WHERE e.event_type = 'watch_start'
                   AND e.content_type IN ('movie', 'episode')
                   AND e.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY s.country, c.id, c.name
                 ORDER BY s.country, watches DESC"
            ),
            []
        );

        // --- Package distribution (via subscriber_subscriptions) ---
        $data['packages'] = $this->safeQuery(
            fn() => $this->db->fetchAll(
                "SELECT p.name, COUNT(DISTINCT ss.subscriber_id) as subscribers
                 FROM subscriber_subscriptions ss
                 JOIN packages p ON ss.package_id = p.id
                 WHERE ss.status IN ('active', 'trial')
                 GROUP BY p.id, p.name
                 ORDER BY subscribers DESC"
            ),
            []
        );

        // --- Advanced Analytics (Phase 2) ---
        $analyticsService = new AnalyticsService();

        // QoE stats
        $data['qoe'] = $this->safeQuery(fn() => $analyticsService->getQoeStats(), [
            'startup' => ['avg_startup_ms' => 0, 'total_starts' => 0],
            'buffering' => ['buffer_events' => 0, 'avg_buffer_ms' => 0, 'affected_users' => 0],
            'errors' => [],
            'total_errors' => 0,
            'quality_distribution' => [],
            'trend' => [],
        ]);

        // Session analytics
        $data['sessions'] = $this->safeQuery(fn() => $analyticsService->getSessionStats(), [
            'summary' => ['total_sessions' => 0, 'unique_users' => 0, 'avg_duration_seconds' => 0, 'bounce_rate' => 0],
            'devices' => [],
            'browsers' => [],
            'os' => [],
            'connections' => [],
            'screens' => [],
        ]);

        // Impression stats (CTR)
        $data['impressions'] = $this->safeQuery(fn() => $analyticsService->getImpressionStats(), [
            'section_ctr' => [],
            'discovery_gap' => [],
            'position_ctr' => [],
        ]);

        // Engagement scores
        $data['engagement'] = $this->safeQuery(fn() => $analyticsService->getEngagementOverview(), [
            'churn_distribution' => [],
            'score_segments' => [],
            'top_engaged' => [],
            'at_risk' => [],
            'avg_score' => 0,
        ]);

        // Retention cohorts
        $data['retention_cohorts'] = $this->safeQuery(fn() => $analyticsService->getRetentionCohorts(), []);

        // Binge watching stats
        $data['binge'] = $this->safeQuery(fn() => $analyticsService->getBingeStats(), [
            'top_binged_series' => [],
            'summary' => ['total_binges' => 0, 'unique_bingers' => 0],
        ]);

        // Concurrent viewers (real-time)
        $data['concurrent'] = $this->safeQuery(fn() => $analyticsService->getConcurrentViewers(), [
            'total' => 0,
            'by_type' => [],
        ]);

        // Share stats
        $data['shares'] = $this->safeQuery(fn() => $analyticsService->getShareStats(), [
            'by_method' => [],
            'top_shared' => [],
        ]);

        return $data;
    }

    /**
     * Build the system context for AI with summarized platform data.
     * Uses a compact text summary instead of raw JSON to reduce prompt size
     * and improve AI response times (especially with local models like Ollama).
     */
    private function buildSystemContext(array $data): string
    {
        $context = "You are a business intelligence analyst for CARI-IPTV, a carrier-grade IPTV/OTT streaming platform serving the Caribbean market.\n\n";
        $context .= "Your role is to analyze platform data, identify trends, provide actionable business insights, and make recommendations to improve the business.\n\n";
        $context .= "When presenting data, use markdown formatting. Use bullet points, bold text, and headers for clarity.\n";
        $context .= "When you reference numbers, include both absolute values and percentages where meaningful.\n";
        $context .= "Suggest specific, actionable strategies — not generic advice.\n";
        $context .= "IMPORTANT: This is an IPTV platform — always analyze Live TV (channel) viewing separately from VOD (movies) and Series. Report both.\n";
        $context .= "NOTE: Subscriber country data is self-reported during registration and may be inaccurate. Only trust 'Active Viewer Countries' data for viewers who actually watched content.\n\n";
        $context .= "=== PLATFORM DATA (Last 30 Days) ===\n\n";
        $context .= $this->summarizeData($data);
        return $context;
    }

    /**
     * Summarize platform data into compact text for AI prompt.
     * Reduces prompt size from ~20KB JSON to ~2-3KB text.
     */
    private function summarizeData(array $data): string
    {
        $s = '';

        // Subscribers
        $sub = $data['subscribers'] ?? [];
        $s .= "SUBSCRIBERS: {$sub['total']} total, {$sub['active']} active, {$sub['suspended']} suspended, {$sub['expired']} expired. New: {$sub['new_7d']} (7d), {$sub['new_30d']} (30d).\n";

        // Subscriber growth
        $growth = $data['subscriber_growth'] ?? [];
        if ($growth) {
            $months = array_map(fn($g) => "{$g['month']}:{$g['count']}", $growth);
            $s .= "GROWTH (monthly): " . implode(', ', $months) . "\n";
        }

        // Content library
        $c = $data['content'] ?? [];
        $s .= "CONTENT: {$c['movies']} movies ({$c['movies_published']} published), {$c['series']} series, {$c['channels']} channels ({$c['channels_active']} active), {$c['categories']} categories.\n";

        // Watch activity
        $w = $data['watch_activity'] ?? [];
        $s .= "WATCH ACTIVITY (30d): {$w['total_events']} events, {$w['unique_viewers']} unique viewers, {$w['starts']} starts, {$w['completions']} completions, {$w['abandonments']} abandonments.\n";
        if (((int)($w['starts'] ?? 0)) > 0) {
            $rate = round(((int)($w['completions'] ?? 0)) / ((int)$w['starts']) * 100, 1);
            $s .= "  Completion rate: {$rate}%. Searches: {$w['searches']} ({$w['failed_searches']} no results).\n";
        }

        // Watch activity by content type
        $byType = $data['watch_by_type'] ?? [];
        if ($byType) {
            $typeLabels = ['channel' => 'Live TV', 'movie' => 'Movies', 'episode' => 'TV Shows/Episodes', 'series' => 'Series'];
            $s .= "WATCH BREAKDOWN BY TYPE: " . implode(', ', array_map(fn($t) =>
                ($typeLabels[$t['content_type']] ?? $t['content_type']) .
                "({$t['starts']} starts/{$t['completions']} completions/{$t['unique_viewers']} viewers)",
                $byType)) . "\n";
        }

        // Live TV aggregate stats
        $ltv = $data['live_tv_stats'] ?? [];
        if (((int)($ltv['total_sessions'] ?? 0)) > 0) {
            $totalHrs = round(($ltv['total_watch_seconds'] ?? 0) / 3600, 1);
            $avgMin = round(($ltv['avg_watch_seconds'] ?? 0) / 60, 1);
            $s .= "LIVE TV TOTAL: {$ltv['unique_viewers']} unique viewers, {$ltv['total_sessions']} sessions, {$totalHrs}hrs total watch time, avg {$avgMin}min/session.\n";
        }

        // Peak hours (top 5)
        $hours = $data['peak_hours'] ?? [];
        if ($hours) {
            usort($hours, fn($a, $b) => ($b['events'] ?? 0) - ($a['events'] ?? 0));
            $top = array_slice($hours, 0, 5);
            $s .= "PEAK HOURS: " . implode(', ', array_map(fn($h) => "{$h['hour']}:00={$h['events']}", $top)) . "\n";
        }

        // Top genres
        $genres = $data['top_genres'] ?? [];
        if ($genres) {
            $s .= "TOP GENRES: " . implode(', ', array_map(fn($g) => "{$g['genre']}({$g['views']})", array_slice($genres, 0, 8))) . "\n";
        }

        // Top movies
        $movies = $data['top_movies'] ?? [];
        if ($movies) {
            $s .= "TOP MOVIES: " . implode(', ', array_map(fn($m) => "{$m['title']}({$m['views']}v/{$m['completions']}c)", array_slice($movies, 0, 8))) . "\n";
        }

        // Top series
        $series = $data['top_series'] ?? [];
        if ($series) {
            $s .= "TOP TV SHOWS: " . implode(', ', array_map(fn($t) => "{$t['title']}({$t['views']}v/{$t['completions']}c/{$t['episodes_watched']}ep)", array_slice($series, 0, 8))) . "\n";
        }

        // Channel viewing
        $channelViews = $data['channel_views'] ?? [];
        if ($channelViews) {
            $s .= "CHANNEL VIEWING: " . implode(', ', array_map(fn($ch) => "{$ch['title']}({$ch['unique_viewers']}viewers/{$ch['total_sessions']}sessions/avg" . round(($ch['avg_watch_seconds'] ?? 0) / 60) . "min)", array_slice($channelViews, 0, 8))) . "\n";
        }
        $channels = $data['top_channels'] ?? [];
        if ($channels) {
            $s .= "TOP CHANNELS (events): " . implode(', ', array_map(fn($ch) => "{$ch['title']}({$ch['views']})", array_slice($channels, 0, 8))) . "\n";
        }

        // Channel status
        $chStatus = $data['channel_status'] ?? [];
        if ($chStatus) {
            $s .= "CHANNEL STATUS: " . implode(', ', array_map(fn($cs) => "{$cs['status']}={$cs['count']}", $chStatus)) . "\n";
        }

        // Top searches
        $searches = $data['top_searches'] ?? [];
        if ($searches) {
            $s .= "TOP SEARCHES: " . implode(', ', array_map(fn($t) => "{$t['term']}({$t['count']}" . ($t['no_results'] > 0 ? ",{$t['no_results']}fail" : '') . ")", array_slice($searches, 0, 10))) . "\n";
        }

        // Ads
        $ad = $data['ad_performance'] ?? [];
        $clicks = $data['ad_clicks'] ?? 0;
        $s .= "ADS (30d): {$ad['total_impressions']} impressions, {$clicks} clicks, \${$ad['total_revenue']} revenue.\n";

        // Platforms
        $plat = $data['platforms'] ?? [];
        if ($plat) {
            $s .= "PLATFORMS: " . implode(', ', array_map(fn($p) => "{$p['platform']}={$p['events']}", $plat)) . "\n";
        }

        // Countries (self-reported by subscribers during registration)
        $countries = $data['countries'] ?? [];
        if ($countries) {
            $s .= "SUBSCRIBER REGISTERED COUNTRIES (self-reported): " . implode(', ', array_map(fn($c) => "{$c['country']}({$c['count']})", array_slice($countries, 0, 8))) . "\n";
        }

        // Active viewer countries (from recent watch activity)
        $viewerCountries = $data['viewer_countries'] ?? [];
        if ($viewerCountries) {
            $s .= "ACTIVE VIEWER COUNTRIES (last 30d): " . implode(', ', array_map(fn($c) =>
                "{$c['country']}({$c['viewers']} viewers, {$c['total_watches']} watches: {$c['live_tv_watches']} live/{$c['movie_watches']} movies/{$c['series_watches']} series)",
                array_slice($viewerCountries, 0, 8))) . "\n";
        }

        // Per-country top channels
        $countryChannels = $data['country_top_channels'] ?? [];
        if ($countryChannels) {
            $byCountry = [];
            foreach ($countryChannels as $cc) {
                $byCountry[$cc['country']][] = "{$cc['channel_name']}({$cc['watches']})";
            }
            $parts = [];
            foreach ($byCountry as $country => $chans) {
                $parts[] = "{$country}: " . implode(', ', array_slice($chans, 0, 3));
            }
            $s .= "TOP CHANNELS BY COUNTRY: " . implode(' | ', array_slice($parts, 0, 5)) . "\n";
        }

        // Per-country top categories
        $countryCats = $data['country_top_categories'] ?? [];
        if ($countryCats) {
            $byCountry = [];
            foreach ($countryCats as $cc) {
                $byCountry[$cc['country']][] = "{$cc['category']}({$cc['watches']})";
            }
            $parts = [];
            foreach ($byCountry as $country => $cats) {
                $parts[] = "{$country}: " . implode(', ', array_slice($cats, 0, 3));
            }
            $s .= "TOP CATEGORIES BY COUNTRY: " . implode(' | ', array_slice($parts, 0, 5)) . "\n";
        }

        // Packages
        $pkgs = $data['packages'] ?? [];
        if ($pkgs) {
            $s .= "PACKAGES: " . implode(', ', array_map(fn($p) => "{$p['name']}({$p['subscribers']})", $pkgs)) . "\n";
        }

        // QoE
        $qoe = $data['qoe'] ?? [];
        if (!empty($qoe['startup'])) {
            $startup = $qoe['startup'];
            $buf = $qoe['buffering'] ?? [];
            $s .= "QoE (30d): Avg startup " . round($startup['avg_startup_ms'] ?? 0) . "ms, ";
            $s .= ($buf['buffer_events'] ?? 0) . " buffer events affecting " . ($buf['affected_users'] ?? 0) . " users (avg " . round($buf['avg_buffer_ms'] ?? 0) . "ms), ";
            $s .= ($qoe['total_errors'] ?? 0) . " playback errors.\n";
        }

        // Session analytics
        $sess = $data['sessions']['summary'] ?? [];
        if (($sess['total_sessions'] ?? 0) > 0) {
            $s .= "SESSIONS (30d): {$sess['total_sessions']} sessions, {$sess['unique_users']} unique users, avg " . round(($sess['avg_duration_seconds'] ?? 0) / 60, 1) . "min duration, {$sess['bounce_rate']}% bounce rate.\n";
        }

        // Engagement overview
        $eng = $data['engagement'] ?? [];
        if (($eng['avg_score'] ?? 0) > 0) {
            $s .= "ENGAGEMENT: Avg score " . $eng['avg_score'] . "/100. ";
            $churn = $eng['churn_distribution'] ?? [];
            if ($churn) {
                $s .= "Churn risk: " . implode(', ', array_map(fn($c) => "{$c['churn_risk']}={$c['count']}", $churn)) . ".\n";
            }
        }

        // Concurrent viewers
        $conc = $data['concurrent'] ?? [];
        if (($conc['total'] ?? 0) > 0) {
            $s .= "CONCURRENT VIEWERS (now): {$conc['total']}.\n";
        }

        // Binge stats
        $binge = $data['binge']['summary'] ?? [];
        if (($binge['total_binges'] ?? 0) > 0) {
            $s .= "BINGE WATCHING (30d): {$binge['total_binges']} binge sessions by {$binge['unique_bingers']} users, avg " . round($binge['avg_episodes_per_binge'] ?? 0, 1) . " episodes/binge.";
            $topBinged = $data['binge']['top_binged_series'] ?? [];
            if ($topBinged) {
                $s .= " Top binged: " . implode(', ', array_map(fn($b) => "{$b['title']}({$b['binge_count']}x)", array_slice($topBinged, 0, 5)));
            }
            $s .= "\n";
        }

        // Content impressions & CTR
        $impressions = $data['impressions'] ?? [];
        $sectionCtr = $impressions['section_ctr'] ?? [];
        if ($sectionCtr) {
            $s .= "CONTENT CTR BY SECTION: " . implode(', ', array_map(fn($sec) => "{$sec['section_type']}=" . round($sec['ctr'] ?? 0, 1) . "% ({$sec['impressions']} views/{$sec['clicks']} clicks)", array_slice($sectionCtr, 0, 8))) . "\n";
        }
        $discoveryGap = $impressions['discovery_gap'] ?? [];
        if ($discoveryGap) {
            $s .= "DISCOVERY GAP (seen but never clicked): " . implode(', ', array_map(fn($d) => "{$d['title']}({$d['impressions']} impressions, 0 clicks)", array_slice($discoveryGap, 0, 5))) . "\n";
        }
        $posCtr = $impressions['position_ctr'] ?? [];
        if ($posCtr) {
            $s .= "POSITION CTR: " . implode(', ', array_map(fn($p) => "pos{$p['position']}=" . round($p['ctr'] ?? 0, 1) . "%", array_slice($posCtr, 0, 6))) . "\n";
        }

        // Share stats
        $shares = $data['shares'] ?? [];
        $byMethod = $shares['by_method'] ?? [];
        if ($byMethod) {
            $s .= "SHARES BY METHOD: " . implode(', ', array_map(fn($m) => "{$m['share_method']}={$m['count']}", $byMethod)) . "\n";
        }
        $topShared = $shares['top_shared'] ?? [];
        if ($topShared) {
            $s .= "TOP SHARED CONTENT: " . implode(', ', array_map(fn($t) => "{$t['title']}({$t['shares']})", array_slice($topShared, 0, 5))) . "\n";
        }

        // Device/browser/OS/connection breakdown from sessions
        $sessDevices = $data['sessions']['devices'] ?? [];
        if ($sessDevices) {
            $s .= "DEVICE TYPES: " . implode(', ', array_map(fn($d) => "{$d['device_type']}={$d['count']}", array_slice($sessDevices, 0, 6))) . "\n";
        }
        $sessBrowsers = $data['sessions']['browsers'] ?? [];
        if ($sessBrowsers) {
            $s .= "BROWSERS: " . implode(', ', array_map(fn($b) => "{$b['browser']}={$b['count']}", array_slice($sessBrowsers, 0, 6))) . "\n";
        }
        $sessOs = $data['sessions']['os'] ?? [];
        if ($sessOs) {
            $s .= "OS: " . implode(', ', array_map(fn($o) => "{$o['os']}={$o['count']}", array_slice($sessOs, 0, 6))) . "\n";
        }
        $sessConn = $data['sessions']['connections'] ?? [];
        if ($sessConn) {
            $s .= "CONNECTION TYPES: " . implode(', ', array_map(fn($c) => "{$c['connection_type']}={$c['count']}", array_slice($sessConn, 0, 5))) . "\n";
        }
        $sessScreens = $data['sessions']['screens'] ?? [];
        if ($sessScreens) {
            $s .= "SCREEN RESOLUTIONS: " . implode(', ', array_map(fn($r) => "{$r['screen_resolution']}={$r['count']}", array_slice($sessScreens, 0, 5))) . "\n";
        }

        // Retention cohorts
        $cohorts = $data['retention_cohorts'] ?? [];
        if ($cohorts) {
            $cohortSummary = [];
            foreach ($cohorts as $c) {
                $key = $c['cohort_month'];
                if (!isset($cohortSummary[$key])) {
                    $cohortSummary[$key] = ['size' => $c['cohort_size'], 'rates' => []];
                }
                $cohortSummary[$key]['rates'][] = "M{$c['months_after']}=" . round($c['retention_rate'], 0) . "%";
            }
            $parts = [];
            foreach (array_slice($cohortSummary, 0, 6, true) as $month => $info) {
                $parts[] = "{$month}(n={$info['size']}): " . implode(', ', $info['rates']);
            }
            $s .= "RETENTION COHORTS: " . implode(' | ', $parts) . "\n";
        }

        // At-risk subscribers
        $atRisk = $data['engagement']['at_risk'] ?? [];
        if ($atRisk) {
            $critical = array_filter($atRisk, fn($a) => ($a['churn_risk'] ?? '') === 'critical');
            $high = array_filter($atRisk, fn($a) => ($a['churn_risk'] ?? '') === 'high');
            $s .= "AT-RISK SUBSCRIBERS: " . count($critical) . " critical, " . count($high) . " high risk.";
            if ($critical) {
                $s .= " Critical: " . implode(', ', array_map(fn($a) => "{$a['username']}(score={$a['score']}, {$a['days_since_last_activity']}d inactive)", array_slice(array_values($critical), 0, 5)));
            }
            $s .= "\n";
        }

        // QoE quality distribution
        $qoeQuality = $qoe['quality_distribution'] ?? [];
        if ($qoeQuality) {
            $s .= "QUALITY DISTRIBUTION: " . implode(', ', array_map(fn($q) => "{$q['resolution']}={$q['count']}", array_slice($qoeQuality, 0, 5))) . "\n";
        }

        return $s;
    }

    /**
     * Build the report generation prompt
     */
    private function buildReportPrompt(array $data, string $focusArea): string
    {
        $prompt = $this->buildSystemContext($data);
        $prompt .= "\n\n=== TASK ===\n";
        $prompt .= "Generate a comprehensive business intelligence report. ";

        switch ($focusArea) {
            case 'engagement':
                $prompt .= "Focus on viewer engagement: watch patterns broken down by Live TV vs VOD vs Series, completion rates, peak hours, content preferences, channel watch time, and how to increase engagement.";
                break;
            case 'growth':
                $prompt .= "Focus on subscriber growth: acquisition trends, churn indicators, geographic distribution, and strategies to grow the subscriber base.";
                break;
            case 'content':
                $prompt .= "Focus on content performance: top-performing content, underperforming categories, content gaps based on search data, and content acquisition recommendations.";
                break;
            case 'revenue':
                $prompt .= "Focus on revenue and monetization: ad performance, package distribution, revenue optimization, and pricing strategy.";
                break;
            default:
                $prompt .= "Cover all areas: subscriber growth, engagement trends (including Live TV/channel viewing separately from VOD), content performance, revenue/ads, and platform usage. Provide an executive summary followed by detailed sections.";
                break;
        }

        $prompt .= "\n\nStructure your report with clear markdown headers (##), bullet points, and key metrics highlighted in **bold**.";
        $prompt .= " End with a prioritized list of 3-5 actionable recommendations.";

        return $prompt;
    }

    /**
     * Build a chat prompt with conversation history
     */
    private function buildChatPrompt(array $history): string
    {
        $prompt = '';
        foreach ($history as $msg) {
            $role = strtoupper($msg['role']);
            $prompt .= "[{$role}]\n{$msg['content']}\n\n";
        }
        $prompt .= "Continue the conversation. Use markdown formatting. If asked for charts or data breakdowns, present the data in a structured way that can be visualized. Be specific and actionable.\n\n[ASSISTANT]\n";
        return $prompt;
    }
}
