-- Migration: Recreate Series Tables
-- Description: Drop and recreate series tables with correct schema (fixes missing columns from partial migration)
-- Date: 2026-01-29

SET NAMES utf8mb4;

-- Disable foreign key checks so we can drop in any order
SET FOREIGN_KEY_CHECKS = 0;

-- Drop any tables that reference series (from previous sessions)
DROP TABLE IF EXISTS `vod_assets`;
DROP TABLE IF EXISTS `series_categories`;
DROP TABLE IF EXISTS `series_cast`;
DROP TABLE IF EXISTS `series_artwork`;
DROP TABLE IF EXISTS `series_trailers`;
DROP TABLE IF EXISTS `series_episodes`;
DROP TABLE IF EXISTS `series_seasons`;
DROP TABLE IF EXISTS `series`;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Series main table (TV Shows)
CREATE TABLE `series` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tmdb_id` INT UNSIGNED NULL COMMENT 'TMDB external ID for metadata sync',
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `original_title` VARCHAR(255) NULL,
    `tagline` VARCHAR(500) NULL,
    `synopsis` TEXT NULL,
    `year` YEAR NULL COMMENT 'First air date year',
    `first_air_date` DATE NULL,
    `last_air_date` DATE NULL,
    `show_status` VARCHAR(50) NULL COMMENT 'Returning Series, Ended, Canceled, In Production',
    `rating` VARCHAR(10) NULL COMMENT 'Content rating (TV-Y, TV-PG, TV-14, TV-MA, etc.)',
    `episode_run_time` INT UNSIGNED NULL COMMENT 'Average episode duration in minutes',
    `vote_average` DECIMAL(3,1) NULL COMMENT 'TMDB rating',
    `genres` JSON NULL COMMENT 'Array of genre names',
    `creators` TEXT NULL COMMENT 'Comma-separated list of creators',
    `networks` TEXT NULL COMMENT 'Comma-separated list of networks',
    `language` VARCHAR(10) DEFAULT 'en',
    `country` VARCHAR(100) NULL,

    -- Image URLs
    `poster_url` VARCHAR(500) NULL,
    `backdrop_url` VARCHAR(500) NULL,
    `logo_url` VARCHAR(500) NULL COMMENT 'Clear logo from Fanart.tv',

    -- Counts (cached from seasons/episodes)
    `number_of_seasons` INT UNSIGNED DEFAULT 0,
    `number_of_episodes` INT UNSIGNED DEFAULT 0,

    -- Category (uses existing categories table with type=series)
    `category_id` INT UNSIGNED NULL,

    -- Status
    `status` ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    `is_featured` TINYINT(1) DEFAULT 0,
    `source` ENUM('manual', 'tmdb') DEFAULT 'manual',

    -- Tracking
    `views` INT UNSIGNED DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `idx_slug` (`slug`),
    INDEX `idx_tmdb_id` (`tmdb_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_year` (`year`),
    INDEX `idx_featured` (`is_featured`),
    INDEX `idx_category` (`category_id`),
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Series seasons table
CREATE TABLE `series_seasons` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `series_id` INT UNSIGNED NOT NULL,
    `tmdb_id` INT UNSIGNED NULL COMMENT 'TMDB season ID',
    `season_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `name` VARCHAR(255) NULL COMMENT 'Season name (e.g. Season 1, Specials)',
    `overview` TEXT NULL,
    `poster_url` VARCHAR(500) NULL,
    `air_date` DATE NULL,
    `episode_count` INT UNSIGNED DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_series` (`series_id`),
    INDEX `idx_season_number` (`season_number`),
    UNIQUE KEY `idx_series_season` (`series_id`, `season_number`),
    FOREIGN KEY (`series_id`) REFERENCES `series`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Series episodes table
CREATE TABLE `series_episodes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `series_id` INT UNSIGNED NOT NULL,
    `season_id` INT UNSIGNED NOT NULL,
    `tmdb_id` INT UNSIGNED NULL COMMENT 'TMDB episode ID',
    `episode_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `name` VARCHAR(255) NULL,
    `overview` TEXT NULL,
    `air_date` DATE NULL,
    `runtime` INT UNSIGNED NULL COMMENT 'Duration in minutes',
    `still_url` VARCHAR(500) NULL COMMENT 'Episode still/thumbnail image',
    `vote_average` DECIMAL(3,1) NULL,

    -- Stream URLs (per episode)
    `stream_url` VARCHAR(1000) NULL COMMENT 'Primary stream URL',
    `stream_url_backup` VARCHAR(1000) NULL COMMENT 'Backup stream URL',

    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_series` (`series_id`),
    INDEX `idx_season` (`season_id`),
    INDEX `idx_episode_number` (`episode_number`),
    UNIQUE KEY `idx_season_episode` (`season_id`, `episode_number`),
    FOREIGN KEY (`series_id`) REFERENCES `series`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`season_id`) REFERENCES `series_seasons`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Series trailers table (per season, with optional season_id)
CREATE TABLE `series_trailers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `series_id` INT UNSIGNED NOT NULL,
    `season_id` INT UNSIGNED NULL COMMENT 'NULL = show-level trailer, set = season trailer',
    `name` VARCHAR(255) NULL,
    `type` ENUM('trailer', 'teaser', 'clip', 'behind_the_scenes', 'featurette') DEFAULT 'trailer',
    `url` VARCHAR(500) NOT NULL COMMENT 'YouTube or other video URL',
    `video_key` VARCHAR(50) NULL COMMENT 'YouTube video ID',
    `source` ENUM('tmdb', 'youtube', 'manual') DEFAULT 'manual',
    `is_primary` TINYINT(1) DEFAULT 0,
    `sort_order` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_series` (`series_id`),
    INDEX `idx_season` (`season_id`),
    INDEX `idx_primary` (`is_primary`),
    FOREIGN KEY (`series_id`) REFERENCES `series`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`season_id`) REFERENCES `series_seasons`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Series artwork table
CREATE TABLE `series_artwork` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `series_id` INT UNSIGNED NOT NULL,
    `type` ENUM('poster', 'backdrop', 'logo', 'banner', 'thumb', 'characterart') NOT NULL,
    `url` VARCHAR(500) NOT NULL,
    `source` ENUM('tmdb', 'fanart', 'manual') DEFAULT 'manual',
    `language` VARCHAR(10) DEFAULT 'en',
    `is_primary` TINYINT(1) DEFAULT 0,
    `width` INT UNSIGNED NULL,
    `height` INT UNSIGNED NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_series` (`series_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_primary` (`is_primary`),
    FOREIGN KEY (`series_id`) REFERENCES `series`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Series cast table
CREATE TABLE `series_cast` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `series_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `character_name` VARCHAR(255) NULL COMMENT 'Character played (for actors)',
    `role` ENUM('actor', 'creator', 'writer', 'producer', 'composer', 'director') DEFAULT 'actor',
    `profile_url` VARCHAR(500) NULL COMMENT 'Profile image URL',
    `tmdb_person_id` INT UNSIGNED NULL,
    `sort_order` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_series` (`series_id`),
    INDEX `idx_role` (`role`),
    FOREIGN KEY (`series_id`) REFERENCES `series`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Series categories junction table
CREATE TABLE `series_categories` (
    `series_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `is_primary` TINYINT(1) DEFAULT 0,
    `sort_order` INT DEFAULT 0,

    PRIMARY KEY (`series_id`, `category_id`),
    INDEX `idx_category` (`category_id`),
    FOREIGN KEY (`series_id`) REFERENCES `series`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add default series categories if they don't exist
INSERT IGNORE INTO `categories` (`name`, `slug`, `type`, `is_active`, `sort_order`) VALUES
    ('Action & Adventure', 'action-adventure', 'series', 1, 1),
    ('Animation', 'animation-series', 'series', 1, 2),
    ('Comedy', 'comedy-series', 'series', 1, 3),
    ('Crime', 'crime-series', 'series', 1, 4),
    ('Documentary', 'documentary-series', 'series', 1, 5),
    ('Drama', 'drama-series', 'series', 1, 6),
    ('Family', 'family-series', 'series', 1, 7),
    ('Kids', 'kids-series', 'series', 1, 8),
    ('Mystery', 'mystery-series', 'series', 1, 9),
    ('News', 'news-series', 'series', 1, 10),
    ('Reality', 'reality-series', 'series', 1, 11),
    ('Sci-Fi & Fantasy', 'sci-fi-fantasy-series', 'series', 1, 12),
    ('Soap', 'soap-series', 'series', 1, 13),
    ('Talk', 'talk-series', 'series', 1, 14),
    ('War & Politics', 'war-politics-series', 'series', 1, 15),
    ('Western', 'western-series', 'series', 1, 16);
