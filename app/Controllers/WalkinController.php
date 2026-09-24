<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Event;
use App\Models\Rsvp;
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

        $limit = min(self::MAX_PER_ENTRY, max(1, (int) $event['max_attendees']));
        if (count($names) > $limit) {
            $errors[] = "一次最多登記 {$limit} 位。人數較多請分兩次登記。";
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
            // IC and contact are optional at the counter, unlike online.
            // An elderly visitor may not have their IC on them and will
            // not recite a phone number to a queue — refusing to record
            // their attendance over that would be worse than useless.
            if (mb_strlen($ic) > 30) {
                $errors[] = "第 {$position} 位：身份證號碼過長。";
                continue;
            }
            if (mb_strlen($contact) > 30) {
                $errors[] = "第 {$position} 位：聯絡號碼過長。";
                continue;
            }

            $attendees[] = [
                'name'    => $name,
                'ic'      => $ic !== '' ? $ic : '—',
                'contact' => $contact !== '' ? $contact : '—',
            ];
        }

        if (!$attendees && !$errors) {
            $errors[] = '請至少填寫一位參加者的姓名。';
        }

        if ($errors) {
            $_SESSION['walkin_errors'] = $errors;
            // Only plain text goes back into the form; anything else
            // (a crafted nested array) is dropped rather than echoed.
            $textOnly = static fn(array $list): array
                => array_map(static fn($v) => is_string($v) ? $v : '', $list);
            $_SESSION['walkin_old']    = [
                'names' => $textOnly($names), 'ics' => $textOnly($ics), 'contacts' => $textOnly($contacts),
            ];
            $this->redirect("/admin/walkin?event={$eventId}");
        }

        try {
            $refCode = (new Rsvp())->createWalkIn(
                $eventId,
                $attendees,
                (string) ($_SESSION['admin_username'] ?? 'admin'),
                Event::refPrefix($event, 'RSVP')
            );
        } catch (Exception $e) {
            $this->flash('error', '儲存失敗',
                DEBUG_MODE ? $e->getMessage() : '登記未儲存，請再試一次。');
            $this->redirect("/admin/walkin?event={$eventId}");
        }

        $count = count($attendees);
        $this->flash(
            'success',
            '已登記並報到',
            "{$refCode}　{$attendees[0]['name']}"
            . ($count > 1 ? " 等 {$count} 位" : '')
            . '　已登記並完成報到。'
        );
        $this->redirect("/admin/walkin?event={$eventId}");
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
