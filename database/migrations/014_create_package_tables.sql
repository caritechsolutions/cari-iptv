-- Migration 014: Packages & Content Groups System
SET NAMES utf8mb4;

-- ============================================
-- Content Groups
-- Bundles of content (channels, movies, series, categories)
-- that can be assigned to packages
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

-- ============================================
-- Content Group Items
-- Individual content items within a group
-- ============================================
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

-- ============================================
-- Packages
-- Subscription packages with pricing and features
-- ============================================
CREATE TABLE IF NOT EXISTS `packages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `icon` VARCHAR(50) DEFAULT 'lucide-package',
    `color` VARCHAR(7) DEFAULT '#6366f1',

    -- Pricing
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'USD',
    `billing_period` ENUM('daily','weekly','monthly','quarterly','semi_annual','annual') NOT NULL DEFAULT 'monthly',

    -- Tax
    `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Tax percentage',
    `tax_inclusive` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=price includes tax, 0=tax added on top',

    -- Trial
    `trial_days` INT UNSIGNED DEFAULT 0,

    -- Limits
    `max_connections` INT UNSIGNED DEFAULT 1 COMMENT 'Simultaneous logins/streams',

    -- Geo & Platform
    `geo_countries` JSON DEFAULT NULL COMMENT 'Array of country codes, null=all',
    `platforms` JSON DEFAULT NULL COMMENT 'Array of platforms (web,mobile,tv,stb), null=all',

    -- Features (extensible JSON)
    `features` JSON DEFAULT NULL COMMENT 'video_quality, catchup_days, npvr_hours, ad_free, etc.',

    -- Status
    `is_free` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT UNSIGNED DEFAULT 0,

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX `idx_slug` (`slug`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_billing` (`billing_period`),
    INDEX `idx_featured` (`is_featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Package Content Groups (Many-to-Many)
-- Links packages to content groups
-- ============================================
CREATE TABLE IF NOT EXISTS `package_content_groups` (
    `package_id` INT UNSIGNED NOT NULL,
    `group_id` INT UNSIGNED NOT NULL,
    `added_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`package_id`, `group_id`),
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`group_id`) REFERENCES `content_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
