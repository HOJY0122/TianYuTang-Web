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
