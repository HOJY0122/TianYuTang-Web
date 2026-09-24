<?php
namespace App\Core;

/**
 * ReceiptOcrParser — turns plain OCR output (words and where they sit
 * on the photo) into receipt fields, using the labels printed on the
 * temple's receipt as landmarks.
 *
 * OCR only knows the words; it cannot tell that "100" belongs to 布施.
 * The printed labels can: they are always in the same places, and the
 * handwriting sits to their right on the same line. So:
 *
 *   1. words are grouped into lines by their height on the page;
 *   2. in each line, the text after a label (布施, 祈福 … 總數) up to the
 *      next label is that box's handwriting — its first number is the
 *      amount;
 *   3. No., 日期/Date, 姓名/Name, 項目, 發據人 are found the same way.
 *
 * Everything found is a guess for a person to check against the photo;
 * the full OCR text is shown beside the form too.
 */
class ReceiptOcrParser
{
    /** Each box's printed label, with the forms OCR sometimes returns (simplified, variants). */
    private const LABELS = [
        'donation' => '布施|佈施',
        'blessing' => '祈福',
        'lotus'    => '蓮花燈|莲花灯|蓮花灯|蓮花|莲花',
        'oil'      => '添油',
        'dragon'   => '龍香|龙香',
        'tower'    => '塔香',
        'gift'     => '樂捐|乐捐',
        'meal'     => '施齋|施斋|施齊',
        'other'    => '其他|其它',
    ];
    private const TOTAL = '總數|总数|總数|總額|总额';

    /**
     * @param array $words list of ['text' => string, 'x1','y1','x2','y2' => int]
     * @return array same shape as ReceiptReader::read() before clean(), plus 'text'
     */
    public static function parse(array $words): array
    {
        $lines = self::lines($words);
        $texts = array_map(static fn($l) => $l['text'], $lines);
        $all   = implode("\n", $texts);

        $amounts = array_fill_keys(array_keys(self::LABELS), 0.0);
        $total   = 0.0;
        $payment = '';
        foreach ($texts as $line) {
            // Every label on this line, left to right.
            $marks = [];
            foreach (self::LABELS + ['total' => self::TOTAL] as $key => $pattern) {
                if (preg_match_all('/' . $pattern . '/u', $line, $m, PREG_OFFSET_CAPTURE)) {
                    foreach ($m[0] as [$found, $pos]) {
                        $marks[] = [$pos, $pos + strlen($found), $key];
                    }
                }
            }
            usort($marks, static fn($a, $b) => $a[0] <=> $b[0]);
            foreach ($marks as $i => [$start, $end, $key]) {
                $stop    = $marks[$i + 1][0] ?? strlen($line);
                $segment = substr($line, $end, max(0, $stop - $end));
                $amount  = self::firstAmount($segment);
                if ($amount === null) {
                    continue;
                }
                if ($key === 'total') {
                    $total = $amount;
                } elseif ($amounts[$key] == 0) {
                    $amounts[$key] = $amount;
                }
            }
            // A tick drawn in the Cash / Bank-In box, if OCR saw one.
            if (preg_match('/[✓✔√☑✅vV×xX]\s*Cash/u', $line)) {
                $payment = 'cash';
            } elseif (preg_match('/[✓✔√☑✅vV×xX]\s*Bank/u', $line)) {
                $payment = 'bank';
            }
        }

        // No. 26432 — the printed red number.
        $no = '';
        if (preg_match('/N[o0]\s*[.．:：]?\s*(\d{4,7})/u', $all, $m)) {
            $no = $m[1];
        }

        // A date written day first: 16/10/26, 16-10-2026, 16.10.2026
        $date = '';
        if (preg_match_all('/(?<!\d)(\d{1,2})\s*[\/.\-]\s*(\d{1,2})\s*[\/.\-]\s*(\d{2,4})(?!\d)/u', $all, $mm, PREG_SET_ORDER)) {
            foreach ($mm as [, $d, $mo, $y]) {
                $y = strlen($y) === 2 ? 2000 + (int) $y : (int) $y;
                if (checkdate((int) $mo, (int) $d, $y) && $y >= 2000 && $y <= 2100) {
                    $date = sprintf('%04d-%02d-%02d', $y, $mo, $d);
                    break;
                }
            }
        }

        $name   = self::after($texts, '姓名|姓\s*名', 60);
        $item   = self::after($texts, '項目|项目', 120);
        $issued = self::issuedBy($lines);

        $boxes = array_sum($amounts);
        $notes = '文字辨識（OCR）自動推斷，請逐項對照相片核對。Filled in from OCR — please check every field against the photo.';
        if ($total > 0 && $boxes > 0 && abs($total - $boxes) > 0.009) {
            $notes .= ' 各項合計與總數不符。Boxes and total do not match.';
        }

        return [
            'receipt_no'  => $no,
            'date'        => $date,
            'item'        => $item,
            'name'        => $name,
            'amounts'     => $amounts,
            'other_label' => '',
            'payment'     => $payment,
            'total'       => $total,
            'issued_by'   => $issued,
            // Handwritten names are OCR's weakest point: mark them for a second look.
            'unsure'      => array_keys(array_filter(['name' => $name, 'issued_by' => $issued])),
            'notes'       => $notes,
            'text'        => $all,
        ];
    }

    /**
     * Group words into lines: a word joins a line when its middle is
     * within about half a word-height of the line's middle.
     */
    private static function lines(array $words): array
    {
        $words = array_values(array_filter($words, static fn($w) => trim((string) $w['text']) !== ''));
        usort($words, static fn($a, $b) => ($a['y1'] + $a['y2']) <=> ($b['y1'] + $b['y2']));
        $lines = [];
        foreach ($words as $w) {
            $h  = max(1, $w['y2'] - $w['y1']);
            $cy = ($w['y1'] + $w['y2']) / 2;
            $placed = false;
            foreach ($lines as &$line) {
                if (abs($line['cy'] - $cy) <= 0.55 * max($h, $line['h'])) {
                    $line['words'][] = $w;
                    $n = count($line['words']);
                    $line['cy'] = ($line['cy'] * ($n - 1) + $cy) / $n;
                    $line['h']  = max($line['h'], $h);
                    $placed = true;
                    break;
                }
            }
            unset($line);
            if (!$placed) {
                $lines[] = ['cy' => $cy, 'h' => $h, 'words' => [$w]];
            }
        }
        foreach ($lines as &$line) {
            usort($line['words'], static fn($a, $b) => $a['x1'] <=> $b['x1']);
            $text = '';
            $prev = null;
            foreach ($line['words'] as $w) {
                // A visible gap between words becomes a space.
                if ($prev !== null && $w['x1'] - $prev['x2'] > 0.35 * $line['h']) {
                    $text .= ' ';
                }
                $text .= $w['text'];
                $prev = $w;
            }
            $line['text'] = $text;
        }
        unset($line);
        usort($lines, static fn($a, $b) => $a['cy'] <=> $b['cy']);
        return $lines;
    }

    /** The first amount in a stretch of text: "RM 1,200.50" → 1200.5 */
    private static function firstAmount(string $s): ?float
    {
        $s = preg_replace('/R\s*M/iu', ' ', $s);
        // Letters OCR commonly confuses with digits in handwriting.
        $s = preg_replace_callback('/[\dOoIl|SsB,.]{1,}/u', static function ($m) {
            $t = $m[0];
            return preg_match('/\d/', $t) ? strtr($t, ['O' => '0', 'o' => '0', 'I' => '1', 'l' => '1', '|' => '1', 'S' => '5', 's' => '5', 'B' => '8']) : $t;
        }, $s);
        if (!preg_match('/\d[\d,]*(?:\.\d{1,2})?/', $s, $m)) {
            return null;
        }
        $v = (float) str_replace(',', '', $m[0]);
        return $v > 0 && $v < 10000000 ? round($v, 2) : null;
    }

    /** Text after a label on its line, e.g. 姓名：陳大文 → 陳大文 */
    private static function after(array $texts, string $label, int $max): string
    {
        foreach ($texts as $line) {
            if (preg_match('/(?:' . $label . ')\s*[:：]?\s*(.+)$/u', $line, $m)) {
                $v = trim(preg_replace('/^(Name|Item)\s*[:：]?\s*/i', '', $m[1]), " \t:：_-—");
                if ($v !== '') {
                    return mb_substr($v, 0, $max);
                }
            }
        }
        return '';
    }

    /** The signature / name written just above "Issued By 發據人". */
    private static function issuedBy(array $lines): string
    {
        foreach ($lines as $i => $line) {
            if (!preg_match('/Issued|發據人|发据人/u', $line['text'])) {
                continue;
            }
            $same = trim(preg_replace('/Issued\s*By|發據人|发据人|[:：]/u', '', $line['text']));
            if ($same !== '') {
                return mb_substr($same, 0, 40);
            }
            $above = $lines[$i - 1] ?? null;
            if ($above && $line['cy'] - $above['cy'] < 3 * $line['h']
                && !preg_match('/' . self::TOTAL . '|Cash|Bank|RM/u', $above['text'])) {
                return mb_substr(trim($above['text']), 0, 40);
            }
        }
        return '';
    }
}
