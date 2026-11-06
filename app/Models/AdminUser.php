<?php
namespace App\Models;

use App\Core\Model;

/**
 * AdminUser — committee member accounts.
 *
 * Three roles:
 *   admin         day-to-day committee work (functions chosen per account)
 *   system_admin  the above PLUS branding, the QR generator, and
 *                 managing who has a login
 *   helper        施齋助理 a Day 3 helper: only the 施齋入座 easy screens
 *
 * system_admin is a superset rather than a parallel role, so nobody
 * needs two accounts — which is what leads to shared passwords.
 */
class AdminUser extends Model
{
    public const ROLE_ADMIN  = 'admin';
    public const ROLE_SYSTEM = 'system_admin';
    public const ROLE_HELPER = 'helper';

    /** role => [中文, English, what it can do] — for the accounts page. */
    public const ROLES = [
        'admin'        => ['管理員', 'Admin', '下面選擇可用功能 functions chosen below'],
        'helper'       => ['施齋助理', 'Day 3 helper', '只有施齋入座簡易模式 only the Day 3 seating easy screens'],
        'system_admin' => ['系統管理員', 'System admin', '全部功能及網站設定 everything + site settings'],
    ];

    /**
     * Passwords that ship with the source code, so anyone can read them:
     *   schema.sql     admin    / tianyutang2026
     *   migration 006  sysadmin / tianyutang-sys2026
     * They never open a web session (AdminController::login): the operator
     * sets the first password with php bin/set-password.php, and no
     * account may be given one again.
     */
    public const DEFAULT_PASSWORDS = ['tianyutang2026', 'tianyutang-sys2026'];

    /** Is this one of the published default passwords? */
    public static function isDefaultPassword(string $password): bool
    {
        foreach (self::DEFAULT_PASSWORDS as $default) {
            if (hash_equals($default, $password)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Does this stored hash still accept a published default password?
     * Asks about the ACCOUNT, not about what was typed, so no trick in the
     * typed text (a hidden NUL byte, extra characters) can hide a default.
     */
    public static function hashIsDefault(string $hash): bool
    {
        foreach (self::DEFAULT_PASSWORDS as $default) {
            if (password_verify($default, $hash)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check a username/password pair.
     *
     * Returns null for both "no such user" and "wrong password"; the login
     * page looks the username up first (findByUsername) to tell them apart.
     */
    public function verify(string $username, string $password): ?array
    {
        $user = $this->fetchOne(
            'SELECT id, username, password_hash, role, permissions, display_name, session_version FROM admin_users WHERE username = ?',
            [$username]
        );

        // bcrypt stops reading at a NUL byte, so "secret\0anything" would
        // pass as "secret". No real password contains one: refuse it.
        if (str_contains($password, "\0")) {
            $user = null;
        }

        if ($user === null) {
            // Spend roughly the same time as a real check, so response
            // time does not reveal whether the username exists.
            password_verify($password, '$2y$12$bVTBOjY2CYPZOe5urEQ.FuOVPzWYx4bkGb9ZbUAEPZkv.wC.0nG1a'); // a real cost-12 hash of random bytes
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }
        // The login page refuses accounts still on a published default.
        $user['has_default_password'] = self::hashIsDefault($user['password_hash']);

        // Stored with an older, weaker setting? Re-hash now while we have the
        // password — but only to make it stronger, never weaker.
        if (self::needsStrongerHash($user['password_hash'])) {
            $this->execute('UPDATE admin_users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }

        unset($user['password_hash']);
        return $user;
    }

    /** True when the hash uses another algorithm or a lower bcrypt cost than PASSWORD_DEFAULT would. */
    private static function needsStrongerHash(string $hash): bool
    {
        if (!password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            return false;
        }
        $info = password_get_info($hash);
        $want = password_get_info(password_hash('x', PASSWORD_DEFAULT));
        if ($info['algo'] !== $want['algo']) {
            return true;
        }
        return (int) ($info['options']['cost'] ?? 0) < (int) ($want['options']['cost'] ?? 0);
    }

    /** Role and session version, checked on every staff page (App\Core\Session). */
    public function sessionState(int $id): ?array
    {
        return $this->fetchOne('SELECT role, permissions, session_version, device_token FROM admin_users WHERE id = ?', [$id]);
    }

    /** The hash of the token held by the device that signed in last (see Session). */
    public function setDeviceToken(int $id, string $hash): void
    {
        $this->execute('UPDATE admin_users SET device_token = ? WHERE id = ?', [$hash, $id]);
    }

    public function recordLogin(int $id): void
    {
        $this->execute('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    /** Every account, for the user-management screen. */
    public function all(): array
    {
        return $this->fetchAll(
            'SELECT id, username, role, permissions, display_name, last_login_at, created_at
             FROM admin_users ORDER BY role DESC, username'
        );
    }

    public function find(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT id, username, role, display_name, last_login_at, created_at
             FROM admin_users WHERE id = ?',
            [$id]
        );
    }

    public function findByUsername(string $username): ?array
    {
        return $this->fetchOne('SELECT id, username FROM admin_users WHERE username = ?', [$username]);
    }

    /** Create an account. Returns the new id. */
    public function create(string $username, string $password, string $role, ?string $displayName, ?array $permissions = null): int
    {
        $this->execute(
            'INSERT INTO admin_users (username, password_hash, role, permissions, display_name) VALUES (?, ?, ?, ?, ?)',
            [$username, password_hash($password, PASSWORD_DEFAULT), $this->normaliseRole($role),
             \App\Core\Access::encode($permissions), $displayName]
        );
        return (int) $this->db->lastInsertId();
    }

    /** Which admin functions this account may use (null = all). Applies on their next click. */
    public function updatePermissions(int $id, ?array $permissions): void
    {
        $this->execute('UPDATE admin_users SET permissions = ? WHERE id = ?', [\App\Core\Access::encode($permissions), $id]);
    }

    public function updatePassword(int $id, string $newPassword): bool
    {
        return $this->execute(
            // A new password signs this person out everywhere else (see App\Core\Session).
            'UPDATE admin_users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?',
            [password_hash($newPassword, PASSWORD_DEFAULT), $id]
        ) >= 0;
    }

    public function updateRole(int $id, string $role): bool
    {
        return $this->execute(
            // A changed role takes effect at once: other sessions must sign in again.
            'UPDATE admin_users SET role = ?, session_version = session_version + 1 WHERE id = ?',
            [$this->normaliseRole($role), $id]
        ) >= 0;
    }

    public function updateDisplayName(int $id, ?string $displayName): bool
    {
        return $this->execute(
            'UPDATE admin_users SET display_name = ? WHERE id = ?',
            [$displayName, $id]
        ) >= 0;
    }

    public function delete(int $id): bool
    {
        return $this->execute('DELETE FROM admin_users WHERE id = ?', [$id]) > 0;
    }

    /**
     * How many system administrators exist.
     *
     * Guards the last one. Deleting or demoting the final system admin
     * would leave nobody able to manage users, reach the settings, or
     * promote anyone back — recoverable only with direct SQL access,
     * which a temple committee does not have.
     */
    public function countSystemAdmins(): int
    {
        return (int) $this->scalar(
            "SELECT COUNT(*) FROM admin_users WHERE role = 'system_admin'"
        );
    }

    /**
     * How many plain admin accounts exist.
     *
     * Worth showing on the system page: with the two roles separated, a
     * site with zero admins has nobody who can actually run the event,
     * and a system admin cannot cover for them.
     */
    public function countAdmins(): int
    {
        return (int) $this->scalar(
            "SELECT COUNT(*) FROM admin_users WHERE role = 'admin'"
        );
    }

    /** Password rules, kept here so create and change agree. */
    public static function validatePassword(string $password, string $confirm): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = '密碼至少需要 8 個字元。';
        }
        // bcrypt only reads the first 72 bytes; anything longer would be
        // silently cut, so say so instead (72 bytes = 72 letters or 24 Chinese characters).
        if (strlen($password) > 72) {
            $errors[] = '密碼過長（最多 72 個英文字元或 24 個中文字）。';
        }
        if (self::isDefaultPassword($password)) {
            $errors[] = '不可使用系統預設密碼，請另設一組。';
        }
        if (str_contains($password, "\0")) {
            $errors[] = '密碼含有不允許的字元。';
        }
        if ($password !== $confirm) {
            $errors[] = '兩次輸入的密碼不一致。';
        }

        return $errors;
    }

    public static function validateUsername(string $username): array
    {
        $errors = [];

        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            $errors[] = '帳號只能使用英文字母、數字、句點、底線或連字號，長度 3–50 字元。';
        }

        return $errors;
    }

    private function normaliseRole(string $role): string
    {
        return isset(self::ROLES[$role]) ? $role : self::ROLE_ADMIN;
    }
}
