-- Migration 024: Add VOD Server integration settings
-- Seeds default settings for VOD Server connection

SET NAMES utf8mb4;

-- Insert VOD Server settings (only if they don't exist)
INSERT INTO settings (`group`, `key`, `value`, `type`)
SELECT 'vod_server', 'vod_server_enabled', '0', 'boolean'
FROM DUAL WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE `group` = 'vod_server' AND `key` = 'vod_server_enabled'
);

INSERT INTO settings (`group`, `key`, `value`, `type`)
SELECT 'vod_server', 'vod_server_url', '', 'string'
FROM DUAL WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE `group` = 'vod_server' AND `key` = 'vod_server_url'
);

INSERT INTO settings (`group`, `key`, `value`, `type`)
SELECT 'vod_server', 'vod_server_api_key', '', 'string'
FROM DUAL WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE `group` = 'vod_server' AND `key` = 'vod_server_api_key'
);

INSERT INTO settings (`group`, `key`, `value`, `type`)
SELECT 'vod_server', 'vod_server_timeout', '30', 'integer'
FROM DUAL WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE `group` = 'vod_server' AND `key` = 'vod_server_timeout'
);
