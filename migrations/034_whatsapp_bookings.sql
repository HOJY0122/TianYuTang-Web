-- ============================================================
-- Migration 034 — WhatsApp bookings keyed in by staff
--
-- rsvp_groups.source gains 'whatsapp': a booking that came in by
-- WhatsApp and was typed in by staff (現場登記 page → 來源 WhatsApp).
-- Unlike a walk-in it is not checked in yet — they arrive on the day.
-- Counted in 施齋 Day 3 "預約總人數 Total reserved".
--
-- Run with:  php bin/migrate.php      Requires migration 033.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE rsvp_groups MODIFY source ENUM('online','walkin','whatsapp') NOT NULL DEFAULT 'online';
