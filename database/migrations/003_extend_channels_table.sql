-- Migration: Extend channels table for full channel management
-- Version: 003
-- Date: 2026-01-24

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- Streaming Servers (channel sources)
-- ============================================
CREATE TABLE IF NOT EXISTS `streaming_servers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `url` VARCHAR(500) NOT NULL,
    `type` ENUM('flussonic', 'wowza', 'nginx', 'external', 'other') NOT NULL DEFAULT 'external',
    `username` VARCHAR(100) DEFAULT NULL,
    `password` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_active` (`is_active`),
    INDEX `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Content Owners (operators/providers)
-- ============================================
CREATE TABLE IF NOT EXISTS `content_owners` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `logo_url` VARCHAR(500) DEFAULT NULL,
    `contact_email` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Add new columns to channels table
-- ============================================
ALTER TABLE `channels`
    ADD COLUMN `key_code` VARCHAR(50) DEFAULT NULL AFTER `slug`,
    ADD COLUMN `logo_landscape_url` VARCHAR(500) DEFAULT NULL AFTER `logo_url`,
    ADD COLUMN `streaming_server_id` INT UNSIGNED DEFAULT NULL AFTER `stream_url_backup`,
    ADD COLUMN `is_published` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`,
    ADD COLUMN `available_without_purchase` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_published`,
    ADD COLUMN `show_to_demo_users` TINYINT(1) NOT NULL DEFAULT 1 AFTER `available_without_purchase`,
    ADD COLUMN `age_limit` ENUM('0+', '7+', '12+', '16+', '18+') NOT NULL DEFAULT '0+' AFTER `show_to_demo_users`,
    ADD COLUMN `os_platforms` JSON DEFAULT NULL AFTER `age_limit`,
    ADD COLUMN `catchup_period_type` ENUM('days', 'hours') NOT NULL DEFAULT 'days' AFTER `catchup_days`,
    ADD COLUMN `external_id` VARCHAR(100) DEFAULT NULL AFTER `catchup_period_type`,
    ADD COLUMN `content_owner_id` INT UNSIGNED DEFAULT NULL AFTER `external_id`,
    ADD COLUMN `epg_last_update` DATETIME DEFAULT NULL AFTER `epg_channel_id`,
    ADD UNIQUE INDEX `idx_key_code` (`key_code`),
    ADD INDEX `idx_published` (`is_published`),
    ADD INDEX `idx_external_id` (`external_id`),
    ADD INDEX `idx_content_owner` (`content_owner_id`),
    ADD INDEX `idx_streaming_server` (`streaming_server_id`),
    ADD CONSTRAINT `fk_channels_content_owner` FOREIGN KEY (`content_owner_id`)
        REFERENCES `content_owners`(`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_channels_streaming_server` FOREIGN KEY (`streaming_server_id`)
        REFERENCES `streaming_servers`(`id`) ON DELETE SET NULL;

-- ============================================
-- Channel Categories (many-to-many)
-- ============================================
CREATE TABLE IF NOT EXISTS `channel_categories` (
    `channel_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`channel_id`, `category_id`),
    FOREIGN KEY (`channel_id`) REFERENCES `channels`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
    INDEX `idx_category` (`category_id`),
    INDEX `idx_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing category_id to channel_categories
INSERT IGNORE INTO `channel_categories` (`channel_id`, `category_id`, `is_primary`)
SELECT `id`, `category_id`, 1 FROM `channels` WHERE `category_id` IS NOT NULL;

-- ============================================
-- Channel Packages (many-to-many) - extends existing package_channels
-- Already exists in schema, just ensure it's there
-- ============================================

-- ============================================
-- Insert default streaming server
-- ============================================
INSERT INTO `streaming_servers` (`name`, `url`, `type`, `is_active`) VALUES
('External URL', 'https://external', 'external', 1),
('Flussonic Media Server', 'https://flussonic.example.com', 'flussonic', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ============================================
-- Insert default content owner
-- ============================================
INSERT INTO `content_owners` (`name`, `slug`, `is_active`) VALUES
('No Content Owner', 'no-owner', 1),
('CARI Media', 'cari-media', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ============================================
-- Update existing channels with default values
-- ============================================
UPDATE `channels` SET
    `is_published` = `is_active`,
    `os_platforms` = '["all"]',
    `show_to_demo_users` = 1
WHERE `is_published` = 0 AND `os_platforms` IS NULL;

-- Generate key_code for existing channels that don't have one
UPDATE `channels` SET `key_code` = LPAD(`id`, 3, '0') WHERE `key_code` IS NULL;

SET FOREIGN_KEY_CHECKS = 1;
