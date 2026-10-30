-- 038_epg_csv_schedule_sources.sql
-- "Schedule CSV" EPG source: a hand-maintained spreadsheet for ONE channel
-- (name, description, start_time, end_time), uploaded in the admin. Used for
-- local channels that have no EIT on a satellite stream and no XMLTV feed.
SET NAMES utf8mb4;

-- Widen the type enum (MODIFY is safe to re-run: same definition each time)
ALTER TABLE `epg_sources`
    MODIFY COLUMN `type` ENUM('eit', 'xmltv_file', 'xmltv_url', 'csv') NOT NULL DEFAULT 'eit';

-- The channel a CSV source feeds
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'epg_sources' AND COLUMN_NAME = 'channel_id');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `epg_sources` ADD COLUMN `channel_id` INT UNSIGNED DEFAULT NULL AFTER `type`',
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'epg_sources' AND INDEX_NAME = 'idx_epg_sources_channel');
SET @s = IF(@idx_exists = 0,
    'ALTER TABLE `epg_sources` ADD INDEX `idx_epg_sources_channel` (`channel_id`)',
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
