-- ============================================================
-- Migration 007 — admin login rate limiting
--
-- Until now the login form accepted unlimited guesses, so a script
-- could try thousands of passwords against the admin account.
--
-- Adds:
--   login_attempts  one row per FAILED login, keyed by IP address.
--                   Five failures within 15 minutes locks that address
--                   out until the oldest failure expires.
--
-- Rows only matter for 15 minutes and the app deletes old ones itself,
-- so this table stays tiny.
--
-- Nothing existing is changed; safe to run twice.
--
-- Requires migrations 001–006.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip           VARCHAR(45) NOT NULL,   -- 45 fits a full IPv6 address
    username     VARCHAR(50) NOT NULL,   -- what was typed, for the record
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- verify ----------
SHOW TABLES LIKE 'login_attempts';
