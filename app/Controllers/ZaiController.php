<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\ImageUploader;
use App\Core\SheetReader;
use App\Models\Event;
use App\Models\Rsvp;
use App\Models\Zai;
use RuntimeException;

/**
 * ZaiController — 施齋 Day 3: who eats at which table.
 *
 *   /admin/zai            總覽 tables at a glance (seats used / free, who sits where)
 *   /admin/zai/registered 已登記 search an IC (or name / phone / ref) → confirm or
 *                         pending → table
 *   /admin/zai/groups     團體 organisation registrations + the PM's list:
 *                         arrived → table(s) → reminder: cheque photo on the staff phone
 *   /admin/zai/waiting    候補 people who came without registering; onsite
 *                         staff seat them later
 *   /admin/zai/tables     設定 how many tables, seats at each
 *
 * Made for a phone in a busy hall: big buttons, plain form posts, and the
 * page jumps back to the row just changed.
 */
class ZaiController extends Controller
{
    // ---------------------------------------------------------- pages

    /** GET /admin/zai */
    public function index(): void
    {
        $this->requireAdmin();
        $event = $this->event();
        $zai = new Zai();
        $this->page('admin/zai_overview', 'overview', $event, [
            'seating' => $zai->seating((int) $event['id']),
            'totals'  => $zai->totals((int) $event['id']),
        ]);
    }

    /**
     * GET /admin/zai/map?table= — 平面圖: the tables in rows (4 per row by
     * default), each with who sits or is booked there. Tap a table to make
     * it a 團體桌 group table, pre-assign a group to it, or change its seats.
     */
    public function map(): void
    {
        $this->requireAdmin();
        $event = $this->event();
        $zai = new Zai();
        $seating = $zai->seating((int) $event['id']);
        $no = (int) ($_GET['table'] ?? 0);
        $this->page('admin/zai_map', 'map', $event, [
            'seating' => $seating,
            'cols'    => max(2, min(10, (int) ((new \App\Models\Setting())->site()['zai_map_cols'] ?? 4))),
            'edit'    => isset($seating[$no]) && $seating[$no]['seats'] > 0 ? $no : null,
            'groups'  => $zai->groups((int) $event['id']),
        ]);
    }

    /** POST /admin/zai/map/table — one table: kind, seats, pre-assigned groups. */
    public function mapTable(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $event = (new Event())->find((int) ($_POST['event_id'] ?? 0));
        $no = (int) ($_POST['table_no'] ?? 0);
        $zai = new Zai();
        if ($event === null || !isset($zai->tables((int) $event['id'])[$no])) {
            $this->redirect('/admin/zai/map');
        }
        $eventId = (int) $event['id'];
        $back = '/admin/zai/map?event=' . $eventId . '&table=' . $no . '#t' . $no;
        if (isset($_POST['kind'])) {
            $zai->setTableKind($eventId, $no, (string) $_POST['kind']);
        }
        if (isset($_POST['seats'])) {
            $zai->setTableSeats($eventId, $no, (int) $_POST['seats']);
        }
        foreach (['add_group' => true, 'remove_group' => false] as $field => $add) {
            $g = $zai->group((int) ($_POST[$field] ?? 0));
            if ($g !== null && (int) $g['event_id'] === $eventId) {
                $zai->groupTable((int) $g['id'], $no, $add);
                if ($add) {
                    $zai->setTableKind($eventId, $no, 'group');   // a table given to a group is a group table
                    $this->warnIfFull($zai, $eventId, $no);
                }
            }
        }
        $this->redirect($back);
    }

    /** POST /admin/zai/map/cols — tables per row on the map. */
    public function mapCols(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        (new \App\Models\Setting())->set('zai_map_cols', (string) max(2, min(10, (int) ($_POST['cols'] ?? 4))));
        $this->redirect('/admin/zai/map?event=' . (int) ($_POST['event_id'] ?? 0));
    }

    /** GET /admin/zai/registered?q= */
    public function registered(): void
    {
        $this->requireAdmin();
        $event = $this->event();
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
        $rsvp = new Rsvp();
        $groups = [];
        $status = [];
        if ($q !== '') {
            foreach ($rsvp->searchGroups((int) $event['id'], ['q' => $q], 20) as $g) {
                if ($g['status'] === 'cancelled') {
                    continue;
                }
                $g['people'] = $rsvp->attendeesOf((int) $g['id']);
                $groups[] = $g;
            }
            $ids = [];
            foreach ($groups as $g) {
                $ids = array_merge($ids, array_column($g['people'], 'id'));
            }
            $status = (new Zai())->peopleStatus($ids);
        }
        $this->page('admin/zai_registered', 'registered', $event, ['q' => $q, 'groups' => $groups, 'status' => $status]);
    }

    /** GET /admin/zai/groups?q= */
    public function groups(): void
    {
        $this->requireAdmin();
        $event = $this->event();
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
        $this->page('admin/zai_groups', 'groups', $event, ['q' => $q, 'groups' => (new Zai())->groups((int) $event['id'], $q)]);
    }

    /** GET /admin/zai/waiting */
    public function waiting(): void
    {
        $this->requireAdmin();
        $event = $this->event();
        $this->page('admin/zai_waiting', 'waiting', $event, ['rows' => (new Zai())->waiting((int) $event['id'])]);
    }

    /** GET /admin/zai/tables */
    public function tables(): void
    {
        $this->requireAdmin();
        $event = $this->event();
        $this->page('admin/zai_tables', 'tables', $event, ['seating' => (new Zai())->seating((int) $event['id'])]);
    }

    // -------------------------------------------------------- actions

    /** POST /admin/zai/person — one registered person: confirmed / pending, table (or clear). */
    public function person(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $zai = new Zai();
        $id = (int) ($_POST['attendee_id'] ?? 0);
        $eventId = $zai->attendeeEvent($id);
        $back = '/admin/zai/registered?event=' . (int) $eventId . '&q=' . urlencode((string) ($_POST['q'] ?? '')) . '#p' . $id;
        if ($eventId === null || $eventId !== (int) ($_POST['event_id'] ?? 0)) {
            $this->redirect('/admin/zai/registered');
        }
        if (($_POST['status'] ?? '') === 'clear') {
            $zai->clearPerson($id);
            $this->redirect($back);
        }
        $status = ($_POST['status'] ?? '') === 'pending' ? 'pending' : 'confirmed';
        $table = $this->tableNo($_POST['table_no'] ?? '');
        $zai->setPerson($eventId, $id, $status, $table, $this->by());
        $this->warnIfFull($zai, $eventId, $table);
        $this->redirect($back);
    }

    /** POST /admin/zai/person-group — everyone in one registration at once. */
    public function personGroup(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $rsvp = new Rsvp();
        $zai = new Zai();
        $group = $rsvp->findGroup((int) ($_POST['group_id'] ?? 0));
        if ($group === null || (int) $group['event_id'] !== (int) ($_POST['event_id'] ?? 0)) {
            $this->redirect('/admin/zai/registered');
        }
        $status = ($_POST['status'] ?? '') === 'pending' ? 'pending' : 'confirmed';
        $table = $this->tableNo($_POST['table_no'] ?? '');
        foreach ($rsvp->attendeesOf((int) $group['id']) as $a) {
            $zai->setPerson((int) $group['event_id'], (int) $a['id'], $status, $table, $this->by());
        }
        $this->warnIfFull($zai, (int) $group['event_id'], $table);
        $this->redirect('/admin/zai/registered?event=' . (int) $group['event_id'] . '&q=' . urlencode((string) ($_POST['q'] ?? '')) . '#g' . (int) $group['id']);
    }

    /** POST /admin/zai/group — a 團體 group: arrived, people, table(s), cheque no. */
    public function saveGroup(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $zai = new Zai();
        $g = $zai->group((int) ($_POST['id'] ?? 0));
        if ($g === null) {
            $this->redirect('/admin/zai/groups');
        }
        $back = '/admin/zai/groups?event=' . (int) $g['event_id'] . '&q=' . urlencode((string) ($_POST['q'] ?? '')) . '#zg' . (int) $g['id'];
        $tables = implode(', ', Zai::tableList((string) ($_POST['tables'] ?? '')));
        $zai->saveGroup((int) $g['id'], [
            'people'    => max(0, min(5000, (int) ($_POST['people'] ?? $g['people']))),
            'tables'    => $tables !== '' ? mb_substr($tables, 0, 120) : null,
            'cheque_no' => $this->str('cheque_no', 40),
            'notes'     => $this->str('notes', 500),
            'status'    => ($_POST['status'] ?? '') === 'arrived' ? 'arrived' : 'expected',
            'by'        => $this->by(),
        ]);
        foreach (Zai::tableList($tables) as $no) {
            $this->warnIfFull($zai, (int) $g['event_id'], $no);
        }
        // Just checked in: remind the staff to take the cheque photo on their phone
        // (nothing is uploaded here). A table warning, if any, is kept underneath.
        if (($_POST['status'] ?? '') === 'arrived' && $g['status'] !== 'arrived') {
            $prev = isset($_SESSION['flash']) ? $_SESSION['flash']['title'] . "\n" . $_SESSION['flash']['message'] : '';
            $this->flash('info', '📷 請拍支票照片 Take the cheque photo',
                "「{$g['name']}」已到。請團體代表拿著支票，用你的手機拍照（不用上傳）。
"
                . "{$g['name']} has arrived — take a photo of the representative with the cheque on your phone (no upload)."
                . ($prev !== '' ? "

" . $prev : ''));
        }
        $this->redirect($back);
    }

    /** POST /admin/zai/groups/import — the PM's list (Excel / CSV / pasted). */
    public function importGroups(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $event = (new Event())->find((int) ($_POST['event_id'] ?? 0));
        if ($event === null) {
            $this->redirect('/admin/zai/groups');
        }
        $back = '/admin/zai/groups?event=' . (int) $event['id'];
        try {
            $file = $_FILES['sheet'] ?? null;
            if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size'] > 10 * 1024 * 1024) {
                    throw new RuntimeException('檔案上傳失敗（10MB 以內）。The upload failed (10 MB at most).');
                }
                $rows = SheetReader::file($file['tmp_name'], (string) $file['name']);
            } else {
                $paste = is_string($_POST['paste'] ?? null) ? mb_substr($_POST['paste'], 0, 300000) : '';
                if (trim($paste) === '') {
                    throw new RuntimeException('請選擇 Excel 檔案，或貼上名單。Choose an Excel file or paste the list.');
                }
                $rows = SheetReader::text($paste);
            }
            $list = Zai::groupsFromRows($rows);
            if (!$list || count($list) > 2000) {
                throw new RuntimeException('找不到團體名稱（每次最多 2000 個）。No group names found (2,000 at most).');
            }
        } catch (RuntimeException $e) {
            $this->flash('error', '未匯入 Not imported', $e->getMessage());
            $this->redirect($back);
        }
        $n = (new Zai())->importGroups((int) $event['id'], $list);
        $this->flash('success', '已匯入 Imported', "已加入 {$n} 個團體。\nAdded {$n} group(s).");
        $this->redirect($back);
    }

    /** POST /admin/zai/groups/delete — only groups from the PM's list (registrations stay). */
    public function deleteGroup(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $zai = new Zai();
        $g = $zai->group((int) ($_POST['id'] ?? 0));
        if ($g !== null && $g['source'] === 'list') {
            $zai->deleteGroup((int) $g['id']);
            (new ImageUploader('zai', true))->delete($g['cheque_path']);
        }
        $this->redirect('/admin/zai/groups?event=' . (int) ($g['event_id'] ?? 0));
    }

    /** GET /admin/zai/cheque?id= — a group's cheque photo, signed-in staff only. */
    public function cheque(): void
    {
        $this->requireAdmin();
        $g = (new Zai())->group((int) ($_GET['id'] ?? 0));
        $file = (new ImageUploader('zai', true))->absolutePath($g['cheque_path'] ?? null);
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

    /** POST /admin/zai/waiting/add */
    public function addWaiting(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $event = (new Event())->find((int) ($_POST['event_id'] ?? 0));
        if ($event === null) {
            $this->redirect('/admin/zai/waiting');
        }
        $back = '/admin/zai/waiting?event=' . (int) $event['id'];
        $name = $this->str('name', 100);
        if ($name === null) {
            $this->flash('error', '未加入 Not added', '請輸入姓名。Please enter a name.');
            $this->redirect($back);
        }
        $zai = new Zai();
        // Walk-in: one person, one IC (required), same age rule as online.
        $ic = $this->str('ic', 30) ?? '';
        if (($problem = Zai::walkInIcProblem($ic, $event)) !== null) {
            $this->flash('error', '未加入 Not added', $problem);
            $this->redirect($back);
        }
        // Already registered online? Send them to the registered list instead.
        if (empty($_POST['force']) && ($reg = $zai->registeredIc((int) $event['id'], $ic)) !== null) {
            $this->flash('info', '已經登記 Already registered',
                "這個身份證已在網上登記：{$reg['name']}（{$reg['ref_code']}）。請到「已登記」確認入座。\n"
                . "This IC is already registered online — {$reg['name']} ({$reg['ref_code']}). Confirm them under Registered.");
            $this->redirect('/admin/zai/registered?event=' . (int) $event['id'] . '&q=' . urlencode($reg['ref_code']));
        }
        $no = $zai->addWaiting((int) $event['id'], [
            'name' => $name, 'ic' => $ic, 'contact' => $this->str('contact', 30),
            'people' => 1, 'notes' => $this->str('notes', 300),
        ], $this->by());
        $this->flash('success', '候補 #' . $no, "{$name} 已加入候補名單，號碼 #{$no}。\n{$name} is on the waiting list, number #{$no}.");
        $this->redirect($back);
    }

    /** POST /admin/zai/waiting/update — seated at a table, or left. */
    public function updateWaiting(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $zai = new Zai();
        $w = $zai->waitingRow((int) ($_POST['id'] ?? 0));
        if ($w === null) {
            $this->redirect('/admin/zai/waiting');
        }
        $status = in_array($_POST['status'] ?? '', ['waiting', 'seated', 'left'], true) ? $_POST['status'] : 'waiting';
        $table = $this->tableNo($_POST['table_no'] ?? '');
        if ($status === 'seated' && $table === null) {
            $this->flash('error', '請選桌號 Choose a table', '安排入座前請先選擇桌號。Choose a table number first.');
            $this->redirect('/admin/zai/waiting?event=' . (int) $w['event_id'] . '#w' . (int) $w['id']);
        }
        $zai->updateWaiting((int) $w['id'], $status, $table);
        if ($status === 'seated') {
            $this->warnIfFull($zai, (int) $w['event_id'], $table);
        }
        $this->redirect('/admin/zai/waiting?event=' . (int) $w['event_id'] . '#w' . (int) $w['id']);
    }

    /** POST /admin/zai/tables — how many tables, seats at each. */
    public function saveTables(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $event = (new Event())->find((int) ($_POST['event_id'] ?? 0));
        if ($event === null) {
            $this->redirect('/admin/zai/tables');
        }
        $zai = new Zai();
        if (isset($_POST['count'])) {
            $zai->setTables((int) $event['id'], (int) $_POST['count'], (int) ($_POST['seats'] ?? Zai::DEFAULT_SEATS), !empty($_POST['reset_seats']));
        }
        // "Use this for every table" wins over the per-table boxes below it.
        foreach (empty($_POST['reset_seats']) ? (array) ($_POST['table'] ?? []) : [] as $no => $seats) {
            $zai->setTableSeats((int) $event['id'], (int) $no, (int) $seats);
        }
        $this->flash('success', '已儲存 Saved', "桌子已更新。\nThe tables are saved.");
        $this->redirect('/admin/zai/tables?event=' . (int) $event['id']);
    }

    // -------------------------------------------------------- helpers

    private function event(): array
    {
        $m = new Event();
        $id = (int) ($_GET['event'] ?? 0);
        return ($id > 0 ? $m->find($id) : null) ?? $m->active();
    }

    private function page(string $view, string $tab, array $event, array $data): void
    {
        $this->view($view, $data + [
            'pageTitle' => '施齋 Day 3 · ' . ['overview' => '總覽 Overview', 'map' => '平面圖 Table map', 'registered' => '已登記 Registered', 'groups' => '團體 Groups',
                                             'waiting' => '候補 Waiting list', 'tables' => '桌子 Tables'][$tab],
            'nav'       => 'zai',
            'tab'       => $tab,
            'event'     => $event,
            'allEvents' => (new Event())->all(),
            'seating'   => $data['seating'] ?? (new Zai())->seating((int) $event['id']),
            'flash'     => $this->takeFlash(),
        ]);
    }

    private function tableNo($v): ?int
    {
        $n = (int) (is_string($v) ? trim($v) : 0);
        return $n > 0 && $n <= 9999 ? $n : null;
    }

    /** A gentle warning (not a refusal): staff on the floor know best. */
    private function warnIfFull(Zai $zai, int $eventId, ?int $table): void
    {
        if ($table === null) {
            return;
        }
        $t = $zai->seating($eventId)[$table] ?? null;
        if ($t === null || $t['seats'] === 0) {
            $this->flash('info', '沒有這張桌子 No such table', "第 {$table} 桌不在桌子設定裡，已照記錄。\nTable {$table} is not set up in Tables — recorded anyway.");
        } elseif ($t['used'] > $t['seats']) {
            $this->flash('info', "第 {$table} 桌已超出 Table {$table} is over",
                "第 {$table} 桌現有 {$t['used']} 人，座位 {$t['seats']} 個。\nTable {$table} now has {$t['used']} people for {$t['seats']} seats.");
        }
    }

    private function str(string $key, int $max): ?string
    {
        $v = is_string($_POST[$key] ?? null) ? trim(preg_replace('/\s+/u', ' ', $_POST[$key])) : '';
        $v = mb_substr($v, 0, $max);
        return $v !== '' ? $v : null;
    }

    private function by(): string
    {
        return mb_substr((string) ($_SESSION['admin_display'] ?? $_SESSION['admin_username'] ?? 'admin'), 0, 50);
    }
}
