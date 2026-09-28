<?php
namespace App\Core;

use RuntimeException;

/**
 * Crypto — encryption at rest for the few values that must not sit in the
 * database as plain text: IC / passport numbers and the AI service keys.
 *
 *   encrypt()     AES-256-GCM (authenticated: a changed byte is detected,
 *                 not silently decrypted to garbage). Stored as
 *                 "enc:v1:<base64 nonce|tag|ciphertext>".
 *   decrypt()     the reverse; values without the prefix (rows saved
 *                 before encryption) are returned as they are.
 *   blindIndex()  a keyed hash for finding a record by an encrypted value
 *                 ("is this IC already registered?") without decrypting
 *                 every row. Without the key it reveals nothing.
 *
 * THE KEY
 *   From APP_KEY in config/config.php if set (base64 of 32 random bytes),
 *   otherwise storage/keys/app.key, created on first use and readable only
 *   by the web server's user. A database dump without the key is useless
 *   to a thief — and equally useless to you if the key is lost, so back up
 *   storage/keys/app.key together with the database.
 */
final class Crypto
{
    private const PREFIX = 'enc:v1:';
    private static ?string $master = null;

    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '' || self::isEncrypted($plain)) {
            return $plain;
        }
        $nonce = random_bytes(12);
        $tag   = '';
        $ct    = openssl_encrypt($plain, 'aes-256-gcm', self::key('enc'), OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($ct === false) {
            throw new RuntimeException('Encryption failed.');
        }
        return self::PREFIX . base64_encode($nonce . $tag . $ct);
    }

    public static function decrypt(?string $stored): ?string
    {
        if ($stored === null || !self::isEncrypted($stored)) {
            return $stored;                       // empty, or saved before encryption
        }
        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key('enc'), OPENSSL_RAW_DATA,
                                 substr($raw, 0, 12), substr($raw, 12, 16));
        if ($plain === false) {
            error_log('Crypto: a value could not be decrypted (wrong key or damaged data).');
            return '';
        }
        return $plain;
    }

    public static function isEncrypted(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    /** Keyed hash of a normalised value, for exact lookups on encrypted columns. */
    public static function blindIndex(string $value): string
    {
        return hash_hmac('sha256', $value, self::key('index'));
    }

    /** Separate keys for separate jobs, all derived from the one master key. */
    private static function key(string $purpose): string
    {
        return hash_hkdf('sha256', self::master(), 32, 'tianyutang/' . $purpose);
    }

    private static function master(): string
    {
        if (self::$master !== null) {
            return self::$master;
        }
        if (defined('APP_KEY') && APP_KEY !== '') {
            $k = base64_decode((string) APP_KEY, true);
            if ($k === false || strlen($k) < 32) {
                throw new RuntimeException('APP_KEY in config.php must be base64 of 32 random bytes.');
            }
            return self::$master = $k;
        }
        $dir  = BASE_PATH . '/storage/keys';
        $file = $dir . '/app.key';
        if (!is_file($file)) {
            if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new RuntimeException('Cannot create storage/keys.');
            }
            // Create the key only if nobody else did in the meantime.
            $h = @fopen($file, 'x');
            if ($h !== false) {
                fwrite($h, base64_encode(random_bytes(32)) . "\n");
                fclose($h);
                @chmod($file, 0600);
            }
        }
        $k = base64_decode(trim((string) file_get_contents($file)), true);
        if ($k === false || strlen($k) < 32) {
            throw new RuntimeException('storage/keys/app.key is damaged.');
        }
        return self::$master = $k;
    }
}
