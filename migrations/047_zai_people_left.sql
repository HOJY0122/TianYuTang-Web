-- ============================================================
-- Migration 047 — 施齋 Day 3: a registered person who LEFT
--
-- Someone seated who then goes (does not want to eat after all) is
-- marked 離開 Left: their seat is freed at once, they no longer count as
-- arrived, and the record keeps that they came and went (left_from = the
-- table they left) instead of looking as if they never came. Confirming
-- them again (they came back) works as for anyone else.
--
-- Run with:  php bin/migrate.php      Requires migration 046.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE zai_people
  MODIFY status ENUM('confirmed','pending','left') NOT NULL DEFAULT 'confirmed',
  ADD COLUMN left_from SMALLINT UNSIGNED NULL DEFAULT NULL AFTER table_no;
