-- ============================================================
-- Migration 042 — 現場報到 Check-in merged into 施齋入座 Day 3
--
-- Registration is only for the Day 3 meal, so "arrived" now has ONE
-- home: Day 3 (zai_people ✓ 確認 = arrived, 待定 = not yet; a 團體 group
-- in zai_groups = arrived). rsvp_attendees.checked_in_at becomes its
-- mirror (kept by the app from now on), so the dashboard, export, print
-- list and registration page agree with Day 3.
--
-- Until now the two were recorded separately and drifted apart. This
-- lines up the existing data once, losing nothing:
--   1. checked in, but not in Day 3 at all   → Day 3 confirmed (no table)
--   2. a 團體 group with members checked in  → group arrived in Day 3
--   3. Day 3 confirmed / group arrived       → checked_in_at set
--   4. Day 3 待定 pending                    → checked_in_at cleared
--      (Day 3 is the later, deliberate choice; testing was done there)
--   5. staff accounts allowed "checkin" now have "zai" instead
--
-- Run with:  php bin/migrate.php      Requires migration 041.
-- ============================================================

SET NAMES utf8mb4;

USE tianyutang2026;

-- 1. Arrived at the old Check-in page, unknown to Day 3 → confirmed there.
--    (Members of a 團體 group are handled as their group, in step 2.)
INSERT INTO zai_people (attendee_id, event_id, status, table_no, updated_by)
SELECT a.id, g.event_id, 'confirmed', NULL, 'check-in'
  FROM rsvp_attendees a
  JOIN rsvp_groups g ON g.id = a.group_id
  LEFT JOIN zai_people z ON z.attendee_id = a.id
 WHERE a.checked_in_at IS NOT NULL
   AND z.attendee_id IS NULL
   AND g.status <> 'cancelled'
   AND g.reg_type <> 'organisation';

-- 2. A 團體 group whose members were checked in → arrived in Day 3.
UPDATE zai_groups z
  JOIN (SELECT group_id, MIN(checked_in_at) AS first_in
          FROM rsvp_attendees WHERE checked_in_at IS NOT NULL GROUP BY group_id) a
    ON a.group_id = z.rsvp_group_id
   SET z.status = 'arrived',
       z.arrived_at = COALESCE(z.arrived_at, a.first_in),
       z.arrived_by = COALESCE(z.arrived_by, 'check-in')
 WHERE z.status = 'expected';

-- 3. Day 3 says arrived → the mirror says so too.
UPDATE rsvp_attendees a
  JOIN zai_people z ON z.attendee_id = a.id
   SET a.checked_in_at = COALESCE(a.checked_in_at, z.updated_at)
 WHERE z.status = 'confirmed';

UPDATE rsvp_attendees a
  JOIN zai_groups z ON z.rsvp_group_id = a.group_id
   SET a.checked_in_at = COALESCE(a.checked_in_at, z.arrived_at, NOW())
 WHERE z.status = 'arrived';

-- 4. Day 3 says "not yet" → not arrived.
UPDATE rsvp_attendees a
  JOIN zai_people z ON z.attendee_id = a.id
   SET a.checked_in_at = NULL
 WHERE z.status = 'pending'
   AND NOT EXISTS (SELECT 1 FROM zai_groups zg WHERE zg.rsvp_group_id = a.group_id AND zg.status = 'arrived');

-- 5. Staff permission "checkin" (現場報到) → "zai" (施齋入座).
UPDATE admin_users
   SET permissions = REPLACE(permissions, '"checkin"', '"zai"')
 WHERE permissions LIKE '%"checkin"%';
