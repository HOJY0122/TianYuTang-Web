<?php
namespace App\Models;

use App\Core\Crypto;
use App\Core\Model;

/**
 * Zai — 施齋 Day 3: the hall's tables and who sits where.
 *
 * Three kinds of guests share the tables:
 *   registered  people who registered online (rsvp_attendees), confirmed
 *               or pending on the day, one seat each        → zai_people
 *   團體 groups  organisation registrations and the PM's own list; a
 *               group may take several tables              → zai_groups
 *   候補 waiting people who came without registering; onsite staff
 *               seat them later                            → zai_waiting
 *
 * Seats are counted per table so staff can see what is still free. A
 * group spread over several tables fills them in the order given.
 */
class Zai extends Model
{
    public const DEFAULT_SEATS = 10;
    public const MAX_TABLES    = 300;

    // ----------------------------------------------------------- tables

    /** table_no => seats, in number order. */
    public function tables(int $eventId): array
    {
        $out = [];
        foreach ($this->fetchAll('SELECT table_no, seats FROM zai_tables WHERE event_id = ? ORDER BY table_no', [$eventId]) as $r) {
            $out[(int) $r['table_no']] = (int) $r['seats'];
        }
        return $out;
    }

    /**
     * Set the hall: tables 1…$count with $seats each. Tables that already
     * exist keep their own seat count unless $resetSeats; tables above
     * $count are removed (people at them keep the number, shown as "no
     * such table" until moved).
     */
    public function setTables(int $eventId, int $count, int $seats, bool $resetSeats): void
    {
        $count = max(0, min(self::MAX_TABLES, $count));
        $seats = max(1, min(50, $seats));
        $this->db->beginTransaction();
        try {
            $this->execute('DELETE FROM zai_tables WHERE event_id = ? AND table_no > ?', [$eventId, $count]);
            for ($n = 1; $n <= $count; $n++) {
                $this->execute(
                    'INSERT INTO zai_tables (event_id, table_no, seats) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE seats = ' . ($resetSeats ? 'VALUES(seats)' : 'seats'),
                    [$eventId, $n, $seats]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** table_no => 'open' (散客桌) | 'group' (團體桌). */
    public function tableKinds(int $eventId): array
    {
        $out = [];
        foreach ($this->fetchAll('SELECT table_no, kind FROM zai_tables WHERE event_id = ?', [$eventId]) as $r) {
            $out[(int) $r['table_no']] = $r['kind'];
        }
        return $out;
    }

    public function setTableKind(int $eventId, int $tableNo, string $kind): void
    {
        $this->execute('UPDATE zai_tables SET kind = ? WHERE event_id = ? AND table_no = ?', [$kind === 'group' ? 'group' : 'open', $eventId, $tableNo]);
    }

    /** Pre-assign (or take away) one table for a 團體 group; its other tables stay. */
    public function groupTable(int $groupId, int $tableNo, bool $add): void
    {
        $g = $this->group($groupId);
        if ($g === null) {
            return;
        }
        $list = self::tableList((string) ($g['tables'] ?? ''));
        $list = $add ? array_values(array_unique(array_merge($list, [$tableNo]))) : array_values(array_diff($list, [$tableNo]));
        $this->execute('UPDATE zai_groups SET tables = ? WHERE id = ?', [$list ? implode(', ', $list) : null, $groupId]);
    }

    public function setTableSeats(int $eventId, int $tableNo, int $seats): void
    {
        $this->execute('UPDATE zai_tables SET seats = ? WHERE event_id = ? AND table_no = ?', [max(1, min(50, $seats)), $eventId, $tableNo]);
    }

    /**
     * Who sits at each table: table_no => ['used' => n, 'who' => [[kind, label, n], …]].
     * Includes table numbers in use that are not (or no longer) set up.
     */
    public function seating(int $eventId): array
    {
        $tables = $this->tables($eventId);
        $kinds = $this->tableKinds($eventId);
        $map = [];
        foreach ($tables as $no => $seats) {
            $map[$no] = ['seats' => $seats, 'used' => 0, 'who' => [], 'kind' => $kinds[$no] ?? 'open', 'reserved' => 0];
        }
        // who: [kind, label, people, extra] — extra for a group: its id and whether it has arrived.
        $add = static function (int $no, string $kind, string $label, int $n, array $extra = []) use (&$map): void {
            $map[$no] ??= ['seats' => 0, 'used' => 0, 'who' => [], 'kind' => 'open', 'reserved' => 0];
            $map[$no]['used'] += $n;
            $map[$no]['who'][] = [$kind, $label, $n, $extra];
            if ($kind === 'group' && empty($extra['arrived'])) {
                $map[$no]['reserved'] += $n;          // pre-assigned, not here yet
            }
        };
        foreach ($this->fetchAll(
            'SELECT p.table_no, a.name, g.ref_code FROM zai_people p
               JOIN rsvp_attendees a ON a.id = p.attendee_id JOIN rsvp_groups g ON g.id = a.group_id
              WHERE p.event_id = ? AND p.table_no IS NOT NULL ORDER BY a.id', [$eventId]) as $r) {
            $add((int) $r['table_no'], 'person', $r['name'] . ' · ' . $r['ref_code'], 1);
        }
        foreach ($this->groups($eventId) as $g) {
            foreach (self::spread($g, $tables) as $no => $n) {
                $add($no, 'group', $g['name'], $n, ['id' => (int) $g['id'], 'arrived' => $g['status'] === 'arrived']);
            }
        }
        foreach ($this->fetchAll("SELECT table_no, name, queue_no, people FROM zai_waiting WHERE event_id = ? AND status = 'seated' AND table_no IS NOT NULL", [$eventId]) as $r) {
            $add((int) $r['table_no'], 'waiting', $r['name'] . ' · #' . $r['queue_no'], (int) $r['people']);
        }
        ksort($map);
        return $map;
    }

    /** A group's people spread over its tables, filling each in turn: table_no => people. */
    public static function spread(array $group, array $tables): array
    {
        $nos = self::tableList((string) ($group['tables'] ?? ''));
        $left = (int) $group['people'];
        $out = [];
        foreach ($nos as $i => $no) {
            $room = $tables[$no] ?? self::DEFAULT_SEATS;
            $n = $i === count($nos) - 1 ? $left : min($room, $left);
            $out[$no] = max(0, $n);
            $left -= $out[$no];
        }
        return $out;
    }

    /** "5, 6,7 " → [5, 6, 7] (numbers only, no repeats). */
    public static function tableList(string $s): array
    {
        preg_match_all('/\d{1,4}/', $s, $m);
        return array_values(array_unique(array_map('intval', $m[0])));
    }

    /** Seats used / total, and how many tables have room. */
    public function totals(int $eventId): array
    {
        $seats = $used = $free = 0;
        foreach ($this->seating($eventId) as $t) {
            $seats += $t['seats'];
            $used += $t['used'];
            $free += $t['seats'] > $t['used'] ? 1 : 0;
        }
        $p = $this->fetchOne("SELECT COALESCE(SUM(status = 'confirmed'), 0) AS confirmed, COALESCE(SUM(status = 'pending'), 0) AS pending
                                FROM zai_people WHERE event_id = ?", [$eventId]);
        $g = $this->fetchOne("SELECT COUNT(*) AS n, COALESCE(SUM(status = 'arrived'), 0) AS arrived FROM zai_groups WHERE event_id = ?", [$eventId]);
        $w = $this->fetchOne("SELECT COALESCE(SUM(status = 'waiting'), 0) AS waiting, COALESCE(SUM(CASE WHEN status = 'waiting' THEN people ELSE 0 END), 0) AS waiting_people
                                FROM zai_waiting WHERE event_id = ?", [$eventId]);
        return ['seats' => $seats, 'used' => $used, 'tables_free' => $free] + $p + ['groups' => $g['n'], 'groups_arrived' => $g['arrived']] + $w
            + ['reserved' => $this->reserved($eventId)];
    }

    /**
     * 預約總人數 Total people reservation, by where it came from:
     * QR website, WhatsApp (keyed in), registered at the counter, 團體
     * groups (organisation registrations + the PM's list), and the
     * waiting list (not counting those who left).
     */
    public function reserved(int $eventId): array
    {
        $by = ['online' => 0, 'whatsapp' => 0, 'walkin' => 0];
        foreach ($this->fetchAll(
            "SELECT g.source, COUNT(a.id) AS n FROM rsvp_groups g JOIN rsvp_attendees a ON a.group_id = g.id
              WHERE g.event_id = ? AND g.status <> 'cancelled' AND g.reg_type = 'individual' GROUP BY g.source", [$eventId]) as $r) {
            $by[$r['source']] = (int) $r['n'];
        }
        $this->syncOrganisations($eventId);
        $by['groups'] = (int) $this->scalar(
            "SELECT COALESCE(SUM(z.people), 0) FROM zai_groups z LEFT JOIN rsvp_groups g ON g.id = z.rsvp_group_id
              WHERE z.event_id = ? AND (g.id IS NULL OR g.status <> 'cancelled')", [$eventId]);
        $by['waiting'] = (int) $this->scalar("SELECT COALESCE(SUM(people), 0) FROM zai_waiting WHERE event_id = ? AND status <> 'left'", [$eventId]);
        $by['total'] = array_sum($by);
        return $by;
    }

    // ------------------------------------------------- registered people

    /** Day 3 status of these attendees: attendee_id => row. */
    public function peopleStatus(array $attendeeIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $attendeeIds)));
        if (!$ids) {
            return [];
        }
        $out = [];
        foreach ($this->fetchAll('SELECT * FROM zai_people WHERE attendee_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids) as $r) {
            $out[(int) $r['attendee_id']] = $r;
        }
        return $out;
    }

    public function setPerson(int $eventId, int $attendeeId, string $status, ?int $tableNo, string $by): void
    {
        $this->execute(
            'INSERT INTO zai_people (attendee_id, event_id, status, table_no, updated_by) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), table_no = VALUES(table_no), updated_by = VALUES(updated_by)',
            [$attendeeId, $eventId, $status, $tableNo, $by]);
    }

    public function clearPerson(int $attendeeId): void
    {
        $this->execute('DELETE FROM zai_people WHERE attendee_id = ?', [$attendeeId]);
    }

    /** The attendee's event (so a crafted POST cannot seat someone from another event). */
    public function attendeeEvent(int $attendeeId): ?int
    {
        $e = $this->scalar('SELECT g.event_id FROM rsvp_attendees a JOIN rsvp_groups g ON g.id = a.group_id WHERE a.id = ?', [$attendeeId]);
        return $e === false ? null : (int) $e;
    }

    // ------------------------------------------------------------ groups

    /**
     * 團體 groups for the event. Organisation registrations are added on
     * first look (and their size kept in step), so the list is complete
     * without anyone copying them over.
     */
    public function groups(int $eventId, string $q = ''): array
    {
        $this->syncOrganisations($eventId);
        $sql = "SELECT z.*, g.ref_code, g.status AS rsvp_status FROM zai_groups z LEFT JOIN rsvp_groups g ON g.id = z.rsvp_group_id
                 WHERE z.event_id = ? AND (g.id IS NULL OR g.status <> 'cancelled')";
        $params = [$eventId];
        if (trim($q) !== '') {
            $like = '%' . addcslashes(trim($q), '%_\\') . '%';
            $sql .= ' AND (z.name LIKE ? OR z.contact LIKE ? OR z.list_no LIKE ? OR g.ref_code LIKE ? OR z.tables LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        return $this->fetchAll($sql . " ORDER BY z.status = 'arrived', z.source, z.id", $params);
    }

    /**
     * Copy new organisation registrations in and keep their size in step.
     * Written without execute(): open pages are only told to refresh
     * (Live) when something really changed — reading the list must never
     * set off a refresh loop.
     */
    private function syncOrganisations(int $eventId): void
    {
        $changed = 0;
        foreach ([
            "INSERT IGNORE INTO zai_groups (event_id, source, rsvp_group_id, name, contact, people)
             SELECT g.event_id, 'rsvp', g.id, COALESCE(NULLIF(g.org_name, ''), g.ref_code),
                    (SELECT contact_no FROM rsvp_attendees WHERE group_id = g.id ORDER BY id LIMIT 1), g.attendee_count
               FROM rsvp_groups g
              WHERE g.event_id = ? AND g.reg_type = 'organisation' AND g.status <> 'cancelled'",
            "UPDATE zai_groups z JOIN rsvp_groups g ON g.id = z.rsvp_group_id
                SET z.people = g.attendee_count, z.name = COALESCE(NULLIF(g.org_name, ''), g.ref_code)
              WHERE z.event_id = ? AND (z.people <> g.attendee_count OR z.name <> COALESCE(NULLIF(g.org_name, ''), g.ref_code))",
        ] as $sql) {
            $st = $this->db->prepare($sql);
            $st->execute([$eventId]);
            $changed += $st->rowCount();
        }
        if ($changed) {
            \App\Core\Live::touch('zai_groups');
        }
    }

    public function group(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM zai_groups WHERE id = ?', [$id]);
    }

    /** Add the PM's list. Returns how many were added. */
    public function importGroups(int $eventId, array $rows): int
    {
        foreach ($rows as $r) {
            $this->execute('INSERT INTO zai_groups (event_id, source, list_no, name, contact, people, notes) VALUES (?, \'list\', ?, ?, ?, ?, ?)',
                [$eventId, $r['list_no'], $r['name'], $r['contact'], $r['people'], $r['notes']]);
        }
        return count($rows);
    }

    public function saveGroup(int $id, array $v): void
    {
        $this->execute(
            'UPDATE zai_groups SET people = ?, tables = ?, cheque_no = ?, notes = ?, status = ?,
                    arrived_at = IF(? = \'arrived\', COALESCE(arrived_at, NOW()), NULL),
                    arrived_by = IF(? = \'arrived\', COALESCE(arrived_by, ?), NULL)
              WHERE id = ?',
            [$v['people'], $v['tables'], $v['cheque_no'], $v['notes'], $v['status'], $v['status'], $v['status'], $v['by'], $id]);
    }

    public function setCheque(int $id, ?string $path): void
    {
        $this->execute('UPDATE zai_groups SET cheque_path = ? WHERE id = ?', [$path, $id]);
    }

    public function deleteGroup(int $id): void
    {
        $this->execute("DELETE FROM zai_groups WHERE id = ? AND source = 'list'", [$id]);
    }

    /**
     * Rows from the PM's spreadsheet → groups. Columns are found by their
     * heading (團體 / Name, 人數 / People, 聯絡 / Contact, 編號 / No., 備註 /
     * Notes); with no heading row: Name, People, Contact, Notes.
     */
    public static function groupsFromRows(array $rows): array
    {
        $norm = static fn($s): string => preg_replace('/[\s._\-:：()（）#]+/u', '', mb_strtolower(trim((string) $s)));
        $want = [
            'name'    => ['團體名稱', '团体名称', '團體', '团体', '名稱', '名称', 'groupname', 'group', 'organisation', 'organization', 'name', '姓名'],
            'people'  => ['人數', '人数', 'people', 'pax', 'persons', 'count', 'qty'],
            'contact' => ['聯絡電話', '联络电话', '聯絡', '联络', '電話', '电话', 'contact', 'phone', 'tel'],
            'list_no' => ['編號', '编号', '序號', '序号', 'no', 'bil', 'number'],
            'notes'   => ['備註', '备注', 'notes', 'remarks', 'remark'],
        ];
        $col = array_fill_keys(array_keys($want), -1);
        $start = 0;
        foreach (array_slice($rows, 0, 15, true) as $i => $row) {
            $head = array_map($norm, $row);
            $found = 0;
            foreach ($want as $key => $names) {
                foreach ($head as $j => $h) {
                    if ($h !== '' && in_array($h, $names, true) && !in_array($j, $col, true)) {
                        $col[$key] = $j;
                        $found++;
                        break;
                    }
                }
            }
            if ($col['name'] >= 0 && $found >= 2) {
                $start = $i + 1;
                break;
            }
            $col = array_fill_keys(array_keys($want), -1);
        }
        if ($col['name'] < 0) {
            $col = ['name' => 0, 'people' => 1, 'contact' => 2, 'notes' => 3, 'list_no' => -1];
        }
        $cell = static fn(array $r, int $j, int $max): ?string =>
            $j >= 0 && ($v = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($r[$j] ?? ''))), 0, $max)) !== '' ? $v : null;
        $out = [];
        foreach (array_slice($rows, $start) as $r) {
            $name = $cell($r, $col['name'], 150);
            if ($name === null) {
                continue;
            }
            $people = (int) preg_replace('/\D/', '', (string) $cell($r, $col['people'], 6));
            $out[] = ['name' => $name, 'people' => max(0, min(5000, $people)), 'contact' => $cell($r, $col['contact'], 60),
                      'list_no' => $cell($r, $col['list_no'], 20), 'notes' => $cell($r, $col['notes'], 500)];
        }
        return $out;
    }

    // ----------------------------------------------------------- waiting

    public function waiting(int $eventId): array
    {
        return $this->fetchAll("SELECT * FROM zai_waiting WHERE event_id = ? ORDER BY status = 'waiting' DESC, queue_no", [$eventId]);
    }

    public function waitingRow(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM zai_waiting WHERE id = ?', [$id]);
    }

    /** Add someone to the waiting list; returns their queue number. */
    public function addWaiting(int $eventId, array $v, string $by): int
    {
        $this->db->beginTransaction();
        try {
            $next = (int) $this->scalar('SELECT COALESCE(MAX(queue_no), 0) + 1 FROM zai_waiting WHERE event_id = ? FOR UPDATE', [$eventId]);
            $norm = Rsvp::icNormal((string) $v['ic']);
            $extra = [];
            foreach ($v['extra_ics'] ?? [] as $other) {
                $o = Rsvp::icNormal((string) $other);
                if ($o !== '' && $o !== $norm) {
                    $extra[$o] = ['h' => Crypto::blindIndex($o), 'l' => substr($o, -4)];
                }
            }
            $this->execute(
                'INSERT INTO zai_waiting (event_id, queue_no, name, ic_hash, ic_last4, extra_ics, contact, people, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$eventId, $next, $v['name'], $norm !== '' ? Crypto::blindIndex($norm) : null, $norm !== '' ? substr($norm, -4) : null,
                 $extra ? json_encode(array_values($extra)) : null, $v['contact'], $v['people'], $v['notes'], $by]);
            $this->db->commit();
            return $next;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateWaiting(int $id, string $status, ?int $tableNo): void
    {
        $this->execute('UPDATE zai_waiting SET status = ?, table_no = ?, seated_at = IF(? = \'seated\', COALESCE(seated_at, NOW()), NULL) WHERE id = ?',
            [$status, $status === 'seated' ? $tableNo : null, $status, $id]);
    }

    /**
     * A walk-in (waiting list) is one person with one IC, checked like the
     * online form: a valid IC / passport and the same age rule.
     * Returns the problem to show, or null when the IC is fine.
     */
    public static function walkInIcProblem(string $ic, array $event): ?string
    {
        if (trim($ic) === '') {
            return '請輸入身份證號碼。Please type the IC number.';
        }
        $ok = \App\Core\Validate::icOrPassport($ic);
        if ($ok === null) {
            return \App\Core\Validate::IC_MESSAGE;
        }
        $rule = \App\Core\FormRules::ageRule();
        if ($rule !== null && ($problem = \App\Core\FormRules::groupAgeProblem([$ok], $rule, $event)) !== null) {
            return str_replace(['第 1 位：', 'Person 1: '], '', $problem);
        }
        return null;
    }

    /** Last 4 digits of every IC on a waiting row: the lead's first, then the others'. */
    public static function waitingLast4(array $row): array
    {
        $out = $row['ic_last4'] ? [$row['ic_last4']] : [];
        foreach (json_decode((string) ($row['extra_ics'] ?? ''), true) ?: [] as $x) {
            $out[] = (string) ($x['l'] ?? '');
        }
        return array_values(array_filter($out, 'strlen'));
    }

    /** Is this IC already registered online for the event? [group ref, name] or null. */
    public function registeredIc(int $eventId, string $ic): ?array
    {
        $norm = Rsvp::icNormal($ic);
        if (strlen($norm) < 6) {
            return null;
        }
        return $this->fetchOne(
            "SELECT g.ref_code, a.name FROM rsvp_attendees a JOIN rsvp_groups g ON g.id = a.group_id
              WHERE g.event_id = ? AND g.status <> 'cancelled' AND a.ic_hash = ? LIMIT 1", [$eventId, Crypto::blindIndex($norm)]);
    }
}
