-- ============================================================
-- Migration 004 — counter (cash) donations
--
-- Until now a donation could only arrive through the public form.
-- Cash handed over at the counter had nowhere to go, so the
-- treasurer's totals would have been missing every walk-in.
--
-- Adds:
--   source       online | counter  — lets cash be reconciled separately
--   receipt_path photo of the paper receipt, kept for disputes
--   recorded_by  which admin entered it (accountability for cash)
--   notes        free text, e.g. "收據簿 #042"
--
-- Existing rows all came through the website, so they default to
-- 'online' — which is why the column carries that default rather than
-- being NULL-able.
--
-- Back up first:
--     mysqldump -u USER -p tianyutang2026 > backup_before_004.sql
--
-- Requires migrations 001–003.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE donations
    ADD COLUMN source       ENUM('online','counter') NOT NULL DEFAULT 'online' AFTER event_id,
    ADD COLUMN receipt_path VARCHAR(255) NULL DEFAULT NULL AFTER status,
    ADD COLUMN recorded_by  VARCHAR(50)  NULL DEFAULT NULL AFTER receipt_path,
    ADD COLUMN notes        VARCHAR(255) NULL DEFAULT NULL AFTER recorded_by,
    ADD INDEX idx_event_source (event_id, source);

-- ---------- verify ----------
SHOW COLUMNS FROM donations LIKE 'source';
SELECT source, COUNT(*) AS rows_ FROM donations GROUP BY source;
