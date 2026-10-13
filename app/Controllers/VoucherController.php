<?php
namespace App\Controllers;

use App\Core\AmountWords;
use App\Core\Controller;
use App\Core\ImageUploader;
use App\Core\SheetReader;
use App\Core\XlsxWriter;
use App\Models\Setting;
use App\Models\Voucher;
use RuntimeException;

/**
 * VoucherController — 布施憑單 Donation vouchers (Finance).
 *
 * The web version of the committee's desktop "Bulk Voucher Generator":
 *   1. make a batch — date, description, amount, payment method;
 *   2. bring in the centre list — the same Excel file (No., Centre Name,
 *      Chinese name, Bank Account), a CSV, or rows pasted from Excel;
 *      one voucher is added per centre;
 *   3. print — every voucher, or the ticked ones, one per page. The
 *      browser's "Save as PDF" makes the combined PDF (see PrintController
 *      for why these are HTML pages, not generated PDFs).
 * Each voucher can be changed, given its own amount, marked paid, or
 * deleted; the batch downloads as an Excel register.
 */
class VoucherController extends Controller
{
    /** GET /admin/vouchers */
    public function index(): void
    {
        $this->requireAdmin();
        $this->view('admin/vouchers', [
            'pageTitle' => '布施憑單 Donation vouchers',
            'nav'       => 'vouchers',
            'batches'   => (new Voucher())->batches(),
            'site'      => (new Setting())->site(),
            'isSystem'  => $this->isSystemAdmin(),
            'flash'     => $this->takeFlash(),
        ]);
    }

    /** GET /admin/vouchers/batch?id= */
    public function batch(): void
    {
        $this->requireAdmin();
        $model = new Voucher();
        $batch = $model->batch((int) ($_GET['id'] ?? 0));
        if ($batch === null) {
            $this->redirect('/admin/vouchers');
        }
        $this->view('admin/voucher_batch', [
            'pageTitle' => $batch['title'] . ' · 布施憑單 Vouchers',
            'nav'       => 'vouchers',
            'batch'     => $batch,
            'vouchers'  => $model->vouchers((int) $batch['id']),
            'flash'     => $this->takeFlash(),
        ]);
    }

    /** POST /admin/vouchers/batch/save — new batch, or change one (id given). */
    public function saveBatch(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $model = new Voucher();
        $id    = (int) ($_POST['id'] ?? 0);
        if ($id && $model->batch($id) === null) {
            $this->redirect('/admin/vouchers');
        }

        $date = $this->str('voucher_date', 10);
        $amount = $this->money($_POST['amount'] ?? '');
        $v = [
            'title'        => $this->str('title', 120),
            'voucher_date' => $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) ? $date : null,
            'description'  => $this->str('description', 120) ?? 'CHARITY DONATION',
            'amount'       => $amount ?? 0,
            'amount_words' => $this->str('amount_words', 200),
            'method'       => $this->str('method', 60) ?? 'Bank Transfer',
            'paper'        => isset(Voucher::PAPERS[$_POST['paper'] ?? '']) ? $_POST['paper'] : 'a4',
            'notes'        => $this->str('notes', 500),
        ];
        $back = $id ? '/admin/vouchers/batch?id=' . $id : '/admin/vouchers';
        if ($v['title'] === null) {
            $this->flash('error', '未儲存 Not saved', '請輸入批次名稱。Please give the batch a name.');
            $this->redirect($back);
        }
        if (trim((string) ($_POST['amount'] ?? '')) !== '' && $amount === null) {
            $this->flash('error', '未儲存 Not saved', '金額不正確。The amount is not a valid number.');
            $this->redirect($back);
        }

        if ($id) {
            $model->updateBatch($id, $v);
            $this->flash('success', '已儲存 Saved', "批次資料已更新。\nThe batch details are saved.");
        } else {
            $id = $model->createBatch($v, (string) ($_SESSION['admin_username'] ?? 'admin'));
            $this->flash('success', '已建立 Created', "批次已建立，現在可以匯入中心名單。\nBatch created — now bring in the centre list.");
        }
        $this->redirect('/admin/vouchers/batch?id=' . $id);
    }

    /** POST /admin/vouchers/batch/delete */
    public function deleteBatch(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $model = new Voucher();
        $batch = $model->batch((int) ($_POST['id'] ?? 0));
        if ($batch) {
            $model->deleteBatch((int) $batch['id']);
            $this->flash('success', '已刪除 Deleted', '「' . $batch['title'] . "」及其憑單已刪除。\nThe batch and its vouchers were deleted.");
        }
        $this->redirect('/admin/vouchers');
    }

    /** POST /admin/vouchers/import — Excel / CSV file, or rows pasted from Excel. */
    public function import(): void
    {
        $this->requireAdmin();
        if (empty($_POST) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->flash('error', '檔案太大 File too large', '檔案超過伺服器限制。The file is over the server limit.');
            $this->redirect('/admin/vouchers');
        }
        $this->requireCsrf();
        $model = new Voucher();
        $batch = $model->batch((int) ($_POST['batch_id'] ?? 0));
        if ($batch === null) {
            $this->redirect('/admin/vouchers');
        }
        $back = '/admin/vouchers/batch?id=' . (int) $batch['id'];

        try {
            $file = $_FILES['sheet'] ?? null;
            if (is_array($file) && is_string($file['name'] ?? null) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
                    throw new RuntimeException('檔案上傳失敗，請再試一次。The upload failed — please try again.');
                }
                if ($file['size'] > 10 * 1024 * 1024) {
                    throw new RuntimeException('檔案超過 10 MB。The file is over 10 MB.');
                }
                $rows = SheetReader::file($file['tmp_name'], $file['name']);
            } else {
                $paste = is_string($_POST['paste'] ?? null) ? mb_substr($_POST['paste'], 0, 500000) : '';
                if (trim($paste) === '') {
                    throw new RuntimeException('請選擇 Excel 檔案，或貼上名單。Choose an Excel file or paste the list.');
                }
                $rows = SheetReader::text($paste);
            }
            [$list, $skipped] = Voucher::fromRows($rows);
            if (!$list) {
                throw new RuntimeException("找不到任何中心名稱。請確認第一個工作表有「No.」和「Centre Name」欄。\nNo centre names found. Check the first sheet has the No. and Centre Name columns.");
            }
            if (count($list) > 1000) {
                throw new RuntimeException('每次最多匯入 1000 個。At most 1,000 at a time.');
            }
        } catch (RuntimeException $e) {
            $this->flash('error', '未匯入 Not imported', $e->getMessage());
            $this->redirect($back);
        }

        $model->add((int) $batch['id'], $list);
        $noBank = count(array_filter($list, static fn($v) => $v['bank_account'] === null));
        $msg = '已加入 ' . count($list) . ' 張憑單。' . "\n" . 'Added ' . count($list) . ' voucher' . (count($list) === 1 ? '' : 's') . '.';
        if ($noBank) {
            $msg .= "\n\n" . $noBank . ' 張沒有銀行戶口（印出時留空）。' . "\n" . $noBank . ' without a bank account (left blank on the voucher).';
        }
        if ($skipped) {
            $msg .= "\n\n" . $skipped . ' 行沒有名稱，已略過。' . "\n" . $skipped . ' row(s) without a name were skipped.';
        }
        $this->flash('success', '已匯入 Imported', $msg);
        $this->redirect($back);
    }

    /** GET /admin/vouchers/template — an empty centre list to fill in. */
    public function template(): void
    {
        $this->requireAdmin();
        $x = new XlsxWriter();
        $x->creator = (string) ((new \App\Models\Setting())->site()['site_name'] ?? '');
        $s = $x->addSheet('Centres 中心', ['widths' => [8, 40, 26, 30, 14], 'freeze' => 1]);
        $x->row($s, array_map(static fn($h) => [$h, 'header'],
            ['No.', 'Centre Name', 'Chinese Centre Name', 'Bank Account', 'Amount (optional)']), 26);
        $x->row($s, ['1', 'Pusat Jagaan Contoh', '示範安老院', ['Maybank 5123 4567 8901', 'textfmt'], '']);
        $x->send('voucher-centre-list.xlsx');
    }

    /** GET /admin/vouchers/edit?id= */
    public function edit(): void
    {
        $this->requireAdmin();
        $model = new Voucher();
        $v = $model->find((int) ($_GET['id'] ?? 0));
        $batch = $v ? $model->batch((int) $v['batch_id']) : null;
        if ($batch === null) {
            $this->redirect('/admin/vouchers');
        }
        $this->view('admin/voucher_form', [
            'pageTitle' => '修改憑單 Edit voucher · ' . Voucher::number($batch, $v),
            'nav'       => 'vouchers',
            'batch'     => $batch,
            'voucher'   => $v,
            'flash'     => $this->takeFlash(),
        ]);
    }

    /** POST /admin/vouchers/save — add one voucher (batch_id) or change one (id). */
    public function save(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $model = new Voucher();
        $id    = (int) ($_POST['id'] ?? 0);
        $row   = $id ? $model->find($id) : null;
        $batch = $model->batch($row ? (int) $row['batch_id'] : (int) ($_POST['batch_id'] ?? 0));
        if ($batch === null || ($id && $row === null)) {
            $this->redirect('/admin/vouchers');
        }
        $back = '/admin/vouchers/batch?id=' . (int) $batch['id'];

        $amountRaw = trim((string) (is_string($_POST['amount'] ?? null) ? $_POST['amount'] : ''));
        $v = [
            'list_no'      => $this->str('list_no', 20),
            'name_en'      => $this->str('name_en', 200),
            'name_zh'      => $this->str('name_zh', 120),
            'bank_account' => $this->str('bank_account', 160),
            'amount'       => $amountRaw === '' ? null : $this->money($amountRaw),
        ];
        if ($v['name_en'] === null && $v['name_zh'] === null) {
            $this->flash('error', '未儲存 Not saved', '請輸入受款中心的名稱。Please enter the centre\'s name.');
            $this->redirect($id ? '/admin/vouchers/edit?id=' . $id : $back);
        }
        if ($amountRaw !== '' && $v['amount'] === null) {
            $this->flash('error', '未儲存 Not saved', '金額不正確。The amount is not a valid number.');
            $this->redirect($id ? '/admin/vouchers/edit?id=' . $id : $back);
        }
        // Same as the batch: keep following the batch if its amount changes later.
        if ($v['amount'] !== null && (float) $v['amount'] === (float) $batch['amount']) {
            $v['amount'] = null;
        }

        if ($row) {
            $model->update($id, $v);
            $this->flash('success', '已儲存 Saved', Voucher::number($batch, $row) . " 已更新。\nThe voucher is saved.");
            $this->redirect($back . '#v' . $id);
        }
        $model->add((int) $batch['id'], [$v]);
        $this->flash('success', '已加入 Added', "憑單已加入。\nThe voucher was added.");
        $this->redirect($back . '#end');
    }

    /** POST /admin/vouchers/delete */
    public function delete(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $model = new Voucher();
        $v = $model->find((int) ($_POST['id'] ?? 0));
        if ($v === null) {
            $this->redirect('/admin/vouchers');
        }
        $model->delete((int) $v['id']);
        $this->flash('success', '已刪除 Deleted', "憑單已刪除，號碼不會再用。\nThe voucher was deleted; its number will not be reused.");
        $this->redirect('/admin/vouchers/batch?id=' . (int) $v['batch_id']);
    }

    /** POST /admin/vouchers/paid — mark paid / not paid (JSON for the list's tick box). */
    public function paid(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $model = new Voucher();
        $v = $model->find((int) ($_POST['id'] ?? 0));
        if ($v === null) {
            $this->wantsJson() ? $this->json(['error' => 'Not found'], 404) : $this->redirect('/admin/vouchers');
        }
        $paid = ($_POST['paid'] ?? '') === '1';
        $model->setPaid((int) $v['id'], $paid);
        if ($this->wantsJson()) {
            $this->json(['ok' => true, 'paid' => $paid]);
        }
        $this->redirect('/admin/vouchers/batch?id=' . (int) $v['batch_id'] . '#v' . (int) $v['id']);
    }

    /** GET /admin/vouchers/print?batch=&ids=1,2,3 — every voucher, or the ticked ones. */
    public function print(): void
    {
        $this->requireAdmin();
        $model = new Voucher();
        $batch = $model->batch((int) ($_GET['batch'] ?? 0));
        if ($batch === null) {
            $this->redirect('/admin/vouchers');
        }
        $ids = $_GET['ids'] ?? null;
        $ids = is_array($ids) ? $ids : (is_string($ids) && $ids !== '' ? explode(',', $ids) : null);
        $this->view('print/vouchers', [
            'batch'    => $batch,
            'vouchers' => $model->vouchers((int) $batch['id'], $ids !== null ? array_slice($ids, 0, 2000) : null),
            'paper'    => isset(Voucher::PAPERS[$_GET['paper'] ?? '']) ? $_GET['paper'] : (isset(Voucher::PAPERS[$batch['paper']]) ? $batch['paper'] : 'a4'),
        ]);
    }

    /** GET /admin/vouchers/excel?batch= — the batch as a spreadsheet register. */
    public function excel(): void
    {
        $this->requireAdmin();
        $model = new Voucher();
        $batch = $model->batch((int) ($_GET['batch'] ?? 0));
        if ($batch === null) {
            $this->redirect('/admin/vouchers');
        }
        $x = new XlsxWriter();
        $x->creator = (string) ((new \App\Models\Setting())->site()['site_name'] ?? '');
        $s = $x->addSheet('Vouchers 憑單', ['widths' => [13, 8, 40, 24, 28, 14, 42, 16, 16], 'freeze' => 1, 'filter' => true, 'zebra' => true]);
        $x->row($s, array_map(static fn($h) => [$h, 'header'], [
            'Voucher No. 憑單號', 'No.', 'Centre Name 中心名稱', 'Chinese Name 中文名稱', 'Bank Account 銀行戶口',
            'Amount 金額 (RM)', 'Amount in words 大寫', 'Method 付款方式', 'Paid 已付']), 30);
        $total = 0.0;
        foreach ($model->vouchers((int) $batch['id']) as $v) {
            $amt = Voucher::amount($batch, $v);
            $total += $amt;
            $x->row($s, [
                Voucher::number($batch, $v),
                [$v['list_no'] ?? '', 'textfmt'],
                $v['name_en'] ?? '',
                $v['name_zh'] ?? '',
                [$v['bank_account'] ?? '', 'textfmt'],
                [$amt, 'money'],
                Voucher::wordsEn($batch, $v),
                $batch['method'],
                $v['paid_at'] ? [XlsxWriter::date($v['paid_at']), 'datetime'] : '',
            ], 20);
        }
        $x->row($s, ['', '', '', '', ['Total 總計', 'header'], [$total, 'money'], AmountWords::en($total), '', ''], 22);
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', (string) $batch['title']);
        $x->send('vouchers-' . (int) $batch['id'] . (trim($slug, '-') !== '' ? '-' . trim($slug, '-') : '') . '.xlsx');
    }

    /**
     * POST /admin/vouchers/layout — the approver's signature printed on every
     * voucher. Everything else on the page is the committee's sample
     * (public/assets/img/voucher-template.png); the signature is the one
     * part kept out of the code, in storage/, because the repository is
     * public. System admin only.
     */
    public function layout(): void
    {
        $this->requireSystemAdmin();
        if (empty($_POST) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->flash('error', '圖片太大 Picture too large', '圖片超過伺服器限制。The picture is over the server limit.');
            $this->redirect('/admin/vouchers#layout');
        }
        $this->requireCsrf();
        $settings = new Setting();
        $uploader = new ImageUploader('vouchers', true);
        $old = (string) ($settings->site()['voucher_signature'] ?? '');
        if (ImageUploader::wasProvided($_FILES['signature'] ?? null)) {
            try {
                // PNG keeps a transparent background, so the signature sits on the line like ink.
                $new = $uploader->store($_FILES['signature'], 1200);
            } catch (RuntimeException $e) {
                $this->flash('error', '簽名圖片 Signature', $e->getMessage());
                $this->redirect('/admin/vouchers#layout');
            }
            $settings->set('voucher_signature', $new);
            if ($old !== '') {
                $uploader->delete($old);
            }
        } elseif (!empty($_POST['remove_signature']) && $old !== '') {
            $settings->set('voucher_signature', null);
            $uploader->delete($old);
        }
        $this->flash('success', '已儲存 Saved', "批准人簽名已更新。\nThe approver's signature is saved.");
        $this->redirect('/admin/vouchers#layout');
    }

    /** GET /admin/vouchers/signature — the approver's signature, only to signed-in voucher staff. */
    public function signature(): void
    {
        $this->requireAdmin();
        $path = (new Setting())->site()['voucher_signature'] ?? null;
        $file = (new ImageUploader('vouchers', true))->absolutePath($path);
        $info = $file !== null ? @getimagesize($file) : false;
        if ($info === false) {
            http_response_code(404);
            exit;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . $info['mime']);
        header('Content-Length: ' . filesize($file));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($file);
        exit;
    }

    // ------------------------------------------------------------------

    /** A trimmed, length-limited text field, or null when empty. */
    private function str(string $key, int $max): ?string
    {
        $v = is_string($_POST[$key] ?? null) ? trim(preg_replace('/\s+/u', ' ', $_POST[$key])) : '';
        $v = mb_substr($v, 0, $max);
        return $v !== '' ? $v : null;
    }

    /** "2,000" / "RM 2000.50" → 2000.50, or null when it is not an amount. */
    private function money($raw): ?float
    {
        $s = str_replace([',', ' ', 'RM', 'rm'], '', is_string($raw) ? trim($raw) : '');
        if ($s === '' || !preg_match('/^\d{1,10}(\.\d{1,2})?$/', $s)) {
            return null;
        }
        return round((float) $s, 2);
    }
}
