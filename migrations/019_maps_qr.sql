-- ============================================================
-- Migration 019 — Google Maps QR image
--
-- events.maps_qr_path: an optional uploaded QR image for the Google
--   Maps link, shown on the home page next to the Waze one. Without an
--   image, a QR code is drawn from the Google Maps link automatically
--   (exactly as for Waze).
--
-- Run with:  php bin/migrate.php      Requires migrations 001–018.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE events
    ADD COLUMN maps_qr_path VARCHAR(255) NULL AFTER waze_qr_path;
