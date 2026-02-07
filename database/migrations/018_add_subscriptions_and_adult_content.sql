-- Migration 018: Subscriber Subscriptions & Adult Content System
-- Links subscribers to packages, adds adult content flags and controls
SET NAMES utf8mb4;

-- ============================================
-- Subscriber Subscriptions (links subscribers to packages)
-- ============================================
CREATE TABLE IF NOT EXISTS `subscriber_subscriptions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscriber_id` INT UNSIGNED NOT NULL,
    `package_id` INT UNSIGNED NOT NULL,
    `status` ENUM('active', 'trial', 'expired', 'cancelled', 'suspended') NOT NULL DEFAULT 'active',
    `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL COMMENT 'NULL = never expires (lifetime/free)',
    `trial_ends_at` DATETIME DEFAULT NULL,
    `cancelled_at` DATETIME DEFAULT NULL,
    `auto_renew` TINYINT(1) NOT NULL DEFAULT 1,
    `payment_method` VARCHAR(50) DEFAULT NULL COMMENT 'stripe, paypal, manual, etc.',
    `payment_reference` VARCHAR(255) DEFAULT NULL COMMENT 'External payment ID',
    `notes` TEXT DEFAULT NULL COMMENT 'Admin notes',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_subscriber` (`subscriber_id`),
    INDEX `idx_package` (`package_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_expires` (`expires_at`),
    INDEX `idx_subscriber_status` (`subscriber_id`, `status`),
    FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Add adult content flag to content tables (MySQL-compatible)
-- ============================================

-- Helper procedure to add column if not exists
DROP PROCEDURE IF EXISTS add_column_if_not_exists;
DELIMITER //
CREATE PROCEDURE add_column_if_not_exists(
    IN tbl_name VARCHAR(64),
    IN col_name VARCHAR(64),
    IN col_definition VARCHAR(255)
)
BEGIN
    SET @table_name = tbl_name;
    SET @column_name = col_name;
    SET @column_def = col_definition;

    SELECT COUNT(*) INTO @col_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @table_name
      AND COLUMN_NAME = @column_name;

    IF @col_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `', @column_name, '` ', @column_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- Add is_adult to movies
CALL add_column_if_not_exists('movies', 'is_adult', 'TINYINT(1) NOT NULL DEFAULT 0');
-- Add is_adult to series
CALL add_column_if_not_exists('series', 'is_adult', 'TINYINT(1) NOT NULL DEFAULT 0');
-- Add is_adult to channels
CALL add_column_if_not_exists('channels', 'is_adult', 'TINYINT(1) NOT NULL DEFAULT 0');
-- Add is_adult to packages
CALL add_column_if_not_exists('packages', 'is_adult', 'TINYINT(1) NOT NULL DEFAULT 0');
-- Add adult_enabled to subscribers
CALL add_column_if_not_exists('subscribers', 'adult_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');

-- Clean up procedure
DROP PROCEDURE IF EXISTS add_column_if_not_exists;

-- ============================================
-- Add indexes for adult content filtering
-- ============================================

-- Helper procedure to add index if not exists
DROP PROCEDURE IF EXISTS add_index_if_not_exists;
DELIMITER //
CREATE PROCEDURE add_index_if_not_exists(
    IN tbl_name VARCHAR(64),
    IN idx_name VARCHAR(64),
    IN idx_columns VARCHAR(255)
)
BEGIN
    SET @table_name = tbl_name;
    SET @index_name = idx_name;

    SELECT COUNT(*) INTO @idx_exists
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @table_name
      AND INDEX_NAME = @index_name;

    IF @idx_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', @table_name, '` ADD INDEX `', @index_name, '` (', idx_columns, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- Add indexes
CALL add_index_if_not_exists('movies', 'idx_adult', '`is_adult`');
CALL add_index_if_not_exists('series', 'idx_adult', '`is_adult`');
CALL add_index_if_not_exists('channels', 'idx_adult', '`is_adult`');

-- Clean up procedure
DROP PROCEDURE IF EXISTS add_index_if_not_exists;

-- ============================================
-- Add subscription page type to app_pages
-- ============================================
-- Modify ENUM to add new page types
ALTER TABLE `app_pages` MODIFY COLUMN `page_type` ENUM(
    'home', 'movies', 'live_tv', 'series', 'categories',
    'search', 'watchlist', 'settings', 'player', 'details',
    'custom', 'subscription', 'profile'
) NOT NULL;

-- ============================================
-- Seed subscription and profile pages for each platform
-- ============================================
INSERT INTO `app_pages` (`name`, `slug`, `page_type`, `platform`, `icon`, `is_system`, `sort_order`) VALUES
('Subscribe', 'subscribe', 'subscription', 'web', 'lucide-credit-card', 1, 8),
('Subscribe', 'subscribe', 'subscription', 'mobile', 'lucide-credit-card', 1, 8),
('Subscribe', 'subscribe', 'subscription', 'tv', 'lucide-credit-card', 1, 8),
('Subscribe', 'subscribe', 'subscription', 'stb', 'lucide-credit-card', 1, 8),
('Profile', 'profile', 'profile', 'web', 'lucide-user', 1, 9),
('Profile', 'profile', 'profile', 'mobile', 'lucide-user', 1, 9),
('Profile', 'profile', 'profile', 'tv', 'lucide-user', 1, 9),
('Profile', 'profile', 'profile', 'stb', 'lucide-user', 1, 9)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
