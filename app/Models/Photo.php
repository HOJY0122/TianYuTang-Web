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
    public function create(int $eventId, string $path, string $thumb, ?string $caption = null): int
    {
        $nextOrder = (int) $this->scalar(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM event_photos WHERE event_id = ?',
            [$eventId]
        );

        $this->execute(
            'INSERT INTO event_photos (event_id, file_path, thumb_path, caption, sort_order)
             VALUES (?, ?, ?, ?, ?)',
            [$eventId, $path, $thumb, $caption, $nextOrder]
        );

        return (int) $this->db->lastInsertId();
    }

    /** All photos for one event, in display order. */
    public function forEvent(int $eventId): array
    {
        return $this->fetchAll(
            'SELECT * FROM event_photos WHERE event_id = ? ORDER BY sort_order, id',
            [$eventId]
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
}
