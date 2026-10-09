-- ============================================================
-- Migration 025 — handwriting font for the public site
--
-- The public site's body text switches to 霞鶩文楷 LXGW WenKai TC: a
-- handwritten Kai style with every Traditional AND Simplified character
-- tested (Iansui, the earlier "hand-written" choice, lacks ~78% of common
-- Simplified characters). A deliberate choice of another font is kept;
-- only the old default (Noto Sans TC) and Iansui move to WenKai. It can
-- be changed any time in System → Forms & fonts.
--
-- Run with:  php bin/migrate.php      Requires migrations 001–024.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

INSERT INTO settings (setting_key, setting_value) VALUES ('body_font', 'wenkai')
    ON DUPLICATE KEY UPDATE setting_value = IF(setting_value IN ('noto_sans', 'iansui', ''), 'wenkai', setting_value);
