<?php
namespace App\Models;

use App\Core\Model;

/**
 * Donation — merit seats (功德席), freewill giving (隨喜), or both at once.
 *
 * A donation is two numbers: how many seats, and how much freewill.
 * The method is DERIVED from them rather than chosen separately:
 *
 *   seats only        → 'table'
 *   freewill only     → 'free'
 *   both              → 'mixed'   (e.g. 2 seats + RM 50)
 *
 * The amount is ALWAYS calculated here from the event's own seat price,
 * never taken from the browser, so a visitor cannot edit the page and
 * submit their own total. Every donation belongs to one event.
 */
class Donation extends Model
{
    public const MAX_SEATS    = 200;
    public const MAX_FREEWILL = 1000000;

    /**
     * Turn "how many seats, how much freewill" into the stored columns.
     *
     * @return array{method:string, table_count:?int, free_amount:?float, amount:float}
     */
    public static function compose(int $seats, float $freeAmount, float $seatPrice): array
    {
        $seats      = max(0, $seats);
        $freeAmount = max(0.0, round($freeAmount, 2));

        $method = match (true) {
            $seats > 0 && $freeAmount > 0 => 'mixed',
            $seats > 0                    => 'table',
            default                       => 'free',
        };

        return [
            'method'      => $method,
            'table_count' => $seats > 0 ? $seats : null,
            'free_amount' => $freeAmount > 0 ? $freeAmount : null,
            'amount'      => round($seats * $seatPrice + $freeAmount, 2),
        ];
    }

    /**
     * Check the two numbers a donor or volunteer typed.
     *
     * @return string[] bilingual problems; empty means acceptable
     */
    public static function validateParts(int $seats, float $freeAmount): array
    {
        $errors = [];
        if ($seats < 0 || $seats > self::MAX_SEATS) {
            $errors[] = '功德席數量不正確（1–' . self::MAX_SEATS . '）。Invalid number of merit seats.';
        }
        if ($freeAmount < 0 || $freeAmount > self::MAX_FREEWILL) {
            $errors[] = '隨喜金額不正確。Invalid freewill amount.';
        }
        if ($seats <= 0 && round($freeAmount, 2) <= 0) {
            $errors[] = '請選擇功德席或輸入隨喜金額。Please choose merit seats or enter a freewill amount.';
        }
        return $errors;
    }

    /** One-line bilingual description, e.g. "功德席 2 席 + 隨喜 RM 50.00". */
    public static function describe(array $row): string
    {
        $parts = [];
        if (!empty($row['table_count'])) {
            $parts[] = '功德席 ' . (int) $row['table_count'] . ' 席 Seats';
        }
        $free = $row['free_amount'] ?? ($row['method'] === 'free' ? $row['amount'] : null);
        if ($free !== null && (float) $free > 0) {
            $parts[] = '隨喜 Freewill ' . rm((float) $free);
        }
        return $parts ? implode(' + ', $parts) : '—';
    }

    /**
     * Record an online donation.
     *
     * @return array{ref_code:string, amount:float, method:string}
     */
    public function create(
        int $eventId,
        string $name,
        string $contact,
        int $seats,
        float $freeAmount,
        float $seatPrice,
        string $refPrefix = 'DON'
    ): array {
        $c = self::compose($seats, $freeAmount, $seatPrice);

        $this->execute(
            'INSERT INTO donations (event_id, name, contact_no, method, table_count, free_amount, amount, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$eventId, $name, $contact, $c['method'], $c['table_count'], $c['free_amount'], $c['amount'], 'pending']
        );
        $id = (int) $this->db->lastInsertId();

        return [
            'ref_code' => $this->assignRefCode('donations', $refPrefix, $id),
            'amount'   => $c['amount'],
            'method'   => $c['method'],
        ];
    }

    /**
     * Record a donation taken at the counter.
     *
     * Kept separate from create() on purpose. A counter donation is a
     * different act: an admin is recording money already in hand, so it
     * is marked paid immediately, tagged as cash, and stamped with who
     * entered it — cash without an owner is how money goes missing.
     *
     * @return array{ref_code:string, amount:float, method:string}
     */
    public function createAtCounter(
        int $eventId,
        string $name,
        string $contact,
        int $seats,
        float $freeAmount,
        float $seatPrice,
        string $recordedBy,
        ?string $receiptPath = null,
        ?string $notes = null,
        string $refPrefix = 'DON'
    ): array {
        $c = self::compose($seats, $freeAmount, $seatPrice);

        $this->execute(
            'INSERT INTO donations
             (event_id, source, name, contact_no, method, table_count, free_amount, amount, status,
              receipt_path, recorded_by, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$eventId, 'counter', $name, $contact, $c['method'], $c['table_count'], $c['free_amount'],
             $c['amount'], 'paid', $receiptPath, $recordedBy, $notes]
        );
        $id = (int) $this->db->lastInsertId();

        return [
            'ref_code' => $this->assignRefCode('donations', $refPrefix, $id),
            'amount'   => $c['amount'],
            'method'   => $c['method'],
        ];
    }

    /**
     * Edit a donation from the admin records page.
     *
     * The amount is recalculated from seats + freewill with the event's
     * CURRENT seat price, exactly as a new donation would be, so an
     * edited row can never carry a total that its parts do not add up to.
     */
    public function update(int $id, array $fields, float $seatPrice): bool
    {
        $c = self::compose((int) $fields['seats'], (float) $fields['free_amount'], $seatPrice);

        $this->execute(
            'UPDATE donations
                SET name = ?, contact_no = ?, method = ?, table_count = ?, free_amount = ?,
                    amount = ?, status = ?, notes = ?
              WHERE id = ?',
            [$fields['name'], $fields['contact'], $c['method'], $c['table_count'], $c['free_amount'],
             $c['amount'], $fields['status'] === 'paid' ? 'paid' : 'pending', $fields['notes'], $id]
        );
        return $this->find($id) !== null;
    }

    /** Donations for one event, newest first. */
    public function all(int $eventId, int $limit = 100): array
    {
        return $this->search($eventId, [], $limit);
    }

    /**
     * Donations for one event, filtered, newest first.
     *
     * @param array{q?:string, status?:string, source?:string} $filters
     */
    public function search(int $eventId, array $filters, int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->filterSql($eventId, $filters);
        return $this->fetchAll(
            'SELECT id, ref_code, source, name, contact_no, method, table_count, free_amount, amount,
                    status, receipt_path, recorded_by, notes, created_at
             FROM donations WHERE ' . $where . '
             ORDER BY created_at DESC, id DESC
             LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /** How many rows search() would return without a limit. */
    public function countSearch(int $eventId, array $filters): int
    {
        [$where, $params] = $this->filterSql($eventId, $filters);
        return (int) $this->scalar('SELECT COUNT(*) FROM donations WHERE ' . $where, $params);
    }

    /** @return array{0:string, 1:array} */
    private function filterSql(int $eventId, array $filters): array
    {
        $where  = ['event_id = ?'];
        $params = [$eventId];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[]  = '(name LIKE ? OR contact_no LIKE ? OR ref_code LIKE ?)';
            $like     = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        if (in_array($filters['status'] ?? '', ['pending', 'paid'], true)) {
            $where[]  = 'status = ?';
            $params[] = $filters['status'];
        }
        if (in_array($filters['source'] ?? '', ['online', 'counter'], true)) {
            $where[]  = 'source = ?';
            $params[] = $filters['source'];
        }
        return [implode(' AND ', $where), $params];
    }

    /**
     * Totals split by where the money came from, so the treasurer can
     * reconcile the cash box against the counter figure without doing
     * the subtraction by hand.
     *
     * @return array{online:float, counter:float, counter_count:int}
     */
    public function totalsBySource(int $eventId): array
    {
        $rows = $this->fetchAll(
            'SELECT source, COALESCE(SUM(amount),0) AS total, COUNT(*) AS n
             FROM donations WHERE event_id = ? GROUP BY source',
            [$eventId]
        );

        $out = ['online' => 0.0, 'counter' => 0.0, 'counter_count' => 0];
        foreach ($rows as $r) {
            if ($r['source'] === 'counter') {
                $out['counter']       = (float) $r['total'];
                $out['counter_count'] = (int) $r['n'];
            } else {
                $out['online'] = (float) $r['total'];
            }
        }
        return $out;
    }

    /**
     * Money split by kind: how much came from seats and how much from
     * freewill. A mixed donation counts in both, each for its own part.
     *
     * @return array{seats:float, freewill:float}
     */
    public function totalsByKind(int $eventId): array
    {
        $row = $this->fetchOne(
            'SELECT COALESCE(SUM(amount - COALESCE(free_amount, 0)), 0) AS seats,
                    COALESCE(SUM(COALESCE(free_amount, 0)), 0)          AS freewill
             FROM donations WHERE event_id = ?',
            [$eventId]
        );
        return ['seats' => (float) $row['seats'], 'freewill' => (float) $row['freewill']];
    }

    /**
     * Money per day, for the dashboard chart.
     *
     * @return array<int, array{day:string, total:float, n:int}>
     */
    public function dailyTotals(int $eventId, int $days = 30): array
    {
        return array_map(
            static fn($r) => ['day' => $r['day'], 'total' => (float) $r['total'], 'n' => (int) $r['n']],
            $this->fetchAll(
                'SELECT DATE(created_at) AS day, SUM(amount) AS total, COUNT(*) AS n
                 FROM donations
                 WHERE event_id = ? AND created_at >= CURDATE() - INTERVAL ' . (int) $days . ' DAY
                 GROUP BY DATE(created_at) ORDER BY day',
                [$eventId]
            )
        );
    }

    /** A single donation, or null. */
    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM donations WHERE id = ?', [$id]);
    }

    /** Mark a donation as paid. */
    public function markPaid(int $id): bool
    {
        return $this->execute("UPDATE donations SET status = 'paid' WHERE id = ?", [$id]) > 0;
    }

    /** Everything pledged for one event. */
    public function totalAmount(int $eventId): float
    {
        return (float) $this->scalar(
            'SELECT COALESCE(SUM(amount), 0) FROM donations WHERE event_id = ?',
            [$eventId]
        );
    }

    /** Everything actually received for one event. */
    public function totalPaid(int $eventId): float
    {
        return (float) $this->scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM donations WHERE event_id = ? AND status = 'paid'",
            [$eventId]
        );
    }

    /** Merit seats sponsored for one event — seat-only AND mixed donations. */
    public function totalTables(int $eventId): int
    {
        return (int) $this->scalar(
            'SELECT COALESCE(SUM(table_count), 0) FROM donations WHERE event_id = ?',
            [$eventId]
        );
    }

    /** How many donations an event has received. */
    public function countFor(int $eventId): int
    {
        return (int) $this->scalar('SELECT COUNT(*) FROM donations WHERE event_id = ?', [$eventId]);
    }

    /** Which event a donation belongs to. */
    public function eventIdOf(int $id): ?int
    {
        $row = $this->fetchOne('SELECT event_id FROM donations WHERE id = ?', [$id]);
        return $row === null ? null : (int) $row['event_id'];
    }
}
