-- Add font_size column to ad_creatives for text scroller ads
-- Allows customizing the font size of scrolling text overlays

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ad_creatives'
    AND COLUMN_NAME = 'font_size');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `ad_creatives` ADD COLUMN `font_size` VARCHAR(10) DEFAULT ''medium'' AFTER `bg_opacity`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
