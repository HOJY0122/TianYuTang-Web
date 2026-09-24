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

// ---------- Time ----------
// Every date and time in this system means Malaysian local time.
// Servers commonly run in UTC (AWS Lightsail does), and MySQL DATETIME
// columns store no timezone at all — so without this line a registration
// window set to close at 23:59 would actually close at 07:59 the next
// morning. Pin it once, here, and treat everything as local from then on.
define('APP_TIMEZONE', 'Asia/Kuala_Lumpur');
date_default_timezone_set(APP_TIMEZONE);

// ---------- Environment ----------
// Set to false before going live so PHP errors are never shown to visitors.
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// ---------- Session hardening ----------
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');
