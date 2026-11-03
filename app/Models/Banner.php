<?php
namespace App\Models;

use App\Core\Model;

/**
 * Banner — the pictures that take turns at the top of the home page.
 *
 * Each row can be nudged and zoomed (pos_x / pos_y / zoom) so the part
 * that matters stays in view whatever height the banner is given.
 * How tall the banner is, how fast it changes and how are site settings
 * (banner_* in Setting::DEFAULTS), shared by every slide.
 */
class Banner extends Model
{
    /** Every slide, in display order. */
    public function all(): array
    {
        return $this->withSizes($this->fetchAll('SELECT * FROM site_banners ORDER BY sort_order, id'));
    }

    /** The slides the public home page shows. */
    public function active(): array
    {
        return $this->withSizes($this->fetchAll('SELECT * FROM site_banners WHERE is_active = 1 ORDER BY sort_order, id'));
    }

    /**
     * Slides carried over from the old single banner have no stored size,
     * and older slides no edge colour; read them from the file once and keep them.
     */
    public function withSizes(array $rows): array
    {
        foreach ($rows as &$r) {
            if (empty($r['img_w']) || empty($r['img_h'])) {
                $info = @getimagesize(BASE_PATH . '/public/' . $r['image_path']);
                $r['img_w'] = $info ? $info[0] : 1920;
                $r['img_h'] = $info ? $info[1] : 640;
                if ($info) {
                    $this->execute('UPDATE site_banners SET img_w = ?, img_h = ? WHERE id = ?', [$r['img_w'], $r['img_h'], $r['id']]);
                }
            }
            if (empty($r['edge_color'])) {
                $r['edge_color'] = self::edgeColor(BASE_PATH . '/public/' . $r['image_path']);
                if ($r['edge_color'] !== null) {
                    $this->execute('UPDATE site_banners SET edge_color = ? WHERE id = ?', [$r['edge_color'], $r['id']]);
                }
            }
        }
        unset($r);
        return $rows;
    }

    /**
     * The most common colour along the picture's four edges ("#fff4c4"), so
     * the sides of a picture shown whole can match its own background.
     * Colours are grouped coarsely to find the winner, then that group's
     * real colours are averaged.
     */
    public static function edgeColor(string $file): ?string
    {
        $info = @getimagesize($file);
        if (!$info || $info[0] * $info[1] > 40_000_000) {
            return null;
        }
        $im = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG  => @imagecreatefrompng($file),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file),
            IMAGETYPE_GIF  => @imagecreatefromgif($file),
            default        => false,
        };
        if (!$im) {
            return null;
        }
        [$w, $h] = [imagesx($im), imagesy($im)];
        $band = max(2, (int) round(min($w, $h) * 0.01));
        $step = max(1, (int) round(max($w, $h) / 400));
        $groups = [];
        $take = static function (int $x, int $y) use ($im, &$groups): void {
            $c = imagecolorsforindex($im, imagecolorat($im, $x, $y));
            if ($c['alpha'] > 64) {
                return;                                   // transparent edge: not a colour
            }
            $k = ($c['red'] >> 4) . '.' . ($c['green'] >> 4) . '.' . ($c['blue'] >> 4);
            $g = &$groups[$k];
            $g = [($g[0] ?? 0) + 1, ($g[1] ?? 0) + $c['red'], ($g[2] ?? 0) + $c['green'], ($g[3] ?? 0) + $c['blue']];
        };
        for ($i = 0; $i < $band; $i++) {
            for ($x = 0; $x < $w; $x += $step) { $take($x, $i); $take($x, $h - 1 - $i); }
            for ($y = 0; $y < $h; $y += $step) { $take($i, $y); $take($w - 1 - $i, $y); }
        }
        imagedestroy($im);
        if (!$groups) {
            return null;
        }
        usort($groups, static fn($a, $b) => $b[0] <=> $a[0]);
        [$n, $r, $g, $b] = $groups[0];
        return sprintf('#%02x%02x%02x', (int) round($r / $n), (int) round($g / $n), (int) round($b / $n));
    }

    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM site_banners WHERE id = ?', [$id]);
    }

    public function add(string $path, int $w, int $h): int
    {
        $next = (int) $this->scalar('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM site_banners');
        $this->execute('INSERT INTO site_banners (image_path, img_w, img_h, sort_order) VALUES (?, ?, ?, ?)',
            [$path, max(1, min(65535, $w)), max(1, min(65535, $h)), $next]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * How a picture fills the banner: cover = fill and crop; blur / plain =
     * the whole picture, the empty sides blurred or the banner's colour.
     */
    public const FITS = [
        'cover' => ['填滿（多出的部分會裁掉）', 'Fill — the extra is cropped'],
        'blur'  => ['整張顯示，兩旁模糊', 'Whole picture, blurred sides'],
        'plain' => ['整張顯示，兩旁用圖片邊緣的顏色', 'Whole picture, sides in its own edge colour'],
    ];

    /** Position, zoom, fit, caption, link and on/off for one slide (values already checked). */
    public function tune(int $id, array $v): void
    {
        $this->execute(
            'UPDATE site_banners SET pos_x = ?, pos_y = ?, zoom = ?, fit = ?, is_active = ?, caption_zh = ?, caption_en = ?, link_url = ?
              WHERE id = ?',
            [$v['pos_x'], $v['pos_y'], $v['zoom'], $v['fit'], $v['is_active'], $v['caption_zh'], $v['caption_en'], $v['link_url'], $id]
        );
    }

    public function reorder(array $ids): void
    {
        $this->db->beginTransaction();
        try {
            foreach (array_values(array_unique(array_map('intval', $ids))) as $i => $id) {
                $this->execute('UPDATE site_banners SET sort_order = ? WHERE id = ?', [$i + 1, $id]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM site_banners WHERE id = ?', [$id]);
    }

    /** How many slides use this file (the same picture can be added twice). */
    public function countUsing(string $path): int
    {
        return (int) $this->scalar('SELECT COUNT(*) FROM site_banners WHERE image_path = ?', [$path]);
    }
}
