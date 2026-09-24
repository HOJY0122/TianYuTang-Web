-- ============================================================
-- Migration 001 — introduce the events table
--
-- ONLY needed if you already have a v1 database with live data.
-- A fresh install just runs schema.sql instead.
--
-- Safe to run once. Back up first:
--     mysqldump -u USER -p tianyutang2026 > backup_before_001.sql
--
-- What it does:
--   1. creates `events`
--   2. inserts the 2026 event
--   3. adds event_id to rsvp_groups and donations
--   4. attaches every existing row to the 2026 event
--   5. locks event_id down as NOT NULL with a foreign key
-- ============================================================

-- UTF-8 declaration — see the note in schema.sql. Without it the CLI
-- may double-encode the Chinese text in the seed row below.
SET NAMES utf8mb4;

USE tianyutang2026;

START TRANSACTION;

-- ---------- 1. events ----------
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name              VARCHAR(150) NOT NULL,
    year              SMALLINT UNSIGNED NOT NULL,
    year_label        VARCHAR(50)  NULL,
    subtitle          VARCHAR(200) NULL,
    location          VARCHAR(255) NOT NULL,
    start_date        DATE NOT NULL,
    end_date          DATE NOT NULL,
    counter_note      VARCHAR(255) NULL,
    merit_table_price DECIMAL(10,2) NOT NULL DEFAULT 500.00,
    max_attendees     TINYINT UNSIGNED NOT NULL DEFAULT 10,
    hero_banner_path  VARCHAR(255) NULL,
    favicon_path      VARCHAR(255) NULL,
    rsvp_opens_at      DATETIME NULL,
    rsvp_closes_at     DATETIME NULL,
    donation_opens_at  DATETIME NULL,
    donation_closes_at DATETIME NULL,
    is_active BOOLEAN NOT NULL DEFAULT FALSE,
    is_test   BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_year (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- 2. the 2026 event ----------
INSERT INTO events
    (name, year, year_label, subtitle, location, start_date, end_date,
     counter_note, merit_table_price, max_attendees, is_active, is_test)
SELECT
    '中壇元帥 · 千秋寶誕', 2026, '丙午年',
    '2026 Zhong Tan Marshal Birthday Celebration',
    'PERSATUAN PENGANUT DEWA TAI ZHI\nKUALA LUMPUR',
    '2026-10-16', '2026-10-18',
    '現場詢問處開放時間：16/10 及 17/10',
    500.00, 10, TRUE, FALSE
WHERE NOT EXISTS (SELECT 1 FROM events WHERE year = 2026 AND is_test = FALSE);

-- ---------- 3. add the column, nullable for now ----------
ALTER TABLE rsvp_groups ADD COLUMN event_id INT NULL AFTER id;
ALTER TABLE donations   ADD COLUMN event_id INT NULL AFTER id;

-- ---------- 4. attach existing rows to the 2026 event ----------
UPDATE rsvp_groups
   SET event_id = (SELECT id FROM events WHERE year = 2026 AND is_test = FALSE LIMIT 1)
 WHERE event_id IS NULL;

UPDATE donations
   SET event_id = (SELECT id FROM events WHERE year = 2026 AND is_test = FALSE LIMIT 1)
 WHERE event_id IS NULL;

-- ---------- 5. lock it down ----------
ALTER TABLE rsvp_groups MODIFY COLUMN event_id INT NOT NULL;
ALTER TABLE donations   MODIFY COLUMN event_id INT NOT NULL;

ALTER TABLE rsvp_groups
    ADD CONSTRAINT fk_rsvp_event  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE RESTRICT,
    ADD INDEX idx_event_status (event_id, status);

ALTER TABLE donations
    ADD CONSTRAINT fk_donation_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE RESTRICT,
    ADD INDEX idx_event_status (event_id, status);

COMMIT;

-- ---------- verify ----------
-- Every row should now show a year; none should be NULL.
SELECT 'rsvp_groups' AS tbl, COUNT(*) AS rows_total, COUNT(event_id) AS rows_with_event FROM rsvp_groups
UNION ALL
SELECT 'donations', COUNT(*), COUNT(event_id) FROM donations;
