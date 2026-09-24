<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\ImageUploader;
use App\Models\AdminUser;
use App\Models\Donation;
use App\Models\Event;
use App\Models\LoginAttempt;
use App\Models\Photo;
use App\Models\Rsvp;
use RuntimeException;

/**
 * AdminController — the committee's private area.
 *
 * Every action except loginForm/login calls requireAdmin() first.
 * The dashboard always works against one event at a time; admin can
 * switch which one with ?event=<id>.
 */
class AdminController extends Controller
{
    /** GET /admin/login */
    public function loginForm(): void
    {
        if (!empty($_SESSION['admin_id'])) {
            $this->redirect($this->homePath());
        }
        $this->view('admin/login', [
            'error' => $this->takeLoginError(),
            'site'  => (new \App\Models\Setting())->site(),
        ]);
    }

    /** POST /admin/login */
    public function login(): void
    {
        $this->requireCsrf();

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $ip       = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $attempts = new LoginAttempt();

        // Checked BEFORE the password, so a locked-out address learns
        // nothing — not even whether a guess would have been right.
        if ($attempts->isLockedOut($ip)) {
            $_SESSION['login_error'] = [
                'message'  => '登入失敗次數過多，請 ' . LoginAttempt::WINDOW_MINUTES . ' 分鐘後再試。'
                            . "\nToo many failed attempts. Please try again in " . LoginAttempt::WINDOW_MINUTES . ' minutes.',
                'attempts' => 0,
            ];
            $this->redirect('/admin/login');
        }

        if ($username === '' || $password === '') {
            $_SESSION['login_error'] = ['message' => "請輸入帳號與密碼。\nPlease enter your username and password."];
            $this->redirect('/admin/login');
        }

        $user = (new AdminUser())->verify($username, $password);

        if ($user === null) {
            $attempts->recordFailure($ip, $username);
            $left = $attempts->remaining($ip);
            $_SESSION['login_error'] = [
                'message'  => $left > 0
                    ? "帳號或密碼錯誤。\nWrong username or password."
                    : '登入失敗次數過多，請 ' . LoginAttempt::WINDOW_MINUTES . ' 分鐘後再試。'
                      . "\nToo many failed attempts. Please try again in " . LoginAttempt::WINDOW_MINUTES . ' minutes.',
                'attempts' => $left,
            ];
            $this->redirect('/admin/login');
        }

        $attempts->clear($ip);

        // Fresh session ID on privilege change — blocks session fixation.
        session_regenerate_id(true);
        $_SESSION['admin_id']       = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_role']     = $user['role'] ?? 'admin';
        $_SESSION['admin_display']  = $user['display_name'] ?: $user['username'];
        (new AdminUser())->recordLogin((int) $user['id']);

        // Still on a password published with the source code? Then this
        // login unlocks exactly one page: the one that replaces it.
        if (AdminUser::isDefaultPassword($password)) {
            $_SESSION['must_change_password'] = true;
            $this->redirect('/account/password');
        }

        // Each role lands in its own area. Sending a system admin to the
        // event dashboard would only bounce them straight back out.
        $this->redirect($this->homePath());
    }

    /**
     * POST /admin/logout
     *
     * POST with a CSRF token, not a plain link: a GET logout can be
     * triggered by any web page (an <img src=".../admin/logout">), which
     * would sign the committee out mid-task.
     */
    public function logout(): void
    {
        $this->requireCsrf();
        $_SESSION = [];
        session_destroy();
        $this->redirect('/admin/login');
    }

    /** GET /admin/dashboard  (optionally ?event=<id>) */
    public function dashboard(): void
    {
        $this->requireAdmin();

        $eventModel    = new Event();
        $rsvpModel     = new Rsvp();
        $donationModel = new Donation();

        // Which event are we looking at? The requested one if it exists,
        // otherwise whichever is active.
        $requestedId = (int) ($_GET['event'] ?? 0);
        $event = $requestedId > 0 ? $eventModel->find($requestedId) : null;
        if ($event === null) {
            $event = $eventModel->active();
        }
        $eventId = (int) $event['id'];

        $this->view('admin/dashboard', [
            'pageTitle'      => '儀表板 Dashboard',
            'nav'            => 'dashboard',
            'event'          => $event,
            'flash'          => $this->takeFlash(),
            'allEvents'      => $eventModel->all(),
            'eventBarPath'   => '/admin/dashboard',
            'totalAttendees' => $rsvpModel->totalAttendees($eventId),
            'totalCheckedIn' => $rsvpModel->totalCheckedIn($eventId),
            'totalGroups'    => $rsvpModel->totalGroups($eventId),
            'byStatus'       => $rsvpModel->countsByStatus($eventId),
            'bySourceRsvp'   => $rsvpModel->countsBySource($eventId),
            'totalTables'    => $donationModel->totalTables($eventId),
            'totalAmount'    => $donationModel->totalAmount($eventId),
            'totalPaid'      => $donationModel->totalPaid($eventId),
            'donationCount'  => $donationModel->countFor($eventId),
            'bySource'       => $donationModel->totalsBySource($eventId),
            'byKind'         => $donationModel->totalsByKind($eventId),
            'dailyPeople'    => $rsvpModel->dailyCounts($eventId, 14),
            'dailyMoney'     => $donationModel->dailyTotals($eventId, 14),
            'recentGroups'   => $rsvpModel->searchGroups($eventId, [], 6),
            'recentDonations'=> $donationModel->search($eventId, [], 6),
        ]);
    }

    /** POST /admin/rsvp/confirm */
    public function confirmRsvp(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $groupId = (int) ($_POST['group_id'] ?? 0);
        (new Rsvp())->setStatus($groupId, 'confirmed');
        $this->backToEventDashboard((new Rsvp())->eventIdOf($groupId));
    }

    /** POST /admin/rsvp/cancel */
    public function cancelRsvp(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $eventId = (new Rsvp())->eventIdOf($groupId);
        (new Rsvp())->setStatus($groupId, 'cancelled');
        $this->backToEventDashboard($eventId);
    }

    /** POST /admin/donation/paid */
    public function markDonationPaid(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $donationId = (int) ($_POST['donation_id'] ?? 0);
        $donation = new Donation();
        $eventId = $donation->eventIdOf($donationId);
        $donation->markPaid($donationId, $_SESSION['admin_username'] ?? null);
        $this->backToEventDashboard($eventId);
    }

    /** POST /admin/event/activate — make an event the one the public site shows. */
    public function activateEvent(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $eventId = (int) ($_POST['event_id'] ?? 0);
        if ($eventId > 0) {
            (new Event())->setActive($eventId);
        }
        $this->backToEventDashboard($eventId);
    }

    // ------------------------------------------------------------------
    // Photo gallery
    // ------------------------------------------------------------------

    /** GET /admin/photos?event=<id> */
    public function photos(): void
    {
        $this->requireAdmin();

        $eventModel = new Event();
        $requestedId = (int) ($_GET['event'] ?? 0);
        $event = $requestedId > 0 ? $eventModel->find($requestedId) : null;
        if ($event === null) {
            $event = $eventModel->active();
        }

        $this->view('admin/photos', [
            'event'     => $event,
            'allEvents' => $eventModel->all(),
            'photos'    => (new Photo())->forEvent((int) $event['id']),
            'flash'     => $this->takeFlash(),
            'maxFiles'  => (int) ini_get('max_file_uploads'),
            'postMax'   => ini_get('post_max_size'),
        ]);
    }

    /** POST /admin/photos/upload */
    public function uploadPhotos(): void
    {
        $this->requireAdmin();

        // A POST that blows past post_max_size arrives with BOTH $_POST
        // and $_FILES empty — including the CSRF token, so the usual
        // check would report "form expired" and send the committee
        // hunting for the wrong problem. Catch it before requireCsrf().
        if (empty($_POST) && empty($_FILES) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->flash(
                'error',
                '檔案太大',
                '這批相片的總大小超過伺服器限制（' . ini_get('post_max_size')
                . '）。請分次上傳，或先將相片壓縮。'
            );
            $this->redirect('/admin/photos');
        }

        $this->requireCsrf();

        $eventId = (int) ($_POST['event_id'] ?? 0);
        $event   = (new Event())->find($eventId);
        if ($event === null) {
            $this->flash('error', '找不到活動', '請先選擇一個活動再上傳相片。');
            $this->redirect('/admin/photos');
        }

        $files = ImageUploader::normaliseMultiple($_FILES['photos'] ?? null);
        if (!$files) {
            $this->flash('error', '未選擇相片', '請選擇至少一張相片再上傳。');
            $this->redirect("/admin/photos?event={$eventId}");
        }

        $uploader  = new ImageUploader('photos');
        $photo     = new Photo();
        $succeeded = 0;
        $failures  = [];

        // Each photo is handled on its own: one bad file in a batch of
        // twenty should not throw away the nineteen that were fine.
        foreach ($files as $i => $file) {
            try {
                $stored = $uploader->storeWithThumbnail($file);
                $photo->create($eventId, $stored['path'], $stored['thumb']);
                $succeeded++;
            } catch (RuntimeException $e) {
                $name = $file['name'] !== '' ? $file['name'] : ('第 ' . ($i + 1) . ' 張');
                $failures[] = $name . '：' . $e->getMessage();
            }
        }

        if ($succeeded > 0 && !$failures) {
            $this->flash('success', '上傳完成', "已成功上傳 {$succeeded} 張相片。");
        } elseif ($succeeded > 0) {
            $this->flash(
                'error',
                '部分相片未上傳',
                "成功 {$succeeded} 張。未成功：" . implode('；', array_slice($failures, 0, 3))
                . (count($failures) > 3 ? ' 等' : '')
            );
        } else {
            $this->flash('error', '上傳失敗', implode('；', array_slice($failures, 0, 3)));
        }

        $this->redirect("/admin/photos?event={$eventId}");
    }

    /** POST /admin/photos/caption */
    public function updateCaption(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $photoModel = new Photo();
        $id = (int) ($_POST['photo_id'] ?? 0);
        $photo = $photoModel->find($id);

        if ($photo !== null) {
            $caption = trim((string) ($_POST['caption'] ?? ''));
            $photoModel->setCaption($id, $caption === '' ? null : mb_substr($caption, 0, 255));
        }

        $this->redirect('/admin/photos?event=' . (int) ($photo['event_id'] ?? 0));
    }

    /** POST /admin/photos/move */
    public function movePhoto(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $photoModel = new Photo();
        $id = (int) ($_POST['photo_id'] ?? 0);
        $photo = $photoModel->find($id);

        if ($photo !== null) {
            $direction = ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down';
            $photoModel->move($id, $direction);
        }

        $this->redirect('/admin/photos?event=' . (int) ($photo['event_id'] ?? 0));
    }

    /** POST /admin/photos/reorder — event_id + ids[] in order, from drag and drop. Answers JSON. */
    public function reorderPhotos(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $ids = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
        (new Photo())->reorder((int) ($_POST['event_id'] ?? 0), array_slice($ids, 0, 2000));
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    /** POST /admin/photos/delete */
    public function deletePhoto(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $photoModel = new Photo();
        $id = (int) ($_POST['photo_id'] ?? 0);
        $photo = $photoModel->find($id);

        if ($photo === null) {
            $this->redirect('/admin/photos');
        }

        $eventId  = (int) $photo['event_id'];
        $uploader = new ImageUploader('photos');

        // Row first, then files. If file deletion fails the gallery is
        // already correct; an orphaned file is untidy, an orphaned row
        // shows a broken image to every visitor.
        $photoModel->delete($id);
        $uploader->delete($photo['file_path']);
        $uploader->delete($photo['thumb_path']);

        $this->flash('success', '已刪除', '相片已從相簿移除。');
        $this->redirect("/admin/photos?event={$eventId}");
    }

    // ------------------------------------------------------------------
    // Test mode
    // ------------------------------------------------------------------

    /** POST /admin/event/test-copy — make a dry-run copy of an event. */
    public function createTestCopy(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $sourceId = (int) ($_POST['event_id'] ?? 0);
        if ($sourceId <= 0) {
            $this->redirect('/admin/dashboard');
        }

        $testId = (new Event())->createTestCopy($sourceId);
        $this->flash(
            'success',
            '測試活動已建立',
            '這是一份測試副本，資料不會計入正式統計。測試完成後可直接刪除。'
            . '請記得按「設為公開」切換到測試活動，才能在前台試用。'
        );
        $this->redirect("/admin/dashboard?event={$testId}");
    }

    /** POST /admin/event/test-delete — remove a test event and its data. */
    public function deleteTestEvent(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $eventModel = new Event();
        $id = (int) ($_POST['event_id'] ?? 0);
        $event = $id > 0 ? $eventModel->find($id) : null;

        if ($event === null || !$event['is_test']) {
            $this->flash('error', '無法刪除', '只有測試活動可以刪除，正式活動的紀錄必須保留。');
            $this->redirect('/admin/dashboard');
        }

        // If the test event is the live one, hand the site back to a real
        // event first — otherwise deleting it leaves nothing active.
        if ($event['is_active']) {
            $real = $eventModel->allReal();
            if ($real) {
                $eventModel->setActive((int) $real[0]['id']);
            }
        }

        // The banner and favicon columns are left alone on purpose. They
        // are historical values now — branding lives in site settings —
        // and a test copy inherits the original's paths, so deleting the
        // files here could strip the banner the live site is still
        // showing. An unused file on disk is a far smaller problem than
        // a blank homepage on the morning of the event.

        // Photo ROWS cascade with the event, but the files on disk do
        // not — collect their paths before the rows disappear.
        $photoUploader = new ImageUploader('photos');
        foreach ((new Photo())->forEvent($id) as $photo) {
            $photoUploader->delete($photo['file_path']);
            $photoUploader->delete($photo['thumb_path']);
        }

        $eventModel->deleteTestEvent($id);
        $this->flash('success', '測試資料已清除', '測試活動與其所有報名、布施與相片紀錄已刪除。');
        $this->redirect('/admin/dashboard');
    }

    // ------------------------------------------------------------------
    // Event details — editing and creating
    // ------------------------------------------------------------------

    /** GET /admin/event/edit?id=<id> */
    public function editEvent(): void
    {
        $this->requireAdmin();

        $eventModel = new Event();
        $id = (int) ($_GET['id'] ?? 0);
        // No id (the menu link) means "the event the website is showing".
        $event = $id > 0 ? $eventModel->find($id) : $eventModel->active();

        if ($event === null) {
            $this->flash('error', '找不到活動 Not found', '找不到該活動，請從列表重新選擇。This event does not exist.');
            $this->redirect('/admin/dashboard');
        }

        $this->view('admin/event_form', [
            'isSystemAdmin' => $this->isSystemAdmin(),
            'mode'   => 'edit',
            'event'  => $event,
            'allEvents' => $eventModel->all(),
            'errors' => $this->takeFormErrors(),
            'old'    => $this->takeOldEventInput(),
            'flash'  => $this->takeFlash(),
        ]);
    }

    /** GET /admin/event/new */
    public function newEvent(): void
    {
        $this->requireAdmin();

        // Prefill from the current active event so the committee only
        // changes what actually differs year to year.
        $template = (new Event())->active();
        $blank = [
            'id'                => null,
            'name'              => $template['name'],
            'year'              => (int) $template['year'] + 1,
            'year_label'        => '',
            'subtitle'          => $template['subtitle'],
            'location'          => $template['location'],
            'start_date'        => '',
            'end_date'          => '',
            'counter_note'      => '',
            'merit_table_price' => $template['merit_table_price'],
            'max_attendees'     => $template['max_attendees'],
            'is_active'         => 0,
            'is_test'           => 0,
        ];

        $this->view('admin/event_form', [
            'isSystemAdmin' => $this->isSystemAdmin(),
            'mode'   => 'new',
            'event'  => $blank,
            'errors' => $this->takeFormErrors(),
            'old'    => $this->takeOldEventInput(),
            'flash'  => $this->takeFlash(),
        ]);
    }

    /** POST /admin/event/save — handles both create and update. */
    public function saveEvent(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $eventModel = new Event();
        $id = (int) ($_POST['id'] ?? 0);
        $isNew = $id === 0;

        $fields = [
            'name'              => trim((string) ($_POST['name'] ?? '')),
            'year'              => (int) ($_POST['year'] ?? 0),
            'year_label'        => trim((string) ($_POST['year_label'] ?? '')) ?: null,
            'subtitle'          => trim((string) ($_POST['subtitle'] ?? '')) ?: null,
            'location'          => trim((string) ($_POST['location'] ?? '')),
            'start_date'        => (string) ($_POST['start_date'] ?? ''),
            'end_date'          => (string) ($_POST['end_date'] ?? ''),
            'date_text_zh'      => mb_substr(trim((string) ($_POST['date_text_zh'] ?? '')), 0, 255) ?: null,
            'date_text_en'      => mb_substr(trim((string) ($_POST['date_text_en'] ?? '')), 0, 255) ?: null,
            'counter_note'      => trim((string) ($_POST['counter_note'] ?? '')) ?: null,
            'merit_table_price' => (float) ($_POST['merit_table_price'] ?? 0),
            'max_attendees'     => (int) ($_POST['max_attendees'] ?? 0),

            // datetime-local sends "2026-10-15T23:59"; MySQL wants a
            // space. Blank means "no limit", which is NULL, not ''.
            'rsvp_opens_at'      => $this->datetimeOrNull($_POST['rsvp_opens_at']      ?? ''),
            'rsvp_closes_at'     => $this->datetimeOrNull($_POST['rsvp_closes_at']     ?? ''),
            'donation_opens_at'  => $this->datetimeOrNull($_POST['donation_opens_at']  ?? ''),
            'donation_closes_at' => $this->datetimeOrNull($_POST['donation_closes_at'] ?? ''),

            // Home-page content
            'welcome_zh'    => trim((string) ($_POST['welcome_zh'] ?? '')) ?: null,
            'welcome_en'    => trim((string) ($_POST['welcome_en'] ?? '')) ?: null,
            'contact_info'  => trim((string) ($_POST['contact_info'] ?? '')) ?: null,
            'maps_url'      => trim((string) ($_POST['maps_url'] ?? '')) ?: null,
            'waze_url'      => trim((string) ($_POST['waze_url'] ?? '')) ?: null,
            'rsvp_note'     => trim((string) ($_POST['rsvp_note'] ?? '')) ?: null,
            'donation_note' => trim((string) ($_POST['donation_note'] ?? '')) ?: null,

            // Online donation limits — blank means "no limit of our own".
            'seats_min' => $this->numberOrNull($_POST['seats_min'] ?? '', true),
            'seats_max' => $this->numberOrNull($_POST['seats_max'] ?? '', true),
            'free_min'  => $this->numberOrNull($_POST['free_min'] ?? '', false),
            'free_max'  => $this->numberOrNull($_POST['free_max'] ?? '', false),
        ];

        $errors = Event::validate($fields);

        // Editing? Then the event must still exist — it may have been a
        // test event deleted in another tab.
        if (!$isNew) {
            if ($eventModel->find($id) === null) {
                $this->flash('error', '找不到活動', '找不到該活動。');
                $this->redirect('/admin/dashboard');
            }
        }

        // Banner and favicon are NOT handled here any more. They are site
        // settings owned by the system admin (/system), not event fields,
        // so this form cannot change them however the POST is crafted.

        // Waze QR image: stored only once every text field is valid, and
        // the old file removed only after the new value is saved.
        $qrUploader = new ImageUploader('waze');
        $oldQr      = $isNew ? null : ($eventModel->find($id)['waze_qr_path'] ?? null);
        if (!$errors && ImageUploader::wasProvided($_FILES['waze_qr'] ?? null)) {
            try {
                $fields['waze_qr_path'] = $qrUploader->store($_FILES['waze_qr'], 600);
            } catch (RuntimeException $e) {
                $errors[] = 'Waze QR：' . $e->getMessage();
            }
        } elseif (!$errors && !empty($_POST['remove_waze_qr'])) {
            $fields['waze_qr_path'] = null;
        }

        if ($errors) {
            $_SESSION['event_form_errors'] = $errors;
            $_SESSION['event_form_old']    = $fields;
            $this->redirect($isNew ? '/admin/event/new' : "/admin/event/edit?id={$id}");
        }

        if ($isNew) {
            $id = $eventModel->create($fields);
            $this->flash(
                'success',
                '活動已新增 Event created',
                "新活動已建立，但尚未公開。確認資料無誤後，請按「設為公開」。\nThe new event is not live yet — press Make live when it is ready."
            );
        } else {
            $eventModel->update($id, $fields);
            // A test copy shares the original's QR file — only delete it
            // when no other event still points at it.
            if ($oldQr && array_key_exists('waze_qr_path', $fields) && $fields['waze_qr_path'] !== $oldQr
                && $eventModel->countOtherEventsUsingImage($oldQr, $id) === 0) {
                $qrUploader->delete($oldQr);
            }
            $this->flash('success', '已儲存 Saved', "活動資料已更新，網站已同步顯示。\nThe event is updated on the website.");
            $this->redirect("/admin/event/edit?id={$id}");
        }
        $this->redirect("/admin/dashboard?event={$id}");
    }

    /** A blank box becomes NULL; anything else a whole number or 2-decimal amount. */
    private function numberOrNull($value, bool $whole)
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }
        return $whole ? (int) $value : round((float) $value, 2);
    }

    /**
     * Normalise a datetime-local value for storage.
     * Blank becomes NULL ("no limit"), and the browser's "T" separator
     * becomes the space MySQL expects. Seconds are added if missing.
     */
    private function datetimeOrNull(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $value = str_replace('T', ' ', $value);
        // "2026-10-15 23:59" → "2026-10-15 23:59:00"
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            $value .= ':00';
        }
        return $value;
    }

    /** @return string[] */
    private function takeFormErrors(): array
    {
        $errors = $_SESSION['event_form_errors'] ?? [];
        unset($_SESSION['event_form_errors']);
        return $errors;
    }

    private function takeOldEventInput(): array
    {
        $old = $_SESSION['event_form_old'] ?? [];
        unset($_SESSION['event_form_old']);
        return $old;
    }

    /**
     * Return to the dashboard still showing the event that was being
     * worked on, rather than snapping back to the active one.
     */
    private function backToEventDashboard(?int $eventId): void
    {
        $this->redirect($eventId ? "/admin/dashboard?event={$eventId}" : '/admin/dashboard');
    }

    /** @return array{message:string, attempts?:int}|null */
    private function takeLoginError(): ?array
    {
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);
        return is_array($error) ? $error : null;
    }
}
