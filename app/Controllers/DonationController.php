<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Validate;
use App\Models\Donation;
use App\Models\Event;
use Exception;

/**
 * DonationController — the donation page and its submission.
 *
 * A donor can sponsor merit seats (功德席), give a freewill amount
 * (隨喜), or BOTH in the same submission — two ticks on the form, one
 * reference number, one total.
 */
class DonationController extends Controller
{
    /** GET /donate */
    public function form(): void
    {
        $event = (new Event())->active();

        $old = $_SESSION['donation_old'] ?? [];
        unset($_SESSION['donation_old']);

        $this->view('donate/index', [
            'event'     => $event,
            'activeNav' => 'donate',
            'window'    => Event::windowStatus($event, Event::SECTION_DONATION),
            'old'       => $old,
            'flash'     => $this->takeFlash(),
        ]);
    }

    /** POST /donation/submit */
    public function submit(): void
    {
        $this->requireCsrf();

        // Bots and floods: a hidden trap field, and at most 10 forms per
        // address in 10 minutes (a family registering several groups is fine).
        if (\App\Core\RateLimit::isBot() || !\App\Core\RateLimit::allow('donation', 10, 10)) {
            $this->flash('error', '請稍後再試 Please try again later',
                "提交次數太多，請過幾分鐘再試。\nToo many submissions from this connection — please try again in a few minutes.");
            $this->redirect('/donate');
        }

        // Always the active event, never a browser-supplied id.
        $event     = (new Event())->active();
        $seatPrice = (float) $event['merit_table_price'];

        // Enforced server-side for the same reason as RSVP: hiding the
        // form is presentation, not protection.
        $window = Event::windowStatus($event, Event::SECTION_DONATION);
        if (!$window['open']) {
            $this->flash(
                'error',
                $window['reason'] === 'not_yet' ? '布施尚未開放 Not open yet' : '布施已截止 Donations closed',
                Event::windowMessage($window, Event::SECTION_DONATION)
            );
            $this->redirect('/donate');
        }

        $name    = trim((string) ($_POST['name'] ?? ''));
        $contact = trim((string) ($_POST['contact'] ?? ''));

        // Two independent ticks. An unticked part counts as zero even if
        // a number is still sitting in its box.
        // Seats: whole numbers only (2.5 is refused, not cut to 2). Freewill:
        // two decimal places at most, rounded half up — BEFORE the check, so
        // 0.001 cannot pass "> 0" and be stored as a RM 0.00 donation.
        $seats = !empty($_POST['want_seats']) ? Donation::parseSeats($_POST['table_count'] ?? '') : 0;
        $free  = !empty($_POST['want_free']) ? Donation::parseAmount($_POST['free_amount'] ?? '') : 0.0;
        if ($seats === null) {
            $this->fail("功德席數量只可以是整數（例如 1、2、3）。\nMerit seats must be a whole number (e.g. 1, 2, 3).");
        }
        if ($free === null) {
            $this->fail("隨喜金額格式不正確，最多兩位小數（例如 50 或 12.50）。\nPlease enter the amount as a number with at most two decimals (e.g. 50 or 12.50).");
        }

        if ($name === '' || mb_strlen($name) > 100) {
            $this->fail("請填寫姓名。\nPlease enter your name.");
        }
        $phone = Validate::phone($contact);
        if ($phone === null) {
            $this->fail(Validate::PHONE_MESSAGE);
        }
        if (!empty($_POST['want_seats']) && $seats <= 0) {
            $this->fail("請輸入功德席數量。\nPlease enter the number of merit seats.");
        }
        if (!empty($_POST['want_free']) && $free <= 0) {
            $this->fail("請輸入隨喜金額。\nPlease enter the freewill amount.");
        }
        if ($errors = Donation::validateParts($seats, $free)) {
            $this->fail(implode("\n", $errors));
        }
        if ($limitMessage = $this->limitProblem($event, $seats, $free)) {
            $this->fail($limitMessage, '感恩您的支持 Thank you for your support', 'info');
        }

        try {
            $result = (new Donation())->create(
                (int) $event['id'], $name, $phone, $seats, $free, $seatPrice,
                Event::refPrefix($event, 'DON')   // TEST-DON-0001 on a dry run
            );
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                die('Donation save failed: ' . $e->getMessage());
            }
            $this->fail("提交失敗，請稍後再試。\nSomething went wrong, please try again later.");
        }

        // Session, not URL — see the note in ConfirmController.
        $_SESSION['donation_confirmation'] = [
            'ref_code'    => $result['ref_code'],
            'amount'      => $result['amount'],
            'name'        => $name,
            'method'      => $result['method'],
            'seats'       => $seats,
            'free_amount' => $free,
            'seat_price'  => $seatPrice,
        ];
        $this->redirect('/donation/success');
    }

    /**
     * The committee's online limits for this event. Going OVER a maximum
     * is generosity, not a mistake — so the reply thanks the donor first,
     * then explains how to give the rest: another online submission, or
     * the counter on the day (which has no limit).
     */
    private function limitProblem(array $event, int $seats, float $free): ?string
    {
        $lim = Event::donationLimits($event);
        if ($seats > 0 && $seats < $lim['seats_min']) {
            return "功德席最少 {$lim['seats_min']} 席。\nThe minimum is {$lim['seats_min']} merit seats.";
        }
        if ($seats > $lim['seats_max']) {
            return "感恩您的大力護持！線上每次最多可認捐 {$lim['seats_max']} 席功德席。\n"
                 . "請先提交 {$lim['seats_max']} 席，再提交一次餘下的席數；或於活動當日親臨櫃台辦理。\n"
                 . "Thank you so much for your generous support! Online, each submission can sponsor up to {$lim['seats_max']} seats. "
                 . 'Please submit ' . $lim['seats_max'] . ' now and again for the rest, or visit our counter on the event day.';
        }
        if ($free > 0 && $free < $lim['free_min']) {
            return '隨喜金額最少 ' . rm($lim['free_min']) . "。\nThe minimum freewill amount is " . rm($lim['free_min']) . '.';
        }
        if ($free > $lim['free_max']) {
            return '感恩您的大力護持！線上每次隨喜最多 ' . rm($lim['free_max']) . "。\n"
                 . "請先提交此金額，再提交一次餘額；或於活動當日親臨櫃台辦理。\n"
                 . 'Thank you so much for your generous support! Online, each freewill gift can be up to ' . rm($lim['free_max'])
                 . '. Please submit that now and again for the rest, or visit our counter on the event day.';
        }
        return null;
    }

    /** Back to the form with the reason — and what they typed. */
    private function fail(string $message, string $title = '提交未完成 Not submitted', string $type = 'error'): void
    {
        $str = static fn(string $k): string => is_string($_POST[$k] ?? null) ? mb_substr($_POST[$k], 0, 100) : '';
        $_SESSION['donation_old'] = [
            'name'        => $str('name'),
            'contact'     => $str('contact'),
            'want_seats'  => !empty($_POST['want_seats']),
            'table_count' => $str('table_count'),
            'want_free'   => !empty($_POST['want_free']),
            'free_amount' => $str('free_amount'),
        ];
        $this->flash($type, $title, $message);
        $this->redirect('/donate');
    }
}
