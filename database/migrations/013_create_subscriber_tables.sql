-- Migration 013: Subscriber Management System
SET NAMES utf8mb4;

-- ============================================
-- Subscriber Groups
-- Groups that subscribers can be assigned to
-- ============================================
CREATE TABLE IF NOT EXISTS `subscriber_groups` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `color` VARCHAR(7) DEFAULT '#6366f1',
    `icon` VARCHAR(50) DEFAULT 'lucide-users',
    `subscriber_count` INT UNSIGNED DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Subscribers
-- End-user accounts for the IPTV platform
-- ============================================
CREATE TABLE IF NOT EXISTS `subscribers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `password` VARCHAR(255) DEFAULT NULL,
    `first_name` VARCHAR(100) DEFAULT NULL,
    `last_name` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `avatar` VARCHAR(500) DEFAULT NULL,
    `status` ENUM('active','inactive','suspended','expired') NOT NULL DEFAULT 'active',
    `is_disabled` TINYINT(1) NOT NULL DEFAULT 0,
    `email_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `phone_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `max_connections` INT UNSIGNED DEFAULT 1,
    `npvr_limit` INT UNSIGNED DEFAULT 0 COMMENT 'NPVR limit in minutes',
    `birthday` DATE DEFAULT NULL,
    `parental_pin` VARCHAR(10) DEFAULT '0000',
    `country` VARCHAR(100) DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `zip_code` VARCHAR(20) DEFAULT NULL,
    `external_id` VARCHAR(255) DEFAULT NULL COMMENT 'External system reference ID',
    `notes` TEXT DEFAULT NULL,
    `last_login` DATETIME DEFAULT NULL,
    `login_count` INT UNSIGNED DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX `idx_username` (`username`),
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_external_id` (`external_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Subscriber Group Members (Many-to-Many)
-- Links subscribers to groups
-- ============================================
CREATE TABLE IF NOT EXISTS `subscriber_group_members` (
    `subscriber_id` INT UNSIGNED NOT NULL,
    `group_id` INT UNSIGNED NOT NULL,
    `added_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`subscriber_id`, `group_id`),
    FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`group_id`) REFERENCES `subscriber_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
