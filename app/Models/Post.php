<?php
namespace App\Models;

use App\Core\Model;

/**
 * Post — news and announcements on the home page, like a small feed.
 *
 * "Parking is full, please use the side entrance", "Thank you to all
 * who attended" — the kind of update the committee would otherwise send
 * round on WhatsApp. Not tied to an event: news outlives the year.
 *
 * Each post has a Chinese and an optional English version. Pinned posts
 * stay at the top; unpublished ones are drafts only admins can see.
 */
class Post extends Model
{
    /**
     * What the public sees: published only, in the order the admin
     * dragged them into (sort_order). New posts start at the top.
     */
    public function feed(int $limit = 10): array
    {
        return $this->fetchAll(
            'SELECT * FROM posts WHERE is_published = TRUE
             ORDER BY sort_order, created_at DESC, id DESC
             LIMIT ' . (int) $limit
        );
    }

    /** Everything, drafts included, for the admin list. */
    public function all(): array
    {
        return $this->fetchAll('SELECT * FROM posts ORDER BY sort_order, created_at DESC, id DESC');
    }

    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM posts WHERE id = ?', [$id]);
    }

    public function create(array $f, string $createdBy): int
    {
        $this->execute(
            'INSERT INTO posts (title_zh, title_en, body_zh, body_en, image_path, is_published, is_pinned, sort_order, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$f['title_zh'], $f['title_en'], $f['body_zh'], $f['body_en'], $f['image_path'] ?? null,
             $f['is_published'] ? 1 : 0, $f['is_pinned'] ? 1 : 0, $this->topSlot(), $createdBy]
        );
        return (int) $this->db->lastInsertId();
    }

    /** A sort_order above every existing post — where new and newly pinned posts go. */
    public function topSlot(): int
    {
        return (int) $this->fetchOne('SELECT COALESCE(MIN(sort_order), 1) - 1 AS s FROM posts')['s'];
    }

    public function moveToTop(int $id): void
    {
        $this->execute('UPDATE posts SET sort_order = ? WHERE id = ?', [$this->topSlot(), $id]);
    }

    /**
     * Save a dragged order: $ids from top to bottom. Ids that are not
     * posts are ignored; posts missing from the list keep their place
     * after the ones given.
     */
    public function reorder(array $ids): void
    {
        $this->db->beginTransaction();
        try {
            foreach (array_values(array_unique(array_map('intval', $ids))) as $i => $id) {
                $this->execute('UPDATE posts SET sort_order = ? WHERE id = ?', [$i + 1, $id]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Update a post. image_path is only written when the key is present. */
    public function update(int $id, array $f): void
    {
        $sql    = 'UPDATE posts SET title_zh = ?, title_en = ?, body_zh = ?, body_en = ?, is_published = ?, is_pinned = ?';
        $params = [$f['title_zh'], $f['title_en'], $f['body_zh'], $f['body_en'],
                   $f['is_published'] ? 1 : 0, $f['is_pinned'] ? 1 : 0];
        if (array_key_exists('image_path', $f)) {
            $sql     .= ', image_path = ?';
            $params[] = $f['image_path'];
        }
        $params[] = $id;
        $this->execute($sql . ' WHERE id = ?', $params);
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM posts WHERE id = ?', [$id]);
    }

    /**
     * @return string[] problems; empty means acceptable
     */
    public static function validate(array $f): array
    {
        $errors = [];
        if ($f['title_zh'] === '' || mb_strlen($f['title_zh']) > 200) {
            $errors[] = '請填寫中文標題（200 字以內）。Chinese title is required (max 200).';
        }
        if (mb_strlen((string) $f['title_en']) > 200) {
            $errors[] = '英文標題過長。English title is too long.';
        }
        if (mb_strlen((string) $f['body_zh']) > 5000 || mb_strlen((string) $f['body_en']) > 5000) {
            $errors[] = '內容過長（5000 字以內）。Content is too long (max 5000).';
        }
        return $errors;
    }
}
