-- Migration: Create settings table for system configuration
-- Date: 2026-01-24

-- Create settings table
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `group` VARCHAR(50) NOT NULL DEFAULT 'general',
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT,
    `type` ENUM('string', 'integer', 'boolean', 'json') NOT NULL DEFAULT 'string',
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_group_key` (`group`, `key`),
    INDEX `idx_group` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default SMTP settings
INSERT IGNORE INTO `settings` (`group`, `key`, value, type, description) VALUES
('smtp', 'enabled', '0', 'boolean', 'Enable SMTP email sending'),
('smtp', 'host', '', 'string', 'SMTP server hostname'),
('smtp', 'port', '587', 'integer', 'SMTP server port'),
('smtp', 'encryption', 'tls', 'string', 'Encryption type: tls, ssl, or none'),
('smtp', 'username', '', 'string', 'SMTP authentication username'),
('smtp', 'password', '', 'string', 'SMTP authentication password'),
('smtp', 'from_email', '', 'string', 'Default sender email address'),
('smtp', 'from_name', 'CARI-IPTV', 'string', 'Default sender name'),
('general', 'site_name', 'CARI-IPTV', 'string', 'Site name displayed in emails and UI'),
('general', 'site_url', '', 'string', 'Base URL of the site'),
('general', 'admin_email', '', 'string', 'Administrator email for notifications');
