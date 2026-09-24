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
     * Slides carried over from the old single banner have no stored size;
     * read it from the file once and keep it.
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
        }
        unset($r);
        return $rows;
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

    /** Position, zoom, caption, link and on/off for one slide (values already checked). */
    public function tune(int $id, array $v): void
    {
        $this->execute(
            'UPDATE site_banners SET pos_x = ?, pos_y = ?, zoom = ?, is_active = ?, caption_zh = ?, caption_en = ?, link_url = ?
              WHERE id = ?',
            [$v['pos_x'], $v['pos_y'], $v['zoom'], $v['is_active'], $v['caption_zh'], $v['caption_en'], $v['link_url'], $id]
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
