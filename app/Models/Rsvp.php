<?php
namespace App\Models;

use App\Core\Model;
use Exception;

/**
 * Rsvp — event registrations.
 *
 * A registration is TWO tables: one rsvp_groups row (the submission)
 * plus one rsvp_attendees row per person in it. Every group belongs to
 * exactly one event, so every query here is scoped by event_id — the
 * 2027 committee should never see 2026's numbers by accident.
 */
class Rsvp extends Model
{
    /**
     * Save a registration group and its attendees in one transaction,
     * so a half-written registration can never reach the database.
     *
     * @param int    $eventId   the event being registered for
     * @param array  $attendees list of ['name'=>, 'ic'=>, 'contact'=>]
     * @param string $refPrefix 'RSVP' normally, 'TEST-RSVP' for a dry run
     * @return string the generated reference code, e.g. RSVP-0001
     * @throws Exception if the save fails (the caller decides what to show)
     */
    public function create(int $eventId, array $attendees, string $refPrefix = 'RSVP'): string
    {
        $count = count($attendees);

        try {
            $this->db->beginTransaction();

            $this->execute(
                'INSERT INTO rsvp_groups (event_id, attendee_count, status) VALUES (?, ?, ?)',
                [$eventId, $count, 'pending']
            );
            $groupId = (int) $this->db->lastInsertId();
            $refCode = $this->assignRefCode('rsvp_groups', $refPrefix, $groupId);

            foreach ($attendees as $a) {
                $this->execute(
                    'INSERT INTO rsvp_attendees (group_id, name, ic_no, contact_no) VALUES (?, ?, ?, ?)',
                    [$groupId, $a['name'], $a['ic'], $a['contact']]
                );
            }

            $this->db->commit();
            return $refCode;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Register somebody who simply turned up on the day.
     *
     * Deliberately different from an online submission:
     *   - status is CONFIRMED straight away. They are standing at the
     *     counter; there is nothing left to confirm.
     *   - everyone is checked in on the spot, for the same reason. The
     *     head count on the check-in screen must include them, or the
     *     committee is planning food for the wrong number of people.
     *   - the volunteer who entered it is stamped on the row, exactly
     *     as counter donations record who took the cash.
     *   - submission windows do not apply. Walk-ins exist *because*
     *     online registration has closed.
     *
     * @param array  $attendees list of ['name'=>, 'ic'=>, 'contact'=>]
     * @return string the generated reference code
     * @throws Exception if the save fails
     */
    public function createWalkIn(
        int $eventId,
        array $attendees,
        string $recordedBy,
        string $refPrefix = 'RSVP'
    ): string {
        $count = count($attendees);

        try {
            $this->db->beginTransaction();

            $this->execute(
                'INSERT INTO rsvp_groups (event_id, source, attendee_count, status, recorded_by)
                 VALUES (?, ?, ?, ?, ?)',
                [$eventId, 'walkin', $count, 'confirmed', $recordedBy]
            );
            $groupId = (int) $this->db->lastInsertId();
            $refCode = $this->assignRefCode('rsvp_groups', $refPrefix, $groupId);

            foreach ($attendees as $a) {
                $this->execute(
                    'INSERT INTO rsvp_attendees (group_id, name, ic_no, contact_no, checked_in_at)
                     VALUES (?, ?, ?, ?, NOW())',
                    [$groupId, $a['name'], $a['ic'], $a['contact']]
                );
            }

            $this->db->commit();
            return $refCode;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /** Head counts split by how people registered, for the counter screen. */
    public function countsBySource(int $eventId): array
    {
        $rows = $this->fetchAll(
            "SELECT g.source,
                    COUNT(DISTINCT g.id) AS groups_count,
                    COUNT(a.id)          AS people_count
             FROM rsvp_groups g
             LEFT JOIN rsvp_attendees a ON a.group_id = g.id
             WHERE g.event_id = ? AND g.status <> 'cancelled'
             GROUP BY g.source",
            [$eventId]
        );

        $out = [
            'online' => ['groups' => 0, 'people' => 0],
            'walkin' => ['groups' => 0, 'people' => 0],
        ];
        foreach ($rows as $r) {
            $key = $r['source'] ?? 'online';
            if (isset($out[$key])) {
                $out[$key] = [
                    'groups' => (int) $r['groups_count'],
                    'people' => (int) $r['people_count'],
                ];
            }
        }
        return $out;
    }

    /** The most recent walk-ins, so the volunteer can see their last entries. */
    public function recentWalkIns(int $eventId, int $limit = 12): array
    {
        return $this->fetchAll(
            "SELECT g.id, g.ref_code, g.attendee_count, g.recorded_by, g.created_at,
                    (SELECT name FROM rsvp_attendees WHERE group_id = g.id ORDER BY id LIMIT 1) AS lead_name
             FROM rsvp_groups g
             WHERE g.event_id = ? AND g.source = 'walkin' AND g.status <> 'cancelled'
             ORDER BY g.id DESC
             LIMIT " . (int) $limit,
            [$eventId]
        );
    }

    /** Registration groups for one event, newest first, with the lead attendee's name. */
    public function allGroups(int $eventId, int $limit = 100): array
    {
        return $this->fetchAll(
            'SELECT g.id, g.ref_code, g.attendee_count, g.status, g.source, g.created_at,
                    (SELECT name FROM rsvp_attendees WHERE group_id = g.id ORDER BY id LIMIT 1) AS lead_name
             FROM rsvp_groups g
             WHERE g.event_id = ?
             ORDER BY g.created_at DESC
             LIMIT ' . (int) $limit,
            [$eventId]
        );
    }

    /** Every attendee in a given group. */
    public function attendeesOf(int $groupId): array
    {
        return $this->fetchAll(
            'SELECT id, name, ic_no, contact_no, checked_in_at
             FROM rsvp_attendees WHERE group_id = ? ORDER BY id',
            [$groupId]
        );
    }

    // ------------------------------------------------------------------
    // On-site check-in
    // ------------------------------------------------------------------

    /**
     * Look up a registration by its reference code, scoped to one event.
     *
     * The event scope matters: codes restart per year once test data is
     * cleared, so RSVP-0001 can exist for both 2026 and 2027. Without
     * the scope the counter could check in last year's family.
     */
    public function findByRefCode(string $refCode, int $eventId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM rsvp_groups WHERE ref_code = ? AND event_id = ?',
            [strtoupper(trim($refCode)), $eventId]
        );
    }

    /** Mark one attendee as arrived. Idempotent — scanning twice is harmless. */
    public function checkIn(int $attendeeId): bool
    {
        return $this->execute(
            'UPDATE rsvp_attendees SET checked_in_at = NOW()
             WHERE id = ? AND checked_in_at IS NULL',
            [$attendeeId]
        ) > 0;
    }

    /** Undo a check-in, for the inevitable wrong tap. */
    public function undoCheckIn(int $attendeeId): bool
    {
        return $this->execute(
            'UPDATE rsvp_attendees SET checked_in_at = NULL WHERE id = ?',
            [$attendeeId]
        ) > 0;
    }

    /** Check in everyone in a group who has not already arrived. */
    public function checkInGroup(int $groupId): int
    {
        return $this->execute(
            'UPDATE rsvp_attendees SET checked_in_at = NOW()
             WHERE group_id = ? AND checked_in_at IS NULL',
            [$groupId]
        );
    }

    /** Which event an attendee belongs to — guards the check-in actions. */
    public function eventIdOfAttendee(int $attendeeId): ?int
    {
        $row = $this->fetchOne(
            'SELECT g.event_id FROM rsvp_attendees a
             JOIN rsvp_groups g ON g.id = a.group_id
             WHERE a.id = ?',
            [$attendeeId]
        );
        return $row === null ? null : (int) $row['event_id'];
    }

    /** How many people have actually arrived. */
    public function totalCheckedIn(int $eventId): int
    {
        return (int) $this->scalar(
            "SELECT COUNT(*) FROM rsvp_attendees a
             JOIN rsvp_groups g ON g.id = a.group_id
             WHERE g.event_id = ? AND g.status <> 'cancelled'
               AND a.checked_in_at IS NOT NULL",
            [$eventId]
        );
    }

    /** The most recent arrivals, for reassurance on the check-in screen. */
    public function recentCheckIns(int $eventId, int $limit = 8): array
    {
        return $this->fetchAll(
            "SELECT a.name, a.checked_in_at, g.ref_code
             FROM rsvp_attendees a
             JOIN rsvp_groups g ON g.id = a.group_id
             WHERE g.event_id = ? AND a.checked_in_at IS NOT NULL
             ORDER BY a.checked_in_at DESC
             LIMIT " . (int) $limit,
            [$eventId]
        );
    }

    /** Move a group to confirmed / cancelled. */
    public function setStatus(int $groupId, string $status): bool
    {
        if (!in_array($status, ['pending', 'confirmed', 'cancelled'], true)) {
            return false;
        }
        return $this->execute(
            'UPDATE rsvp_groups SET status = ? WHERE id = ?',
            [$status, $groupId]
        ) > 0;
    }

    /** Head count for one event, excluding cancelled registrations. */
    public function totalAttendees(int $eventId): int
    {
        return (int) $this->scalar(
            "SELECT COUNT(*) FROM rsvp_attendees a
             JOIN rsvp_groups g ON g.id = a.group_id
             WHERE g.event_id = ? AND g.status <> 'cancelled'",
            [$eventId]
        );
    }

    /** Number of registration submissions for one event, excluding cancelled. */
    public function totalGroups(int $eventId): int
    {
        return (int) $this->scalar(
            "SELECT COUNT(*) FROM rsvp_groups WHERE event_id = ? AND status <> 'cancelled'",
            [$eventId]
        );
    }

    /**
     * Every attendee for an event as a flat list, for the counter's
     * printed check-in sheet.
     *
     * Sorted by NAME, not by reference code. At the counter a person
     * says their name — nobody arrives quoting RSVP-0042 — so the sheet
     * has to be scannable the way it will actually be used.
     *
     * Cancelled registrations are left out: they should not have a tick
     * box waiting for them.
     */
    public function attendeeSheet(int $eventId): array
    {
        return $this->fetchAll(
            "SELECT a.name, a.ic_no, a.contact_no, a.checked_in_at,
                    g.ref_code, g.status, g.source, g.attendee_count
             FROM rsvp_attendees a
             JOIN rsvp_groups g ON g.id = a.group_id
             WHERE g.event_id = ? AND g.status <> 'cancelled'
             ORDER BY a.name, g.ref_code",
            [$eventId]
        );
    }

    /**
     * Which event a group belongs to — used by admin actions to confirm
     * the row being changed really belongs to the event on screen.
     */
    public function eventIdOf(int $groupId): ?int
    {
        $row = $this->fetchOne('SELECT event_id FROM rsvp_groups WHERE id = ?', [$groupId]);
        return $row === null ? null : (int) $row['event_id'];
    }
}
