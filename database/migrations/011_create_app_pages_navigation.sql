-- Migration 011: App Pages & Navigation
-- Control page types and navigation flow through the application
SET NAMES utf8mb4;

-- ============================================
-- App Pages (what pages exist in the app)
-- ============================================
CREATE TABLE IF NOT EXISTS `app_pages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `page_type` ENUM('home', 'movies', 'live_tv', 'series', 'categories', 'search', 'watchlist', 'settings', 'player', 'details', 'custom') NOT NULL,
    `platform` ENUM('web', 'mobile', 'tv', 'stb') NOT NULL,
    `layout_id` INT UNSIGNED DEFAULT NULL COMMENT 'Links to app_layouts for page content',
    `icon` VARCHAR(50) DEFAULT NULL COMMENT 'Lucide icon name',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'System pages cannot be deleted',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `settings` JSON DEFAULT NULL COMMENT 'Page-specific config',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_platform_type` (`platform`, `page_type`),
    INDEX `idx_platform_active` (`platform`, `is_active`, `sort_order`),
    INDEX `idx_slug_platform` (`slug`, `platform`),
    FOREIGN KEY (`layout_id`) REFERENCES `app_layouts`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- App Navigation Menus
-- ============================================
CREATE TABLE IF NOT EXISTS `app_navigation` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `platform` ENUM('web', 'mobile', 'tv', 'stb') NOT NULL,
    `position` ENUM('main', 'footer', 'sidebar', 'top') NOT NULL DEFAULT 'main',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `settings` JSON DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_platform_position` (`platform`, `position`),
    UNIQUE KEY `uk_platform_position` (`platform`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- App Navigation Items
-- ============================================
CREATE TABLE IF NOT EXISTS `app_navigation_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `navigation_id` INT UNSIGNED NOT NULL,
    `page_id` INT UNSIGNED DEFAULT NULL,
    `label` VARCHAR(100) NOT NULL,
    `icon` VARCHAR(50) DEFAULT NULL,
    `url` VARCHAR(500) DEFAULT NULL COMMENT 'For external/custom links',
    `target` ENUM('page', 'url', 'deeplink') NOT NULL DEFAULT 'page',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `settings` JSON DEFAULT NULL COMMENT 'Badge, color, visibility rules',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_nav_order` (`navigation_id`, `sort_order`),
    FOREIGN KEY (`navigation_id`) REFERENCES `app_navigation`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`page_id`) REFERENCES `app_pages`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Seed default pages for each platform
-- ============================================
INSERT INTO `app_pages` (`name`, `slug`, `page_type`, `platform`, `icon`, `is_system`, `sort_order`) VALUES
-- Web platform
('Home', 'home', 'home', 'web', 'lucide-home', 1, 0),
('Movies', 'movies', 'movies', 'web', 'lucide-film', 1, 1),
('TV Shows', 'series', 'series', 'web', 'lucide-clapperboard', 1, 2),
('Live TV', 'live', 'live_tv', 'web', 'lucide-radio', 1, 3),
('Categories', 'categories', 'categories', 'web', 'lucide-grid-3x3', 1, 4),
('Search', 'search', 'search', 'web', 'lucide-search', 1, 5),
('My List', 'watchlist', 'watchlist', 'web', 'lucide-bookmark', 1, 6),
('Settings', 'settings', 'settings', 'web', 'lucide-settings', 1, 7),
-- Mobile platform
('Home', 'home', 'home', 'mobile', 'lucide-home', 1, 0),
('Movies', 'movies', 'movies', 'mobile', 'lucide-film', 1, 1),
('TV Shows', 'series', 'series', 'mobile', 'lucide-clapperboard', 1, 2),
('Live TV', 'live', 'live_tv', 'mobile', 'lucide-radio', 1, 3),
('Categories', 'categories', 'categories', 'mobile', 'lucide-grid-3x3', 1, 4),
('Search', 'search', 'search', 'mobile', 'lucide-search', 1, 5),
('My List', 'watchlist', 'watchlist', 'mobile', 'lucide-bookmark', 1, 6),
('Settings', 'settings', 'settings', 'mobile', 'lucide-settings', 1, 7),
-- TV platform
('Home', 'home', 'home', 'tv', 'lucide-home', 1, 0),
('Movies', 'movies', 'movies', 'tv', 'lucide-film', 1, 1),
('TV Shows', 'series', 'series', 'tv', 'lucide-clapperboard', 1, 2),
('Live TV', 'live', 'live_tv', 'tv', 'lucide-radio', 1, 3),
('Categories', 'categories', 'categories', 'tv', 'lucide-grid-3x3', 1, 4),
('Search', 'search', 'search', 'tv', 'lucide-search', 1, 5),
('My List', 'watchlist', 'watchlist', 'tv', 'lucide-bookmark', 1, 6),
('Settings', 'settings', 'settings', 'tv', 'lucide-settings', 1, 7),
-- STB platform
('Home', 'home', 'home', 'stb', 'lucide-home', 1, 0),
('Movies', 'movies', 'movies', 'stb', 'lucide-film', 1, 1),
('TV Shows', 'series', 'series', 'stb', 'lucide-clapperboard', 1, 2),
('Live TV', 'live', 'live_tv', 'stb', 'lucide-radio', 1, 3),
('Categories', 'categories', 'categories', 'stb', 'lucide-grid-3x3', 1, 4),
('Search', 'search', 'search', 'stb', 'lucide-search', 1, 5),
('My List', 'watchlist', 'watchlist', 'stb', 'lucide-bookmark', 1, 6),
('Settings', 'settings', 'settings', 'stb', 'lucide-settings', 1, 7);

-- Seed default main navigation for each platform
INSERT INTO `app_navigation` (`name`, `platform`, `position`, `settings`) VALUES
('Main Navigation', 'web', 'main', '{"style":"sidebar","show_icons":true,"show_labels":true}'),
('Main Navigation', 'mobile', 'main', '{"style":"bottom_tab","show_icons":true,"show_labels":true,"max_items":5}'),
('Main Navigation', 'tv', 'main', '{"style":"top_bar","show_icons":true,"show_labels":true}'),
('Main Navigation', 'stb', 'main', '{"style":"sidebar","show_icons":true,"show_labels":true}');

-- Seed default navigation items (linking to the pages we just created)
-- Web main nav
INSERT INTO `app_navigation_items` (`navigation_id`, `page_id`, `label`, `icon`, `target`, `sort_order`)
SELECT n.id, p.id, p.name, p.icon, 'page', p.sort_order
FROM app_navigation n
JOIN app_pages p ON p.platform = n.platform
WHERE n.platform = 'web' AND n.position = 'main'
AND p.page_type IN ('home', 'movies', 'series', 'live_tv', 'search', 'watchlist');

-- Mobile main nav (limited to 5 for bottom tab)
INSERT INTO `app_navigation_items` (`navigation_id`, `page_id`, `label`, `icon`, `target`, `sort_order`)
SELECT n.id, p.id, p.name, p.icon, 'page', p.sort_order
FROM app_navigation n
JOIN app_pages p ON p.platform = n.platform
WHERE n.platform = 'mobile' AND n.position = 'main'
AND p.page_type IN ('home', 'movies', 'live_tv', 'search', 'watchlist');

-- TV main nav
INSERT INTO `app_navigation_items` (`navigation_id`, `page_id`, `label`, `icon`, `target`, `sort_order`)
SELECT n.id, p.id, p.name, p.icon, 'page', p.sort_order
FROM app_navigation n
JOIN app_pages p ON p.platform = n.platform
WHERE n.platform = 'tv' AND n.position = 'main'
AND p.page_type IN ('home', 'movies', 'series', 'live_tv', 'search');

-- STB main nav
INSERT INTO `app_navigation_items` (`navigation_id`, `page_id`, `label`, `icon`, `target`, `sort_order`)
SELECT n.id, p.id, p.name, p.icon, 'page', p.sort_order
FROM app_navigation n
JOIN app_pages p ON p.platform = n.platform
WHERE n.platform = 'stb' AND n.position = 'main'
AND p.page_type IN ('home', 'movies', 'series', 'live_tv', 'search');
