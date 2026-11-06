<?php
declare(strict_types=1);

/**
 * Set a staff account's password from the server shell.
 *
 *   php bin/set-password.php admin
 *
 * Use it for the first password after a fresh install (the seeded
 * accounts ship with passwords printed in the source, and those never
 * open a web session), or to recover when nobody can sign in.
 * It asks for the password twice without showing it, applies the same
 * rules as the web form, signs that account out everywhere and lifts any
 * login lock.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config/config.php';

// Same autoloader as public/index.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
require BASE_PATH . '/app/Core/helpers.php';

use App\Models\AdminUser;
use App\Models\LoginAttempt;

$username = trim((string) ($argv[1] ?? ''));
if ($username === '') {
    fwrite(STDERR, "Usage: php bin/set-password.php <username>\n");
    exit(1);
}

$users = new AdminUser();
$user  = $users->findByUsername($username);
if ($user === null) {
    fwrite(STDERR, "No account named \"{$username}\".\n");
    exit(1);
}

/** Read one line without echoing it when we are on a terminal. */
$ask = static function (string $prompt): string {
    fwrite(STDOUT, $prompt);
    $tty = function_exists('posix_isatty') && posix_isatty(STDIN);
    if ($tty) {
        shell_exec('stty -echo');          // fixed command, no user input
    }
    $line = fgets(STDIN);
    if ($tty) {
        shell_exec('stty echo');
        fwrite(STDOUT, "\n");
    }
    return rtrim((string) $line, "\r\n");
};

$password = $ask("New password for {$user['username']}: ");
$confirm  = $ask('Type it again: ');

$errors = AdminUser::validatePassword($password, $confirm);
if ($errors) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

$users->updatePassword((int) $user['id'], $password);   // bcrypt; signs out every session
(new LoginAttempt())->clear((string) $user['username']);

echo "Password set for {$user['username']}. They can sign in now.\n";
