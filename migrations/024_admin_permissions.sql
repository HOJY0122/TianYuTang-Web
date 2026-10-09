-- ============================================================
-- Migration 024 — choose which functions each admin can use
--
-- admin_users.permissions: JSON list of the admin functions this account
--   may use (e.g. ["checkin","walkin"]). NULL = every function, which is
--   what all existing accounts keep. System admins always have everything.
--   See App\Core\Access for the list of functions.
--
-- Run with:  php bin/migrate.php      Requires migrations 001–023.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE admin_users
    ADD COLUMN permissions TEXT NULL AFTER role;
