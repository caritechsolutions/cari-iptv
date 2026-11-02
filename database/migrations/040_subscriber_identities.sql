-- 040_subscriber_identities.sql
-- Google / Apple sign-in: which external identity belongs to which subscriber.
-- A subscriber may have several (Google and Apple) and still a password.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `subscriber_identities` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscriber_id` INT UNSIGNED NOT NULL,
    `provider` ENUM('google', 'apple') NOT NULL,
    `provider_user_id` VARCHAR(191) NOT NULL COMMENT 'sub claim of the provider token',
    `email` VARCHAR(255) DEFAULT NULL COMMENT 'email as the provider reported it (Apple may relay)',
    `refresh_token` TEXT DEFAULT NULL COMMENT 'Apple refresh token, needed to revoke on account deletion',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login_at` DATETIME DEFAULT NULL,
    UNIQUE INDEX `uq_provider_user` (`provider`, `provider_user_id`),
    INDEX `idx_identity_subscriber` (`subscriber_id`),
    FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Social-only accounts have no password; the column must allow NULL
SET @nullable = (SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscribers' AND COLUMN_NAME = 'password');
SET @s = IF(@nullable = 'NO',
    'ALTER TABLE `subscribers` MODIFY COLUMN `password` VARCHAR(255) DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
