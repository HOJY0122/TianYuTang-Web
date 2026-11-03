-- ============================================================
-- Migration 033 — 施齋 Day 3: tables and seating
--
-- zai_tables   the hall's tables for an event: number and seats (e.g. 10)
-- zai_people   Day 3 for people who registered online (rsvp_attendees):
--              confirmed / pending and their table
-- zai_groups   團體 groups: organisation registrations (rsvp_group_id)
--              and the PM's own list (imported); check-in, table(s),
--              a photo of the cheque (private, storage/zai)
-- zai_waiting  候補 waiting list: people who came without registering;
--              onsite staff seat them later. IC kept only as a keyed
--              hash + last 4 digits (to spot someone already registered).
--
-- Run with:  php bin/migrate.php      Requires migrations 001–032.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

CREATE TABLE IF NOT EXISTS zai_tables (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id    INT NOT NULL,
    table_no    SMALLINT UNSIGNED NOT NULL,
    seats       TINYINT UNSIGNED NOT NULL DEFAULT 10,
    UNIQUE KEY uq_zai_table (event_id, table_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS zai_people (
    attendee_id INT NOT NULL PRIMARY KEY,
    event_id    INT NOT NULL,
    status      ENUM('confirmed','pending') NOT NULL DEFAULT 'confirmed',
    table_no    SMALLINT UNSIGNED NULL,
    updated_by  VARCHAR(50) NULL,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_zai_people_event (event_id, table_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS zai_groups (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id      INT NOT NULL,
    source        ENUM('rsvp','list') NOT NULL DEFAULT 'list',
    rsvp_group_id INT NULL,
    list_no       VARCHAR(20)  NULL,
    name          VARCHAR(150) NOT NULL,
    contact       VARCHAR(60)  NULL,
    people        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status        ENUM('expected','arrived') NOT NULL DEFAULT 'expected',
    tables        VARCHAR(120) NULL,
    cheque_no     VARCHAR(40)  NULL,
    cheque_path   VARCHAR(255) NULL,
    notes         VARCHAR(500) NULL,
    arrived_at    DATETIME NULL,
    arrived_by    VARCHAR(50) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_zai_rsvp_group (rsvp_group_id),
    KEY idx_zai_groups_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS zai_waiting (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id    INT NOT NULL,
    queue_no    INT UNSIGNED NOT NULL,
    name        VARCHAR(100) NOT NULL,
    ic_hash     CHAR(64) NULL,
    ic_last4    VARCHAR(4) NULL,
    contact     VARCHAR(30) NULL,
    people      TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status      ENUM('waiting','seated','left') NOT NULL DEFAULT 'waiting',
    table_no    SMALLINT UNSIGNED NULL,
    notes       VARCHAR(300) NULL,
    created_by  VARCHAR(50) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    seated_at   DATETIME NULL,
    UNIQUE KEY uq_zai_queue (event_id, queue_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
