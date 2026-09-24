-- ============================================================
-- Migration 013 — home page banner slideshow
--
-- site_banners: one row per banner picture. Several pictures take turns
--   at the top of the home page (fade or slide); each can be nudged
--   (pos_x / pos_y, where the picture is anchored, 0–100 %) and zoomed
--   (zoom, 100–250 %) so the important part stays in view, and can carry
--   an optional caption and link. img_w / img_h are the picture's own
--   size, used when the banner height is "whole picture".
--
-- The banner uploaded in Site settings so far becomes slide 1, so the
-- home page looks exactly the same until slides are added.
--
-- Run with:  php bin/migrate.php      Requires migrations 001–012.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

CREATE TABLE IF NOT EXISTS site_banners (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    image_path  VARCHAR(255)      NOT NULL,
    img_w       SMALLINT UNSIGNED NULL,
    img_h       SMALLINT UNSIGNED NULL,
    sort_order  INT               NOT NULL DEFAULT 0,
    is_active   TINYINT(1)        NOT NULL DEFAULT 1,
    pos_x       TINYINT UNSIGNED  NOT NULL DEFAULT 50,
    pos_y       TINYINT UNSIGNED  NOT NULL DEFAULT 50,
    zoom        SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    caption_zh  VARCHAR(120)      NULL,
    caption_en  VARCHAR(160)      NULL,
    link_url    VARCHAR(255)      NULL,
    created_at  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The current Site-settings banner becomes the first slide.
INSERT INTO site_banners (image_path, sort_order)
SELECT setting_value, 1 FROM settings
 WHERE setting_key = 'site_banner_path' AND setting_value IS NOT NULL AND setting_value <> ''
   AND NOT EXISTS (SELECT 1 FROM site_banners);
DELETE FROM settings WHERE setting_key = 'site_banner_path';

-- ---------- verify ----------
SELECT id, image_path, sort_order FROM site_banners;
