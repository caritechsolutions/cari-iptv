-- Migration 012: Advertising System
-- Supports text scroller, banner image, pre-roll video, mid-roll video ads
-- with flexible targeting by package, channel, category, platform, schedule
SET NAMES utf8mb4;

-- ============================================
-- Ad Campaigns
-- Container for ad creatives with scheduling and budget
-- ============================================
CREATE TABLE IF NOT EXISTS `ad_campaigns` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `advertiser` VARCHAR(255) DEFAULT NULL COMMENT 'Advertiser/client name',
    `description` TEXT,
    `status` ENUM('draft','active','paused','completed','archived') NOT NULL DEFAULT 'draft',
    `priority` INT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1=highest, 10=lowest',
    `start_date` DATETIME DEFAULT NULL,
    `end_date` DATETIME DEFAULT NULL,
    `daily_budget` DECIMAL(10,2) DEFAULT NULL COMMENT 'Daily spend limit',
    `total_budget` DECIMAL(10,2) DEFAULT NULL COMMENT 'Total campaign budget',
    `daily_impressions_cap` INT UNSIGNED DEFAULT NULL COMMENT 'Max impressions per day',
    `total_impressions_cap` INT UNSIGNED DEFAULT NULL COMMENT 'Max total impressions',
    `frequency_cap` INT UNSIGNED DEFAULT NULL COMMENT 'Max times per user per day',
    `total_impressions` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_clicks` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_spend` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_schedule` (`start_date`, `end_date`),
    INDEX `idx_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Ad Creatives
-- The actual ad content (text, images, video)
-- ============================================
CREATE TABLE IF NOT EXISTS `ad_creatives` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `campaign_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `type` ENUM('text_scroller','banner','pre_roll','mid_roll') NOT NULL,
    `status` ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'draft',

    -- Text Scroller fields
    `scroll_text` TEXT COMMENT 'Scrolling text content',
    `scroll_speed` ENUM('slow','normal','fast') DEFAULT 'normal',
    `text_color` VARCHAR(7) DEFAULT '#FFFFFF',
    `bg_color` VARCHAR(7) DEFAULT '#000000',
    `bg_opacity` DECIMAL(3,2) DEFAULT 0.80 COMMENT '0.0 to 1.0',

    -- Banner fields
    `image_url` VARCHAR(500) DEFAULT NULL,
    `image_width` INT UNSIGNED DEFAULT NULL,
    `image_height` INT UNSIGNED DEFAULT NULL,
    `banner_position` ENUM('top','bottom','overlay_bottom','overlay_top','sidebar') DEFAULT 'bottom',
    `click_url` VARCHAR(500) DEFAULT NULL COMMENT 'Click-through URL',
    `click_target` ENUM('_blank','_self','deeplink') DEFAULT '_blank',

    -- Video Ad fields (pre-roll / mid-roll)
    `video_url` VARCHAR(500) DEFAULT NULL COMMENT 'Direct video URL',
    `vast_tag_url` VARCHAR(1000) DEFAULT NULL COMMENT 'VAST XML endpoint for third-party ads',
    `video_duration` INT UNSIGNED DEFAULT NULL COMMENT 'Duration in seconds',
    `skip_after` INT UNSIGNED DEFAULT NULL COMMENT 'Allow skip after N seconds (NULL=no skip)',
    `companion_banner_url` VARCHAR(500) DEFAULT NULL COMMENT 'Companion banner during video ad',

    -- Mid-roll specific
    `midroll_offset_type` ENUM('seconds','percent','cue') DEFAULT 'percent',
    `midroll_offset_value` VARCHAR(50) DEFAULT NULL COMMENT 'e.g. "300" (secs), "50" (%), "cue"',

    -- Common fields
    `alt_text` VARCHAR(255) DEFAULT NULL,
    `weight` INT UNSIGNED NOT NULL DEFAULT 100 COMMENT 'Weight for rotation within campaign',
    `impressions` INT UNSIGNED NOT NULL DEFAULT 0,
    `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_campaign` (`campaign_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`campaign_id`) REFERENCES `ad_campaigns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Ad Zones
-- Pre-defined placement zones in the player/app
-- ============================================
CREATE TABLE IF NOT EXISTS `ad_zones` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `zone_type` ENUM('text_scroller','banner','pre_roll','mid_roll') NOT NULL,
    `description` TEXT,
    `default_settings` JSON DEFAULT NULL COMMENT 'Default zone config',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_slug` (`slug`),
    INDEX `idx_type` (`zone_type`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default zones
INSERT IGNORE INTO `ad_zones` (`name`, `slug`, `zone_type`, `description`, `default_settings`) VALUES
    ('Live TV Text Scroller', 'live-text-scroller', 'text_scroller', 'Scrolling text ticker on live TV channels', '{"position": "bottom", "height": 40}'),
    ('VOD Text Scroller', 'vod-text-scroller', 'text_scroller', 'Scrolling text ticker on VOD content', '{"position": "bottom", "height": 40}'),
    ('Live TV Banner', 'live-banner', 'banner', 'Banner overlay on live TV channels', '{"position": "bottom", "size": "728x90"}'),
    ('VOD Banner', 'vod-banner', 'banner', 'Banner overlay on VOD content', '{"position": "bottom", "size": "728x90"}'),
    ('App Banner Top', 'app-banner-top', 'banner', 'Banner at top of app pages', '{"position": "top", "size": "728x90"}'),
    ('App Banner Bottom', 'app-banner-bottom', 'banner', 'Banner at bottom of app pages', '{"position": "bottom", "size": "728x90"}'),
    ('Pre-Roll VOD', 'preroll-vod', 'pre_roll', 'Pre-roll video before VOD playback', '{"max_duration": 30}'),
    ('Pre-Roll Live', 'preroll-live', 'pre_roll', 'Pre-roll video before live channel join', '{"max_duration": 15}'),
    ('Mid-Roll VOD', 'midroll-vod', 'mid_roll', 'Mid-roll video during VOD playback', '{"max_duration": 30, "min_content_duration": 600}'),
    ('Mid-Roll Live', 'midroll-live', 'mid_roll', 'Mid-roll video during live TV (ad breaks)', '{"max_duration": 30}');

-- ============================================
-- Ad Placements
-- Links campaigns/creatives to zones with targeting
-- ============================================
CREATE TABLE IF NOT EXISTS `ad_placements` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `campaign_id` INT UNSIGNED NOT NULL,
    `creative_id` INT UNSIGNED NOT NULL,
    `zone_id` INT UNSIGNED NOT NULL,
    `status` ENUM('active','paused') NOT NULL DEFAULT 'active',
    `priority` INT UNSIGNED NOT NULL DEFAULT 5,
    `start_date` DATETIME DEFAULT NULL COMMENT 'Override campaign schedule',
    `end_date` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_campaign` (`campaign_id`),
    INDEX `idx_creative` (`creative_id`),
    INDEX `idx_zone` (`zone_id`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`campaign_id`) REFERENCES `ad_campaigns`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`creative_id`) REFERENCES `ad_creatives`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`zone_id`) REFERENCES `ad_zones`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Ad Targeting Rules
-- Flexible targeting per placement
-- ============================================
CREATE TABLE IF NOT EXISTS `ad_targeting_rules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `placement_id` INT UNSIGNED NOT NULL,
    `rule_type` ENUM('package','channel','category','content_type','platform','geo','schedule') NOT NULL,
    `rule_operator` ENUM('include','exclude') NOT NULL DEFAULT 'include',
    `rule_value` JSON NOT NULL COMMENT 'Array of IDs or values',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_placement` (`placement_id`),
    INDEX `idx_type` (`rule_type`),
    FOREIGN KEY (`placement_id`) REFERENCES `ad_placements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Ad Impressions
-- Track every ad view
-- ============================================
CREATE TABLE IF NOT EXISTS `ad_impressions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `campaign_id` INT UNSIGNED NOT NULL,
    `creative_id` INT UNSIGNED NOT NULL,
    `placement_id` INT UNSIGNED DEFAULT NULL,
    `zone_id` INT UNSIGNED DEFAULT NULL,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `session_id` VARCHAR(100) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `platform` VARCHAR(50) DEFAULT NULL,
    `channel_id` INT UNSIGNED DEFAULT NULL,
    `content_type` VARCHAR(50) DEFAULT NULL COMMENT 'live, vod, series',
    `content_id` INT UNSIGNED DEFAULT NULL,
    `revenue` DECIMAL(10,4) DEFAULT 0.0000 COMMENT 'CPM revenue',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_campaign_date` (`campaign_id`, `created_at`),
    INDEX `idx_creative` (`creative_id`),
    INDEX `idx_zone` (`zone_id`),
    INDEX `idx_user_date` (`user_id`, `created_at`),
    INDEX `idx_date` (`created_at`),
    INDEX `idx_channel` (`channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Ad Events
-- Track clicks, completions, skips, errors
-- ============================================
CREATE TABLE IF NOT EXISTS `ad_events` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `impression_id` BIGINT UNSIGNED DEFAULT NULL,
    `campaign_id` INT UNSIGNED NOT NULL,
    `creative_id` INT UNSIGNED NOT NULL,
    `event_type` ENUM('click','complete','skip','error','quartile_25','quartile_50','quartile_75','mute','unmute','pause','resume','fullscreen') NOT NULL,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `session_id` VARCHAR(100) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `metadata` JSON DEFAULT NULL COMMENT 'Extra event data',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_impression` (`impression_id`),
    INDEX `idx_campaign_event` (`campaign_id`, `event_type`),
    INDEX `idx_creative_event` (`creative_id`, `event_type`),
    INDEX `idx_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
