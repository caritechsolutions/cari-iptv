-- Migration 019: Seed Default Packages
-- Creates starter subscription packages for the platform
SET NAMES utf8mb4;

-- ============================================
-- Default Content Groups
-- ============================================
INSERT INTO `content_groups` (`name`, `slug`, `description`, `icon`, `color`, `is_active`, `sort_order`)
SELECT * FROM (SELECT
    'Basic Channels' AS `name`,
    'basic-channels' AS `slug`,
    'Standard definition channels available with basic subscription' AS `description`,
    'lucide-tv' AS `icon`,
    '#3b82f6' AS `color`,
    1 AS `is_active`,
    1 AS `sort_order`
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `content_groups` WHERE `slug` = 'basic-channels');

INSERT INTO `content_groups` (`name`, `slug`, `description`, `icon`, `color`, `is_active`, `sort_order`)
SELECT * FROM (SELECT
    'Premium Channels' AS `name`,
    'premium-channels' AS `slug`,
    'HD and premium channels including sports and movies' AS `description`,
    'lucide-crown' AS `icon`,
    '#f59e0b' AS `color`,
    1 AS `is_active`,
    2 AS `sort_order`
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `content_groups` WHERE `slug` = 'premium-channels');

INSERT INTO `content_groups` (`name`, `slug`, `description`, `icon`, `color`, `is_active`, `sort_order`)
SELECT * FROM (SELECT
    'Movies Library' AS `name`,
    'movies-library' AS `slug`,
    'Full access to the movies on demand library' AS `description`,
    'lucide-film' AS `icon`,
    '#8b5cf6' AS `color`,
    1 AS `is_active`,
    3 AS `sort_order`
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `content_groups` WHERE `slug` = 'movies-library');

INSERT INTO `content_groups` (`name`, `slug`, `description`, `icon`, `color`, `is_active`, `sort_order`)
SELECT * FROM (SELECT
    'TV Shows Library' AS `name`,
    'tv-shows-library' AS `slug`,
    'Full access to TV series and shows on demand' AS `description`,
    'lucide-clapperboard' AS `icon`,
    '#ec4899' AS `color`,
    1 AS `is_active`,
    4 AS `sort_order`
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `content_groups` WHERE `slug` = 'tv-shows-library');

-- ============================================
-- Default Packages
-- ============================================

-- Free Package
INSERT INTO `packages` (`name`, `slug`, `description`, `price`, `currency`, `billing_period`, `trial_days`, `max_connections`, `is_free`, `is_active`, `is_featured`, `sort_order`, `features`)
SELECT * FROM (SELECT
    'Free' AS `name`,
    'free' AS `slug`,
    'Get started with basic channels and limited content' AS `description`,
    0.00 AS `price`,
    'USD' AS `currency`,
    'monthly' AS `billing_period`,
    0 AS `trial_days`,
    1 AS `max_connections`,
    1 AS `is_free`,
    1 AS `is_active`,
    0 AS `is_featured`,
    1 AS `sort_order`,
    '["Basic SD channels", "Limited VOD content", "1 device", "Ad-supported"]' AS `features`
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `packages` WHERE `slug` = 'free');

-- Basic Package
INSERT INTO `packages` (`name`, `slug`, `description`, `price`, `currency`, `billing_period`, `trial_days`, `max_connections`, `is_free`, `is_active`, `is_featured`, `sort_order`, `features`)
SELECT * FROM (SELECT
    'Basic' AS `name`,
    'basic' AS `slug`,
    'Enjoy more channels and on-demand content' AS `description`,
    9.99 AS `price`,
    'USD' AS `currency`,
    'monthly' AS `billing_period`,
    7 AS `trial_days`,
    2 AS `max_connections`,
    0 AS `is_free`,
    1 AS `is_active`,
    0 AS `is_featured`,
    2 AS `sort_order`,
    '["Basic + HD channels", "Movies library", "2 devices", "7-day catch-up", "Ad-free"]' AS `features`
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `packages` WHERE `slug` = 'basic');

-- Premium Package
INSERT INTO `packages` (`name`, `slug`, `description`, `price`, `currency`, `billing_period`, `trial_days`, `max_connections`, `is_free`, `is_active`, `is_featured`, `sort_order`, `features`)
SELECT * FROM (SELECT
    'Premium' AS `name`,
    'premium' AS `slug`,
    'The ultimate entertainment package with everything included' AS `description`,
    19.99 AS `price`,
    'USD' AS `currency`,
    'monthly' AS `billing_period`,
    7 AS `trial_days`,
    4 AS `max_connections`,
    0 AS `is_free`,
    1 AS `is_active`,
    1 AS `is_featured`,
    3 AS `sort_order`,
    '["All channels including premium", "Full movies & TV shows library", "4 devices", "14-day catch-up", "Ad-free", "HD & 4K quality"]' AS `features`
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `packages` WHERE `slug` = 'premium');

-- ============================================
-- Link packages to content groups
-- ============================================

-- Free package gets basic channels only
INSERT IGNORE INTO `package_content_groups` (`package_id`, `group_id`)
SELECT p.id, cg.id FROM `packages` p, `content_groups` cg
WHERE p.slug = 'free' AND cg.slug = 'basic-channels';

-- Basic package gets basic channels + movies
INSERT IGNORE INTO `package_content_groups` (`package_id`, `group_id`)
SELECT p.id, cg.id FROM `packages` p, `content_groups` cg
WHERE p.slug = 'basic' AND cg.slug IN ('basic-channels', 'movies-library');

-- Premium package gets everything
INSERT IGNORE INTO `package_content_groups` (`package_id`, `group_id`)
SELECT p.id, cg.id FROM `packages` p, `content_groups` cg
WHERE p.slug = 'premium' AND cg.slug IN ('basic-channels', 'premium-channels', 'movies-library', 'tv-shows-library');
