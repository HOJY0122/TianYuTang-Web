<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\XlsxWriter;
use App\Models\Donation;
use App\Models\Event;
use App\Models\Rsvp;
use App\Models\Setting;

/**
 * ExportController — CSV downloads for the committee's own records.
 *
 * CSV rather than a real .xlsx on purpose: it needs no library, no
 * Composer install on the server, and Excel, Numbers, LibreOffice and
 * Google Sheets all open it. The treasurer can save it as .xlsx from
 * there if they want formatting.
 *
 * Two things that sound like details but decide whether the file is
 * usable at all are handled below: the UTF-8 BOM, and CSV injection.
 */
class ExportController extends Controller
{
    /** GET /admin/export/attendees?event=<id> */
    public function attendees(): void
    {
        $this->requireAdmin();

        $event = $this->resolveEvent();
        $rows  = (new Rsvp())->attendeeSheet((int) $event['id']);

        // 來源 matters to the committee afterwards: how many people
        // planned ahead versus turned up on the day is what decides how
        // much food to order next year.
        $data = [[
            '姓名 Name', '身份證號碼 IC No.', '聯絡號碼 Contact',
            '報名編號 Ref', '來源 Source', '狀態 Status', '報到 Checked In',
        ]];
        foreach ($rows as $r) {
            $data[] = [
                $r['name'],
                $r['ic_no'],
                $r['contact_no'],
                $r['ref_code'],
                ($r['source'] ?? 'online') === 'walkin' ? '現場 Walk-in' : '線上 Online',
                $r['status'] === 'confirmed' ? '已確認 Confirmed' : '待確認 Pending',
                $r['checked_in_at'] ? date('Y-m-d H:i', strtotime($r['checked_in_at'])) : '',
            ];
        }

        $this->sendCsv($this->filename($event, 'attendees'), $data);
    }

    /** GET /admin/export/donations?event=<id> */
    public function donations(): void
    {
        $this->requireAdmin();

        $event    = $this->resolveEvent();
        $eventId  = (int) $event['id'];
        $model    = new Donation();
        $rows     = $model->all($eventId, 10000);

        $data = [['編號 Ref', '來源 Source', '姓名 Name', '聯絡號碼 Contact', '方式 Method',
                  '功德席數 Seats', '隨喜 Freewill (RM)', '總額 Amount (RM)', '狀態 Status',
                  '登記者 Recorded by', '備註 Notes', '收據 Receipt', '提交時間 Submitted']];

        foreach ($rows as $r) {
            $data[] = [
                $r['ref_code'],
                ($r['source'] ?? 'online') === 'counter' ? '現場 Counter' : '線上 Online',
                $r['name'],
                $r['contact_no'],
                ['table' => '功德席 Merit Seat', 'free' => '隨喜布施 Freewill', 'mixed' => '功德席+隨喜 Seats+Freewill'][$r['method']] ?? $r['method'],
                !empty($r['table_count']) ? (string) (int) $r['table_count'] : '',
                // Older freewill rows predate free_amount; their amount IS the freewill.
                number_format((float) ($r['free_amount'] ?? ($r['method'] === 'free' ? $r['amount'] : 0)), 2, '.', ''),
                number_format((float) $r['amount'], 2, '.', ''),  // no thousands separator: it would split the cell
                $r['status'] === 'paid' ? '已付 Paid' : '待付 Pending',
                $r['recorded_by'] ?? '',
                $r['notes'] ?? '',
                !empty($r['receipt_path']) ? '有 Yes' : '',
                $r['created_at'],
            ];
        }

        // A totals row the treasurer does not have to compute by hand.
        $data[] = [];
        $bySource = $model->totalsBySource($eventId);
        $data[] = ['總計 Total', '', '', '', '', (string) $model->totalTables($eventId), '',
                   number_format($model->totalAmount($eventId), 2, '.', ''), '', '', '', '', ''];
        $data[] = ['已收 Received', '', '', '', '', '', '',
                   number_format($model->totalPaid($eventId), 2, '.', ''), '', '', '', '', ''];
        $data[] = ['線上 Online', '', '', '', '', '', '',
                   number_format($bySource['online'], 2, '.', ''), '', '', '', '', ''];
        $data[] = ['現場 Counter', '', '', '', '', '', '',
                   number_format($bySource['counter'], 2, '.', ''), '', '', '', '', ''];

        $this->sendCsv($this->filename($event, 'donations'), $data);
    }

    // ------------------------------------------------------------------
    // Excel — laid out like the committee's own sample workbooks:
    // a Dashboard sheet of totals, then a Master sheet of every row.
    // ------------------------------------------------------------------

    /** GET /admin/export/attendees-excel?event=<id>  (also …/attendees.xlsx) */
    public function attendeesExcel(): void
    {
        $this->requireAdmin();
        $event = $this->resolveEvent();
        $id    = (int) $event['id'];
        $rsvp  = new Rsvp();
        $rows  = $rsvp->masterList($id);
        $st    = $rsvp->countsByStatus($id);
        $sizes = $rsvp->groupSizes($id);
        $max   = max(10, (int) $event['max_attendees'], $sizes ? max(array_keys($sizes)) : 0);

        $x = $this->workbook();
        $this->dashboard($x, 'Dashboard', $event, 'RSVP Dashboard 報名統計',
            ['Summary 摘要', 'Total 數量', 'Group Size 每組人數', 'Groups 組數'],
            [
                ['👥 Total Attendees 參加人數',     $rsvp->totalAttendees($id), 'kpi'],
                ['📋 Registration Groups 報名組數', $rsvp->totalGroups($id),    'kpi'],
                ['⏳ Pending 待確認',              $st['pending'],             'kpi'],
                ['✅ Confirmed 已確認',            $st['confirmed'],           'kpi'],
                ['🙋 Checked In 已報到',           $rsvp->totalCheckedIn($id), 'kpi'],
                ['❌ Cancelled 已取消',            $st['cancelled'],           'kpi'],
            ],
            array_map(fn($n) => [$n . ' Pax 位', $sizes[$n] ?? 0, 'int'], range(1, $max)),
            '💡 活動當日：在「RSVP Master」搜尋姓名並更新狀態。Event day: use "RSVP Master" to search names and update the status.'
        );

        $master = $x->addSheet('RSVP Master', [
            'widths' => [16, 18, 10, 10, 28, 20, 18, 14, 34], 'freeze' => 1, 'filter' => true, 'zebra' => true,
            'dropdown' => ['H', ['Pending', 'Confirmed', 'Checked In', 'Cancelled']],
        ]);
        $x->row($master, array_map(fn($h) => [$h, 'header'], [
            'Registration ID 報名編號', 'Submitted 提交時間', 'Group Size 人數', 'Attendee 第幾位',
            'Name 姓名', 'IC No. 身份證', 'Contact No. 電話', 'Status 狀態', 'Notes 備註',
        ]), 32);
        $seq = [];
        foreach ($rows as $r) {
            $seq[$r['group_id']] = ($seq[$r['group_id']] ?? 0) + 1;
            $status = $r['status'] === 'cancelled' ? 'Cancelled'
                : ($r['checked_in_at'] ? 'Checked In' : ucfirst($r['status']));
            $notes = trim(($r['source'] === 'walkin' ? '現場報名 Walk-in' : '')
                . (!empty($r['recorded_by']) ? ' · ' . $r['recorded_by'] : '')
                . ($r['checked_in_at'] ? ' · 報到 ' . date('d/m H:i', strtotime($r['checked_in_at'])) : ''), ' ·');
            $x->row($master, [
                $r['ref_code'],
                [XlsxWriter::date($r['created_at']), 'datetime'],
                [(int) $r['attendee_count'], 'int'],
                [(int) $seq[$r['group_id']], 'int'],
                $r['name'],
                [$r['ic_no'], 'textfmt'],        // text, so leading zeros and dashes survive
                [$r['contact_no'], 'textfmt'],
                [$status, 'status'],
                $notes,
            ], 20);
        }
        $x->send($this->filename($event, 'rsvp', 'xlsx'));
    }

    /** GET /admin/export/donations-excel?event=<id>  (also …/donations.xlsx) */
    public function donationsExcel(): void
    {
        $this->requireAdmin();
        $event = $this->resolveEvent();
        $id    = (int) $event['id'];
        $model = new Donation();
        $rows  = array_reverse($model->all($id, 100000));   // oldest first, like a register
        $kind  = $model->totalsByKind($id);
        $src   = $model->totalsBySource($id);
        $total = $model->totalAmount($id);
        $paid  = $model->totalPaid($id);

        $x = $this->workbook();
        $this->dashboard($x, 'Donation Dashboard', $event, 'Donation Dashboard 布施統計',
            ['Summary 摘要', 'Total 數量', 'Donation Method 布施方式', 'Amount 金額'],
            [
                ['💰 Total Donation 布施總額',     $total,                     'kpimoney'],
                ['👥 Total Donors 布施人數',       count($rows),               'kpi'],
                ['🪷 Total Merit Seats 功德席數',  $model->totalTables($id),   'kpi'],
                ['⏳ Pending 待付',               max(0, $total - $paid),     'kpimoney'],
                ['✅ Paid / Received 已收',        $paid,                      'kpimoney'],
            ],
            [
                ['🙏 隨喜布施 Freewill',  $kind['freewill'], 'money'],
                ['🪷 功德席 Merit Seats', $kind['seats'],    'money'],
                ['🌐 線上 Online',       $src['online'],    'money'],
                ['💵 現場 Counter',      $src['counter'],   'money'],
            ],
            '💡 活動當日：在「Donation Master」核對布施者並更新付款狀態。Event day: use "Donation Master" to check donors and update the payment status.'
        );

        $master = $x->addSheet('Donation Master', [
            'widths' => [15, 18, 24, 16, 42, 12, 16, 12, 34], 'freeze' => 1, 'filter' => true, 'zebra' => true,
            'dropdown' => ['H', ['Pending', 'Paid', 'Cancelled']],
        ]);
        $x->row($master, array_map(fn($h) => [$h, 'header'], [
            'Donation ID 布施編號', 'Submitted 提交時間', 'Name 姓名', 'Contact No. 電話', 'Donation Method 布施方式',
            'Merit Seats 功德席', 'Amount (RM) 金額', 'Status 狀態', 'Notes 備註',
        ]), 32);
        foreach ($rows as $r) {
            $notes = implode(' · ', array_filter([
                $r['source'] === 'counter' ? '現場 Counter' : '',
                $r['recorded_by'] ? '登記 ' . $r['recorded_by'] : '',
                $r['receipt_path'] ? '有收據 Receipt' : '',
                $r['notes'] ?? '',
            ]));
            $x->row($master, [
                $r['ref_code'],
                [XlsxWriter::date($r['created_at']), 'datetime'],
                $r['name'],
                [$r['contact_no'], 'textfmt'],
                Donation::describe($r),
                [(int) ($r['table_count'] ?? 0), 'int'],
                [(float) $r['amount'], 'money'],
                [$r['status'] === 'paid' ? 'Paid' : 'Pending', 'status'],
                $notes,
            ], 20);
        }
        $x->send($this->filename($event, 'donations', 'xlsx'));
    }

    private function workbook(): XlsxWriter
    {
        $x = new XlsxWriter();
        $x->creator = (new Setting())->site()['site_name'];
        return $x;
    }

    /**
     * The summary sheet both workbooks open on, laid out like the
     * committee's samples: a title, a line saying which event and when
     * it was exported, then two side-by-side tables and a tip.
     *
     * @param array $left  rows of [label, value, style] — the main totals
     * @param array $right rows of [label, value, style] — the breakdown
     */
    private function dashboard(XlsxWriter $x, string $name, array $event, string $what,
                               array $heads, array $left, array $right, string $tip): void
    {
        $rows = max(count($left), count($right));
        $tipRow = 5 + $rows + 1;
        $s = $x->addSheet($name, [
            'widths' => [34, 18, 3, 30, 18, 3], 'freeze' => 4, 'nogrid' => true,
            'merges' => ['A1:F1', 'A2:F2', "A{$tipRow}:F{$tipRow}"],
        ]);
        $dates = date('d/m/Y', strtotime($event['start_date']))
            . ($event['end_date'] !== $event['start_date'] ? ' – ' . date('d/m/Y', strtotime($event['end_date'])) : '');
        $by = $_SESSION['admin_display'] ?? ($_SESSION['admin_username'] ?? '');
        $x->row($s, [[$this->title($event, $what), 'title']], 34);
        $x->row($s, [['📅 ' . $dates . '　📍 ' . preg_replace('/\s+/', ' ', (string) $event['location'])
            . '　｜　匯出 Exported ' . date('d/m/Y H:i') . ($by !== '' ? ' · ' . $by : ''), 'subtitle']], 20);
        $x->row($s, []);
        $x->row($s, [[$heads[0], 'header'], [$heads[1], 'header'], ['', 'blank'], [$heads[2], 'header'], [$heads[3], 'header']], 26);
        for ($i = 0; $i < $rows; $i++) {
            $l = $left[$i] ?? null;
            $r = $right[$i] ?? null;
            $x->row($s, [
                $l ? [$l[0], 'bold'] : ['', 'blank'],
                $l ? [$l[1], $l[2]] : ['', 'blank'],
                ['', 'blank'],
                $r ? [$r[0], 'text'] : ['', 'blank'],
                $r ? [$r[1], $r[2]] : ['', 'blank'],
            ], 22);
        }
        $x->row($s, []);
        $x->row($s, [[$tip, 'note']], 30);
    }

    /** "🙏 2026 中壇元帥千秋寶誕｜RSVP Dashboard" — like the committee's sample. */
    private function title(array $event, string $what): string
    {
        return '🙏 ' . $event['year'] . ' ' . $event['name'] . '｜' . $what;
    }

    // ------------------------------------------------------------------

    /**
     * Send an array of rows as a CSV download.
     *
     * The BOM is not decoration. Excel on Windows assumes the system
     * codepage unless a file starts with a UTF-8 byte-order mark, so
     * without it every Chinese name opens as mojibake — 陳大文 becomes
     * ä¸­æ–‡. Other tools ignore the BOM harmlessly.
     */
    private function sendCsv(string $filename, array $rows): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        // Discard anything already buffered. A stray warning or a blank
        // line before the BOM would corrupt the download — and unlike a
        // web page, a broken CSV fails silently in Excel rather than
        // showing an error.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $out = fopen('php://output', 'w');

        fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM

        foreach ($rows as $row) {
            // The 4th argument ($escape) must be passed explicitly: PHP
            // 8.4 deprecates relying on its default, and the notice is
            // printed straight into the file, breaking every row. Empty
            // string disables backslash escaping, which is what RFC 4180
            // (and every spreadsheet) actually expects.
            fputcsv($out, array_map([$this, 'neutralise'], $row), ',', '"', '');
        }

        fclose($out);
        exit;
    }

    /**
     * Defuse CSV / formula injection.
     *
     * Every name and contact number in this file was typed by a member
     * of the public. A cell beginning = + - @ (or tab / carriage return)
     * is treated as a FORMULA by Excel, LibreOffice and Google Sheets —
     * so a "name" of =HYPERLINK("http://evil","click") becomes a live
     * link in the treasurer's spreadsheet, and some payloads can invoke
     * external commands.
     *
     * Prefixing with a single quote makes the cell literal text. The
     * quote is not shown once the file is opened in a spreadsheet.
     */
    private function neutralise($value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return $value;
        }

        if (in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }

    /** e.g. tianyutang-2026-attendees-20260924.csv */
    private function filename(array $event, string $kind, string $ext = 'csv'): string
    {
        return sprintf('tianyutang-%d-%s-%s.%s', (int) $event['year'], $kind, date('Ymd'), $ext);
    }

    private function resolveEvent(): array
    {
        $eventModel = new Event();
        $requested  = (int) ($_GET['event'] ?? 0);
        $event      = $requested > 0 ? $eventModel->find($requested) : null;

        return $event ?? $eventModel->active();
    }
}
