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
        $data = $this->gatherPlatformData();
        Response::json(['success' => true, 'data' => $data]);
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

        $data = $this->gatherPlatformData();
        $focusArea = trim($_POST['focus'] ?? 'general');

        $prompt = $this->buildReportPrompt($data, $focusArea);

        $report = $aiService->complete($prompt, [
            'max_tokens' => 2000,
            'temperature' => 0.6,
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
            'channels_active' => (int) ($this->safeQuery(fn() => $this->db->fetch("SELECT COUNT(*) as c FROM channels WHERE status = 'active'"), ['c' => 0])['c'] ?? 0),
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

        // --- Subscriber country distribution ---
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

        return $data;
    }

    /**
     * Build the system context for AI with all platform data
     */
    private function buildSystemContext(array $data): string
    {
        $context = "You are a business intelligence analyst for CARI-IPTV, a carrier-grade IPTV/OTT streaming platform serving the Caribbean market.\n\n";
        $context .= "Your role is to analyze platform data, identify trends, provide actionable business insights, and make recommendations to improve the business.\n\n";
        $context .= "When presenting data, use markdown formatting. Use bullet points, bold text, and headers for clarity.\n";
        $context .= "When you reference numbers, include both absolute values and percentages where meaningful.\n";
        $context .= "Suggest specific, actionable strategies — not generic advice.\n\n";
        $context .= "=== PLATFORM DATA (Last 30 Days) ===\n\n";
        $context .= json_encode($data, JSON_PRETTY_PRINT);
        return $context;
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
                $prompt .= "Focus on viewer engagement: watch patterns, completion rates, peak hours, content preferences, and how to increase engagement.";
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
                $prompt .= "Cover all areas: subscriber growth, engagement trends, content performance, revenue/ads, and platform usage. Provide an executive summary followed by detailed sections.";
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
