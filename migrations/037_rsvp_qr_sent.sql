-- ============================================================
-- Migration 037 — registration QR sent by staff
--
-- The public confirmation page no longer shows a QR. Staff check each
-- registration first, then send the check-in QR themselves (報名紀錄 →
-- QR Code: download / WhatsApp / print). These record when and by whom,
-- so the list can show who still needs theirs.
--
-- Run with:  php bin/migrate.php      Requires migration 036.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE rsvp_groups
    ADD COLUMN qr_sent_at DATETIME NULL AFTER recorded_by,
    ADD COLUMN qr_sent_by VARCHAR(50) NULL AFTER qr_sent_at;
