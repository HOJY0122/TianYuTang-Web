<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Donation;
use App\Models\Event;
use Exception;

/**
 * DonationController — validates and stores 功德布施 submissions.
 */
class DonationController extends Controller
{
    /** POST /donation/submit */
    public function submit(): void
    {
        $this->requireCsrf();

        // Always the active event, never a browser-supplied id.
        $event     = (new Event())->active();
        $seatPrice = (float) $event['merit_table_price'];

        // Enforced server-side for the same reason as RSVP: hiding the
        // form is presentation, not protection.
        $window = Event::windowStatus($event, Event::SECTION_DONATION);
        if (!$window['open']) {
            $this->flash(
                'error',
                $window['reason'] === 'not_yet' ? '布施尚未開放' : '布施已截止',
                Event::windowMessage($window, Event::SECTION_DONATION)
            );
            $this->redirect('/#donation');
        }

        $name    = trim((string) ($_POST['name'] ?? ''));
        $contact = trim((string) ($_POST['contact'] ?? ''));
        $method  = (string) ($_POST['method'] ?? '');

        if ($name === '' || mb_strlen($name) > 100) {
            $this->fail('請填寫有效的姓名。');
        }
        if (!$this->looksLikePhone($contact)) {
            $this->fail('請填寫有效的聯絡號碼。');
        }
        if (!in_array($method, ['free', 'table'], true)) {
            $this->fail('請選擇布施方式。');
        }

        $freeAmount = 0.0;
        $tableCount = null;

        if ($method === 'free') {
            $freeAmount = (float) ($_POST['free_amount'] ?? 0);
            if ($freeAmount <= 0) {
                $this->fail('請輸入有效的布施金額。');
            }
            if ($freeAmount > 1000000) {
                $this->fail('金額過大，請聯絡我們的工作人員協助處理。');
            }
        } else {
            $tableCount = (int) ($_POST['table_count'] ?? 0);
            if ($tableCount <= 0 || $tableCount > 200) {
                $this->fail('請輸入有效的功德席數量。');
            }
        }

        try {
            $result = (new Donation())->create(
                (int) $event['id'], $name, $contact, $method, $freeAmount, $tableCount, $seatPrice,
                Event::refPrefix($event, 'DON')   // TEST-DON-0001 on a dry run
            );
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                die('Donation save failed: ' . $e->getMessage());
            }
            $this->fail('提交失敗，請稍後再試。');
        }

        // Session, not URL — see the note in ConfirmController.
        $_SESSION['donation_confirmation'] = [
            'ref_code' => $result['ref_code'],
            'amount'   => $result['amount'],
            'name'     => $name,
            'method'   => $method,
            'seats'    => $tableCount,
        ];
        $this->redirect('/donation/success');
    }

    private function looksLikePhone(string $phone): bool
    {
        $digitsOnly = preg_replace('/\D/', '', $phone);
        $len = strlen($digitsOnly);
        return $len >= 9 && $len <= 15;
    }

    private function fail(string $message): void
    {
        $_SESSION['old_input'] = [
            'don_name'    => (string) ($_POST['name'] ?? ''),
            'don_contact' => (string) ($_POST['contact'] ?? ''),
            'don_method'  => (string) ($_POST['method'] ?? 'free'),
        ];
        $this->flash('error', '提交未完成', $message);
        $this->redirect('/#donation');
    }
}
