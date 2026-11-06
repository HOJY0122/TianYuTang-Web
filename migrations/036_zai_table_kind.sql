-- ============================================================
-- Migration 036 — 施齋 Day 3 table map: group tables
--
-- zai_tables.kind — what a table is for:
--   open   散客桌 general: registered online, walk-in, WhatsApp, waiting list
--   group  團體桌 kept for 團體 groups; the admin can pre-assign a group
--          to it on the 平面圖 Map before they arrive
-- Easy mode keeps group tables out of the way when seating individuals.
--
-- Run with:  php bin/migrate.php      Requires migration 035.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE zai_tables ADD COLUMN kind ENUM('open','group') NOT NULL DEFAULT 'open' AFTER seats;
