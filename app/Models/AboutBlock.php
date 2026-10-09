<?php
namespace App\Models;

use App\Core\Model;

/**
 * AboutBlock — one piece of the 關於我們 About page: a large centred
 * picture ('image') or a heading with paragraphs ('text'), shown in
 * sort_order. Edited in System → 關於我們 About page.
 */
class AboutBlock extends Model
{
    /** Layout choices: value => [中文, English]. The first of each is the default. */
    public const WIDTHS = ['m' => ['中', 'Medium'], 's' => ['窄', 'Narrow'], 'l' => ['寬', 'Wide'], 'full' => ['全寬', 'Full width']];
    public const TEXT_SIZES = ['m' => ['中', 'Medium'], 's' => ['小', 'Small'], 'l' => ['大', 'Large']];
    public const ALIGNS = ['justify' => ['左右對齊', 'Justified'], 'left' => ['靠左', 'Left'], 'center' => ['置中', 'Centred']];

    public function all(): array
    {
        return $this->fetchAll('SELECT * FROM about_blocks ORDER BY sort_order, id');
    }

    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM about_blocks WHERE id = ?', [$id]);
    }

    public function count(): int
    {
        return (int) $this->scalar('SELECT COUNT(*) FROM about_blocks');
    }

    /** New blocks go to the end. */
    public function create(array $v): int
    {
        $next = (int) $this->scalar('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM about_blocks');
        $this->execute(
            'INSERT INTO about_blocks (kind, image_path, heading_zh, heading_en, body_zh, body_en, width, text_size, align, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$v['kind'], $v['image_path'], $v['heading_zh'], $v['heading_en'], $v['body_zh'], $v['body_en'],
             $v['width'], $v['text_size'], $v['align'], $next]
        );
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $v): void
    {
        $this->execute(
            'UPDATE about_blocks SET image_path = ?, heading_zh = ?, heading_en = ?, body_zh = ?, body_en = ?,
                    width = ?, text_size = ?, align = ? WHERE id = ?',
            [$v['image_path'], $v['heading_zh'], $v['heading_en'], $v['body_zh'], $v['body_en'],
             $v['width'], $v['text_size'], $v['align'], $id]
        );
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM about_blocks WHERE id = ?', [$id]);
    }

    /** Save a new order: the ids in the order they now appear. */
    public function reorder(array $ids): void
    {
        $pos = 1;
        foreach ($ids as $id) {
            $this->execute('UPDATE about_blocks SET sort_order = ? WHERE id = ?', [$pos++, (int) $id]);
        }
    }

    /** Swap a block with its neighbour (the ↑ ↓ buttons). */
    public function move(int $id, string $dir): void
    {
        $ids = array_map('intval', array_column($this->all(), 'id'));
        $i = array_search($id, $ids, true);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($i === false || $j < 0 || $j >= count($ids)) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        $this->reorder($ids);
    }
}
