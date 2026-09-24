-- ============================================================
-- Migration 003 — on-site check-in
--
-- Adds a timestamp recording when each attendee arrived at the
-- counter. NULL means "not yet arrived".
--
-- Why per ATTENDEE rather than per registration: a family of four
-- registers once but does not always arrive together. Recording it per
-- person means the head count on the day is real rather than assumed.
--
-- Back up first:
--     mysqldump -u USER -p tianyutang2026 > backup_before_003.sql
--
-- Requires migrations 001 and 002.
-- Safe to run once; re-running errors harmlessly on the duplicate column.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE rsvp_attendees
    ADD COLUMN checked_in_at DATETIME NULL DEFAULT NULL AFTER contact_no,
    ADD INDEX idx_checked_in (checked_in_at);

-- ---------- verify ----------
-- Should list the checked_in_at column.
SHOW COLUMNS FROM rsvp_attendees LIKE 'checked_in_at';
