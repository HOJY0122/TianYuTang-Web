<?php
/**
 * Database migration runner — brings the database up to date with the code.
 *
 * Run from the project root:
 *
 *     php bin/migrate.php            run every migration not applied yet
 *     php bin/migrate.php --status   list applied / pending, change nothing
 *     php bin/migrate.php --yes      run without the "are you sure?" prompt
 *
 * HOW IT KNOWS WHAT HAS RUN
 *   A small table, schema_migrations, records each file once it succeeds.
 *   Next time that file is skipped. This matters because most migrations
 *   are NOT safe to run twice — "ALTER TABLE … ADD COLUMN" fails the
 *   second time, and 005 resets every account's role.
 *
 *   Databases set up before this runner existed have no such table, so
 *   on the very first run it LOOKS at the database instead (does the
 *   column that 004 adds exist? then 004 has run) and records what it
 *   finds as "detected". From then on the table is the only record.
 *
 * WHY NOT JUST THE mysql COMMAND?
 *   The .sql files still work by hand. This adds three things: it
 *   remembers what ran, it stops at the first error, and it ignores each
 *   file's "USE tianyutang2026;" and uses DB_NAME from config/config.php —
 *   so it works on hosting where the database is called something like
 *   "myacc_tianyutang".
 *
 * Command line only. It cannot be opened in a browser.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config/config.php';
require BASE_PATH . '/app/Core/Database.php';

use App\Core\Database;

/**
 * How to recognise, on a database older than this runner, that each
 * existing migration has already been applied: [table, column]. A null
 * column means "the table exists". Only used on the first run.
 *
 * schema.sql now creates schema_migrations itself and records every
 * migration it contains, so a fresh install never needs this list, and
 * new migrations need no entry here — only in schema.sql.
 */
const ALREADY_APPLIED_IF = [
    '001_add_events.sql'              => ['rsvp_groups',    'event_id'],
    '002_add_event_photos.sql'        => ['event_photos',   null],
    '003_add_checkin.sql'             => ['rsvp_attendees', 'checked_in_at'],
    '004_add_counter_donations.sql'   => ['donations',      'source'],
    '005_add_roles_and_settings.sql'  => ['admin_users',    'role'],
    '006_split_roles_and_walkin.sql'  => ['rsvp_groups',    'source'],
    '007_add_login_attempts.sql'      => ['login_attempts', null],
    '008_site_content_and_mixed_donations.sql' => ['posts', null],
];

$args      = array_slice($argv, 1);
$statusOnly = in_array('--status', $args, true);
$assumeYes  = in_array('--yes', $args, true) || in_array('-y', $args, true);

if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
    echo "Usage: php bin/migrate.php [--status] [--yes]\n";
    exit(0);
}

$db = Database::conn();
echo "Database: " . DB_NAME . " on " . DB_HOST . "\n\n";

// ---------- 1. Is there a database to migrate at all? ----------
if (!tableExists($db, 'admin_users')) {
    fail("This database is empty. Import schema.sql first (it already contains\n"
       . "every migration), then run this command to start tracking.");
}

// ---------- 2. The record of what has run ----------
// Nothing is WRITTEN until the person has confirmed (or there is nothing
// to confirm). Answering "no" must leave the database exactly as it was.
$firstRun = !tableExists($db, 'schema_migrations');
if ($firstRun) {
    echo "(First run: nothing is recorded yet, so this is worked out by\n"
       . " inspecting the tables.)\n\n";
}

$files = glob(BASE_PATH . '/migrations/*.sql');
sort($files, SORT_STRING);   // 001, 002, … — the numbers ARE the order

$applied = $firstRun ? [] : array_column(
    $db->query('SELECT filename, how FROM schema_migrations')->fetchAll(),
    'how',
    'filename'
);

// First run on an existing database: note what is already there.
$detected = [];
if ($firstRun) {
    foreach ($files as $path) {
        $name = basename($path);
        [$table, $column] = ALREADY_APPLIED_IF[$name] ?? [null, null];
        $present = $table !== null
            && ($column === null ? tableExists($db, $table) : columnExists($db, $table, $column));
        if ($present) {
            $applied[$name] = 'detected';
            $detected[]     = $name;
        }
    }
}

// ---------- 3. Status ----------
$pending = [];
foreach ($files as $path) {
    $name = basename($path);
    if (isset($applied[$name])) {
        echo "  [done]     {$name}" . ($applied[$name] === 'detected' ? '   (already in the database)' : '') . "\n";
    } else {
        echo "  [PENDING]  {$name}\n";
        $pending[] = $path;
    }
}
echo "\n";

if (!$pending) {
    if (!$statusOnly) {
        startTracking($db, $firstRun, $detected);
    }
    echo "Up to date — nothing to run.\n";
    exit(0);
}
if ($statusOnly) {
    echo count($pending) . " pending. Run `php bin/migrate.php` to apply.\n";
    exit(0);
}

// ---------- 4. Confirm ----------
// Schema changes cannot be rolled back by MySQL, so a backup is the
// only real undo. Say so every time, not just in a README.
echo count($pending) . " migration(s) will run, in the order above.\n";
echo "BACK UP FIRST:  mysqldump -u " . DB_USER . " -p " . DB_NAME . " > backup.sql\n\n";

if (!$assumeYes) {
    echo "Type yes to continue: ";
    $answer = trim((string) fgets(STDIN));
    if (strtolower($answer) !== 'yes') {
        echo "Cancelled. Nothing was changed.\n";
        exit(0);
    }
    echo "\n";
}

// ---------- 5. Run ----------
startTracking($db, $firstRun, $detected);

foreach ($pending as $path) {
    $name = basename($path);
    echo "=== {$name}\n";

    $statements = splitSql((string) file_get_contents($path));

    foreach ($statements as $i => $sql) {
        // The runner is already connected to DB_NAME. Obeying the file's
        // own USE line could switch to a database of the wrong name.
        if (preg_match('/^USE\s/i', $sql)) {
            continue;
        }

        try {
            if (preg_match('/^(SELECT|SHOW|DESCRIBE)\s/i', $sql)) {
                // The files end with "verify" queries — show their answer.
                printRows($db->query($sql)->fetchAll());
            } else {
                $db->exec($sql);
            }
        } catch (PDOException $e) {
            // Earlier statements in this file may already have taken
            // effect (MySQL commits ALTER TABLE immediately), so the file
            // is NOT recorded — and the person is told exactly where it
            // stopped.
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            fail(
                "FAILED in {$name}, statement " . ($i + 1) . " of " . count($statements) . ":\n\n"
                . '    ' . preview($sql) . "\n\n"
                . 'MySQL said: ' . $e->getMessage() . "\n\n"
                . "This migration was NOT marked as done. Statements before this one\n"
                . "may already have run. Fix the cause (or restore your backup), then\n"
                . "run this command again. Later migrations were not touched."
            );
        }
    }

    record($db, $name, 'ran');
    echo "    ok\n\n";
}

echo "Done — the database is up to date.\n";
exit(0);


// ======================================================================
// helpers
// ======================================================================

function tableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function columnExists(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Create schema_migrations (first run only) and record what was detected. */
function startTracking(PDO $db, bool $firstRun, array $detected): void
{
    if (!$firstRun) {
        return;
    }
    $db->exec(
        "CREATE TABLE schema_migrations (
            filename   VARCHAR(191) PRIMARY KEY,
            how        ENUM('ran','detected') NOT NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    foreach ($detected as $name) {
        record($db, $name, 'detected');
    }
}

function record(PDO $db, string $filename, string $how): void
{
    $db->prepare('INSERT INTO schema_migrations (filename, how) VALUES (?, ?)')
       ->execute([$filename, $how]);
}

/**
 * Split a .sql file into single statements.
 *
 * Not just explode(';') — a semicolon inside a quoted string or a
 * comment is not the end of a statement. This walks the text once,
 * remembering whether it is inside quotes or a comment.
 *
 * @return string[]
 */
function splitSql(string $sql): array
{
    $statements = [];
    $current    = '';
    $len        = strlen($sql);
    $quote      = null;   // ' " or ` while inside a quoted string

    for ($i = 0; $i < $len; $i++) {
        $ch   = $sql[$i];
        $next = $sql[$i + 1] ?? '';

        if ($quote !== null) {
            $current .= $ch;
            if ($ch === '\\' && $quote !== '`') {        // \' inside a string
                $current .= $next;
                $i++;
            } elseif ($ch === $quote) {
                $quote = null;                            // '' re-opens next loop
            }
            continue;
        }

        // "-- comment" (MySQL needs the space) or "# comment": skip to end of line.
        if (($ch === '-' && $next === '-' && ctype_space($sql[$i + 2] ?? ' ')) || $ch === '#') {
            $end = strpos($sql, "\n", $i);
            $i   = $end === false ? $len : $end;
            $current .= "\n";
            continue;
        }
        // "/* comment */"
        if ($ch === '/' && $next === '*') {
            $end = strpos($sql, '*/', $i + 2);
            $i   = $end === false ? $len : $end + 1;
            $current .= ' ';
            continue;
        }

        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
        }

        if ($ch === ';') {
            $statements[] = trim($current);
            $current = '';
            continue;
        }
        $current .= $ch;
    }
    $statements[] = trim($current);

    return array_values(array_filter($statements, static fn($s) => $s !== ''));
}

/** Print query results as a small aligned table. */
function printRows(array $rows): void
{
    if (!$rows) {
        echo "    (no rows)\n";
        return;
    }
    $cols   = array_keys($rows[0]);
    $widths = [];
    foreach ($cols as $c) {
        $widths[$c] = mb_strwidth((string) $c);
        foreach ($rows as $r) {
            $widths[$c] = max($widths[$c], mb_strwidth(cell($r[$c])));
        }
    }
    $line = static function (array $values) use ($cols, $widths): string {
        $out = [];
        foreach ($cols as $k => $c) {
            $v     = $values[$k];
            $out[] = $v . str_repeat(' ', $widths[$c] - mb_strwidth($v));
        }
        return '    ' . rtrim(implode('  ', $out)) . "\n";
    };
    echo $line(array_map('strval', $cols));
    foreach ($rows as $r) {
        echo $line(array_map('cell', array_values($r)));
    }
}

function cell($value): string
{
    $s = $value === null ? 'NULL' : (string) $value;
    return mb_strimwidth(str_replace(["\r", "\n"], ' ', $s), 0, 60, '…');
}

function preview(string $sql): string
{
    $flat = preg_replace('/\s+/', ' ', $sql);
    return mb_strimwidth($flat, 0, 200, ' …');
}

function fail(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}
