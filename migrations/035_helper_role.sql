-- ============================================================
-- Migration 035 — a third role: 施齋助理 Day 3 helper
--
-- admin_users.role gains 'helper': a login for the people helping at
-- the Day 3 meal. It opens only the 施齋入座 easy screens (big buttons,
-- one question per screen) — no menu, no other admin page, no money.
-- Created in 系統 → 帳號管理 User accounts.
--
-- Run with:  php bin/migrate.php      Requires migration 034.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE admin_users MODIFY role ENUM('admin','system_admin','helper') NOT NULL DEFAULT 'admin';
