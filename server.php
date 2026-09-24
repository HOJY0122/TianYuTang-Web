<?php
/**
 * Development server router — FOR LOCAL TESTING ONLY.
 *
 * PHP's built-in server has no .htaccess support, so this small script
 * does the same job: serve real files (CSS, images) directly, and send
 * everything else to the front controller.
 *
 * Run it from the project root with:
 *     php -S localhost:8000 -t public server.php
 *
 * Then open http://localhost:8000
 *
 * On XAMPP/Apache or real hosting this file is never used — .htaccess
 * handles the rewriting instead. It is safe to delete before uploading.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . '/public' . $path;

// A real file that is not a PHP script → let the server return it as-is.
if ($path !== '/' && is_file($file) && !str_ends_with($file, '.php')) {
    return false;
}

require __DIR__ . '/public/index.php';
