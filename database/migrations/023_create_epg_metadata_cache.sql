-- 023_create_epg_metadata_cache.sql
-- Caches TMDB metadata for EPG programme titles
-- Avoids repeated API calls and stores images locally as WebP
SET NAMES utf8mb4;

-- ---- Programme metadata cache (keyed by normalised title) ----
CREATE TABLE IF NOT EXISTS `epg_programme_metadata` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title_normalised` VARCHAR(255) NOT NULL COMMENT 'Lowercase trimmed programme title for dedup',
    `title_display` VARCHAR(255) NOT NULL COMMENT 'Original display title',
    `tmdb_id` INT UNSIGNED DEFAULT NULL COMMENT 'TMDB movie or TV show ID',
    `media_type` ENUM('movie','tv') DEFAULT NULL COMMENT 'movie or tv',
    `overview` TEXT DEFAULT NULL,
    `year` VARCHAR(4) DEFAULT NULL,
    `vote_average` DECIMAL(3,1) DEFAULT NULL,
    `genres` VARCHAR(500) DEFAULT NULL COMMENT 'Comma-separated genre names',
    `runtime` INT UNSIGNED DEFAULT NULL COMMENT 'Runtime in minutes',
    `number_of_seasons` INT UNSIGNED DEFAULT NULL,
    `directors` VARCHAR(500) DEFAULT NULL COMMENT 'Comma-separated director names',
    `poster_url` VARCHAR(500) DEFAULT NULL COMMENT 'Original TMDB poster URL',
    `poster_local` VARCHAR(500) DEFAULT NULL COMMENT 'Local WebP poster path',
    `backdrop_url` VARCHAR(500) DEFAULT NULL COMMENT 'Original TMDB backdrop URL',
    `backdrop_local` VARCHAR(500) DEFAULT NULL COMMENT 'Local WebP backdrop path',
    `status` ENUM('pending','complete','not_found','error') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_title_normalised` (`title_normalised`),
    KEY `idx_tmdb_id` (`tmdb_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Cast members for cached programme metadata ----
CREATE TABLE IF NOT EXISTS `epg_programme_cast` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `metadata_id` INT UNSIGNED NOT NULL COMMENT 'FK to epg_programme_metadata',
    `name` VARCHAR(255) NOT NULL,
    `character_name` VARCHAR(255) DEFAULT NULL,
    `tmdb_person_id` INT UNSIGNED DEFAULT NULL,
    `profile_url` VARCHAR(500) DEFAULT NULL COMMENT 'Original TMDB profile URL',
    `profile_image` VARCHAR(500) DEFAULT NULL COMMENT 'Local WebP profile image path',
    `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_metadata_id` (`metadata_id`),
    CONSTRAINT `fk_epg_cast_metadata` FOREIGN KEY (`metadata_id`)
        REFERENCES `epg_programme_metadata` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
