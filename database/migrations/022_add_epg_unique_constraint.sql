-- Add unique constraint on epg_programs to prevent duplicate events
-- Uses (epg_source_id, channel_id, external_event_id) to identify unique programmes

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
