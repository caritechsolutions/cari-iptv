-- 037_epg_fetch_backoff.sql
-- Track every fetch attempt (not only successes) and consecutive failures so the
-- EPG cron can back off from a dead source instead of retrying every 5 minutes.
SET NAMES utf8mb4;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'epg_sources' AND COLUMN_NAME = 'last_attempt');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `epg_sources` ADD COLUMN `last_attempt` DATETIME DEFAULT NULL AFTER `last_fetch`',
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'epg_sources' AND COLUMN_NAME = 'consecutive_errors');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `epg_sources` ADD COLUMN `consecutive_errors` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `last_attempt`',
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Programme lookups are by channel + time window
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'epg_programs' AND INDEX_NAME = 'idx_epg_programs_channel_time');
SET @s = IF(@idx_exists = 0,
    'ALTER TABLE `epg_programs` ADD INDEX `idx_epg_programs_channel_time` (`channel_id`, `start_time`, `end_time`)',
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
