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
    /** What the public sees: published only, pinned first, newest next. */
    public function feed(int $limit = 10): array
    {
        return $this->fetchAll(
            'SELECT * FROM posts WHERE is_published = TRUE
             ORDER BY is_pinned DESC, created_at DESC, id DESC
             LIMIT ' . (int) $limit
        );
    }

    /** Everything, drafts included, for the admin list. */
    public function all(): array
    {
        return $this->fetchAll('SELECT * FROM posts ORDER BY is_pinned DESC, created_at DESC, id DESC');
    }

    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM posts WHERE id = ?', [$id]);
    }

    public function create(array $f, string $createdBy): int
    {
        $this->execute(
            'INSERT INTO posts (title_zh, title_en, body_zh, body_en, image_path, is_published, is_pinned, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$f['title_zh'], $f['title_en'], $f['body_zh'], $f['body_en'], $f['image_path'] ?? null,
             $f['is_published'] ? 1 : 0, $f['is_pinned'] ? 1 : 0, $createdBy]
        );
        return (int) $this->db->lastInsertId();
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
