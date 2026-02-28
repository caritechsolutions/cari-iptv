-- 029_create_subtitle_tables.sql
-- Subtitle support for movies (and future: series episodes)
SET NAMES utf8mb4;

-- Movie subtitles table
CREATE TABLE IF NOT EXISTS `movie_subtitles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `movie_id` INT UNSIGNED NOT NULL,
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
    INDEX `idx_movie_subtitles_movie` (`movie_id`),
    UNIQUE KEY `idx_movie_subtitles_lang` (`movie_id`, `language_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
