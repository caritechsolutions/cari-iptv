-- Migration 010: App Layout Builder
-- Configurable app home screen layouts per platform
SET NAMES utf8mb4;

-- ============================================
-- App Layouts (one active per platform)
-- ============================================
CREATE TABLE IF NOT EXISTS `app_layouts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `platform` ENUM('web', 'mobile', 'tv', 'stb') NOT NULL,
    `status` ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `schedule_start` DATETIME DEFAULT NULL,
    `schedule_end` DATETIME DEFAULT NULL,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `updated_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_platform_status` (`platform`, `status`),
    INDEX `idx_platform_default` (`platform`, `is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- App Layout Sections (components within a layout)
-- ============================================
CREATE TABLE IF NOT EXISTS `app_layout_sections` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `layout_id` INT UNSIGNED NOT NULL,
    `section_type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `settings` JSON NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_layout_order` (`layout_id`, `sort_order`),
    FOREIGN KEY (`layout_id`) REFERENCES `app_layouts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- App Layout Items (content for curated sections)
-- ============================================
CREATE TABLE IF NOT EXISTS `app_layout_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `section_id` INT UNSIGNED NOT NULL,
    `content_type` ENUM('movie', 'series', 'channel', 'category', 'custom') NOT NULL,
    `content_id` INT UNSIGNED DEFAULT NULL,
    `settings` JSON DEFAULT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_section_order` (`section_id`, `sort_order`),
    INDEX `idx_content` (`content_type`, `content_id`),
    FOREIGN KEY (`section_id`) REFERENCES `app_layout_sections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
