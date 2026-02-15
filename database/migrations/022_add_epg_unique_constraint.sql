-- Add unique constraint on epg_programs to prevent duplicate events
-- Uses (epg_source_id, channel_id, external_event_id) to identify unique programmes

-- First, remove existing duplicates keeping only the row with the highest id
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'epg_programs'
    AND INDEX_NAME = 'uq_epg_source_channel_event');
SET @s = IF(@idx_exists = 0,
    'DELETE p1 FROM epg_programs p1
     INNER JOIN epg_programs p2
     WHERE p1.epg_source_id = p2.epg_source_id
       AND p1.channel_id = p2.channel_id
       AND p1.external_event_id = p2.external_event_id
       AND p1.id < p2.id',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Now add the unique index
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'epg_programs'
    AND INDEX_NAME = 'uq_epg_source_channel_event');
SET @s = IF(@idx_exists = 0,
    'ALTER TABLE `epg_programs` ADD UNIQUE INDEX `uq_epg_source_channel_event` (`epg_source_id`, `channel_id`, `external_event_id`)',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
