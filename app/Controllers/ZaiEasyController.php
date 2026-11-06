<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\ImageUploader;
use App\Models\Event;
use App\Models\Rsvp;
use App\Models\Zai;
use RuntimeException;

/**
 * ZaiEasyController — 施齋入座 簡易模式 Easy mode, for the helpers on the day.
 *
 * One question per screen, huge buttons, no menu, no jargon:
 *
 *   start   "誰來了？Who is here?"
 *             ① 已經網上登記  → type IC → tick people → ✓ → tap a table → done
 *             ② 團體          → tap the group → people / tables / cheque photo → done
 *             ③ 沒有登記      → name, phone, how many → queue number → done
 *             ④ 候補名單      → tap a waiting person → tap a table → done
 *   done    a big ✓ with what happened, and "下一位 Next".
 *
 * Always the active event. Everything is written through App\Models\Zai,
 * the same as the full Day 3 pages, so supervisors see it at once.
 */
class ZaiEasyController extends Controller
{
    /** GET /admin/zai/easy */
    public function start(): void
    {
        $this->requireAdmin();
        $event = (new Event())->active();
        $this->screen('start', ['totals' => (new Zai())->totals((int) $event['id'])]);
    }

    /** GET /admin/zai/easy/find?q= — someone who registered online. */
    public function find(): void
    {
        $this->requireAdmin();
        $event = (new Event())->active();
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 40);
        $people = [];
        if ($q !== '') {
            $rsvp = new Rsvp();
            $all = [];
            foreach ($rsvp->searchGroups((int) $event['id'], ['q' => $q], 10) as $g) {
                if ($g['status'] === 'cancelled') {
                    continue;
                }
                foreach ($rsvp->attendeesOf((int) $g['id']) as $a) {
                    $all[] = $a + ['ref_code' => $g['ref_code'], 'group_id' => (int) $g['id'], 'org_name' => $g['reg_type'] === 'organisation' ? $g['org_name'] : null];
                }
            }
            $status = (new Zai())->peopleStatus(array_column($all, 'id'));
            foreach ($all as $a) {
                $a['zai'] = $status[(int) $a['id']] ?? null;
                $people[] = $a;
            }
        }
        $this->screen('find', ['q' => $q, 'people' => $people]);
    }

    /**
     * POST /admin/zai/easy/pick — the people ticked on the search screen:
     * "✓ 確認，選桌" goes on to the table screen; "待定" marks them pending.
     */
    public function pick(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $ids = $this->ids($_POST['ids'] ?? []);
        if (!$ids) {
            $this->done('!', '沒有選人 No one ticked', '請先按名字選人。Tap the names first.', 'warn', ['/admin/zai/easy/find', '返回 Back']);
        }
        if (($_POST['go'] ?? '') === 'table') {
            $this->redirect('/admin/zai/easy/table?for=people&ids=' . implode(',', $ids));
        }
        $this->pending();
    }

    /** The ticked people are 待定 pending (no table yet). */
    private function pending(): void
    {
        $event = (new Event())->active();
        $zai = new Zai();
        $names = [];
        foreach ($this->ids($_POST['ids'] ?? []) as $id) {
            if ($zai->attendeeEvent($id) === (int) $event['id']) {
                $zai->setPerson((int) $event['id'], $id, 'pending', null, $this->by());
                $names[] = $this->attendeeName($id);
            }
        }
        $this->done('…', '待定 Pending', $names ? implode('、', $names) : '沒有選人 No one ticked', 'warn');
    }

    /**
     * GET /admin/zai/easy/table?for=people&ids=1,2 | for=wait&id=3 — tap a table.
     * Tables with enough room are big and green; the rest are grey.
     */
    public function table(): void
    {
        $this->requireAdmin();
        $event = (new Event())->active();
        $for = ($_GET['for'] ?? '') === 'wait' ? 'wait' : 'people';
        $zai = new Zai();
        if ($for === 'wait') {
            $w = $zai->waitingRow((int) ($_GET['id'] ?? 0));
            if ($w === null || (int) $w['event_id'] !== (int) $event['id']) {
                $this->redirect('/admin/zai/easy/waitlist');
            }
            $who = [$w['name'] . ((int) $w['people'] > 1 ? '（' . (int) $w['people'] . ' 人）' : '')];
            $need = (int) $w['people'];
            $ids = [(int) $w['id']];
        } else {
            $ids = array_values(array_filter($this->ids(explode(',', (string) ($_GET['ids'] ?? ''))),
                static fn($id) => $zai->attendeeEvent($id) === (int) $event['id']));
            if (!$ids) {
                $this->redirect('/admin/zai/easy/find');
            }
            $who = array_map([$this, 'attendeeName'], $ids);
            $need = count($ids);
        }
        $this->screen('table', ['for' => $for, 'ids' => $ids, 'who' => $who, 'need' => $need,
                                'seating' => $zai->seating((int) $event['id'])]);
    }

    /** POST /admin/zai/easy/seat — put them at the tapped table. */
    public function seat(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $event = (new Event())->active();
        $eventId = (int) $event['id'];
        $zai = new Zai();
        $table = (int) ($_POST['table'] ?? 0);
        if ($table < 1) {
            $this->redirect('/admin/zai/easy');
        }
        $names = [];
        if (($_POST['for'] ?? '') === 'wait') {
            $w = $zai->waitingRow((int) ($_POST['id'] ?? 0));
            if ($w && (int) $w['event_id'] === $eventId) {
                $zai->updateWaiting((int) $w['id'], 'seated', $table);
                $names[] = $w['name'] . ((int) $w['people'] > 1 ? '（' . (int) $w['people'] . ' 人）' : '');
            }
            $next = ['/admin/zai/easy/waitlist', '下一位候補 Next waiting'];
        } else {
            foreach ($this->ids(explode(',', (string) ($_POST['ids'] ?? ''))) as $id) {
                if ($zai->attendeeEvent($id) === $eventId) {
                    $zai->setPerson($eventId, $id, 'confirmed', $table, $this->by());
                    $names[] = $this->attendeeName($id);
                }
            }
            $next = null;
        }
        $t = $zai->seating($eventId)[$table] ?? ['seats' => 0, 'used' => 0];
        $over = $t['seats'] > 0 && $t['used'] > $t['seats'];
        $this->done('第 ' . $table . ' 桌', 'Table ' . $table, implode('、', $names)
            . ($over ? "\n⚠ 這桌已超過 {$t['seats']} 人，請告訴負責人。This table is over — tell the supervisor." : ''),
            $over ? 'warn' : 'ok', $next);
    }

    /** GET /admin/zai/easy/groups?q= */
    public function groups(): void
    {
        $this->requireAdmin();
        $event = (new Event())->active();
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 40);
        $this->screen('groups', ['q' => $q, 'groups' => (new Zai())->groups((int) $event['id'], $q)]);
    }

    /** GET /admin/zai/easy/group?id= */
    public function group(): void
    {
        $this->requireAdmin();
        $event = (new Event())->active();
        $zai = new Zai();
        $g = $zai->group((int) ($_GET['id'] ?? 0));
        if ($g === null || (int) $g['event_id'] !== (int) $event['id']) {
            $this->redirect('/admin/zai/easy/groups');
        }
        $this->screen('group', ['g' => $g, 'seating' => $zai->seating((int) $event['id'])]);
    }

    /** POST /admin/zai/easy/group — arrived, people, tables, cheque photo in one go. */
    public function saveGroup(): void
    {
        $this->requireAdmin();
        if (empty($_POST) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->done('!', '相片太大 Photo too large', '請再拍一次。Please take the photo again.', 'warn', ['/admin/zai/easy/groups', '返回 Back']);
        }
        $this->requireCsrf();
        $event = (new Event())->active();
        $zai = new Zai();
        $g = $zai->group((int) ($_POST['id'] ?? 0));
        if ($g === null || (int) $g['event_id'] !== (int) $event['id']) {
            $this->redirect('/admin/zai/easy/groups');
        }
        $tables = array_slice(Zai::tableList(implode(',', array_map('strval', (array) ($_POST['tables'] ?? [])))), 0, 30);
        if (!$tables) {
            $this->done('!', '請選桌號 Choose a table', '請按一張或多張桌子。Tap one or more tables.', 'warn', ['/admin/zai/easy/group?id=' . (int) $g['id'], '返回 Back']);
        }
        $zai->saveGroup((int) $g['id'], [
            'people'    => max(0, min(5000, (int) ($_POST['people'] ?? $g['people']))),
            'tables'    => implode(', ', $tables),
            'cheque_no' => $this->str('cheque_no', 40) ?? $g['cheque_no'],
            'notes'     => $g['notes'],
            'status'    => 'arrived',
            'by'        => $this->by(),
        ]);
        $photo = '';
        if (ImageUploader::wasProvided($_FILES['cheque'] ?? null)) {
            $uploader = new ImageUploader('zai', true);
            try {
                $path = $uploader->store($_FILES['cheque'], 2000);
                $zai->setCheque((int) $g['id'], $path);
                $uploader->delete($g['cheque_path']);
                $photo = "\n📷 支票相片已存 Cheque photo saved";
            } catch (RuntimeException $e) {
                $photo = "\n⚠ 相片未存：" . $e->getMessage();
            }
        } elseif (!$g['cheque_path']) {
            $photo = "\n⚠ 還沒有支票相片 No cheque photo yet";
        }
        $this->done('第 ' . implode('、', $tables) . ' 桌', $g['name'] . ' · ' . (int) ($_POST['people'] ?? $g['people']) . ' 人', '✓ 已到 Arrived' . $photo,
            str_contains($photo, '⚠') ? 'warn' : 'ok', ['/admin/zai/easy/groups', '下一個團體 Next group']);
    }

    /** GET /admin/zai/easy/wait — someone who did not register. */
    public function wait(): void
    {
        $this->requireAdmin();
        $this->screen('wait', []);
    }

    /** POST /admin/zai/easy/wait */
    public function addWait(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $event = (new Event())->active();
        $zai = new Zai();
        $name = $this->str('name', 100);
        if ($name === null) {
            $this->redirect('/admin/zai/easy/wait');
        }
        $ic = $this->str('ic', 20) ?? '';
        if ($ic !== '' && ($reg = $zai->registeredIc((int) $event['id'], $ic)) !== null) {
            // Registered after all: go straight to their registration.
            $this->redirect('/admin/zai/easy/find?q=' . urlencode($reg['ref_code']) . '&found=1');
        }
        $no = $zai->addWaiting((int) $event['id'], [
            'name' => $name, 'ic' => $ic, 'contact' => $this->str('contact', 30),
            'people' => max(1, min(50, (int) ($_POST['people'] ?? 1))), 'notes' => null,
        ], $this->by());
        $this->done('#' . $no, '候補號碼 Waiting number', $name . "\n請到候補區等候，叫號後安排座位。\nPlease wait in the waiting area until called.", 'info');
    }

    /** GET /admin/zai/easy/waitlist */
    public function waitlist(): void
    {
        $this->requireAdmin();
        $event = (new Event())->active();
        $rows = array_values(array_filter((new Zai())->waiting((int) $event['id']), static fn($r) => $r['status'] === 'waiting'));
        $this->screen('waitlist', ['rows' => $rows]);
    }

    /** GET /admin/zai/easy/done */
    public function doneScreen(): void
    {
        $this->requireAdmin();
        $d = $_SESSION['zai_done'] ?? null;
        unset($_SESSION['zai_done']);
        if ($d === null) {
            $this->redirect('/admin/zai/easy');
        }
        $this->screen('done', ['d' => $d]);
    }

    // ------------------------------------------------------------------

    private function screen(string $name, array $data): void
    {
        $this->view('zai_easy/' . $name, $data + [
            'event'  => (new Event())->active(),
            'screen' => $name,
            'onlyZai' => \App\Core\Access::isHelper(),
        ]);
    }

    /** Show the big ✓ screen (POST → redirect → GET, so a refresh never repeats it). */
    private function done(string $big, string $title, string $text, string $tone = 'ok', ?array $next = null): void
    {
        $_SESSION['zai_done'] = ['big' => $big, 'title' => $title, 'text' => $text, 'tone' => $tone,
                                 'next' => $next ?? ['/admin/zai/easy', '下一位 Next person']];
        $this->redirect('/admin/zai/easy/done');
    }

    private function ids($raw): array
    {
        return array_slice(array_values(array_unique(array_filter(array_map('intval', is_array($raw) ? $raw : [])))), 0, 50);
    }

    private function attendeeName(int $id): string
    {
        $row = $this->scalarName($id);
        return $row ?? ('#' . $id);
    }

    private function scalarName(int $id): ?string
    {
        $st = \App\Core\Database::conn()->prepare('SELECT name FROM rsvp_attendees WHERE id = ?');
        $st->execute([$id]);
        $n = $st->fetchColumn();
        return $n === false ? null : (string) $n;
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
