<?php
namespace App\Core;

/**
 * Session — how sign-ins are kept, and when they end.
 *
 *   start()       the cookie: its own name, only for this site's folder,
 *                 HttpOnly (scripts cannot read it), SameSite=Lax (other
 *                 sites cannot send it with their forms), Secure on HTTPS,
 *                 never in a URL, ids refused unless this server made them.
 *   signIn()      after a correct password: a brand-new session id and a
 *                 new form token (so nothing from before the sign-in can be
 *                 reused), and the facts checked below.
 *   guard()       on every staff page:
 *                   - 60 minutes without activity, or 12 hours after
 *                     signing in → signed out
 *                   - used from a different browser than the one that
 *                     signed in (a stolen cookie) → signed out
 *                   - password or role changed, or account removed, since
 *                     signing in → signed out (session_version)
 *                   - "one device per account" switched on (Site
 *                     settings) and the account signed in again on
 *                     another device since → signed out ('device')
 *                   - a fresh session id every 15 minutes, so an id seen
 *                     once soon stops working
 *                   - pages are never stored by the browser or a proxy, so
 *                     Back after signing out shows nothing
 *   signOut()     wipes the session and its cookie.
 */
final class Session
{
    public const IDLE_MINUTES     = 60;
    public const ABSOLUTE_HOURS   = 12;
    private const ROTATE_SECONDS  = 900;

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    }

    public static function start(): void
    {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) (self::ABSOLUTE_HOURS * 3600));
        session_name('TYTSESS');
        session_set_cookie_params([
            'lifetime' => 0,                                  // gone when the browser closes
            'path'     => (BASE_URL === '' ? '' : BASE_URL) . '/',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function signIn(array $user): void
    {
        session_regenerate_id(true);
        $now = time();
        $_SESSION['admin_id']        = (int) $user['id'];
        $_SESSION['admin_username']  = $user['username'];
        $_SESSION['admin_role']      = $user['role'] ?? 'admin';
        $_SESSION['admin_display']   = ($user['display_name'] ?? '') ?: $user['username'];
        $_SESSION['session_version'] = (int) ($user['session_version'] ?? 0);
        $_SESSION['signed_in_at']    = $now;
        $_SESSION['last_seen']       = $now;
        $_SESSION['rotated_at']      = $now;
        $_SESSION['browser']         = self::browser();
        $_SESSION['csrf_token']      = bin2hex(random_bytes(32));

        // This device's token. Only its hash is kept in the database; a
        // sign-in elsewhere replaces it (see guard() and singleDevice()).
        $token = bin2hex(random_bytes(32));
        $_SESSION['device_token'] = $token;
        (new \App\Models\AdminUser())->setDeviceToken((int) $user['id'], hash('sha256', $token));
    }

    /** Site settings → "one device per account": a new sign-in signs the older device out. */
    public static function singleDevice(): bool
    {
        return (new \App\Models\Setting())->get('single_device', '0') === '1';
    }

    /** Has this account signed in on another device since this session began? */
    public static function replacedElsewhere(?array $state = null): bool
    {
        $state ??= (new \App\Models\AdminUser())->sessionState((int) ($_SESSION['admin_id'] ?? 0));
        if ($state === null) {
            return true;
        }
        $mine = hash('sha256', (string) ($_SESSION['device_token'] ?? ''));
        return !hash_equals((string) ($state['device_token'] ?? ''), $mine);
    }

    /**
     * Check a staff session; returns null if all is well, or the reason it
     * was ended ('idle', 'expired', 'browser', 'revoked', 'device').
     */
    public static function guard(): ?string
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');

        $now = time();
        $reason = null;
        if ($now - (int) ($_SESSION['last_seen'] ?? 0) > self::IDLE_MINUTES * 60) {
            $reason = 'idle';
        } elseif ($now - (int) ($_SESSION['signed_in_at'] ?? 0) > self::ABSOLUTE_HOURS * 3600) {
            $reason = 'expired';
        } elseif (!hash_equals((string) ($_SESSION['browser'] ?? ''), self::browser())) {
            $reason = 'browser';
        } else {
            $state = (new \App\Models\AdminUser())->sessionState((int) $_SESSION['admin_id']);
            if ($state === null || (int) $state['session_version'] !== (int) ($_SESSION['session_version'] ?? -1)) {
                $reason = 'revoked';
            } elseif (self::singleDevice() && self::replacedElsewhere($state)) {
                $reason = 'device';
            } else {
                $_SESSION['admin_role'] = $state['role'];     // a role change applies at once
            }
        }
        if ($reason !== null) {
            self::signOut();
            return $reason;
        }

        $_SESSION['last_seen'] = $now;
        if ($now - (int) ($_SESSION['rotated_at'] ?? 0) > self::ROTATE_SECONDS) {
            session_regenerate_id(true);
            $_SESSION['rotated_at'] = $now;
        }
        return null;
    }

    /** Is there a live staff session? For endpoints that must not extend it (e.g. /live). */
    public static function isStaff(): bool
    {
        return !empty($_SESSION['admin_id'])
            && time() - (int) ($_SESSION['last_seen'] ?? 0) <= self::IDLE_MINUTES * 60
            && hash_equals((string) ($_SESSION['browser'] ?? ''), self::browser());
    }

    public static function signOut(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'],
                                           'httponly' => true, 'samesite' => $p['samesite'] ?: 'Lax']);
            session_destroy();
            session_start();                                   // a clean session for the sign-in message
            session_regenerate_id(true);
        }
    }

    /** The browser a session belongs to (its User-Agent, hashed — never stored as is). */
    private static function browser(): string
    {
        return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }
}
