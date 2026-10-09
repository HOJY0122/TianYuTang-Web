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
        'book'   => 'CAST(book_no AS UNSIGNED) {dir}, book_no',
    ];

    /** The receipt number as a number, for ranges and gaps. */
    private const NUM = 'CAST(receipt_no AS UNSIGNED)';

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
        return $this->fetchOne('SELECT id, receipt_no, book_no, name, total FROM receipts WHERE receipt_no = ? AND id <> ? LIMIT 1',
            [$no, $exceptId]);
    }

    /**
     * @param array{q?:string, from?:string, to?:string, payment?:string, sort?:string, dir?:string} $f
     */
    public function search(array $f, int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->where($f);
        $dir  = strtolower($f['dir'] ?? '') === 'asc' ? 'ASC' : 'DESC';
        $sort = str_replace('{dir}', $dir, self::SORTS[$f['sort'] ?? ''] ?? 'created_at');
        // Then by number, so a book (or a day) reads in the order of its pages.
        $num  = self::NUM;
        return $this->fetchAll(
            "SELECT * FROM receipts {$where} ORDER BY {$sort} {$dir}, {$num} {$dir}, id {$dir} LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /** Row count and money totals for the same filters. */
    public function summary(array $f): array
    {
        [$where, $params] = $this->where($f);
        $sums = implode(', ', array_map(static fn($c) => "COALESCE(SUM({$c}),0) AS {$c}", self::amountColumns()));
        return $this->fetchOne("SELECT COUNT(*) AS n, COALESCE(SUM(needs_check),0) AS to_check, COALESCE(SUM(total),0) AS total, {$sums} FROM receipts {$where}", $params)
            ?? ['n' => 0, 'to_check' => 0, 'total' => 0];
    }

    /**
     * One line per receipt book for the same filters (the book filter
     * itself left out, so every book stays in the list to switch to):
     * how many receipts, how many still to check, the number range, how
     * many numbers in that range are not here, and the money.
     */
    public function books(array $f): array
    {
        unset($f['book']);
        [$where, $params] = $this->where($f);
        $num = self::NUM;
        $rows = $this->fetchAll(
            "SELECT book_no, COUNT(*) AS n, COALESCE(SUM(needs_check),0) AS to_check, COALESCE(SUM(total),0) AS total,
                    MIN(NULLIF({$num},0)) AS lo, MAX(NULLIF({$num},0)) AS hi,
                    COUNT(DISTINCT NULLIF({$num},0)) AS numbers, COUNT(NULLIF({$num},0)) AS numbered
               FROM receipts {$where}
              GROUP BY book_no
              ORDER BY book_no IS NULL, CAST(book_no AS UNSIGNED), book_no",
            $params
        );
        $register = $this->bookRegister();
        foreach ($rows as &$r) {
            $r['missing'] = $r['lo'] !== null ? (int) $r['hi'] - (int) $r['lo'] + 1 - (int) $r['numbers'] : 0;
            $r['repeats'] = (int) $r['numbered'] - (int) $r['numbers'];
            $r['holder']       = $register[$r['book_no']]['holder'] ?? null;
            $r['holder_phone'] = $register[$r['book_no']]['holder_phone'] ?? null;
        }
        unset($r);

        // Books handed out but with no receipt entered yet — shown too, so
        // the list says who is holding every book. Not when searching for
        // receipts (a word, dates, numbers…), where an empty book means nothing.
        $searching = array_filter(array_intersect_key($f, array_flip(['q', 'from', 'to', 'payment', 'no_from', 'no_to', 'check'])),
            static fn($v) => $v !== '' && $v !== null);
        if (!$searching) {
            $have = array_flip(array_filter(array_column($rows, 'book_no'), 'is_string'));
            foreach ($register as $no => $b) {
                if (isset($have[$no]) || (($f['holder'] ?? '') !== '' && $b['holder'] !== $f['holder'])) {
                    continue;
                }
                $rows[] = ['book_no' => (string) $no, 'n' => 0, 'to_check' => 0, 'total' => 0, 'lo' => null, 'hi' => null,
                           'numbers' => 0, 'numbered' => 0, 'missing' => 0, 'repeats' => 0,
                           'holder' => $b['holder'], 'holder_phone' => $b['holder_phone']];
            }
            usort($rows, static function ($a, $b) {
                if (($a['book_no'] === null) !== ($b['book_no'] === null)) {
                    return $a['book_no'] === null ? 1 : -1;
                }
                return strnatcasecmp((string) $a['book_no'], (string) $b['book_no']);
            });
        }
        return $rows;
    }

    /** Every registered book: book_no => [holder, holder_phone]. */
    public function bookRegister(): array
    {
        $out = [];
        foreach ($this->fetchAll('SELECT book_no, holder, holder_phone FROM receipt_books') as $r) {
            $out[$r['book_no']] = $r;
        }
        return $out;
    }

    /** One book's register line, or null. */
    public function book(string $book): ?array
    {
        return $this->fetchOne('SELECT * FROM receipt_books WHERE book_no = ?', [$book]);
    }

    /**
     * Register a book or change who holds it. An empty holder/phone leaves
     * the saved one alone unless $replace (the book form, where clearing a
     * box means clearing it).
     */
    public function saveBook(string $book, ?string $holder, ?string $phone, string $by, bool $replace = false): void
    {
        $keep = $replace ? 'VALUES(%1$s)' : 'COALESCE(VALUES(%1$s), %1$s)';
        $this->execute(
            'INSERT INTO receipt_books (book_no, holder, holder_phone, updated_by) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE holder = ' . sprintf($keep, 'holder') . ', holder_phone = ' . sprintf($keep, 'holder_phone')
             . ', updated_by = VALUES(updated_by)',
            [$book, $holder, $phone, $by]
        );
    }

    /** Everyone holding a book, for the "person in charge" filter. */
    public function holders(): array
    {
        return array_column($this->fetchAll(
            'SELECT DISTINCT holder FROM receipt_books WHERE holder IS NOT NULL AND holder <> \'\' ORDER BY holder'), 'holder');
    }

    /**
     * The numbers missing from one book's range, and any number used
     * twice in it. A range wider than $wide is almost surely a misread
     * number, so the gaps are not listed one by one.
     * @return array{missing: int[], repeats: string[], wide: bool}
     */
    public function bookGaps(?string $book, int $wide = 2000): array
    {
        $num = self::NUM;
        [$cond, $p] = $book === null ? ['book_no IS NULL', []] : ['book_no = ?', [$book]];
        $nums = array_map('intval', array_column($this->fetchAll(
            "SELECT DISTINCT {$num} AS x FROM receipts WHERE {$cond} AND {$num} > 0 ORDER BY x", $p), 'x'));
        $repeats = array_column($this->fetchAll(
            "SELECT receipt_no FROM receipts WHERE {$cond} AND {$num} > 0 GROUP BY receipt_no HAVING COUNT(*) > 1 ORDER BY {$num}", $p),
            'receipt_no');
        if (!$nums) {
            return ['missing' => [], 'repeats' => $repeats, 'wide' => false];
        }
        $lo = $nums[0];
        $hi = end($nums);
        if ($hi - $lo > $wide) {
            return ['missing' => [], 'repeats' => $repeats, 'wide' => true];
        }
        return ['missing' => array_values(array_diff(range($lo, $hi), $nums)), 'repeats' => $repeats, 'wide' => false];
    }

    /** Every book number used so far, for the book box's suggestions. */
    public function bookNumbers(): array
    {
        $all = array_unique(array_merge(
            array_column($this->fetchAll('SELECT DISTINCT book_no FROM receipts WHERE book_no IS NOT NULL'), 'book_no'),
            array_map('strval', array_keys($this->bookRegister()))));
        natcasesort($all);
        return array_values($all);
    }

    /** A bulk-uploaded photo: a receipt row waiting to be read and checked. */
    public function createDraft(?string $book, string $imagePath, string $by): int
    {
        $this->execute(
            "INSERT INTO receipts (book_no, image_path, source, needs_check, created_by) VALUES (?, ?, 'manual', 1, ?)",
            [$book, $imagePath, $by]
        );
        return (int) $this->db->lastInsertId();
    }

    /** Put the AI's reading into a receipt that nobody has checked yet. */
    public function fillFromAi(int $id, array $v, ?string $notes): void
    {
        $cols = array_merge(['receipt_no', 'receipt_date', 'item', 'name', 'other_label', 'total', 'payment', 'issued_by'],
            self::amountColumns());
        $set  = implode(', ', array_map(static fn($c) => "{$c} = ?", $cols));
        $vals = array_map(static fn($c) => $v[$c], $cols);
        $this->execute("UPDATE receipts SET {$set}, source = 'ai', ai_notes = ? WHERE id = ? AND needs_check = 1",
            array_merge($vals, [$notes, $id]));
    }

    /** Bulk-uploaded receipts in a book that the AI has not read yet. */
    public function unread(?string $book): array
    {
        [$cond, $p] = $book === null ? ['book_no IS NULL', []] : ['book_no = ?', [$book]];
        return $this->fetchAll(
            "SELECT id, receipt_no FROM receipts WHERE {$cond} AND needs_check = 1 AND source = 'manual' AND image_path IS NOT NULL ORDER BY id",
            $p);
    }

    /** The next receipt waiting to be checked: same book first, in number order. */
    public function nextToCheck(?string $book, int $afterId): ?array
    {
        $num = self::NUM;
        return $this->fetchOne(
            "SELECT id FROM receipts WHERE needs_check = 1 AND id <> ?
              ORDER BY (book_no <=> ?) DESC, {$num} = 0, {$num}, id LIMIT 1",
            [$afterId, $book]);
    }

    private function where(array $f): array
    {
        $w = [];
        $p = [];
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $w[] = '(receipt_no LIKE ? OR book_no LIKE ? OR name LIKE ? OR item LIKE ? OR issued_by LIKE ? OR notes LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($p, $like, $like, $like, $like, $like, $like);
        }
        // Every book a member is in charge of.
        if (($f['holder'] ?? '') !== '') {
            $w[] = 'book_no IN (SELECT book_no FROM receipt_books WHERE holder = ?)';
            $p[] = $f['holder'];
        }
        // One book, or "no book written" (the list's ‘—’ row).
        $book = (string) ($f['book'] ?? '');
        if ($book === '-') {
            $w[] = 'book_no IS NULL';
        } elseif ($book !== '') {
            $w[] = 'book_no = ?';
            $p[] = $book;
        }
        // Receipt number range: 26400 – 26450 (either end may be left open).
        foreach (['no_from' => '>=', 'no_to' => '<='] as $key => $op) {
            $n = (string) ($f[$key] ?? '');
            if ($n !== '' && ctype_digit($n)) {
                $w[] = self::NUM . " {$op} ? AND receipt_no <> ''";
                $p[] = (int) $n;
            }
        }
        if (($f['check'] ?? '') === '1' || ($f['check'] ?? '') === '0') {
            $w[] = 'needs_check = ?';
            $p[] = (int) $f['check'];
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
        $cols = array_merge(['receipt_no', 'book_no', 'receipt_date', 'item', 'name', 'other_label', 'total', 'payment',
                             'issued_by', 'notes'], self::amountColumns());
        $vals = array_map(static fn($c) => $v[$c], $cols);

        if ($id) {
            $set = implode(', ', array_map(static fn($c) => "{$c} = ?", $cols));
            // Saved from the form = a person has checked it.
            $this->execute("UPDATE receipts SET {$set}, needs_check = 0, updated_by = ? WHERE id = ?", array_merge($vals, [$by, $id]));
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

    /** The bank's transaction receipt, for a Bank-In receipt (null removes it). */
    public function setBankSlip(int $id, ?string $path): void
    {
        $this->execute('UPDATE receipts SET bank_slip_path = ? WHERE id = ?', [$path, $id]);
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM receipts WHERE id = ?', [$id]);
    }
}
