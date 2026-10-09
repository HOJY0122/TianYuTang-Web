-- ============================================================
-- Migration 023 — who is holding each receipt book
--
-- receipt_books: one row per paper receipt book handed out — the book
--   number and the member in charge of it (負責人) with a phone number,
--   so the committee knows who has which book. A book can be registered
--   before any receipt from it is entered. receipts.book_no matches
--   book_no here (both stored in capitals).
--
-- Run with:  php bin/migrate.php      Requires migrations 001–022.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

CREATE TABLE IF NOT EXISTS receipt_books (
    book_no      VARCHAR(30)  NOT NULL PRIMARY KEY,
    holder       VARCHAR(100) NULL,               -- 負責人 member in charge
    holder_phone VARCHAR(30)  NULL,
    updated_by   VARCHAR(50)  NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_book_holder (holder)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Books already used by receipts get a row, holder to be filled in.
INSERT IGNORE INTO receipt_books (book_no)
    SELECT DISTINCT book_no FROM receipts WHERE book_no IS NOT NULL;
