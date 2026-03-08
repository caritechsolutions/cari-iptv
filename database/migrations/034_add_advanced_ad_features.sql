-- Migration 034: Advanced Advertising Features
-- Adds: waterfall chains, A/B testing, ad pods, conversion tracking,
--        dayparting, floor prices, companion sync, revenue forecasting
SET NAMES utf8mb4;

-- ============================================
-- Ad Waterfall Chains
-- Fallback sequence when direct-sold ads don't fill
-- ============================================
CREATE TABLE IF NOT EXISTS `ad_waterfall_chains` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `zone_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_zone` (`zone_id`),
    FOREIGN KEY (`zone_id`) REFERENCES `ad_zones`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ad_waterfall_steps` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `chain_id` INT UNSIGNED NOT NULL,
    `step_order` INT UNSIGNED NOT NULL DEFAULT 1,
    `source_type` ENUM('direct','campaign','vast_tag','house') NOT NULL DEFAULT 'direct',
    `campaign_id` INT UNSIGNED DEFAULT NULL COMMENT 'For direct/campaign source',
    `vast_tag_url` VARCHAR(1000) DEFAULT NULL COMMENT 'For 3rd-party VAST source',
    `timeout_ms` INT UNSIGNED NOT NULL DEFAULT 3000 COMMENT 'Max wait before fallback',
    `floor_cpm` DECIMAL(10,4) DEFAULT NULL COMMENT 'Min CPM to accept',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_chain_order` (`chain_id`, `step_order`),
    FOREIGN KEY (`chain_id`) REFERENCES `ad_waterfall_chains`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- A/B Testing for Creatives
-- Split traffic between variants within a campaign
-- ============================================
CREATE TABLE IF NOT EXISTS `ad_ab_tests` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `campaign_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `status` ENUM('draft','running','completed','paused') NOT NULL DEFAULT 'draft',
    `winner_creative_id` INT UNSIGNED DEFAULT NULL COMMENT 'Auto-promoted winner',
    `confidence_threshold` DECIMAL(5,2) NOT NULL DEFAULT 95.00 COMMENT 'Statistical confidence %',
    `min_impressions` INT UNSIGNED NOT NULL DEFAULT 1000 COMMENT 'Min impressions before evaluation',
    `metric` ENUM('ctr','completion_rate','conversion_rate') NOT NULL DEFAULT 'ctr',
    `started_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_campaign` (`campaign_id`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`campaign_id`) REFERENCES `ad_campaigns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ad_ab_test_variants` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `test_id` INT UNSIGNED NOT NULL,
    `creative_id` INT UNSIGNED NOT NULL,
    `traffic_weight` INT UNSIGNED NOT NULL DEFAULT 50 COMMENT 'Percentage of traffic',
    `impressions` INT UNSIGNED NOT NULL DEFAULT 0,
    `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
    `completions` INT UNSIGNED NOT NULL DEFAULT 0,
    `conversions` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_control` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Control variant flag',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_test` (`test_id`),
    INDEX `idx_creative` (`creative_id`),
    FOREIGN KEY (`test_id`) REFERENCES `ad_ab_tests`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`creative_id`) REFERENCES `ad_creatives`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Ad Pods / Ad Breaks
-- Sequence of ads in one break (like TV commercial breaks)
-- ============================================
CREATE TABLE IF NOT EXISTS `ad_pods` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `zone_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `max_ads` INT UNSIGNED NOT NULL DEFAULT 3 COMMENT 'Max ads in pod',
    `max_duration_seconds` INT UNSIGNED NOT NULL DEFAULT 90 COMMENT 'Total pod duration limit',
    `min_content_duration` INT UNSIGNED DEFAULT NULL COMMENT 'Min content length to trigger pod',
    `separation_seconds` INT UNSIGNED NOT NULL DEFAULT 300 COMMENT 'Min gap between pods',
    `allow_competitor_ads` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Allow same-industry ads together',
    `pod_type` ENUM('pre_roll','mid_roll','post_roll') NOT NULL DEFAULT 'pre_roll',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_zone` (`zone_id`),
    INDEX `idx_type` (`pod_type`),
    FOREIGN KEY (`zone_id`) REFERENCES `ad_zones`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Conversion Tracking
-- Track post-click actions (signup, purchase, page visit)
-- ============================================
CREATE TABLE IF NOT EXISTS `ad_conversion_goals` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `campaign_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `goal_type` ENUM('page_visit','signup','purchase','custom') NOT NULL DEFAULT 'page_visit',
    `tracking_url` VARCHAR(500) DEFAULT NULL COMMENT 'URL pattern to match',
    `pixel_code` VARCHAR(100) DEFAULT NULL COMMENT 'Tracking pixel ID',
    `value` DECIMAL(10,2) DEFAULT NULL COMMENT 'Conversion value in currency',
    `attribution_window_hours` INT UNSIGNED NOT NULL DEFAULT 720 COMMENT 'Default 30 days',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_campaign` (`campaign_id`),
    INDEX `idx_pixel` (`pixel_code`),
    FOREIGN KEY (`campaign_id`) REFERENCES `ad_campaigns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ad_conversions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `goal_id` INT UNSIGNED NOT NULL,
    `campaign_id` INT UNSIGNED NOT NULL,
    `creative_id` INT UNSIGNED DEFAULT NULL,
    `impression_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Last-touch impression',
    `click_event_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Last-touch click',
    `user_id` INT UNSIGNED DEFAULT NULL,
    `session_id` VARCHAR(100) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `conversion_value` DECIMAL(10,2) DEFAULT NULL,
    `attribution_type` ENUM('last_click','last_view','first_click') NOT NULL DEFAULT 'last_click',
    `metadata` JSON DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_goal` (`goal_id`),
    INDEX `idx_campaign` (`campaign_id`, `created_at`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_date` (`created_at`),
    FOREIGN KEY (`goal_id`) REFERENCES `ad_conversion_goals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Advanced Dayparting
-- Day-of-week + hour grid for fine-grained scheduling
-- ============================================
CREATE TABLE IF NOT EXISTS `ad_daypart_schedules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `campaign_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL DEFAULT 'Default Schedule',
    `priority_multiplier` DECIMAL(3,2) NOT NULL DEFAULT 1.00 COMMENT 'Boost priority during this slot',
    `budget_multiplier` DECIMAL(3,2) NOT NULL DEFAULT 1.00 COMMENT 'Adjust budget during this slot',
    `schedule_grid` JSON NOT NULL COMMENT '{"mon":[0,0,0,0,0,0,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,0,0,0],"tue":[...],...}',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_campaign` (`campaign_id`),
    FOREIGN KEY (`campaign_id`) REFERENCES `ad_campaigns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Floor Prices per Zone (RTB Lite)
-- ============================================

-- Add floor_cpm to ad_zones
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ad_zones'
    AND COLUMN_NAME = 'floor_cpm');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `ad_zones` ADD COLUMN `floor_cpm` DECIMAL(10,4) DEFAULT NULL COMMENT ''Minimum CPM to serve ads'' AFTER `default_settings`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add fallback_vast_url to ad_zones
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ad_zones'
    AND COLUMN_NAME = 'fallback_vast_url');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `ad_zones` ADD COLUMN `fallback_vast_url` VARCHAR(1000) DEFAULT NULL COMMENT ''VAST URL when no direct ads fill'' AFTER `floor_cpm`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- Add industry/advertiser_category to campaigns for competitor separation
-- ============================================
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ad_campaigns'
    AND COLUMN_NAME = 'advertiser_category');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `ad_campaigns` ADD COLUMN `advertiser_category` VARCHAR(100) DEFAULT NULL COMMENT ''Industry category for competitor separation'' AFTER `advertiser`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- Add ab_test_id to ad_creatives for A/B test tracking
-- ============================================
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ad_creatives'
    AND COLUMN_NAME = 'ab_test_id');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `ad_creatives` ADD COLUMN `ab_test_id` INT UNSIGNED DEFAULT NULL COMMENT ''A/B test this creative belongs to'' AFTER `weight`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- Add companion_creative_id for companion ad sync
-- ============================================
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ad_creatives'
    AND COLUMN_NAME = 'companion_creative_id');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `ad_creatives` ADD COLUMN `companion_creative_id` INT UNSIGNED DEFAULT NULL COMMENT ''Linked banner to show alongside video ad'' AFTER `companion_banner_url`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- Ad Revenue Forecasts (AI-generated)
-- ============================================
CREATE TABLE IF NOT EXISTS `ad_revenue_forecasts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `forecast_date` DATE NOT NULL,
    `zone_type` VARCHAR(50) DEFAULT NULL,
    `predicted_impressions` INT UNSIGNED NOT NULL DEFAULT 0,
    `predicted_revenue` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `predicted_fill_rate` DECIMAL(5,2) DEFAULT NULL,
    `confidence_level` DECIMAL(5,2) DEFAULT NULL COMMENT 'AI confidence 0-100',
    `actual_impressions` INT UNSIGNED DEFAULT NULL COMMENT 'Filled in later',
    `actual_revenue` DECIMAL(10,2) DEFAULT NULL,
    `ai_analysis` TEXT DEFAULT NULL COMMENT 'AI narrative explanation',
    `model_version` VARCHAR(50) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_date_zone` (`forecast_date`, `zone_type`),
    INDEX `idx_date` (`forecast_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
