-- Migration 032: Analytics event tracking + Recommendation engine tables
SET NAMES utf8mb4;

-- =========================================================================
-- ANALYTICS: Behavioral event collection
-- =========================================================================

CREATE TABLE IF NOT EXISTS `subscriber_events` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscriber_id` INT UNSIGNED NOT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `event_category` VARCHAR(30) NOT NULL DEFAULT 'general',
    `content_type` VARCHAR(30) DEFAULT NULL,
    `content_id` INT UNSIGNED DEFAULT NULL,
    `page` VARCHAR(100) DEFAULT NULL,
    `metadata` JSON DEFAULT NULL,
    `session_id` VARCHAR(64) DEFAULT NULL,
    `platform` VARCHAR(20) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_sub_events_subscriber` (`subscriber_id`, `created_at` DESC),
    INDEX `idx_sub_events_type` (`event_type`, `created_at` DESC),
    INDEX `idx_sub_events_content` (`content_type`, `content_id`, `created_at` DESC),
    INDEX `idx_sub_events_session` (`session_id`),
    INDEX `idx_sub_events_created` (`created_at`),

    FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- RECOMMENDATIONS: User profiles and pre-computed recommendations
-- =========================================================================

-- AI-generated taste profiles per subscriber (refreshed by cron/backend)
CREATE TABLE IF NOT EXISTS `subscriber_profiles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscriber_id` INT UNSIGNED NOT NULL,
    `genre_affinities` JSON DEFAULT NULL,
    `actor_affinities` JSON DEFAULT NULL,
    `director_affinities` JSON DEFAULT NULL,
    `viewing_patterns` JSON DEFAULT NULL,
    `language_preferences` JSON DEFAULT NULL,
    `content_preferences` JSON DEFAULT NULL,
    `ai_summary` TEXT DEFAULT NULL,
    `profile_version` INT UNSIGNED NOT NULL DEFAULT 1,
    `last_analyzed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX `idx_sub_profiles_subscriber` (`subscriber_id`),
    FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Named recommendation sets per subscriber ("Because you watched X", "Trending in JM", etc.)
CREATE TABLE IF NOT EXISTS `recommendation_sets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscriber_id` INT UNSIGNED NOT NULL,
    `set_type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `reason` TEXT DEFAULT NULL,
    `source_content_type` VARCHAR(30) DEFAULT NULL,
    `source_content_id` INT UNSIGNED DEFAULT NULL,
    `priority` TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `expires_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_rec_sets_subscriber` (`subscriber_id`, `priority`, `set_type`),
    INDEX `idx_rec_sets_expires` (`expires_at`),
    FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual recommended items within a set
CREATE TABLE IF NOT EXISTS `recommendation_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `set_id` INT UNSIGNED NOT NULL,
    `content_type` VARCHAR(30) NOT NULL,
    `content_id` INT UNSIGNED NOT NULL,
    `score` DECIMAL(5,3) NOT NULL DEFAULT 0.000,
    `match_percent` TINYINT UNSIGNED DEFAULT NULL,
    `reason` VARCHAR(255) DEFAULT NULL,
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_rec_items_set` (`set_id`, `sort_order`),
    INDEX `idx_rec_items_content` (`content_type`, `content_id`),
    FOREIGN KEY (`set_id`) REFERENCES `recommendation_sets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Global trending content (not per-subscriber, computed periodically)
CREATE TABLE IF NOT EXISTS `trending_content` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `content_type` VARCHAR(30) NOT NULL,
    `content_id` INT UNSIGNED NOT NULL,
    `region` VARCHAR(10) DEFAULT NULL,
    `trend_score` DECIMAL(8,3) NOT NULL DEFAULT 0.000,
    `view_count_24h` INT UNSIGNED NOT NULL DEFAULT 0,
    `view_count_7d` INT UNSIGNED NOT NULL DEFAULT 0,
    `computed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX `idx_trending_unique` (`content_type`, `content_id`, `region`),
    INDEX `idx_trending_score` (`region`, `trend_score` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
