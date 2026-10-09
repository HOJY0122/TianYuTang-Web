-- ============================================================
-- Migration 026 — 關於我們 About us page
--
-- about_blocks: the About page, one block after another in sort_order,
--   written in System → 關於我們 About page:
--     kind 'image' — a picture shown large and centred (e.g. the main
--                    deity's poster), with an optional caption;
--     kind 'text'  — an optional heading and paragraphs, Chinese and
--                    English.
--   Pictures live in public/uploads/about (served only through signed
--   /media addresses, like news photos).
--
-- Run with:  php bin/migrate.php      Requires migrations 001–025.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

CREATE TABLE IF NOT EXISTS about_blocks (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kind        ENUM('image','text') NOT NULL,
    image_path  VARCHAR(255) NULL,
    heading_zh  VARCHAR(120) NULL,
    heading_en  VARCHAR(160) NULL,
    body_zh     TEXT NULL,
    body_en     TEXT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_about_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
