-- ============================================================
-- Migration 044 — website menu: 關於我們 → 關於本壇
--
-- The About item in the website's top menu now reads 關於本壇 (the page
-- heading already does). A Wording-page value still holding the old
-- default would hide the new one, so only that exact old value is
-- cleared; any other wording someone typed is kept.
--
-- Run with:  php bin/migrate.php      Requires migration 043.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

DELETE FROM settings WHERE setting_key = 'txt.nav.about.zh' AND setting_value = '關於我們';
