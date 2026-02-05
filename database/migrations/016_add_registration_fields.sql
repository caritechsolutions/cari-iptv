-- Migration 016: Add registration, email verification, and remember me support
SET NAMES utf8mb4;

-- ============================================
-- Add registration fields to admin_users
-- (Using INFORMATION_SCHEMA checks for MySQL 8.x compatibility)
-- ============================================

-- Add date_of_birth column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users' AND COLUMN_NAME = 'date_of_birth');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `admin_users` ADD COLUMN `date_of_birth` DATE DEFAULT NULL AFTER `last_name`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add country column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users' AND COLUMN_NAME = 'country');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `admin_users` ADD COLUMN `country` VARCHAR(100) DEFAULT NULL AFTER `date_of_birth`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add phone column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users' AND COLUMN_NAME = 'phone');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `admin_users` ADD COLUMN `phone` VARCHAR(30) DEFAULT NULL AFTER `country`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add email_verification_token column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users' AND COLUMN_NAME = 'email_verification_token');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `admin_users` ADD COLUMN `email_verification_token` VARCHAR(64) DEFAULT NULL AFTER `two_factor_enabled`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add email_verified_at column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users' AND COLUMN_NAME = 'email_verified_at');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `admin_users` ADD COLUMN `email_verified_at` DATETIME DEFAULT NULL AFTER `email_verification_token`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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
