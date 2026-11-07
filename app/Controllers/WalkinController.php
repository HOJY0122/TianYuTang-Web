<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\FormRules;
use App\Core\Validate;
use App\Models\Event;
use App\Models\Rsvp;
use App\Models\Zai;
use Exception;

/**
 * WalkinController — registering people who turn up on the day.
 *
 * The public form handles people who plan ahead. This handles everyone
 * else: online registration closed two weeks ago, an uncle arrives with
 * his family at nine in the morning, and the counter still has to write
 * them down. Before this page that meant a paper list nobody typed up,
 * so the head count, the check-in screen and the exported spreadsheet
 * all disagreed with the hall.
 *
 * It is the registration twin of the counter donation page, and behaves
 * the same way on purpose:
 *   - submission windows do NOT apply; this page exists for after they close
 *   - the registration is confirmed and checked in immediately
 *   - the volunteer who entered it is recorded on the row
 *
 * Walk-ins are ordinary registrations once saved, so they appear in the
 * dashboard, the check-in screen, the printed sheet and the export with
 * everything else — tagged 現場, never separated into their own silo.
 */
class WalkinController extends Controller
{
    /** How many people one walk-in form can register at once. */
    private const MAX_PER_ENTRY = 20;

    /** GET /admin/walkin?event=<id> */
    public function index(): void
    {
        $this->requireAdmin();

        $eventModel = new Event();
        $requested  = (int) ($_GET['event'] ?? 0);
        $event      = $requested > 0 ? $eventModel->find($requested) : null;
        $event      = $event ?? $eventModel->active();
        $eventId    = (int) $event['id'];

        $rsvp = new Rsvp();

        $this->view('admin/walkin', [
            'event'      => $event,
            'allEvents'  => $eventModel->all(),
            'counts'     => $rsvp->countsBySource($eventId),
            'recent'     => $rsvp->recentWalkIns($eventId),
            'maxPerForm' => min(self::MAX_PER_ENTRY, max(1, (int) $event['max_attendees'])),
            'flash'      => $this->takeFlash(),
            'old'        => $this->takeOld(),
        ]);
    }

    /** POST /admin/walkin/save */
    public function save(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $eventModel = new Event();
        $eventId    = (int) ($_POST['event_id'] ?? 0);
        $event      = $eventModel->find($eventId);

        if ($event === null) {
            $this->flash('error', '找不到活動', '請先選擇一個活動。');
            $this->redirect('/admin/walkin');
        }

        $names    = (array) ($_POST['attendee_name'] ?? []);
        $ics      = (array) ($_POST['attendee_ic'] ?? []);
        $contacts = (array) ($_POST['attendee_contact'] ?? []);

        $errors    = [];
        $attendees = [];

        // 來源: someone at the counter now, or a WhatsApp booking typed in ahead.
        $source = ($_POST['source'] ?? '') === 'whatsapp' ? 'whatsapp' : 'walkin';
        // A walk-in is one person, one IC, individual. Only a WhatsApp booking may hold several.
        $limit = $source === 'walkin' ? 1 : min(self::MAX_PER_ENTRY, max(1, (int) $event['max_attendees']));
        if (count(array_filter($names, static fn($v) => is_string($v) && trim($v) !== '')) > $limit) {
            $errors[] = $source === 'walkin'
                ? '現場報名一次只登記 1 位（1 人 1 身份證）。Walk-in: one person at a time, one IC each.'
                : "一次最多登記 {$limit} 位。人數較多請分兩次登記。";
        }

        foreach ($names as $i => $rawName) {
            // Plain text only — attendee_name[0][]=x arrives as a nested
            // array, which (string) would turn into the word "Array".
            if (!is_string($rawName) || !is_string($ics[$i] ?? '') || !is_string($contacts[$i] ?? '')) {
                $errors[] = '參加者資料格式不正確，請重新填寫。';
                break;
            }
            $name    = trim((string) $rawName);
            $ic      = trim((string) ($ics[$i] ?? ''));
            $contact = trim((string) ($contacts[$i] ?? ''));

            // A completely blank row is somebody who changed their mind
            // about the number of people — skip it rather than nag.
            if ($name === '' && $ic === '' && $contact === '') {
                continue;
            }

            $position = count($attendees) + 1;

            if ($name === '' || mb_strlen($name) > 100) {
                $errors[] = "第 {$position} 位：請填寫姓名。";
                continue;
            }
            // IC required and checked the same way as the online form, so the
            // age rule can be applied; the phone stays optional at the counter.
            $icOk = Validate::icOrPassport($ic);
            if ($icOk === null) {
                $errors[] = "第 {$position} 位：" . ($ic === '' ? '請填寫身份證號碼。IC number is required.' : Validate::IC_MESSAGE);
                continue;
            }
            $ic = $icOk;
            if (mb_strlen($contact) > 30) {
                $errors[] = "第 {$position} 位：聯絡號碼過長。";
                continue;
            }

            $attendees[] = [
                'name'    => $name,
                'ic'      => $ic,
                'contact' => $contact !== '' ? $contact : '—',
            ];
        }

        if (!$attendees && !$errors) {
            $errors[] = '請至少填寫一位參加者的姓名。';
        }

        if ($attendees && !$errors) {
            // Same age rule as the online registration (e.g. 66 and above).
            $rule = FormRules::ageRule();
            if ($rule !== null && ($problem = FormRules::groupAgeProblem(array_column($attendees, 'ic'), $rule, $event)) !== null) {
                $errors[] = $problem;
            }
            // Already registered online? They check in instead of registering twice.
            $zai = new Zai();
            foreach ($attendees as $n => $a) {
                if (($reg = $zai->registeredIc($eventId, $a['ic'])) !== null) {
                    $errors[] = '第 ' . ($n + 1) . " 位已在網上報名：{$reg['name']}（{$reg['ref_code']}），不用再登記，施齋當天在「施齋入座」確認。"
                              . "Already registered online ({$reg['ref_code']}) — no need to register again; confirm them in 施齋入座 on Day 3.";
                }
            }
        }

        if ($errors) {
            $_SESSION['walkin_errors'] = $errors;
            // Only plain text goes back into the form; anything else
            // (a crafted nested array) is dropped rather than echoed.
            $textOnly = static fn(array $list): array
                => array_map(static fn($v) => is_string($v) ? $v : '', $list);
            $_SESSION['walkin_old']    = [
                'names' => $textOnly($names), 'ics' => $textOnly($ics), 'contacts' => $textOnly($contacts),
                'source' => $source,
            ];
            $this->redirect("/admin/walkin?event={$eventId}");
        }

        try {
            $refCode = (new Rsvp())->createWalkIn(
                $eventId,
                $attendees,
                (string) ($_SESSION['admin_username'] ?? 'admin'),
                Event::refPrefix($event, 'RSVP'),
                $source
            );
        } catch (Exception $e) {
            $this->flash('error', '儲存失敗',
                DEBUG_MODE ? $e->getMessage() : '登記未儲存，請再試一次。');
            $this->redirect("/admin/walkin?event={$eventId}");
        }

        $count = count($attendees);
        $who = "{$refCode}　{$attendees[0]['name']}" . ($count > 1 ? " 等 {$count} 位" : '');
        $source === 'whatsapp'
            ? $this->flash('success', '已登記 WhatsApp 預約', $who . "　已登記。施齋當天在「施齋入座」確認入座。\nWhatsApp booking saved — confirm and seat them in 施齋入座 on Day 3.")
            : $this->flash('success', '已登記', $who . "　已登記。施齋當天在「施齋入座」確認入座。\nRegistered — confirm and seat them in 施齋入座 on Day 3.");
        $this->redirect("/admin/walkin?event={$eventId}");
    }

    /**
     * GET /admin/walkin/ic-check?ic= — for the form's live check:
     * {"message": ""} when the IC is free, otherwise why it cannot be used.
     */
    public function icCheck(): void
    {
        $this->requireAdmin();
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        $ic = Validate::icOrPassport(mb_substr((string) ($_GET['ic'] ?? ''), 0, 30));
        $event = (new Event())->find((int) ($_GET['event'] ?? 0)) ?? (new Event())->active();
        $reg = $ic === null ? null : (new Zai())->registeredIc((int) $event['id'], $ic);
        echo json_encode(['message' => $reg === null ? '' :
            "此身份證已經報名（{$reg['ref_code']} {$reg['name']}），不能再登記。施齋當天在「施齋入座」確認即可。\n"
            . "This IC is already registered ({$reg['ref_code']} {$reg['name']}) — it cannot be registered again. Confirm them in 施齋入座 on Day 3."],
            JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Errors and previously typed values, surviving one redirect. */
    private function takeOld(): array
    {
        $old    = $_SESSION['walkin_old'] ?? [];
        $errors = $_SESSION['walkin_errors'] ?? [];
        unset($_SESSION['walkin_old'], $_SESSION['walkin_errors']);
        return ['values' => $old, 'errors' => $errors];
    }
}
