<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\ImageUploader;
use App\Models\Donation;
use App\Models\Event;
use RuntimeException;

/**
 * CounterController — recording cash taken at the counter.
 *
 * The public form handles people who pledge online. This handles the
 * other half: somebody walks up on the day, hands over cash, and a
 * volunteer writes a paper receipt. Without this, none of that money
 * appears in the system and the treasurer's totals are simply wrong.
 *
 * Differences from a public submission, all deliberate:
 *   - marked PAID on save, because the money is already in hand
 *   - stamped with the admin who entered it (cash needs an owner)
 *   - the paper receipt can be photographed and attached, so a
 *     disputed amount can be checked against the original
 *   - submission windows do NOT apply; the counter runs on the day,
 *     often after online registration has closed
 */
class CounterController extends Controller
{
    /** GET /admin/counter?event=<id> */
    public function index(): void
    {
        $this->requireAdmin();

        $eventModel = new Event();
        $requested  = (int) ($_GET['event'] ?? 0);
        $event      = $requested > 0 ? $eventModel->find($requested) : null;
        $event      = $event ?? $eventModel->active();
        $eventId    = (int) $event['id'];

        $donation = new Donation();

        $this->view('admin/counter', [
            'event'     => $event,
            'allEvents' => $eventModel->all(),
            'totals'    => $donation->totalsBySource($eventId),
            'recent'    => $this->recentCounterDonations($donation, $eventId),
            'flash'     => $this->takeFlash(),
            'old'       => $this->takeOld(),
        ]);
    }

    /** POST /admin/counter/save */
    public function save(): void
    {
        $this->requireAdmin();

        // Same post_max_size trap as the photo gallery: an oversized
        // receipt photo empties $_POST, so the CSRF check would report
        // "form expired" and send the volunteer hunting the wrong bug.
        if (empty($_POST) && empty($_FILES) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->flash('error', '相片太大',
                '收據相片超過伺服器限制（' . ini_get('post_max_size') . '）。請重拍或先壓縮。');
            $this->redirect('/admin/counter');
        }

        $this->requireCsrf();

        $eventModel = new Event();
        $eventId    = (int) ($_POST['event_id'] ?? 0);
        $event      = $eventModel->find($eventId);

        if ($event === null) {
            $this->flash('error', '找不到活動', '請先選擇一個活動。');
            $this->redirect('/admin/counter');
        }

        $name    = trim((string) ($_POST['name'] ?? ''));
        $contact = trim((string) ($_POST['contact'] ?? ''));
        $method  = (string) ($_POST['method'] ?? '');
        $notes   = trim((string) ($_POST['notes'] ?? ''));

        $errors = [];

        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = '請填寫布施者姓名。';
        }
        // Contact is optional here — a walk-in may not want to give one,
        // and refusing the donation over a phone number would be absurd.
        if ($contact !== '' && mb_strlen($contact) > 30) {
            $errors[] = '聯絡號碼過長。';
        }
        if (!in_array($method, ['free', 'table'], true)) {
            $errors[] = '請選擇布施方式。';
        }

        $freeAmount = 0.0;
        $tableCount = null;
        $seatPrice  = (float) $event['merit_table_price'];

        if ($method === 'free') {
            $freeAmount = (float) ($_POST['free_amount'] ?? 0);
            if ($freeAmount <= 0 || $freeAmount > 1000000) {
                $errors[] = '請輸入有效的布施金額。';
            }
        } elseif ($method === 'table') {
            $tableCount = (int) ($_POST['table_count'] ?? 0);
            if ($tableCount <= 0 || $tableCount > 200) {
                $errors[] = '請輸入有效的功德席數量。';
            }
        }

        // Receipt photo is optional but strongly encouraged.
        $receiptPath = null;
        if (ImageUploader::wasProvided($_FILES['receipt'] ?? null)) {
            try {
                $receiptPath = (new ImageUploader('receipts'))->store($_FILES['receipt'], 1600);
            } catch (RuntimeException $e) {
                $errors[] = '收據相片：' . $e->getMessage();
            }
        }

        if ($errors) {
            $_SESSION['counter_errors'] = $errors;
            $_SESSION['counter_old']    = [
                'name' => $name, 'contact' => $contact, 'method' => $method,
                'free_amount' => $_POST['free_amount'] ?? '',
                'table_count' => $_POST['table_count'] ?? '',
                'notes' => $notes,
            ];
            $this->redirect("/admin/counter?event={$eventId}");
        }

        $result = (new Donation())->createAtCounter(
            $eventId,
            $name,
            $contact !== '' ? $contact : '—',
            $method,
            $freeAmount,
            $tableCount,
            $seatPrice,
            (string) ($_SESSION['admin_username'] ?? 'admin'),
            $receiptPath,
            $notes !== '' ? mb_substr($notes, 0, 255) : null,
            Event::refPrefix($event, 'DON')
        );

        $this->flash(
            'success',
            '已記錄',
            "{$result['ref_code']}　{$name}　" . rm($result['amount'])
            . ($receiptPath ? '　（收據已附）' : '')
        );
        $this->redirect("/admin/counter?event={$eventId}");
    }

    private function recentCounterDonations(Donation $model, int $eventId): array
    {
        $all = $model->all($eventId, 200);
        $counter = array_filter($all, static fn($d) => ($d['source'] ?? 'online') === 'counter');
        return array_slice(array_values($counter), 0, 12);
    }

    /** @return string[] */
    private function takeOld(): array
    {
        $old = $_SESSION['counter_old'] ?? [];
        $errors = $_SESSION['counter_errors'] ?? [];
        unset($_SESSION['counter_old'], $_SESSION['counter_errors']);
        return ['values' => $old, 'errors' => $errors];
    }
}
