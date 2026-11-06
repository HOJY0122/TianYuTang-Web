<?php
/**
 * config.local.php — this machine's real settings. NEVER commit it.
 *
 *   cp config/config.local.example.php config/config.local.php
 *   chmod 600 config/config.local.php
 *
 * git ignores config/config.local.php. Anything defined here wins over
 * the safe default in config/config.php; leave out what you don't change.
 * AI keys do NOT belong here: paste them in Site settings → ⑤ AI, where
 * they are stored encrypted.
 */

// ---------- Database ----------
define('DB_HOST', 'localhost');
define('DB_NAME', 'tianyutang2026');
define('DB_USER', 'tyt_app');          // a user with rights on this database only
define('DB_PASS', 'change-me');

// ---------- Real server ----------
// define('FORCE_HTTPS', true);
// define('SITE_URL', 'https://your-domain');
