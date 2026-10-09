<?php
namespace App\Models;

use App\Core\Model;

/**
 * PhotoCategory — the kinds of album (新年, 中壇千秋寶誕, 慈善布施 …), set in
 * Admin → Photos. The public gallery goes 年份 year → 類別 category →
 * photos. A hidden category's photos stay in the admin but are not shown.
 */
class PhotoCategory extends Model
{
    public function all(bool $visibleOnly = false): array
    {
        return $this->fetchAll('SELECT * FROM photo_categories' . ($visibleOnly ? ' WHERE is_visible = 1' : '') . ' ORDER BY sort_order, id');
    }

    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM photo_categories WHERE id = ?', [$id]);
    }

    public function create(string $zh, ?string $en): int
    {
        $next = (int) $this->scalar('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM photo_categories');
        $this->execute('INSERT INTO photo_categories (name_zh, name_en, sort_order) VALUES (?, ?, ?)', [$zh, $en, $next]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $zh, ?string $en, bool $visible): void
    {
        $this->execute('UPDATE photo_categories SET name_zh = ?, name_en = ?, is_visible = ? WHERE id = ?', [$zh, $en, $visible ? 1 : 0, $id]);
    }

    /** Delete a category; its photos are kept and become "其他 Others". */
    public function delete(int $id): void
    {
        $this->execute('UPDATE event_photos SET category_id = NULL WHERE category_id = ?', [$id]);
        $this->execute('DELETE FROM photo_categories WHERE id = ?', [$id]);
    }

    public function move(int $id, string $dir): void
    {
        $ids = array_map('intval', array_column($this->all(), 'id'));
        $i = array_search($id, $ids, true);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($i === false || $j < 0 || $j >= count($ids)) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        foreach ($ids as $pos => $cid) {
            $this->execute('UPDATE photo_categories SET sort_order = ? WHERE id = ?', [$pos + 1, $cid]);
        }
    }
}
