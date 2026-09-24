<?php
namespace App\Core;

use PDO;
use PDOException;

/**
 * Database — thin PDO wrapper, single shared connection.
 *
 * Models never call `new PDO` themselves; they ask Database::conn().
 * That way the connection settings live in exactly one place.
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);

                // Align MySQL's clock with the app's. Otherwise a server
                // running UTC stamps created_at eight hours behind the
                // times the committee sees everywhere else. Sent as a
                // numeric offset because the named-timezone tables are
                // often not loaded on a default MySQL install.
                $offset = (new \DateTime('now', new \DateTimeZone(APP_TIMEZONE)))->format('P');
                self::$pdo->exec("SET time_zone = '{$offset}'");
            } catch (PDOException $e) {
                if (DEBUG_MODE) {
                    die('Database connection failed: ' . $e->getMessage());
                }
                die('無法連接資料庫，請稍後再試。 / Database unavailable, please try again later.');
            }
        }
        return self::$pdo;
    }
}
