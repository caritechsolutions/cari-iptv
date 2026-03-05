-- 031_create_subscriber_ratings.sql
-- Subscriber content ratings (1-5 stars for movies and series)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `subscriber_ratings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subscriber_id` INT UNSIGNED NOT NULL,
    `content_type` ENUM('movie', 'series') NOT NULL,
    `content_id` INT UNSIGNED NOT NULL,
    `rating` TINYINT UNSIGNED NOT NULL COMMENT '1-5 stars',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_subscriber_content` (`subscriber_id`, `content_type`, `content_id`),
    INDEX `idx_content_rating` (`content_type`, `content_id`),
    INDEX `idx_subscriber` (`subscriber_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add community_rating and community_rating_count columns to movies table
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'movies'
    AND COLUMN_NAME = 'community_rating');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `movies` ADD COLUMN `community_rating` DECIMAL(3,1) DEFAULT NULL AFTER `vote_average`, ADD COLUMN `community_rating_count` INT UNSIGNED DEFAULT 0 AFTER `community_rating`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add community_rating and community_rating_count columns to series table
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'series'
    AND COLUMN_NAME = 'community_rating');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `series` ADD COLUMN `community_rating` DECIMAL(3,1) DEFAULT NULL AFTER `vote_average`, ADD COLUMN `community_rating_count` INT UNSIGNED DEFAULT 0 AFTER `community_rating`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
