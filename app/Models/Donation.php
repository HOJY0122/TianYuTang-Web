<?php
namespace App\Models;

use App\Core\Model;

/**
 * Donation — freewill donations and merit-seat (功德席) sponsorships.
 *
 * The amount is ALWAYS calculated here from the event's own price,
 * never taken from the browser, so a visitor cannot edit the page and
 * submit their own total. Every donation belongs to one event.
 */
class Donation extends Model
{
    /**
     * Record a donation.
     *
     * @param int      $eventId    the event being donated to
     * @param string   $method     'free' or 'table'
     * @param float    $freeAmount amount entered, used when $method = 'free'
     * @param int|null $tableCount number of seats, used when $method = 'table'
     * @param float    $seatPrice  price per merit seat, taken from the event row
     * @return array{ref_code:string, amount:float}
     */
    public function create(
        int $eventId,
        string $name,
        string $contact,
        string $method,
        float $freeAmount = 0.0,
        ?int $tableCount = null,
        float $seatPrice = 500.0,
        string $refPrefix = 'DON'
    ): array {
        if ($method === 'table') {
            $amount = $tableCount * $seatPrice;   // server-side price, from the event
        } else {
            $amount = $freeAmount;
            $tableCount = null;
        }

        $this->execute(
            'INSERT INTO donations (event_id, name, contact_no, method, table_count, amount, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$eventId, $name, $contact, $method, $tableCount, $amount, 'pending']
        );
        $id = (int) $this->db->lastInsertId();
        $refCode = $this->assignRefCode('donations', $refPrefix, $id);

        return ['ref_code' => $refCode, 'amount' => (float) $amount];
    }

    /**
     * Record a donation taken at the counter.
     *
     * Kept separate from create() on purpose. A counter donation is a
     * different act: an admin is recording money already in hand, so it
     * is marked paid immediately, tagged as cash, and stamped with who
     * entered it — cash without an owner is how money goes missing.
     *
     * @return array{ref_code:string, amount:float}
     */
    public function createAtCounter(
        int $eventId,
        string $name,
        string $contact,
        string $method,
        float $freeAmount,
        ?int $tableCount,
        float $seatPrice,
        string $recordedBy,
        ?string $receiptPath = null,
        ?string $notes = null,
        string $refPrefix = 'DON'
    ): array {
        if ($method === 'table') {
            $amount = $tableCount * $seatPrice;
        } else {
            $amount = $freeAmount;
            $tableCount = null;
        }

        $this->execute(
            'INSERT INTO donations
             (event_id, source, name, contact_no, method, table_count, amount, status,
              receipt_path, recorded_by, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$eventId, 'counter', $name, $contact, $method, $tableCount, $amount, 'paid',
             $receiptPath, $recordedBy, $notes]
        );

        $id = (int) $this->db->lastInsertId();
        $refCode = $this->assignRefCode('donations', $refPrefix, $id);

        return ['ref_code' => $refCode, 'amount' => (float) $amount];
    }

    /** Donations for one event, newest first. */
    public function all(int $eventId, int $limit = 100): array
    {
        return $this->fetchAll(
            'SELECT id, ref_code, source, name, contact_no, method, table_count, amount,
                    status, receipt_path, recorded_by, notes, created_at
             FROM donations WHERE event_id = ?
             ORDER BY created_at DESC LIMIT ' . (int) $limit,
            [$eventId]
        );
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

    /** Merit seats sponsored for one event. */
    public function totalTables(int $eventId): int
    {
        return (int) $this->scalar(
            "SELECT COALESCE(SUM(table_count), 0) FROM donations WHERE event_id = ? AND method = 'table'",
            [$eventId]
        );
    }

    /** Which event a donation belongs to. */
    public function eventIdOf(int $id): ?int
    {
        $row = $this->fetchOne('SELECT event_id FROM donations WHERE id = ?', [$id]);
        return $row === null ? null : (int) $row['event_id'];
    }
}
