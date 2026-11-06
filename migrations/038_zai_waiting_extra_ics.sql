-- ============================================================
-- Migration 038 — waiting list: ICs of the other people in a party
--
-- Easy mode now asks for the IC of the person who queues (required) and,
-- when they come with others, lets staff add the others' ICs too (each
-- optional). Stored like every other IC here: only a blind index (to spot
-- someone who registered online after all) and the last 4 digits (to
-- show), as JSON [{"h": "...", "l": "5678"}, …]. The IC itself is never kept.
--
-- Run with:  php bin/migrate.php      Requires migration 037.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

ALTER TABLE zai_waiting
    ADD COLUMN extra_ics VARCHAR(2000) NULL AFTER ic_last4;
