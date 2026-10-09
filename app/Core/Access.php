<?php
namespace App\Core;

/**
 * Access — which parts of the admin area each admin may use.
 *
 * A system admin can use everything, always. An ordinary admin can be
 * limited to chosen functions (System → User accounts): e.g. a helper on
 * the day who only does check-in, or someone who should not see the
 * money on the dashboard. admin_users.permissions holds the chosen keys
 * as JSON; NULL means "everything" (accounts made before this existed).
 *
 * The check runs on the SERVER for every admin page and action
 * (Controller::requireAdmin), matched by address — a hidden menu item is
 * only a courtesy. An /admin address that is not listed here is refused
 * to a limited admin, so a page added later is closed until it is listed.
 */
final class Access
{
    /** key => [icon, 中文, English, group, first page]. Same order as the menu. */
    public const MODULES = [
        'dashboard'     => ['chart',        '儀表板',   'Dashboard',        '活動管理 Event',      '/admin/dashboard'],
        'registrations' => ['form',         '報名紀錄', 'Registrations',    '活動管理 Event',      '/admin/registrations'],
        'donations'     => ['coins',        '布施紀錄', 'Donations',        '活動管理 Event',      '/admin/donations'],
        'event'         => ['calendar',     '活動資料', 'Event details',    '活動管理 Event',      '/admin/event/edit'],
        'posts'         => ['newspaper',    '最新消息', 'News',             '活動管理 Event',      '/admin/posts'],
        'photos'        => ['camera',       '相簿',     'Photos',           '活動管理 Event',      '/admin/photos'],
        'checkin'       => ['check-circle', '現場報到', 'Check-in',         '活動當天 On the day', '/admin/checkin'],
        'walkin'        => ['walk',         '現場報名', 'Walk-in register', '活動當天 On the day', '/admin/walkin'],
        'counter'       => ['cash',         '現場布施', 'Counter donation', '活動當天 On the day', '/admin/counter'],
        'receipts'      => ['receipt',      '收據紀錄', 'Receipts',         '財務 Finance',        '/admin/receipts'],
    ];

    /**
     * Address (or the start of one) => the function(s) that open it.
     * Longest match wins; several keys = any one of them is enough.
     */
    private const ROUTES = [
        '/admin/dashboard'        => ['dashboard'],
        '/admin/registrations'    => ['registrations'],
        '/admin/rsvp/'            => ['registrations'],
        '/admin/print/attendees'  => ['registrations'],
        '/admin/export/attendees' => ['registrations'],
        '/admin/donations'        => ['donations'],
        '/admin/donation/'        => ['donations'],
        '/admin/print/donations'  => ['donations'],
        '/admin/export/donations' => ['donations'],
        '/admin/event/'           => ['event'],
        // Stop / resume online responses sits in the bar above the lists too.
        '/admin/event/responses'  => ['event', 'registrations', 'donations'],
        '/admin/qr'               => ['event'],
        '/admin/posts'            => ['posts'],
        '/admin/photos'           => ['photos'],
        '/admin/checkin'          => ['checkin'],
        '/admin/walkin'           => ['walkin'],
        '/admin/counter'          => ['counter'],
        '/admin/receipt'          => ['counter'],     // the counter's printed receipt (not /admin/receipts)
        '/admin/receipts'         => ['receipts'],
        '/admin/no-access'        => [],              // open to every signed-in admin
    ];

    /** Ready-made choices for the account form. */
    public const PRESETS = [
        'all'      => ['全部功能', 'Everything',           null],
        'day'      => ['活動當天', 'On the day',           ['checkin', 'walkin', 'counter']],
        'checkin'  => ['只做報到', 'Check-in only',        ['checkin']],
        'records'  => ['報名與報到', 'Registrations',     ['registrations', 'checkin', 'walkin']],
        'finance'  => ['財務',     'Finance',              ['donations', 'counter', 'receipts']],
        'content'  => ['網站內容', 'News & photos',        ['posts', 'photos', 'event']],
        'nomoney'  => ['不看金額', 'No money',             ['registrations', 'event', 'posts', 'photos', 'checkin', 'walkin']],
    ];

    /** Chosen keys from a form (unknown ones dropped), or null for "everything". */
    public static function fromForm($raw): ?array
    {
        $keys = is_array($raw) ? array_values(array_intersect(array_keys(self::MODULES), array_map('strval', $raw))) : [];
        return count($keys) === count(self::MODULES) ? null : $keys;
    }

    /** Stored JSON → list of keys, or null for "everything". */
    public static function decode(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }
        $list = json_decode($json, true);
        return is_array($list) ? array_values(array_intersect(array_keys(self::MODULES), $list)) : [];
    }

    public static function encode(?array $keys): ?string
    {
        return $keys === null ? null : json_encode(array_values($keys));
    }

    /** May the signed-in person use this function? */
    public static function can(string $module): bool
    {
        if (($_SESSION['admin_role'] ?? '') === 'system_admin') {
            return true;
        }
        $allowed = $_SESSION['admin_perms'] ?? null;
        return $allowed === null || in_array($module, (array) $allowed, true);
    }

    /** Is the signed-in admin limited at all? */
    public static function limited(): bool
    {
        return ($_SESSION['admin_role'] ?? '') !== 'system_admin' && ($_SESSION['admin_perms'] ?? null) !== null;
    }

    /** May the signed-in person open this address? */
    public static function allowsPath(string $path): bool
    {
        if (!self::limited() || !str_starts_with($path, '/admin')) {
            return true;
        }
        // Longest matching start wins: /admin/receipts (receipt book) beats
        // /admin/receipt (the counter's printed receipt).
        $best = null;
        foreach (array_keys(self::ROUTES) as $prefix) {
            if (str_starts_with($path, $prefix) && ($best === null || strlen($prefix) > strlen($best))) {
                $best = $prefix;
            }
        }
        if ($best === null) {
            return false;                       // not listed: closed to limited admins
        }
        $mods = self::ROUTES[$best];
        if (!$mods) {
            return true;
        }
        foreach ($mods as $m) {
            if (self::can($m)) {
                return true;
            }
        }
        return false;
    }

    /** First page this person may open (their "home"). */
    public static function home(): string
    {
        if (($_SESSION['admin_role'] ?? '') === 'system_admin') {
            return '/system';
        }
        foreach (self::MODULES as $key => $m) {
            if (self::can($key)) {
                return $m[4];
            }
        }
        return '/admin/no-access';
    }

    /** "報到、現場報名" — the chosen functions in words (null = everything). */
    public static function describe(?array $keys): string
    {
        if ($keys === null) {
            return '全部功能 Everything';
        }
        if (!$keys) {
            return '沒有功能 Nothing';
        }
        return implode('、', array_map(static fn($k) => self::MODULES[$k][1], $keys));
    }
}
