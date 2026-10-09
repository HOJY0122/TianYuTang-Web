-- ============================================================
-- Migration 021 — receipt books and bulk upload
--
-- receipts.book_no: the paper receipt BOOK a receipt was torn from
--   (簿號 e.g. "12" or "A03"). Receipts can be filtered and grouped by
--   book, and each book shows its number range and any numbers missing.
-- receipts.needs_check: 1 = added by bulk upload (photo only, or read by
--   AI) and not yet checked by a person. Saving it from the form sets 0.
--   Receipts added one at a time are checked before saving, so they
--   start at 0, as do all existing receipts.
--
-- Run with:  php bin/migrate.php      Requires migrations 001–020.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE receipts
    ADD COLUMN book_no     VARCHAR(30) NULL AFTER receipt_no,
    ADD COLUMN needs_check TINYINT(1)  NOT NULL DEFAULT 0 AFTER ai_notes,
    ADD INDEX idx_receipt_book (book_no, receipt_no),
    ADD INDEX idx_receipt_check (needs_check);
