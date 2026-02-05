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

        $this->ok($programmes, [
            'channel_id' => (int)$channelId,
            'total' => count($programmes),
            'date' => $filters['date'] ?? date('Y-m-d'),
        ]);
    }
}
