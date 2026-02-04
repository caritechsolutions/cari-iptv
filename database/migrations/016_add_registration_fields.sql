-- Migration 016: Add registration, email verification, and remember me support
SET NAMES utf8mb4;

-- ============================================
-- Add registration fields to admin_users
-- ============================================
ALTER TABLE `admin_users`
    ADD COLUMN IF NOT EXISTS `date_of_birth` DATE DEFAULT NULL AFTER `last_name`,
    ADD COLUMN IF NOT EXISTS `country` VARCHAR(100) DEFAULT NULL AFTER `date_of_birth`,
    ADD COLUMN IF NOT EXISTS `phone` VARCHAR(30) DEFAULT NULL AFTER `country`,
    ADD COLUMN IF NOT EXISTS `email_verification_token` VARCHAR(64) DEFAULT NULL AFTER `two_factor_enabled`,
    ADD COLUMN IF NOT EXISTS `email_verified_at` DATETIME DEFAULT NULL AFTER `email_verification_token`;

-- ============================================
-- Remember Me Tokens
-- Persistent login tokens for "keep me logged in"
-- ============================================
CREATE TABLE IF NOT EXISTS `admin_remember_tokens` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admin_user_id` INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of remember token',
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_admin_user` (`admin_user_id`),
    INDEX `idx_token_hash` (`token_hash`),
    INDEX `idx_expires` (`expires_at`),
    FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Backfill: Mark all existing admin users as email-verified
-- so they can continue logging in without disruption
-- ============================================
UPDATE `admin_users`
SET `email_verified_at` = `created_at`
WHERE `email_verified_at` IS NULL;
