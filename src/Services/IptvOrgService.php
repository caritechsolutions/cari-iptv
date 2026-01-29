<?php
/**
 * CARI-IPTV IPTV-org API Service
 * Integration with the iptv-org open channel database
 * https://github.com/iptv-org/iptv
 */

namespace CariIPTV\Services;

class IptvOrgService
{
    private const API_BASE = 'https://iptv-org.github.io/api/';
    private const CACHE_DIR = BASE_PATH . '/storage/cache/iptv-org';
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Search channels from iptv-org API
     */
    public function searchChannels(array $filters = []): array
    {
        $channels = $this->getApiData('channels');
        $streams = $this->getStreamIndex();
        $logos = $this->getLogoIndex();

        if ($channels === null) {
            return ['success' => false, 'message' => 'Failed to fetch channel data from iptv-org', 'results' => []];
        }

        // Apply filters
        $results = [];
        foreach ($channels as $ch) {
            // Country filter
            if (!empty($filters['country']) && $ch['country'] !== $filters['country']) {
                continue;
            }

            // Category filter
            if (!empty($filters['category'])) {
                if (!in_array($filters['category'], $ch['categories'] ?? [])) {
                    continue;
                }
            }

            // Skip NSFW
            if ($ch['is_nsfw'] ?? false) {
                continue;
            }

            // Skip closed channels
            if (!empty($ch['closed'])) {
                continue;
            }

            // Search filter (name)
            if (!empty($filters['search'])) {
                $search = strtolower($filters['search']);
                $name = strtolower($ch['name']);
                $altNames = array_map('strtolower', $ch['alt_names'] ?? []);
                $found = str_contains($name, $search);
                if (!$found) {
                    foreach ($altNames as $alt) {
                        if (str_contains($alt, $search)) {
                            $found = true;
                            break;
                        }
                    }
                }
                if (!$found) continue;
            }

            // Only include channels that have streams (unless searching by name specifically)
            $channelStreams = $streams[$ch['id']] ?? [];
            if (empty($filters['search']) && empty($channelStreams)) {
                continue;
            }

            $logo = $logos[$ch['id']] ?? null;
            $bestStream = $this->pickBestStream($channelStreams);

            $results[] = [
                'id' => $ch['id'],
                'name' => $ch['name'],
                'alt_names' => $ch['alt_names'] ?? [],
                'country' => $ch['country'],
                'categories' => $ch['categories'] ?? [],
                'network' => $ch['network'],
                'website' => $ch['website'],
                'logo_url' => $logo['url'] ?? null,
                'has_stream' => !empty($channelStreams),
                'stream_count' => count($channelStreams),
                'stream_url' => $bestStream['url'] ?? null,
                'stream_quality' => $bestStream['quality'] ?? null,
                'streams' => array_map(function($s) {
                    return [
                        'url' => $s['url'],
                        'quality' => $s['quality'],
                        'user_agent' => $s['user_agent'],
                        'referrer' => $s['referrer'],
                    ];
                }, $channelStreams),
            ];
        }

        // Sort: channels with streams first, then by name
        usort($results, function($a, $b) {
            if ($a['has_stream'] !== $b['has_stream']) {
                return $b['has_stream'] <=> $a['has_stream'];
            }
            return strcasecmp($a['name'], $b['name']);
        });

        // Limit results
        $limit = (int) ($filters['limit'] ?? 100);
        $total = count($results);
        $results = array_slice($results, 0, $limit);

        return [
            'success' => true,
            'total' => $total,
            'showing' => count($results),
            'results' => $results,
        ];
    }

    /**
     * Get list of countries that have channels
     */
    public function getCountries(): array
    {
        $countries = $this->getApiData('countries');
        if (!$countries) return [];

        $result = [];
        foreach ($countries as $c) {
            $result[] = [
                'code' => $c['code'],
                'name' => $c['name'],
                'flag' => $c['flag'] ?? '',
            ];
        }

        usort($result, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $result;
    }

    /**
     * Get iptv-org categories
     */
    public function getCategories(): array
    {
        $categories = $this->getApiData('categories');
        if (!$categories) return [];
        return $categories;
    }

    /**
     * Pick the best stream from a list (prefer higher quality)
     */
    private function pickBestStream(array $streams): ?array
    {
        if (empty($streams)) return null;

        $qualityOrder = ['4k' => 5, '2160p' => 5, '1080p' => 4, '720p' => 3, '576p' => 2, '480p' => 1, '360p' => 0];

        usort($streams, function($a, $b) use ($qualityOrder) {
            $qa = $qualityOrder[strtolower($a['quality'] ?? '')] ?? -1;
            $qb = $qualityOrder[strtolower($b['quality'] ?? '')] ?? -1;
            return $qb <=> $qa;
        });

        return $streams[0];
    }

    /**
     * Build stream index keyed by channel ID
     */
    private function getStreamIndex(): array
    {
        $streams = $this->getApiData('streams');
        if (!$streams) return [];

        $index = [];
        foreach ($streams as $s) {
            if (!empty($s['channel'])) {
                $index[$s['channel']][] = $s;
            }
        }
        return $index;
    }

    /**
     * Build logo index keyed by channel ID (pick largest)
     */
    private function getLogoIndex(): array
    {
        $logos = $this->getApiData('logos');
        if (!$logos) return [];

        $index = [];
        foreach ($logos as $l) {
            if (empty($l['channel'])) continue;
            $cid = $l['channel'];
            // Keep the largest logo
            if (!isset($index[$cid]) || ($l['width'] ?? 0) > ($index[$cid]['width'] ?? 0)) {
                $index[$cid] = $l;
            }
        }
        return $index;
    }

    /**
     * Fetch and cache API data
     */
    private function getApiData(string $endpoint): ?array
    {
        $cacheFile = self::CACHE_DIR . "/{$endpoint}.json";

        // Check cache
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < self::CACHE_TTL) {
            $data = file_get_contents($cacheFile);
            $decoded = json_decode($data, true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        // Fetch from API
        $url = self::API_BASE . "{$endpoint}.json";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_USERAGENT => 'CARI-IPTV/1.0',
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            // Try returning stale cache if available
            if (file_exists($cacheFile)) {
                $decoded = json_decode(file_get_contents($cacheFile), true);
                if ($decoded !== null) return $decoded;
            }
            return null;
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) return null;

        // Write cache
        if (!is_dir(self::CACHE_DIR)) {
            mkdir(self::CACHE_DIR, 0775, true);
        }
        file_put_contents($cacheFile, $response);

        return $decoded;
    }

    /**
     * Clear cached API data
     */
    public function clearCache(): void
    {
        if (!is_dir(self::CACHE_DIR)) return;
        $files = glob(self::CACHE_DIR . '/*.json');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}
