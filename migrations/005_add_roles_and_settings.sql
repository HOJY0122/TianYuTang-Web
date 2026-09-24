-- ============================================================
-- Migration 005 — admin roles and site settings
--
-- Splits the single all-powerful login into two roles:
--
--   admin         the committee's day-to-day work: registrations,
--                 donations, check-in, events, photos, exports
--   system_admin  everything above PLUS the technical and branding
--                 controls: site name, favicon, banner, QR generator,
--                 and managing who has a login at all
--
-- system_admin is a SUPERSET of admin, not a parallel role. A system
-- admin who could not confirm a registration would be useless on the
-- day, and a second login just to do ordinary work invites password
-- sharing — which is exactly what the role split is meant to prevent.
--
-- The existing 'admin' account is promoted to system_admin, otherwise
-- this migration would lock everyone out of the settings screens.
--
-- Also adds a `settings` table for site-level values that are not tied
-- to any one year — the header name being the obvious one.
--
-- Back up first:
--     mysqldump -u USER -p tianyutang2026 > backup_before_005.sql
--
-- Requires migrations 001–004.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

-- ---------- 1. roles ----------
ALTER TABLE admin_users
    ADD COLUMN role ENUM('admin','system_admin') NOT NULL DEFAULT 'admin' AFTER password_hash,
    ADD COLUMN display_name VARCHAR(80) NULL DEFAULT NULL AFTER role,
    ADD COLUMN last_login_at DATETIME NULL DEFAULT NULL AFTER display_name;

-- Whoever exists already must keep full access, or nobody can reach
-- the settings screens after this runs.
UPDATE admin_users SET role = 'system_admin';

-- ---------- 2. site-level settings ----------
CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (setting_key, setting_value) VALUES
    ('site_name',    '天玉堂'),
    ('site_tagline', '🙏 感恩您的參與與支持　｜　Thank you for your kind support')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- ---------- verify ----------
SELECT username, role FROM admin_users;
SELECT setting_key, setting_value FROM settings;
