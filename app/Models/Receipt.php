<?php
namespace App\Models;

use App\Core\Model;
use App\Core\ReceiptReader;

/**
 * Receipt — one page from the temple's paper receipt book, stored so the
 * handwritten books can be searched, totalled and exported.
 *
 * Amount columns are named amt_<key> for each key in
 * ReceiptReader::CATEGORIES (布施 → amt_donation, …), so the list of
 * boxes lives in one place.
 */
class Receipt extends Model
{
    /** Columns the list can be sorted by: request value => SQL. Nothing else reaches ORDER BY. */
    public const SORTS = [
        'date'   => 'receipt_date',
        'no'     => 'CAST(receipt_no AS UNSIGNED)',
        'name'   => 'name',
        'total'  => 'total',
        'added'  => 'created_at',
    ];

    public static function amountColumns(): array
    {
        return array_map(static fn($k) => 'amt_' . $k, array_keys(ReceiptReader::CATEGORIES));
    }

    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM receipts WHERE id = ?', [$id]);
    }

    /** Another receipt already saved with this number (for a duplicate warning). */
    public function findByNumber(string $no, int $exceptId = 0): ?array
    {
        if ($no === '') {
            return null;
        }
        return $this->fetchOne('SELECT id, receipt_no, name, total FROM receipts WHERE receipt_no = ? AND id <> ? LIMIT 1',
            [$no, $exceptId]);
    }

    /**
     * @param array{q?:string, from?:string, to?:string, payment?:string, sort?:string, dir?:string} $f
     */
    public function search(array $f, int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->where($f);
        $sort = self::SORTS[$f['sort'] ?? ''] ?? 'created_at';
        $dir  = strtolower($f['dir'] ?? '') === 'asc' ? 'ASC' : 'DESC';
        return $this->fetchAll(
            "SELECT * FROM receipts {$where} ORDER BY {$sort} {$dir}, id {$dir} LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /** Row count and money totals for the same filters. */
    public function summary(array $f): array
    {
        [$where, $params] = $this->where($f);
        $sums = implode(', ', array_map(static fn($c) => "COALESCE(SUM({$c}),0) AS {$c}", self::amountColumns()));
        return $this->fetchOne("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS total, {$sums} FROM receipts {$where}", $params)
            ?? ['n' => 0, 'total' => 0];
    }

    private function where(array $f): array
    {
        $w = [];
        $p = [];
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $w[] = '(receipt_no LIKE ? OR name LIKE ? OR item LIKE ? OR issued_by LIKE ? OR notes LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($p, $like, $like, $like, $like, $like);
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
            $d = (string) ($f[$key] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                $w[] = "receipt_date {$op} ?";
                $p[] = $d;
            }
        }
        if (in_array($f['payment'] ?? '', ['cash', 'bank'], true)) {
            $w[] = 'payment = ?';
            $p[] = $f['payment'];
        }
        return [$w ? 'WHERE ' . implode(' AND ', $w) : '', $p];
    }

    /** Insert or update from checked form values; returns the id. */
    public function save(?int $id, array $v, string $by): int
    {
        $cols = array_merge(['receipt_no', 'receipt_date', 'item', 'name', 'other_label', 'total', 'payment',
                             'issued_by', 'notes'], self::amountColumns());
        $vals = array_map(static fn($c) => $v[$c], $cols);

        if ($id) {
            $set = implode(', ', array_map(static fn($c) => "{$c} = ?", $cols));
            $this->execute("UPDATE receipts SET {$set}, updated_by = ? WHERE id = ?", array_merge($vals, [$by, $id]));
            return $id;
        }
        $cols = array_merge($cols, ['image_path', 'source', 'ai_notes', 'created_by']);
        $vals = array_merge($vals, [$v['image_path'], $v['source'], $v['ai_notes'], $by]);
        $this->execute('INSERT INTO receipts (' . implode(', ', $cols) . ') VALUES (' . rtrim(str_repeat('?, ', count($cols)), ', ') . ')', $vals);
        return (int) $this->db->lastInsertId();
    }

    public function setImage(int $id, ?string $path): void
    {
        $this->execute('UPDATE receipts SET image_path = ? WHERE id = ?', [$path, $id]);
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM receipts WHERE id = ?', [$id]);
    }
}
