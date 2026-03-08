-- Migration 033: Advanced analytics — QoE tracking, session analytics, content impressions, engagement scores
SET NAMES utf8mb4;

-- =========================================================================
-- QoE (Quality of Experience) events — buffering, errors, startup latency
-- Stored separately from subscriber_events for high-volume performance data
-- =========================================================================
CREATE TABLE IF NOT EXISTS `subscriber_qoe_events` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscriber_id` INT UNSIGNED NOT NULL,
    `content_type` VARCHAR(30) DEFAULT NULL,
    `content_id` INT UNSIGNED DEFAULT NULL,
    `event_type` VARCHAR(50) NOT NULL COMMENT 'buffer_start, buffer_end, playback_error, startup, quality_switch, quality_report',
    `metadata` JSON DEFAULT NULL COMMENT '{bitrate, resolution, buffer_duration_ms, error_code, startup_ms, from_quality, to_quality}',
    `session_id` VARCHAR(64) DEFAULT NULL,
    `platform` VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_qoe_subscriber` (`subscriber_id`, `created_at` DESC),
    INDEX `idx_qoe_type` (`event_type`, `created_at` DESC),
    INDEX `idx_qoe_content` (`content_type`, `content_id`),
    INDEX `idx_qoe_created` (`created_at`),

    FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- Session analytics — tracks complete user sessions for frequency/recency
-- =========================================================================
CREATE TABLE IF NOT EXISTS `subscriber_sessions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscriber_id` INT UNSIGNED NOT NULL,
    `session_id` VARCHAR(64) NOT NULL,
    `platform` VARCHAR(20) DEFAULT NULL,
    `device_type` VARCHAR(30) DEFAULT NULL COMMENT 'desktop, mobile, tablet, tv, stb',
    `browser` VARCHAR(50) DEFAULT NULL,
    `os` VARCHAR(50) DEFAULT NULL,
    `screen_resolution` VARCHAR(20) DEFAULT NULL COMMENT '1920x1080',
    `connection_type` VARCHAR(20) DEFAULT NULL COMMENT '4g, 3g, wifi, ethernet',
    `country` VARCHAR(100) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ended_at` DATETIME DEFAULT NULL,
    `duration_seconds` INT UNSIGNED DEFAULT NULL,
    `page_views` INT UNSIGNED NOT NULL DEFAULT 0,
    `watch_starts` INT UNSIGNED NOT NULL DEFAULT 0,
    `search_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_bounce` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 if only 1 page view and <30s',
    `referrer` VARCHAR(500) DEFAULT NULL,

    UNIQUE INDEX `idx_sessions_session_id` (`session_id`),
    INDEX `idx_sessions_subscriber` (`subscriber_id`, `started_at` DESC),
    INDEX `idx_sessions_started` (`started_at`),
    INDEX `idx_sessions_platform` (`platform`, `started_at` DESC),

    FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- Content impressions — what users SEE vs what they click
-- =========================================================================
CREATE TABLE IF NOT EXISTS `content_impressions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscriber_id` INT UNSIGNED NOT NULL,
    `content_type` VARCHAR(30) NOT NULL,
    `content_id` INT UNSIGNED NOT NULL,
    `section_type` VARCHAR(50) DEFAULT NULL COMMENT 'hero_slideshow, content_row, etc.',
    `section_id` INT UNSIGNED DEFAULT NULL,
    `position` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Position within the section (0-indexed)',
    `was_clicked` TINYINT(1) NOT NULL DEFAULT 0,
    `dwell_ms` INT UNSIGNED DEFAULT NULL COMMENT 'Time visible on screen',
    `session_id` VARCHAR(64) DEFAULT NULL,
    `platform` VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_impressions_subscriber` (`subscriber_id`, `created_at` DESC),
    INDEX `idx_impressions_content` (`content_type`, `content_id`, `created_at` DESC),
    INDEX `idx_impressions_section` (`section_type`, `created_at` DESC),
    INDEX `idx_impressions_created` (`created_at`),

    FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- Engagement scores — computed daily per subscriber
-- =========================================================================
CREATE TABLE IF NOT EXISTS `subscriber_engagement_scores` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscriber_id` INT UNSIGNED NOT NULL,
    `score` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '0-100 composite engagement score',
    `frequency_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'How often they visit (0-100)',
    `depth_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'How much they watch per session (0-100)',
    `breadth_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'How varied their content is (0-100)',
    `recency_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'How recently they visited (0-100)',
    `churn_risk` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
    `days_since_last_activity` INT UNSIGNED NOT NULL DEFAULT 0,
    `sessions_7d` INT UNSIGNED NOT NULL DEFAULT 0,
    `sessions_30d` INT UNSIGNED NOT NULL DEFAULT 0,
    `watch_hours_7d` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `watch_hours_30d` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `unique_titles_30d` INT UNSIGNED NOT NULL DEFAULT 0,
    `features_used` JSON DEFAULT NULL COMMENT '["search","watchlist","rating","share","subtitle"]',
    `computed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX `idx_engagement_subscriber` (`subscriber_id`),
    INDEX `idx_engagement_score` (`score` DESC),
    INDEX `idx_engagement_churn` (`churn_risk`, `score`),
    INDEX `idx_engagement_computed` (`computed_at`),

    FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- Binge watching detection — tracks consecutive episode sessions
-- =========================================================================
CREATE TABLE IF NOT EXISTS `binge_sessions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscriber_id` INT UNSIGNED NOT NULL,
    `series_id` INT UNSIGNED NOT NULL,
    `season_number` INT UNSIGNED DEFAULT NULL,
    `episodes_watched` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_watch_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
    `started_at` DATETIME NOT NULL,
    `ended_at` DATETIME DEFAULT NULL,
    `avg_gap_seconds` INT UNSIGNED DEFAULT NULL COMMENT 'Average gap between episodes',
    `auto_play_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `manual_next_count` INT UNSIGNED NOT NULL DEFAULT 0,

    INDEX `idx_binge_subscriber` (`subscriber_id`, `started_at` DESC),
    INDEX `idx_binge_series` (`series_id`, `started_at` DESC),

    FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- Retention cohorts — precomputed monthly cohort data
-- =========================================================================
CREATE TABLE IF NOT EXISTS `retention_cohorts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cohort_month` CHAR(7) NOT NULL COMMENT 'YYYY-MM signup month',
    `months_after` TINYINT UNSIGNED NOT NULL COMMENT '0=signup month, 1=next month, etc.',
    `cohort_size` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total subscribers in this cohort',
    `active_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Subscribers still active N months later',
    `retention_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentage retained',
    `computed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX `idx_cohort_unique` (`cohort_month`, `months_after`),
    INDEX `idx_cohort_month` (`cohort_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- Share events — social sharing tracking
-- =========================================================================
CREATE TABLE IF NOT EXISTS `content_shares` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscriber_id` INT UNSIGNED NOT NULL,
    `content_type` VARCHAR(30) NOT NULL,
    `content_id` INT UNSIGNED NOT NULL,
    `share_method` VARCHAR(50) DEFAULT NULL COMMENT 'copy_link, whatsapp, facebook, twitter, email, native_share',
    `platform` VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_shares_subscriber` (`subscriber_id`, `created_at` DESC),
    INDEX `idx_shares_content` (`content_type`, `content_id`),
    INDEX `idx_shares_created` (`created_at`),

    FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- Add new event types to subscriber_events for extended tracking
-- (The event types are validated in PHP, not DB-constrained, so no ALTER needed)
-- =========================================================================

-- =========================================================================
-- Add referrer_source column to subscribers for attribution tracking
-- =========================================================================
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'subscribers'
    AND COLUMN_NAME = 'referrer_source');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `subscribers` ADD COLUMN `referrer_source` VARCHAR(255) DEFAULT NULL AFTER `country`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'subscribers'
    AND COLUMN_NAME = 'referrer_campaign');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `subscribers` ADD COLUMN `referrer_campaign` VARCHAR(255) DEFAULT NULL AFTER `referrer_source`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
