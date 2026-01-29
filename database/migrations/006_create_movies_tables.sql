-- Migration: Create Movies Tables
-- Description: Create tables for movies feature with trailers, artwork, and cast support
-- Date: 2026-01-27

SET NAMES utf8mb4;

-- Movies main table
CREATE TABLE IF NOT EXISTS `movies` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tmdb_id` INT UNSIGNED NULL COMMENT 'TMDB external ID for metadata sync',
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `original_title` VARCHAR(255) NULL,
    `tagline` VARCHAR(500) NULL,
    `synopsis` TEXT NULL,
    `year` YEAR NULL,
    `release_date` DATE NULL,
    `rating` VARCHAR(10) NULL COMMENT 'Content rating (PG, PG-13, R, etc.)',
    `runtime` INT UNSIGNED NULL COMMENT 'Duration in minutes',
    `vote_average` DECIMAL(3,1) NULL COMMENT 'TMDB rating',
    `genres` JSON NULL COMMENT 'Array of genre names',
    `director` VARCHAR(255) NULL,
    `writers` TEXT NULL COMMENT 'Comma-separated list of writers',
    `language` VARCHAR(10) DEFAULT 'en',
    `country` VARCHAR(100) NULL,
    `production_companies` TEXT NULL,

    -- Stream URLs (like channels)
    `stream_url` VARCHAR(1000) NULL COMMENT 'Primary stream URL',
    `stream_url_backup` VARCHAR(1000) NULL COMMENT 'Backup stream URL',

    -- Primary images (from TMDB by default)
    `poster_url` VARCHAR(500) NULL,
    `backdrop_url` VARCHAR(500) NULL,
    `logo_url` VARCHAR(500) NULL COMMENT 'Clear logo from Fanart.tv',

    -- Category (uses existing categories table with type=vod)
    `category_id` INT UNSIGNED NULL,

    -- Status
    `status` ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    `is_featured` TINYINT(1) DEFAULT 0,
    `is_free` TINYINT(1) DEFAULT 0 COMMENT 'Royalty-free content flag',
    `source` ENUM('manual', 'tmdb', 'youtube_cc', 'internet_archive') DEFAULT 'manual',
    `source_url` VARCHAR(500) NULL COMMENT 'Original source URL for free content',

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

-- Movie trailers table (supports multiple trailers per movie)
CREATE TABLE IF NOT EXISTS `movie_trailers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `movie_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NULL,
    `type` ENUM('trailer', 'teaser', 'clip', 'behind_the_scenes', 'featurette') DEFAULT 'trailer',
    `url` VARCHAR(500) NOT NULL COMMENT 'YouTube or other video URL',
    `video_key` VARCHAR(50) NULL COMMENT 'YouTube video ID',
    `source` ENUM('tmdb', 'youtube', 'manual') DEFAULT 'manual',
    `is_primary` TINYINT(1) DEFAULT 0,
    `sort_order` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_movie` (`movie_id`),
    INDEX `idx_primary` (`is_primary`),
    FOREIGN KEY (`movie_id`) REFERENCES `movies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Movie artwork table (supports multiple artwork from different sources)
CREATE TABLE IF NOT EXISTS `movie_artwork` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `movie_id` INT UNSIGNED NOT NULL,
    `type` ENUM('poster', 'backdrop', 'logo', 'disc', 'banner', 'thumb') NOT NULL,
    `url` VARCHAR(500) NOT NULL,
    `source` ENUM('tmdb', 'fanart', 'manual') DEFAULT 'manual',
    `language` VARCHAR(10) DEFAULT 'en',
    `is_primary` TINYINT(1) DEFAULT 0,
    `width` INT UNSIGNED NULL,
    `height` INT UNSIGNED NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_movie` (`movie_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_primary` (`is_primary`),
    FOREIGN KEY (`movie_id`) REFERENCES `movies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Movie cast table (stores cast and crew)
CREATE TABLE IF NOT EXISTS `movie_cast` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `movie_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `character_name` VARCHAR(255) NULL COMMENT 'Character played (for actors)',
    `role` ENUM('actor', 'director', 'writer', 'producer', 'composer', 'cinematographer') DEFAULT 'actor',
    `profile_url` VARCHAR(500) NULL COMMENT 'Profile image URL',
    `tmdb_person_id` INT UNSIGNED NULL,
    `sort_order` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_movie` (`movie_id`),
    INDEX `idx_role` (`role`),
    FOREIGN KEY (`movie_id`) REFERENCES `movies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Movie categories junction table (movies can belong to multiple categories)
CREATE TABLE IF NOT EXISTS `movie_categories` (
    `movie_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `is_primary` TINYINT(1) DEFAULT 0,
    `sort_order` INT DEFAULT 0,

    PRIMARY KEY (`movie_id`, `category_id`),
    INDEX `idx_category` (`category_id`),
    FOREIGN KEY (`movie_id`) REFERENCES `movies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add some default VOD categories if they don't exist
INSERT IGNORE INTO `categories` (`name`, `slug`, `type`, `is_active`, `sort_order`) VALUES
    ('Action', 'action', 'vod', 1, 1),
    ('Adventure', 'adventure', 'vod', 1, 2),
    ('Animation', 'animation', 'vod', 1, 3),
    ('Comedy', 'comedy', 'vod', 1, 4),
    ('Crime', 'crime', 'vod', 1, 5),
    ('Documentary', 'documentary', 'vod', 1, 6),
    ('Drama', 'drama', 'vod', 1, 7),
    ('Family', 'family', 'vod', 1, 8),
    ('Fantasy', 'fantasy', 'vod', 1, 9),
    ('History', 'history', 'vod', 1, 10),
    ('Horror', 'horror', 'vod', 1, 11),
    ('Music', 'music', 'vod', 1, 12),
    ('Mystery', 'mystery', 'vod', 1, 13),
    ('Romance', 'romance', 'vod', 1, 14),
    ('Science Fiction', 'science-fiction', 'vod', 1, 15),
    ('Thriller', 'thriller', 'vod', 1, 16),
    ('War', 'war', 'vod', 1, 17),
    ('Western', 'western', 'vod', 1, 18);
