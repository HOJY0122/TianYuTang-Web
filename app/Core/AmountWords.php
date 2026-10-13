<?php
namespace App\Core;

/**
 * AmountWords — an amount written out, as on a cheque or voucher.
 *
 *   AmountWords::en(2000)      → "Two Thousand Only"
 *   AmountWords::en(1234.5)    → "One Thousand Two Hundred Thirty Four and Fifty Sen Only"
 *   AmountWords::zh(2000)      → "貳仟令吉正"
 *   AmountWords::zh(10050.2)   → "壹萬零伍拾令吉貳拾仙"
 *
 * Chinese uses the financial numerals (壹貳參…) so a digit cannot be
 * changed with one pen stroke.
 */
final class AmountWords
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    private const SCALE = ['', 'Thousand', 'Million', 'Billion'];

    private const ZH_DIGITS = ['零', '壹', '貳', '參', '肆', '伍', '陸', '柒', '捌', '玖'];
    private const ZH_UNITS  = ['', '拾', '佰', '仟'];
    private const ZH_BIG    = ['', '萬', '億'];

    /** [whole ringgit, sen] — rounded to the sen. */
    private static function split(float $amount): array
    {
        $cents = (int) round(abs($amount) * 100);
        return [intdiv($cents, 100), $cents % 100];
    }

    public static function en(float $amount): string
    {
        [$rm, $sen] = self::split($amount);
        $words = $rm > 0 ? self::enWhole($rm) : ($sen > 0 ? '' : 'Zero');
        if ($sen > 0) {
            $words .= ($words !== '' ? ' and ' : '') . self::enWhole($sen) . ' Sen';
        }
        return $words . ' Only';
    }

    private static function enWhole(int $n): string
    {
        $parts = [];
        for ($i = 0; $n > 0 && $i < count(self::SCALE); $i++, $n = intdiv($n, 1000)) {
            $chunk = $n % 1000;
            if ($chunk > 0) {
                array_unshift($parts, trim(self::enHundreds($chunk) . ' ' . self::SCALE[$i]));
            }
        }
        return implode(' ', $parts);
    }

    private static function enHundreds(int $n): string
    {
        $out = [];
        if ($n >= 100) {
            $out[] = self::ONES[intdiv($n, 100)] . ' Hundred';
            $n %= 100;
        }
        if ($n >= 20) {
            $out[] = trim(self::TENS[intdiv($n, 10)] . ' ' . self::ONES[$n % 10]);
        } elseif ($n > 0) {
            $out[] = self::ONES[$n];
        }
        return implode(' ', $out);
    }

    public static function zh(float $amount): string
    {
        [$rm, $sen] = self::split($amount);
        $out = $rm > 0 ? self::zhWhole($rm) . '令吉' : '';
        if ($sen > 0) {
            return $out . self::zhWhole($sen) . '仙';
        }
        return ($out !== '' ? $out : '零令吉') . '正';
    }

    private static function zhWhole(int $n): string
    {
        $groups = [];
        while ($n > 0) {
            $groups[] = $n % 10000;
            $n = intdiv($n, 10000);
        }
        $out = '';
        $zero = false;
        for ($g = count($groups) - 1; $g >= 0; $g--) {
            $v = $groups[$g];
            if ($v === 0) {
                $zero = $out !== '';
                continue;
            }
            if ($out !== '' && ($zero || $v < 1000)) {
                $out .= '零';
            }
            $out .= self::zhGroup($v) . (self::ZH_BIG[$g] ?? '');
            $zero = false;
        }
        return $out;
    }

    private static function zhGroup(int $v): string
    {
        $out = '';
        $zero = false;
        for ($p = 3; $p >= 0; $p--) {
            $d = intdiv($v, 10 ** $p) % 10;
            if ($d === 0) {
                $zero = $out !== '';
                continue;
            }
            $out .= ($zero ? '零' : '') . self::ZH_DIGITS[$d] . self::ZH_UNITS[$p];
            $zero = false;
        }
        return $out;
    }
}
