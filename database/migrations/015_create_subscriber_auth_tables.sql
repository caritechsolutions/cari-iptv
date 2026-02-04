-- Migration 015: Subscriber Authentication (JWT tokens & sessions)
SET NAMES utf8mb4;

-- ============================================
-- Subscriber Refresh Tokens
-- Long-lived tokens for obtaining new access tokens
-- ============================================
CREATE TABLE IF NOT EXISTS `subscriber_tokens` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscriber_id` INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of refresh token',
    `device_name` VARCHAR(255) DEFAULT NULL COMMENT 'Browser/device identifier',
    `device_type` ENUM('web','mobile','tv','stb') DEFAULT 'web',
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `expires_at` DATETIME NOT NULL,
    `last_used_at` DATETIME DEFAULT NULL,
    `revoked_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_subscriber` (`subscriber_id`),
    INDEX `idx_token_hash` (`token_hash`),
    INDEX `idx_expires` (`expires_at`),
    FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Subscriber Watch History
-- Tracks what users have watched for continue watching
-- ============================================
CREATE TABLE IF NOT EXISTS `subscriber_watch_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscriber_id` INT UNSIGNED NOT NULL,
    `content_type` ENUM('movie','episode','channel') NOT NULL,
    `content_id` INT UNSIGNED NOT NULL,
    `progress_seconds` INT UNSIGNED DEFAULT 0 COMMENT 'Playback position in seconds',
    `duration_seconds` INT UNSIGNED DEFAULT 0 COMMENT 'Total duration in seconds',
    `completed` TINYINT(1) NOT NULL DEFAULT 0,
    `last_watched_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX `idx_subscriber_content` (`subscriber_id`, `content_type`, `content_id`),
    INDEX `idx_last_watched` (`subscriber_id`, `last_watched_at` DESC),
    FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Subscriber Watchlist / Favourites
-- Content saved by user for later viewing
-- ============================================
CREATE TABLE IF NOT EXISTS `subscriber_watchlist` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscriber_id` INT UNSIGNED NOT NULL,
    `content_type` ENUM('movie','series','channel') NOT NULL,
    `content_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX `idx_subscriber_content` (`subscriber_id`, `content_type`, `content_id`),
    FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
