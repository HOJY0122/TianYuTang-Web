-- ============================================================
-- Migration 007 — teach the receipt scanner from its own mistakes
--
-- This does NOT train a machine-learning model. It records, for every
-- field on every scanned receipt, what the OCR guessed and what the
-- human actually saved. Three things come out of that one table:
--
--   1. ACCURACY.  "Name is wrong 60% of the time, amount 12%." Without
--      this you are guessing which part of the scanner to improve.
--
--   2. A CORRECTION DICTIONARY.  When '50P' has been corrected to '50'
--      several times, apply it automatically from then on. That is the
--      part that feels like learning, and it is a GROUP BY.
--
--   3. A LABELLED TRAINING SET.  If the temple ever does want a real
--      model, this table plus the stored receipt images IS the training
--      data. Start collecting now or that option stays closed: a
--      correction that was never recorded cannot be recovered later.
--
-- Deliberately NOT tied to the receipts table with a foreign key.
-- doc_type + doc_id means the same log works for receipts, counter
-- donations, or anything scanned later, and the log survives even if a
-- receipt row is deleted — the whole point is to keep the history.
--
-- Back up first:
--     mysqldump -u USER -p tianyutang2026 > backup_before_007.sql
--
-- Running it twice is harmless but not silent: the table creation skips
-- itself, then the index at the end stops with "Duplicate key name
-- 'idx_name'". That error means it already ran — nothing is damaged.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

CREATE TABLE IF NOT EXISTS ocr_corrections (
    id          INT AUTO_INCREMENT PRIMARY KEY,

    doc_type    VARCHAR(20)  NOT NULL DEFAULT 'receipt',
    doc_id      INT          NOT NULL,

    field_name  VARCHAR(40)  NOT NULL,

    -- What the scanner produced. NULL means it found nothing at all,
    -- which is a different failure from finding the wrong thing and is
    -- worth being able to tell apart in the accuracy report.
    ocr_value   VARCHAR(255) NULL,

    -- What the human saved. This is the ground truth.
    final_value VARCHAR(255) NULL,

    -- Stored rather than computed, so the accuracy report stays a plain
    -- indexed count instead of comparing strings across the whole table.
    was_wrong   TINYINT(1)   NOT NULL DEFAULT 0,

    image_path  VARCHAR(255) NULL,
    corrected_by VARCHAR(50) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Drives the accuracy report: "which fields go wrong most often".
    INDEX idx_field_wrong (field_name, was_wrong),

    -- Drives the correction dictionary lookup, which runs once per
    -- field on every scan and must not table-scan.
    INDEX idx_lookup (field_name, ocr_value(64)),

    INDEX idx_doc (doc_type, doc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Donor memory: the name lookup runs on every scan, against a column
-- that was only ever filtered by id before.
ALTER TABLE donations ADD INDEX idx_name (name);

-- ---------- verify ----------
SHOW COLUMNS FROM ocr_corrections;
