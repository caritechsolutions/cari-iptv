<?php
/**
 * CARI-IPTV EPG API Controller
 * Public endpoints for Electronic Programme Guide data
 */

namespace CariIPTV\Controllers\Api;

use CariIPTV\Services\ContentApiService;

class EpgController extends BaseApiController
{
    private ContentApiService $service;

    public function __construct()
    {
        $this->service = new ContentApiService();
    }

    /**
     * GET /api/v1/epg
     * ?channel_id=5&date=2025-01-15&limit=500
     * Returns EPG programmes. Without channel_id returns all channels' programmes.
     */
    public function index(): void
    {
        $filters = $this->queryFilters([
            'channel_id' => null,
            'date' => null,
            'limit' => 500,
        ]);

        try {
            $programmes = $this->service->getEpg($filters);
        } catch (\Throwable $e) {
            $this->ok([], ['total' => 0, 'message' => 'EPG data not available']);
            return;
        }

        // Convert datetime fields to ISO 8601 UTC format
        $programmes = array_map([$this, 'formatEpgTimestamps'], $programmes);

        // Group by channel if no specific channel requested
        if (empty($filters['channel_id'])) {
            $grouped = [];
            foreach ($programmes as $p) {
                $chId = $p['channel_id'];
                if (!isset($grouped[$chId])) {
                    $grouped[$chId] = [
                        'channel_id' => (int)$chId,
                        'channel_name' => $p['channel_name'],
                        'programmes' => [],
                    ];
                }
                unset($p['channel_name']);
                $grouped[$chId]['programmes'][] = $p;
            }
            $programmes = array_values($grouped);
        }

        $this->ok($programmes, [
            'total' => count($programmes),
            'date' => $filters['date'] ?? date('Y-m-d'),
            'timezone' => 'UTC',
        ]);
    }

    /**
     * GET /api/v1/epg/{channelId}
     * ?date=2025-01-15
     * Returns EPG for a specific channel
     */
    public function channel(string $channelId): void
    {
        $filters = [
            'channel_id' => (int)$channelId,
            'date' => $this->query('date'),
            'limit' => 200,
        ];

        try {
            $programmes = $this->service->getEpg($filters);
        } catch (\Throwable $e) {
            $this->ok([], ['total' => 0, 'channel_id' => (int)$channelId]);
            return;
        }

        // Convert datetime fields to ISO 8601 UTC format
        $programmes = array_map([$this, 'formatEpgTimestamps'], $programmes);

        $this->ok($programmes, [
            'channel_id' => (int)$channelId,
            'total' => count($programmes),
            'date' => $filters['date'] ?? date('Y-m-d'),
            'timezone' => 'UTC',
        ]);
    }

    /**
     * GET /api/v1/epg/programme-info?title=...&description=...&channel_id=...
     * Returns cached TMDB metadata for a programme title.
     * If not cached, returns null immediately and queues for background enrichment.
     * Channel context + description help AI pick the correct TMDB match during enrichment.
     */
    public function programmeInfo(): void
    {
        $title = trim($this->query('title', ''));
        if (empty($title)) {
            $this->error('Title parameter is required', 400);
            return;
        }

        // Only return cached data — no live TMDB lookups on user click
        $epgMetadata = new \CariIPTV\Services\EpgMetadataService();
        $cached = $epgMetadata->getCachedMetadata($title);
        if ($cached) {
            $this->ok($cached, ['source' => 'cache']);
            return;
        }

        // Not cached — queue for background enrichment and return immediately
        $epgMetadata->queueTitlesForEnrichment([$title]);
        $this->ok(null, ['source' => 'pending', 'message' => 'Queued for enrichment']);
    }

    /**
     * Convert MySQL DATETIME fields to ISO 8601 UTC format (with Z suffix)
     * so JavaScript correctly interprets them as UTC timestamps.
     */
    private function formatEpgTimestamps(array $programme): array
    {
        foreach (['start_time', 'end_time'] as $field) {
            if (!empty($programme[$field])) {
                // Convert "2025-01-15 14:00:00" to "2025-01-15T14:00:00Z"
                $programme[$field] = str_replace(' ', 'T', $programme[$field]) . 'Z';
            }
        }
        return $programme;
    }
}
