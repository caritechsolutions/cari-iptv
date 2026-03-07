<?php
/**
 * CARI-IPTV Recommendation Service
 * Generates AI-powered user profiles and content recommendations.
 * Designed to run as a background cron job or triggered on-demand.
 */

namespace CariIPTV\Services;

use CariIPTV\Core\Database;

class RecommendationService
{
    private Database $db;
    private AnalyticsService $analytics;

    // Recommendation set types
    public const SET_FOR_YOU = 'for_you';
    public const SET_BECAUSE_WATCHED = 'because_watched';
    public const SET_TRENDING = 'trending';
    public const SET_TOP_PICKS = 'top_picks';
    public const SET_HIDDEN_GEMS = 'hidden_gems';
    public const SET_NEW_RELEASES = 'new_releases';
    public const SET_EVENING_PLAN = 'evening_plan';

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->analytics = new AnalyticsService();
    }

    // =========================================================================
    // PROFILE GENERATION (Tier 1 — runs daily per subscriber)
    // =========================================================================

    /**
     * Generate or refresh a subscriber's taste profile using AI
     */
    public function generateProfile(int $subscriberId): bool
    {
        $stats = $this->analytics->getSubscriberStats($subscriberId);

        // Gather watch history
        $watchHistory = $this->db->fetchAll(
            "SELECT wh.content_type, wh.content_id, wh.completed, wh.progress_seconds, wh.duration_seconds,
                    COALESCE(m.title, s.title) as title,
                    COALESCE(m.genres, s.genres) as genres,
                    COALESCE(mc.name, sc.name) as category_name
             FROM subscriber_watch_history wh
             LEFT JOIN movies m ON wh.content_type = 'movie' AND wh.content_id = m.id
             LEFT JOIN series s ON wh.content_type = 'episode' AND wh.content_id = s.id
             LEFT JOIN categories mc ON m.category_id = mc.id
             LEFT JOIN categories sc ON s.category_id = sc.id
             WHERE wh.subscriber_id = ?
             ORDER BY wh.last_watched_at DESC
             LIMIT 100",
            [$subscriberId]
        );

        // Gather ratings
        $ratings = $this->db->fetchAll(
            "SELECT r.content_type, r.content_id, r.rating,
                    COALESCE(m.title, s.title) as title,
                    COALESCE(m.genres, s.genres) as genres
             FROM subscriber_ratings r
             LEFT JOIN movies m ON r.content_type = 'movie' AND r.content_id = m.id
             LEFT JOIN series s ON r.content_type = 'series' AND r.content_id = s.id
             WHERE r.subscriber_id = ?
             ORDER BY r.updated_at DESC",
            [$subscriberId]
        );

        // Build genre affinities from combined data
        $genreScores = [];
        foreach ($watchHistory as $item) {
            $cat = $item['category_name'] ?? '';
            if (!$cat) continue;
            $weight = $item['completed'] ? 2.0 : 1.0;
            $genreScores[$cat] = ($genreScores[$cat] ?? 0) + $weight;
        }
        foreach ($ratings as $item) {
            $genres = $item['genres'] ?? '';
            if (!$genres) continue;
            foreach (explode(',', $genres) as $g) {
                $g = trim($g);
                if ($g) {
                    $genreScores[$g] = ($genreScores[$g] ?? 0) + ($item['rating'] / 5.0);
                }
            }
        }
        arsort($genreScores);

        // Build actor affinities
        $actorAffinities = [];
        foreach ($stats['favorite_actors'] ?? [] as $actor) {
            $actorAffinities[] = [
                'name' => $actor['name'],
                'tmdb_id' => $actor['tmdb_person_id'] ?? null,
                'score' => (int) $actor['appearances'],
            ];
        }

        // Build viewing patterns
        $viewingPatterns = [
            'peak_hours' => array_slice(array_column($stats['time_patterns'] ?? [], 'hour'), 0, 3),
            'peak_days' => array_slice(array_column($stats['day_patterns'] ?? [], 'day_of_week'), 0, 3),
            'completion_rate' => $this->calcCompletionRate($stats['completion_rate'] ?? []),
            'avg_session_events' => $this->calcAvgSession($stats['session_stats'] ?? []),
        ];

        // Content preferences
        $contentPrefs = [
            'movies_watched' => count(array_filter($watchHistory, fn($w) => $w['content_type'] === 'movie')),
            'series_watched' => count(array_filter($watchHistory, fn($w) => $w['content_type'] === 'episode')),
            'prefers' => null,
        ];
        if ($contentPrefs['movies_watched'] > 0 || $contentPrefs['series_watched'] > 0) {
            $contentPrefs['prefers'] = $contentPrefs['movies_watched'] > $contentPrefs['series_watched'] * 1.5 ? 'movies' :
                ($contentPrefs['series_watched'] > $contentPrefs['movies_watched'] * 1.5 ? 'series' : 'both');
        }

        // Generate AI summary if AI is available
        $aiSummary = $this->generateAISummary($subscriberId, $genreScores, $actorAffinities, $viewingPatterns, $watchHistory, $ratings);

        // Upsert profile
        $this->db->execute(
            "INSERT INTO subscriber_profiles
                (subscriber_id, genre_affinities, actor_affinities, director_affinities, viewing_patterns, language_preferences, content_preferences, ai_summary, last_analyzed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                genre_affinities = VALUES(genre_affinities),
                actor_affinities = VALUES(actor_affinities),
                viewing_patterns = VALUES(viewing_patterns),
                content_preferences = VALUES(content_preferences),
                ai_summary = VALUES(ai_summary),
                profile_version = profile_version + 1,
                last_analyzed_at = NOW()",
            [
                $subscriberId,
                json_encode($genreScores),
                json_encode($actorAffinities),
                json_encode([]), // director_affinities — populated in future
                json_encode($viewingPatterns),
                json_encode([]), // language_preferences — populated in future
                json_encode($contentPrefs),
                $aiSummary,
            ]
        );

        return true;
    }

    /**
     * Use AI to generate a natural language taste profile summary
     */
    private function generateAISummary(
        int $subscriberId,
        array $genreScores,
        array $actorAffinities,
        array $viewingPatterns,
        array $watchHistory,
        array $ratings
    ): ?string {
        try {
            $ai = new AIService();
            if (!$ai->isAvailable()) return null;

            $topGenres = array_slice(array_keys($genreScores), 0, 5);
            $topActors = array_slice(array_column($actorAffinities, 'name'), 0, 5);
            $recentTitles = array_slice(array_column($watchHistory, 'title'), 0, 10);
            $highRated = array_filter($ratings, fn($r) => $r['rating'] >= 4);
            $highRatedTitles = array_slice(array_column($highRated, 'title'), 0, 5);

            $prompt = "You are a content recommendation AI for an IPTV streaming service in the Caribbean. "
                . "Analyze this viewer's behavior and create a brief taste profile (2-3 sentences). "
                . "Be specific about what kinds of content they'd enjoy.\n\n"
                . "Top genres: " . implode(', ', $topGenres) . "\n"
                . "Favorite actors: " . implode(', ', $topActors) . "\n"
                . "Recently watched: " . implode(', ', $recentTitles) . "\n"
                . "Highly rated: " . implode(', ', $highRatedTitles) . "\n"
                . "Viewing pattern: " . ($viewingPatterns['prefers'] ?? 'mixed') . " viewer, "
                . "completion rate " . ($viewingPatterns['completion_rate'] ?? 'unknown') . "%\n"
                . "Peak hours: " . implode(', ', array_map(fn($h) => $h . ':00', $viewingPatterns['peak_hours'] ?? [])) . "\n\n"
                . "Write the profile in third person. Focus on actionable insights for recommendations.";

            return $ai->complete($prompt, ['max_tokens' => 200]);
        } catch (\Throwable $e) {
            error_log('AI profile generation failed: ' . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    // RECOMMENDATION GENERATION (Tier 2 — runs after profile generation)
    // =========================================================================

    /**
     * Generate all recommendation sets for a subscriber
     */
    public function generateRecommendations(int $subscriberId): int
    {
        // Clear expired and old recommendations
        $this->db->execute(
            "DELETE FROM recommendation_sets WHERE subscriber_id = ? AND (expires_at IS NOT NULL AND expires_at < NOW())",
            [$subscriberId]
        );

        $setsGenerated = 0;

        // 1. "For You" — content-based from genre/actor affinities
        if ($this->generateForYou($subscriberId)) $setsGenerated++;

        // 2. "Because You Watched X" — item-based similarity
        $setsGenerated += $this->generateBecauseYouWatched($subscriberId);

        // 3. "Trending Now" — global trending
        if ($this->generateTrending($subscriberId)) $setsGenerated++;

        // 4. "Top Picks" — highest rated content matching profile
        if ($this->generateTopPicks($subscriberId)) $setsGenerated++;

        // 5. "Hidden Gems" — high-rated, low-view content matching profile
        if ($this->generateHiddenGems($subscriberId)) $setsGenerated++;

        // 6. "New Releases" — recently added content matching profile
        if ($this->generateNewReleases($subscriberId)) $setsGenerated++;

        return $setsGenerated;
    }

    /**
     * "Recommended For You" — matches subscriber's genre and actor affinities
     */
    private function generateForYou(int $subscriberId): bool
    {
        $profile = $this->getProfile($subscriberId);
        if (!$profile) return false;

        $genreAffinities = json_decode($profile['genre_affinities'] ?? '{}', true) ?: [];
        if (empty($genreAffinities)) return false;

        $topGenres = array_slice(array_keys($genreAffinities), 0, 5);

        // Get watched content IDs to exclude
        $watched = $this->getWatchedContentIds($subscriberId);

        // Find movies matching top genres, not yet watched
        $movies = $this->db->fetchAll(
            "SELECT m.id, 'movie' as content_type, m.title, m.vote_average, m.year,
                    m.poster_url, m.community_rating, c.name as category_name
             FROM movies m
             LEFT JOIN categories c ON m.category_id = c.id
             WHERE m.status = 'published'
               AND c.name IN (" . implode(',', array_fill(0, count($topGenres), '?')) . ")
             ORDER BY COALESCE(m.community_rating, m.vote_average) DESC, m.created_at DESC
             LIMIT 30",
            $topGenres
        );

        // Find series matching top genres
        $series = $this->db->fetchAll(
            "SELECT s.id, 'series' as content_type, s.title, s.vote_average, s.year,
                    s.poster_url, s.community_rating, c.name as category_name
             FROM series s
             LEFT JOIN categories c ON s.category_id = c.id
             WHERE s.status = 'published'
               AND c.name IN (" . implode(',', array_fill(0, count($topGenres), '?')) . ")
             ORDER BY COALESCE(s.community_rating, s.vote_average) DESC, s.created_at DESC
             LIMIT 20",
            $topGenres
        );

        // Merge and score
        $items = [];
        foreach (array_merge($movies, $series) as $item) {
            $key = $item['content_type'] . ':' . $item['id'];
            if (isset($watched[$key])) continue;

            $genreScore = $genreAffinities[$item['category_name']] ?? 0;
            $ratingScore = (float) ($item['community_rating'] ?? $item['vote_average'] ?? 0);
            $item['score'] = round(($genreScore * 0.6) + ($ratingScore * 0.4), 3);
            $item['match_percent'] = min(99, max(60, (int) ($item['score'] * 10 + 50)));
            $items[] = $item;
        }

        usort($items, fn($a, $b) => $b['score'] <=> $a['score']);
        $items = array_slice($items, 0, 20);

        if (empty($items)) return false;

        return $this->saveRecommendationSet(
            $subscriberId,
            self::SET_FOR_YOU,
            'Recommended For You',
            'Based on your viewing history and preferences',
            $items,
            1, // high priority
            24 // expires in 24 hours
        );
    }

    /**
     * "Because You Watched X" — find similar content for recently watched items
     */
    private function generateBecauseYouWatched(int $subscriberId): int
    {
        // Get last 5 completed or highly-watched items
        $recentWatched = $this->db->fetchAll(
            "SELECT wh.content_type, wh.content_id,
                    COALESCE(m.title, s.title) as title,
                    COALESCE(m.category_id, s.category_id) as category_id,
                    COALESCE(mc.name, sc.name) as category_name
             FROM subscriber_watch_history wh
             LEFT JOIN movies m ON wh.content_type = 'movie' AND wh.content_id = m.id
             LEFT JOIN series s ON wh.content_type = 'episode' AND wh.content_id = s.id
             LEFT JOIN categories mc ON m.category_id = mc.id
             LEFT JOIN categories sc ON s.category_id = sc.id
             WHERE wh.subscriber_id = ? AND wh.completed = 1
             ORDER BY wh.last_watched_at DESC
             LIMIT 5",
            [$subscriberId]
        );

        $watched = $this->getWatchedContentIds($subscriberId);
        $setsCreated = 0;

        // Clear old "because you watched" sets
        $this->db->execute(
            "DELETE FROM recommendation_sets WHERE subscriber_id = ? AND set_type = ?",
            [$subscriberId, self::SET_BECAUSE_WATCHED]
        );

        foreach ($recentWatched as $source) {
            if (!$source['category_id'] || !$source['title']) continue;

            // Find similar content in same category
            $similar = [];

            if ($source['content_type'] === 'movie') {
                $similar = $this->db->fetchAll(
                    "SELECT m.id, 'movie' as content_type, m.title, m.vote_average, m.year,
                            m.poster_url, m.community_rating
                     FROM movies m
                     WHERE m.status = 'published' AND m.category_id = ? AND m.id != ?
                     ORDER BY COALESCE(m.community_rating, m.vote_average) DESC
                     LIMIT 15",
                    [$source['category_id'], $source['content_id']]
                );
            } else {
                $similar = $this->db->fetchAll(
                    "SELECT s.id, 'series' as content_type, s.title, s.vote_average, s.year,
                            s.poster_url, s.community_rating
                     FROM series s
                     WHERE s.status = 'published' AND s.category_id = ? AND s.id != ?
                     ORDER BY COALESCE(s.community_rating, s.vote_average) DESC
                     LIMIT 15",
                    [$source['category_id'], $source['content_id']]
                );
            }

            // Filter out already watched
            $items = [];
            foreach ($similar as $item) {
                $key = $item['content_type'] . ':' . $item['id'];
                if (isset($watched[$key])) continue;
                $item['score'] = (float) ($item['community_rating'] ?? $item['vote_average'] ?? 0) / 10;
                $item['match_percent'] = min(95, max(55, (int) ($item['score'] * 100)));
                $items[] = $item;
            }

            if (count($items) < 3) continue;
            $items = array_slice($items, 0, 10);

            $this->saveRecommendationSet(
                $subscriberId,
                self::SET_BECAUSE_WATCHED,
                'Because You Watched ' . $source['title'],
                'Similar content in ' . ($source['category_name'] ?? 'this genre'),
                $items,
                3,
                48, // expires in 48 hours
                $source['content_type'] === 'movie' ? 'movie' : 'series',
                (int) $source['content_id']
            );
            $setsCreated++;

            if ($setsCreated >= 3) break; // Max 3 "because you watched" sets
        }

        return $setsCreated;
    }

    /**
     * "Trending Now" — most viewed content in last 24-48 hours
     */
    private function generateTrending(int $subscriberId): bool
    {
        $watched = $this->getWatchedContentIds($subscriberId);

        // Get most-viewed movies in last 48 hours from watch history
        $movies = $this->db->fetchAll(
            "SELECT m.id, 'movie' as content_type, m.title, m.vote_average, m.year,
                    m.poster_url, m.community_rating, COUNT(wh.id) as recent_views
             FROM subscriber_watch_history wh
             JOIN movies m ON wh.content_type = 'movie' AND wh.content_id = m.id AND m.status = 'published'
             WHERE wh.last_watched_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
             GROUP BY m.id
             ORDER BY recent_views DESC
             LIMIT 15"
        );

        $series = $this->db->fetchAll(
            "SELECT s.id, 'series' as content_type, s.title, s.vote_average, s.year,
                    s.poster_url, s.community_rating, COUNT(wh.id) as recent_views
             FROM subscriber_watch_history wh
             JOIN series s ON wh.content_type = 'episode' AND wh.content_id = s.id AND s.status = 'published'
             WHERE wh.last_watched_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
             GROUP BY s.id
             ORDER BY recent_views DESC
             LIMIT 10"
        );

        $items = [];
        foreach (array_merge($movies, $series) as $item) {
            $key = $item['content_type'] . ':' . $item['id'];
            if (isset($watched[$key])) continue;
            $item['score'] = (float) ($item['recent_views'] ?? 0) / 100;
            $items[] = $item;
        }

        if (empty($items)) return false;

        return $this->saveRecommendationSet(
            $subscriberId,
            self::SET_TRENDING,
            'Trending Now',
            'Popular with viewers right now',
            array_slice($items, 0, 15),
            2,
            12 // refresh every 12 hours
        );
    }

    /**
     * "Top Picks" — highest rated content matching user profile
     */
    private function generateTopPicks(int $subscriberId): bool
    {
        $watched = $this->getWatchedContentIds($subscriberId);

        $movies = $this->db->fetchAll(
            "SELECT m.id, 'movie' as content_type, m.title, m.vote_average, m.year,
                    m.poster_url, m.community_rating
             FROM movies m
             WHERE m.status = 'published'
               AND COALESCE(m.community_rating, m.vote_average) >= 7.0
             ORDER BY COALESCE(m.community_rating, m.vote_average) DESC
             LIMIT 15"
        );

        $series = $this->db->fetchAll(
            "SELECT s.id, 'series' as content_type, s.title, s.vote_average, s.year,
                    s.poster_url, s.community_rating
             FROM series s
             WHERE s.status = 'published'
               AND COALESCE(s.community_rating, s.vote_average) >= 7.0
             ORDER BY COALESCE(s.community_rating, s.vote_average) DESC
             LIMIT 10"
        );

        $items = [];
        foreach (array_merge($movies, $series) as $item) {
            $key = $item['content_type'] . ':' . $item['id'];
            if (isset($watched[$key])) continue;
            $item['score'] = (float) ($item['community_rating'] ?? $item['vote_average'] ?? 0) / 10;
            $items[] = $item;
        }

        if (empty($items)) return false;

        return $this->saveRecommendationSet(
            $subscriberId,
            self::SET_TOP_PICKS,
            'Top Picks',
            'Highest rated content you haven\'t seen yet',
            array_slice($items, 0, 15),
            4,
            24
        );
    }

    /**
     * "Hidden Gems" — high-rated but low-view content
     */
    private function generateHiddenGems(int $subscriberId): bool
    {
        $watched = $this->getWatchedContentIds($subscriberId);

        $movies = $this->db->fetchAll(
            "SELECT m.id, 'movie' as content_type, m.title, m.vote_average, m.year,
                    m.poster_url, m.community_rating,
                    (SELECT COUNT(*) FROM subscriber_watch_history wh WHERE wh.content_type = 'movie' AND wh.content_id = m.id) as view_count
             FROM movies m
             WHERE m.status = 'published'
               AND COALESCE(m.community_rating, m.vote_average) >= 6.5
             HAVING view_count < 10
             ORDER BY COALESCE(m.community_rating, m.vote_average) DESC
             LIMIT 15"
        );

        $items = [];
        foreach ($movies as $item) {
            $key = 'movie:' . $item['id'];
            if (isset($watched[$key])) continue;
            $item['score'] = (float) ($item['community_rating'] ?? $item['vote_average'] ?? 0) / 10;
            $items[] = $item;
        }

        if (count($items) < 3) return false;

        return $this->saveRecommendationSet(
            $subscriberId,
            self::SET_HIDDEN_GEMS,
            'Hidden Gems',
            'Great content that deserves more attention',
            array_slice($items, 0, 10),
            5,
            48
        );
    }

    /**
     * "New Releases" — recently added content
     */
    private function generateNewReleases(int $subscriberId): bool
    {
        $watched = $this->getWatchedContentIds($subscriberId);

        $movies = $this->db->fetchAll(
            "SELECT m.id, 'movie' as content_type, m.title, m.vote_average, m.year,
                    m.poster_url, m.community_rating
             FROM movies m
             WHERE m.status = 'published'
               AND m.created_at > DATE_SUB(NOW(), INTERVAL 14 DAY)
             ORDER BY m.created_at DESC
             LIMIT 15"
        );

        $series = $this->db->fetchAll(
            "SELECT s.id, 'series' as content_type, s.title, s.vote_average, s.year,
                    s.poster_url, s.community_rating
             FROM series s
             WHERE s.status = 'published'
               AND s.created_at > DATE_SUB(NOW(), INTERVAL 14 DAY)
             ORDER BY s.created_at DESC
             LIMIT 10"
        );

        $items = [];
        foreach (array_merge($movies, $series) as $item) {
            $key = $item['content_type'] . ':' . $item['id'];
            if (isset($watched[$key])) continue;
            $item['score'] = 0.5;
            $items[] = $item;
        }

        if (empty($items)) return false;

        return $this->saveRecommendationSet(
            $subscriberId,
            self::SET_NEW_RELEASES,
            'New Releases',
            'Recently added to the library',
            array_slice($items, 0, 15),
            3,
            24
        );
    }

    // =========================================================================
    // RETRIEVAL (called by API for player consumption)
    // =========================================================================

    /**
     * Get all recommendation sets for a subscriber (what the player fetches)
     */
    public function getRecommendations(int $subscriberId, ?string $filterType = null): array
    {
        $sql = "SELECT rs.*, ri.id as item_id, ri.content_type, ri.content_id, ri.score,
                       ri.match_percent, ri.reason as item_reason, ri.sort_order
                FROM recommendation_sets rs
                JOIN recommendation_items ri ON ri.set_id = rs.id
                WHERE rs.subscriber_id = ?
                  AND (rs.expires_at IS NULL OR rs.expires_at > NOW())
                ORDER BY rs.priority ASC, rs.id ASC, ri.sort_order ASC";
        $rows = $this->db->fetchAll($sql, [$subscriberId]);

        // Group into sets with items
        $sets = [];
        foreach ($rows as $row) {
            $setId = $row['id'];
            if (!isset($sets[$setId])) {
                $sets[$setId] = [
                    'id' => $setId,
                    'set_type' => $row['set_type'],
                    'title' => $row['title'],
                    'reason' => $row['reason'],
                    'priority' => (int) $row['priority'],
                    'items' => [],
                ];
            }
            $sets[$setId]['items'][] = [
                'content_type' => $row['content_type'],
                'content_id' => (int) $row['content_id'],
                'score' => (float) $row['score'],
                'match_percent' => $row['match_percent'] ? (int) $row['match_percent'] : null,
                'reason' => $row['item_reason'],
            ];
        }

        $sets = array_values($sets);

        // Filter by content type if requested
        if ($filterType === 'movie' || $filterType === 'series') {
            foreach ($sets as &$set) {
                $set['items'] = array_values(array_filter(
                    $set['items'],
                    fn($i) => $i['content_type'] === $filterType
                ));
            }
            // Remove empty sets
            $sets = array_values(array_filter($sets, fn($s) => !empty($s['items'])));
        }

        return $sets;
    }

    /**
     * Get recommendations enriched with full content details (for the player)
     */
    public function getEnrichedRecommendations(int $subscriberId, ?string $filterType = null): array
    {
        $sets = $this->getRecommendations($subscriberId, $filterType);

        // Collect all content IDs to batch-fetch details
        $movieIds = [];
        $seriesIds = [];
        foreach ($sets as $set) {
            foreach ($set['items'] as $item) {
                if ($item['content_type'] === 'movie') $movieIds[] = $item['content_id'];
                elseif ($item['content_type'] === 'series') $seriesIds[] = $item['content_id'];
            }
        }

        // Batch fetch movie details
        $movieDetails = [];
        if ($movieIds) {
            $placeholders = implode(',', array_fill(0, count($movieIds), '?'));
            $rows = $this->db->fetchAll(
                "SELECT id, title, poster_url, backdrop_url, vote_average, year, runtime, genres,
                        community_rating, community_rating_count, synopsis, status
                 FROM movies WHERE id IN ($placeholders) AND status = 'published'",
                $movieIds
            );
            foreach ($rows as $r) {
                $r['content_type'] = 'movie';
                if (!empty($r['community_rating'])) {
                    $r['vote_average'] = (float) $r['community_rating'];
                }
                $movieDetails[$r['id']] = $r;
            }
        }

        // Batch fetch series details
        $seriesDetails = [];
        if ($seriesIds) {
            $placeholders = implode(',', array_fill(0, count($seriesIds), '?'));
            $rows = $this->db->fetchAll(
                "SELECT id, title, poster_url, backdrop_url, vote_average, year, genres,
                        community_rating, community_rating_count, synopsis, status
                 FROM series WHERE id IN ($placeholders) AND status = 'published'",
                $seriesIds
            );
            foreach ($rows as $r) {
                $r['content_type'] = 'series';
                if (!empty($r['community_rating'])) {
                    $r['vote_average'] = (float) $r['community_rating'];
                }
                $seriesDetails[$r['id']] = $r;
            }
        }

        // Enrich items with content details
        foreach ($sets as &$set) {
            $enrichedItems = [];
            foreach ($set['items'] as $item) {
                $details = null;
                if ($item['content_type'] === 'movie') {
                    $details = $movieDetails[$item['content_id']] ?? null;
                } elseif ($item['content_type'] === 'series') {
                    $details = $seriesDetails[$item['content_id']] ?? null;
                }
                if ($details) {
                    $enrichedItems[] = array_merge($details, [
                        'match_percent' => $item['match_percent'],
                        'recommendation_reason' => $item['reason'],
                    ]);
                }
            }
            $set['items'] = $enrichedItems;
        }

        // Remove sets with no valid items
        return array_values(array_filter($sets, fn($s) => !empty($s['items'])));
    }

    /**
     * Get subscriber's taste profile
     */
    public function getProfile(int $subscriberId): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM subscriber_profiles WHERE subscriber_id = ?",
            [$subscriberId]
        );
    }

    /**
     * Check if recommendations need refreshing
     */
    public function needsRefresh(int $subscriberId): bool
    {
        $profile = $this->getProfile($subscriberId);
        if (!$profile || !$profile['last_analyzed_at']) return true;

        $lastAnalyzed = strtotime($profile['last_analyzed_at']);
        return (time() - $lastAnalyzed) > 86400; // 24 hours
    }

    // =========================================================================
    // BATCH PROCESSING (for cron job)
    // =========================================================================

    /**
     * Process all subscribers who need profile/recommendation refresh.
     * Call from a cron job: php artisan recommendations:generate
     */
    public function processAllSubscribers(int $limit = 50): array
    {
        // Get subscribers with watch activity who need refresh
        $subscribers = $this->db->fetchAll(
            "SELECT DISTINCT s.id
             FROM subscribers s
             JOIN subscriber_watch_history wh ON wh.subscriber_id = s.id
             LEFT JOIN subscriber_profiles sp ON sp.subscriber_id = s.id
             WHERE s.status = 'active'
               AND (sp.last_analyzed_at IS NULL OR sp.last_analyzed_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))
             ORDER BY sp.last_analyzed_at ASC
             LIMIT ?",
            [$limit]
        );

        $results = ['processed' => 0, 'profiles' => 0, 'recommendations' => 0, 'errors' => 0];

        foreach ($subscribers as $sub) {
            try {
                $this->generateProfile($sub['id']);
                $results['profiles']++;

                $sets = $this->generateRecommendations($sub['id']);
                $results['recommendations'] += $sets;

                $results['processed']++;
            } catch (\Throwable $e) {
                error_log('Recommendation generation failed for subscriber ' . $sub['id'] . ': ' . $e->getMessage());
                $results['errors']++;
            }
        }

        return $results;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function getWatchedContentIds(int $subscriberId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT content_type, content_id FROM subscriber_watch_history WHERE subscriber_id = ?",
            [$subscriberId]
        );
        $map = [];
        foreach ($rows as $r) {
            $map[$r['content_type'] . ':' . $r['content_id']] = true;
        }
        return $map;
    }

    private function saveRecommendationSet(
        int $subscriberId,
        string $setType,
        string $title,
        string $reason,
        array $items,
        int $priority = 5,
        int $expiresInHours = 24,
        ?string $sourceContentType = null,
        ?int $sourceContentId = null
    ): bool {
        if (empty($items)) return false;

        // Delete existing set of this type (except because_watched which can have multiple)
        if ($setType !== self::SET_BECAUSE_WATCHED) {
            $this->db->execute(
                "DELETE FROM recommendation_sets WHERE subscriber_id = ? AND set_type = ?",
                [$subscriberId, $setType]
            );
        }

        $this->db->execute(
            "INSERT INTO recommendation_sets
                (subscriber_id, set_type, title, reason, source_content_type, source_content_id, priority, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))",
            [$subscriberId, $setType, $title, $reason, $sourceContentType, $sourceContentId, $priority, $expiresInHours]
        );
        $setId = $this->db->lastInsertId();

        foreach ($items as $i => $item) {
            $this->db->execute(
                "INSERT INTO recommendation_items (set_id, content_type, content_id, score, match_percent, reason, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $setId,
                    $item['content_type'],
                    $item['id'],
                    $item['score'] ?? 0,
                    $item['match_percent'] ?? null,
                    $item['reason'] ?? null,
                    $i,
                ]
            );
        }

        return true;
    }

    private function calcCompletionRate(array $stats): int
    {
        $started = (int) ($stats['started'] ?? 0);
        $completed = (int) ($stats['completed'] ?? 0);
        return $started > 0 ? (int) round(($completed / $started) * 100) : 0;
    }

    private function calcAvgSession(array $stats): float
    {
        $sessions = (int) ($stats['sessions'] ?? 0);
        $events = (int) ($stats['total_events'] ?? 0);
        return $sessions > 0 ? round($events / $sessions, 1) : 0;
    }
}
