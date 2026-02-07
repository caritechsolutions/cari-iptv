-- Migration 018: Subscriber Subscriptions & Adult Content System
-- Links subscribers to packages, adds adult content flags and controls
SET NAMES utf8mb4;

-- ============================================
-- Subscriber Subscriptions (links subscribers to packages)
-- ============================================
CREATE TABLE IF NOT EXISTS `subscriber_subscriptions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscriber_id` INT UNSIGNED NOT NULL,
    `package_id` INT UNSIGNED NOT NULL,
    `status` ENUM('active', 'trial', 'expired', 'cancelled', 'suspended') NOT NULL DEFAULT 'active',
    `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL COMMENT 'NULL = never expires (lifetime/free)',
    `trial_ends_at` DATETIME DEFAULT NULL,
    `cancelled_at` DATETIME DEFAULT NULL,
    `auto_renew` TINYINT(1) NOT NULL DEFAULT 1,
    `payment_method` VARCHAR(50) DEFAULT NULL COMMENT 'stripe, paypal, manual, etc.',
    `payment_reference` VARCHAR(255) DEFAULT NULL COMMENT 'External payment ID',
    `notes` TEXT DEFAULT NULL COMMENT 'Admin notes',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_subscriber` (`subscriber_id`),
    INDEX `idx_package` (`package_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_expires` (`expires_at`),
    INDEX `idx_subscriber_status` (`subscriber_id`, `status`),
    FOREIGN KEY (`subscriber_id`) REFERENCES `subscribers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Add adult content flag to content tables
-- ============================================
ALTER TABLE `movies` ADD COLUMN IF NOT EXISTS `is_adult` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_featured`;
ALTER TABLE `series` ADD COLUMN IF NOT EXISTS `is_adult` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_featured`;
ALTER TABLE `channels` ADD COLUMN IF NOT EXISTS `is_adult` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`;

-- Add index for filtering
ALTER TABLE `movies` ADD INDEX IF NOT EXISTS `idx_adult` (`is_adult`);
ALTER TABLE `series` ADD INDEX IF NOT EXISTS `idx_adult` (`is_adult`);
ALTER TABLE `channels` ADD INDEX IF NOT EXISTS `idx_adult` (`is_adult`);

-- ============================================
-- Add adult flag to packages (adult-only packages)
-- ============================================
ALTER TABLE `packages` ADD COLUMN IF NOT EXISTS `is_adult` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_featured`;

-- ============================================
-- Add adult content controls to subscribers
-- ============================================
-- Note: parental_pin already exists, we add adult_enabled flag
ALTER TABLE `subscribers` ADD COLUMN IF NOT EXISTS `adult_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `parental_pin`;

-- ============================================
-- Add subscription page type to app_pages
-- ============================================
ALTER TABLE `app_pages` MODIFY COLUMN `page_type` ENUM(
    'home', 'movies', 'live_tv', 'series', 'categories',
    'search', 'watchlist', 'settings', 'player', 'details',
    'custom', 'subscription', 'profile'
) NOT NULL;

-- ============================================
-- Payment Gateway Settings (stored in settings table)
-- Settings groups: 'payment_stripe', 'payment_paypal', etc.
-- ============================================
-- No schema changes needed - uses existing settings table with group prefix

-- ============================================
-- Seed subscription page for each platform
-- ============================================
INSERT INTO `app_pages` (`name`, `slug`, `page_type`, `platform`, `icon`, `is_system`, `sort_order`) VALUES
('Subscribe', 'subscribe', 'subscription', 'web', 'lucide-credit-card', 1, 8),
('Subscribe', 'subscribe', 'subscription', 'mobile', 'lucide-credit-card', 1, 8),
('Subscribe', 'subscribe', 'subscription', 'tv', 'lucide-credit-card', 1, 8),
('Subscribe', 'subscribe', 'subscription', 'stb', 'lucide-credit-card', 1, 8),
('Profile', 'profile', 'profile', 'web', 'lucide-user', 1, 9),
('Profile', 'profile', 'profile', 'mobile', 'lucide-user', 1, 9),
('Profile', 'profile', 'profile', 'tv', 'lucide-user', 1, 9),
('Profile', 'profile', 'profile', 'stb', 'lucide-user', 1, 9)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
