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
            $this->redirect('/admin/dashboard');
        }
        $this->view('admin/login', ['error' => $this->takeLoginError()]);
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
            $_SESSION['login_error'] = '登入失敗次數過多，請 ' . LoginAttempt::WINDOW_MINUTES . ' 分鐘後再試。';
            $this->redirect('/admin/login');
        }

        if ($username === '' || $password === '') {
            $_SESSION['login_error'] = '請輸入帳號與密碼。';
            $this->redirect('/admin/login');
        }

        $user = (new AdminUser())->verify($username, $password);

        if ($user === null) {
            $attempts->recordFailure($ip, $username);
            $_SESSION['login_error'] = '帳號或密碼錯誤。';
            $this->redirect('/admin/login');
        }

        $attempts->clear($ip);

        // Fresh session ID on privilege change — blocks session fixation.
        session_regenerate_id(true);
        $_SESSION['admin_id']       = $user['id'];
        $_SESSION['admin_username'] = $user['username'];

        // Still on the password printed in the README? Then this login
        // unlocks exactly one page: the one that replaces it.
        if (hash_equals(AdminUser::DEFAULT_PASSWORD, $password)) {
            $_SESSION['must_change_password'] = true;
            $this->redirect('/admin/password');
        }

        $this->redirect('/admin/dashboard');
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

    /** GET /admin/password */
    public function passwordForm(): void
    {
        $this->requireAdmin(true);

        $errors = $_SESSION['password_errors'] ?? [];
        unset($_SESSION['password_errors']);

        $this->view('admin/password', [
            'forced' => !empty($_SESSION['must_change_password']),
            'errors' => $errors,
        ]);
    }

    /** POST /admin/password */
    public function changePassword(): void
    {
        $this->requireAdmin(true);
        $this->requireCsrf();

        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $userModel = new AdminUser();
        $userId    = (int) $_SESSION['admin_id'];

        // Asking for the current password again means a borrowed,
        // unlocked laptop is not enough to take the account over.
        $errors = $userModel->checkPassword($userId, $current)
            ? AdminUser::validateNewPassword((string) $_SESSION['admin_username'], $new, $confirm)
            : ['目前密碼不正確。'];

        if ($errors) {
            $_SESSION['password_errors'] = $errors;
            $this->redirect('/admin/password');
        }

        $userModel->updatePassword($userId, $new);
        session_regenerate_id(true);
        unset($_SESSION['must_change_password']);

        $this->flash('success', '密碼已更新', '請記住新密碼，下次登入時使用。');
        $this->redirect('/admin/dashboard');
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
            'event'          => $event,
            'flash'          => $this->takeFlash(),
            'allEvents'      => $eventModel->all(),
            'totalAttendees' => $rsvpModel->totalAttendees($eventId),
            'totalCheckedIn' => $rsvpModel->totalCheckedIn($eventId),
            'totalGroups'    => $rsvpModel->totalGroups($eventId),
            'totalTables'    => $donationModel->totalTables($eventId),
            'totalAmount'    => $donationModel->totalAmount($eventId),
            'totalPaid'      => $donationModel->totalPaid($eventId),
            'bySource'       => $donationModel->totalsBySource($eventId),
            'rsvpGroups'     => $rsvpModel->allGroups($eventId),
            'donations'      => $donationModel->all($eventId),
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
        $donation->markPaid($donationId);
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

        // Remove the test event's uploads too, or they sit on disk
        // forever with nothing pointing at them — but ONLY if no other
        // event still uses the same file. A test copy inherits the
        // original's banner path, so deleting blindly here would strip
        // the live event's banner.
        foreach ([
            'hero_banner_path' => 'banners',
            'favicon_path'     => 'favicons',
        ] as $column => $subdir) {
            $path = $event[$column] ?? null;
            if ($path && $eventModel->countOtherEventsUsingImage($path, $id) === 0) {
                (new ImageUploader($subdir))->delete($path);
            }
        }

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
        $event = $id > 0 ? $eventModel->find($id) : null;

        if ($event === null) {
            $this->flash('error', '找不到活動', '找不到該活動，請從列表重新選擇。');
            $this->redirect('/admin/dashboard');
        }

        $this->view('admin/event_form', [
            'mode'   => 'edit',
            'event'  => $event,
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
            'counter_note'      => trim((string) ($_POST['counter_note'] ?? '')) ?: null,
            'merit_table_price' => (float) ($_POST['merit_table_price'] ?? 0),
            'max_attendees'     => (int) ($_POST['max_attendees'] ?? 0),

            // datetime-local sends "2026-10-15T23:59"; MySQL wants a
            // space. Blank means "no limit", which is NULL, not ''.
            'rsvp_opens_at'      => $this->datetimeOrNull($_POST['rsvp_opens_at']      ?? ''),
            'rsvp_closes_at'     => $this->datetimeOrNull($_POST['rsvp_closes_at']     ?? ''),
            'donation_opens_at'  => $this->datetimeOrNull($_POST['donation_opens_at']  ?? ''),
            'donation_closes_at' => $this->datetimeOrNull($_POST['donation_closes_at'] ?? ''),
        ];

        $errors = Event::validate($fields);

        // Editing? Then the event must still exist — it may have been a
        // test event deleted in another tab.
        $existing = null;
        if (!$isNew) {
            $existing = $eventModel->find($id);
            if ($existing === null) {
                $this->flash('error', '找不到活動', '找不到該活動。');
                $this->redirect('/admin/dashboard');
            }
        }

        // ---- Image uploads ----
        // Two lists, because deleting a file cannot be undone:
        //   $newFiles  written during this request. If the save does not
        //              happen they belong to nothing, so they are removed.
        //   $oldFiles  the images being replaced or removed. Deleted only
        //              AFTER the new values are saved — deleting them any
        //              earlier left the live site pointing at a file that
        //              no longer existed whenever the form was rejected.
        // Skipped entirely when a text field is already wrong, so a
        // rejected form never touches the disk at all.
        $newFiles = [];
        $oldFiles = [];

        if (!$errors) {
            $imageFields = [
                'hero_banner' => ['hero_banner_path', 'banners', 1920],
                'favicon'     => ['favicon_path',     'favicons', 180],
            ];
            try {
                foreach ($imageFields as $input => [$column, $subdir, $maxWidth]) {
                    $result = $this->handleImageField($input, $subdir, $maxWidth, !empty($_POST["remove_{$input}"]));
                    if ($result === false) {
                        continue;   // untouched — keep what is stored
                    }
                    $fields[$column] = $result;
                    if ($result !== null) {
                        $newFiles[] = [$subdir, $result];
                    }
                    $current = $existing[$column] ?? null;
                    if ($current && $current !== $result) {
                        $oldFiles[] = [$subdir, $current];
                    }
                }
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($errors) {
            // e.g. the banner uploaded fine, then the favicon was rejected:
            // nothing will ever point at that banner, so take it back off.
            foreach ($newFiles as [$subdir, $path]) {
                (new ImageUploader($subdir))->delete($path);
            }
            unset($fields['hero_banner_path'], $fields['favicon_path']);

            $_SESSION['event_form_errors'] = $errors;
            $_SESSION['event_form_old']    = $fields;
            $this->redirect($isNew ? '/admin/event/new' : "/admin/event/edit?id={$id}");
        }

        if ($isNew) {
            $id = $eventModel->create($fields);
        } else {
            $eventModel->update($id, $fields);
        }

        // The new values are safely stored — only now drop the old files.
        foreach ($oldFiles as [$subdir, $path]) {
            $this->deleteImageIfUnshared(new ImageUploader($subdir), $path, $id);
        }

        if ($isNew) {
            $this->flash(
                'success',
                '活動已新增',
                '新活動已建立，但尚未公開。確認資料無誤後，請按「設為公開」。'
            );
        } else {
            $this->flash('success', '已儲存', '活動資料已更新，網站已同步顯示。');
        }
        $this->redirect("/admin/dashboard?event={$id}");
    }

    /**
     * Process one optional image field on the event form.
     *
     * Returns the new stored path, null when the image is being removed,
     * or FALSE meaning "leave whatever is already there alone" — which is
     * why the caller checks `!== false` rather than truthiness. Without
     * that distinction, saving the form without touching the file input
     * would wipe an existing banner.
     *
     * This method never deletes anything; saveEvent() decides that once
     * it knows the save went through.
     *
     * @return string|null|false
     */
    private function handleImageField(string $inputName, string $subdir, int $maxWidth, bool $removeRequested)
    {
        $file = $_FILES[$inputName] ?? null;

        if (ImageUploader::wasProvided($file)) {
            return (new ImageUploader($subdir))->store($file, $maxWidth);
        }
        return $removeRequested ? null : false;
    }

    /**
     * Delete an uploaded file only when no other event still points at
     * it. Test copies share their original's image paths, so an
     * unconditional delete here would strip the live event's banner.
     */
    private function deleteImageIfUnshared(ImageUploader $uploader, ?string $path, int $eventId): void
    {
        if (empty($path)) {
            return;
        }
        if ((new Event())->countOtherEventsUsingImage($path, $eventId) === 0) {
            $uploader->delete($path);
        }
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

    private function takeLoginError(): string
    {
        $error = $_SESSION['login_error'] ?? '';
        unset($_SESSION['login_error']);
        return $error;
    }
}
