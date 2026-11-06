<?php

/**
 * 天玉堂 2026 — Application configuration
 *
 * This file is PUBLIC (it is in git). It holds safe defaults only.
 *
 * Real values for a machine — database password, APP_KEY, FORCE_HTTPS,
 * SITE_URL, API keys — go in config/config.local.php, which git ignores.
 * Copy config/config.local.example.php to config.local.php to start.
 * Anything defined there wins over the default below.
 */

if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// ---------- Database ----------
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'tianyutang2026');
defined('DB_USER') || define('DB_USER', 'root');   // set the real one in config.local.php
defined('DB_PASS') || define('DB_PASS', '');       // set the real one in config.local.php

// ---------- Site ----------
// Fallback name only. The event name, dates, venue, merit-seat price and
// attendee limit now live on the active row in the `events` table, so the
// committee edits them in admin rather than in this file. Do not
// reintroduce them here — two sources of truth is how they drift apart.
defined('SITE_NAME') || define('SITE_NAME', '天玉堂');

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
// Put it in Site settings → ⑤ AI (stored encrypted), never in this file.
defined('ANTHROPIC_API_KEY') || define('ANTHROPIC_API_KEY', '');
defined('ANTHROPIC_MODEL') || define('ANTHROPIC_MODEL', 'claude-opus-5');
defined('ANTHROPIC_API_URL') || define('ANTHROPIC_API_URL', '');   // leave empty (only changed for testing)
// NVIDIA (build.nvidia.com) instead of Anthropic: choose it in Site
// settings → ⑤ AI, and paste its key there (never in this file).
defined('NVIDIA_API_KEY') || define('NVIDIA_API_KEY', '');      // e.g. 'nvapi-xxxxxxxx'
defined('NVIDIA_API_URL') || define('NVIDIA_API_URL', '');      // leave empty (only changed for testing)
// Google Cloud Vision (free OCR, first 1,000 scans a month): choose it in
// Site settings → ⑤ AI, and paste its key there (never in this file).
defined('GOOGLE_VISION_API_KEY') || define('GOOGLE_VISION_API_KEY', '');   // e.g. 'AIzaSy…'
defined('GOOGLE_VISION_URL') || define('GOOGLE_VISION_URL', '');       // leave empty (only changed for testing)

// ---------- Security ----------
// The site's public address, e.g. 'https://www.tianyutang.org' (no trailing
// slash). Used on printed QR codes. Leave empty to use the address the
// page was opened with (checked to be a proper host name).
defined('SITE_URL') || define('SITE_URL', '');
// true on the real server once HTTPS works: every http:// visit is sent to
// https://, and browsers are told to stay on HTTPS.
defined('FORCE_HTTPS') || define('FORCE_HTTPS', false);
// Encryption key for IC numbers and AI keys (base64 of 32 random bytes).
// Leave empty to use storage/keys/app.key, created automatically.
// Either way: BACK IT UP WITH THE DATABASE — without it that data is unreadable.
defined('APP_KEY') || define('APP_KEY', '');

// ---------- Time ----------
// Every date and time in this system means Malaysian local time.
// Servers commonly run in UTC (AWS Lightsail does), and MySQL DATETIME
// columns store no timezone at all — so without this line a registration
// window set to close at 23:59 would actually close at 07:59 the next
// morning. Pin it once, here, and treat everything as local from then on.
defined('APP_TIMEZONE') || define('APP_TIMEZONE', 'Asia/Kuala_Lumpur');
date_default_timezone_set(APP_TIMEZONE);

// ---------- Environment ----------
// Off by default, so a fresh upload never shows PHP errors (and the SQL
// inside them) to visitors. Turn it on only on your own machine while
// developing — and never commit it as true.
defined('DEBUG_MODE') || define('DEBUG_MODE', false);

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
