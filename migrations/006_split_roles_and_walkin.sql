-- ============================================================
-- Migration 006 — two separate roles, and the walk-in register
--
-- PART A — the roles become genuinely separate areas
--
--   admin         runs the event: registrations, walk-ins, donations,
--                 check-in, counter cash, events, photos, exports.
--                 Lives at /admin/*
--   system_admin  runs the site: banner, favicon, site name, who has a
--                 login, QR generator. Lives at /system/*
--
-- Migration 005 made system_admin a superset that shared the admin
-- dashboard. This migration separates them: each role has its own
-- login landing page and cannot open the other's pages at all.
--
-- That separation only works if BOTH accounts exist, so this creates
-- 'sysadmin' and returns 'admin' to the plain admin role. Nobody is
-- locked out either way: a system admin can reset an admin's password
-- from 系統管理 → 管理帳號, and can create more accounts of either role.
--
--   >>> CHANGE THE sysadmin PASSWORD IMMEDIATELY AFTER RUNNING THIS <<<
--       sysadmin / tianyutang-sys2026
--
-- Branding also moves out of the event form and into site settings,
-- because a banner and a favicon belong to the site, not to one year.
-- The existing values are copied across so nothing disappears.
--
-- PART B — walk-in registrations
--
-- People who never registered online and simply turn up on the day.
-- They are ordinary registrations with two extra facts recorded: that
-- they came from the counter, and which volunteer entered them.
--
-- Back up first:
--     mysqldump -u USER -p tianyutang2026 > backup_before_006.sql
--
-- Requires migrations 001–005.
--
-- Running it twice is harmless but NOT silent: the account and settings
-- parts skip themselves, while the ALTER TABLE at the end stops with
-- "Duplicate column name 'source'". That error means it already ran —
-- nothing is damaged. Same behaviour as migration 004.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

-- ---------- A1. make sure a system administrator exists ----------
-- Inserted BEFORE the demotion below, so there is never a moment
-- where the database holds no system administrator at all.
INSERT INTO admin_users (username, password_hash, role, display_name)
VALUES (
    'sysadmin',
    '$2y$12$/cp9j.JgVXWqYCy/NS8jsu4gq17ZusFrafFVujN2.pzbw6YLXaWy.',
    'system_admin',
    '系統管理員'
)
ON DUPLICATE KEY UPDATE username = username;

-- ---------- A2. return the day-to-day account to the admin role ----------
-- Only the default 'admin' account. Any other account a system admin
-- deliberately promoted is left exactly as it is.
UPDATE admin_users SET role = 'admin' WHERE username = 'admin';

-- ---------- A3. branding becomes site-level ----------
-- Copy whatever the live event is using, so the site looks identical
-- the moment this finishes.
INSERT INTO settings (setting_key, setting_value)
SELECT 'site_banner_path', hero_banner_path
FROM events
WHERE is_active = 1 AND hero_banner_path IS NOT NULL
LIMIT 1
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO settings (setting_key, setting_value)
SELECT 'site_favicon_path', favicon_path
FROM events
WHERE is_active = 1 AND favicon_path IS NOT NULL
LIMIT 1
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- The events.hero_banner_path / favicon_path columns are deliberately
-- NOT dropped. Old rows keep their history, and the public pages still
-- fall back to them if a setting is missing.

-- ---------- B. walk-in registrations ----------
-- Mirrors donations.source / donations.recorded_by, added in 004, so
-- both halves of "who took this at the counter" look the same.
ALTER TABLE rsvp_groups
    ADD COLUMN source ENUM('online','walkin') NOT NULL DEFAULT 'online' AFTER event_id,
    ADD COLUMN recorded_by VARCHAR(50) NULL DEFAULT NULL AFTER status;

-- Everything that existed before this migration arrived online.
UPDATE rsvp_groups SET source = 'online' WHERE source IS NULL;

-- Counted often on the day ("how many walk-ins so far?"), so it gets
-- an index rather than a full scan of the table each time.
ALTER TABLE rsvp_groups ADD INDEX idx_event_source (event_id, source);

-- ---------- verify ----------
SELECT username, role FROM admin_users ORDER BY role, username;
SELECT setting_key, setting_value FROM settings ORDER BY setting_key;
SELECT source, COUNT(*) AS groups_count FROM rsvp_groups GROUP BY source;
