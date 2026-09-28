<?php
namespace App\Core;

/**
 * Live — realtime updates for every open page (public, admin, system admin).
 *
 * The same idea as SignalR, built for PHP on ordinary hosting:
 *
 *   1. Every save bumps a counter for the table it touched (touch(), called
 *      from Model::execute). The bumps are written once, when the request
 *      ends, so a page never sees half of a save that touches two tables.
 *   2. Open pages ask GET /live?t=posts,donations every few seconds
 *      (public/assets/js/live.js). The answer is just the counters.
 *   3. When a counter has moved, the page fetches itself again and swaps
 *      in the parts marked data-live="<tables>" — never a part someone is
 *      typing in.
 *
 * Why not a push connection (SignalR, WebSockets, Server-Sent Events)?
 * Each open connection keeps a PHP process busy for as long as the page
 * is open; on shared hosting a few dozen visitors would use up every
 * worker and the site would stop answering. A counter check is one
 * indexed read that finishes in a millisecond.
 */
final class Live
{
    /** Tables whose changes do not concern any page. */
    private const IGNORE = ['live_versions', 'login_attempts', 'schema_migrations', 'ocr_corrections'];

    /**
     * What visitors who are not signed in may ask about. Only "something
     * changed" is ever revealed, never what; admin-only tables stay out.
     */
    public const PUBLIC_TOPICS = ['events', 'posts', 'event_photos', 'site_banners', 'settings'];

    private static array $touched = [];
    private static bool $hooked = false;

    /** Note that a statement wrote to a table (the SQL is only read, never changed). */
    public static function touchFromSql(string $sql): void
    {
        if (preg_match('/^\s*(?:INSERT\s+(?:IGNORE\s+)?INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM|ALTER\s+TABLE|TRUNCATE(?:\s+TABLE)?)\s+`?(\w+)`?/i', $sql, $m)) {
            self::touch($m[1]);
        }
    }

    public static function touch(string $table): void
    {
        $table = strtolower($table);
        if (in_array($table, self::IGNORE, true)) {
            return;
        }
        self::$touched[$table] = true;
        if (!self::$hooked) {
            self::$hooked = true;
            register_shutdown_function([self::class, 'flush']);
        }
    }

    /** Write the bumps (end of request). A missing table (before migration 014) is ignored. */
    public static function flush(): void
    {
        if (!self::$touched) {
            return;
        }
        try {
            $db = Database::conn();
            $st = $db->prepare('INSERT INTO live_versions (topic, version) VALUES (?, 1)
                                ON DUPLICATE KEY UPDATE version = version + 1');
            foreach (array_keys(self::$touched) as $topic) {
                $st->execute([$topic]);
            }
        } catch (\Throwable $e) {
            // Realtime is a convenience: never let it break a save.
        }
        self::$touched = [];
    }

    /** Current counters for the given topics (0 for a table never changed). */
    public static function versions(array $topics): array
    {
        $topics = array_values(array_unique(array_filter($topics, static fn($t) => preg_match('/^\w{1,40}$/', $t))));
        if (!$topics) {
            return [];
        }
        $out = array_fill_keys($topics, 0);
        try {
            $st = Database::conn()->prepare('SELECT topic, version FROM live_versions WHERE topic IN ('
                . implode(',', array_fill(0, count($topics), '?')) . ')');
            $st->execute($topics);
            foreach ($st->fetchAll() as $r) {
                $out[$r['topic']] = (int) $r['version'];
            }
        } catch (\Throwable $e) {
        }
        return $out;
    }
}
