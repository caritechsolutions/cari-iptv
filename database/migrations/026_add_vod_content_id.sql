-- 026_add_vod_content_id.sql
-- Add vod_content_id to movies table to track the content ID used on the VOD server.
-- This is needed for DRM key lookup since the content ID may differ from movie-{id}.

SET NAMES utf8mb4;

-- vod_content_id: the content_id used on the VOD server for this movie
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'movies'
    AND COLUMN_NAME = 'vod_content_id');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `movies` ADD COLUMN `vod_content_id` VARCHAR(255) DEFAULT NULL AFTER `vod_job_id`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
