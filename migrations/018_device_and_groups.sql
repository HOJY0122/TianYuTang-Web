-- ============================================================
-- Migration 018 — one device per account, organisation registrations
--
-- admin_users.device_token: a hash of the token given to the browser at
--   the latest sign-in. With "one device per account" switched on
--   (Site settings), a sign-in on another device replaces it, and the
--   older device is signed out on its next click or realtime check.
--
-- rsvp_groups.reg_type / org_name: a registration is either an
--   individual / family one, or an organisation group with the
--   organisation's name (Site settings → Registration chooses which
--   kinds the public form offers). Every person's own details are
--   stored exactly as before.
--
-- Run with:  php bin/migrate.php      Requires migrations 001–017.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE admin_users
    ADD COLUMN device_token CHAR(64) NULL AFTER session_version;

ALTER TABLE rsvp_groups
    ADD COLUMN reg_type ENUM('individual','organisation') NOT NULL DEFAULT 'individual' AFTER source,
    ADD COLUMN org_name VARCHAR(150) NULL AFTER reg_type;
