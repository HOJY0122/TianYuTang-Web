-- ============================================================
-- Migration 027 — About page: size and layout per block
--
-- about_blocks.width      s / m / l / full — how wide the block is
--                         (narrow 520px, medium 720px, wide 960px, full)
-- about_blocks.text_size  s / m / l — paragraph text size
-- about_blocks.align      justify / left / center — paragraph alignment
--
-- Run with:  php bin/migrate.php      Requires migrations 001–026.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE about_blocks
    ADD COLUMN width     VARCHAR(8) NOT NULL DEFAULT 'm'       AFTER body_en,
    ADD COLUMN text_size VARCHAR(4) NOT NULL DEFAULT 'm'       AFTER width,
    ADD COLUMN align     VARCHAR(8) NOT NULL DEFAULT 'justify' AFTER text_size;
