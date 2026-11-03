-- ============================================================
-- Migration 031 — home banner: show a whole picture
--
-- site_banners.fit — how a picture fills the banner:
--   cover  填滿 Fill: fills the frame, the rest is cropped (as before)
--   blur   整張 Whole picture, the empty sides filled with a blurred copy
--   plain  整張 Whole picture, plain sides (the banner's own colour)
-- A picture taller or wider than the banner (e.g. a poster) can now be
-- shown whole instead of cut off.
--
-- Run with:  php bin/migrate.php      Requires migration 013.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE site_banners ADD COLUMN fit VARCHAR(8) NOT NULL DEFAULT 'cover' AFTER zoom;
