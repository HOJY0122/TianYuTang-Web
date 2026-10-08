<?php
namespace App\Core;

/**
 * Media — photos (albums, news posts, home banner) are served only
 * through signed, expiring addresses: /media?f=uploads/photos/x.jpg&e=…&s=…
 *
 * The files themselves can no longer be opened by their folder address
 * (uploads/photos|posts|banners/.htaccess, and server.php in development).
 *
 *   url()    the signed address a page puts in <img src>. It stays the
 *            same for a few hours (so browsers can cache it), then stops
 *            working — a copied link is soon dead.
 *   serve()  checks the signature and the time, and that the browser is
 *            loading the file AS AN IMAGE inside a page of this site:
 *            typing or pasting the address, or "open image in new tab",
 *            asks for a document and is refused.
 *
 * This keeps casual copying and hot-linking away. It cannot stop a photo
 * that is on someone's screen from being photographed or screen-captured
 * — no website can — so pages add deterrents too (js/protect.js) and an
 * optional watermark.
 */
final class Media
{
    /** Folders whose files go through here. */
    public const FOLDERS = ['uploads/photos/', 'uploads/posts/', 'uploads/banners/'];
    /** Addresses change every WINDOW seconds and work for up to two windows. */
    private const WINDOW = 21600;   // 6 hours

    public static function protects(string $path): bool
    {
        foreach (self::FOLDERS as $f) {
            if (str_starts_with($path, $f)) {
                return true;
            }
        }
        return false;
    }

    public static function url(?string $path): string
    {
        $path = ltrim((string) $path, '/');
        if ($path === '' || !self::protects($path)) {
            return BASE_URL . '/' . $path;
        }
        $exp = (intdiv(time(), self::WINDOW) + 2) * self::WINDOW;
        return BASE_URL . '/media?f=' . rawurlencode($path) . '&e=' . $exp . '&s=' . substr(Crypto::sign($path . '|' . $exp, 'media'), 0, 32);
    }

    /** GET /media — never returns. */
    public static function serve(): void
    {
        $path = is_string($_GET['f'] ?? null) ? $_GET['f'] : '';
        $exp  = (int) ($_GET['e'] ?? 0);
        $sig  = is_string($_GET['s'] ?? null) ? $_GET['s'] : '';

        $ok = self::protects($path)
            && preg_match('#^uploads/(photos|posts|banners)/[A-Za-z0-9_\-./]+\.(jpe?g|png|gif|webp)$#i', $path)
            && !str_contains($path, '..')
            && $exp >= time() && $exp <= time() + 3 * self::WINDOW
            && hash_equals(substr(Crypto::sign($path . '|' . $exp, 'media'), 0, 32), $sig);

        // Only as an image inside one of our pages. Browsers send
        // Sec-Fetch-Dest (most since 2020–2023); older ones are judged by
        // the Referer. A request with neither is allowed (very old browsers).
        $dest = (string) ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '');
        $site = (string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
        $ref  = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($ok && $dest !== '' && ($dest !== 'image' || !in_array($site, ['same-origin', 'same-site'], true))) {
            $ok = false;
        }
        if ($ok && $dest === '' && $ref !== '' && parse_url($ref, PHP_URL_HOST) !== parse_url('http://' . site_host(), PHP_URL_HOST)) {
            $ok = false;
        }

        $file = BASE_PATH . '/public/' . $path;
        if (!$ok || !is_file($file)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store');
            echo "此相片只能在網站內觀看。\nThis photo can only be viewed on the website.";
            exit;
        }

        $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
        $etag = '"' . substr(md5($path . filemtime($file) . filesize($file)), 0, 16) . '"';
        header('Content-Type: ' . $types[strtolower(pathinfo($file, PATHINFO_EXTENSION))]);
        header('Content-Disposition: inline; filename="photo"');
        header('Cache-Control: private, max-age=3600');
        header('ETag: ' . $etag);
        header('X-Robots-Tag: noindex, noimageindex');
        header('Cross-Origin-Resource-Policy: same-origin');      // other sites cannot embed it
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}
