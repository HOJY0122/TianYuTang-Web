<?php
namespace App\Core;

/**
 * Validate — the rules for identity and phone numbers, in ONE place.
 *
 * The same rules run in the browser (public/assets/js/validate.js) so a
 * visitor sees the problem as they type — but the browser can be
 * skipped, so these are the ones that actually decide. Keep the two in
 * step when changing either.
 *
 * Each check returns the value tidied into one standard format (so the
 * committee's lists are consistent), or null when it is not valid.
 */
class Validate
{
    /**
     * Malaysian MyKad IC or a passport number.
     *
     *   IC:       12 digits, dashes/spaces optional, first six a real
     *             birth date (YYMMDD) → stored as 650101-10-1234
     *   Passport: contains a letter; 6–20 letters/digits → stored upper-case
     */
    public static function icOrPassport(string $raw): ?string
    {
        $compact = strtoupper(preg_replace('/[\s\-]/', '', trim($raw)));
        if ($compact === '') {
            return null;
        }

        if (preg_match('/^\d{12}$/', $compact)) {
            $yy = (int) substr($compact, 0, 2);
            $mm = (int) substr($compact, 2, 2);
            $dd = (int) substr($compact, 4, 2);
            // The century is unknown, but checking both candidate years
            // still rejects 31 April, 30 February and month 13.
            if (!checkdate($mm, $dd, 1900 + $yy) && !checkdate($mm, $dd, 2000 + $yy)) {
                return null;
            }
            return substr($compact, 0, 6) . '-' . substr($compact, 6, 2) . '-' . substr($compact, 8);
        }

        if (preg_match('/[A-Z]/', $compact) && preg_match('/^[A-Z0-9]{6,20}$/', $compact)) {
            return $compact;
        }
        return null;
    }

    /**
     * A contact number.
     *
     *   Malaysia (0…, 60…, +60…):
     *     mobile   01x + 7–8 digits  → 012-345 6789 / 011-2345 6789
     *     landline 03–09 + 7–8 digits → 03-1234 5678 / 04-123 4567
     *   Elsewhere: must start with + and have 8–15 digits → kept as typed
     */
    public static function phone(string $raw): ?string
    {
        $raw = trim($raw);
        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === '') {
            return null;
        }

        $isPlus = str_starts_with($raw, '+');
        if (str_starts_with($digits, '60') && ($isPlus || strlen($digits) >= 11)) {
            $digits = '0' . substr($digits, 2);          // +60 12… → 012…
        } elseif ($isPlus) {
            // A foreign number. Only the length can be checked.
            $n = strlen($digits);
            return ($n >= 8 && $n <= 15) ? '+' . $digits : null;
        }

        if (preg_match('/^01\d{8,9}$/', $digits)) {                // mobile
            $rest = substr($digits, 3);
            return substr($digits, 0, 3) . '-' . substr($rest, 0, strlen($rest) - 4) . ' ' . substr($rest, -4);
        }
        if (preg_match('/^0[3-9]\d{7,8}$/', $digits)) {             // landline
            $rest = substr($digits, 2);
            return substr($digits, 0, 2) . '-' . substr($rest, 0, strlen($rest) - 4) . ' ' . substr($rest, -4);
        }
        return null;
    }

    /** Bilingual explanation shown next to an invalid IC / passport. */
    public const IC_MESSAGE = '身份證號碼格式不正確（例：650101-10-1234），或護照號碼須為 6–20 個英文字母／數字。'
        . "\nThe IC number should look like 650101-10-1234, or a passport number of 6–20 letters/digits.";

    /** Bilingual explanation shown next to an invalid phone number. */
    public const PHONE_MESSAGE = '聯絡號碼格式不正確（例：012-345 6789 或 03-1234 5678）。海外號碼請以 + 國碼開頭。'
        . "\nThe contact number should look like 012-345 6789 or 03-1234 5678. Overseas numbers start with + and the country code.";
}
