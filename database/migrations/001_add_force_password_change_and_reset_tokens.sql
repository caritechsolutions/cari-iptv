-- Migration: Add force_password_change to admin_users and create password_resets table
-- Date: 2026-01-24

-- Add force_password_change column to admin_users
ALTER TABLE `admin_users`
ADD COLUMN IF NOT EXISTS `force_password_change` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password_changed_at`;

-- Create password reset tokens table
CREATE TABLE IF NOT EXISTS `admin_password_resets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admin_user_id` INT UNSIGNED NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_token` (`token`),
    FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users`(`id`) ON DELETE CASCADE,
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user-specific permissions table (overrides role-based permissions)
CREATE TABLE IF NOT EXISTS `admin_user_permissions` (
    `admin_user_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `granted` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`admin_user_id`, `permission_id`),
    FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `admin_permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert page-level permissions if they don't exist
INSERT IGNORE INTO `admin_permissions` (`name`, `slug`, `module`, `description`) VALUES
('View Dashboard', 'dashboard.view', 'dashboard', 'Access the dashboard page'),
('View Analytics', 'analytics.view', 'analytics', 'Access analytics and reports'),
('Manage Channels', 'channels.manage', 'channels', 'Create, edit, and delete channels'),
('View Channels', 'channels.view', 'channels', 'View channel list'),
('Manage VOD', 'vod.manage', 'vod', 'Create, edit, and delete VOD content'),
('View VOD', 'vod.view', 'vod', 'View VOD library'),
('Manage Series', 'series.manage', 'series', 'Create, edit, and delete series'),
('View Series', 'series.view', 'series', 'View series list'),
('Manage EPG', 'epg.manage', 'epg', 'Import and manage EPG data'),
('View EPG', 'epg.view', 'epg', 'View EPG schedule'),
('Manage Categories', 'categories.manage', 'categories', 'Create, edit, and delete categories'),
('View Categories', 'categories.view', 'categories', 'View category list'),
('Manage Subscribers', 'subscribers.manage', 'subscribers', 'Create, edit, and delete subscriber users'),
('View Subscribers', 'subscribers.view', 'subscribers', 'View subscriber list'),
('Manage Subscriptions', 'subscriptions.manage', 'subscriptions', 'Manage user subscriptions'),
('View Subscriptions', 'subscriptions.view', 'subscriptions', 'View subscription list'),
('Manage Packages', 'packages.manage', 'packages', 'Create, edit, and delete packages'),
('View Packages', 'packages.view', 'packages', 'View package list'),
('Manage Admin Users', 'admins.manage', 'admins', 'Create, edit, and delete admin users'),
('View Admin Users', 'admins.view', 'admins', 'View admin user list'),
('View Activity Log', 'activity.view', 'activity', 'View system activity log'),
('Manage Settings', 'settings.manage', 'settings', 'Modify system settings'),
('View Settings', 'settings.view', 'settings', 'View system settings');
