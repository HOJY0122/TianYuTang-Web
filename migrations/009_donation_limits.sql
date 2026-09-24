-- ============================================================
-- Migration 009 — online donation limits per event
--
-- Lets the committee set, for each event, the smallest and largest
-- online donation:
--   seats_min / seats_max   merit seats per online submission
--   free_min  / free_max    freewill amount (RM) per online submission
--
-- NULL means "no limit of our own" (the system's built-in safety caps
-- still apply). Above the maximum, the donor is thanked and asked to
-- submit again for the remainder, or to visit the counter on the day.
-- Counter (cash) donations entered by staff are never limited.
--
-- Nothing existing changes. Run with:  php bin/migrate.php
-- Requires migrations 001–008.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE events
    ADD COLUMN seats_min SMALLINT UNSIGNED NULL DEFAULT NULL AFTER donation_note,
    ADD COLUMN seats_max SMALLINT UNSIGNED NULL DEFAULT NULL AFTER seats_min,
    ADD COLUMN free_min  DECIMAL(10,2)     NULL DEFAULT NULL AFTER seats_max,
    ADD COLUMN free_max  DECIMAL(10,2)     NULL DEFAULT NULL AFTER free_min;

-- ---------- verify ----------
SHOW COLUMNS FROM events LIKE 'seats_%';
SHOW COLUMNS FROM events LIKE 'free_%';
