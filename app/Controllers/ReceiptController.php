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

    /** GET /admin/receipts?q=&from=&to=&payment=&sort=&dir=&page= */
    public function index(): void
    {
        $this->requireAdmin();
        $f = $this->filters();
        $model = new Receipt();
        $sum   = $model->summary($f);
        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $pages = max(1, (int) ceil($sum['n'] / self::PER_PAGE));

        $this->view('admin/receipts', [
            'pageTitle'  => '收據紀錄 Receipts',
            'nav'        => 'receipts',
            'filters'    => $f,
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
        $this->requireAdmin();
        $this->view('admin/receipt_scan', [
            'pageTitle' => '新增收據 Add Receipt',
            'nav'       => 'receipts',
            'aiReady'   => ReceiptReader::configured(),
            'flash'     => $this->takeFlash(),
        ]);
    }

    /**
     * POST /admin/receipts/scan — keep the photo, ask the AI to read it,
     * then open the review form with the photo beside the draft.
     */
    public function scan(): void
    {
        $this->requireAdmin();
        if (empty($_POST) && empty($_FILES) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->flash('error', '相片太大 Photo too large',
                '相片超過伺服器限制（' . ini_get('post_max_size') . '）。The photo is over the server limit.');
            $this->redirect('/admin/receipts/new');
        }
        $this->requireCsrf();
        $this->discardDraftPhoto();

        $draft = ['source' => 'manual', 'ai_notes' => null, 'image_path' => null, 'values' => []];

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
                    $draft['values'] = $this->fromReading($read);
                    $draft['ai_notes'] = trim(
                        ($read['unsure'] ? '請再看一眼 Please double-check: ' . $this->fieldNames($read['unsure']) . '。' : '') . $read['notes']
                    ) ?: null;
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
        $this->requireAdmin();
        $draft = $_SESSION['receipt_draft'] ?? null;
        if ($draft === null) {
            $this->redirect('/admin/receipts/new');
        }
        $this->form(null, $draft['values'] + $this->blank(), $draft);
    }

    /** GET /admin/receipts/edit?id= */
    public function edit(): void
    {
        $this->requireAdmin();
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
            'flash'     => $this->takeFlash(),
        ]);
    }

    /** POST /admin/receipts/save — create (from the draft) or update */
    public function save(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $model = new Receipt();
        $id    = (int) ($_POST['id'] ?? 0);
        $row   = $id ? $model->find($id) : null;
        if ($id && $row === null) {
            $this->redirect('/admin/receipts');
        }
        $draft = $row ? null : ($_SESSION['receipt_draft'] ?? ['source' => 'manual', 'ai_notes' => null, 'image_path' => null]);

        $str = static fn(string $k, int $max): string => mb_substr(is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '', 0, $max);
        $num = static fn(string $k): float => round(max(0, min(9999999, (float) str_replace([',', 'RM', ' '], '', (string) ($_POST[$k] ?? '0')))), 2);

        $v = [
            'receipt_no'   => preg_replace('/\D+/', '', $str('receipt_no', 30)) ?: null,
            'receipt_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $str('receipt_date', 10)) ? $str('receipt_date', 10) : null,
            'item'         => $str('item', 255) ?: null,
            'name'         => $str('name', 150) ?: null,
            'other_label'  => $str('other_label', 100) ?: null,
            'total'        => $num('total'),
            'payment'      => in_array($_POST['payment'] ?? '', ['cash', 'bank'], true) ? $_POST['payment'] : '',
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

        $errors = [];
        if ($v['name'] === null && $v['receipt_no'] === null && $v['total'] == 0) {
            $errors[] = '請至少填寫收據號碼、姓名或金額。Please fill in at least the number, a name or an amount.';
        }
        if ($errors) {
            $_SESSION['receipt_old'] = $v + ['receipt_no' => '', 'receipt_date' => ''];
            $this->flash('error', '未儲存 Not saved', implode("\n", $errors));
            $this->redirect($row ? '/admin/receipts/edit?id=' . $id : '/admin/receipts/review');
        }

        $by = (string) ($_SESSION['admin_username'] ?? 'admin');
        if ($row === null) {
            $v += ['image_path' => $draft['image_path'] ?? null, 'source' => $draft['source'] ?? 'manual',
                   'ai_notes' => $draft['ai_notes'] ?? null];
        }
        $newId = $model->save($row ? $id : null, $v, $by);
        unset($_SESSION['receipt_draft']);   // the photo now belongs to the saved receipt

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

        $warn = abs($sumBoxes - $v['total']) > 0.009 && $sumBoxes > 0
            ? "\n⚠️ 各項合計 " . rm($sumBoxes) . ' ≠ 總數 ' . rm($v['total']) . '。Boxes add up to ' . rm($sumBoxes) . ', not the total.'
            : '';
        $this->flash('success', '已儲存 Saved',
            '收據 ' . ($v['receipt_no'] ? 'No. ' . $v['receipt_no'] : '#' . $newId) . ' · ' . ($v['name'] ?? '—') . ' · ' . rm($v['total']) . $warn);
        $this->redirect(!empty($_POST['next']) ? '/admin/receipts/new' : '/admin/receipts/edit?id=' . $newId);
    }

    /** POST /admin/receipts/delete */
    public function delete(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $model = new Receipt();
        $row   = $model->find((int) ($_POST['id'] ?? 0));
        if ($row) {
            $model->delete((int) $row['id']);
            (new ImageUploader('receipts', true))->delete($row['image_path']);
            $this->flash('success', '已刪除 Deleted',
                '收據 ' . ($row['receipt_no'] ? 'No. ' . $row['receipt_no'] : '#' . $row['id']) . ' 已刪除。Receipt deleted.');
        }
        $this->redirect('/admin/receipts');
    }

    /** POST /admin/receipts/cancel — leave the review without saving */
    public function cancel(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $this->discardDraftPhoto();
        unset($_SESSION['receipt_draft']);
        $this->redirect('/admin/receipts');
    }

    /**
     * GET /admin/receipts/image?id=<id>  or  ?draft=1
     * The only way to see a receipt photo: signed-in staff, looked up by
     * id (or the caller's own unsaved draft) — never by a path from the browser.
     */
    public function image(): void
    {
        $this->requireAdmin();
        $path = !empty($_GET['draft'])
            ? ($_SESSION['receipt_draft']['image_path'] ?? null)
            : ((new Receipt())->find((int) ($_GET['id'] ?? 0))['image_path'] ?? null);
        $file = (new ImageUploader('receipts', true))->absolutePath($path);
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
        $this->requireAdmin();
        $f    = $this->filters();
        $rows = (new Receipt())->search($f, 100000);
        $cats = ReceiptReader::CATEGORIES;

        $x = new XlsxWriter();
        $x->creator = (new \App\Models\Setting())->site()['site_name'];
        $s = $x->addSheet('Receipts 收據', [
            'widths' => array_merge([12, 12, 22, 24], array_fill(0, count($cats), 12), [14, 10, 16, 30]),
            'freeze' => 1, 'filter' => true, 'zebra' => true,
        ]);
        $head = ['No. 號碼', 'Date 日期', 'Name 姓名', 'Item 項目'];
        foreach ($cats as [$zh, $en]) {
            $head[] = $zh . ' ' . $en;
        }
        array_push($head, 'Total 總數', 'Paid by 方式', 'Issued by 發據人', 'Notes 備註');
        $x->row($s, array_map(static fn($h) => [$h, 'header'], $head), 34);
        foreach ($rows as $r) {
            $cells = [
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
                $r['issued_by'] ?? '',
                trim(($r['other_label'] ? '其他 Other: ' . $r['other_label'] . ' · ' : '') . ($r['notes'] ?? ''), ' ·'));
            $x->row($s, $cells, 20);
        }
        $x->send('receipts-' . date('Ymd') . '.xlsx');
    }

    // ------------------------------------------------------------------

    private function filters(): array
    {
        $g = static fn(string $k): string => is_string($_GET[$k] ?? null) ? trim($_GET[$k]) : '';
        return [
            'q'       => mb_substr($g('q'), 0, 100),
            'from'    => $g('from'),
            'to'      => $g('to'),
            'payment' => $g('payment'),
            'sort'    => isset(Receipt::SORTS[$g('sort')]) ? $g('sort') : 'added',
            'dir'     => $g('dir') === 'asc' ? 'asc' : 'desc',
        ];
    }

    private function blank(): array
    {
        $v = ['receipt_no' => '', 'receipt_date' => '', 'item' => '', 'name' => '', 'other_label' => '',
              'total' => '', 'payment' => '', 'issued_by' => '', 'notes' => ''];
        foreach (Receipt::amountColumns() as $c) {
            $v[$c] = '';
        }
        return $v;
    }

    /** The AI's reading, as form values. */
    /** ['name', 'issued_by'] → "姓名 Name、發據人 Issued by" */
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
}
