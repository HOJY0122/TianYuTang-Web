-- ============================================================
-- Migration 002 — the yearly photo archive
--
-- Run this on any database created before the gallery existed.
-- Safe to run twice: the table is only created if missing, and no
-- existing data is touched.
--
-- Back up first:
--     mysqldump -u USER -p tianyutang2026 > backup_before_002.sql
--
-- Requires migration 001 to have been applied already (this table
-- references events.id).
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

CREATE TABLE IF NOT EXISTS event_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id   INT NOT NULL,
    file_path  VARCHAR(255) NOT NULL,
    thumb_path VARCHAR(255) NOT NULL,
    caption    VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event_order (event_id, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- verify ----------
-- Should list the event_photos table.
SHOW TABLES LIKE 'event_photos';
