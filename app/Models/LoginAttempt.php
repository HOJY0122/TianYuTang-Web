<?php
namespace App\Models;

use App\Core\Model;

/**
 * LoginAttempt — wrong passwords, counted per ACCOUNT.
 *
 * Only a wrong password for a username that really exists is recorded.
 * After MAX_FAILURES within WINDOW_MINUTES that account is locked — on
 * every phone and computer, since the count belongs to the account, not
 * to a device or session — until the oldest failure ages out, the person
 * signs in, or a system admin resets the password.
 *
 * A username that does not exist is told so straight away and costs
 * nothing: there is no account to guess a password for.
 *
 * Stored in the database, not the session: throwing the session cookie
 * away between guesses must not reset the count.
 */
class LoginAttempt extends Model
{
    public const MAX_FAILURES   = 5;
    public const WINDOW_MINUTES = 15;

    /** Has this account used up its wrong passwords? */
    public function isLockedOut(string $username): bool
    {
        return $this->recentFailures($username) >= self::MAX_FAILURES;
    }

    /** How many more wrong passwords this account may take before it locks. */
    public function remaining(string $username): int
    {
        return max(0, self::MAX_FAILURES - $this->recentFailures($username));
    }

    /** Whole minutes until a locked account opens again (at least 1). */
    public function minutesLeft(string $username): int
    {
        $oldest = $this->scalar(
            'SELECT TIMESTAMPDIFF(SECOND, NOW(), MIN(attempted_at) + INTERVAL ' . (int) self::WINDOW_MINUTES . ' MINUTE)
               FROM (SELECT attempted_at FROM login_attempts
                      WHERE username = ? AND attempted_at >= NOW() - INTERVAL ' . (int) self::WINDOW_MINUTES . ' MINUTE
                      ORDER BY attempted_at DESC LIMIT ' . (int) self::MAX_FAILURES . ') AS recent',
            [$username]
        );
        return max(1, (int) ceil(((int) $oldest) / 60));
    }

    /** Record one wrong password for an existing account. */
    public function recordFailure(string $username, string $ip): void
    {
        $this->execute(
            'INSERT INTO login_attempts (ip, username) VALUES (?, ?)',
            [mb_substr($ip, 0, 45), mb_substr($username, 0, 50)]
        );

        // Housekeeping: rows older than the window no longer count for
        // anything, so there is no reason to keep them.
        $this->execute(
            'DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL ' . (int) self::WINDOW_MINUTES . ' MINUTE'
        );
    }

    /** Signed in, or password reset by a system admin: start the count again. */
    public function clear(string $username): void
    {
        $this->execute('DELETE FROM login_attempts WHERE username = ?', [$username]);
    }

    /** Accounts locked right now: username => minutes left (for System → Users). */
    public function lockedAccounts(): array
    {
        $rows = $this->fetchAll(
            'SELECT username FROM login_attempts
              WHERE attempted_at >= NOW() - INTERVAL ' . (int) self::WINDOW_MINUTES . ' MINUTE
              GROUP BY username HAVING COUNT(*) >= ' . (int) self::MAX_FAILURES
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['username']] = $this->minutesLeft($r['username']);
        }
        return $out;
    }

    private function recentFailures(string $username): int
    {
        return (int) $this->scalar(
            'SELECT COUNT(*) FROM login_attempts
             WHERE username = ? AND attempted_at >= NOW() - INTERVAL ' . (int) self::WINDOW_MINUTES . ' MINUTE',
            [$username]
        );
    }
}
