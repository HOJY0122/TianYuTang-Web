-- ============================================================
-- Migration 011 — scanned paper receipts, and news post order
--
-- receipts: the temple's own paper receipt book (收據 No. 26432 …),
--   photographed and read by AI or typed in by hand, so the handwritten
--   books can be searched, totalled and exported. One column per box
--   printed on the receipt: 布施 祈福 蓮花燈 添油 龍香 塔香 樂捐 施齋 其他.
--   The photo is kept privately (storage/receipts), never under public/.
--
-- posts.sort_order: news posts can be dragged into any order in admin.
--   Existing posts keep today's order (pinned first, newest first).
--
-- Settings: from now on an EMPTY wording or footer/letterhead line means
--   "show nothing", as typed. Before, empty meant "use the default", so
--   rows saved empty under the old rule are removed here — they go back
--   to their defaults, exactly as they looked before this update.
--
-- Nothing existing changes. Run with:  php bin/migrate.php
-- Requires migrations 001–010.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

CREATE TABLE IF NOT EXISTS receipts (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_no    VARCHAR(30)   NULL,                 -- printed number, e.g. 26432
    receipt_date  DATE          NULL,
    item          VARCHAR(255)  NULL,                 -- 項目
    name          VARCHAR(150)  NULL,                 -- 姓名 Name
    amt_donation  DECIMAL(10,2) NOT NULL DEFAULT 0,   -- 布施
    amt_blessing  DECIMAL(10,2) NOT NULL DEFAULT 0,   -- 祈福
    amt_lotus     DECIMAL(10,2) NOT NULL DEFAULT 0,   -- 蓮花燈
    amt_oil       DECIMAL(10,2) NOT NULL DEFAULT 0,   -- 添油
    amt_dragon    DECIMAL(10,2) NOT NULL DEFAULT 0,   -- 龍香
    amt_tower     DECIMAL(10,2) NOT NULL DEFAULT 0,   -- 塔香
    amt_gift      DECIMAL(10,2) NOT NULL DEFAULT 0,   -- 樂捐
    amt_meal      DECIMAL(10,2) NOT NULL DEFAULT 0,   -- 施齋
    amt_other     DECIMAL(10,2) NOT NULL DEFAULT 0,   -- 其他
    other_label   VARCHAR(100)  NULL,                 -- what "其他" was for
    total         DECIMAL(10,2) NOT NULL DEFAULT 0,   -- 總數 as written
    payment       ENUM('cash','bank','') NOT NULL DEFAULT '',
    issued_by     VARCHAR(100)  NULL,                 -- 發據人
    notes         TEXT          NULL,
    image_path    VARCHAR(255)  NULL,                 -- private photo (storage/receipts)
    source        ENUM('ai','manual') NOT NULL DEFAULT 'manual',
    ai_notes      TEXT          NULL,                 -- what the AI was unsure about
    created_by    VARCHAR(50)   NULL,
    updated_by    VARCHAR(50)   NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_receipt_no (receipt_no),
    INDEX idx_receipt_date (receipt_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE posts
    ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER is_pinned;

-- Keep the current order: pinned first, then newest first.
SET @n := 0;
UPDATE posts SET sort_order = (@n := @n + 1)
 ORDER BY is_pinned DESC, created_at DESC, id DESC;

DELETE FROM settings
 WHERE setting_value = ''
   AND (setting_key LIKE 'txt.%'
        OR setting_key IN ('footer_title', 'footer_copyright', 'pdf_name', 'pdf_name_en',
                           'pdf_line1', 'pdf_line2', 'pdf_line3'));

-- ---------- verify ----------
SHOW COLUMNS FROM receipts LIKE 'amt_%';
SHOW COLUMNS FROM posts LIKE 'sort_order';
