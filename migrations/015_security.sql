-- ============================================================
-- Migration 015 — security
--
-- rsvp_attendees: IC / passport numbers are stored ENCRYPTED from now on
--   (AES-256-GCM, key in storage/keys/app.key — back it up with the
--   database). ic_no grows to hold the ciphertext; ic_hash (a keyed hash)
--   finds a record by its full number; ic_last4 allows searching by the
--   last four digits and masked display. 015_security_data.php encrypts
--   the numbers already saved.
--
-- admin_users.session_version: raised when a password or role changes or
--   an account is removed, which signs that person out everywhere else.
--
-- rate_limits: how often one address submitted a public form, so a
--   script cannot flood registrations or donations.
--
-- Run with:  php bin/migrate.php      Requires migrations 001–014.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE rsvp_attendees
    MODIFY ic_no VARCHAR(255) NOT NULL,
    ADD COLUMN ic_hash  CHAR(64)   NULL AFTER ic_no,
    ADD COLUMN ic_last4 VARCHAR(4) NULL AFTER ic_hash,
    ADD INDEX idx_ic_hash (ic_hash);

ALTER TABLE admin_users
    ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS rate_limits (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action      VARCHAR(30) NOT NULL,
    who         CHAR(64)    NOT NULL,          -- keyed hash of the address, not the address itself
    created_at  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rate (action, who, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- verify ----------
SHOW COLUMNS FROM rsvp_attendees LIKE 'ic%';
