<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Donation;
use App\Models\Event;
use App\Models\Rsvp;

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
                  '功德席數 Seats', '金額 Amount (RM)', '狀態 Status',
                  '登記者 Recorded by', '備註 Notes', '收據 Receipt', '提交時間 Submitted']];

        foreach ($rows as $r) {
            $data[] = [
                $r['ref_code'],
                ($r['source'] ?? 'online') === 'counter' ? '現場 Counter' : '線上 Online',
                $r['name'],
                $r['contact_no'],
                $r['method'] === 'table' ? '功德席 Merit Seat' : '隨喜布施 Freewill',
                $r['method'] === 'table' ? (string) (int) $r['table_count'] : '',
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
        $data[] = ['總計 Total', '', '', '', '', (string) $model->totalTables($eventId),
                   number_format($model->totalAmount($eventId), 2, '.', ''), '', '', '', '', ''];
        $data[] = ['已收 Received', '', '', '', '', '',
                   number_format($model->totalPaid($eventId), 2, '.', ''), '', '', '', '', ''];
        $data[] = ['線上 Online', '', '', '', '', '',
                   number_format($bySource['online'], 2, '.', ''), '', '', '', '', ''];
        $data[] = ['現場 Counter', '', '', '', '', '',
                   number_format($bySource['counter'], 2, '.', ''), '', '', '', '', ''];

        $this->sendCsv($this->filename($event, 'donations'), $data);
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
    private function filename(array $event, string $kind): string
    {
        return sprintf('tianyutang-%d-%s-%s.csv', (int) $event['year'], $kind, date('Ymd'));
    }

    private function resolveEvent(): array
    {
        $eventModel = new Event();
        $requested  = (int) ($_GET['event'] ?? 0);
        $event      = $requested > 0 ? $eventModel->find($requested) : null;

        return $event ?? $eventModel->active();
    }
}
