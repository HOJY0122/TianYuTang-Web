-- ============================================================
-- Migration 039 — new thank-you wording after registering / donating
--
-- The confirmation pages get new default wording (thank-you message, and
-- for registrations a remark to bring the IC instead of the WhatsApp QR
-- promise). Wording edited on the Wording page is saved in settings and
-- would hide the new defaults, so those few saved values are cleared.
--
-- Run with:  php bin/migrate.php      Requires migration 038.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

DELETE FROM settings WHERE setting_key IN (
    'txt.success.rsvp_title.zh', 'txt.success.rsvp_title.en',
    'txt.success.rsvp_text.zh',  'txt.success.rsvp_text.en',
    'txt.success.rsvp_qr.zh',    'txt.success.rsvp_qr.en',
    'txt.success.don_title.zh',  'txt.success.don_title.en',
    'txt.success.don_text.zh',   'txt.success.don_text.en'
);
