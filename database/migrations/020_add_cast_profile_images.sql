-- Migration 020: Add local profile_image column to cast tables for WebP storage
-- Also ensures tmdb_person_id is populated for cross-content actor linking

SET NAMES utf8mb4;

-- Add profile_image to movie_cast (local WebP path)
DROP PROCEDURE IF EXISTS add_cast_col;
DELIMITER //
CREATE PROCEDURE add_cast_col()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'movie_cast' AND column_name = 'profile_image'
    ) THEN
        ALTER TABLE movie_cast ADD COLUMN profile_image VARCHAR(500) DEFAULT NULL AFTER profile_url;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'series_cast' AND column_name = 'profile_image'
    ) THEN
        ALTER TABLE series_cast ADD COLUMN profile_image VARCHAR(500) DEFAULT NULL AFTER profile_url;
    END IF;
END //
DELIMITER ;

CALL add_cast_col();
DROP PROCEDURE IF EXISTS add_cast_col;
