-- ============================================================
-- Migration 030 — vouchers print on A4 by default
--
-- The printed voucher now follows the committee's sample (one Payment
-- Voucher per A4 page; A5 prints the same page smaller). Batches made
-- before this used the old A5 default, so they move to A4 too.
--
-- Run with:  php bin/migrate.php      Requires migration 029.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE voucher_batches ALTER paper SET DEFAULT 'a4';
UPDATE voucher_batches SET paper = 'a4';
