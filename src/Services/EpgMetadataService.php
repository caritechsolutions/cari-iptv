<?php
/**
 * CARI-IPTV EPG Metadata Service
 * Enriches EPG programme titles with TMDB metadata and caches locally.
 * Images are processed through ImageService for WebP conversion.
 */

namespace CariIPTV\Services;

use CariIPTV\Core\Database;

class EpgMetadataService
{
    private Database $db;
    private MetadataService $metadata;
    private ImageService $imageService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->metadata = new MetadataService();
        $this->imageService = new ImageService();
    }

    /**
     * Normalise a programme title for deduplication.
     * Strips common suffixes like "(R)", "New:", episode numbers, etc.
     */
    public static function normaliseTitle(string $title): string
    {
        $t = trim($title);
        // Strip common broadcast annotations
        $t = preg_replace('/\s*\((?:R|repeat|live|new|premiere)\)\s*$/i', '', $t);
        $t = preg_replace('/\s*-\s*(?:Series|Season|Episode|Ep\.?|S\d+E\d+)[\s\d:.]*/i', '', $t);
        $t = preg_replace('/\s*:\s*(?:Series|Season|Episode)\s+\d+.*$/i', '', $t);
        $t = mb_strtolower(trim($t), 'UTF-8');
        return $t;
    }

    /**
     * Get cached metadata for a programme title.
     * Returns null if not cached or still pending.
     */
    public function getCachedMetadata(string $title): ?array
    {
        $normalised = self::normaliseTitle($title);
        if (empty($normalised)) return null;

        $row = $this->db->fetch(
            "SELECT * FROM epg_programme_metadata WHERE title_normalised = ? AND status = 'complete'",
            [$normalised]
        );

        if (!$row) return null;

        // Fetch cast
        $cast = $this->db->fetchAll(
            "SELECT * FROM epg_programme_cast WHERE metadata_id = ? ORDER BY sort_order ASC",
            [$row['id']]
        );

        return [
            'match' => [
                'tmdb_id' => (int)$row['tmdb_id'],
                'title' => $row['title_display'],
                'overview' => $row['overview'],
                'year' => $row['year'],
                'vote_average' => (float)$row['vote_average'],
                'genres' => $row['genres'] ? explode(',', $row['genres']) : [],
                'runtime' => $row['runtime'] ? (int)$row['runtime'] : null,
                'number_of_seasons' => $row['number_of_seasons'] ? (int)$row['number_of_seasons'] : null,
                'directors' => $row['directors'] ? explode(',', $row['directors']) : [],
                'poster' => $row['poster_local'] ?: $row['poster_url'],
                'backdrop' => $row['backdrop_local'] ?: $row['backdrop_url'],
                'cast' => array_map(function ($c) {
                    return [
                        'name' => $c['name'],
                        'character_name' => $c['character_name'],
                        'profile_image' => $c['profile_image'] ?: $c['profile_url'],
                        'tmdb_person_id' => $c['tmdb_person_id'] ? (int)$c['tmdb_person_id'] : null,
                    ];
                }, $cast),
            ],
            'media_type' => $row['media_type'],
        ];
    }

    /**
     * Queue unique programme titles from an EPG import for TMDB enrichment.
     * Only queues titles that don't already have a metadata record.
     */
    public function queueTitlesForEnrichment(array $titles): int
    {
        $queued = 0;
        $seen = [];

        foreach ($titles as $title) {
            $normalised = self::normaliseTitle($title);
            if (empty($normalised) || isset($seen[$normalised])) continue;
            $seen[$normalised] = true;

            // Check if already exists
            $existing = $this->db->fetch(
                "SELECT id FROM epg_programme_metadata WHERE title_normalised = ?",
                [$normalised]
            );

            if (!$existing) {
                $this->db->execute(
                    "INSERT IGNORE INTO epg_programme_metadata (title_normalised, title_display, status) VALUES (?, ?, 'pending')",
                    [$normalised, trim($title)]
                );
                $queued++;
            }
        }

        return $queued;
    }

    /**
     * Process pending metadata records — fetch from TMDB and download images.
     * Call this after EPG import or as a cron job.
     *
     * @param int $limit Max records to process per run (to avoid timeout)
     * @return array Stats about the processing run
     */
    public function processPending(int $limit = 50): array
    {
        if (!$this->metadata->isTmdbConfigured()) {
            return ['processed' => 0, 'error' => 'TMDB not configured'];
        }

        $pending = $this->db->fetchAll(
            "SELECT id, title_normalised, title_display FROM epg_programme_metadata WHERE status = 'pending' ORDER BY created_at ASC LIMIT ?",
            [$limit]
        );

        $stats = ['processed' => 0, 'found' => 0, 'not_found' => 0, 'errors' => 0];

        foreach ($pending as $record) {
            try {
                $this->enrichRecord($record);
                $stats['processed']++;

                $updated = $this->db->fetch("SELECT status FROM epg_programme_metadata WHERE id = ?", [$record['id']]);
                if ($updated && $updated['status'] === 'complete') {
                    $stats['found']++;
                } else {
                    $stats['not_found']++;
                }

                // Small delay to respect TMDB rate limits
                usleep(250000); // 250ms
            } catch (\Throwable $e) {
                error_log("[EpgMetadata] Error processing '{$record['title_display']}': " . $e->getMessage());
                $this->db->execute(
                    "UPDATE epg_programme_metadata SET status = 'error' WHERE id = ?",
                    [$record['id']]
                );
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /**
     * Enrich a single metadata record with TMDB data and download images.
     * Looks up EPG description + channel context to help AI pick the correct TMDB match.
     */
    private function enrichRecord(array $record): void
    {
        // Grab description AND channel info from any EPG programme with this title
        // Use LIKE match since title_normalised may have stripped suffixes
        $epgContext = $this->db->fetch(
            "SELECT p.description, c.name AS channel_name, c.description AS channel_description
             FROM epg_programs p
             LEFT JOIN channels c ON p.channel_id = c.id
             WHERE LOWER(TRIM(p.title)) LIKE CONCAT(?, '%')
               AND p.description IS NOT NULL AND p.description != ''
             ORDER BY p.start_time DESC
             LIMIT 1",
            [$record['title_normalised']]
        );

        // Fallback: try without description requirement to at least get channel context
        if (!$epgContext) {
            $epgContext = $this->db->fetch(
                "SELECT p.description, c.name AS channel_name, c.description AS channel_description
                 FROM epg_programs p
                 LEFT JOIN channels c ON p.channel_id = c.id
                 WHERE LOWER(TRIM(p.title)) LIKE CONCAT(?, '%')
                 ORDER BY p.start_time DESC
                 LIMIT 1",
                [$record['title_normalised']]
            );
        }

        $description = $epgContext['description'] ?? '';
        $channelContext = [
            'name' => $epgContext['channel_name'] ?? '',
            'description' => $epgContext['channel_description'] ?? '',
        ];

        $result = $this->metadata->searchProgrammeInfo($record['title_display'], $description, $channelContext);

        if (isset($result['error']) || empty($result['match'])) {
            $this->db->execute(
                "UPDATE epg_programme_metadata SET status = 'not_found' WHERE id = ?",
                [$record['id']]
            );
            return;
        }

        $match = $result['match'];
        $mediaType = $result['media_type'] ?? null;

        // Download and process poster to WebP
        $posterLocal = null;
        $posterUrl = $match['poster'] ?? null;
        if ($posterUrl) {
            $posterLocal = $this->processImage($posterUrl, 'epg', $record['id'], 'poster');
        }

        // Download and process backdrop to WebP
        $backdropLocal = null;
        $backdropUrl = $match['backdrop'] ?? null;
        if ($backdropUrl) {
            $backdropLocal = $this->processImage($backdropUrl, 'epg', $record['id'], 'backdrop');
        }

        // Extract genres
        $genres = '';
        if (!empty($match['genres'])) {
            $genreNames = array_map(function ($g) {
                return is_array($g) ? ($g['name'] ?? '') : (string)$g;
            }, $match['genres']);
            $genres = implode(',', array_filter($genreNames));
        }

        // Extract directors
        $directors = '';
        if (!empty($match['directors'])) {
            $dirNames = array_map(function ($d) {
                return is_array($d) ? ($d['name'] ?? '') : (string)$d;
            }, $match['directors']);
            $directors = implode(',', array_filter($dirNames));
        }

        // Determine title for display
        $displayTitle = $match['title'] ?? $match['name'] ?? $record['title_display'];

        // Update the metadata record
        $this->db->execute(
            "UPDATE epg_programme_metadata SET
                tmdb_id = ?, media_type = ?, overview = ?, year = ?,
                vote_average = ?, genres = ?, runtime = ?, number_of_seasons = ?,
                directors = ?, poster_url = ?, poster_local = ?,
                backdrop_url = ?, backdrop_local = ?, title_display = ?,
                status = 'complete'
             WHERE id = ?",
            [
                $match['id'] ?? $match['tmdb_id'] ?? null,
                $mediaType,
                $match['overview'] ?? null,
                $match['year'] ?? null,
                $match['vote_average'] ?? null,
                $genres ?: null,
                $match['runtime'] ?? $match['episode_run_time'] ?? null,
                $match['number_of_seasons'] ?? null,
                $directors ?: null,
                $posterUrl,
                $posterLocal,
                $backdropUrl,
                $backdropLocal,
                $displayTitle,
                $record['id'],
            ]
        );

        // Save cast
        if (!empty($match['cast'])) {
            // Clear existing cast first
            $this->db->execute("DELETE FROM epg_programme_cast WHERE metadata_id = ?", [$record['id']]);

            foreach (array_slice($match['cast'], 0, 10) as $i => $castMember) {
                $profileUrl = $castMember['profile'] ?? $castMember['profile_url'] ?? null;
                $profileLocal = null;

                if ($profileUrl) {
                    $profileLocal = $this->processImage($profileUrl, 'cast', $record['id'] . '_' . $i, 'profile');
                }

                $this->db->execute(
                    "INSERT INTO epg_programme_cast (metadata_id, name, character_name, tmdb_person_id, profile_url, profile_image, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [
                        $record['id'],
                        $castMember['name'],
                        $castMember['character'] ?? $castMember['character_name'] ?? null,
                        $castMember['tmdb_person_id'] ?? null,
                        $profileUrl,
                        $profileLocal,
                        $i,
                    ]
                );
            }
        }
    }

    /**
     * Download a remote image and process it through ImageService for WebP conversion.
     * Returns the local WebP path or null on failure.
     */
    private function processImage(string $url, string $context, string|int $entityId, string $type): ?string
    {
        try {
            $result = $this->imageService->processFromUrl($url, $context, $entityId, $type);
            if ($result['success'] && !empty($result['variants'])) {
                // Return the largest variant available
                $variants = $result['variants'];
                // Prefer poster > backdrop > medium > thumb > any
                foreach (['poster', 'backdrop', 'banner', 'medium', 'thumb'] as $pref) {
                    if (isset($variants[$pref])) return $variants[$pref];
                }
                return reset($variants);
            }
        } catch (\Throwable $e) {
            error_log("[EpgMetadata] Image processing failed for {$url}: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Retry enrichment for records that previously failed or had no match.
     * Useful when TMDB was not configured during initial import.
     */
    public function retryFailed(int $limit = 50): array
    {
        $this->db->execute(
            "UPDATE epg_programme_metadata SET status = 'pending' WHERE status IN ('not_found', 'error') ORDER BY updated_at ASC LIMIT ?",
            [$limit]
        );
        return $this->processPending($limit);
    }
}
