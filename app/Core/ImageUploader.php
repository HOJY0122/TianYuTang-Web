<?php
namespace App\Core;

use RuntimeException;

/**
 * ImageUploader — the single place uploaded images enter this system.
 *
 * Uploads are the most dangerous thing a web app accepts, so this class
 * is deliberately paranoid. The protections, in order of importance:
 *
 *  1. RE-ENCODE EVERY IMAGE THROUGH GD.
 *     This is the strongest defence and the reason the others are
 *     backup. A file that is both valid JPEG and valid PHP (a
 *     "polyglot") is a real attack: the image header keeps getimagesize()
 *     happy while PHP code hides in a comment block. Decoding the pixels
 *     and writing a brand-new file from them discards everything that is
 *     not pixels — payload, EXIF, trailing bytes. What lands on disk is
 *     a file this server wrote, not a file the visitor uploaded.
 *
 *  2. The filename is generated here, never taken from the upload.
 *     Stops "shell.php", "a.php.jpg", "../../config.php" and friends.
 *
 *  3. The extension comes from the detected image type, not the name.
 *
 *  4. Size and dimension limits, checked before any decoding, so a
 *     "decompression bomb" cannot exhaust memory.
 *
 *  5. The uploads directory refuses to execute scripts (see its
 *     .htaccess). Defence in depth — by this point nothing executable
 *     should exist there anyway.
 *
 * PUBLIC vs PRIVATE
 *   Banners and gallery photos are meant to be seen by everyone, so they
 *   go under public/uploads and the web server hands them out directly.
 *   Receipt photos are financial records, so they are stored PRIVATE:
 *   under storage/, outside the web root, where no URL can reach them.
 *   An admin-only route (CounterController::receipt) streams them.
 */
class ImageUploader
{
    /** Image types we accept, mapped to their canonical extension. */
    private const ALLOWED = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];

    private const MAX_BYTES      = 5 * 1024 * 1024;  // 5 MB per file
    private const MAX_DIMENSION  = 6000;             // px, guards against bombs
    private const JPEG_QUALITY   = 85;

    private string $uploadDir;
    private string $publicPrefix;
    private string $rootDir;    // nothing outside this may ever be deleted or read
    private string $pathBase;   // stored paths are relative to this

    /**
     * @param string $subdir  folder name, e.g. 'banners'
     * @param bool   $private true = storage/<subdir> (never web-reachable),
     *                        false = public/uploads/<subdir>
     */
    public function __construct(string $subdir = '', bool $private = false)
    {
        $subdir = trim($subdir, '/');

        if ($private) {
            // Stored as "receipts/ab12….jpg", relative to storage/.
            $this->pathBase     = BASE_PATH . '/storage';
            $this->rootDir      = $this->pathBase;
            $this->uploadDir    = $this->rootDir . ($subdir ? '/' . $subdir : '');
            $this->publicPrefix = $subdir;
        } else {
            // Stored as "uploads/banners/ab12….jpg", relative to public/.
            $this->pathBase     = BASE_PATH . '/public';
            $this->rootDir      = $this->pathBase . '/uploads';
            $this->uploadDir    = $this->rootDir . ($subdir ? '/' . $subdir : '');
            $this->publicPrefix = 'uploads' . ($subdir ? '/' . $subdir : '');
        }

        if (!is_dir($this->uploadDir) && !mkdir($this->uploadDir, 0755, true) && !is_dir($this->uploadDir)) {
            throw new RuntimeException('Cannot create upload directory: ' . $this->uploadDir);
        }
    }

    /**
     * Handle one $_FILES entry.
     *
     * @param array      $file     a single entry from $_FILES
     * @param int|null   $maxWidth resize down to this width if wider
     * @return string    path relative to public/, e.g. "uploads/banners/ab12….jpg"
     * @throws RuntimeException with a message safe to show the user
     */
    public function store(array $file, ?int $maxWidth = null): string
    {
        $this->assertUploadOk($file);

        $tmpPath = $file['tmp_name'];

        // is_uploaded_file() confirms this really came through an HTTP
        // upload, not a path an attacker talked us into reading.
        if (!is_uploaded_file($tmpPath)) {
            throw new RuntimeException('檔案上傳失敗，請重試。');
        }

        if ($file['size'] > self::MAX_BYTES) {
            throw new RuntimeException('圖片檔案過大，請上傳 5MB 以內的圖片。');
        }

        // getimagesize() reads the header only — cheap, and it tells us
        // the REAL type regardless of what the filename claims.
        $info = @getimagesize($tmpPath);
        if ($info === false) {
            throw new RuntimeException('這不是有效的圖片檔案，請上傳 JPG、PNG、GIF 或 WebP。');
        }

        [$width, $height, $type] = $info;

        if (!isset(self::ALLOWED[$type])) {
            throw new RuntimeException('不支援的圖片格式，請上傳 JPG、PNG、GIF 或 WebP。');
        }
        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw new RuntimeException('圖片尺寸過大，請上傳 6000×6000 像素以內的圖片。');
        }
        if ($width < 1 || $height < 1) {
            throw new RuntimeException('圖片尺寸不正確。');
        }

        $image = $this->decode($tmpPath, $type);
        if ($image === null) {
            throw new RuntimeException('圖片無法讀取，可能已損毀，請換一張再試。');
        }

        if ($maxWidth !== null && $width > $maxWidth) {
            $image = $this->resizeToWidth($image, $width, $height, $maxWidth);
        }

        $extension = self::ALLOWED[$type];
        $filename  = $this->randomName($extension);
        $target    = $this->uploadDir . '/' . $filename;

        $written = $this->encode($image, $target, $type);
        imagedestroy($image);

        if (!$written) {
            throw new RuntimeException('圖片儲存失敗，請稍後再試。');
        }

        // Never executable, whatever the web server is configured to do.
        @chmod($target, 0644);

        return $this->publicPrefix . '/' . $filename;
    }

    /**
     * Store an image twice: a display copy and a small thumbnail.
     *
     * The gallery needs both. Showing a grid of full-size phone photos
     * would mean several megabytes per row — unusable for 長輩 on mobile
     * data, which is most of this site's audience.
     *
     * The source is decoded ONCE and both sizes are written from that
     * same decode, so the payload-stripping guarantee applies to each.
     *
     * @return array{path:string, thumb:string}
     */
    public function storeWithThumbnail(array $file, int $maxWidth = 1600, int $thumbWidth = 600): array
    {
        $this->assertUploadOk($file);

        $tmpPath = $file['tmp_name'];

        if (!is_uploaded_file($tmpPath)) {
            throw new RuntimeException('檔案上傳失敗，請重試。');
        }
        if ($file['size'] > self::MAX_BYTES) {
            throw new RuntimeException('圖片檔案過大，請上傳 5MB 以內的圖片。');
        }

        $info = @getimagesize($tmpPath);
        if ($info === false) {
            throw new RuntimeException('這不是有效的圖片檔案，請上傳 JPG、PNG、GIF 或 WebP。');
        }

        [$width, $height, $type] = $info;

        if (!isset(self::ALLOWED[$type])) {
            throw new RuntimeException('不支援的圖片格式，請上傳 JPG、PNG、GIF 或 WebP。');
        }
        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION || $width < 1 || $height < 1) {
            throw new RuntimeException('圖片尺寸不正確或過大。');
        }

        $source = $this->decode($tmpPath, $type);
        if ($source === null) {
            throw new RuntimeException('圖片無法讀取，可能已損毀，請換一張再試。');
        }

        $extension = self::ALLOWED[$type];
        $base      = bin2hex(random_bytes(16));

        $displayName = $base . '.' . $extension;
        $thumbName   = $base . '_thumb.' . $extension;

        // --- display copy ---
        $display = $this->copyScaled($source, $width, $height, $maxWidth);
        $okBig   = $this->encode($display, $this->uploadDir . '/' . $displayName, $type);
        if ($display !== $source) {
            imagedestroy($display);
        }

        // --- thumbnail ---
        $thumb   = $this->copyScaled($source, $width, $height, $thumbWidth);
        $okSmall = $this->encode($thumb, $this->uploadDir . '/' . $thumbName, $type);
        if ($thumb !== $source) {
            imagedestroy($thumb);
        }

        imagedestroy($source);

        if (!$okBig || !$okSmall) {
            // Never leave half a photo behind.
            @unlink($this->uploadDir . '/' . $displayName);
            @unlink($this->uploadDir . '/' . $thumbName);
            throw new RuntimeException('圖片儲存失敗，請稍後再試。');
        }

        @chmod($this->uploadDir . '/' . $displayName, 0644);
        @chmod($this->uploadDir . '/' . $thumbName, 0644);

        return [
            'path'  => $this->publicPrefix . '/' . $displayName,
            'thumb' => $this->publicPrefix . '/' . $thumbName,
        ];
    }

    /**
     * Normalise PHP's awkward shape for <input type="file" multiple>.
     *
     * PHP gives one array per PROPERTY rather than one array per file:
     *   $_FILES['photos']['name']     = ['a.jpg', 'b.jpg']
     *   $_FILES['photos']['tmp_name'] = ['/tmp/x', '/tmp/y']
     * This flips it into the per-file shape the rest of the code expects.
     *
     * @return array[] one normal $_FILES-style entry per selected file
     */
    public static function normaliseMultiple(?array $field): array
    {
        if ($field === null || !isset($field['name'])) {
            return [];
        }

        // A single-file input arrives already in the right shape.
        if (!is_array($field['name'])) {
            return self::wasProvided($field) ? [$field] : [];
        }

        $files = [];
        foreach (array_keys($field['name']) as $i) {
            $one = [
                'name'     => $field['name'][$i]     ?? '',
                'type'     => $field['type'][$i]     ?? '',
                'tmp_name' => $field['tmp_name'][$i] ?? '',
                'error'    => $field['error'][$i]    ?? UPLOAD_ERR_NO_FILE,
                'size'     => $field['size'][$i]     ?? 0,
            ];
            if (self::wasProvided($one)) {
                $files[] = $one;
            }
        }
        return $files;
    }

    /** Delete a previously stored file (confined as absolutePath() describes). */
    public function delete(?string $relativePath): void
    {
        $full = $this->absolutePath($relativePath);
        if ($full !== null) {
            @unlink($full);
        }
    }

    /**
     * Turn a stored path back into a real file on disk, or null.
     *
     * The result is confined to this uploader's root folder, so a crafted
     * value such as "../../config/config.php" cannot reach outside it.
     */
    public function absolutePath(?string $relativePath): ?string
    {
        if (empty($relativePath)) {
            return null;
        }

        $root = realpath($this->rootDir);
        $full = realpath($this->pathBase . '/' . $relativePath);

        if ($root === false || $full === false) {
            return null;
        }
        if (!str_starts_with($full, $root . DIRECTORY_SEPARATOR)) {
            return null;   // outside the root folder — refuse
        }
        return is_file($full) ? $full : null;
    }

    /** Was a file actually chosen, and did PHP accept it? */
    public static function wasProvided(?array $file): bool
    {
        return $file !== null
            && isset($file['error'])
            && $file['error'] !== UPLOAD_ERR_NO_FILE;
    }

    // ------------------------------------------------------------------
    // internals
    // ------------------------------------------------------------------

    /** Turn PHP's upload error codes into messages a person can act on. */
    private function assertUploadOk(array $file): void
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_OK) {
            return;
        }

        throw new RuntimeException(match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                '圖片超過伺服器允許的上傳大小，請壓縮後再試。',
            UPLOAD_ERR_PARTIAL =>
                '上傳中斷，請重試。',
            UPLOAD_ERR_NO_FILE =>
                '未選擇任何檔案。',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE =>
                '伺服器無法暫存檔案，請聯絡管理員。',
            UPLOAD_ERR_EXTENSION =>
                '上傳被伺服器擋下，請聯絡管理員。',
            default =>
                '上傳失敗，請重試。',
        });
    }

    /** @return \GdImage|null */
    private function decode(string $path, int $type)
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_GIF  => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default        => false,
        };
        return $image === false ? null : $image;
    }

    private function encode($image, string $target, int $type): bool
    {
        return match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $target, self::JPEG_QUALITY),
            IMAGETYPE_PNG  => imagepng($image, $target, 6),
            IMAGETYPE_GIF  => imagegif($image, $target),
            IMAGETYPE_WEBP => imagewebp($image, $target, self::JPEG_QUALITY),
            default        => false,
        };
    }

    /**
     * Scale down and DESTROY the source (used by store(), which owns it).
     * @return \GdImage
     */
    private function resizeToWidth($image, int $width, int $height, int $maxWidth)
    {
        $resized = $this->copyScaled($image, $width, $height, $maxWidth);
        if ($resized !== $image) {
            imagedestroy($image);
        }
        return $resized;
    }

    /**
     * Scale down WITHOUT destroying the source, so one decode can feed
     * several output sizes. Returns the source itself when no scaling
     * is needed — callers must compare before destroying.
     *
     * @return \GdImage
     */
    private function copyScaled($image, int $width, int $height, int $targetWidth)
    {
        if ($width <= $targetWidth) {
            return $image;
        }

        $newWidth  = $targetWidth;
        $newHeight = max(1, (int) round($height * ($targetWidth / $width)));

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        // Keep transparency for PNG/GIF/WebP rather than filling it black.
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $resized;
    }

    /** Unguessable filename, generated here — never derived from the upload. */
    private function randomName(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }
}
