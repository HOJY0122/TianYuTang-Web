-- ============================================================
-- Migration 022 — wrong passwords counted per account
--
-- Login failures are now counted per username (only for usernames that
-- exist), not per IP address, so the lookup needs an index on username.
-- Old rows were counted per IP and are cleared: at most 15 minutes of
-- history is lost.
--
-- Run with:  php bin/migrate.php      Requires migrations 001–021.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

DELETE FROM login_attempts;

ALTER TABLE login_attempts
    ADD INDEX idx_user_time (username, attempted_at);
