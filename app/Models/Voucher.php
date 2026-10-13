<?php
namespace App\Models;

use App\Core\AmountWords;
use App\Core\Crypto;
use App\Core\Model;

/**
 * Voucher — 布施憑單 Donation vouchers, kept in batches (one giving round
 * each). A voucher uses its batch's amount unless it has its own.
 * Bank accounts are stored encrypted (App\Core\Crypto).
 *
 * Voucher number: DV<batch>-<seq>, e.g. DV007-012. A deleted voucher's
 * number is never handed out again, so a printed number always means
 * one voucher.
 */
class Voucher extends Model
{
    /** Paper choices for printing — the same page, full size or scaled down. */
    public const PAPERS = [
        'a4' => ['A4，每頁一張（與樣本相同）', 'A4, one per page (as the sample)'],
        'a5' => ['A5，每頁一張（縮小）', 'A5, one per page (smaller)'],
    ];

    // ---------------------------------------------------------- batches

    /** Every batch, newest first, with how many vouchers, the total and how many are paid. */
    public function batches(): array
    {
        return $this->fetchAll(
            'SELECT b.*, COUNT(v.id) AS n, COALESCE(SUM(COALESCE(v.amount, b.amount)), 0) AS total,
                    COALESCE(SUM(v.paid_at IS NOT NULL), 0) AS paid
               FROM voucher_batches b LEFT JOIN vouchers v ON v.batch_id = b.id
              GROUP BY b.id ORDER BY COALESCE(b.voucher_date, DATE(b.created_at)) DESC, b.id DESC');
    }

    public function batch(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM voucher_batches WHERE id = ?', [$id]);
    }

    public function createBatch(array $v, string $by): int
    {
        $this->execute(
            'INSERT INTO voucher_batches (title, voucher_date, description, amount, amount_words, method, paper, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$v['title'], $v['voucher_date'], $v['description'], $v['amount'], $v['amount_words'], $v['method'], $v['paper'], $v['notes'], $by]);
        return (int) $this->db->lastInsertId();
    }

    public function updateBatch(int $id, array $v): void
    {
        $this->execute(
            'UPDATE voucher_batches SET title = ?, voucher_date = ?, description = ?, amount = ?, amount_words = ?, method = ?, paper = ?, notes = ?
              WHERE id = ?',
            [$v['title'], $v['voucher_date'], $v['description'], $v['amount'], $v['amount_words'], $v['method'], $v['paper'], $v['notes'], $id]);
    }

    /** Delete a batch and its vouchers. */
    public function deleteBatch(int $id): void
    {
        $this->execute('DELETE FROM vouchers WHERE batch_id = ?', [$id]);
        $this->execute('DELETE FROM voucher_batches WHERE id = ?', [$id]);
    }

    // --------------------------------------------------------- vouchers

    /** A batch's vouchers in number order, bank accounts readable. $ids limits it to those. */
    public function vouchers(int $batchId, ?array $ids = null): array
    {
        $sql = 'SELECT * FROM vouchers WHERE batch_id = ?';
        $params = [$batchId];
        if ($ids !== null) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
            if (!$ids) {
                return [];
            }
            $sql .= ' AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = array_merge($params, $ids);
        }
        return array_map([$this, 'readable'], $this->fetchAll($sql . ' ORDER BY seq', $params));
    }

    public function find(int $id): ?array
    {
        $row = $this->fetchOne('SELECT * FROM vouchers WHERE id = ?', [$id]);
        return $row ? $this->readable($row) : null;
    }

    private function readable(array $row): array
    {
        $row['bank_account'] = $row['bank_account'] !== null ? (string) Crypto::decrypt($row['bank_account']) : null;
        return $row;
    }

    /** Add vouchers to a batch; each gets the next number. Returns how many were added. */
    public function add(int $batchId, array $list): int
    {
        $this->db->beginTransaction();
        try {
            // Numbers of deleted vouchers are not reused: carry on from the highest ever given.
            // FOR UPDATE: two people importing at once still get different numbers.
            $next = max((int) $this->scalar('SELECT last_seq FROM voucher_batches WHERE id = ? FOR UPDATE', [$batchId]),
                        (int) $this->scalar('SELECT COALESCE(MAX(seq), 0) FROM vouchers WHERE batch_id = ?', [$batchId]));
            foreach ($list as $v) {
                $this->execute(
                    'INSERT INTO vouchers (batch_id, seq, list_no, name_en, name_zh, bank_account, amount) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$batchId, ++$next, $v['list_no'], $v['name_en'], $v['name_zh'],
                     $v['bank_account'] !== null ? Crypto::encrypt($v['bank_account']) : null, $v['amount']]);
            }
            $this->execute('UPDATE voucher_batches SET last_seq = ? WHERE id = ?', [$next, $batchId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return count($list);
    }

    public function update(int $id, array $v): void
    {
        $this->execute(
            'UPDATE vouchers SET list_no = ?, name_en = ?, name_zh = ?, bank_account = ?, amount = ? WHERE id = ?',
            [$v['list_no'], $v['name_en'], $v['name_zh'],
             $v['bank_account'] !== null ? Crypto::encrypt($v['bank_account']) : null, $v['amount'], $id]);
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM vouchers WHERE id = ?', [$id]);
    }

    public function setPaid(int $id, bool $paid): void
    {
        $this->execute('UPDATE vouchers SET paid_at = ' . ($paid ? 'NOW()' : 'NULL') . ' WHERE id = ?', [$id]);
    }

    // ---------------------------------------------------------- display

    public static function number(array $batch, array $voucher): string
    {
        return 'DV' . str_pad((string) $batch['id'], 3, '0', STR_PAD_LEFT) . '-' . str_pad((string) $voucher['seq'], 3, '0', STR_PAD_LEFT);
    }

    /** "RM 2,000", or "RM 2,000.50" when there are sen — as on the sample voucher. */
    public static function rm(float $amount): string
    {
        return 'RM ' . number_format($amount, fmod(round($amount, 2), 1.0) == 0.0 ? 0 : 2);
    }

    /**
     * The lines at the top of the printed voucher: the saved ones
     * (Vouchers → 憑單版面), else the printout letterhead from Site settings.
     */
    public static function headerLines(array $site): array
    {
        $saved = $site['voucher_header'] ?? null;
        if ($saved !== null && trim($saved) !== '') {
            $lines = preg_split('/\R/u', $saved);
        } else {
            $lines = [Setting::effective('pdf_name', $site) ?: (string) $site['site_name'], Setting::effective('pdf_name_en', $site),
                      Setting::effective('pdf_line1', $site), Setting::effective('pdf_line2', $site), Setting::effective('pdf_line3', $site)];
        }
        return array_values(array_filter(array_map('trim', $lines), static fn($l) => $l !== ''));
    }

    public static function amount(array $batch, array $voucher): float
    {
        return (float) ($voucher['amount'] ?? $batch['amount']);
    }

    /** "Two Thousand Only" — the batch's own wording when the amount is the batch's. */
    public static function wordsEn(array $batch, array $voucher): string
    {
        $same = $voucher['amount'] === null || (float) $voucher['amount'] === (float) $batch['amount'];
        if ($same && trim((string) $batch['amount_words']) !== '') {
            return (string) $batch['amount_words'];
        }
        return AmountWords::en(self::amount($batch, $voucher));
    }

    public static function wordsZh(array $batch, array $voucher): string
    {
        return AmountWords::zh(self::amount($batch, $voucher));
    }

    // ----------------------------------------------------------- import

    /**
     * Rows from a spreadsheet → vouchers to add. Works like the desktop
     * Bulk Voucher Generator: find the header row (the one with "No."),
     * pick the columns by name, and fall back to the blank-headed columns
     * after the centre name for the Chinese name and bank account. With
     * no header row at all, the columns are taken as
     * [No.,] Name, Chinese name, Bank account[, Amount].
     *
     * @return array{0: list<array>, 1: int} vouchers, rows skipped (no name)
     */
    public static function fromRows(array $rows): array
    {
        $norm = static fn($s): string => preg_replace('/[\s._\-:：()（）#]+/u', '', mb_strtolower(trim((string) $s)));
        $headerAt = null;
        foreach (array_slice($rows, 0, 30, true) as $i => $row) {
            foreach ($row as $cell) {
                if (in_array($norm($cell), ['no', 'bil', '序號', '序号', '編號', '编号', 'number'], true)) {
                    $headerAt = $i;
                    break 2;
                }
            }
        }

        $col = ['no' => -1, 'en' => -1, 'zh' => -1, 'bank' => -1, 'amount' => -1];
        if ($headerAt !== null) {
            $head = array_map($norm, $rows[$headerAt]);
            $want = [
                'zh'     => ['chinesecentrename', 'chinesename', '中文名稱', '中文名称', '中文', '中文名'],
                'en'     => ['englishcentrename', 'centrename', 'centername', 'englishname', 'organisation', 'organization', 'name', '名稱', '名称'],
                'bank'   => ['bankaccount', 'bankaccountno', 'accountno', 'accountnumber', 'bank', '銀行戶口', '银行户口', '戶口', '户口'],
                'amount' => ['amount', 'amountrm', 'rm', '金額', '金额'],
                'no'     => ['no', 'bil', '序號', '序号', '編號', '编号', 'number'],
            ];
            $taken = [];
            foreach ([true, false] as $exact) {          // exact names first, then "contains"
                foreach ($want as $key => $names) {
                    if ($col[$key] >= 0) {
                        continue;
                    }
                    foreach ($names as $name) {
                        foreach ($head as $j => $h) {
                            if ($h === '' || isset($taken[$j])) {
                                continue;
                            }
                            // "Name" must not grab "Chinese Name".
                            if ($key === 'en' && preg_match('/chinese|中文/u', $h)) {
                                continue;
                            }
                            if ($exact ? $h === $name : (mb_strlen($name) > 2 && str_contains($h, $name))) {
                                $col[$key] = $j;
                                $taken[$j] = true;
                                break 2;
                            }
                        }
                    }
                }
            }
            // The usual centre list leaves the Chinese-name and bank columns without a heading.
            if ($col['en'] >= 0) {
                $width = max(array_map('count', array_slice($rows, $headerAt)));
                for ($j = $col['en'] + 1; $j < $width && ($col['zh'] < 0 || $col['bank'] < 0); $j++) {
                    if (($head[$j] ?? '') === '' && !isset($taken[$j])) {
                        $col[$col['zh'] < 0 ? 'zh' : 'bank'] = $j;
                        $taken[$j] = true;
                    }
                }
            }
            $data = array_slice($rows, $headerAt + 1);
        } else {
            $first = $rows[0] ?? [];
            $o = preg_match('/^\d{1,5}\.?$/', trim((string) ($first[0] ?? ''))) ? 1 : 0;
            $col = ['no' => $o ? 0 : -1, 'en' => $o, 'zh' => $o + 1, 'bank' => $o + 2, 'amount' => $o + 3];
            $data = $rows;
        }

        $cell = static fn(array $r, int $j, int $max): ?string =>
            $j >= 0 && ($v = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($r[$j] ?? ''))), 0, $max)) !== '' ? $v : null;
        $out = [];
        $skipped = 0;
        foreach ($data as $r) {
            $en = $cell($r, $col['en'], 200);
            $zh = $cell($r, $col['zh'], 120);
            // A Chinese name in the English column (and nothing beside it) is moved across.
            if ($zh === null && $en !== null && preg_match('/\p{Han}/u', $en) && !preg_match('/[A-Za-z]/', $en)) {
                [$zh, $en] = [$en, null];
            }
            if ($en === null && $zh === null) {
                if (array_filter($r, static fn($c) => trim((string) $c) !== '')) {
                    $skipped++;
                }
                continue;
            }
            $amt = $cell($r, $col['amount'], 30);
            $amt = $amt !== null ? str_replace([',', 'RM', 'rm', ' '], '', $amt) : null;
            $out[] = [
                'list_no'      => $cell($r, $col['no'], 20),
                'name_en'      => $en,
                'name_zh'      => $zh,
                'bank_account' => $cell($r, $col['bank'], 160),
                'amount'       => $amt !== null && is_numeric($amt) && (float) $amt > 0 && (float) $amt < 1e10 ? round((float) $amt, 2) : null,
            ];
        }
        return [$out, $skipped];
    }
}
