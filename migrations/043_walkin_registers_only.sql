-- ============================================================
-- Migration 043 — 現場報名 Walk-in register: registering only
--
-- The walk-in form is used on Day 1–2 to register people for the Day 3
-- meal after online registration closed. Until now saving it also marked
-- them as arrived (and migration 042 carried that into 施齋入座 Day 3), so
-- people who only registered showed as already here.
--
-- From now on a walk-in is a registration like any other; they arrive
-- when Day 3 confirms them. This removes only the AUTOMATIC arrival made
-- at registration time — anyone Day 3 has seated (table chosen) or
-- confirmed later by hand keeps their status.
--
-- Run with:  php bin/migrate.php      Requires migration 042.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

-- Day 3 rows that were made automatically for a walk-in: confirmed, no
-- table, written by migration 042 ('check-in') or by the walk-in save
-- itself (within a few seconds of the registration).
DELETE z FROM zai_people z
  JOIN rsvp_attendees a ON a.id = z.attendee_id
  JOIN rsvp_groups g    ON g.id = a.group_id
 WHERE g.source = 'walkin'
   AND z.status = 'confirmed'
   AND z.table_no IS NULL
   AND (z.updated_by = 'check-in' OR ABS(TIMESTAMPDIFF(SECOND, z.updated_at, g.created_at)) <= 5);

-- Their automatic "arrived" stamp goes too, unless Day 3 still has them
-- confirmed.
UPDATE rsvp_attendees a
  JOIN rsvp_groups g ON g.id = a.group_id
  LEFT JOIN zai_people z ON z.attendee_id = a.id
   SET a.checked_in_at = NULL
 WHERE g.source = 'walkin'
   AND a.checked_in_at IS NOT NULL
   AND ABS(TIMESTAMPDIFF(SECOND, a.checked_in_at, g.created_at)) <= 5
   AND (z.attendee_id IS NULL OR z.status <> 'confirmed');
