<?php
namespace App\Core;

use App\Models\Event;
use App\Models\Setting;

/**
 * UAT test mode — switched on and off by a system admin (System → UAT).
 *
 * ON:  the real event is remembered and a TEST COPY of it becomes the live
 *      event (created the first time; reused afterwards). Everything testers
 *      do — registrations, donations, check-in, counter — lands on that test
 *      event (reference codes start with TEST-) and never touches the real
 *      records. A sliding announcement bar appears at the top of every page,
 *      public and admin.
 * OFF: the real event is live again and the bar disappears. The test data
 *      can be kept for review or deleted in the same step.
 *
 * Built on the existing test-event mechanism (Event::createTestCopy), so the
 * code being tested is exactly the code that will run for real.
 */
final class Uat
{
    public const INTERVALS = [2, 3, 4, 5, 6, 8];
    /** Bar size, % of normal (System → UAT → 大小 Size). */
    public const SIZE_MIN = 80, SIZE_MAX = 160;

    public static function size(?array $site = null): int
    {
        $site ??= (new Setting())->site();
        return max(self::SIZE_MIN, min(self::SIZE_MAX, (int) ($site['uat_size'] ?? 100)));
    }

    /** Shown when no messages have been written yet: "中文 | English" per line. */
    public const DEFAULT_MESSAGES = "🧪 系統測試中（UAT）— 現在看到的是測試資料 | System testing (UAT) — you are seeing test data\n"
        . "報名與布施只作測試，不會列入正式紀錄 | Registrations and donations here are tests, not real records\n"
        . "發現問題或有建議？請告訴管理員 | Found a problem or have an idea? Please tell the administrator";

    public static function isOn(?array $site = null): bool
    {
        $site ??= (new Setting())->site();
        return ($site['uat_mode'] ?? '0') === '1';
    }

    public static function interval(?array $site = null): int
    {
        $site ??= (new Setting())->site();
        $s = (int) ($site['uat_interval'] ?? 4);
        return in_array($s, self::INTERVALS, true) ? $s : 4;
    }

    /** @return array<int, array{0:string,1:string}> [Chinese, English] per message */
    public static function messages(?array $site = null): array
    {
        $site ??= (new Setting())->site();
        $raw = trim((string) ($site['uat_messages'] ?? ''));
        $out = [];
        foreach (preg_split('/\R/u', $raw !== '' ? $raw : self::DEFAULT_MESSAGES) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$zh, $en] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            $out[] = [$zh, $en];
        }
        return array_slice($out, 0, 12);
    }

    /**
     * Switch UAT on: a test copy of the real event becomes live.
     * @return array the test event now live
     */
    public static function turnOn(): array
    {
        $events  = new Event();
        $setting = new Setting();
        $live    = $events->active();

        // The real event to come back to (never a test event).
        $real = !empty($live['is_test'])
            ? ($events->find((int) $setting->get('uat_return_event_id', '0')) ?? ($events->allReal()[0] ?? $live))
            : $live;

        // Reuse the UAT test copy if it still exists; otherwise make one.
        $test = $events->find((int) $setting->get('uat_event_id', '0'));
        if ($test === null || empty($test['is_test'])) {
            $test = $events->find($events->createTestCopy((int) $real['id']));
        }

        $events->setActive((int) $test['id']);
        $setting->set('uat_return_event_id', (string) $real['id']);
        $setting->set('uat_event_id', (string) $test['id']);
        $setting->set('uat_mode', '1');
        return $test;
    }

    /** Switch UAT off: the real event is live again; optionally delete the test data. */
    public static function turnOff(bool $deleteTestData): void
    {
        $events  = new Event();
        $setting = new Setting();
        $real = $events->find((int) $setting->get('uat_return_event_id', '0'));
        if ($real === null || !empty($real['is_test'])) {
            $real = $events->allReal()[0] ?? null;
        }
        if ($real !== null) {
            $events->setActive((int) $real['id']);
        }
        if ($deleteTestData && ($testId = (int) $setting->get('uat_event_id', '0')) > 0) {
            $events->deleteTestEvent($testId);
            $setting->set('uat_event_id', '');
        }
        $setting->set('uat_mode', '0');
    }

    /** Start the test data over: delete the UAT test event and make a fresh copy (UAT stays on). */
    public static function resetTestData(): array
    {
        $setting = new Setting();
        $events  = new Event();
        if (($testId = (int) $setting->get('uat_event_id', '0')) > 0) {
            $real = $events->find((int) $setting->get('uat_return_event_id', '0')) ?? ($events->allReal()[0] ?? null);
            if ($real !== null) {
                $events->setActive((int) $real['id']);      // a live event must exist while the old copy goes
            }
            $events->deleteTestEvent($testId);
        }
        $setting->set('uat_event_id', '');
        return self::turnOn();
    }

    /** The UAT test event's id, or 0. */
    public static function testEventId(): int
    {
        return (int) (new Setting())->get('uat_event_id', '0');
    }
}
