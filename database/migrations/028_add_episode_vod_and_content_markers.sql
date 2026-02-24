-- Migration 028: Add VOD transcode tracking to episodes + content markers table
SET NAMES utf8mb4;

-- ============================================
-- 1. VOD transcode columns on series_episodes
-- ============================================

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'series_episodes'
    AND COLUMN_NAME = 'vod_server_id');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `series_episodes` ADD COLUMN `vod_server_id` INT UNSIGNED DEFAULT NULL AFTER `stream_url_backup`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'series_episodes'
    AND COLUMN_NAME = 'vod_job_id');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `series_episodes` ADD COLUMN `vod_job_id` INT UNSIGNED DEFAULT NULL AFTER `vod_server_id`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'series_episodes'
    AND COLUMN_NAME = 'vod_content_id');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `series_episodes` ADD COLUMN `vod_content_id` VARCHAR(255) DEFAULT NULL AFTER `vod_job_id`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'series_episodes'
    AND COLUMN_NAME = 'vod_status');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `series_episodes` ADD COLUMN `vod_status` VARCHAR(20) DEFAULT NULL AFTER `vod_content_id`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'series_episodes'
    AND COLUMN_NAME = 'vod_progress');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `series_episodes` ADD COLUMN `vod_progress` DECIMAL(5,2) DEFAULT 0 AFTER `vod_status`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'series_episodes'
    AND COLUMN_NAME = 'vod_error');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `series_episodes` ADD COLUMN `vod_error` TEXT DEFAULT NULL AFTER `vod_progress`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 2. Content markers table
-- For intro/credits/ad-cue markers on movies and episodes
-- ============================================

CREATE TABLE IF NOT EXISTS `content_markers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `content_type` ENUM('movie','episode') NOT NULL,
    `content_id` INT UNSIGNED NOT NULL,
    `marker_type` VARCHAR(50) NOT NULL COMMENT 'intro_start, intro_end, credits_start, ad_cue',
    `position_seconds` DECIMAL(10,3) NOT NULL COMMENT 'Timestamp in seconds (ms precision)',
    `label` VARCHAR(100) DEFAULT NULL COMMENT 'Optional display label',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_content` (`content_type`, `content_id`),
    INDEX `idx_type` (`content_type`, `content_id`, `marker_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
