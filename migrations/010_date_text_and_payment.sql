-- ============================================================
-- Migration 010 — the event's date line in the committee's own words,
--                 and who took each payment at the counter
--
-- The home page writes the date for you from start/end dates:
--   "2026年10月16日（星期五） 起 · 3 天 / 16, 17 & 18 October 2026"
-- These two optional columns replace it when filled in — for a lunar
-- date ("農曆九月初七至初九"), or any wording the committee prefers.
-- NULL / empty = keep the automatic line.
--
-- donations.paid_at / paid_by: when a donor shows their donation QR at
-- the counter and pays, staff mark it paid — and the time and the staff
-- member are kept, so the treasurer can match cash to people.
--
-- Nothing existing changes. Run with:  php bin/migrate.php
-- Requires migrations 001–009.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE events
    ADD COLUMN date_text_zh VARCHAR(255) NULL DEFAULT NULL AFTER end_date,
    ADD COLUMN date_text_en VARCHAR(255) NULL DEFAULT NULL AFTER date_text_zh;

ALTER TABLE donations
    ADD COLUMN paid_at DATETIME    NULL DEFAULT NULL AFTER status,
    ADD COLUMN paid_by VARCHAR(50) NULL DEFAULT NULL AFTER paid_at;

-- ---------- verify ----------
SHOW COLUMNS FROM events LIKE 'date_text_%';
SHOW COLUMNS FROM donations LIKE 'paid_%';
