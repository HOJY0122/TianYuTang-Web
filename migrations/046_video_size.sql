-- ============================================================
-- Migration 046 — 相簿影片的長闊 Album video width / height
--
-- Kept so the website can tell a vertical (phone, 9:16) video from a
-- horizontal one before it loads, and size the tile and the player to
-- fit it, with no wide black bars around a tall video.
--
-- Run with:  php bin/migrate.php      Requires migration 045.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE event_photos
  ADD COLUMN video_w SMALLINT UNSIGNED NULL DEFAULT NULL AFTER duration_sec,
  ADD COLUMN video_h SMALLINT UNSIGNED NULL DEFAULT NULL AFTER video_w;
