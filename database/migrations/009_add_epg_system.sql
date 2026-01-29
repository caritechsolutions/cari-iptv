-- Migration 009: Add EPG source management and channel mapping
-- Extends the existing epg_programs table with source tracking
SET NAMES utf8mb4;

-- ============================================
-- EPG Sources (MPTS/EIT streams, XMLTV files, URLs)
-- ============================================
CREATE TABLE IF NOT EXISTS `epg_sources` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `type` ENUM('eit', 'xmltv_file', 'xmltv_url') NOT NULL DEFAULT 'eit',
    `source_url` VARCHAR(500) DEFAULT NULL COMMENT 'Multicast address for EIT, URL for xmltv_url, NULL for file uploads',
    `source_port` INT UNSIGNED DEFAULT NULL COMMENT 'UDP port for EIT streams',
    `eit_pid` VARCHAR(10) DEFAULT '0x12' COMMENT 'PID for EIT tables (default 0x12 = 18)',
    `capture_timeout` INT UNSIGNED DEFAULT 120 COMMENT 'Seconds to capture EIT data',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `auto_refresh` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Enable cron-based auto refresh',
    `refresh_interval` INT UNSIGNED DEFAULT 3600 COMMENT 'Auto refresh interval in seconds',
    `last_fetch` DATETIME DEFAULT NULL,
    `last_status` ENUM('success', 'error', 'running', 'pending') DEFAULT 'pending',
    `last_message` TEXT DEFAULT NULL,
    `programme_count` INT UNSIGNED DEFAULT 0,
    `channel_count` INT UNSIGNED DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- EPG Channel Map (links EIT service_ids / XMLTV IDs to our channels)
-- ============================================
CREATE TABLE IF NOT EXISTS `epg_channel_map` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `epg_source_id` INT UNSIGNED NOT NULL,
    `epg_channel_id` VARCHAR(100) NOT NULL COMMENT 'service_id from EIT or channel id from XMLTV',
    `epg_channel_name` VARCHAR(255) DEFAULT NULL COMMENT 'Service name from SDT or XMLTV display-name',
    `channel_id` INT UNSIGNED DEFAULT NULL COMMENT 'Mapped channel in our system',
    `is_mapped` TINYINT(1) NOT NULL DEFAULT 0,
    `programme_count` INT UNSIGNED DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`epg_source_id`) REFERENCES `epg_sources`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`channel_id`) REFERENCES `channels`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `uk_source_epg_channel` (`epg_source_id`, `epg_channel_id`),
    INDEX `idx_channel_id` (`channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Extend epg_programs with source tracking
-- ============================================
ALTER TABLE `epg_programs`
    ADD COLUMN `epg_source_id` INT UNSIGNED DEFAULT NULL AFTER `id`,
    ADD COLUMN `subtitle` VARCHAR(500) DEFAULT NULL AFTER `title`,
    ADD COLUMN `external_event_id` VARCHAR(50) DEFAULT NULL COMMENT 'event_id from EIT or programme ID' AFTER `channel_id`,
    ADD COLUMN `language` VARCHAR(10) DEFAULT NULL AFTER `rating`,
    ADD INDEX `idx_source` (`epg_source_id`),
    ADD INDEX `idx_external_event` (`channel_id`, `external_event_id`);
