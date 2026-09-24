<?php
/**
 * Global helper functions, available in controllers and views.
 */

/** Escape a value for safe output inside HTML. Use on EVERY dynamic value. */
function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Build a URL for a route path, correct whether the site sits at the
 *  domain root or inside a subfolder. */
function url(string $path = '/'): string
{
    $path = '/' . ltrim($path, '/');
    return BASE_URL . $path;
}

/** Build a URL for a file under public/assets. */
function asset(string $path): string
{
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

/** Current CSRF token, generated once per session. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input carrying the CSRF token — drop this inside every <form>. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

/** Format an amount as Malaysian Ringgit. */
function rm(float $amount): string
{
    return 'RM ' . number_format($amount, 2);
}

/**
 * Hide all but the last 4 characters of an IC / passport number.
 * Used anywhere a number is shown back to a visitor — a confirmation
 * page can be seen over a shoulder or left open on a shared phone.
 */
function mask_ic(?string $ic): string
{
    $ic = (string) $ic;
    $len = mb_strlen($ic);
    if ($len <= 4) {
        return $ic;
    }
    // A fixed-length mask also hides how long the number is.
    return '****' . mb_substr($ic, -4);
}

/** Short Ringgit for headline tiles: RM 12,300 (cents only when there are any). */
function rm_compact(float $amount): string
{
    $whole = abs($amount - round($amount)) < 0.005;
    return 'RM ' . number_format($amount, $whole ? 0 : 2);
}

/**
 * An ⓘ button with a short bilingual explanation that opens on tap.
 * Used beside fields a visitor may hesitate over ("why do you need my
 * IC?"). The click handling lives in layouts/header.php.
 */
function info_tip(string $zh, string $en): string
{
    static $n = 0;
    $id = 'info' . (++$n);
    return '<button type="button" class="info-btn" aria-expanded="false" aria-controls="' . $id . '" title="說明 More info">'
         . '<span aria-hidden="true">i</span><span class="sr-only">說明 More info</span></button>'
         . '<span class="info-pop" id="' . $id . '" role="note" hidden>' . h($zh) . '<span class="en">' . h($en) . '</span></span>';
}

/** Site wording (see App\Core\Text) — plain text, not yet escaped. */
function t(string $key, string $lang = 'zh', array $vars = []): string
{
    return App\Core\Text::get($key, $lang, $vars);
}

/**
 * Bilingual wording as HTML: the Chinese, then the English in a
 * <span class="en"> (left out when the English has been cleared).
 */
function tb(string $key, array $vars = []): string
{
    $en = t($key, 'en', $vars);
    return h(t($key, 'zh', $vars)) . ($en !== '' ? '<span class="en">' . h($en) . '</span>' : '');
}

/**
 * A sortable column heading for admin lists: click to sort by this
 * column, click again to flip ascending / descending. The arrow shows
 * the current direction. $query is the list's current filters.
 */
function sort_th(string $label, string $key, array $query, string $path, string $default = 'desc', string $class = ''): string
{
    $active = ($query['sort'] ?? '') === $key;
    $dir    = $active ? (($query['dir'] ?? 'desc') === 'asc' ? 'desc' : 'asc') : $default;
    $href   = url($path) . '?' . http_build_query(array_merge($query, ['sort' => $key, 'dir' => $dir, 'page' => 1]));
    $arrow  = $active ? (($query['dir'] ?? 'desc') === 'asc' ? '▲' : '▼') : '⇅';
    $aria   = $active ? (($query['dir'] ?? 'desc') === 'asc' ? 'ascending' : 'descending') : 'none';
    return '<th' . ($class ? ' class="' . h($class) . '"' : '') . ' aria-sort="' . $aria . '"><a class="sort-link' . ($active ? ' is-active' : '') . '" href="' . h($href) . '"'
         . ' title="排序 Sort">' . $label . ' <span class="sort-arrow" aria-hidden="true">' . $arrow . '</span></a></th>';
}
