<?php
namespace App\Core;

/**
 * RateLimit — how often one address may do something (e.g. submit the
 * registration form), so a script cannot flood the records.
 *
 * Addresses are stored only as a keyed hash (Crypto::blindIndex): enough to
 * count repeat visits, useless to anyone reading the table.
 */
final class RateLimit
{
    /** Record one attempt and say whether it is still within the limit. */
    public static function allow(string $action, int $max, int $minutes): bool
    {
        $db  = Database::conn();
        $who = Crypto::blindIndex('ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        try {
            $st = $db->prepare('SELECT COUNT(*) FROM rate_limits WHERE action = ? AND who = ? AND created_at > (NOW() - INTERVAL ? MINUTE)');
            $st->execute([$action, $who, $minutes]);
            if ((int) $st->fetchColumn() >= $max) {
                return false;
            }
            $db->prepare('INSERT INTO rate_limits (action, who) VALUES (?, ?)')->execute([$action, $who]);
            if (random_int(1, 50) === 1) {                     // now and then, forget old entries
                $db->exec('DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 1 DAY)');
            }
        } catch (\Throwable $e) {
            return true;                                         // before migration 015: never block people
        }
        return true;
    }

    /**
     * The hidden "website" box on public forms: people never see it, simple
     * bots fill in every field. Filled in → not a person.
     */
    public static function isBot(): bool
    {
        return trim((string) ($_POST['website'] ?? '')) !== '';
    }
}
