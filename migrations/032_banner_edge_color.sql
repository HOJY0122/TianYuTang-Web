-- ============================================================
-- Migration 032 — home banner: the picture's own edge colour
--
-- site_banners.edge_color — the most common colour along a picture's
-- edges (e.g. the cream paper behind a poster), worked out once from the
-- file. "整張顯示，兩旁素色 Whole picture, plain sides" fills the sides with
-- it, so the picture blends into them with no visible join.
--
-- Run with:  php bin/migrate.php      Requires migration 031.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE site_banners ADD COLUMN edge_color CHAR(7) NULL AFTER fit;
