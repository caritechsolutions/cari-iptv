-- Migration 021: Add timezone column to epg_sources
-- EPG sources can specify their timezone so times are converted to UTC on import
SET NAMES utf8mb4;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'epg_sources'
    AND COLUMN_NAME = 'timezone');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `epg_sources` ADD COLUMN `timezone` VARCHAR(50) DEFAULT ''UTC'' AFTER `refresh_interval`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
