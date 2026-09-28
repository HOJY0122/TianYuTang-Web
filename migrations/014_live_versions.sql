-- ============================================================
-- Migration 014 — realtime updates
--
-- live_versions: one counter per table. Every save to a table adds 1 to
-- its counter (App\Core\Live, called from Model::execute). Open pages ask
-- /live every few seconds "have the counters of the tables I show
-- changed?" and, when one has, fetch fresh content for just those parts.
-- A tiny table read, so thousands of checks cost almost nothing.
--
-- Run with:  php bin/migrate.php      Requires migrations 001–013.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

CREATE TABLE IF NOT EXISTS live_versions (
    topic       VARCHAR(40)  NOT NULL PRIMARY KEY,
    version     INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- verify ----------
SHOW COLUMNS FROM live_versions;
