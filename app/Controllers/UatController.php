<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Uat;
use App\Models\Event;
use App\Models\Setting;

/**
 * System → 🧪 UAT 測試模式. Only a system admin can switch it or change
 * its announcements; everyone else just sees the bar while it is on.
 */
class UatController extends Controller
{
    /** GET /system/uat */
    public function index(): void
    {
        $this->requireSystemAdmin();
        $events = new Event();
        $testId = Uat::testEventId();
        $test   = $testId ? $events->find($testId) : null;
        $site   = (new Setting())->site();
        $this->view('system/uat', [
            'pageTitle' => 'UAT 測試模式 UAT test mode',
            'nav'       => 'uat',
            'site'      => $site,
            'on'        => Uat::isOn($site),
            'live'      => $events->active(),
            'test'      => $test && !empty($test['is_test']) ? $test : null,
            'testStats' => $test ? $this->stats((int) $test['id']) : null,
            'flash'     => $this->takeFlash(),
        ]);
    }

    /** POST /system/uat/toggle — on, or off (optionally deleting the test data) */
    public function toggle(): void
    {
        $this->requireSystemAdmin();
        $this->requireCsrf();
        if (($_POST['to'] ?? '') === 'on') {
            $test = Uat::turnOn();
            $this->flash('success', 'UAT 已開啟 UAT is on',
                "現在使用測試活動「{$test['name']}」。所有頁面頂部會顯示測試公告。\n"
                . "The site now runs on the test event \"{$test['name']}\". Every page shows the test announcement bar.");
        } else {
            $delete = !empty($_POST['delete_test_data']);
            Uat::turnOff($delete);
            $this->flash('success', 'UAT 已關閉 UAT is off',
                "正式活動已恢復。" . ($delete ? '測試資料已刪除。' : '測試資料已保留，可隨時檢查或刪除。') . "\n"
                . 'The real event is live again. ' . ($delete ? 'The test data was deleted.' : 'The test data was kept for review.'));
        }
        $this->redirect('/system/uat');
    }

    /** POST /system/uat/save — announcements and speed */
    public function save(): void
    {
        $this->requireSystemAdmin();
        $this->requireCsrf();
        $setting = new Setting();
        $msgs = is_string($_POST['uat_messages'] ?? null) ? trim($_POST['uat_messages']) : '';
        $msgs = implode("\n", array_slice(array_filter(array_map(
            static fn($l) => mb_substr(trim($l), 0, 200), preg_split('/\R/u', $msgs)
        ), 'strlen'), 0, 12));
        $setting->set('uat_messages', $msgs === '' ? null : $msgs);
        $sec = (int) ($_POST['uat_interval'] ?? 4);
        $setting->set('uat_interval', (string) (in_array($sec, Uat::INTERVALS, true) ? $sec : 4));
        $this->flash('success', '已儲存 Saved', '測試公告已更新。The announcements are updated.');
        $this->redirect('/system/uat');
    }

    /** POST /system/uat/reset — throw the test data away and start from a fresh copy */
    public function reset(): void
    {
        $this->requireSystemAdmin();
        $this->requireCsrf();
        if (!Uat::isOn()) {
            $this->redirect('/system/uat');
        }
        Uat::resetTestData();
        $this->flash('success', '測試資料已重設 Test data reset', '舊的測試資料已刪除，換上全新的測試活動。The old test data was deleted and a fresh test event is live.');
        $this->redirect('/system/uat');
    }

    /** How much has been tried on the test event. */
    private function stats(int $eventId): array
    {
        $db = \App\Core\Database::conn();
        $q = static function (string $sql) use ($db, $eventId): int {
            $st = $db->prepare($sql);
            $st->execute([$eventId]);
            return (int) $st->fetchColumn();
        };
        return [
            'groups'    => $q('SELECT COUNT(*) FROM rsvp_groups WHERE event_id = ?'),
            'people'    => $q('SELECT COUNT(*) FROM rsvp_attendees a JOIN rsvp_groups g ON g.id = a.group_id WHERE g.event_id = ?'),
            'donations' => $q('SELECT COUNT(*) FROM donations WHERE event_id = ?'),
        ];
    }
}
