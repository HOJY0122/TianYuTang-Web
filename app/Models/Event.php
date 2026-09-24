<?php
namespace App\Models;

use App\Core\Model;
use RuntimeException;

/**
 * Event — one row per year or season.
 *
 * This is the spine of the system. Everything the committee changes
 * yearly (dates, venue, price, banner, submission windows) lives on an
 * event row rather than in code, and every registration and donation
 * belongs to exactly one event.
 */
class Event extends Model
{
    /**
     * The event the public site is currently showing.
     *
     * Cached per request: the homepage asks for it several times while
     * rendering, and there is no reason to hit the database each time.
     *
     * If no row is flagged active — someone cleared the flag by hand —
     * fall back to the newest real event rather than showing visitors a
     * blank page. Only a database with no events at all is a genuine
     * setup error worth throwing on.
     *
     * @throws RuntimeException if the events table is empty
     */
    public function active(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $event = $this->fetchOne(
            'SELECT * FROM events WHERE is_active = TRUE ORDER BY id DESC LIMIT 1'
        );

        if ($event === null) {
            // Graceful fallback: newest non-test event.
            $event = $this->fetchOne(
                'SELECT * FROM events WHERE is_test = FALSE ORDER BY year DESC, id DESC LIMIT 1'
            );
        }

        if ($event === null) {
            throw new RuntimeException(
                'The events table is empty. Load schema.sql, or insert an event row and set is_active = TRUE.'
            );
        }

        return $cached = $event;
    }

    /** A single event by id, or null. */
    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM events WHERE id = ?', [$id]);
    }

    /** Every event, newest year first — for the admin event switcher. */
    public function all(): array
    {
        return $this->fetchAll('SELECT * FROM events ORDER BY year DESC, id DESC');
    }

    /** Real events only — test dry runs excluded. */
    public function allReal(): array
    {
        return $this->fetchAll('SELECT * FROM events WHERE is_test = FALSE ORDER BY year DESC, id DESC');
    }

    // ------------------------------------------------------------------
    // Test mode
    //
    // A dry run is just another event row with is_test = TRUE. That is
    // far lighter than a second database or a staging subdomain, and it
    // exercises the real code path rather than a copy of it that might
    // behave differently.
    // ------------------------------------------------------------------

    /**
     * Duplicate an event as a test copy. Management can then register
     * and donate against it exactly as the public would, without a
     * single row touching the real figures.
     *
     * @return int the new test event's id
     */
    public function createTestCopy(int $sourceId): int
    {
        $source = $this->find($sourceId);
        if ($source === null) {
            throw new RuntimeException('Cannot copy an event that does not exist.');
        }

        $fields = [];
        foreach (self::EDITABLE as $column) {
            $fields[$column] = $source[$column];
        }
        $fields['name'] = '【測試】' . $source['name'];

        // A test copy has no submission windows — management should be
        // able to try it at any hour, not only inside the real window.
        $fields['rsvp_opens_at']      = null;
        $fields['rsvp_closes_at']     = null;
        $fields['donation_opens_at']  = null;
        $fields['donation_closes_at'] = null;

        return $this->create($fields, true);
    }

    /**
     * Delete a test event and everything recorded against it.
     *
     * Refuses to touch a real event: the foreign keys already block
     * that at the database level, but checking here means the committee
     * gets a clear message instead of a constraint error, and a mistyped
     * id can never reach a year of real records.
     */
    public function deleteTestEvent(int $id): bool
    {
        $event = $this->find($id);
        if ($event === null || !$event['is_test']) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            // rsvp_attendees goes automatically — it cascades from
            // rsvp_groups. donations and rsvp_groups must go by hand
            // because their event_id is ON DELETE RESTRICT.
            $this->execute('DELETE FROM donations   WHERE event_id = ?', [$id]);
            $this->execute('DELETE FROM rsvp_groups WHERE event_id = ?', [$id]);
            $this->execute('DELETE FROM events      WHERE id = ? AND is_test = TRUE', [$id]);

            $this->db->commit();

            // A dry run consumes id numbers, and reference codes are built
            // from the row id — so without this the first real booking
            // after testing would read RSVP-0007 rather than RSVP-0001.
            // Only safe to reset while the table is genuinely empty.
            $this->resetAutoIncrementIfEmpty('rsvp_groups');
            $this->resetAutoIncrementIfEmpty('rsvp_attendees');
            $this->resetAutoIncrementIfEmpty('donations');

            return true;
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * How many OTHER events still point at this image file?
     *
     * A test copy inherits the banner and favicon paths of the event it
     * was copied from, so the same file is referenced twice. Deleting
     * the test event must not delete a file the real event is still
     * using — this is the check that prevents that.
     */
    public function countOtherEventsUsingImage(?string $path, int $excludeEventId): int
    {
        if (empty($path)) {
            return 0;
        }
        return (int) $this->scalar(
            'SELECT COUNT(*) FROM events
             WHERE id <> ? AND (hero_banner_path = ? OR favicon_path = ?)',
            [$excludeEventId, $path, $path]
        );
    }

    /**
     * Reference-code prefix for an event.
     * Test submissions are stamped TEST- so nobody can mistake a dry-run
     * confirmation for a real booking.
     */
    public static function refPrefix(array $event, string $base): string
    {
        return !empty($event['is_test']) ? 'TEST-' . $base : $base;
    }

    /**
     * Make one event the active one. Exactly one row may be active, so
     * this clears the rest in the same transaction — otherwise a failure
     * halfway through could leave the site with no active event at all.
     */
    public function setActive(int $id): bool
    {
        try {
            $this->db->beginTransaction();
            $this->execute('UPDATE events SET is_active = FALSE');
            $changed = $this->execute('UPDATE events SET is_active = TRUE WHERE id = ?', [$id]);
            $this->db->commit();
            return $changed > 0;
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Fields the admin form is allowed to write. Anything outside this
     * list is ignored, so a crafted POST cannot flip is_active or
     * is_test — those have their own deliberate actions.
     */
    private const EDITABLE = [
        'name', 'year', 'year_label', 'subtitle', 'location',
        'start_date', 'end_date', 'counter_note',
        'merit_table_price', 'max_attendees',
        'hero_banner_path', 'favicon_path',
        'rsvp_opens_at', 'rsvp_closes_at',
        'donation_opens_at', 'donation_closes_at',
    ];

    /** Update the editable fields of an event. */
    public function update(int $id, array $fields): bool
    {
        $set = [];
        $params = [];
        foreach ($fields as $key => $value) {
            if (in_array($key, self::EDITABLE, true)) {
                $set[] = "{$key} = ?";
                $params[] = $value;
            }
        }
        if (!$set) {
            return false;
        }

        $params[] = $id;
        $this->execute(
            'UPDATE events SET ' . implode(', ', $set) . ' WHERE id = ?',
            $params
        );
        // Note: rowCount() is 0 when the submitted values match what is
        // already stored. That is a successful save, not a failure, so
        // this returns true as long as the row exists.
        return $this->find($id) !== null;
    }

    /**
     * Create a new event. Always created inactive — the committee
     * activates it deliberately once they are happy with the details,
     * so building next year's event never disturbs the live site.
     *
     * @return int the new event's id
     */
    public function create(array $fields, bool $isTest = false): int
    {
        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($fields as $key => $value) {
            if (in_array($key, self::EDITABLE, true)) {
                $columns[] = $key;
                $placeholders[] = '?';
                $params[] = $value;
            }
        }

        $columns[] = 'is_active';
        $placeholders[] = '?';
        $params[] = 0;

        $columns[] = 'is_test';
        $placeholders[] = '?';
        $params[] = $isTest ? 1 : 0;

        $this->execute(
            'INSERT INTO events (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $placeholders) . ')',
            $params
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Check submitted event details.
     *
     * Returns a list of human-readable problems; an empty list means the
     * input is good. Validation lives here rather than in the controller
     * so that creating and editing cannot drift apart.
     *
     * @return string[]
     */
    public static function validate(array $input): array
    {
        $errors = [];

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 150) {
            $errors[] = '活動名稱不可空白，且不可超過 150 字。';
        }

        $location = trim((string) ($input['location'] ?? ''));
        if ($location === '' || mb_strlen($location) > 255) {
            $errors[] = '地點不可空白，且不可超過 255 字。';
        }

        $year = (int) ($input['year'] ?? 0);
        if ($year < 2000 || $year > 2100) {
            $errors[] = '年份必須介於 2000 至 2100 之間。';
        }

        $start = (string) ($input['start_date'] ?? '');
        $end   = (string) ($input['end_date'] ?? '');
        $startTs = strtotime($start);
        $endTs   = strtotime($end);

        if ($start === '' || $startTs === false) {
            $errors[] = '開始日期格式不正確。';
        }
        if ($end === '' || $endTs === false) {
            $errors[] = '結束日期格式不正確。';
        }
        if ($startTs !== false && $endTs !== false) {
            if ($endTs < $startTs) {
                $errors[] = '結束日期不可早於開始日期。';
            } elseif (($endTs - $startTs) > 30 * 86400) {
                $errors[] = '活動期間不可超過 30 天，請確認日期是否填錯。';
            }
        }

        $price = (float) ($input['merit_table_price'] ?? -1);
        if ($price < 0 || $price > 100000) {
            $errors[] = '功德席價格必須介於 0 至 100,000 之間。';
        }

        $max = (int) ($input['max_attendees'] ?? 0);
        if ($max < 1 || $max > 50) {
            $errors[] = '每次報名人數上限必須介於 1 至 50 之間。';
        }

        // Submission windows — both ends optional, but if both are given
        // the close must come after the open.
        foreach ([
            self::SECTION_RSVP     => '報名',
            self::SECTION_DONATION => '布施',
        ] as $section => $label) {
            $opens  = trim((string) ($input["{$section}_opens_at"]  ?? ''));
            $closes = trim((string) ($input["{$section}_closes_at"] ?? ''));

            $opensTs  = $opens  !== '' ? strtotime($opens)  : null;
            $closesTs = $closes !== '' ? strtotime($closes) : null;

            if ($opens !== '' && $opensTs === false) {
                $errors[] = "{$label}開放時間格式不正確。";
            }
            if ($closes !== '' && $closesTs === false) {
                $errors[] = "{$label}截止時間格式不正確。";
            }
            if ($opensTs && $closesTs && $closesTs <= $opensTs) {
                $errors[] = "{$label}截止時間必須晚於開放時間。";
            }
        }

        return $errors;
    }

    /**
     * Human-readable date line for the event, one entry per day.
     * "2026年10月16日（星期五）" etc — built from start_date/end_date so
     * changing the dates in admin updates the page with no code change.
     *
     * @return string[] one formatted line per day
     */
    public static function formatDateLines(array $event): array
    {
        $weekdays = ['日', '一', '二', '三', '四', '五', '六'];
        $lines = [];

        $start = strtotime($event['start_date']);
        $end   = strtotime($event['end_date']);
        if ($start === false || $end === false || $end < $start) {
            return $lines;
        }

        // Guard against a silly range locking the page up.
        $maxDays = 31;
        for ($day = $start, $n = 0; $day <= $end && $n < $maxDays; $day = strtotime('+1 day', $day), $n++) {
            $lines[] = date('Y', $day) . '年'
                . (int) date('n', $day) . '月'
                . (int) date('j', $day) . '日'
                . '（星期' . $weekdays[(int) date('w', $day)] . '）';
        }

        return $lines;
    }

    // ------------------------------------------------------------------
    // Submission windows
    //
    // Each section (RSVP, donation) has an optional opens_at and
    // closes_at. NULL means "no limit on that end", so leaving both
    // blank keeps a section open indefinitely — which is how an event
    // behaves until the committee decides otherwise.
    // ------------------------------------------------------------------

    public const SECTION_RSVP     = 'rsvp';
    public const SECTION_DONATION = 'donation';

    /**
     * Is a section accepting submissions right now?
     *
     * @return array{open:bool, reason:string, opens_at:?string, closes_at:?string}
     *         `reason` is one of: open, not_yet, closed
     */
    public static function windowStatus(array $event, string $section): array
    {
        $opensAt  = $event["{$section}_opens_at"]  ?? null;
        $closesAt = $event["{$section}_closes_at"] ?? null;

        $now      = time();
        $opensTs  = $opensAt  ? strtotime($opensAt)  : null;
        $closesTs = $closesAt ? strtotime($closesAt) : null;

        $result = [
            'open'      => true,
            'reason'    => 'open',
            'opens_at'  => $opensAt,
            'closes_at' => $closesAt,
        ];

        if ($opensTs !== null && $opensTs !== false && $now < $opensTs) {
            $result['open']   = false;
            $result['reason'] = 'not_yet';
            return $result;
        }

        if ($closesTs !== null && $closesTs !== false && $now > $closesTs) {
            $result['open']   = false;
            $result['reason'] = 'closed';
            return $result;
        }

        return $result;
    }

    /**
     * A bilingual message explaining why a section is unavailable,
     * suitable for showing to a visitor.
     */
    public static function windowMessage(array $status, string $section): string
    {
        $what = $section === self::SECTION_RSVP ? '報名' : '布施';

        if ($status['reason'] === 'not_yet') {
            $when = self::formatDateTime($status['opens_at']);
            return "線上{$what}尚未開放，將於 {$when} 開始，敬請留意。";
        }

        if ($status['reason'] === 'closed') {
            $when = self::formatDateTime($status['closes_at']);
            return "線上{$what}已於 {$when} 截止。如仍希望參與，歡迎於活動當日親臨現場辦理。";
        }

        return '';
    }

    /** Format a stored DATETIME for display, e.g. "2026年10月15日 23:59". */
    public static function formatDateTime(?string $datetime): string
    {
        if (empty($datetime)) {
            return '';
        }
        $ts = strtotime($datetime);
        if ($ts === false) {
            return '';
        }
        return date('Y', $ts) . '年' . (int) date('n', $ts) . '月' . (int) date('j', $ts) . '日 ' . date('H:i', $ts);
    }

    /** Short English date range, e.g. "16, 17 & 18 October". */
    public static function formatDateRangeEn(array $event): string
    {
        $start = strtotime($event['start_date']);
        $end   = strtotime($event['end_date']);
        if ($start === false || $end === false || $end < $start) {
            return '';
        }

        $days = [];
        for ($day = $start, $n = 0; $day <= $end && $n < 31; $day = strtotime('+1 day', $day), $n++) {
            $days[] = (int) date('j', $day);
        }

        $month = date('F', $start);
        if (count($days) === 1) {
            return $days[0] . ' ' . $month;
        }

        $last = array_pop($days);
        return implode(', ', $days) . ' & ' . $last . ' ' . $month;
    }
}
