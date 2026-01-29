<?php
/**
 * CARI-IPTV EPG Service
 * Manages EPG sources, parses EIT/XMLTV data, imports programmes
 */

namespace CariIPTV\Services;

use CariIPTV\Core\Database;

class EpgService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ========================================================================
    // SOURCE MANAGEMENT
    // ========================================================================

    public function getSources(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['type'])) {
            $where[] = 'type = ?';
            $params[] = $filters['type'];
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[] = 'is_active = ?';
            $params[] = (int) $filters['is_active'];
        }

        $whereClause = implode(' AND ', $where);

        return $this->db->fetchAll(
            "SELECT * FROM epg_sources WHERE {$whereClause} ORDER BY name",
            $params
        );
    }

    public function getSource(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM epg_sources WHERE id = ?", [$id]);
    }

    public function createSource(array $data): int
    {
        return $this->db->insert('epg_sources', [
            'name' => $data['name'],
            'type' => $data['type'],
            'source_url' => $data['source_url'] ?? null,
            'source_port' => !empty($data['source_port']) ? (int) $data['source_port'] : null,
            'eit_pid' => $data['eit_pid'] ?? '0x12',
            'capture_timeout' => (int) ($data['capture_timeout'] ?? 120),
            'is_active' => (int) ($data['is_active'] ?? 1),
            'auto_refresh' => (int) ($data['auto_refresh'] ?? 0),
            'refresh_interval' => (int) ($data['refresh_interval'] ?? 3600),
        ]);
    }

    public function updateSource(int $id, array $data): bool
    {
        $fields = [];
        $allowed = ['name', 'type', 'source_url', 'source_port', 'eit_pid',
                     'capture_timeout', 'is_active', 'auto_refresh', 'refresh_interval'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[$field] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $this->db->update('epg_sources', $fields, 'id = ?', [$id]);
        return true;
    }

    public function deleteSource(int $id): bool
    {
        // Delete programmes from this source
        $this->db->execute("DELETE FROM epg_programs WHERE epg_source_id = ?", [$id]);
        // Delete channel mappings
        $this->db->execute("DELETE FROM epg_channel_map WHERE epg_source_id = ?", [$id]);
        // Delete source
        return $this->db->delete('epg_sources', 'id = ?', [$id]) > 0;
    }

    public function updateSourceStatus(int $id, string $status, ?string $message = null): void
    {
        $data = [
            'last_status' => $status,
            'last_message' => $message,
        ];

        if ($status === 'success') {
            $data['last_fetch'] = date('Y-m-d H:i:s');
        }

        $this->db->update('epg_sources', $data, 'id = ?', [$id]);
    }

    // ========================================================================
    // CHANNEL MAPPING
    // ========================================================================

    public function getChannelMappings(int $sourceId): array
    {
        return $this->db->fetchAll(
            "SELECT m.*, c.name as channel_name, c.logo_url as channel_logo
             FROM epg_channel_map m
             LEFT JOIN channels c ON m.channel_id = c.id
             WHERE m.epg_source_id = ?
             ORDER BY m.epg_channel_name, m.epg_channel_id",
            [$sourceId]
        );
    }

    public function mapChannel(int $mappingId, ?int $channelId): bool
    {
        $this->db->update('epg_channel_map', [
            'channel_id' => $channelId,
            'is_mapped' => $channelId ? 1 : 0,
        ], 'id = ?', [$mappingId]);

        return true;
    }

    public function autoMapChannels(int $sourceId): int
    {
        $mappings = $this->db->fetchAll(
            "SELECT id, epg_channel_name FROM epg_channel_map
             WHERE epg_source_id = ? AND is_mapped = 0 AND epg_channel_name IS NOT NULL",
            [$sourceId]
        );

        $mapped = 0;
        foreach ($mappings as $m) {
            // Try exact name match first
            $channel = $this->db->fetch(
                "SELECT id FROM channels WHERE LOWER(name) = LOWER(?)",
                [$m['epg_channel_name']]
            );

            // Try partial match
            if (!$channel) {
                $channel = $this->db->fetch(
                    "SELECT id FROM channels WHERE LOWER(name) LIKE LOWER(?) LIMIT 1",
                    ['%' . $m['epg_channel_name'] . '%']
                );
            }

            if ($channel) {
                $this->db->update('epg_channel_map', [
                    'channel_id' => $channel['id'],
                    'is_mapped' => 1,
                ], 'id = ?', [$m['id']]);
                $mapped++;
            }
        }

        return $mapped;
    }

    // ========================================================================
    // EIT EXTRACTION (TSDuck)
    // ========================================================================

    /**
     * Run TSDuck EIT extraction for a source
     * Returns the path to the XML output file or null on failure
     */
    public function extractEit(array $source): ?string
    {
        if ($source['type'] !== 'eit') {
            return null;
        }

        $address = $source['source_url'];
        $port = (int) $source['source_port'];
        $pid = $source['eit_pid'] ?? '0x12';
        $timeout = (int) ($source['capture_timeout'] ?? 120);

        if (empty($address) || empty($port)) {
            $this->updateSourceStatus($source['id'], 'error', 'Missing stream address or port');
            return null;
        }

        // Verify TSDuck is installed
        $tspPath = trim(shell_exec('which tsp 2>/dev/null') ?? '');
        if (empty($tspPath)) {
            $this->updateSourceStatus($source['id'], 'error', 'TSDuck (tsp) not found. Install with: sudo apt install tsduck');
            return null;
        }

        $outputDir = BASE_PATH . '/storage/epg';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        // Output files for EIT and SDT data
        $eitFile = $outputDir . '/eit_' . $source['id'] . '_' . time() . '.xml';
        $sdtFile = $outputDir . '/sdt_' . $source['id'] . '_' . time() . '.xml';

        $this->updateSourceStatus($source['id'], 'running', 'Capturing EIT data...');

        // Extract EIT tables (PID 0x12) - programme data
        $eitPidDec = $this->pidToDecimal($pid);
        $eitCmd = sprintf(
            'timeout %d tsp -I ip %s:%d -P tables --pid %d --xml-output %s -O drop 2>&1',
            $timeout,
            escapeshellarg($address),
            $port,
            $eitPidDec,
            escapeshellarg($eitFile)
        );

        $eitOutput = shell_exec($eitCmd);

        // Extract SDT tables (PID 0x11 = 17) - service names
        $sdtCmd = sprintf(
            'timeout %d tsp -I ip %s:%d -P tables --pid 17 --xml-output %s -O drop 2>&1',
            min($timeout, 30), // SDT is quick, 30s is plenty
            escapeshellarg($address),
            $port,
            escapeshellarg($sdtFile)
        );

        $sdtOutput = shell_exec($sdtCmd);

        // Parse SDT for service names (if available)
        $serviceNames = [];
        if (file_exists($sdtFile) && filesize($sdtFile) > 0) {
            $serviceNames = $this->parseSdtXml($sdtFile);
            unlink($sdtFile);
        }

        // Validate EIT output
        if (!file_exists($eitFile) || filesize($eitFile) === 0) {
            $this->updateSourceStatus($source['id'], 'error',
                'No EIT data captured. Check stream address and that PID ' . $pid . ' carries EIT tables.');
            if (file_exists($eitFile)) unlink($eitFile);
            return null;
        }

        // Import the EIT data
        $result = $this->importEitXml($source['id'], $eitFile, $serviceNames);

        // Clean up
        unlink($eitFile);

        if ($result['success']) {
            $this->updateSourceStatus($source['id'], 'success',
                "Imported {$result['programmes']} programmes for {$result['channels']} services");
            $this->db->update('epg_sources', [
                'programme_count' => $result['programmes'],
                'channel_count' => $result['channels'],
            ], 'id = ?', [$source['id']]);
        } else {
            $this->updateSourceStatus($source['id'], 'error', $result['message']);
        }

        return $result['success'] ? $eitFile : null;
    }

    /**
     * Parse TSDuck SDT XML for service names
     * Returns array of service_id => service_name
     */
    private function parseSdtXml(string $filePath): array
    {
        $names = [];

        try {
            $xml = simplexml_load_file($filePath);
            if (!$xml) return $names;

            foreach ($xml->children() as $table) {
                if ($table->getName() !== 'SDT') continue;

                foreach ($table->children() as $service) {
                    if ($service->getName() !== 'service') continue;

                    $serviceId = (string) ($service['service_id'] ?? '');
                    if (empty($serviceId)) continue;

                    // Look for service_descriptor with service_name
                    foreach ($service->children() as $desc) {
                        if ($desc->getName() === 'service_descriptor') {
                            $name = (string) ($desc['service_name'] ?? '');
                            if (!empty($name)) {
                                $names[$serviceId] = $name;
                            }
                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('SDT parse error: ' . $e->getMessage());
        }

        return $names;
    }

    /**
     * Parse TSDuck EIT XML and import programmes
     */
    public function importEitXml(int $sourceId, string $filePath, array $serviceNames = []): array
    {
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => 'EIT XML file not found', 'programmes' => 0, 'channels' => 0];
        }

        try {
            $xml = simplexml_load_file($filePath);
            if (!$xml) {
                return ['success' => false, 'message' => 'Failed to parse EIT XML', 'programmes' => 0, 'channels' => 0];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'XML parse error: ' . $e->getMessage(), 'programmes' => 0, 'channels' => 0];
        }

        $channelsFound = [];
        $programmes = [];

        foreach ($xml->children() as $table) {
            if ($table->getName() !== 'EIT') continue;

            $serviceId = (string) ($table['service_id'] ?? '');
            if (empty($serviceId)) continue;

            $channelsFound[$serviceId] = true;

            foreach ($table->children() as $event) {
                if ($event->getName() !== 'event') continue;

                $eventId = (string) ($event['event_id'] ?? '');
                $startTime = (string) ($event['start_time'] ?? '');
                $duration = (string) ($event['duration'] ?? '');

                if (empty($startTime) || empty($duration)) continue;

                // Parse start time and duration
                $startDt = $this->parseEitDateTime($startTime);
                $endDt = $this->addDuration($startDt, $duration);

                if (!$startDt || !$endDt) continue;

                // Extract descriptors
                $title = '';
                $subtitle = '';
                $description = '';
                $language = '';
                $category = '';
                $rating = '';

                foreach ($event->children() as $desc) {
                    $descName = $desc->getName();

                    if ($descName === 'short_event_descriptor') {
                        $language = (string) ($desc['language_code'] ?? '');
                        $title = (string) ($desc->event_name ?? '');
                        $subtitle = (string) ($desc->text ?? '');
                    }

                    if ($descName === 'extended_event_descriptor') {
                        // Append extended text to description
                        $extText = (string) ($desc->text ?? '');
                        if (!empty($extText)) {
                            $description .= $extText;
                        }
                    }

                    if ($descName === 'content_descriptor') {
                        foreach ($desc->children() as $content) {
                            if ($content->getName() === 'content') {
                                $nibble1 = (int) ($content['content_nibble_level_1'] ?? 0);
                                $cat = $this->eitContentCategory($nibble1);
                                if ($cat) $category = $cat;
                                break;
                            }
                        }
                    }

                    if ($descName === 'parental_rating_descriptor') {
                        foreach ($desc->children() as $pr) {
                            if ($pr->getName() === 'country') {
                                $rating = (string) ($pr['rating'] ?? '');
                                break;
                            }
                        }
                    }
                }

                if (empty($title)) continue;

                // Use subtitle as description if no extended description
                if (empty($description) && !empty($subtitle)) {
                    $description = $subtitle;
                    $subtitle = '';
                }

                $programmes[] = [
                    'service_id' => $serviceId,
                    'event_id' => $eventId,
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'description' => $description,
                    'start_time' => $startDt,
                    'end_time' => $endDt,
                    'category' => $category,
                    'rating' => $rating,
                    'language' => $language,
                ];
            }
        }

        if (empty($programmes)) {
            return ['success' => false, 'message' => 'No programme data found in EIT tables', 'programmes' => 0, 'channels' => 0];
        }

        // Ensure channel mappings exist
        foreach (array_keys($channelsFound) as $svcId) {
            $serviceName = $serviceNames[$svcId] ?? null;
            $this->ensureChannelMapping($sourceId, $svcId, $serviceName);
        }

        // Get the channel mapping for this source
        $mappings = $this->db->fetchAll(
            "SELECT epg_channel_id, channel_id FROM epg_channel_map
             WHERE epg_source_id = ? AND is_mapped = 1",
            [$sourceId]
        );

        $channelMap = [];
        foreach ($mappings as $m) {
            $channelMap[$m['epg_channel_id']] = (int) $m['channel_id'];
        }

        // Clear old programmes for this source
        $this->db->execute("DELETE FROM epg_programs WHERE epg_source_id = ?", [$sourceId]);

        // Insert programmes
        $imported = 0;
        $serviceStats = [];

        foreach ($programmes as $prog) {
            $channelId = $channelMap[$prog['service_id']] ?? null;
            if (!$channelId) continue;

            $this->db->insert('epg_programs', [
                'epg_source_id' => $sourceId,
                'channel_id' => $channelId,
                'external_event_id' => $prog['event_id'],
                'title' => $prog['title'],
                'subtitle' => $prog['subtitle'] ?: null,
                'description' => $prog['description'] ?: null,
                'start_time' => $prog['start_time'],
                'end_time' => $prog['end_time'],
                'category' => $prog['category'] ?: null,
                'rating' => $prog['rating'] ?: null,
                'language' => $prog['language'] ?: null,
            ]);

            $imported++;

            if (!isset($serviceStats[$prog['service_id']])) {
                $serviceStats[$prog['service_id']] = 0;
            }
            $serviceStats[$prog['service_id']]++;
        }

        // Update channel mapping programme counts
        foreach ($serviceStats as $svcId => $count) {
            $this->db->execute(
                "UPDATE epg_channel_map SET programme_count = ? WHERE epg_source_id = ? AND epg_channel_id = ?",
                [$count, $sourceId, $svcId]
            );
        }

        // Update channel epg_last_update timestamps
        if (!empty($channelMap)) {
            $channelIds = array_unique(array_values($channelMap));
            $placeholders = implode(',', array_fill(0, count($channelIds), '?'));
            $this->db->execute(
                "UPDATE channels SET epg_last_update = NOW() WHERE id IN ({$placeholders})",
                $channelIds
            );
        }

        return [
            'success' => true,
            'programmes' => $imported,
            'channels' => count($serviceStats),
            'total_found' => count($programmes),
            'unmapped' => count($programmes) - $imported,
        ];
    }

    /**
     * Ensure a channel mapping record exists for a service_id
     */
    private function ensureChannelMapping(int $sourceId, string $epgChannelId, ?string $serviceName): void
    {
        $existing = $this->db->fetch(
            "SELECT id FROM epg_channel_map WHERE epg_source_id = ? AND epg_channel_id = ?",
            [$sourceId, $epgChannelId]
        );

        if ($existing) {
            // Update name if we have one and it's different
            if ($serviceName) {
                $this->db->update('epg_channel_map', [
                    'epg_channel_name' => $serviceName,
                ], 'id = ?', [$existing['id']]);
            }
            return;
        }

        $this->db->insert('epg_channel_map', [
            'epg_source_id' => $sourceId,
            'epg_channel_id' => $epgChannelId,
            'epg_channel_name' => $serviceName,
            'is_mapped' => 0,
        ]);
    }

    // ========================================================================
    // XMLTV IMPORT (for Option 2 - file upload or URL)
    // ========================================================================

    /**
     * Import XMLTV format data
     */
    public function importXmltvFile(int $sourceId, string $filePath): array
    {
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => 'XMLTV file not found', 'programmes' => 0, 'channels' => 0];
        }

        try {
            $xml = simplexml_load_file($filePath);
            if (!$xml) {
                return ['success' => false, 'message' => 'Failed to parse XMLTV file', 'programmes' => 0, 'channels' => 0];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'XML parse error: ' . $e->getMessage(), 'programmes' => 0, 'channels' => 0];
        }

        // Parse channel definitions
        $xmltvChannels = [];
        foreach ($xml->channel as $ch) {
            $id = (string) ($ch['id'] ?? '');
            $name = (string) ($ch->{'display-name'} ?? $id);
            if (!empty($id)) {
                $xmltvChannels[$id] = $name;
                $this->ensureChannelMapping($sourceId, $id, $name);
            }
        }

        // Get channel mappings
        $mappings = $this->db->fetchAll(
            "SELECT epg_channel_id, channel_id FROM epg_channel_map
             WHERE epg_source_id = ? AND is_mapped = 1",
            [$sourceId]
        );

        $channelMap = [];
        foreach ($mappings as $m) {
            $channelMap[$m['epg_channel_id']] = (int) $m['channel_id'];
        }

        // Clear old programmes
        $this->db->execute("DELETE FROM epg_programs WHERE epg_source_id = ?", [$sourceId]);

        // Parse programmes
        $imported = 0;
        $serviceStats = [];

        foreach ($xml->programme as $prog) {
            $channelRef = (string) ($prog['channel'] ?? '');
            $channelId = $channelMap[$channelRef] ?? null;
            if (!$channelId) continue;

            $startStr = (string) ($prog['start'] ?? '');
            $stopStr = (string) ($prog['stop'] ?? '');

            if (empty($startStr) || empty($stopStr)) continue;

            $startTime = $this->parseXmltvDateTime($startStr);
            $endTime = $this->parseXmltvDateTime($stopStr);

            if (!$startTime || !$endTime) continue;

            $title = (string) ($prog->title ?? '');
            if (empty($title)) continue;

            $subtitle = (string) ($prog->{'sub-title'} ?? '');
            $description = (string) ($prog->desc ?? '');
            $category = (string) ($prog->category ?? '');
            $rating = '';

            if (isset($prog->rating)) {
                $rating = (string) ($prog->rating->value ?? '');
            }

            $episodeNum = (string) ($prog->{'episode-num'} ?? '');
            $language = '';
            if (isset($prog->title['lang'])) {
                $language = (string) $prog->title['lang'];
            }

            $this->db->insert('epg_programs', [
                'epg_source_id' => $sourceId,
                'channel_id' => $channelId,
                'external_event_id' => null,
                'title' => $title,
                'subtitle' => $subtitle ?: null,
                'description' => $description ?: null,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'category' => $category ?: null,
                'episode_info' => $episodeNum ?: null,
                'rating' => $rating ?: null,
                'language' => $language ?: null,
            ]);

            $imported++;

            if (!isset($serviceStats[$channelRef])) {
                $serviceStats[$channelRef] = 0;
            }
            $serviceStats[$channelRef]++;
        }

        // Update programme counts on mappings
        foreach ($serviceStats as $epgChId => $count) {
            $this->db->execute(
                "UPDATE epg_channel_map SET programme_count = ? WHERE epg_source_id = ? AND epg_channel_id = ?",
                [$count, $sourceId, $epgChId]
            );
        }

        // Update source stats
        $this->db->update('epg_sources', [
            'programme_count' => $imported,
            'channel_count' => count($serviceStats),
        ], 'id = ?', [$sourceId]);

        // Update channel timestamps
        if (!empty($channelMap)) {
            $channelIds = array_unique(array_values($channelMap));
            $placeholders = implode(',', array_fill(0, count($channelIds), '?'));
            $this->db->execute(
                "UPDATE channels SET epg_last_update = NOW() WHERE id IN ({$placeholders})",
                $channelIds
            );
        }

        return [
            'success' => true,
            'programmes' => $imported,
            'channels' => count($serviceStats),
            'total_xmltv_channels' => count($xmltvChannels),
            'unmapped' => count($xmltvChannels) - count($channelMap),
        ];
    }

    // ========================================================================
    // PROGRAMME QUERIES
    // ========================================================================

    public function getProgrammes(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['channel_id'])) {
            $where[] = 'p.channel_id = ?';
            $params[] = (int) $filters['channel_id'];
        }

        if (!empty($filters['source_id'])) {
            $where[] = 'p.epg_source_id = ?';
            $params[] = (int) $filters['source_id'];
        }

        if (!empty($filters['date'])) {
            $where[] = 'DATE(p.start_time) = ?';
            $params[] = $filters['date'];
        }

        if (!empty($filters['now'])) {
            $where[] = 'p.start_time <= NOW() AND p.end_time > NOW()';
        }

        $whereClause = implode(' AND ', $where);

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(200, max(10, (int) ($filters['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM epg_programs p WHERE {$whereClause}",
            $params
        )['cnt'];

        $programmes = $this->db->fetchAll(
            "SELECT p.*, c.name as channel_name, c.logo_url as channel_logo
             FROM epg_programs p
             LEFT JOIN channels c ON p.channel_id = c.id
             WHERE {$whereClause}
             ORDER BY p.start_time ASC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'data' => $programmes,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $total > 0 ? ceil($total / $perPage) : 0,
        ];
    }

    public function getStatistics(): array
    {
        $stats = [];

        $stats['total_sources'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM epg_sources"
        )['cnt'];

        $stats['active_sources'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM epg_sources WHERE is_active = 1"
        )['cnt'];

        $stats['total_programmes'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM epg_programs"
        )['cnt'];

        $stats['upcoming_programmes'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM epg_programs WHERE start_time > NOW()"
        )['cnt'];

        $stats['channels_with_epg'] = (int) $this->db->fetch(
            "SELECT COUNT(DISTINCT channel_id) as cnt FROM epg_programs WHERE start_time > NOW()"
        )['cnt'];

        $stats['total_mappings'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM epg_channel_map"
        )['cnt'];

        $stats['mapped_channels'] = (int) $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM epg_channel_map WHERE is_mapped = 1"
        )['cnt'];

        return $stats;
    }

    public function clearOldProgrammes(int $daysOld = 1): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));
        return $this->db->execute(
            "DELETE FROM epg_programs WHERE end_time < ?",
            [$cutoff]
        );
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Parse EIT datetime format: "2024-01-15 14:00:00"
     */
    private function parseEitDateTime(string $dateStr): ?string
    {
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', trim($dateStr));
        if (!$dt) {
            // Try alternate format without seconds
            $dt = \DateTime::createFromFormat('Y-m-d H:i', trim($dateStr));
        }
        return $dt ? $dt->format('Y-m-d H:i:s') : null;
    }

    /**
     * Parse XMLTV datetime format: "20240115140000 +0000"
     */
    private function parseXmltvDateTime(string $dateStr): ?string
    {
        $dateStr = trim($dateStr);

        // Format: YYYYMMDDHHMMSS +HHMM
        $dt = \DateTime::createFromFormat('YmdHis O', $dateStr);
        if (!$dt) {
            // Try without timezone
            $dt = \DateTime::createFromFormat('YmdHis', substr($dateStr, 0, 14));
        }
        return $dt ? $dt->format('Y-m-d H:i:s') : null;
    }

    /**
     * Add HH:MM:SS duration to a datetime string
     */
    private function addDuration(string $startTime, string $duration): ?string
    {
        $dt = new \DateTime($startTime);
        $parts = explode(':', $duration);

        $hours = (int) ($parts[0] ?? 0);
        $minutes = (int) ($parts[1] ?? 0);
        $seconds = (int) ($parts[2] ?? 0);

        $dt->modify("+{$hours} hours +{$minutes} minutes +{$seconds} seconds");
        return $dt->format('Y-m-d H:i:s');
    }

    /**
     * Convert PID from hex string to decimal
     */
    private function pidToDecimal(string $pid): int
    {
        if (str_starts_with(strtolower($pid), '0x')) {
            return hexdec($pid);
        }
        return (int) $pid;
    }

    /**
     * Map DVB content_nibble_level_1 to category name
     * Based on ETSI EN 300 468 Table 28
     */
    private function eitContentCategory(int $nibble1): ?string
    {
        return match ($nibble1) {
            0x1 => 'Movie/Drama',
            0x2 => 'News',
            0x3 => 'Entertainment',
            0x4 => 'Sports',
            0x5 => 'Children',
            0x6 => 'Music',
            0x7 => 'Arts/Culture',
            0x8 => 'Social/Politics',
            0x9 => 'Education/Science',
            0xA => 'Leisure',
            0xB => 'Lifestyle',
            default => null,
        };
    }
}
