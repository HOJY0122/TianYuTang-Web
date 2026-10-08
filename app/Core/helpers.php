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

/**
 * An icon from public/assets/icons.svg, e.g. icon('save').
 * SVG line icons instead of emoji: they look the same on every phone and
 * computer (older devices often show emoji as empty boxes), take the text
 * colour, and scale with the text. Decorative: hidden from screen readers.
 */
function icon(string $name, string $class = ''): string
{
    static $v = null;
    $v ??= substr((string) @md5_file(BASE_PATH . '/public/assets/icons.svg'), 0, 8);
    return '<svg class="ic' . ($class !== '' ? ' ' . h($class) : '') . '" aria-hidden="true" focusable="false">'
         . '<use href="' . h(asset('icons.svg')) . '?v=' . $v . '#' . h($name) . '"></use></svg>';
}

/** Address of an uploaded photo: signed and expiring for albums, posts and banners (App\Core\Media). */
function media_url(?string $path): string
{
    return App\Core\Media::url($path);
}

/**
 * <img> for a public photo. When its part of the site is protected
 * (albums / news), the tag carries the SHUFFLED copy for js/photos.js to
 * put back together on a canvas; otherwise a normal signed image.
 * $attrs: extra attributes (class, alt, data-…), escaped here.
 */
function photo_img(string $path, string $area, array $attrs = []): string
{
    $guard = App\Core\Media::guarded($area);
    if ($guard) {
        $attrs['class']    = trim(($attrs['class'] ?? '') . ' scr');
        $attrs['data-src'] = App\Core\Media::url($path, true);
        $attrs['data-scr'] = (string) App\Core\Media::seed($path);
        $attrs['src']      = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
    } else {
        $attrs['src'] = App\Core\Media::url($path);
    }
    $out = '<img';
    foreach ($attrs as $k => $v) {
        $out .= ' ' . h($k) . '="' . h((string) $v) . '"';
    }
    return $out . ' draggable="false">';
}

/** Address + key the full-size viewer needs for a photo: data-full / data-seed attributes. */
function photo_full_attrs(string $path, string $area): string
{
    return App\Core\Media::guarded($area)
        ? 'data-full="' . h(App\Core\Media::url($path, true)) . '" data-seed="' . App\Core\Media::seed($path) . '"'
        : 'data-full="' . h(App\Core\Media::url($path)) . '"';
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

/**
 * 1 → 一, 12 → 十二, 25 → 二十五 — for "第一位" headings, which read
 * naturally in the brush heading font where Latin digits do not.
 */
function zh_num(int $n): string
{
    $d = ['', '一', '二', '三', '四', '五', '六', '七', '八', '九'];
    if ($n <= 0 || $n >= 100) {
        return (string) $n;
    }
    if ($n < 10) {
        return $d[$n];
    }
    return ($n >= 20 ? $d[intdiv($n, 10)] : '') . '十' . $d[$n % 10];
}

/**
 * Realtime fingerprint for a part of a page (see js/live.js).
 * Wrap the part's content in live_sig_start() … live_sig_end(): a hidden
 * marker with a hash of exactly what was sent is added, so an open page
 * reloads only when that part would really look different — not on every
 * change to the tables it watches.
 */
function live_sig_start(): void
{
    ob_start();
}

function live_sig_end(): void
{
    $html = (string) ob_get_clean();
    echo $html, '<i hidden data-live-sig="', substr(md5($html), 0, 16), '"></i>';
}

/**
 * The site's host name, for full addresses (printed QR codes, the HTTPS
 * redirect). SITE_URL in config.php wins; otherwise the request's Host
 * header — but only if it looks like a host name, so a forged header
 * cannot put someone else's address on a QR poster.
 */
function site_host(): string
{
    if (defined('SITE_URL') && SITE_URL !== '') {
        return (string) parse_url(SITE_URL, PHP_URL_HOST) . (parse_url(SITE_URL, PHP_URL_PORT) ? ':' . parse_url(SITE_URL, PHP_URL_PORT) : '');
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (!preg_match('/^[A-Za-z0-9.\-]{1,253}(:\d{1,5})?$/', $host)) {
        $host = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
    }
    return $host;
}

/** Full address of the site's home page, e.g. https://tianyutang.org/ */
function site_url(): string
{
    if (defined('SITE_URL') && SITE_URL !== '') {
        return rtrim(SITE_URL, '/') . '/';
    }
    return (App\Core\Session::isHttps() ? 'https' : 'http') . '://' . site_host() . BASE_URL . '/';
}

