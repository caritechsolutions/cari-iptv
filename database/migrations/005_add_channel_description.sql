-- Migration: Add description column to channels table
-- Version: 005
-- Date: 2026-01-26

-- Add description column to channels
ALTER TABLE `channels`
    ADD COLUMN `description` TEXT DEFAULT NULL AFTER `name`;
