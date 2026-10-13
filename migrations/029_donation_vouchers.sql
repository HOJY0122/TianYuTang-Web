-- ============================================================
-- Migration 029 — 布施憑單 Donation vouchers
--
-- When the temple gives money to other charity centres (慈善布施), each
-- centre gets a printed voucher. The committee keeps them in batches:
--
-- voucher_batches: one giving round — date, description, the usual
--   amount and payment method, e.g. "2026 Charity · RM 2,000 · Bank
--   Transfer". Amount in words is worked out from the amount unless
--   typed in.
-- vouchers: one per centre — its No. from the Excel list, English and
--   Chinese name, bank account (encrypted, App\Core\Crypto), an amount
--   when it differs from the batch's, and when it was paid.
--
-- Run with:  php bin/migrate.php      Requires migrations 001–028.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

CREATE TABLE IF NOT EXISTS voucher_batches (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(120) NOT NULL,
    voucher_date  DATE NULL,
    description   VARCHAR(120) NOT NULL DEFAULT 'CHARITY DONATION',
    amount        DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_words  VARCHAR(200) NULL,
    method        VARCHAR(60)  NOT NULL DEFAULT 'Bank Transfer',
    paper         VARCHAR(10)  NOT NULL DEFAULT 'a5',
    notes         VARCHAR(500) NULL,
    last_seq      INT UNSIGNED NOT NULL DEFAULT 0,
    created_by    VARCHAR(60)  NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vouchers (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id      INT UNSIGNED NOT NULL,
    seq           INT UNSIGNED NOT NULL,
    list_no       VARCHAR(20)  NULL,
    name_en       VARCHAR(200) NULL,
    name_zh       VARCHAR(120) NULL,
    bank_account  VARCHAR(400) NULL,
    amount        DECIMAL(12,2) NULL,
    paid_at       DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_voucher_seq (batch_id, seq),
    CONSTRAINT fk_voucher_batch FOREIGN KEY (batch_id) REFERENCES voucher_batches (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
