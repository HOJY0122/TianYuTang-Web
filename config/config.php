<?php
/**
 * 天玉堂 2026 — Application configuration
 *
 * This is the ONLY file you need to edit when moving between machines
 * (local XAMPP → shared hosting → VPS).
 */

// ---------- Database ----------
define('DB_HOST', 'localhost');
define('DB_NAME', 'tianyutang2026');
define('DB_USER', 'root');   // change on real hosting
define('DB_PASS', '');       // change on real hosting

// ---------- Site ----------
// Fallback name only. The event name, dates, venue, merit-seat price and
// attendee limit now live on the active row in the `events` table, so the
// committee edits them in admin rather than in this file. Do not
// reintroduce them here — two sources of truth is how they drift apart.
define('SITE_NAME', '天玉堂');

// ---------- AI receipt reading (optional) ----------
// 收據掃描 Receipts can read a photo of a handwritten receipt with Claude
// (Anthropic). The EASIEST way: system admin → 網站設定 Site settings →
// ⑤ AI, paste the key there and press 🔌 Test connection.
//
// Or paste it here, between the two quotes on the right:
//     define('ANTHROPIC_API_KEY', 'sk-ant-api03-xxxxxxxx');
// Left empty, the server's ANTHROPIC_API_KEY environment variable or the
// key saved in Site settings is used. With no key, receipts are typed in.
// Keep a real key out of git: it is a password that costs money.
define('ANTHROPIC_API_KEY', '');
define('ANTHROPIC_MODEL', 'claude-opus-5');
define('ANTHROPIC_API_URL', '');   // leave empty (only changed for testing)

// ---------- Time ----------
// Every date and time in this system means Malaysian local time.
// Servers commonly run in UTC (AWS Lightsail does), and MySQL DATETIME
// columns store no timezone at all — so without this line a registration
// window set to close at 23:59 would actually close at 07:59 the next
// morning. Pin it once, here, and treat everything as local from then on.
define('APP_TIMEZONE', 'Asia/Kuala_Lumpur');
date_default_timezone_set(APP_TIMEZONE);

// ---------- Environment ----------
// Off by default, so a fresh upload never shows PHP errors (and the SQL
// inside them) to visitors. Turn it on only on your own machine while
// developing — and never commit it as true.
define('DEBUG_MODE', false);

if (DEBUG_MODE) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    // Hidden from visitors, but still written to the server's error log
    // so a problem on the live site can actually be diagnosed.
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

// ---------- Session hardening ----------
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');

// Over HTTPS, tell the browser never to send the session cookie over
// plain HTTP, where anyone on the same wifi could read it and take over
// an admin login. Decided per request rather than hard-coded, so local
// http://localhost testing still works.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
unset($isHttps);
