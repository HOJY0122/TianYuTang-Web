<?php
namespace App\Models;

use App\Core\Model;

/**
 * AdminUser — committee member accounts for the admin area.
 */
class AdminUser extends Model
{
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
