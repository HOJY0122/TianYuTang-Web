-- 天玉堂 — Event Registration System
-- Database schema (v2 — events)
--
-- ⚠  THIS FILE IS FOR A FRESH INSTALL ONLY.
--
--    Every CREATE below says IF NOT EXISTS, so importing this over an
--    EXISTING database silently skips the tables that are already
--    there. You end up with a new `events` table beside old v1 tables
--    that have no event_id — and the app dies with
--    "Unknown column 'g.event_id'".
--
--    Upgrading a database that already has data?
--        → run migrations/001_add_events.sql instead.
--
--    Already hit that error? Running the migration repairs it; your
--    existing registrations and donations are preserved.

-- Tell the client this file is UTF-8. Without this line the `mysql`
-- command-line tool may read it as latin1 and store every Chinese
-- character double-encoded (中 becomes ä¸­). phpMyAdmin usually gets
-- this right on its own; the CLI does not.
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS tianyutang2026 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tianyutang2026;

-- ============================================================
-- events — one row per year / season.
-- Everything the committee changes yearly lives here rather than
-- in code: dates, venue, price, banner. RSVPs and donations each
-- belong to exactly one event.
-- ============================================================
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- What the public sees
    name              VARCHAR(150) NOT NULL,          -- 天玉堂中壇元帥千秋寶誕
    year              SMALLINT UNSIGNED NOT NULL,     -- 2026
    year_label        VARCHAR(50)  NULL,              -- 丙午年
    subtitle          VARCHAR(200) NULL,              -- English strapline
    location          VARCHAR(255) NOT NULL,
    start_date        DATE NOT NULL,
    end_date          DATE NOT NULL,
    counter_note      VARCHAR(255) NULL,              -- e.g. 現場詢問處開放時間：16/10 及 17/10

    -- Money and limits (per event — prices change between years)
    merit_table_price DECIMAL(10,2) NOT NULL DEFAULT 500.00,
    max_attendees     TINYINT UNSIGNED NOT NULL DEFAULT 10,

    -- Uploaded assets. Unused until the file-upload work lands;
    -- the columns exist now so that change needs no migration.
    hero_banner_path  VARCHAR(255) NULL,
    favicon_path      VARCHAR(255) NULL,

    -- Submission windows. NULL = no restriction on that end.
    -- Unused until the open/close work lands.
    rsvp_opens_at      DATETIME NULL,
    rsvp_closes_at     DATETIME NULL,
    donation_opens_at  DATETIME NULL,
    donation_closes_at DATETIME NULL,

    -- is_active: the one event the public site shows.
    -- is_test:   a management dry run; excluded from real statistics.
    is_active BOOLEAN NOT NULL DEFAULT FALSE,
    is_test   BOOLEAN NOT NULL DEFAULT FALSE,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_active (is_active),
    INDEX idx_year (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- rsvp_groups — one row per registration submission
-- ============================================================
CREATE TABLE IF NOT EXISTS rsvp_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,

    -- online = the public form; walkin = entered at the counter (migration 006)
    source ENUM('online','walkin') NOT NULL DEFAULT 'online',

    ref_code VARCHAR(20) NULL UNIQUE,           -- RSVP-0001, set from id right after insert
    attendee_count TINYINT UNSIGNED NOT NULL,
    status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
    recorded_by VARCHAR(50) NULL DEFAULT NULL,  -- which admin entered a walk-in
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- RESTRICT, not CASCADE: deleting an event must never silently
    -- destroy the registrations attached to it.
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE RESTRICT,
    INDEX idx_event_status (event_id, status),
    INDEX idx_event_source (event_id, source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- rsvp_attendees — one row per person within a group
-- ============================================================
CREATE TABLE IF NOT EXISTS rsvp_attendees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    ic_no VARCHAR(30) NOT NULL,
    contact_no VARCHAR(30) NOT NULL,

    -- When this person arrived at the counter. NULL = not yet.
    -- Per attendee rather than per registration: a family of four
    -- registers once but does not always arrive together.
    checked_in_at DATETIME NULL DEFAULT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES rsvp_groups(id) ON DELETE CASCADE,
    INDEX idx_group (group_id),
    INDEX idx_checked_in (checked_in_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- donations — freewill or merit-seat (功德席)
-- ============================================================
CREATE TABLE IF NOT EXISTS donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,

    -- Where it came from. Cash taken at the counter has to be
    -- reconcilable separately from what the website collected.
    source ENUM('online','counter') NOT NULL DEFAULT 'online',

    ref_code VARCHAR(20) NULL UNIQUE,           -- DON-0001, set from id right after insert
    name VARCHAR(100) NOT NULL,
    contact_no VARCHAR(30) NOT NULL,
    method ENUM('free','table') NOT NULL,
    table_count SMALLINT UNSIGNED DEFAULT NULL, -- only when method = 'table'
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','paid') NOT NULL DEFAULT 'pending',

    receipt_path VARCHAR(255) NULL DEFAULT NULL, -- photo of the paper receipt
    recorded_by  VARCHAR(50)  NULL DEFAULT NULL, -- which admin entered it
    notes        VARCHAR(255) NULL DEFAULT NULL, -- e.g. 收據簿 #042

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE RESTRICT,
    INDEX idx_event_status (event_id, status),
    INDEX idx_event_source (event_id, source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- event_photos — the yearly photo archive
--
-- Each photo belongs to one event, so the gallery organises itself by
-- year with no extra work. Two files are stored per photo: a display
-- copy and a small thumbnail, because a grid of full-size phone photos
-- would be unusable on mobile data.
-- ============================================================
CREATE TABLE IF NOT EXISTS event_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id   INT NOT NULL,
    file_path  VARCHAR(255) NOT NULL,   -- display copy, max 1600px wide
    thumb_path VARCHAR(255) NOT NULL,   -- grid thumbnail, max 600px wide
    caption    VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,  -- lower numbers appear first
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- CASCADE here, unlike registrations: photos are illustrative, not
    -- records the committee is accountable for. Removing an event
    -- should take its pictures with it rather than block the delete.
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event_order (event_id, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- admin_users — committee logins
-- ============================================================
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,

    -- system_admin is a SUPERSET of admin: everything the committee
    -- does, plus branding, the QR generator and managing logins.
    role ENUM('admin','system_admin') NOT NULL DEFAULT 'admin',

    display_name  VARCHAR(80) NULL DEFAULT NULL,
    last_login_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- settings — site-level values not tied to any one year
-- (the header name, for example, outlives every event)
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- login_attempts — failed admin logins, for rate limiting.
-- Five failures from one IP within 15 minutes locks it out
-- until the oldest ages out. Old rows are pruned by the app.
-- ============================================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip           VARCHAR(45) NOT NULL,
    username     VARCHAR(50) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Seed data
-- ============================================================

-- The 2026 event.
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

-- Default admin. Login: admin / tianyutang2026
-- The app will not let this password be used for anything except
-- choosing a new one: the first login goes straight to the
-- change-password page (see AdminUser::DEFAULT_PASSWORDS).
INSERT INTO admin_users (username, password_hash, role, display_name) VALUES
('admin', '$2y$12$2peuIpyQnsls10rNrIOTU.8xcMbsvlnnhpCIxLsQZkia9EapTIvgW', 'system_admin', '系統管理員')
ON DUPLICATE KEY UPDATE username = username;

-- Site-level defaults.
INSERT INTO settings (setting_key, setting_value) VALUES
    ('site_name',    '天玉堂'),
    ('site_tagline', '🙏 感恩您的參與與支持　｜　Thank you for your kind support')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
