<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\ImageUploader;
use App\Core\ReceiptReader;
use App\Core\XlsxWriter;
use App\Models\Receipt;
use RuntimeException;

/**
 * ReceiptController — 收據 Receipts: the temple's paper receipt book,
 * photographed and read by AI (or typed in), then kept, searched,
 * edited and exported.
 *
 * The flow is scan → review → save. The AI's reading is only ever a
 * draft shown beside the photo; nothing is stored until a person has
 * looked at it and pressed Save. Photos are financial records, so they
 * live in storage/receipts (outside the web root) and are shown only
 * through image() below, to signed-in staff.
 */
class ReceiptController extends Controller
{
    private const PER_PAGE = 30;

    /** GET /admin/receipts?q=&book=&no_from=&no_to=&check=&from=&to=&payment=&sort=&dir=&page= */
    public function index(): void
    {
        $this->requireReceipts();
        $f = $this->filters();
        $model = new Receipt();
        $sum   = $model->summary($f);
        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $pages = max(1, (int) ceil($sum['n'] / self::PER_PAGE));

        $book = $f['book'] === '' ? false : ($f['book'] === '-' ? null : $f['book']);

        $this->view('admin/receipts', [
            'pageTitle'  => '收據紀錄 Receipts',
            'nav'        => 'receipts',
            'filters'    => $f,
            'books'      => $model->books($f),
            'holders'    => $model->holders(),
            'bookInfo'   => is_string($book) ? ($model->book($book) ?? ['book_no' => $book, 'holder' => null, 'holder_phone' => null]) : null,
            'gaps'       => $book !== false ? $model->bookGaps($book) : null,
            'unread'     => $book !== false ? count($model->unread($book)) : 0,
            'rows'       => $model->search($f, self::PER_PAGE, (min($page, $pages) - 1) * self::PER_PAGE),
            'sum'        => $sum,
            'page'       => $page,
            'pages'      => $pages,
            'aiReady'    => ReceiptReader::configured(),
            'flash'      => $this->takeFlash(),
        ]);
    }

    /** GET /admin/receipts/new — step 1: photo (or type it in) */
    public function create(): void
    {
        $this->requireReceipts();
        $this->view('admin/receipt_scan', [
            'pageTitle' => '新增收據 Add Receipt',
            'nav'       => 'receipts',
            'aiReady'   => ReceiptReader::configured(),
            'book'      => (string) ($_SESSION['receipt_last_book'] ?? ''),
            'bookList'  => (new Receipt())->bookNumbers(),
            'register'  => (new Receipt())->bookRegister(),
            'flash'     => $this->takeFlash(),
        ]);
    }

    /**
     * POST /admin/receipts/scan — keep the photo, ask the AI to read it,
     * then open the review form with the photo beside the draft.
     */
    public function scan(): void
    {
        $this->requireReceipts();
        if (empty($_POST) && empty($_FILES) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->flash('error', '相片太大 Photo too large',
                '相片超過伺服器限制（' . ini_get('post_max_size') . '）。The photo is over the server limit.');
            $this->redirect('/admin/receipts/new');
        }
        $this->requireCsrf();
        $this->discardDraftPhoto();

        $book  = $this->bookFrom($_POST['book_no'] ?? '');
        $_SESSION['receipt_last_book'] = $book ?? '';
        // Who holds this book — saved with the book (a blank box keeps the saved name).
        if ($book !== null) {
            (new Receipt())->saveBook($book, $this->holderFrom($_POST['holder'] ?? ''), $this->phoneFrom($_POST['holder_phone'] ?? ''),
                (string) ($_SESSION['admin_username'] ?? 'admin'));
        }
        $draft = ['source' => 'manual', 'ai_notes' => null, 'image_path' => null, 'values' => ['book_no' => $book ?? '']];

        if (ImageUploader::wasProvided($_FILES['photo'] ?? null)) {
            $uploader = new ImageUploader('receipts', true);
            try {
                // 2000px keeps small handwriting legible for the AI and for people.
                $draft['image_path'] = $uploader->store($_FILES['photo'], 2000);
            } catch (RuntimeException $e) {
                $this->flash('error', '相片 Photo', $e->getMessage());
                $this->redirect('/admin/receipts/new');
            }

            if (!empty($_POST['use_ai'])) {
                try {
                    $read = (new ReceiptReader())->read($uploader->absolutePath($draft['image_path']));
                    $draft['source'] = 'ai';
                    $draft['values'] = $this->fromReading($read) + $draft['values'];
                    $draft['ai_notes'] = $this->aiNotes($read);
                    $draft['unsure'] = $read['unsure'];
                    $draft['ocr_text'] = $read['text'] ?? null;   // Google Vision: everything it read
                } catch (RuntimeException $e) {
                    // The photo is still useful — type it in beside it.
                    $this->flash('error', 'AI 讀取失敗 AI could not read it', $e->getMessage());
                }
            }
        }

        $_SESSION['receipt_draft'] = $draft;
        $this->redirect('/admin/receipts/review');
    }

    /** GET /admin/receipts/review — step 2: check the draft against the photo */
    public function review(): void
    {
        $this->requireReceipts();
        $draft = $_SESSION['receipt_draft'] ?? null;
        if ($draft === null) {
            $this->redirect('/admin/receipts/new');
        }
        $this->form(null, $draft['values'] + $this->blank(), $draft);
    }

    /** GET /admin/receipts/edit?id= */
    public function edit(): void
    {
        $this->requireReceipts();
        $row = (new Receipt())->find((int) ($_GET['id'] ?? 0));
        if ($row === null) {
            $this->flash('error', '找不到 Not found', '找不到這張收據。This receipt does not exist.');
            $this->redirect('/admin/receipts');
        }
        $this->form($row, $row, null);
    }

    private function form(?array $row, array $values, ?array $draft): void
    {
        $old = $_SESSION['receipt_old'] ?? null;
        unset($_SESSION['receipt_old']);
        $values = $old ?? $values;
        $this->view('admin/receipt_form', [
            'pageTitle' => $row ? '收據 Receipt ' . ($row['receipt_no'] ? 'No. ' . $row['receipt_no'] : '#' . $row['id']) : '核對收據 Check Receipt',
            'nav'       => 'receipts',
            'row'       => $row,
            'v'         => $values,
            'draft'     => $draft,
            'duplicate' => (new Receipt())->findByNumber((string) $values['receipt_no'], (int) ($row['id'] ?? 0)),
            'bookList'  => (new Receipt())->bookNumbers(),
            'register'  => (new Receipt())->bookRegister(),
            'flash'     => $this->takeFlash(),
        ]);
    }

    /** POST /admin/receipts/save — create (from the draft) or update */
    public function save(): void
    {
        $this->requireReceipts();
        $this->requireCsrf();
        $model = new Receipt();
        $id    = (int) ($_POST['id'] ?? 0);
        $row   = $id ? $model->find($id) : null;
        if ($id && $row === null) {
            $this->redirect('/admin/receipts');
        }
        $draft = $row ? null : ($_SESSION['receipt_draft'] ?? ['source' => 'manual', 'ai_notes' => null, 'image_path' => null]);

        [$v, $sumBoxes] = $this->clean($_POST);

        $errors = [];
        if ($v['name'] === null && $v['receipt_no'] === null && $v['total'] == 0) {
            $errors[] = '請至少填寫收據號碼、姓名或金額。Please fill in at least the number, a name or an amount.';
        }
        if ($errors) {
            $_SESSION['receipt_old'] = $v + ['receipt_no' => '', 'book_no' => '', 'receipt_date' => ''];
            $this->flash('error', '未儲存 Not saved', implode("\n", $errors));
            $this->redirect($row ? '/admin/receipts/edit?id=' . $id : '/admin/receipts/review');
        }

        $by = (string) ($_SESSION['admin_username'] ?? 'admin');
        if ($row === null) {
            $v += ['image_path' => $draft['image_path'] ?? null, 'source' => $draft['source'] ?? 'manual',
                   'ai_notes' => $draft['ai_notes'] ?? null];
        }
        $newId = $model->save($row ? $id : null, $v, $by);
        if ($row === null) {
            unset($_SESSION['receipt_draft']);   // the photo now belongs to the saved receipt
        }
        $_SESSION['receipt_last_book'] = $v['book_no'] ?? '';

        // Editing: an optional new photo replaces the old one.
        if ($row !== null && ImageUploader::wasProvided($_FILES['photo'] ?? null)) {
            $uploader = new ImageUploader('receipts', true);
            try {
                $newPath = $uploader->store($_FILES['photo'], 2000);
                $model->setImage($newId, $newPath);
                $uploader->delete($row['image_path']);
            } catch (RuntimeException $e) {
                $this->flash('error', '相片未更換 Photo not replaced', $e->getMessage() . "
（其他內容已儲存。Everything else was saved.）");
                $this->redirect('/admin/receipts/edit?id=' . $newId);
            }
        }

        // Bank-In: the bank's transaction receipt (a photo or screenshot).
        $slipMsg = $this->saveBankSlip($newId, $row['bank_slip_path'] ?? null);

        $warn = abs($sumBoxes - $v['total']) > 0.009 && $sumBoxes > 0
            ? "\n各項合計 " . rm($sumBoxes) . ' ≠ 總數 ' . rm($v['total']) . '。Boxes add up to ' . rm($sumBoxes) . ', not the total.'
            : '';
        $this->flash('success', '已儲存 Saved',
            '收據 ' . ($v['book_no'] ? '簿 Book ' . $v['book_no'] . ' · ' : '') . ($v['receipt_no'] ? 'No. ' . $v['receipt_no'] : '#' . $newId)
            . ' · ' . ($v['name'] ?? '—') . ' · ' . rm($v['total']) . $warn . $slipMsg);

        // Checking a bulk-uploaded book: straight on to the next one waiting.
        if (($_POST['next'] ?? '') === 'check') {
            $next = $model->nextToCheck($v['book_no'], $newId);
            if ($next) {
                $this->redirect('/admin/receipts/edit?id=' . (int) $next['id']);
            }
            $this->flash('success', '全部核對完成 All checked',
                "已儲存，沒有其他待核對的收據。\nSaved — there are no more receipts waiting to be checked.");
            $this->redirect('/admin/receipts' . ($v['book_no'] ? '?book=' . rawurlencode($v['book_no']) : ''));
        }
        $this->redirect(!empty($_POST['next']) ? '/admin/receipts/new' : '/admin/receipts/edit?id=' . $newId);
    }

    /**
     * GET /admin/receipts/bulk?book= — a whole receipt book at once: choose
     * every photo, they go up one by one (bulk-upload.js), and the AI reads
     * each in turn. Every receipt is then checked against its photo
     * (“待核對 To check”) before it counts as checked.
     */
    public function bulk(): void
    {
        $this->requireReceipts();
        $model = new Receipt();
        $book  = $this->bookFrom($_GET['book'] ?? ($_SESSION['receipt_last_book'] ?? ''));
        $this->view('admin/receipt_bulk', [
            'pageTitle' => '整本上傳 Upload a Book',
            'nav'       => 'receipts',
            'aiReady'   => ReceiptReader::configured(),
            'book'      => $book ?? '',
            'bookList'  => $model->bookNumbers(),
            'register'  => $model->bookRegister(),
            'unread'    => $book !== null ? $model->unread($book) : [],
            'resume'    => !empty($_GET['resume']),
            'flash'     => $this->takeFlash(),
        ]);
    }

    /** POST /admin/receipts/bulk-upload — one photo of the book → a receipt waiting to be checked (JSON). */
    public function bulkUpload(): void
    {
        $this->requireReceipts();
        if (empty($_POST) && empty($_FILES) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->json(['ok' => false, 'error' => '相片超過伺服器限制（' . ini_get('post_max_size') . '）。Over the server limit.'], 413);
        }
        $this->requireCsrf();
        $by = (string) ($_SESSION['admin_username'] ?? 'admin');
        session_write_close();   // the next photos need not wait for this one

        $book = $this->bookFrom($_POST['book_no'] ?? '');
        if ($book === null) {
            $this->json(['ok' => false, 'error' => '請先填寫簿號。Please enter the book number.'], 422);
        }
        if (!ImageUploader::wasProvided($_FILES['photo'] ?? null)) {
            $this->json(['ok' => false, 'error' => '未收到相片 No photo received'], 422);
        }
        $uploader = new ImageUploader('receipts', true);
        try {
            $path = $uploader->store($_FILES['photo'], 2000);
        } catch (RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        $model = new Receipt();
        // Who has this book: kept with the book, not each receipt. A blank
        // box leaves the name already saved alone.
        $model->saveBook($book, $this->holderFrom($_POST['holder'] ?? ''), $this->phoneFrom($_POST['holder_phone'] ?? ''), $by);
        $id = $model->createDraft($book, $path, $by);
        $this->json(['ok' => true, 'id' => $id, 'edit' => url('/admin/receipts/edit') . '?id=' . $id]);
    }

    /** POST /admin/receipts/ai-read — let the AI read one receipt still waiting to be checked (JSON). */
    public function aiRead(): void
    {
        $this->requireReceipts();
        $this->requireCsrf();
        session_write_close();   // reading takes 10–40 s; other requests carry on meanwhile

        $model = new Receipt();
        $row   = $model->find((int) ($_POST['id'] ?? 0));
        if ($row === null || (int) $row['needs_check'] !== 1 || !$row['image_path']) {
            $this->json(['ok' => false, 'error' => '這張收據已核對或沒有相片。Already checked, or no photo.'], 409);
        }
        if (!ReceiptReader::configured()) {
            $this->json(['ok' => false, 'error' => 'AI 讀取尚未啟用 AI reading is off'], 503);
        }
        try {
            $read = (new ReceiptReader())->read((new ImageUploader('receipts', true))->absolutePath($row['image_path']));
        } catch (RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
        [$v] = $this->clean($this->fromReading($read));
        $model->fillFromAi((int) $row['id'], $v, $this->aiNotes($read));
        $this->json(['ok' => true, 'receipt_no' => $v['receipt_no'], 'name' => $v['name'],
                     'total' => rm($v['total']), 'unsure' => (bool) $read['unsure']]);
    }

    /**
     * POST /admin/receipts/book — register a receipt book or change who is
     * in charge of it (負責人). A book can be handed out before any of
     * its receipts are entered.
     */
    public function saveBook(): void
    {
        $this->requireReceipts();
        $this->requireCsrf();
        $book = $this->bookFrom($_POST['book_no'] ?? '');
        if ($book === null) {
            $this->flash('error', '未儲存 Not saved', '請填寫簿號。Please enter the book number.');
            $this->redirect('/admin/receipts');
        }
        $holder = $this->holderFrom($_POST['holder'] ?? '');
        $phone  = $this->phoneFrom($_POST['holder_phone'] ?? '');
        (new Receipt())->saveBook($book, $holder, $phone, (string) ($_SESSION['admin_username'] ?? 'admin'), true);
        $this->flash('success', '已儲存 Saved', "簿 Book {$book} · 負責人 In charge: " . ($holder ?? '—') . ($phone ? " · {$phone}" : ''));
        $this->redirect('/admin/receipts?' . http_build_query(['book' => $book, 'sort' => 'no', 'dir' => 'asc']));
    }

    /** POST /admin/receipts/delete */
    public function delete(): void
    {
        $this->requireReceipts();
        $this->requireCsrf();
        $model = new Receipt();
        $row   = $model->find((int) ($_POST['id'] ?? 0));
        if ($row) {
            $model->delete((int) $row['id']);
            (new ImageUploader('receipts', true))->delete($row['image_path']);
            (new ImageUploader('receipts/bank', true))->delete($row['bank_slip_path'] ?? null);
            $this->flash('success', '已刪除 Deleted',
                '收據 ' . ($row['receipt_no'] ? 'No. ' . $row['receipt_no'] : '#' . $row['id']) . ' 已刪除。Receipt deleted.');
        }
        $this->redirect('/admin/receipts');
    }

    /** POST /admin/receipts/cancel — leave the review without saving */
    public function cancel(): void
    {
        $this->requireReceipts();
        $this->requireCsrf();
        $this->discardDraftPhoto();
        unset($_SESSION['receipt_draft']);
        $this->redirect('/admin/receipts');
    }

    /**
     * GET /admin/receipts/image?id=<id>  or  ?draft=1  or  ?id=<id>&slip=1
     * The only way to see a receipt photo or bank slip: signed-in staff,
     * looked up by id (or the caller's own unsaved draft) — never by a
     * path from the browser.
     */
    public function image(): void
    {
        $this->requireReceipts();
        $slip = !empty($_GET['slip']);
        $row  = empty($_GET['draft']) ? (new Receipt())->find((int) ($_GET['id'] ?? 0)) : null;
        $path = !empty($_GET['draft'])
            ? ($_SESSION['receipt_draft']['image_path'] ?? null)
            : ($row[$slip ? 'bank_slip_path' : 'image_path'] ?? null);
        $file = (new ImageUploader($slip ? 'receipts/bank' : 'receipts', true))->absolutePath($path);
        $info = $file !== null ? @getimagesize($file) : false;
        if ($info === false) {
            http_response_code(404);
            require BASE_PATH . '/app/Views/errors/404.php';
            return;
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

    /** GET /admin/receipts/excel — the filtered list as a spreadsheet */
    public function excel(): void
    {
        $this->requireReceipts();
        $f    = $this->filters();
        $rows = (new Receipt())->search($f, 100000);
        $cats = ReceiptReader::CATEGORIES;

        $x = new XlsxWriter();
        $x->creator = (new \App\Models\Setting())->site()['site_name'];
        $s = $x->addSheet('Receipts 收據', [
            'widths' => array_merge([10, 18, 12, 12, 22, 24], array_fill(0, count($cats), 12), [14, 12, 12, 16, 30, 12]),
            'freeze' => 1, 'filter' => true, 'zebra' => true,
        ]);
        $holders = (new Receipt())->bookRegister();
        $head = ['Book 簿號', 'In charge 負責人', 'No. 號碼', 'Date 日期', 'Name 姓名', 'Item 項目'];
        foreach ($cats as [$zh, $en]) {
            $head[] = $zh . ' ' . $en;
        }
        array_push($head, 'Total 總數', 'Paid by 方式', 'Bank slip 轉帳單據', 'Issued by 發據人', 'Notes 備註', 'Checked 已核對');
        $x->row($s, array_map(static fn($h) => [$h, 'header'], $head), 34);
        foreach ($rows as $r) {
            $cells = [
                [$r['book_no'] ?? '', 'textfmt'],
                $r['book_no'] !== null ? (string) ($holders[$r['book_no']]['holder'] ?? '') : '',
                [$r['receipt_no'] ?? '', 'textfmt'],
                $r['receipt_date'] ? [XlsxWriter::date($r['receipt_date']), 'datetime'] : '',
                $r['name'] ?? '',
                $r['item'] ?? '',
            ];
            foreach (array_keys($cats) as $k) {
                $cells[] = [(float) $r['amt_' . $k], 'money'];
            }
            array_push($cells,
                [(float) $r['total'], 'money'],
                ['cash' => 'Cash 現金', 'bank' => 'Bank-in 轉帳'][$r['payment']] ?? '',
                !empty($r['bank_slip_path']) ? 'Yes 有' : ($r['payment'] === 'bank' ? 'No 沒有' : ''),
                $r['issued_by'] ?? '',
                trim(($r['other_label'] ? '其他 Other: ' . $r['other_label'] . ' · ' : '') . ($r['notes'] ?? ''), ' ·'),
                (int) $r['needs_check'] ? 'No 待核對' : 'Yes 是');
            $x->row($s, $cells, 20);
        }
        $x->send('receipts-' . date('Ymd') . '.xlsx');
    }

    // ------------------------------------------------------------------

    private function filters(): array
    {
        $g = static fn(string $k): string => is_string($_GET[$k] ?? null) ? trim($_GET[$k]) : '';
        $digits = static fn(string $k): string => ctype_digit($g($k)) ? substr($g($k), 0, 12) : '';
        return [
            'q'       => mb_substr($g('q'), 0, 100),
            'book'    => $g('book') === '-' ? '-' : ($this->bookFrom($g('book')) ?? ''),
            'holder'  => mb_substr($g('holder'), 0, 100),
            'no_from' => $digits('no_from'),
            'no_to'   => $digits('no_to'),
            'check'   => in_array($g('check'), ['0', '1'], true) ? $g('check') : '',
            'from'    => $g('from'),
            'to'      => $g('to'),
            'payment' => $g('payment'),
            'sort'    => isset(Receipt::SORTS[$g('sort')]) ? $g('sort') : 'added',
            'dir'     => $g('dir') === 'asc' ? 'asc' : 'desc',
        ];
    }

    private function blank(): array
    {
        $v = ['receipt_no' => '', 'book_no' => (string) ($_SESSION['receipt_last_book'] ?? ''), 'receipt_date' => '', 'item' => '', 'name' => '', 'other_label' => '',
              'total' => '', 'payment' => '', 'issued_by' => '', 'notes' => ''];
        foreach (Receipt::amountColumns() as $c) {
            $v[$c] = '';
        }
        return $v;
    }

    /** The AI's reading, as form values. */
    /** ['name', 'issued_by'] → "姓名 Name、發據人 Issued by" */
    /**
     * Store, replace or remove the bank-in slip after a receipt is saved.
     * Only kept for Bank-In receipts; switching to cash leaves an existing
     * slip alone (so a mis-tap loses nothing) until it is removed.
     * @return string extra line for the "Saved" message
     */
    private function saveBankSlip(int $id, ?string $old): string
    {
        $model    = new Receipt();
        $uploader = new ImageUploader('receipts/bank', true);
        if (($_POST['payment'] ?? '') === 'bank' && ImageUploader::wasProvided($_FILES['bank_slip'] ?? null)) {
            try {
                $model->setBankSlip($id, $uploader->store($_FILES['bank_slip'], 2000));
            } catch (RuntimeException $e) {
                $this->flash('error', '轉帳單據未上傳 Bank slip not uploaded', $e->getMessage() . "\n（收據其他內容已儲存。Everything else was saved.）");
                $this->redirect('/admin/receipts/edit?id=' . $id);
            }
            $uploader->delete($old);
            return "\n已附上轉帳單據。Bank slip attached.";
        }
        if ($old && !empty($_POST['remove_bank_slip'])) {
            $model->setBankSlip($id, null);
            $uploader->delete($old);
            return "\n轉帳單據已移除。Bank slip removed.";
        }
        return '';
    }

    /**
     * Form values (or the AI's reading) → what is stored: trimmed, cut to
     * each column's size, numbers as numbers.
     * @return array{0: array, 1: float} values, and what the boxes add up to
     */
    private function clean(array $in): array
    {
        $str = static fn(string $k, int $max): string => mb_substr(is_scalar($in[$k] ?? null) ? trim((string) $in[$k]) : '', 0, $max);
        $num = static fn(string $k): float => round(max(0, min(9999999, (float) str_replace([',', 'RM', ' '], '', is_scalar($in[$k] ?? null) ? (string) $in[$k] : '0'))), 2);

        $v = [
            'receipt_no'   => substr(preg_replace('/\D+/', '', $str('receipt_no', 60)), 0, 30) ?: null,
            'book_no'      => $this->bookFrom($in['book_no'] ?? ''),
            'receipt_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $str('receipt_date', 10)) ? $str('receipt_date', 10) : null,
            'item'         => $str('item', 255) ?: null,
            'name'         => $str('name', 150) ?: null,
            'other_label'  => $str('other_label', 100) ?: null,
            'total'        => $num('total'),
            'payment'      => in_array($in['payment'] ?? '', ['cash', 'bank'], true) ? $in['payment'] : '',
            'issued_by'    => $str('issued_by', 100) ?: null,
            'notes'        => $str('notes', 2000) ?: null,
        ];
        $sumBoxes = 0.0;
        foreach (Receipt::amountColumns() as $col) {
            $v[$col] = $num($col);
            $sumBoxes += $v[$col];
        }
        // An empty total means "add the boxes up for me".
        if ($v['total'] == 0 && $sumBoxes > 0) {
            $v['total'] = round($sumBoxes, 2);
        }
        return [$v, $sumBoxes];
    }

    /** A book number as typed → "12", "A03", "2026-1" (letters, digits, - / .), or null. */
    private function bookFrom($raw): ?string
    {
        $b = is_string($raw) ? strtoupper(preg_replace('/[^0-9A-Za-z\-\/.]+/', '', $raw)) : '';
        return $b !== '' ? substr($b, 0, 30) : null;
    }

    /** A person's name as typed: trimmed, inner spaces tidied, at most 100 characters; null when empty. */
    private function holderFrom($raw): ?string
    {
        $n = is_string($raw) ? trim(preg_replace('/\s+/u', ' ', $raw)) : '';
        return $n !== '' ? mb_substr($n, 0, 100) : null;
    }

    /** A phone number: digits, +, - and spaces only, at most 30; null when empty. */
    private function phoneFrom($raw): ?string
    {
        $n = is_string($raw) ? trim(preg_replace('/[^0-9+\- ]+/', '', $raw)) : '';
        return $n !== '' ? substr($n, 0, 30) : null;
    }

    /** What the AI was unsure about, as the note shown beside the photo. */
    private function aiNotes(array $read): ?string
    {
        return trim(
            ($read['unsure'] ? '請再看一眼 Please double-check: ' . $this->fieldNames($read['unsure']) . '。' : '') . $read['notes']
        ) ?: null;
    }

    private function fieldNames(array $keys): string
    {
        $names = [
            'receipt_no' => '號碼 No.', 'date' => '日期 Date', 'item' => '項目 Item', 'name' => '姓名 Name',
            'payment' => '付款方式 Paid by', 'total' => '總數 Total', 'issued_by' => '發據人 Issued by',
            'other_label' => '其他 Other',
        ];
        foreach (ReceiptReader::CATEGORIES as $k => [$zh, $en]) {
            $names[$k] = "{$zh} {$en}";
        }
        return implode('、', array_map(static fn($k) => $names[$k] ?? $k, $keys));
    }

    private function fromReading(array $r): array
    {
        $v = [
            'receipt_no' => $r['receipt_no'], 'receipt_date' => $r['date'], 'item' => $r['item'],
            'name' => $r['name'], 'other_label' => $r['other_label'], 'payment' => $r['payment'],
            'total' => $r['total'] ?: '', 'issued_by' => $r['issued_by'], 'notes' => '',
        ];
        foreach ($r['amounts'] as $k => $amt) {
            $v['amt_' . $k] = $amt ?: '';
        }
        return $v;
    }

    /** An unsaved draft's photo is removed when a new scan starts or the review is cancelled. */
    private function discardDraftPhoto(): void
    {
        $old = $_SESSION['receipt_draft']['image_path'] ?? null;
        if ($old) {
            (new ImageUploader('receipts', true))->delete($old);
        }
    }

    /**
     * Signed in, and the Receipts feature switched on in Site settings —
     * or a system admin, who keeps access while it is off. Checked on
     * every receipt page and action, so a typed or bookmarked address
     * does not get round a hidden menu item.
     */
    private function requireReceipts(): void
    {
        $this->requireAdmin();
        if (($this->siteSetting('receipts_enabled') ?? '1') !== '1' && !$this->isSystemAdmin()) {
            $this->flash('error', '收據功能已停用 Receipts are switched off',
                "收據紀錄目前由系統管理員停用。\nThe Receipts feature is currently switched off by the system admin.");
            $this->redirect('/admin/dashboard');
        }
    }

    private function siteSetting(string $key): ?string
    {
        return (new \App\Models\Setting())->site()[$key] ?? null;
    }
}
