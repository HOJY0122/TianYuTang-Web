<?php
namespace App\Core;

/**
 * FormRules — the choices a system admin makes in System → 表單與字體
 * Forms & fonts, read in one place by the public pages, the counter and
 * the save checks.
 *
 *   regTypes()   which kinds of registration the public form offers:
 *                individual / family, organisation group, or both
 *   ageRule()    "66 and above only": the minimum age, worked out from
 *                the Malaysian IC (the first six digits are the birth
 *                date YYMMDD)
 *   amounts()    the quick-amount buttons on the donate page and counter
 *   BODY_FONTS   the typefaces for the public site's ordinary text
 *
 * The browser shows the same rules as people type (register page), but
 * these are the ones that decide.
 */
final class FormRules
{
    public const TYPES = [
        'individual'   => ['只限個人 / 家庭', 'Individual / family only'],
        'both'         => ['個人與團體都可以', 'Individual and organisation group'],
        'organisation' => ['只限團體 / 機構', 'Organisation group only'],
    ];

    /** How the age is counted. */
    public const AGE_BASIS = [
        'year'  => ['按出生年計（今年 − 出生年）', 'By birth year (this year − year born)'],
        'event' => ['按活動首日的實際歲數', 'Exact age on the first event day'],
    ];

    /** Who in a group must meet the age. */
    public const AGE_WHO = [
        'all' => ['每一位參加者', 'Every person'],
        'any' => ['至少一位參加者', 'At least one person in the group'],
    ];

    /** A passport (or any number that is not a MyKad IC) cannot show an age. */
    public const AGE_OTHER = [
        'allow' => ['接受（無法核對歲數）', 'Accept (the age cannot be checked)'],
        'block' => ['不接受，只收大馬身份證', 'Refuse — Malaysian IC only'],
    ];

    public const AGE_RANGE = [1, 120];
    public const AMOUNT_RANGE = [1, 100000];

    /**
     * Ordinary-text typefaces for the public site. All have the full
     * Traditional character set; key => [label, CSS family, Google Fonts
     * parameter, sample note].
     */
    public const BODY_FONTS = [
        'noto_sans'  => ['思源黑體 Noto Sans TC',        'Noto Sans TC',           'Noto+Sans+TC:wght@400;500;700;800',    '清楚易讀（預設） Clear and modern (default)'],
        'chiron_hei' => ['昭源黑體 Chiron Hei HK',      'Chiron Hei HK',          'Chiron+Hei+HK:wght@400;500;700;800',   '較圓潤的黑體 Softer, rounder sans'],
        'huninn'     => ['粉圓體 Huninn',               'Huninn',                 'Huninn',                               '圓體，親切 Rounded and friendly'],
        'noto_serif' => ['思源宋體 Noto Serif TC',       'Noto Serif TC',          'Noto+Serif+TC:wght@400;500;700;900',   '明體，莊重 Classic serif, formal'],
        'chiron_sung'=> ['昭源宋體 Chiron Sung HK',     'Chiron Sung HK',         'Chiron+Sung+HK:wght@400;500;700;900',  '宋體，傳統書卷 Traditional book style'],
        'cactus'     => ['仙人掌明體 Cactus Classical',  'Cactus Classical Serif', 'Cactus+Classical+Serif',               '古典明體 Old-style serif'],
        'wenkai'     => ['霞鶩文楷 LXGW WenKai TC',     'LXGW WenKai TC',         'LXGW+WenKai+TC:wght@400;700',          '楷書，溫和 Gentle Kai script'],
        'iansui'     => ['芫荽 Iansui',                 'Iansui',                 'Iansui',                               '手寫感 Hand-written feel'],
    ];
    public const BODY_SIZE = [90, 125];

    /** Allowed registration kinds, in the order the buttons show them. */
    public static function regTypes(?array $site = null): array
    {
        $site ??= (new \App\Models\Setting())->site();
        return match ($site['rsvp_types'] ?? 'individual') {
            'both'         => ['individual', 'organisation'],
            'organisation' => ['organisation'],
            default        => ['individual'],
        };
    }

    /** Most people in one registration of this kind. */
    public static function maxPeople(array $event, string $type, ?array $site = null): int
    {
        $site ??= (new \App\Models\Setting())->site();
        $base = max(1, (int) $event['max_attendees']);
        if ($type === 'organisation' && (int) ($site['rsvp_org_max'] ?? 0) > 0) {
            return min(200, (int) $site['rsvp_org_max']);
        }
        return $base;
    }

    /** The age rule, or null when there is none. */
    public static function ageRule(?array $site = null): ?array
    {
        $site ??= (new \App\Models\Setting())->site();
        if (($site['rsvp_age_on'] ?? '0') !== '1') {
            return null;
        }
        return [
            'min'   => max(self::AGE_RANGE[0], min(self::AGE_RANGE[1], (int) ($site['rsvp_age_min'] ?? 66))),
            'basis' => isset(self::AGE_BASIS[$site['rsvp_age_basis'] ?? '']) ? $site['rsvp_age_basis'] : 'year',
            'who'   => isset(self::AGE_WHO[$site['rsvp_age_who'] ?? '']) ? $site['rsvp_age_who'] : 'all',
            'other' => isset(self::AGE_OTHER[$site['rsvp_age_other'] ?? '']) ? $site['rsvp_age_other'] : 'allow',
        ];
    }

    /** The date ages are counted on: the event's first day, or today if it has none. */
    public static function ageDate(array $event): \DateTimeImmutable
    {
        $d = !empty($event['start_date']) ? \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $event['start_date']) : false;
        return $d ?: new \DateTimeImmutable('today');
    }

    /**
     * Birth date from a MyKad number (650101-10-1234), or null for a
     * passport / anything else. The century is the one that makes the
     * person not yet born in the future: 05… is 2005, 65… is 1965.
     */
    public static function birthDate(string $ic, ?\DateTimeImmutable $on = null): ?\DateTimeImmutable
    {
        $digits = preg_replace('/[\s\-]/', '', $ic);
        if (!preg_match('/^\d{12}$/', $digits)) {
            return null;
        }
        $on ??= new \DateTimeImmutable('today');
        $yy = (int) substr($digits, 0, 2);
        $mm = (int) substr($digits, 2, 2);
        $dd = (int) substr($digits, 4, 2);
        $year = 2000 + $yy;
        if ($year > (int) $on->format('Y')) {
            $year -= 100;
        }
        if (!checkdate($mm, $dd, $year)) {
            return null;
        }
        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $mm, $dd));
    }

    /** Age on $on, counted the chosen way. */
    public static function age(\DateTimeImmutable $born, \DateTimeImmutable $on, string $basis): int
    {
        if ($basis === 'year') {
            return (int) $on->format('Y') - (int) $born->format('Y');
        }
        return $born->diff($on)->y;
    }

    /**
     * Check one person's number against the rule.
     * @return array{ok:bool, age:?int, born:?string, checked:bool}
     *   checked = false when the number cannot show an age (passport)
     */
    public static function checkAge(string $ic, array $rule, array $event): array
    {
        $on   = self::ageDate($event);
        $born = self::birthDate($ic, $on);
        if ($born === null) {
            return ['ok' => $rule['other'] === 'allow', 'age' => null, 'born' => null, 'checked' => false];
        }
        $age = self::age($born, $on, $rule['basis']);
        return ['ok' => $age >= $rule['min'], 'age' => $age, 'born' => $born->format('Y-m-d'), 'checked' => true];
    }

    /**
     * Check a whole group. Returns null when it passes, or the message
     * explaining who does not meet the rule.
     * @param string[] $ics already tidied
     */
    public static function groupAgeProblem(array $ics, array $rule, array $event): ?string
    {
        $min = $rule['min'];
        $how = $rule['basis'] === 'year' ? '按出生年計' : '以活動首日計';
        $results = [];
        foreach (array_values($ics) as $i => $ic) {
            $results[$i + 1] = self::checkAge($ic, $rule, $event);
        }
        // A number that cannot show an age, when those are refused, is always a problem.
        foreach ($results as $n => $r) {
            if (!$r['checked'] && $rule['other'] === 'block') {
                return "第 {$n} 位：本活動只接受大馬身份證號碼（用來核對 {$min} 歲或以上）。\n"
                     . "Person {$n}: only Malaysian IC numbers are accepted (to check the age of {$min} and above).";
            }
        }
        if ($rule['who'] === 'any') {
            foreach ($results as $r) {
                if ($r['ok'] && $r['checked']) {
                    return null;
                }
            }
            // Passports accepted and nobody provably old enough: let a group of passports through.
            $anyChecked = array_filter($results, static fn($r) => $r['checked']);
            if (!$anyChecked) {
                return null;
            }
            return "本活動須至少一位參加者 {$min} 歲或以上（{$how}）。\n"
                 . "At least one person must be {$min} or older.";
        }
        foreach ($results as $n => $r) {
            if ($r['checked'] && !$r['ok']) {
                return "第 {$n} 位：本活動只限 {$min} 歲或以上（{$how}，此身份證為 {$r['age']} 歲）。\n"
                     . "Person {$n}: this event is for ages {$min} and above (this IC shows age {$r['age']}).";
            }
        }
        return null;
    }

    /**
     * Quick-amount buttons from a setting such as "1, 5, 10, 50": whole
     * Ringgit, sorted, no repeats, at most 12.
     * @return int[]
     */
    public static function parseAmounts(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\s,，、;]+/u', $raw) as $part) {
            if ($part !== '' && ctype_digit($part)) {
                $n = (int) $part;
                if ($n >= self::AMOUNT_RANGE[0] && $n <= self::AMOUNT_RANGE[1]) {
                    $out[$n] = $n;
                }
            }
        }
        sort($out);
        return array_slice(array_values($out), 0, 12);
    }

    /** @return int[] the public donate page's quick amounts */
    public static function amounts(?array $site = null, string $key = 'donate_amounts'): array
    {
        $site ??= (new \App\Models\Setting())->site();
        return self::parseAmounts((string) ($site[$key] ?? ''));
    }

    /** [CSS family, Google Fonts parameter] of the public body font. */
    public static function bodyFont(?array $site = null): array
    {
        $site ??= (new \App\Models\Setting())->site();
        $f = self::BODY_FONTS[$site['body_font'] ?? ''] ?? self::BODY_FONTS['noto_sans'];
        return [$f[1], $f[2]];
    }

    public static function bodySize(?array $site = null): int
    {
        $site ??= (new \App\Models\Setting())->site();
        return max(self::BODY_SIZE[0], min(self::BODY_SIZE[1], (int) ($site['body_size'] ?? 100)));
    }
}
