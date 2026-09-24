<?php
namespace App\Models;

use App\Core\Model;

/**
 * AdminUser — committee member accounts.
 *
 * Two roles:
 *   admin         day-to-day committee work
 *   system_admin  the above PLUS branding, the QR generator, and
 *                 managing who has a login
 *
 * system_admin is a superset rather than a parallel role, so nobody
 * needs two accounts — which is what leads to shared passwords.
 */
class AdminUser extends Model
{
    public const ROLE_ADMIN  = 'admin';
    public const ROLE_SYSTEM = 'system_admin';

    /**
     * The password schema.sql ships with. It is published with the
     * source, so anyone can read it — which is why logging in with it
     * only unlocks the change-password page, and why no account may be
     * given it again.
     */
    public const DEFAULT_PASSWORD = 'tianyutang2026';

    /**
     * Check a username/password pair.
     *
     * Returns null for BOTH "no such user" and "wrong password" so the
     * login page cannot be used to discover which usernames exist.
     */
    public function verify(string $username, string $password): ?array
    {
        $user = $this->fetchOne(
            'SELECT id, username, password_hash, role, display_name FROM admin_users WHERE username = ?',
            [$username]
        );

        if ($user === null) {
            // Spend roughly the same time as a real check, so response
            // time does not reveal whether the username exists.
            password_verify($password, '$2y$12$usesomesillystringforsalt0000000000000000000000000000000');
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        unset($user['password_hash']);
        return $user;
    }

    public function recordLogin(int $id): void
    {
        $this->execute('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    /** Every account, for the user-management screen. */
    public function all(): array
    {
        return $this->fetchAll(
            'SELECT id, username, role, display_name, last_login_at, created_at
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
        return $this->fetchOne('SELECT id FROM admin_users WHERE username = ?', [$username]);
    }

    /** Create an account. Returns the new id. */
    public function create(string $username, string $password, string $role, ?string $displayName): int
    {
        $this->execute(
            'INSERT INTO admin_users (username, password_hash, role, display_name) VALUES (?, ?, ?, ?)',
            [$username, password_hash($password, PASSWORD_DEFAULT), $this->normaliseRole($role), $displayName]
        );
        return (int) $this->db->lastInsertId();
    }

    public function updatePassword(int $id, string $newPassword): bool
    {
        return $this->execute(
            'UPDATE admin_users SET password_hash = ? WHERE id = ?',
            [password_hash($newPassword, PASSWORD_DEFAULT), $id]
        ) >= 0;
    }

    public function updateRole(int $id, string $role): bool
    {
        return $this->execute(
            'UPDATE admin_users SET role = ? WHERE id = ?',
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
        if (strlen($password) > 200) {
            $errors[] = '密碼過長。';
        }
        if ($password === self::DEFAULT_PASSWORD) {
            $errors[] = '不可使用系統預設密碼，請另設一組。';
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
        return $role === self::ROLE_SYSTEM ? self::ROLE_SYSTEM : self::ROLE_ADMIN;
    }
}
