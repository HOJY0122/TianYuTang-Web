<?php
namespace App\Models;

use App\Core\Model;

/**
 * Photo — the yearly photo archive.
 *
 * Every photo belongs to one event, so the gallery groups itself by year
 * with no extra bookkeeping. Ordering is explicit (sort_order) rather
 * than by upload time, because the committee will want the best picture
 * first, not whichever happened to upload fastest.
 */
class Photo extends Model
{
    /** Add a photo to an event, placed at the end of the current order. */
    public function create(int $eventId, string $path, string $thumb, ?string $caption = null, ?int $categoryId = null): int
    {
        $nextOrder = (int) $this->scalar(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM event_photos WHERE event_id = ?',
            [$eventId]
        );

        $this->execute(
            'INSERT INTO event_photos (event_id, category_id, file_path, thumb_path, caption, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$eventId, $categoryId ?: null, $path, $thumb, $caption, $nextOrder]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * All photos for one event, in display order. $category: null = every
     * photo, 0 = uncategorised ("其他 Others"), N = that category.
     */
    public function forEvent(int $eventId, ?int $category = null): array
    {
        [$cond, $p] = match (true) {
            $category === null => ['', []],
            $category === 0    => [' AND category_id IS NULL', []],
            default            => [' AND category_id = ?', [$category]],
        };
        return $this->fetchAll(
            'SELECT * FROM event_photos WHERE event_id = ?' . $cond . ' ORDER BY sort_order, id',
            array_merge([$eventId], $p)
        );
    }

    /** Photo counts per category for one event (admin filter tabs): category_id|0 => n. */
    public function categoryCounts(int $eventId): array
    {
        $out = [];
        foreach ($this->fetchAll('SELECT COALESCE(category_id, 0) AS c, COUNT(*) AS n FROM event_photos WHERE event_id = ? GROUP BY c', [$eventId]) as $r) {
            $out[(int) $r['c']] = (int) $r['n'];
        }
        return $out;
    }

    /** Move one photo to another category (null = "其他 Others"). */
    public function setCategory(int $id, ?int $categoryId): void
    {
        $this->execute('UPDATE event_photos SET category_id = ? WHERE id = ?', [$categoryId ?: null, $id]);
    }

    // ------------------------------------------------------------------
    // Public gallery, layer by layer: 年份 → 類別 → 相片. Only real
    // (non-test) events and visible categories; uncategorised photos form
    // "其他 Others" (category 0).

    private const VISIBLE = '(p.category_id IS NULL OR c.is_visible = 1)';

    /** Layer 1 — every year with photos: year, label, photo count, album count, cover. */
    public function galleryYears(): array
    {
        $rows = $this->fetchAll(
            'SELECT e.year, MAX(e.year_label) AS year_label, COUNT(p.id) AS photo_count,
                    COUNT(DISTINCT COALESCE(p.category_id, 0)) AS album_count
               FROM event_photos p
               JOIN events e ON e.id = p.event_id AND e.is_test = FALSE
               LEFT JOIN photo_categories c ON c.id = p.category_id
              WHERE ' . self::VISIBLE . '
              GROUP BY e.year ORDER BY e.year DESC'
        );
        foreach ($rows as &$r) {
            $r['cover'] = $this->cover((string) $r['year'], null);
        }
        return $rows;
    }

    /** Layer 2 — the categories of one year that have photos, in category order. */
    public function galleryAlbums(string $year): array
    {
        $rows = $this->fetchAll(
            'SELECT COALESCE(p.category_id, 0) AS cat_id, MAX(c.name_zh) AS name_zh, MAX(c.name_en) AS name_en,
                    COALESCE(MAX(c.sort_order), 999999) AS ord, COUNT(p.id) AS photo_count
               FROM event_photos p
               JOIN events e ON e.id = p.event_id AND e.is_test = FALSE
               LEFT JOIN photo_categories c ON c.id = p.category_id
              WHERE e.year = ? AND ' . self::VISIBLE . '
              GROUP BY cat_id ORDER BY ord, cat_id',
            [$year]
        );
        foreach ($rows as &$r) {
            $r['cat_id'] = (int) $r['cat_id'];
            $r['cover']  = $this->cover($year, $r['cat_id']);
        }
        return $rows;
    }

    /** Layer 3 — the photos of one year's category (0 = Others). */
    public function galleryPhotos(string $year, int $catId): array
    {
        return $this->fetchAll(
            'SELECT p.* FROM event_photos p
               JOIN events e ON e.id = p.event_id AND e.is_test = FALSE
               LEFT JOIN photo_categories c ON c.id = p.category_id
              WHERE e.year = ? AND ' . ($catId === 0 ? 'p.category_id IS NULL' : 'p.category_id = ? AND c.is_visible = 1') . '
              ORDER BY e.id, p.sort_order, p.id',
            $catId === 0 ? [$year] : [$year, $catId]
        );
    }

    /** The first photo (by the committee's order) of a year, or of one of its categories. */
    private function cover(string $year, ?int $catId): ?array
    {
        $cond = $catId === null ? self::VISIBLE : ($catId === 0 ? 'p.category_id IS NULL' : 'p.category_id = ' . (int) $catId);
        return $this->fetchOne(
            'SELECT p.thumb_path, p.file_path FROM event_photos p
               JOIN events e ON e.id = p.event_id AND e.is_test = FALSE
               LEFT JOIN photo_categories c ON c.id = p.category_id
              WHERE e.year = ? AND ' . $cond . '
              ORDER BY e.id DESC, p.sort_order, p.id LIMIT 1',
            [$year]
        );
    }

    /** The first few photos of an event — used for the homepage teaser. */
    public function previewForEvent(int $eventId, int $limit = 6): array
    {
        return $this->fetchAll(
            'SELECT * FROM event_photos WHERE event_id = ? ORDER BY sort_order, id LIMIT ' . (int) $limit,
            [$eventId]
        );
    }

    /** A single photo, or null. */
    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM event_photos WHERE id = ?', [$id]);
    }

    /** Update a caption. */
    public function setCaption(int $id, ?string $caption): bool
    {
        return $this->execute(
            'UPDATE event_photos SET caption = ? WHERE id = ?',
            [$caption, $id]
        ) >= 0;
    }

    /** Remove a photo row. The files are deleted by the controller. */
    public function delete(int $id): bool
    {
        return $this->execute('DELETE FROM event_photos WHERE id = ?', [$id]) > 0;
    }

    /**
     * Move a photo one place earlier or later.
     *
     * Implemented as a swap with its neighbour rather than renumbering
     * the whole set: fewer writes, and two photos cannot end up sharing
     * a position if two admins reorder at the same moment.
     */
    public function move(int $id, string $direction): bool
    {
        $photo = $this->find($id);
        if ($photo === null) {
            return false;
        }

        $comparison = $direction === 'up' ? '<' : '>';
        $order      = $direction === 'up' ? 'DESC' : 'ASC';

        $neighbour = $this->fetchOne(
            "SELECT * FROM event_photos
             WHERE event_id = ?
               AND (sort_order {$comparison} ? OR (sort_order = ? AND id {$comparison} ?))
             ORDER BY sort_order {$order}, id {$order}
             LIMIT 1",
            [$photo['event_id'], $photo['sort_order'], $photo['sort_order'], $id]
        );

        if ($neighbour === null) {
            return false;   // already at the end
        }

        try {
            $this->db->beginTransaction();
            $this->execute('UPDATE event_photos SET sort_order = ? WHERE id = ?',
                [$neighbour['sort_order'], $photo['id']]);
            $this->execute('UPDATE event_photos SET sort_order = ? WHERE id = ?',
                [$photo['sort_order'], $neighbour['id']]);
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Events that actually have photos, newest year first.
     * This is what the public gallery's year switcher is built from —
     * a year with no pictures should not appear as an empty tab.
     */
    public function eventsWithPhotos(): array
    {
        return $this->fetchAll(
            "SELECT e.id, e.name, e.year, e.year_label, COUNT(p.id) AS photo_count
             FROM events e
             JOIN event_photos p ON p.event_id = e.id
             WHERE e.is_test = FALSE
             GROUP BY e.id, e.name, e.year, e.year_label
             ORDER BY e.year DESC, e.id DESC"
        );
    }

    /**
     * Albums for the public pages: one per year that has photos, newest
     * first, each carrying its photos. A new event never hides an older
     * album — 2026 stays visible after 2027 is created.
     *
     * @param int $maxAlbums 0 = every year
     * @param int $perAlbum  0 = every photo; otherwise the first N
     */
    public function albums(int $maxAlbums = 0, int $perAlbum = 0): array
    {
        $years = $this->eventsWithPhotos();
        if ($maxAlbums > 0) {
            $years = array_slice($years, 0, $maxAlbums);
        }
        foreach ($years as &$year) {
            $year['photos'] = $perAlbum > 0
                ? $this->previewForEvent((int) $year['id'], $perAlbum)
                : $this->forEvent((int) $year['id']);
        }
        unset($year);
        return $years;
    }

    /**
     * The albums the home page shows (System → Forms & fonts → 相簿):
     *   previous  the latest album from BEFORE the live event — in 2026 the
     *             2025 photos, in 2027 the 2026 ones (the newest album if
     *             there is no older one yet)
     *   latest    the newest album, whichever year
     *   recent    the newest three
     */
    public function homeAlbums(string $mode, array $liveEvent, int $perAlbum): array
    {
        $years = $this->eventsWithPhotos();
        if ($mode === 'previous') {
            $liveYear = (int) ($liveEvent['year'] ?? 0);
            $older = array_values(array_filter($years, static fn($y) =>
                (int) $y['id'] !== (int) ($liveEvent['id'] ?? 0) && (int) $y['year'] < $liveYear));
            $years = array_slice($older ?: $years, 0, 1);
        } else {
            $years = array_slice($years, 0, $mode === 'recent' ? 3 : 1);
        }
        foreach ($years as &$year) {
            $year['photos'] = $this->previewForEvent((int) $year['id'], $perAlbum);
        }
        unset($year);
        return $years;
    }

    /**
     * Save a dragged order for one event's album: $ids from first to last.
     * Only photos of that event are touched, so a crafted list cannot
     * shuffle another year's album.
     */
    public function reorder(int $eventId, array $ids): void
    {
        $this->db->beginTransaction();
        try {
            foreach (array_values(array_unique(array_map('intval', $ids))) as $i => $id) {
                $this->execute('UPDATE event_photos SET sort_order = ? WHERE id = ? AND event_id = ?', [$i + 1, $id, $eventId]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
