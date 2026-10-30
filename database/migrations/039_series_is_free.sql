-- 039_series_is_free.sql
-- "Free Content" flag for TV shows, matching movies.is_free and
-- channels.available_without_purchase: playable without a package.
SET NAMES utf8mb4;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'series' AND COLUMN_NAME = 'is_free');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `series` ADD COLUMN `is_free` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Available without a package'' AFTER `is_featured`',
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
