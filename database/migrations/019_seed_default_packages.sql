-- Migration 019: Reconcile packages table schema & seed content groups
-- Adds missing columns to packages table created by install.sh
-- The install.sh schema only has: id, name, slug, description, price, currency,
-- duration_days, max_streams, is_active, is_featured, sort_order, created_at, updated_at
-- This migration adds the extended columns needed by the admin panel and API.
SET NAMES utf8mb4;

-- ============================================
-- Add missing columns to packages table
-- Uses a stored procedure for MySQL compatibility
-- ============================================
DROP PROCEDURE IF EXISTS add_col_if_missing;
DELIMITER //
CREATE PROCEDURE add_col_if_missing(
    IN tbl VARCHAR(64),
    IN col VARCHAR(64),
    IN col_def VARCHAR(500)
)
BEGIN
    SELECT COUNT(*) INTO @exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col;
    IF @exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `', col, '` ', col_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- Add missing columns to packages
CALL add_col_if_missing('packages', 'icon', "VARCHAR(50) DEFAULT 'lucide-package'");
CALL add_col_if_missing('packages', 'color', "VARCHAR(7) DEFAULT '#6366f1'");
CALL add_col_if_missing('packages', 'billing_period', "ENUM('daily','weekly','monthly','quarterly','semi_annual','annual') NOT NULL DEFAULT 'monthly'");
CALL add_col_if_missing('packages', 'tax_rate', "DECIMAL(5,2) NOT NULL DEFAULT 0.00");
CALL add_col_if_missing('packages', 'tax_inclusive', "TINYINT(1) NOT NULL DEFAULT 0");
CALL add_col_if_missing('packages', 'trial_days', "INT UNSIGNED DEFAULT 0");
CALL add_col_if_missing('packages', 'max_connections', "INT UNSIGNED DEFAULT 1");
CALL add_col_if_missing('packages', 'geo_countries', "JSON DEFAULT NULL");
CALL add_col_if_missing('packages', 'platforms', "JSON DEFAULT NULL");
CALL add_col_if_missing('packages', 'features', "JSON DEFAULT NULL");
CALL add_col_if_missing('packages', 'is_free', "TINYINT(1) NOT NULL DEFAULT 0");
CALL add_col_if_missing('packages', 'is_adult', "TINYINT(1) NOT NULL DEFAULT 0");

-- Clean up
DROP PROCEDURE IF EXISTS add_col_if_missing;

-- ============================================
-- Create content_groups table if not exists
-- (install.sh doesn't create it)
-- ============================================
CREATE TABLE IF NOT EXISTS `content_groups` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `icon` VARCHAR(50) DEFAULT 'lucide-layers',
    `color` VARCHAR(7) DEFAULT '#6366f1',
    `content_count` INT UNSIGNED DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT UNSIGNED DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX `idx_slug` (`slug`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `content_group_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT UNSIGNED NOT NULL,
    `content_type` ENUM('channel','movie','series','category') NOT NULL,
    `content_id` INT UNSIGNED NOT NULL,
    `sort_order` INT UNSIGNED DEFAULT 0,
    `added_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX `idx_group_content` (`group_id`, `content_type`, `content_id`),
    INDEX `idx_content` (`content_type`, `content_id`),
    FOREIGN KEY (`group_id`) REFERENCES `content_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `package_content_groups` (
    `package_id` INT UNSIGNED NOT NULL,
    `group_id` INT UNSIGNED NOT NULL,
    `added_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`package_id`, `group_id`),
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`group_id`) REFERENCES `content_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Seed default content groups (skip if exist)
-- ============================================
INSERT INTO `content_groups` (`name`, `slug`, `description`, `icon`, `color`, `is_active`, `sort_order`)
SELECT * FROM (SELECT
    'Basic Channels' AS `name`, 'basic-channels' AS `slug`,
    'Standard definition channels available with basic subscription' AS `description`,
    'lucide-tv' AS `icon`, '#3b82f6' AS `color`, 1 AS `is_active`, 1 AS `sort_order`
) AS tmp WHERE NOT EXISTS (SELECT 1 FROM `content_groups` WHERE `slug` = 'basic-channels');

INSERT INTO `content_groups` (`name`, `slug`, `description`, `icon`, `color`, `is_active`, `sort_order`)
SELECT * FROM (SELECT
    'Premium Channels' AS `name`, 'premium-channels' AS `slug`,
    'HD and premium channels including sports and movies' AS `description`,
    'lucide-crown' AS `icon`, '#f59e0b' AS `color`, 1 AS `is_active`, 2 AS `sort_order`
) AS tmp WHERE NOT EXISTS (SELECT 1 FROM `content_groups` WHERE `slug` = 'premium-channels');

INSERT INTO `content_groups` (`name`, `slug`, `description`, `icon`, `color`, `is_active`, `sort_order`)
SELECT * FROM (SELECT
    'Movies Library' AS `name`, 'movies-library' AS `slug`,
    'Full access to the movies on demand library' AS `description`,
    'lucide-film' AS `icon`, '#8b5cf6' AS `color`, 1 AS `is_active`, 3 AS `sort_order`
) AS tmp WHERE NOT EXISTS (SELECT 1 FROM `content_groups` WHERE `slug` = 'movies-library');

INSERT INTO `content_groups` (`name`, `slug`, `description`, `icon`, `color`, `is_active`, `sort_order`)
SELECT * FROM (SELECT
    'TV Shows Library' AS `name`, 'tv-shows-library' AS `slug`,
    'Full access to TV series and shows on demand' AS `description`,
    'lucide-clapperboard' AS `icon`, '#ec4899' AS `color`, 1 AS `is_active`, 4 AS `sort_order`
) AS tmp WHERE NOT EXISTS (SELECT 1 FROM `content_groups` WHERE `slug` = 'tv-shows-library');
