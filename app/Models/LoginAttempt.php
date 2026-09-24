<?php
namespace App\Models;

use App\Core\Model;

/**
 * LoginAttempt — slows down password guessing on the admin login.
 *
 * Every FAILED login is recorded against the visitor's IP address. Once
 * an address reaches MAX_FAILURES within WINDOW_MINUTES, further
 * attempts are refused until the oldest failure ages out.
 *
 * Stored in the database, not the session, on purpose: an attacker
 * simply throws the session cookie away between guesses, so a
 * session-based counter would never count past one.
 *
 * Keyed by IP rather than username so that nobody can lock the real
 * committee out of an account just by typing its name wrongly five
 * times from somewhere else.
 */
class LoginAttempt extends Model
{
    public const MAX_FAILURES   = 5;
    public const WINDOW_MINUTES = 15;

    /** Has this address used up its allowance of wrong guesses? */
    public function isLockedOut(string $ip): bool
    {
        return $this->recentFailures($ip) >= self::MAX_FAILURES;
    }

    /**
     * How many more wrong passwords this address may try before being
     * locked out. Counted per address, not per username, so it says
     * nothing about which usernames exist.
     */
    public function remaining(string $ip): int
    {
        return max(0, self::MAX_FAILURES - $this->recentFailures($ip));
    }

    /** Record one failed attempt. */
    public function recordFailure(string $ip, string $username): void
    {
        $this->execute(
            'INSERT INTO login_attempts (ip, username) VALUES (?, ?)',
            [$ip, mb_substr($username, 0, 50)]
        );

        // Housekeeping: rows older than the window no longer count for
        // anything, so there is no reason to keep them.
        $this->execute(
            'DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL ' . (int) self::WINDOW_MINUTES . ' MINUTE'
        );
    }

    /** A successful login wipes the slate clean for that address. */
    public function clear(string $ip): void
    {
        $this->execute('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
    }

    private function recentFailures(string $ip): int
    {
        return (int) $this->scalar(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip = ? AND attempted_at >= NOW() - INTERVAL ' . (int) self::WINDOW_MINUTES . ' MINUTE',
            [$ip]
        );
    }
}
