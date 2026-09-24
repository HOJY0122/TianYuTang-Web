-- ============================================================
-- Migration 008 — editable site content, mixed donations, news
--
-- Adds:
--
--   events (admin-editable event content shown on the home page)
--     welcome_zh / welcome_en   welcome paragraph, both languages
--     contact_info              enquiry line, e.g. phone / WhatsApp
--     maps_url / waze_url       "open in Google Maps / Waze" buttons
--     waze_qr_path              optional uploaded Waze QR image
--                               (without one, a QR is drawn from waze_url)
--     rsvp_note / donation_note extra notes shown above each form
--
--   donations
--     method 'mixed'            merit seats AND a freewill amount in one
--                               submission (e.g. 2 seats + RM 50)
--     free_amount               the freewill part, kept separately so
--                               the treasurer can see both halves
--
--   posts                       news / announcements for the home page
--
-- Existing freewill donations get free_amount = amount, so every row
-- reads the same way afterwards.
--
-- Back up first:
--     mysqldump -u USER -p tianyutang2026 > backup_before_008.sql
-- Or run it with:   php bin/migrate.php
--
-- Requires migrations 001–007.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE events
    ADD COLUMN welcome_zh    TEXT         NULL AFTER counter_note,
    ADD COLUMN welcome_en    TEXT         NULL AFTER welcome_zh,
    ADD COLUMN contact_info  VARCHAR(255) NULL AFTER welcome_en,
    ADD COLUMN maps_url      VARCHAR(500) NULL AFTER contact_info,
    ADD COLUMN waze_url      VARCHAR(500) NULL AFTER maps_url,
    ADD COLUMN waze_qr_path  VARCHAR(255) NULL AFTER waze_url,
    ADD COLUMN rsvp_note     TEXT         NULL AFTER waze_qr_path,
    ADD COLUMN donation_note TEXT         NULL AFTER rsvp_note;

ALTER TABLE donations
    MODIFY COLUMN method ENUM('free','table','mixed') NOT NULL,
    ADD COLUMN free_amount DECIMAL(10,2) NULL DEFAULT NULL AFTER table_count;

UPDATE donations SET free_amount = amount WHERE method = 'free' AND free_amount IS NULL;

CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title_zh     VARCHAR(200) NOT NULL,
    title_en     VARCHAR(200) NULL,
    body_zh      TEXT         NULL,
    body_en      TEXT         NULL,
    image_path   VARCHAR(255) NULL,
    is_published BOOLEAN NOT NULL DEFAULT TRUE,
    is_pinned    BOOLEAN NOT NULL DEFAULT FALSE,   -- pinned posts stay on top
    created_by   VARCHAR(50)  NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_feed (is_published, is_pinned, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- verify ----------
SHOW COLUMNS FROM donations LIKE 'method';
SELECT method, COUNT(*) AS rows_ FROM donations GROUP BY method;
