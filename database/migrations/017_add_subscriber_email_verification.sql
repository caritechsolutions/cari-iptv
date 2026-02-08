-- Migration 017: Add email verification token to subscribers table
SET NAMES utf8mb4;

ALTER TABLE subscribers
    ADD COLUMN email_verification_token VARCHAR(255) DEFAULT NULL AFTER email_verified,
    ADD COLUMN email_verified_at DATETIME DEFAULT NULL AFTER email_verification_token;

-- Index for token lookups during verification
CREATE INDEX idx_subscribers_email_verification_token ON subscribers(email_verification_token);

-- Backfill: Mark all existing subscribers as email-verified so they are not locked out
UPDATE subscribers SET email_verified = 1, email_verified_at = NOW()
WHERE email_verified_at IS NULL;
