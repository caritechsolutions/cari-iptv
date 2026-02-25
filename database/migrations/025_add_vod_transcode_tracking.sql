-- 025_add_vod_transcode_tracking.sql
-- Add VOD transcode job tracking columns to movies table
-- Allows the admin UI to persist and display job status across page reloads

SET NAMES utf8mb4;

-- vod_server_id: which VOD server this movie was sent to
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'movies'
    AND COLUMN_NAME = 'vod_server_id');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `movies` ADD COLUMN `vod_server_id` INT UNSIGNED DEFAULT NULL AFTER `stream_url_backup`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- vod_job_id: the job ID on the VOD server
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'movies'
    AND COLUMN_NAME = 'vod_job_id');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `movies` ADD COLUMN `vod_job_id` INT UNSIGNED DEFAULT NULL AFTER `vod_server_id`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- vod_status: current transcode status
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'movies'
    AND COLUMN_NAME = 'vod_status');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `movies` ADD COLUMN `vod_status` VARCHAR(20) DEFAULT NULL AFTER `vod_job_id`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- vod_progress: transcode progress 0-100
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'movies'
    AND COLUMN_NAME = 'vod_progress');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `movies` ADD COLUMN `vod_progress` DECIMAL(5,2) DEFAULT NULL AFTER `vod_status`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- vod_error: error message if failed
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'movies'
    AND COLUMN_NAME = 'vod_error');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `movies` ADD COLUMN `vod_error` TEXT DEFAULT NULL AFTER `vod_progress`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
