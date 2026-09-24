<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Event;
use App\Models\Rsvp;
use Exception;

/**
 * RsvpController — the registration page and its submission.
 *
 * One submission = one registration group with ONE reference code,
 * holding every person in it: a family of four gets RSVP-0012 and four
 * attendee rows, each with their own name, IC and contact number.
 *
 *   Controller = check the input is sane, decide what the visitor sees
 *   Model      = how it is actually written to the database
 */
class RsvpController extends Controller
{
    /** GET /register */
    public function form(): void
    {
        $event = (new Event())->active();

        $old = $_SESSION['rsvp_old'] ?? [];
        unset($_SESSION['rsvp_old']);

        $this->view('register/index', [
            'event'      => $event,
            'activeNav'  => 'register',
            'window'     => Event::windowStatus($event, Event::SECTION_RSVP),
            'old'        => $old,
            'flash'      => $this->takeFlash(),
        ]);
    }

    /** POST /rsvp/submit */
    public function submit(): void
    {
        $this->requireCsrf();

        // Which event is this for? Always the active one — never a value
        // the browser supplies, or a visitor could register against a
        // closed or test event by editing the form.
        $event = (new Event())->active();
        $maxAttendees = (int) $event['max_attendees'];

        // Is registration actually open? Checked HERE, not just by hiding
        // the form — a closed form is still submittable by anyone who
        // crafts the POST or leaves a stale tab open past the deadline.
        $window = Event::windowStatus($event, Event::SECTION_RSVP);
        if (!$window['open']) {
            $this->flash(
                'error',
                $window['reason'] === 'not_yet' ? '報名尚未開放 Not open yet' : '報名已截止 Registration closed',
                Event::windowMessage($window, Event::SECTION_RSVP)
            );
            $this->redirect('/register');
        }

        $count    = (int) ($_POST['attendee_count'] ?? 0);
        // array_values(): the loop below reads [0], [1], [2]… but a
        // crafted POST can send attendee_name[7]=… — right count, wrong
        // keys — which would read keys that do not exist.
        $names    = array_values((array) ($_POST['attendee_name'] ?? []));
        $ics      = array_values((array) ($_POST['attendee_ic'] ?? []));
        $contacts = array_values((array) ($_POST['attendee_contact'] ?? []));

        // --- Validate the submission as a whole ---
        if ($count < 1 || $count > $maxAttendees) {
            $this->fail("報名人數不正確（1–{$maxAttendees} 位）。\nPlease choose between 1 and {$maxAttendees} people.");
        }
        if (count($names) !== $count || count($ics) !== $count || count($contacts) !== $count) {
            $this->fail("參加者資料不完整，請確認每位參加者都已填寫。\nPlease fill in the details for every person.");
        }

        // --- Validate each attendee ---
        $attendees = [];
        for ($i = 0; $i < $count; $i++) {
            // Each entry must be plain text. attendee_name[0][]=x would
            // arrive as a nested array and become the string "Array".
            if (!is_string($names[$i]) || !is_string($ics[$i]) || !is_string($contacts[$i])) {
                $this->fail("參加者資料格式不正確，請重新填寫。\nPlease check the details and try again.");
            }
            $name    = trim($names[$i]);
            $ic      = trim($ics[$i]);
            $contact = trim($contacts[$i]);
            $n       = $i + 1;

            if ($name === '' || mb_strlen($name) > 100) {
                $this->fail("第 {$n} 位參加者：請填寫姓名。\nPerson {$n}: please enter a name.");
            }
            if (!$this->looksLikeIc($ic)) {
                $this->fail("第 {$n} 位參加者：身份證號碼格式不正確。\nPerson {$n}: the IC / passport number does not look right.");
            }
            if (!$this->looksLikePhone($contact)) {
                $this->fail("第 {$n} 位參加者：聯絡號碼格式不正確。\nPerson {$n}: the contact number does not look right.");
            }

            $attendees[] = ['name' => $name, 'ic' => $ic, 'contact' => $contact];
        }

        // --- Save ---
        try {
            $refCode = (new Rsvp())->create(
                (int) $event['id'],
                $attendees,
                Event::refPrefix($event, 'RSVP')   // TEST-RSVP-0001 on a dry run
            );
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                die('RSVP save failed: ' . $e->getMessage());
            }
            $this->fail("報名失敗，請稍後再試。\nSomething went wrong, please try again later.");
        }

        // Hand the details to the confirmation page through the SESSION,
        // not the URL — reference codes are sequential and guessable, so
        // a ?ref= page would let anyone read other people's names and
        // IC numbers. IC numbers are masked even here.
        $_SESSION['rsvp_confirmation'] = [
            'ref_code'  => $refCode,
            'count'     => $count,
            'attendees' => array_map(
                static fn($a) => ['name' => $a['name'], 'ic' => mask_ic($a['ic']), 'contact' => $a['contact']],
                $attendees
            ),
        ];
        $this->redirect('/rsvp/success');
    }

    /**
     * Malaysian IC: 12 digits, with or without dashes (e.g. 901010-10-1234).
     * Kept lenient so foreign passport numbers are still accepted.
     */
    private function looksLikeIc(string $ic): bool
    {
        if ($ic === '' || mb_strlen($ic) > 30) {
            return false;
        }
        $digitsOnly = preg_replace('/\D/', '', $ic);
        // Either a proper 12-digit IC, or an alphanumeric passport-style ID.
        return strlen($digitsOnly) === 12 || preg_match('/^[A-Za-z0-9\- ]{6,30}$/', $ic) === 1;
    }

    /** Malaysian mobile/landline: 9–15 digits once spaces and dashes go. */
    private function looksLikePhone(string $phone): bool
    {
        $digitsOnly = preg_replace('/\D/', '', $phone);
        $len = strlen($digitsOnly);
        return $len >= 9 && $len <= 15;
    }

    /**
     * Send the visitor back with an explanation — and everything they
     * typed, so a family of eight is never retyped over one wrong digit.
     */
    private function fail(string $message): void
    {
        $text = static fn($list): array => array_values(array_map(
            static fn($v) => is_string($v) ? mb_substr($v, 0, 100) : '',
            is_array($list) ? $list : []
        ));
        $_SESSION['rsvp_old'] = [
            'count'    => max(1, (int) ($_POST['attendee_count'] ?? 1)),
            'names'    => $text($_POST['attendee_name'] ?? []),
            'ics'      => $text($_POST['attendee_ic'] ?? []),
            'contacts' => $text($_POST['attendee_contact'] ?? []),
        ];
        $this->flash('error', '報名未完成 Not submitted', $message);
        $this->redirect('/register');
    }
}
