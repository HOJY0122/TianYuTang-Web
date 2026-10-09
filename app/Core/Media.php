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
 * Scrambled copies (url(..., true), &x=1): the file sent is the photo cut
 * into square tiles and shuffled. Only the page's script (js/protect.js)
 * knows the order (data-scr) and puts the tiles back together on a
 * <canvas>, so what DevTools → Sources / Network shows, or what a
 * download tool saves, is a jumbled picture. Shuffled copies are made
 * once and cached in storage/cache/media.
 *
 * This keeps casual copying and hot-linking away. It cannot stop a photo
 * that is on someone's screen from being photographed or screen-captured
 * — no website can — so pages add deterrents too (js/protect.js) and an
 * optional watermark.
 */
final class Media
{
    /** Folders whose files go through here. */
    public const FOLDERS = ['uploads/photos/', 'uploads/posts/', 'uploads/banners/', 'uploads/about/'];
    /** Addresses change every WINDOW seconds and work for up to two windows. */
    private const WINDOW = 21600;   // 6 hours

    /** Is this part of the public site protected (Forms & fonts → 相片保護)? albums | news | banner | qr */
    public static function guarded(string $area): bool
    {
        $site = (new \App\Models\Setting())->site();
        return ($site['protect_photos'] ?? '1') === '1' && ($site['protect_' . $area] ?? '0') === '1';
    }

    public static function protects(string $path): bool
    {
        foreach (self::FOLDERS as $f) {
            if (str_starts_with($path, $f)) {
                return true;
            }
        }
        return false;
    }

    public static function url(?string $path, bool $scrambled = false): string
    {
        $path = ltrim((string) $path, '/');
        if ($path === '' || !self::protects($path)) {
            return BASE_URL . '/' . $path;
        }
        $exp = (intdiv(time(), self::WINDOW) + 2) * self::WINDOW;
        $x   = $scrambled ? '1' : '0';
        return BASE_URL . '/media?f=' . rawurlencode($path) . '&e=' . $exp . ($scrambled ? '&x=1' : '')
             . '&s=' . substr(Crypto::sign($path . '|' . $exp . '|' . $x, 'media'), 0, 32);
    }

    /** The tile order key for a photo (the page needs it to put the tiles back). */
    public static function seed(string $path): int
    {
        return (int) hexdec(substr(Crypto::sign(ltrim($path, '/'), 'scramble'), 0, 8));
    }

    /** Tile size for an image: about eight across, on 16-px steps (JPEG blocks, so no seams). */
    public static function tile(int $w, int $h): int
    {
        return max(16, intdiv(intdiv(min($w, $h), 8), 16) * 16);
    }

    /** The shuffled order of n tiles (mulberry32 + Fisher–Yates; js/protect.js does the same). */
    public static function order(int $seed, int $n): array
    {
        $imul = static fn(int $a, int $b): int => ((($a & 0xFFFF) * $b) + (((($a >> 16) * $b) & 0xFFFF) << 16)) & 0xFFFFFFFF;
        $a = $seed & 0xFFFFFFFF;
        $p = $n > 0 ? range(0, $n - 1) : [];
        for ($i = $n - 1; $i > 0; $i--) {
            $a = ($a + 0x6D2B79F5) & 0xFFFFFFFF;
            $t = $imul($a ^ ($a >> 15), $a | 1);
            $t = (($t + $imul($t ^ ($t >> 7), $t | 61)) & 0xFFFFFFFF) ^ $t;
            $r = (($t ^ ($t >> 14)) & 0xFFFFFFFF) / 4294967296;
            $j = (int) floor($r * ($i + 1));
            [$p[$i], $p[$j]] = [$p[$j], $p[$i]];
        }
        return $p;
    }

    /** Path of the shuffled copy of $file (made and cached on first use), or null if GD cannot read it. */
    private static function scrambledFile(string $path, string $file): ?string
    {
        $dir = BASE_PATH . '/storage/cache/media';
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'png' ? 'png' : 'jpg';
        // "q95": part of the name, so copies made at the old quality are
        // simply not used any more (they are rebuilt on first view).
        $out = $dir . '/' . sha1($path . '|' . filemtime($file) . '|' . self::seed($path) . '|q95') . '.' . $ext;
        if (is_file($out)) {
            return $out;
        }
        $src = @imagecreatefromstring((string) file_get_contents($file));
        if (!$src) {
            return null;
        }
        $w = imagesx($src); $h = imagesy($src);
        $t = self::tile($w, $h);
        $cols = intdiv($w, $t); $rows = intdiv($h, $t);
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);           // edges beyond the last whole tile stay as they are
        $order = self::order(self::seed($path), $cols * $rows);
        foreach ($order as $i => $from) {
            imagecopy($dst, $src, ($i % $cols) * $t, intdiv($i, $cols) * $t, ($from % $cols) * $t, intdiv($from, $cols) * $t, $t, $t);
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $tmp = $out . '.' . bin2hex(random_bytes(4));
        // 95: this is a second save of an already-compressed photo; a
        // lower setting visibly softens it (tiles are 16px-aligned, so
        // they do not add block edges of their own).
        $ext === 'png' ? imagepng($dst, $tmp, 6) : imagejpeg($dst, $tmp, 95);
        rename($tmp, $out);
        return $out;
    }

    /** GET /media — never returns. */
    public static function serve(): void
    {
        $path = is_string($_GET['f'] ?? null) ? $_GET['f'] : '';
        $exp  = (int) ($_GET['e'] ?? 0);
        $sig  = is_string($_GET['s'] ?? null) ? $_GET['s'] : '';
        $x    = ($_GET['x'] ?? '') === '1' ? '1' : '0';

        $ok = self::protects($path)
            && preg_match('#^uploads/(photos|posts|banners|about)/[A-Za-z0-9_\-./]+\.(jpe?g|png|gif|webp)$#i', $path)
            && !str_contains($path, '..')
            && $exp >= time() && $exp <= time() + 3 * self::WINDOW
            && hash_equals(substr(Crypto::sign($path . '|' . $exp . '|' . $x, 'media'), 0, 32), $sig);

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

        if ($x === '1') {
            $file = self::scrambledFile($path, $file) ?? $file;
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
