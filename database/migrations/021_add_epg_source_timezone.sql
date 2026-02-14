-- Migration 021: Add timezone column to epg_sources
-- EPG sources can specify their timezone so times are converted to UTC on import
SET NAMES utf8mb4;

ALTER TABLE epg_sources ADD COLUMN IF NOT EXISTS timezone VARCHAR(50) DEFAULT 'UTC' AFTER refresh_interval;
