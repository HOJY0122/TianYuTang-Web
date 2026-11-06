-- ============================================================
-- Migration 040 — waiting list keeps the IC (encrypted) for staff to see
--
-- The easy-mode waiting list now shows each person's name, full IC and
-- phone so staff can check them quickly. The IC is stored encrypted, the
-- same way as registrations (rsvp_attendees.ic_no); ic_hash / ic_last4
-- stay for matching. Older rows only have the last 4 digits.
--
-- Run with:  php bin/migrate.php      Requires migration 039.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE zai_waiting
    ADD COLUMN ic_no VARCHAR(255) NULL AFTER name;
