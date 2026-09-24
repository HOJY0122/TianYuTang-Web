<?php
namespace App\Models;

use App\Core\Model;

/**
 * AdminUser — committee member accounts for the admin area.
 */
class AdminUser extends Model
{
    /**
     * The password schema.sql ships with. It is printed in the README,
     * so anyone can read it — which is why logging in with it only
     * unlocks the change-password page.
     */
    public const DEFAULT_PASSWORD = 'tianyutang2026';

    public const MIN_PASSWORD_LENGTH = 10;

    /**
     * Problems with a proposed new password; empty means acceptable.
     *
     * @return string[]
     */
    public static function validateNewPassword(string $username, string $new, string $confirm): array
    {
        $errors = [];
        if (mb_strlen($new) < self::MIN_PASSWORD_LENGTH) {
            $errors[] = '新密碼至少需要 ' . self::MIN_PASSWORD_LENGTH . ' 個字元。';
        }
        if ($new === self::DEFAULT_PASSWORD || strcasecmp($new, $username) === 0) {
            $errors[] = '新密碼不可與預設密碼或帳號名稱相同。';
        }
        if ($new !== $confirm) {
            $errors[] = '兩次輸入的新密碼不一致。';
        }
        return $errors;
    }

    /** Is this the right current password for the given user id? */
    public function checkPassword(int $id, string $password): bool
    {
        $hash = $this->scalar('SELECT password_hash FROM admin_users WHERE id = ?', [$id]);
        return is_string($hash) && password_verify($password, $hash);
    }

    /**
     * Check a username/password pair.
     *
     * @return array|null the user row on success, null on failure.
     *         Deliberately returns null for BOTH "no such user" and
     *         "wrong password" so the login page cannot be used to
     *         discover which usernames exist.
     */
    public function verify(string $username, string $password): ?array
    {
        $user = $this->fetchOne(
            'SELECT id, username, password_hash FROM admin_users WHERE username = ?',
            [$username]
        );

        if ($user === null) {
            // Spend roughly the same time as a real check would, so the
            // response time does not reveal whether the username exists.
            password_verify($password, '$2y$12$usesomesillystringforsalt0000000000000000000000000000000');
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        unset($user['password_hash']);
        return $user;
    }

    /** Replace a user's password with a new hash. */
    public function updatePassword(int $id, string $newPassword): bool
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        return $this->execute('UPDATE admin_users SET password_hash = ? WHERE id = ?', [$hash, $id]) > 0;
    }
}
