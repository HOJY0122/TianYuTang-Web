-- ============================================================
-- Migration 041 — thank-you wording tidied
--
-- The confirmation headings lose the 🙏 emoji and the exclamation marks
-- (the heading font has no "！", so it was drawn in another font), and
-- the text reads as Traditional Chinese as used here: 報名 (not "RSVP"),
-- 義工, 儘快. Saved Wording-page values for these texts would hide the
-- new defaults, so they are cleared (they can be edited again after).
--
-- Run with:  php bin/migrate.php      Requires migration 040.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

DELETE FROM settings WHERE setting_key IN (
    'txt.success.rsvp_title.zh', 'txt.success.rsvp_title.en',
    'txt.success.rsvp_text.zh',  'txt.success.rsvp_text.en',
    'txt.success.don_title.zh',  'txt.success.don_title.en',
    'txt.success.don_text.zh',   'txt.success.don_text.en'
);
