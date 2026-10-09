-- ============================================================
-- Migration 020 — stop / resume online responses by hand
--
-- events.rsvp_stopped / donation_stopped: 1 = the committee pressed
--   "Stop accepting" (e.g. enough people registered by hand). The public
--   form closes at once, whatever the opening times say; "Resume" goes
--   back to the times. New events and test copies start at 0.
--
-- Run with:  php bin/migrate.php      Requires migrations 001–019.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE events
    ADD COLUMN rsvp_stopped     TINYINT(1) NOT NULL DEFAULT 0 AFTER rsvp_closes_at,
    ADD COLUMN donation_stopped TINYINT(1) NOT NULL DEFAULT 0 AFTER donation_closes_at;
