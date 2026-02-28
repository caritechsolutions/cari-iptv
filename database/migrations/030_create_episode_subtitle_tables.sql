-- 030_create_episode_subtitle_tables.sql
-- Subtitle support for TV series episodes
SET NAMES utf8mb4;

-- Episode subtitles table
CREATE TABLE IF NOT EXISTS `episode_subtitles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `episode_id` INT UNSIGNED NOT NULL,
    `language_code` VARCHAR(10) NOT NULL COMMENT 'ISO 639-1 code (en, es, fr)',
    `language_name` VARCHAR(100) NOT NULL COMMENT 'Full name (English, Spanish)',
    `source` VARCHAR(30) NOT NULL DEFAULT 'upload' COMMENT 'upload, opensubtitles, extracted',
    `external_id` VARCHAR(255) DEFAULT NULL COMMENT 'OpenSubtitles file ID',
    `file_path` VARCHAR(500) NOT NULL COMMENT 'Path relative to public/',
    `format` VARCHAR(10) NOT NULL DEFAULT 'vtt',
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `is_forced` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Forced/SDH subtitles',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_episode_subtitles_episode` (`episode_id`),
    UNIQUE KEY `idx_episode_subtitles_lang` (`episode_id`, `language_code`),
    FOREIGN KEY (`episode_id`) REFERENCES `series_episodes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
