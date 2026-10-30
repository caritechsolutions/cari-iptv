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
            'channel_id' => !empty($data['channel_id']) ? (int) $data['channel_id'] : null,
            'source_url' => $data['source_url'] ?? null,
            'source_port' => !empty($data['source_port']) ? (int) $data['source_port'] : null,
            'eit_pid' => $data['eit_pid'] ?? '0x12',
            'capture_timeout' => (int) ($data['capture_timeout'] ?? 120),
            'is_active' => (int) ($data['is_active'] ?? 1),
            'auto_refresh' => (int) ($data['auto_refresh'] ?? 0),
            'refresh_interval' => (int) ($data['refresh_interval'] ?? 3600),
            'timezone' => $data['timezone'] ?? 'UTC',
        ]);
    }

    public function updateSource(int $id, array $data): bool
    {
        $fields = [];
        $allowed = ['name', 'type', 'channel_id', 'source_url', 'source_port', 'eit_pid',
                     'capture_timeout', 'is_active', 'auto_refresh', 'refresh_interval', 'timezone'];

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
            $data['last_attempt'] = $data['last_fetch'];
            $data['consecutive_errors'] = 0;
        } elseif ($status === 'error' || $status === 'running') {
            if ($status === 'error') {
                $this->db->execute(
                    "UPDATE epg_sources SET consecutive_errors = consecutive_errors + 1 WHERE id = ?",
                    [$id]
                );
            }
        }

        $this->db->update('epg_sources', $data, 'id = ?', [$id]);
    }

    /**
     * Whether an auto-refresh source is due. After an error the wait doubles
     * per consecutive failure (capped at 6h) so a dead stream is not retried
     * every cron tick.
     */
    public static function isSourceDue(array $source, ?int $now = null): bool
    {
        if (empty($source['auto_refresh'])) return false;
        $now = $now ?? time();
        $interval = max(60, (int) ($source['refresh_interval'] ?? 1800));
        $errors = (int) ($source['consecutive_errors'] ?? 0);
        if ($errors > 0) {
            $interval = min($interval * (2 ** min($errors, 8)), 6 * 3600);
        }
        $last = $source['last_attempt'] ?? $source['last_fetch'] ?? null;
        if (empty($last)) return true;
        return ($now - strtotime($last . ' UTC')) >= $interval;
    }


    // ========================================================================
    // SCHEDULE CSV (one channel per file)
    // ========================================================================

    /** Where a previewed upload waits for the admin's confirmation. */
    private const CSV_PREVIEW_DIR = BASE_PATH . '/storage/cache/epg-csv';

    /**
     * Parse a schedule CSV. Columns (header row, any order, case-insensitive):
     *   name | title | programme | program
     *   description | desc | synopsis            (optional)
     *   start_time | start
     *   end_time | end
     * Times are local to $tz unless they carry an explicit offset / Z.
     * Accepted date forms: ISO (2026-07-01 00:00[:ss], 2026-07-01T00:00),
     * Excel exports (7/1/26 0:00, 7/1/2026 0:00, 01/07/2026 00:00 — d/m is
     * detected when any first field exceeds 12).
     *
     * @return array{rows: array<int, array>, issues: array<int, array>, columns: array}
     */
    public function parseScheduleCsv(string $path, string $tz): array
    {
        $fh = fopen($path, 'r');
        if (!$fh) {
            return ['rows' => [], 'issues' => [['line' => 0, 'level' => 'error', 'message' => 'Cannot open file']], 'columns' => []];
        }

        // Strip a UTF-8 BOM (Excel writes one)
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($fh);

        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            return ['rows' => [], 'issues' => [['line' => 1, 'level' => 'error', 'message' => 'File is empty']], 'columns' => []];
        }

        $aliases = [
            'name' => ['name', 'title', 'programme', 'program', 'programme name', 'program name'],
            'description' => ['description', 'desc', 'synopsis', 'details'],
            'start' => ['start_time', 'start', 'start time', 'starttime', 'from'],
            'end' => ['end_time', 'end', 'end time', 'endtime', 'to', 'stop', 'stop_time'],
        ];
        $cols = [];
        foreach ($header as $i => $h) {
            $key = strtolower(trim((string) $h));
            foreach ($aliases as $field => $names) {
                if (in_array($key, $names, true)) $cols[$field] = $i;
            }
        }

        $issues = [];
        foreach (['name', 'start', 'end'] as $required) {
            if (!isset($cols[$required])) {
                $issues[] = ['line' => 1, 'level' => 'error', 'message' => "Missing column '{$required}' (header row has: " . implode(', ', array_map('trim', $header)) . ')'];
            }
        }
        if (!empty($issues)) {
            fclose($fh);
            return ['rows' => [], 'issues' => $issues, 'columns' => $cols];
        }

        // First pass: read raw rows, decide d/m vs m/d for slash dates
        $raw = [];
        $line = 1;
        $dayFirst = false;
        while (($r = fgetcsv($fh)) !== false) {
            $line++;
            if ($r === [null] || count(array_filter($r, fn($v) => trim((string) $v) !== '')) === 0) continue;
            $raw[] = ['line' => $line, 'r' => $r];
            foreach (['start', 'end'] as $f) {
                $v = trim((string) ($r[$cols[$f]] ?? ''));
                if (preg_match('~^(\d{1,2})/(\d{1,2})/~', $v, $m) && (int) $m[1] > 12) $dayFirst = true;
            }
        }
        fclose($fh);

        $zone = new \DateTimeZone($tz ?: 'UTC');
        $rows = [];
        foreach ($raw as $item) {
            $r = $item['r'];
            $ln = $item['line'];
            $name = trim((string) ($r[$cols['name']] ?? ''));
            $desc = isset($cols['description']) ? trim((string) ($r[$cols['description']] ?? '')) : '';
            $startRaw = trim((string) ($r[$cols['start']] ?? ''));
            $endRaw = trim((string) ($r[$cols['end']] ?? ''));

            $start = $this->parseScheduleDateTime($startRaw, $zone, $dayFirst);
            $end = $this->parseScheduleDateTime($endRaw, $zone, $dayFirst);

            $rowErrors = [];
            if ($name === '') $rowErrors[] = 'name is empty';
            if (!$start) $rowErrors[] = "cannot read start_time '{$startRaw}'";
            if (!$end) $rowErrors[] = "cannot read end_time '{$endRaw}'";
            if ($start && $end && $end <= $start) $rowErrors[] = 'end_time is not after start_time';

            if ($rowErrors) {
                $issues[] = ['line' => $ln, 'level' => 'error', 'message' => implode('; ', $rowErrors) . ' — row skipped'];
                continue;
            }

            $rows[] = [
                'line' => $ln,
                'title' => $name,
                'description' => $desc,
                'start_time' => $start->format('Y-m-d H:i:s'),
                'end_time' => $end->format('Y-m-d H:i:s'),
                'start_local' => $start->setTimezone($zone)->format('Y-m-d H:i'),
                'end_local' => $end->setTimezone($zone)->format('Y-m-d H:i'),
            ];
        }

        // Order by start, then check continuity
        usort($rows, fn($a, $b) => strcmp($a['start_time'], $b['start_time']));
        for ($i = 1; $i < count($rows); $i++) {
            $prev = $rows[$i - 1];
            $cur = $rows[$i];
            if ($cur['start_time'] < $prev['end_time']) {
                $issues[] = ['line' => $cur['line'], 'level' => 'warning',
                    'message' => "overlaps the previous programme (row {$prev['line']} ends {$prev['end_local']}, this starts {$cur['start_local']})"];
            } elseif ($cur['start_time'] > $prev['end_time']) {
                $gap = (strtotime($cur['start_time']) - strtotime($prev['end_time'])) / 60;
                $issues[] = ['line' => $cur['line'], 'level' => 'warning',
                    'message' => sprintf('gap of %s before this programme (%s → %s) — the guide will show "No information"',
                        $gap >= 60 ? round($gap / 60, 1) . ' h' : $gap . ' min', $prev['end_local'], $cur['start_local'])];
            }
        }

        $past = count(array_filter($rows, fn($r) => $r['end_time'] < gmdate('Y-m-d H:i:s')));
        if ($past > 0) {
            $issues[] = ['line' => 0, 'level' => 'info', 'message' => "{$past} programme(s) have already ended and will not show in the guide"];
        }

        usort($issues, fn($a, $b) => $a['line'] <=> $b['line']);
        return ['rows' => $rows, 'issues' => $issues, 'columns' => $cols];
    }

    private function parseScheduleDateTime(string $v, \DateTimeZone $zone, bool $dayFirst): ?\DateTimeImmutable
    {
        if ($v === '') return null;
        $v = preg_replace('/\s+/', ' ', $v);

        // Explicit offset or Z: honour it
        if (preg_match('/(Z|[+-]\d{2}:?\d{2})$/', $v)) {
            try { return (new \DateTimeImmutable($v))->setTimezone(new \DateTimeZone('UTC')); } catch (\Exception) { return null; }
        }

        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d'];
        // Two-digit-year forms first: 'y' needs exactly two digits so it cannot
        // swallow a four-digit year, whereas 'Y' would read "26" as year 0026.
        $formats = array_merge($formats, $dayFirst
            ? ['d/m/y H:i:s', 'd/m/y H:i', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/y', 'd/m/Y']
            : ['n/j/y H:i:s', 'n/j/y H:i', 'n/j/Y H:i:s', 'n/j/Y H:i', 'n/j/y', 'n/j/Y']);
        $formats = array_merge($formats, ['d-m-Y H:i', 'd.m.Y H:i', 'Y/m/d H:i', 'j M Y H:i', 'd M Y H:i', 'M j Y H:i', 'g:i A n/j/Y']);
        foreach ($formats as $f) {
            $dt = \DateTimeImmutable::createFromFormat('!' . $f, $v, $zone);
            if (!$dt) continue;
            // createFromFormat accepts out-of-range parts (e.g. 31/02) with a warning; reject those
            $err = \DateTimeImmutable::getLastErrors();
            if (is_array($err) && ($err['warning_count'] || $err['error_count'])) continue;
            return $dt->setTimezone(new \DateTimeZone('UTC'));
        }
        return null;
    }

    /**
     * Parse an uploaded schedule and park it for confirmation.
     * @return array{success:bool, token?:string, rows?:int, errors?:int, warnings?:int, issues?:array, sample?:array, range?:array, message?:string}
     */
    public function previewScheduleCsv(int $sourceId, string $path): array
    {
        $source = $this->getSource($sourceId);
        if (!$source || $source['type'] !== 'csv') {
            return ['success' => false, 'message' => 'Not a schedule CSV source'];
        }
        if (empty($source['channel_id'])) {
            return ['success' => false, 'message' => 'This source has no channel selected — edit the source and pick the channel first'];
        }

        $parsed = $this->parseScheduleCsv($path, $source['timezone'] ?: 'UTC');
        $errors = count(array_filter($parsed['issues'], fn($i) => $i['level'] === 'error'));
        $warnings = count(array_filter($parsed['issues'], fn($i) => $i['level'] === 'warning'));

        if (empty($parsed['rows'])) {
            return ['success' => false, 'message' => 'No usable rows found', 'issues' => $parsed['issues'], 'errors' => $errors, 'warnings' => $warnings];
        }

        if (!is_dir(self::CSV_PREVIEW_DIR)) @mkdir(self::CSV_PREVIEW_DIR, 0775, true);
        $token = bin2hex(random_bytes(12));
        file_put_contents(self::CSV_PREVIEW_DIR . "/{$sourceId}_{$token}.json", json_encode($parsed['rows']));
        $this->purgeOldPreviews();

        $first = $parsed['rows'][0];
        $last = $parsed['rows'][count($parsed['rows']) - 1];
        return [
            'success' => true,
            'token' => $token,
            'rows' => count($parsed['rows']),
            'errors' => $errors,
            'warnings' => $warnings,
            'issues' => $parsed['issues'],
            'sample' => array_slice($parsed['rows'], 0, 5),
            'range' => ['from' => $first['start_local'], 'to' => $last['end_local'], 'timezone' => $source['timezone'] ?: 'UTC'],
            'channel' => $this->db->fetch("SELECT id, name FROM channels WHERE id = ?", [$source['channel_id']]),
        ];
    }

    /**
     * Commit a previewed schedule: the window the file covers replaces what
     * was there for this channel/source; earlier rows are kept for catch-up.
     */
    public function importScheduleCsv(int $sourceId, string $token): array
    {
        $source = $this->getSource($sourceId);
        if (!$source || $source['type'] !== 'csv' || empty($source['channel_id'])) {
            return ['success' => false, 'message' => 'Not a schedule CSV source with a channel'];
        }
        if (!preg_match('/^[a-f0-9]{24}$/', $token)) {
            return ['success' => false, 'message' => 'Invalid preview token'];
        }
        $file = self::CSV_PREVIEW_DIR . "/{$sourceId}_{$token}.json";
        if (!is_file($file)) {
            return ['success' => false, 'message' => 'Preview expired — upload the file again'];
        }
        $rows = json_decode((string) file_get_contents($file), true) ?: [];
        @unlink($file);
        if (empty($rows)) {
            return ['success' => false, 'message' => 'Nothing to import'];
        }

        $channelId = (int) $source['channel_id'];
        $epgChannelId = 'csv:' . $channelId;

        // One mapping row per CSV source, always mapped to its channel
        $existing = $this->db->fetch(
            "SELECT id FROM epg_channel_map WHERE epg_source_id = ? AND epg_channel_id = ?",
            [$sourceId, $epgChannelId]
        );
        $channelName = $this->db->fetch("SELECT name FROM channels WHERE id = ?", [$channelId])['name'] ?? null;
        if ($existing) {
            $this->db->update('epg_channel_map', ['channel_id' => $channelId, 'is_mapped' => 1, 'epg_channel_name' => $channelName], 'id = ?', [$existing['id']]);
        } else {
            $this->db->insert('epg_channel_map', [
                'epg_source_id' => $sourceId, 'epg_channel_id' => $epgChannelId,
                'epg_channel_name' => $channelName, 'channel_id' => $channelId, 'is_mapped' => 1,
            ]);
        }
        // Anything else attached to this source is stale
        $this->db->execute("DELETE FROM epg_channel_map WHERE epg_source_id = ? AND epg_channel_id <> ?", [$sourceId, $epgChannelId]);

        $programmes = [];
        foreach ($rows as $r) {
            $programmes[] = [
                'service_id' => $epgChannelId,
                'event_id' => 'c' . substr(md5($channelId . '|' . $r['start_time']), 0, 16),
                'title' => $r['title'],
                'subtitle' => '',
                'description' => $r['description'] ?? '',
                'start_time' => $r['start_time'],
                'end_time' => $r['end_time'],
                'category' => '',
                'episode_info' => '',
                'rating' => '',
                'language' => '',
            ];
        }

        try {
            $write = $this->writeProgrammes($sourceId, $programmes, [$epgChannelId => $channelId], 'service_id', futureOnly: false);
        } catch (\Throwable $e) {
            $this->updateSourceStatus($sourceId, 'error', 'Import failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Import failed: ' . $e->getMessage()];
        }

        $this->db->update('epg_sources', ['programme_count' => $write['imported'], 'channel_count' => 1], 'id = ?', [$sourceId]);
        $msg = "Imported {$write['imported']} programmes for {$channelName}";
        $this->updateSourceStatus($sourceId, 'success', $msg);

        if ($write['imported'] > 0) {
            $this->queueMetadataEnrichment($programmes);
        }

        return ['success' => true, 'message' => $msg, 'programmes' => $write['imported'], 'channels' => 1];
    }

    private function purgeOldPreviews(): void
    {
        foreach (glob(self::CSV_PREVIEW_DIR . '/*.json') ?: [] as $f) {
            if (filemtime($f) < time() - 3600) @unlink($f);
        }
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
        $m = $this->db->fetch("SELECT epg_source_id, epg_channel_id FROM epg_channel_map WHERE id = ?", [$mappingId]);
        if (!$m) return false;

        if ($channelId) {
            // one channel per source
            $this->db->execute(
                "UPDATE epg_channel_map SET channel_id = NULL, is_mapped = 0
                 WHERE epg_source_id = ? AND channel_id = ? AND id <> ?",
                [$m['epg_source_id'], $channelId, $mappingId]
            );
            // remember the key on the channel so other sources map on their own
            $this->db->update('channels', ['epg_channel_id' => $m['epg_channel_id']], 'id = ?', [$channelId]);
        }

        $this->db->update('epg_channel_map', [
            'channel_id' => $channelId,
            'is_mapped' => $channelId ? 1 : 0,
        ], 'id = ?', [$mappingId]);

        return true;
    }

    public function autoMapChannels(int $sourceId): int
    {
        $mappings = $this->db->fetchAll(
            "SELECT id, epg_channel_id, epg_channel_name FROM epg_channel_map
             WHERE epg_source_id = ? AND is_mapped = 0",
            [$sourceId]
        );

        $mapped = 0;
        foreach ($mappings as $m) {
            $channelId = $this->resolveChannel($m['epg_channel_id'], $m['epg_channel_name']);
            if ($channelId) {
                $this->db->update('epg_channel_map', [
                    'channel_id' => $channelId,
                    'is_mapped' => 1,
                ], 'id = ?', [$m['id']]);
                $mapped++;
            }
        }

        return $mapped;
    }

    /**
     * Set the mapping for one of our channels from the channel form:
     * every source that knows $epgChannelId points at $channelId, and any
     * older mapping of this channel in those sources is released.
     * Also stores the id on the channel so future sources map automatically.
     */
    public function mapChannelByEpgId(int $channelId, ?string $epgChannelId): void
    {
        $epgChannelId = $epgChannelId !== null ? trim($epgChannelId) : '';
        $this->db->update('channels', ['epg_channel_id' => $epgChannelId !== '' ? $epgChannelId : null], 'id = ?', [$channelId]);

        if ($epgChannelId === '') {
            $this->db->execute(
                "UPDATE epg_channel_map SET channel_id = NULL, is_mapped = 0 WHERE channel_id = ?",
                [$channelId]
            );
            return;
        }

        $rows = $this->db->fetchAll(
            "SELECT id, epg_source_id FROM epg_channel_map WHERE epg_channel_id = ?",
            [$epgChannelId]
        );
        foreach ($rows as $r) {
            // one channel per source: release the previous row this channel held there
            $this->db->execute(
                "UPDATE epg_channel_map SET channel_id = NULL, is_mapped = 0
                 WHERE epg_source_id = ? AND channel_id = ? AND id <> ?",
                [$r['epg_source_id'], $channelId, $r['id']]
            );
            $this->db->update('epg_channel_map', ['channel_id' => $channelId, 'is_mapped' => 1], 'id = ?', [$r['id']]);
        }
    }

    /**
     * EPG channel ids known across all sources, for the channel form's picker.
     */
    public function knownEpgChannels(): array
    {
        return $this->db->fetchAll(
            "SELECT m.epg_channel_id, m.epg_channel_name, s.name AS source_name, m.channel_id
             FROM epg_channel_map m JOIN epg_sources s ON s.id = m.epg_source_id
             ORDER BY m.epg_channel_name, m.epg_channel_id"
        );
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
        // --pack-all-sections captures all section versions (p/f + schedule segments 0x50-0x5F)
        // Without it, only the first version of each table is captured, missing most schedule data
        $eitPidDec = $this->pidToDecimal($pid);
        $eitCmd = sprintf(
            'timeout %d tsp -I ip %s:%d -P tables --pid %d --pack-all-sections --xml-output %s -O drop 2>&1',
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
        // SDT missed this capture: keep filtering with the services seen on a
        // previous fetch rather than importing every cross-carried service.
        if (empty($serviceNames)) {
            $serviceNames = $this->knownLocalServices($source['id']);
        }

        // Validate EIT output
        if (!file_exists($eitFile) || filesize($eitFile) === 0) {
            $this->updateSourceStatus($source['id'], 'error',
                'No EIT data captured. Check stream address and that PID ' . $pid . ' carries EIT tables.');
            if (file_exists($eitFile)) unlink($eitFile);
            return null;
        }

        // Import the EIT data
        try {
            $sourceTz = $source['timezone'] ?? 'UTC';
            $result = $this->importEitXml($source['id'], $eitFile, $serviceNames, $sourceTz);
        } catch (\Exception $e) {
            error_log('EIT import failed: ' . $e->getMessage());
            $this->updateSourceStatus($source['id'], 'error', 'Import failed: ' . $e->getMessage());
            if (file_exists($eitFile)) unlink($eitFile);
            return null;
        }

        // Clean up
        if (file_exists($eitFile)) unlink($eitFile);

        if ($result['success']) {
            $sdtInfo = !empty($serviceNames) ? ' (SDT filter: ' . count($serviceNames) . ' local services)' : '';
            $this->updateSourceStatus($source['id'], 'success',
                "Imported {$result['programmes']} programmes for {$result['channels']} services" . $sdtInfo);
            $this->db->update('epg_sources', [
                'programme_count' => $result['programmes'],
                'channel_count' => $result['channels'],
            ], 'id = ?', [$source['id']]);
            // Drop unmapped rows for services that are not on this transponder
            if (!empty($serviceNames)) {
                $this->pruneStaleMappings($source['id'], array_keys($serviceNames));
            }
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
                // Only "SDT actual": the SDT PID also carries the service lists
                // of other transponders, which are not on this stream.
                if ((string) ($table['actual'] ?? 'true') === 'false') continue;

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
    public function importEitXml(int $sourceId, string $filePath, array $serviceNames = [], ?string $sourceTz = null): array
    {
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => 'EIT XML file not found', 'programmes' => 0, 'channels' => 0];
        }

        // Get source timezone if not provided
        if ($sourceTz === null) {
            $source = $this->getSource($sourceId);
            $sourceTz = $source['timezone'] ?? 'UTC';
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

            // Filter EIT to only services present in the local SDT
            // This excludes cross-carried EIT from other transponders
            if (!empty($serviceNames) && !isset($serviceNames[$serviceId])) {
                continue;
            }

            $channelsFound[$serviceId] = true;

            foreach ($table->children() as $event) {
                if ($event->getName() !== 'event') continue;

                $eventId = (string) ($event['event_id'] ?? '');
                $startTime = (string) ($event['start_time'] ?? '');
                $duration = (string) ($event['duration'] ?? '');

                if (empty($startTime) || empty($duration)) continue;

                // Parse start time and duration
                $startDt = $this->parseEitDateTime($startTime, $sourceTz);
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

        // Ensure channel mappings exist, resolving new services against channels.epg_channel_id
        foreach (array_keys($channelsFound) as $svcId) {
            $serviceName = $serviceNames[$svcId] ?? null;
            $this->ensureChannelMapping($sourceId, $svcId, $serviceName);
        }

        $channelMap = $this->channelMapFor($sourceId);

        // DVB EIT carries only current + future events, so only that window is replaced;
        // past rows stay for catch-up. All of it happens in one transaction so the guide
        // never shows an empty grid mid-import.
        $write = $this->writeProgrammes($sourceId, $programmes, $channelMap, 'service_id', futureOnly: true);
        $imported = $write['imported'];
        $serviceStats = $write['stats'];

        // Queue unique programme titles for TMDB metadata enrichment (outside the transaction)
        $metadataQueued = 0;
        if ($imported > 0) {
            $metadataQueued = $this->queueMetadataEnrichment($programmes);
        }

        return [
            'success' => true,
            'programmes' => $imported,
            'channels' => count($serviceStats),
            'total_found' => count($programmes),
            'unmapped' => count($programmes) - $imported,
            'metadata_queued' => $metadataQueued,
        ];
    }

    /**
     * Services previously confirmed local to an EIT source (named via its SDT).
     */
    private function knownLocalServices(int $sourceId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT epg_channel_id, epg_channel_name FROM epg_channel_map
             WHERE epg_source_id = ? AND epg_channel_name IS NOT NULL AND epg_channel_name <> ''",
            [$sourceId]
        );
        $names = [];
        foreach ($rows as $r) $names[$r['epg_channel_id']] = $r['epg_channel_name'];
        return $names;
    }

    /**
     * Remove unmapped mapping rows that do not belong to the source. With a
     * list of local service ids, anything else unmapped goes; without one,
     * only nameless rows with no programmes (the cross-carried leftovers).
     * Rows an admin has mapped are always kept.
     */
    public function pruneStaleMappings(int $sourceId, array $keepIds = []): int
    {
        if (!empty($keepIds)) {
            $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
            return $this->db->execute(
                "DELETE FROM epg_channel_map
                 WHERE epg_source_id = ? AND is_mapped = 0 AND epg_channel_id NOT IN ({$placeholders})",
                array_merge([$sourceId], array_values($keepIds))
            );
        }
        return $this->db->execute(
            "DELETE FROM epg_channel_map
             WHERE epg_source_id = ? AND is_mapped = 0
               AND (epg_channel_name IS NULL OR epg_channel_name = '')
               AND COALESCE(programme_count, 0) = 0",
            [$sourceId]
        );
    }

    /**
     * Mapped channels for a source: epg_channel_id => channel id.
     */
    private function channelMapFor(int $sourceId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT epg_channel_id, channel_id FROM epg_channel_map
             WHERE epg_source_id = ? AND is_mapped = 1 AND channel_id IS NOT NULL",
            [$sourceId]
        );
        $map = [];
        foreach ($rows as $m) {
            $map[$m['epg_channel_id']] = (int) $m['channel_id'];
        }
        return $map;
    }

    /**
     * Write a parsed programme list for a source in one transaction.
     *
     * - futureOnly=true  (EIT): replace rows ending after now; older rows are kept.
     * - futureOnly=false (XMLTV): replace rows inside the window the file covers;
     *   rows outside it (older schedules) are kept for catch-up.
     * Inserts are batched and upserted on (source, channel, external_event_id).
     *
     * @return array{imported:int, stats:array<string,int>}
     */
    private function writeProgrammes(int $sourceId, array $programmes, array $channelMap, string $keyField, bool $futureOnly): array
    {
        $rows = [];
        $stats = [];
        $minStart = null;
        $maxEnd = null;
        foreach ($programmes as $prog) {
            $channelId = $channelMap[$prog[$keyField]] ?? null;
            if (!$channelId) continue;
            $rows[] = [
                $sourceId, $channelId, $prog['event_id'] ?: null,
                mb_substr($prog['title'], 0, 255),
                $prog['subtitle'] !== '' ? mb_substr($prog['subtitle'], 0, 500) : null,
                $prog['description'] !== '' ? $prog['description'] : null,
                $prog['start_time'], $prog['end_time'],
                $prog['category'] !== '' ? mb_substr($prog['category'], 0, 100) : null,
                ($prog['episode_info'] ?? '') !== '' ? mb_substr($prog['episode_info'], 0, 50) : null,
                $prog['rating'] !== '' ? mb_substr($prog['rating'], 0, 10) : null,
                $prog['language'] !== '' ? mb_substr($prog['language'], 0, 10) : null,
            ];
            $stats[$prog[$keyField]] = ($stats[$prog[$keyField]] ?? 0) + 1;
            if ($minStart === null || $prog['start_time'] < $minStart) $minStart = $prog['start_time'];
            if ($maxEnd === null || $prog['end_time'] > $maxEnd) $maxEnd = $prog['end_time'];
        }

        $this->db->beginTransaction();
        try {
            if ($futureOnly) {
                $this->db->execute(
                    "DELETE FROM epg_programs WHERE epg_source_id = ? AND end_time > NOW()",
                    [$sourceId]
                );
            } elseif ($minStart !== null) {
                $this->db->execute(
                    "DELETE FROM epg_programs WHERE epg_source_id = ? AND end_time > ? AND start_time < ?",
                    [$sourceId, $minStart, $maxEnd]
                );
            }

            // Past rows beyond the retention window go in the same pass
            $this->db->execute(
                "DELETE FROM epg_programs WHERE epg_source_id = ? AND end_time < DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$sourceId]
            );

            $cols = "(epg_source_id, channel_id, external_event_id, title, subtitle, description, start_time, end_time, category, episode_info, rating, language)";
            $update = "ON DUPLICATE KEY UPDATE title = VALUES(title), subtitle = VALUES(subtitle), description = VALUES(description),
                       start_time = VALUES(start_time), end_time = VALUES(end_time), category = VALUES(category),
                       episode_info = VALUES(episode_info), rating = VALUES(rating), language = VALUES(language)";
            $rowSql = '(' . implode(',', array_fill(0, 12, '?')) . ')';
            foreach (array_chunk($rows, 200) as $chunk) {
                $sql = "INSERT INTO epg_programs {$cols} VALUES " . implode(',', array_fill(0, count($chunk), $rowSql)) . ' ' . $update;
                $this->db->execute($sql, array_merge(...$chunk));
            }

            foreach ($stats as $epgChId => $count) {
                $this->db->execute(
                    "UPDATE epg_channel_map SET programme_count = ? WHERE epg_source_id = ? AND epg_channel_id = ?",
                    [$count, $sourceId, (string) $epgChId]
                );
            }

            if (!empty($channelMap)) {
                $channelIds = array_values(array_unique($channelMap));
                $placeholders = implode(',', array_fill(0, count($channelIds), '?'));
                $this->db->execute(
                    "UPDATE channels SET epg_last_update = NOW() WHERE id IN ({$placeholders})",
                    $channelIds
                );
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        return ['imported' => count($rows), 'stats' => $stats];
    }

    /**
     * Ensure a channel mapping record exists for a service_id.
     * A new mapping is resolved automatically when a channel carries this id in
     * channels.epg_channel_id (the XMLTV / iptv-org key) or has exactly the same name.
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

        $channelId = $this->resolveChannel($epgChannelId, $serviceName);
        $this->db->insert('epg_channel_map', [
            'epg_source_id' => $sourceId,
            'epg_channel_id' => $epgChannelId,
            'epg_channel_name' => $serviceName,
            'channel_id' => $channelId,
            'is_mapped' => $channelId ? 1 : 0,
        ]);
    }

    /**
     * Find our channel for an EPG channel id / name: epg_channel_id first, then an
     * exact (case-insensitive) name match. Never a partial match, which mapped
     * "BBC" to "BBC Earth".
     */
    public function resolveChannel(string $epgChannelId, ?string $name): ?int
    {
        $row = $this->db->fetch(
            "SELECT id FROM channels WHERE epg_channel_id = ? LIMIT 1",
            [$epgChannelId]
        );
        if (!$row && $name !== null && $name !== '') {
            $row = $this->db->fetch(
                "SELECT id FROM channels WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1",
                [$name]
            );
        }
        return $row ? (int) $row['id'] : null;
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

        // Get source timezone for fallback when XMLTV has no offset
        $source = $this->getSource($sourceId);
        $sourceTz = $source['timezone'] ?? 'UTC';

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

        $channelMap = $this->channelMapFor($sourceId);

        // Parse programmes
        $programmes = [];
        foreach ($xml->programme as $prog) {
            $channelRef = (string) ($prog['channel'] ?? '');
            if (!isset($channelMap[$channelRef])) continue;

            $startStr = (string) ($prog['start'] ?? '');
            $stopStr = (string) ($prog['stop'] ?? '');
            if (empty($startStr) || empty($stopStr)) continue;

            $startTime = $this->parseXmltvDateTime($startStr, $sourceTz);
            $endTime = $this->parseXmltvDateTime($stopStr, $sourceTz);
            if (!$startTime || !$endTime || $endTime <= $startTime) continue;

            $title = (string) ($prog->title ?? '');
            if (empty($title)) continue;

            $rating = isset($prog->rating) ? (string) ($prog->rating->value ?? '') : '';
            $language = isset($prog->title['lang']) ? (string) $prog->title['lang'] : '';

            $programmes[] = [
                'service_id' => $channelRef,
                // XMLTV has no event id; channel + start time identifies a slot
                'event_id' => 'x' . substr(md5($channelRef . '|' . $startTime), 0, 16),
                'title' => $title,
                'subtitle' => (string) ($prog->{'sub-title'} ?? ''),
                'description' => (string) ($prog->desc ?? ''),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'category' => (string) ($prog->category ?? ''),
                'episode_info' => (string) ($prog->{'episode-num'} ?? ''),
                'rating' => $rating,
                'language' => $language,
            ];
        }

        // Replace the whole window the file covers, keep anything older for catch-up.
        $write = $this->writeProgrammes($sourceId, $programmes, $channelMap, 'service_id', futureOnly: false);
        $imported = $write['imported'];
        $serviceStats = $write['stats'];

        // Update source stats
        $this->db->update('epg_sources', [
            'programme_count' => $imported,
            'channel_count' => count($serviceStats),
        ], 'id = ?', [$sourceId]);

        // Queue unique programme titles for TMDB metadata enrichment (outside the transaction)
        $metadataQueued = 0;
        if ($imported > 0) {
            $metadataQueued = $this->queueMetadataEnrichment($programmes);
        }

        return [
            'success' => true,
            'programmes' => $imported,
            'channels' => count($serviceStats),
            'total_xmltv_channels' => count($xmltvChannels),
            'unmapped' => count($xmltvChannels) - count($channelMap),
            'metadata_queued' => $metadataQueued,
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
            // A calendar day in the caller's timezone, converted to a UTC range
            [$dayStart, $dayEnd] = self::localDayToUtcRange($filters['date'], $filters['tz'] ?? null);
            $where[] = 'p.start_time >= ? AND p.start_time < ?';
            $params[] = $dayStart;
            $params[] = $dayEnd;
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
     * Convert a calendar date in a timezone to the [start, end) UTC range it covers.
     * Falls back to the configured display timezone, then UTC, when the zone is missing or invalid.
     */
    public static function localDayToUtcRange(string $date, ?string $tz): array
    {
        $zone = null;
        foreach ([$tz, self::displayTimezone(), 'UTC'] as $candidate) {
            if (empty($candidate)) continue;
            try { $zone = new \DateTimeZone($candidate); break; } catch (\Exception) {}
        }
        $start = \DateTime::createFromFormat('!Y-m-d', $date, $zone) ?: new \DateTime('today', $zone);
        $end = (clone $start)->modify('+1 day');
        $utc = new \DateTimeZone('UTC');
        return [$start->setTimezone($utc)->format('Y-m-d H:i:s'), $end->setTimezone($utc)->format('Y-m-d H:i:s')];
    }

    /** Display timezone from config/app.php (storage stays UTC). */
    public static function displayTimezone(): string
    {
        static $tz = null;
        if ($tz === null) {
            $cfg = defined('BASE_PATH') && file_exists(BASE_PATH . '/src/Config/app.php') ? require BASE_PATH . '/src/Config/app.php' : [];
            $tz = $cfg['timezone'] ?? 'UTC';
        }
        return $tz;
    }

    /**
     * Parse EIT datetime format: "2024-01-15 14:00:00"
     * EIT/DVB times are typically UTC. The source timezone setting is used as fallback.
     * All times are converted to UTC for storage.
     */
    private function parseEitDateTime(string $dateStr, string $sourceTz = 'UTC'): ?string
    {
        // DVB EIT start times are defined as UTC (ETSI EN 300 468) and TSDuck
        // prints them as such; the source timezone must not be applied here or
        // every programme shifts by the zone offset. The parameter is kept for
        // signature compatibility only.
        $tz = new \DateTimeZone('UTC');
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', trim($dateStr), $tz);
        if (!$dt) {
            $dt = \DateTime::createFromFormat('Y-m-d H:i', trim($dateStr), $tz);
        }
        if (!$dt) return null;

        // Convert to UTC for storage
        $dt->setTimezone(new \DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s');
    }

    /**
     * Parse XMLTV datetime format: "20240115140000 +0000"
     * If the XMLTV string has a timezone offset, it is used for conversion.
     * If no offset is present, the source timezone setting is used as fallback.
     * All times are converted to UTC for storage.
     */
    private function parseXmltvDateTime(string $dateStr, string $sourceTz = 'UTC'): ?string
    {
        $dateStr = trim($dateStr);

        // Format: YYYYMMDDHHMMSS +HHMM (with timezone offset)
        $dt = \DateTime::createFromFormat('YmdHis O', $dateStr);
        if (!$dt) {
            // Try without timezone offset - use source timezone as fallback
            $tz = new \DateTimeZone($sourceTz);
            $dt = \DateTime::createFromFormat('YmdHis', substr($dateStr, 0, 14), $tz);
        }
        if (!$dt) return null;

        // Convert to UTC for storage
        $dt->setTimezone(new \DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s');
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

    // ========================================================================
    // METADATA ENRICHMENT
    // ========================================================================

    /**
     * Queue unique programme titles for TMDB metadata enrichment.
     * Runs enrichment immediately for a small batch; queues the rest.
     */
    private function queueMetadataEnrichment(array $programmes): int
    {
        // Extract unique titles
        $titles = [];
        foreach ($programmes as $prog) {
            $title = is_array($prog) ? ($prog['title'] ?? '') : (string)$prog;
            if (!empty($title)) {
                $titles[] = $title;
            }
        }

        if (empty($titles)) return 0;

        try {
            $epgMetadata = new EpgMetadataService();
            $queued = $epgMetadata->queueTitlesForEnrichment($titles);

            // Process ALL pending titles (not just newly queued — older pending may still exist)
            $epgMetadata->processAllPending();

            return $queued;
        } catch (\Throwable $e) {
            error_log("[EpgService] Metadata enrichment error: " . $e->getMessage());
            return 0;
        }
    }
}
