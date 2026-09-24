<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Donation;
use App\Models\Event;
use App\Models\Rsvp;
use Exception;

/**
 * RecordsController — the admin's lists of registrations and donations,
 * with search, filters, paging, quick actions and a full edit page for
 * every record.
 *
 * Every write re-checks the record's event on the server; list filters
 * only ever come from the query string and are whitelisted in the model.
 */
class RecordsController extends Controller
{
    private const PER_PAGE = 25;

    // ------------------------------------------------------------------
    // Registrations
    // ------------------------------------------------------------------

    /** GET /admin/registrations?event=&q=&status=&source=&page= */
    public function registrations(): void
    {
        $this->requireAdmin();
        [$event, $allEvents] = $this->pickEvent();
        $filters = $this->filters(['status', 'source']);
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $rsvp    = new Rsvp();
        $total   = $rsvp->countGroups((int) $event['id'], $filters);

        $this->view('admin/registrations', [
            'pageTitle'    => '報名紀錄 Registrations',
            'nav'          => 'registrations',
            'event'        => $event,
            'allEvents'    => $allEvents,
            'eventBarPath' => '/admin/registrations',
            'filters'      => $filters,
            'groups'       => $rsvp->searchGroups((int) $event['id'], $filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'total'        => $total,
            'page'         => $page,
            'pages'        => max(1, (int) ceil($total / self::PER_PAGE)),
            'flash'        => $this->takeFlash(),
        ]);
    }

    /** GET /admin/registrations/edit?id= */
    public function editRegistration(): void
    {
        $this->requireAdmin();
        $rsvp  = new Rsvp();
        $group = $rsvp->findGroup((int) ($_GET['id'] ?? 0));
        if ($group === null) {
            $this->flash('error', '找不到 Not found', '找不到這筆報名。This registration does not exist.');
            $this->redirect('/admin/registrations');
        }

        $this->view('admin/registration_edit', [
            'pageTitle' => '編輯報名 Edit Registration',
            'nav'       => 'registrations',
            'group'     => $group,
            'event'     => (new Event())->find((int) $group['event_id']),
            'people'    => $rsvp->attendeesOf((int) $group['id']),
            'errors'    => $this->takeErrors(),
            'flash'     => $this->takeFlash(),
        ]);
    }

    /** POST /admin/registrations/save */
    public function saveRegistration(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $rsvp    = new Rsvp();
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $group   = $rsvp->findGroup($groupId);
        if ($group === null) {
            $this->redirect('/admin/registrations');
        }

        $ids      = array_values((array) ($_POST['person_id'] ?? []));
        $names    = array_values((array) ($_POST['person_name'] ?? []));
        $ics      = array_values((array) ($_POST['person_ic'] ?? []));
        $contacts = array_values((array) ($_POST['person_contact'] ?? []));
        $removed  = array_map('intval', array_keys(array_filter((array) ($_POST['person_remove'] ?? []))));

        $people = [];
        $errors = [];
        foreach ($names as $i => $name) {
            if (in_array($i, $removed, true)) {
                continue;
            }
            $name    = is_string($name) ? trim($name) : '';
            $ic      = is_string($ics[$i] ?? null) ? trim($ics[$i]) : '';
            $contact = is_string($contacts[$i] ?? null) ? trim($contacts[$i]) : '';
            if ($name === '' && $ic === '' && $contact === '') {
                continue;   // a blank "add person" row that was never filled in
            }
            $n = count($people) + 1;
            if ($name === '' || mb_strlen($name) > 100) {
                $errors[] = "第 {$n} 位：請填寫姓名。Person {$n}: name is required.";
            }
            if (mb_strlen($ic) > 30 || mb_strlen($contact) > 30) {
                $errors[] = "第 {$n} 位：證件或電話過長。Person {$n}: IC or phone is too long.";
            }
            $people[] = ['id' => (int) ($ids[$i] ?? 0), 'name' => $name,
                         'ic' => $ic !== '' ? $ic : '—', 'contact' => $contact !== '' ? $contact : '—'];
        }
        if (!$people) {
            $errors[] = '至少要保留一位參加者；要取消整筆報名，請把狀態改成「已取消」。'
                      . 'Keep at least one person. To cancel the whole registration, set its status to Cancelled.';
        }

        if ($errors) {
            $_SESSION['record_errors'] = $errors;
            $this->redirect('/admin/registrations/edit?id=' . $groupId);
        }

        try {
            $rsvp->updateGroup($groupId, (string) ($_POST['status'] ?? 'pending'), $people);
        } catch (Exception $e) {
            $_SESSION['record_errors'] = ['儲存失敗，請再試一次。Save failed, please try again.'];
            $this->redirect('/admin/registrations/edit?id=' . $groupId);
        }

        $this->flash('success', '已儲存 Saved', "{$group['ref_code']} 已更新。{$group['ref_code']} has been updated.");
        $this->redirect('/admin/registrations/edit?id=' . $groupId);
    }

    /** POST /admin/registrations/status — quick confirm / cancel from the list */
    public function registrationStatus(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $rsvp    = new Rsvp();
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $rsvp->setStatus($groupId, (string) ($_POST['status'] ?? ''));
        $this->redirect($this->returnPath('/admin/registrations?event=' . (int) $rsvp->eventIdOf($groupId)));
    }

    // ------------------------------------------------------------------
    // Donations
    // ------------------------------------------------------------------

    /** GET /admin/donations?event=&q=&status=&source=&page= */
    public function donations(): void
    {
        $this->requireAdmin();
        [$event, $allEvents] = $this->pickEvent();
        $filters  = $this->filters(['status', 'source']);
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $donation = new Donation();
        $total    = $donation->countSearch((int) $event['id'], $filters);

        $this->view('admin/donations', [
            'pageTitle'    => '布施紀錄 Donations',
            'nav'          => 'donations',
            'event'        => $event,
            'allEvents'    => $allEvents,
            'eventBarPath' => '/admin/donations',
            'filters'      => $filters,
            'rows'         => $donation->search((int) $event['id'], $filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'total'        => $total,
            'page'         => $page,
            'pages'        => max(1, (int) ceil($total / self::PER_PAGE)),
            'sum'          => $donation->totalAmount((int) $event['id']),
            'paid'         => $donation->totalPaid((int) $event['id']),
            'flash'        => $this->takeFlash(),
        ]);
    }

    /** GET /admin/donations/edit?id= */
    public function editDonation(): void
    {
        $this->requireAdmin();
        $donation = (new Donation())->find((int) ($_GET['id'] ?? 0));
        if ($donation === null) {
            $this->flash('error', '找不到 Not found', '找不到這筆布施。This donation does not exist.');
            $this->redirect('/admin/donations');
        }

        $this->view('admin/donation_edit', [
            'pageTitle' => '編輯布施 Edit Donation',
            'nav'       => 'donations',
            'd'         => $donation,
            'event'     => (new Event())->find((int) $donation['event_id']),
            'errors'    => $this->takeErrors(),
            'flash'     => $this->takeFlash(),
        ]);
    }

    /** POST /admin/donations/save */
    public function saveDonation(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $model = new Donation();
        $id    = (int) ($_POST['donation_id'] ?? 0);
        $row   = $model->find($id);
        if ($row === null) {
            $this->redirect('/admin/donations');
        }
        $event = (new Event())->find((int) $row['event_id']);

        $str    = static fn(string $k): string => is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '';
        $fields = [
            'name'        => $str('name'),
            'contact'     => $str('contact'),
            'seats'       => (int) $str('seats'),
            'free_amount' => round((float) $str('free_amount'), 2),
            'status'      => $str('status'),
            'notes'       => $str('notes') !== '' ? mb_substr($str('notes'), 0, 255) : null,
        ];

        $errors = Donation::validateParts($fields['seats'], $fields['free_amount']);
        if ($fields['name'] === '' || mb_strlen($fields['name']) > 100) {
            $errors[] = '請填寫姓名。Name is required.';
        }
        if (mb_strlen($fields['contact']) > 30) {
            $errors[] = '聯絡號碼過長。Contact number is too long.';
        }
        if ($errors) {
            $_SESSION['record_errors'] = $errors;
            $this->redirect('/admin/donations/edit?id=' . $id);
        }
        if ($fields['contact'] === '') {
            $fields['contact'] = '—';
        }

        $model->update($id, $fields, (float) $event['merit_table_price']);
        $this->flash('success', '已儲存 Saved', "{$row['ref_code']} 已更新。{$row['ref_code']} has been updated.");
        $this->redirect('/admin/donations/edit?id=' . $id);
    }

    /** POST /admin/donations/paid — quick "mark paid" from the list */
    public function donationPaid(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $model = new Donation();
        $id    = (int) ($_POST['donation_id'] ?? 0);
        $model->markPaid($id);
        $this->redirect($this->returnPath('/admin/donations?event=' . (int) $model->eventIdOf($id)));
    }

    // ------------------------------------------------------------------

    /** @return array{0:array, 1:array} the requested (or live) event, and all events */
    private function pickEvent(): array
    {
        $model = new Event();
        $id    = (int) ($_GET['event'] ?? 0);
        $event = ($id > 0 ? $model->find($id) : null) ?? $model->active();
        return [$event, $model->all()];
    }

    /** The search box plus whitelisted dropdown filters, as plain strings. */
    private function filters(array $keys): array
    {
        $out = ['q' => is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : ''];
        foreach ($keys as $k) {
            $out[$k] = is_string($_GET[$k] ?? null) ? $_GET[$k] : '';
        }
        return $out;
    }

    /**
     * Where to go after a quick action: the list page the admin was on
     * (with its filters), but only ever a path inside /admin — never an
     * outside address smuggled into the form.
     */
    private function returnPath(string $fallback): string
    {
        $return = is_string($_POST['return'] ?? null) ? $_POST['return'] : '';
        return preg_match('#^/admin/[A-Za-z0-9/_\-]*(\?[A-Za-z0-9=&%_\-.+]*)?$#', $return) ? $return : $fallback;
    }

    private function takeErrors(): array
    {
        $errors = $_SESSION['record_errors'] ?? [];
        unset($_SESSION['record_errors']);
        return $errors;
    }
}
