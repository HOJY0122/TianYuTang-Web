-- ============================================================
-- Migration 012 — bank-in slip on a receipt
--
-- receipts.bank_slip_path: for receipts paid by 轉帳 Bank-In, a photo
--   or screenshot of the bank's transaction receipt. Kept privately
--   (storage/receipts/bank), never under public/, like receipt photos.
--
-- Nothing existing changes. Run with:  php bin/migrate.php
-- Requires migrations 001–011.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE receipts
    ADD COLUMN bank_slip_path VARCHAR(255) NULL AFTER image_path;

-- ---------- verify ----------
SHOW COLUMNS FROM receipts LIKE 'bank_slip_path';
