-- Migration 036: Subscriber password reset tokens + account deletion marker
-- Supports the mobile app "Forgot password" and "Delete account" flows.
SET NAMES utf8mb4;

-- ============================================
-- Subscriber Password Reset Tokens
-- Time-limited, single-use tokens (raw token is emailed, only the
-- SHA-256 hash is stored — same pattern as admin_password_resets).
-- ============================================
CREATE TABLE IF NOT EXISTS `subscriber_password_resets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscriber_id` INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of the emailed token',
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME DEFAULT NULL,
    `requested_ip` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX `idx_token_hash` (`token_hash`),
    INDEX `idx_subscriber` (`subscriber_id`),
    INDEX `idx_expires` (`expires_at`),
    FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- subscribers.deleted_at — set when a subscriber deletes their account.
-- The row is anonymised and kept (see SubscriberAuthService::deleteAccount)
-- so that aggregate analytics and advertiser reporting keep referential integrity.
-- ============================================
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'subscribers'
    AND COLUMN_NAME = 'deleted_at');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `subscribers` ADD COLUMN `deleted_at` DATETIME DEFAULT NULL AFTER `login_count`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'subscribers'
    AND INDEX_NAME = 'idx_subscribers_deleted_at');
SET @s = IF(@idx_exists = 0,
    'ALTER TABLE `subscribers` ADD INDEX `idx_subscribers_deleted_at` (`deleted_at`)',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
