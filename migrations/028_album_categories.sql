-- ============================================================
-- Migration 028 — album categories (年份 → 類別 → 相片)
--
-- photo_categories: the kinds of album the committee keeps, e.g.
--   新年 New Year · 中壇千秋寶誕 Marshal's Birthday · 慈善布施 Charity.
--   Named, ordered and shown / hidden in Admin → Photos.
-- event_photos.category_id: which category a photo belongs to (NULL =
--   "其他 Others"). The public gallery goes year → category → photos.
--
-- Existing photos were all taken at the yearly birthday celebration,
-- so they start in 中壇千秋寶誕; the committee can move any of them.
--
-- Run with:  php bin/migrate.php      Requires migrations 001–027.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

CREATE TABLE IF NOT EXISTS photo_categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name_zh     VARCHAR(60)  NOT NULL,
    name_en     VARCHAR(80)  NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    is_visible  TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO photo_categories (name_zh, name_en, sort_order) VALUES
    ('新年', 'Chinese New Year', 1),
    ('中壇千秋寶誕', 'Marshal\'s Birthday', 2),
    ('慈善布施', 'Charity', 3);

ALTER TABLE event_photos
    ADD COLUMN category_id INT UNSIGNED NULL AFTER event_id,
    ADD INDEX idx_photo_category (category_id);

UPDATE event_photos SET category_id = (SELECT id FROM photo_categories WHERE name_zh = '中壇千秋寶誕' LIMIT 1);
