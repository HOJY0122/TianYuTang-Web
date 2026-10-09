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
        $locked   = static fn(int $min): string => "此帳號登入失敗次數過多，已暫停登入，請 {$min} 分鐘後再試，或請系統管理員重設密碼。"
            . "\nToo many wrong passwords — this account is locked. Try again in {$min} minute" . ($min > 1 ? 's' : '')
            . ', or ask a system admin to reset the password.';

        if ($username === '' || $password === '') {
            $_SESSION['login_error'] = ['message' => "請輸入帳號與密碼。\nPlease enter your username and password.", 'username' => $username];
            $this->redirect('/admin/login');
        }

        // A script trying names or passwords by the hundred from one
        // connection is stopped here; a person never gets near this.
        if (!\App\Core\RateLimit::allow('login', 40, LoginAttempt::WINDOW_MINUTES)) {
            $_SESSION['login_error'] = ['message' => '嘗試太頻繁，請 ' . LoginAttempt::WINDOW_MINUTES . ' 分鐘後再試。'
                . "\nToo many tries from this connection. Please wait " . LoginAttempt::WINDOW_MINUTES . ' minutes.'];
            $this->redirect('/admin/login');
        }

        // No such account: say so. Nothing is counted — there is no
        // account whose password could be guessed.
        $account = (new AdminUser())->findByUsername(mb_substr($username, 0, 50));
        if ($account === null || mb_strlen($username) > 50) {
            $_SESSION['login_error'] = ['message' => "找不到此帳號，請檢查帳號名稱。\nNo account with this username — please check it.",
                                        'field' => 'username', 'username' => $username];
            $this->redirect('/admin/login');
        }
        $name = (string) $account['username'];

        // Locked: refused BEFORE the password is checked, so even a
        // correct guess teaches nothing until the lock lifts.
        if ($attempts->isLockedOut($name)) {
            $_SESSION['login_error'] = ['message' => $locked($attempts->minutesLeft($name)), 'attempts' => 0, 'username' => $username];
            $this->redirect('/admin/login');
        }

        $user = (new AdminUser())->verify($name, $password);

        if ($user === null) {
            // Wrong password for a real account: this is what counts.
            $attempts->recordFailure($name, $ip);
            $left = $attempts->remaining($name);
            $_SESSION['login_error'] = [
                'message'  => $left > 0 ? "密碼錯誤。\nWrong password." : $locked($attempts->minutesLeft($name)),
                'attempts' => $left,
                'field'    => 'password',
                'username' => $username,
            ];
            $this->redirect('/admin/login');
        }

        $attempts->clear($name);

        // Fresh session id and form token on sign-in — blocks session
        // fixation — plus the facts every later page checks (Session::guard).
        \App\Core\Session::signIn($user);
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
        \App\Core\Session::signOut();
        $this->redirect('/admin/login');
    }

    /** GET /admin/no-access — signed in, but no function has been given to this account yet. */
    public function noAccess(): void
    {
        $this->requireAdmin();
        if (\App\Core\Access::home() !== '/admin/no-access') {
            $this->redirect(\App\Core\Access::home());
        }
        $this->view('admin/no_access', ['pageTitle' => '沒有可用功能 No functions', 'nav' => '', 'flash' => null]);
    }

    /**
     * GET /admin/control?event=<id> — 控制台 Control panel: which event is
     * live, stop / resume online responses, edit or add events, test
     * copies. Kept off the dashboard, which is for the numbers.
     */
    public function control(): void
    {
        $this->requireAdmin();
        $eventModel = new Event();
        $requested  = (int) ($_GET['event'] ?? 0);
        $event      = ($requested > 0 ? $eventModel->find($requested) : null) ?? $eventModel->active();
        $this->view('admin/control', [
            'pageTitle'    => '控制台 Control panel',
            'nav'          => 'control',
            'event'        => $event,
            'allEvents'    => $eventModel->all(),
            'eventBarPath' => '/admin/control',
            'flash'        => $this->takeFlash(),
        ]);
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
        $this->redirect($eventId ? "/admin/control?event={$eventId}" : '/admin/control');
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
            if ($this->wantsJson()) {
                $this->json(['ok' => false, 'error' => '檔案超過伺服器限制（' . ini_get('post_max_size') . '）。Over the server limit.'], 413);
            }
            $this->flash(
                'error',
                '檔案太大',
                '這批相片的總大小超過伺服器限制（' . ini_get('post_max_size')
                . '）。請分次上傳，或先將相片壓縮。'
            );
            $this->redirect('/admin/photos');
        }

        $this->requireCsrf();
        if ($this->wantsJson()) {
            session_write_close();   // bulk upload: the next photos need not wait for this one
        }

        $eventId = (int) ($_POST['event_id'] ?? 0);
        $event   = (new Event())->find($eventId);
        if ($event === null) {
            if ($this->wantsJson()) {
                $this->json(['ok' => false, 'error' => '找不到活動 Event not found'], 404);
            }
            $this->flash('error', '找不到活動', '請先選擇一個活動再上傳相片。');
            $this->redirect('/admin/photos');
        }

        $files = ImageUploader::normaliseMultiple($_FILES['photos'] ?? null);
        if (!$files) {
            if ($this->wantsJson()) {
                $this->json(['ok' => false, 'error' => '未收到相片 No photo received'], 422);
            }
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

        // The bulk uploader sends a few at a time and keeps its own tally.
        if ($this->wantsJson()) {
            $this->json(['ok' => !$failures, 'saved' => $succeeded, 'failures' => $failures]);
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
            $this->redirect('/admin/control');
        }

        $testId = (new Event())->createTestCopy($sourceId);
        $this->flash(
            'success',
            '測試活動已建立',
            '這是一份測試副本，資料不會計入正式統計。測試完成後可直接刪除。'
            . '請記得按「設為公開」切換到測試活動，才能在前台試用。'
        );
        $this->redirect("/admin/control?event={$testId}");
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
            $this->redirect('/admin/control');
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
        $this->redirect('/admin/control');
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
            $this->redirect('/admin/control');
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
                $this->redirect('/admin/control');
            }
        }

        // Banner and favicon are NOT handled here any more. They are site
        // settings owned by the system admin (/system), not event fields,
        // so this form cannot change them however the POST is crafted.

        // Waze and Google Maps QR images: stored only once every text field
        // is valid, and the old file removed only after the new value is saved.
        $qrUploader = new ImageUploader('waze');
        $current    = $isNew ? [] : ($eventModel->find($id) ?? []);
        $oldQrs     = [];
        foreach (['waze' => 'Waze QR', 'maps' => 'Google Maps QR'] as $qk => $qLabel) {
            $col = $qk . '_qr_path';
            $oldQrs[$col] = $current[$col] ?? null;
            if (!$errors && ImageUploader::wasProvided($_FILES[$qk . '_qr'] ?? null)) {
                try {
                    $fields[$col] = $qrUploader->store($_FILES[$qk . '_qr'], 600);
                } catch (RuntimeException $e) {
                    $errors[] = $qLabel . '：' . $e->getMessage();
                }
            } elseif (!$errors && !empty($_POST['remove_' . $qk . '_qr'])) {
                $fields[$col] = null;
            }
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
            // A test copy shares the original's QR files — only delete one
            // when no other event still points at it.
            foreach ($oldQrs as $col => $oldQr) {
                if ($oldQr && array_key_exists($col, $fields) && $fields[$col] !== $oldQr
                    && $eventModel->countOtherEventsUsingImage($oldQr, $id) === 0) {
                    $qrUploader->delete($oldQr);
                }
            }
            $this->flash('success', '已儲存 Saved', "活動資料已更新，網站已同步顯示。\nThe event is updated on the website.");
            $this->redirect("/admin/event/edit?id={$id}");
        }
        $this->redirect("/admin/control?event={$id}");
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
    /**
     * POST /admin/event/responses — stop or resume online registration /
     * donation by hand, whatever the opening times say (e.g. enough
     * people already registered on paper). Admins and system admins.
     */
    public function toggleResponses(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $id      = (int) ($_POST['event_id'] ?? 0);
        $section = in_array($_POST['section'] ?? '', [Event::SECTION_RSVP, Event::SECTION_DONATION], true) ? $_POST['section'] : null;
        $events  = new Event();
        $event   = $events->find($id);
        if ($event === null || $section === null) {
            $this->redirect('/admin/control');
        }
        $stop = ($_POST['to'] ?? '') === 'stop';
        $events->setStopped($id, $section, $stop);
        $what = $section === Event::SECTION_RSVP ? ['報名', 'registration'] : ['布施', 'donation'];
        $this->flash('success', $stop ? '已停止接受 Stopped' : '已恢復 Resumed', $stop
            ? "線上{$what[0]}已停止接受，網站即時更新。現場{$what[0]}仍可在後台登記。\nOnline {$what[1]} is stopped; the website shows it at once. On-site entries still work here."
            : "線上{$what[0]}已恢復，按設定的開放時間接受。\nOnline {$what[1]} is back on, following the opening times.");
        $back = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $path = (string) parse_url($back, PHP_URL_PATH);
        $query = (string) parse_url($back, PHP_URL_QUERY);
        // Back to the admin page it came from (same site only).
        if ($path !== '' && str_starts_with($path, BASE_URL . '/admin') && parse_url($back, PHP_URL_HOST) === parse_url('http://' . site_host(), PHP_URL_HOST)) {
            $this->redirect(substr($path, strlen(BASE_URL)) . ($query !== '' ? '?' . $query : ''));
        }
        $this->redirect('/admin/control?event=' . $id);
    }

    private function datetimeOrNull(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $value = str_replace('T', ' ', $value);
        // "2026-10-15 23:59" → "2026-10-15 23:59:00"; seconds kept when given.
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            $value .= ':00';
        }
        $value = substr($value, 0, 19);                 // drop any fraction (07:39:00.000)
        $d = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
        return $d && $d->format('Y-m-d H:i:s') === $value ? $value : null;
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

    /** @return array{message:string, attempts?:int, field?:string, username?:string}|null */
    private function takeLoginError(): ?array
    {
        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);
        return is_array($error) ? $error : null;
    }
}
