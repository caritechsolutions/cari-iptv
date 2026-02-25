-- 027_add_vod_server_public_url.sql
-- Add public_url to vod_servers table.
-- The existing 'url' field is used for server-to-server API calls (internal network).
-- The new 'public_url' is used for browser-facing HLS stream URLs (e.g. via Nginx proxy).

SET NAMES utf8mb4;

-- public_url: the externally-accessible URL for this VOD server (used in stream_url for players)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'vod_servers'
    AND COLUMN_NAME = 'public_url');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `vod_servers` ADD COLUMN `public_url` VARCHAR(500) DEFAULT NULL AFTER `url`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
