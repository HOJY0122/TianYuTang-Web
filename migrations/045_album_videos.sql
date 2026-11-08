-- ============================================================
-- Migration 045 — 相簿影片 Album videos
--
-- An album item can now be a short video. It stays one row in
-- event_photos, so ordering, categories, covers and the home page work
-- for videos exactly as for photos:
--   file_path / thumb_path  the video's cover picture (like any photo)
--   video_path              the video itself, "videos/<random>.mp4",
--                           stored in storage/ (outside the web folder)
--   duration_sec            its length, shown on the tile ("1:23")
-- A row with no video_path is a photo, as before.
--
-- Run with:  php bin/migrate.php      Requires migration 044.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE event_photos
  ADD COLUMN video_path   VARCHAR(255)      NULL DEFAULT NULL AFTER thumb_path,
  ADD COLUMN duration_sec SMALLINT UNSIGNED NULL DEFAULT NULL AFTER video_path;
