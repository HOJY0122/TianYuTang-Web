<?php
namespace App\Core;

use RuntimeException;

/**
 * VideoUploader — short album videos (MP4 / MOV / WebM), sent in pieces.
 *
 * A phone video is often 50–200 MB, far over PHP's 20 MB per upload and
 * nginx's body limit. So the browser sends it in CHUNK-sized pieces:
 *
 *   start()   checks the size, makes an upload id (random, kept in the
 *             signed-in session) and an empty part file
 *   append()  adds the next piece — only at the exact byte where the last
 *             one ended, so a repeated or out-of-order piece cannot
 *             corrupt the file
 *   finish()  checks the whole file is there and really is a video (the
 *             first bytes, not the name), then moves it into storage/videos
 *             under a random name
 *
 * Videos live OUTSIDE the web folder (storage/videos), so no server rule
 * is needed to keep their folder address shut; they are played only
 * through signed addresses (Media::videoUrl / Media::serveVideo).
 * The cover picture goes through ImageUploader like any photo.
 */
final class VideoUploader
{
    public const MAX_BYTES = 200 * 1024 * 1024;   // 200 MB — a few minutes of phone video
    private const CHUNK    = 4 * 1024 * 1024;     // 4 MB a piece, or less if the host allows less (chunkSize)
    private const KEEP_FREE = 512 * 1024 * 1024;  // never fill the disk: leave 512 MB

    private string $dir;
    private string $tmpDir;

    /** Bytes per piece: 4 MB, or less where PHP's upload limits are smaller (some hosts allow only 2 MB). */
    public static function chunkSize(): int
    {
        $bytes = static function (string $v): int {
            $v = trim($v);
            $n = (int) $v;
            return match (strtolower(substr($v, -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
        };
        $limit = self::CHUNK;
        foreach (['upload_max_filesize', 'post_max_size'] as $k) {
            $b = $bytes((string) ini_get($k));
            if ($b > 0) {
                $limit = min($limit, $b - 64 * 1024);      // room for the other form fields
            }
        }
        return max(256 * 1024, $limit);
    }

    public function __construct()
    {
        $this->dir    = BASE_PATH . '/storage/videos';
        $this->tmpDir = BASE_PATH . '/storage/tmp/video-parts';
    }

    /** Begin an upload of $size bytes → upload id. */
    public function start(int $size): string
    {
        if ($size <= 0) {
            throw new RuntimeException('影片是空的。The video is empty.');
        }
        if ($size > self::MAX_BYTES) {
            throw new RuntimeException('影片超過 200MB，請剪短或壓縮後再上傳。Video over 200 MB — trim or compress it first.');
        }
        foreach ([$this->dir, $this->tmpDir] as $d) {
            if (!is_dir($d) && !@mkdir($d, 0755, true) && !is_dir($d)) {
                throw new RuntimeException('伺服器無法儲存影片，請聯絡管理員。The server cannot store videos.');
            }
        }
        $this->sweep();
        $free = @disk_free_space($this->dir);
        if ($free !== false && $free < $size * 2 + self::KEEP_FREE) {
            throw new RuntimeException('伺服器空間不足，請先刪除舊影片或聯絡管理員。Not enough space on the server.');
        }
        $id = bin2hex(random_bytes(16));
        touch($this->part($id));
        $_SESSION['video_uploads'][$id] = ['size' => $size, 'at' => time()];
        return $id;
    }

    /**
     * Add one piece at byte $offset → bytes received so far.
     * $chunk: the $_FILES entry of the piece.
     */
    public function append(string $id, int $offset, array $chunk): int
    {
        $meta = $this->meta($id);
        if (($chunk['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($chunk['tmp_name'] ?? ''))) {
            throw new RuntimeException('這一段沒有收到，請重試。This piece did not arrive — try again.');
        }
        $part = $this->part($id);
        clearstatcache(true, $part);
        $have = (int) @filesize($part);
        $len  = (int) $chunk['size'];
        if ($offset !== $have) {
            // Already have it (a retry after a lost reply): just say where we are.
            if ($offset < $have && $offset + $len <= $have) {
                return $have;
            }
            throw new RuntimeException('上傳次序錯了，請重新上傳。Pieces out of order — start again.');
        }
        if ($len <= 0 || $len > self::chunkSize() || $have + $len > $meta['size']) {
            throw new RuntimeException('這一段大小不對。This piece has the wrong size.');
        }
        $in  = fopen($chunk['tmp_name'], 'rb');
        $out = fopen($part, 'ab');
        if (!$in || !$out || !flock($out, LOCK_EX)) {
            throw new RuntimeException('伺服器無法寫入影片。The server could not write the video.');
        }
        stream_copy_to_stream($in, $out);
        fflush($out);
        flock($out, LOCK_UN);
        fclose($out);
        fclose($in);
        clearstatcache(true, $part);
        return (int) filesize($part);
    }

    /** All pieces in → the stored path ("videos/<random>.mp4"). The upload id is spent. */
    public function finish(string $id): string
    {
        $meta = $this->meta($id);
        $part = $this->part($id);
        clearstatcache(true, $part);
        if ((int) @filesize($part) !== $meta['size']) {
            throw new RuntimeException('影片未完整上傳，請重試。The video did not arrive in full — try again.');
        }
        $ext = self::detect($part);
        if ($ext === null) {
            $this->abort($id);
            throw new RuntimeException('這不是 MP4 / MOV / WebM 影片。Not an MP4, MOV or WebM video.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!@rename($part, $this->dir . '/' . $name)) {
            throw new RuntimeException('伺服器無法儲存影片。The server could not store the video.');
        }
        @chmod($this->dir . '/' . $name, 0644);
        unset($_SESSION['video_uploads'][$id]);
        return 'videos/' . $name;
    }

    /** Throw away an unfinished upload. */
    public function abort(string $id): void
    {
        if (self::validId($id)) {
            @unlink($this->part($id));
            unset($_SESSION['video_uploads'][$id]);
        }
    }

    /** Delete a stored video ("videos/<name>"); anything else is ignored. */
    public function delete(?string $path): void
    {
        $file = self::file($path);
        if ($file !== null) {
            @unlink($file);
        }
    }

    /** The file on disk for a stored path, or null if the path is not one of ours. */
    public static function file(?string $path): ?string
    {
        if (!is_string($path) || !preg_match('#^videos/[a-f0-9]{32}\.(mp4|webm)$#', $path)) {
            return null;
        }
        return BASE_PATH . '/storage/' . $path;
    }

    /**
     * What the first bytes say the file is: 'mp4' (MP4, MOV, M4V — all
     * "ISO media", served as video/mp4, which browsers play best), 'webm',
     * or null.
     */
    public static function detect(string $file): ?string
    {
        $h = (string) @file_get_contents($file, false, null, 0, 16);
        if (strlen($h) >= 12 && substr($h, 4, 4) === 'ftyp') {
            return 'mp4';
        }
        if (strncmp($h, "\x1A\x45\xDF\xA3", 4) === 0) {
            return 'webm';
        }
        return null;
    }

    private function meta(string $id): array
    {
        $m = self::validId($id) ? ($_SESSION['video_uploads'][$id] ?? null) : null;
        if (!is_array($m) || !is_file($this->part($id))) {
            throw new RuntimeException('上傳已失效，請重新選擇影片。This upload expired — choose the video again.');
        }
        return $m;
    }

    private static function validId(string $id): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/', $id);
    }

    private function part(string $id): string
    {
        return $this->tmpDir . '/' . $id . '.part';
    }

    /** Parts left behind (a closed tab) are removed after a day. */
    private function sweep(): void
    {
        foreach (glob($this->tmpDir . '/*.part') ?: [] as $f) {
            if (@filemtime($f) < time() - 86400) {
                @unlink($f);
            }
        }
    }
}
