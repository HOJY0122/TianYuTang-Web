<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\ImageUploader;
use App\Models\AdminUser;
use App\Models\Event;
use App\Models\Setting;
use RuntimeException;

/**
 * SystemController — the system administrator's area.
 *
 * Everything here is either technical or permanent: who has a login,
 * what the site is called, and the QR generator. None of it is needed
 * to run an event day-to-day, which is exactly why it is separated —
 * a volunteer confirming registrations should not be one stray click
 * away from renaming the site or deleting an account.
 *
 * Every action calls requireSystemAdmin() on the server. Hiding menu
 * items is presentation; this is the actual gate.
 */
class SystemController extends Controller
{
    /** GET /system — site settings (identity, images, footer) */
    public function index(): void
    {
        $this->requireSystemAdmin();

        // The one fact about the event worth showing here: which is live.
        $activeEvent = null;
        try {
            $activeEvent = (new Event())->active();
        } catch (\Throwable $e) {
            // No event set up yet. The settings below still work.
        }

        $this->view('system/index', [
            'pageTitle'   => '網站設定 Site Settings',
            'nav'         => 'system',
            'settings'    => (new Setting())->site(),
            'activeEvent' => $activeEvent,
            'flash'       => $this->takeFlash(),
        ]);
    }

    /** GET /system/users — who has a login */
    public function users(): void
    {
        $this->requireSystemAdmin();
        $users = new AdminUser();
        $this->view('system/users', [
            'pageTitle'   => '帳號管理 User Accounts',
            'nav'         => 'users',
            'users'       => $users->all(),
            'systemCount' => $users->countSystemAdmins(),
            'adminCount'  => $users->countAdmins(),
            'flash'       => $this->takeFlash(),
        ]);
    }

    // ------------------------------------------------------------------
    // Site settings
    // ------------------------------------------------------------------

    /** POST /system/settings */
    public function saveSettings(): void
    {
        $this->requireSystemAdmin();

        // An oversized banner empties $_POST entirely, which would make
        // the CSRF check below fire and send the system admin hunting a
        // "form expired" bug that is really a file-size limit.
        if (empty($_POST) && empty($_FILES) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->flash('error', '圖片太大',
                '上傳的圖片超過伺服器限制（' . ini_get('post_max_size') . '）。請先縮小再試。');
            $this->redirect('/system');
        }

        $this->requireCsrf();

        $setting = new Setting();

        // Text settings: every key the pages use, with a length limit each.
        $limits = [
            'site_name' => 80, 'site_name_en' => 120, 'site_tagline' => 255,
            'footer_org' => 150, 'footer_address' => 255, 'footer_contact' => 150,
            'footer_note_zh' => 500, 'footer_note_en' => 500,
        ];
        $values = [];
        foreach ($limits as $key => $max) {
            $values[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
            if (mb_strlen($values[$key]) > $max) {
                $this->flash('error', '無法儲存 Not saved', "內容過長（最多 {$max} 字）。Text too long (max {$max}).");
                $this->redirect('/system');
            }
        }
        if ($values['site_name'] === '') {
            $this->flash('error', '無法儲存 Not saved', '網站名稱不可空白。The site name cannot be empty.');
            $this->redirect('/system');
        }
        foreach ($values as $key => $value) {
            $setting->set($key, $value);
        }
        // Heading font: only one of the known choices can be stored.
        $font = is_string($_POST['heading_font'] ?? null) ? $_POST['heading_font'] : '';
        if (isset(Setting::HEADING_FONTS[$font])) {
            $setting->set('heading_font', $font);
        }

        // Images are done after the text has been saved, so a rejected
        // upload never discards a perfectly good name change.
        $imageNotes = [];
        foreach ([
            ['logo',        'site_logo_path',    'logos',    400,  '網站標誌 Logo'],
            ['hero_banner', 'site_banner_path',  'banners',  1920, '首頁橫幅 Banner'],
            ['favicon',     'site_favicon_path', 'favicons', 180,  '網站小圖示 Favicon'],
        ] as [$input, $key, $subdir, $maxWidth, $label]) {
            try {
                $note = $this->storeBrandingImage($setting, $input, $key, $subdir, $maxWidth, $label);
                if ($note !== null) {
                    $imageNotes[] = $note;
                }
            } catch (RuntimeException $e) {
                $this->flash('error', $label . '上傳失敗',
                    $e->getMessage() . '　（其他設定已儲存。）');
                $this->redirect('/system');
            }
        }

        $this->flash(
            'success',
            '已儲存',
            "網站設定已更新。Site settings saved." . ($imageNotes ? '　' . implode('　', $imageNotes) : '')
        );
        $this->redirect('/system');
    }

    /**
     * Replace or remove one branding image.
     *
     * Returns a short note for the confirmation message, or null when
     * the field was left untouched — the common case, and the one that
     * must not wipe the stored image.
     */
    private function storeBrandingImage(
        Setting $setting,
        string $inputName,
        string $settingKey,
        string $subdir,
        int $maxWidth,
        string $label
    ): ?string {
        $uploader = new ImageUploader($subdir);
        $current  = $setting->get($settingKey);
        $file     = $_FILES[$inputName] ?? null;
        $remove   = !empty($_POST['remove_' . $inputName]);

        if (!ImageUploader::wasProvided($file)) {
            if (!$remove) {
                return null;                      // untouched — keep it
            }
            $setting->set($settingKey, null);
            $this->deleteUnreferenced($uploader, $current);
            return $label . '已移除。';
        }

        // The new file is written first. Only once it is safely on disk
        // is the old one removed, so a failure mid-way can never leave
        // the site with neither image.
        $newPath = $uploader->store($file, $maxWidth);
        $setting->set($settingKey, $newPath);

        if ($current && $current !== $newPath) {
            $this->deleteUnreferenced($uploader, $current);
        }

        return $label . '已更新。';
    }

    /**
     * Delete an old branding file, but only if nothing else — no event and
     * no other site setting — still points at it. Migration 006 copied
     * these paths out of the events table, so the very same file may
     * still be an old event's stored banner.
     */
    private function deleteUnreferenced(ImageUploader $uploader, ?string $path): void
    {
        if (empty($path)) {
            return;
        }
        if ((new Event())->countOtherEventsUsingImage($path, 0) > 0) {
            return;
        }
        // …nor another site setting. The same picture is often used as
        // both logo and favicon; replacing the logo must not delete the
        // file the favicon still points at. (The setting being changed
        // has already been saved with its new value, so it won't match.)
        if (in_array($path, (new Setting())->all(), true)) {
            return;
        }
        $uploader->delete($path);
    }

    // ------------------------------------------------------------------
    // User accounts
    // ------------------------------------------------------------------

    /** POST /system/users/create */
    public function createUser(): void
    {
        $this->requireSystemAdmin();
        $this->requireCsrf();

        $users = new AdminUser();

        $username    = trim((string) ($_POST['username'] ?? ''));
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $password    = (string) ($_POST['password'] ?? '');
        $confirm     = (string) ($_POST['password_confirm'] ?? '');
        $role        = (string) ($_POST['role'] ?? AdminUser::ROLE_ADMIN);

        $errors = array_merge(
            AdminUser::validateUsername($username),
            AdminUser::validatePassword($password, $confirm)
        );

        if (!$errors && $users->findByUsername($username) !== null) {
            $errors[] = '這個帳號已經存在，請換一個。';
        }

        if ($errors) {
            $this->flash('error', '無法建立帳號', implode(' ', $errors));
            $this->redirect('/system/users');
        }

        $users->create($username, $password, $role, $displayName !== '' ? $displayName : null);

        $this->flash(
            'success',
            '帳號已建立',
            "{$username} 已建立（" . ($role === AdminUser::ROLE_SYSTEM ? '系統管理員' : '管理員') . '）。'
            . '請將密碼親自交給對方，不要用訊息傳送。'
        );
        $this->redirect('/system/users');
    }

    /** POST /system/users/role */
    public function changeRole(): void
    {
        $this->requireSystemAdmin();
        $this->requireCsrf();

        $users = new AdminUser();
        $id    = (int) ($_POST['user_id'] ?? 0);
        $role  = (string) ($_POST['role'] ?? AdminUser::ROLE_ADMIN);
        $user  = $users->find($id);

        if ($user === null) {
            $this->flash('error', '找不到帳號', '找不到這個帳號。');
            $this->redirect('/system/users');
        }

        // Never leave the system without a system administrator. There
        // would be no way back short of editing the database by hand.
        if ($user['role'] === AdminUser::ROLE_SYSTEM
            && $role !== AdminUser::ROLE_SYSTEM
            && $users->countSystemAdmins() <= 1) {
            $this->flash(
                'error',
                '無法變更',
                '這是最後一位系統管理員。請先指派另一位系統管理員，再變更此帳號。'
            );
            $this->redirect('/system/users');
        }


        $users->updateRole($id, $role);

        // Changing your OWN role has to take effect immediately, or the
        // session would keep claiming a privilege the database no longer
        // grants.
        if ($id === (int) ($_SESSION['admin_id'] ?? 0)) {
            $_SESSION['admin_role'] = $role;
        }

        $this->flash('success', '已更新', "{$user['username']} 的權限已變更。");
        $this->redirect('/system/users');
    }

    /** POST /system/users/password — reset someone else's password. */
    public function resetPassword(): void
    {
        $this->requireSystemAdmin();
        $this->requireCsrf();

        $users    = new AdminUser();
        $id       = (int) ($_POST['user_id'] ?? 0);
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');
        $user     = $users->find($id);

        if ($user === null) {
            $this->flash('error', '找不到帳號', '找不到這個帳號。');
            $this->redirect('/system/users');
        }

        $errors = AdminUser::validatePassword($password, $confirm);
        if ($errors) {
            $this->flash('error', '無法重設密碼', implode(' ', $errors));
            $this->redirect('/system/users');
        }

        $users->updatePassword($id, $password);

        $this->flash('success', '密碼已重設', "{$user['username']} 的密碼已更新。請親自交給對方。");
        $this->redirect('/system/users');
    }

    /** POST /system/users/delete */
    public function deleteUser(): void
    {
        $this->requireSystemAdmin();
        $this->requireCsrf();

        $users = new AdminUser();
        $id    = (int) ($_POST['user_id'] ?? 0);
        $user  = $users->find($id);

        if ($user === null) {
            $this->flash('error', '找不到帳號', '找不到這個帳號。');
            $this->redirect('/system/users');
        }

        // Deleting yourself mid-session leaves a logged-in browser with
        // an account that no longer exists.
        if ($id === (int) ($_SESSION['admin_id'] ?? 0)) {
            $this->flash('error', '無法刪除', '不能刪除自己目前登入的帳號。');
            $this->redirect('/system/users');
        }

        if ($user['role'] === AdminUser::ROLE_SYSTEM && $users->countSystemAdmins() <= 1) {
            $this->flash('error', '無法刪除', '這是最後一位系統管理員，無法刪除。');
            $this->redirect('/system/users');
        }


        $users->delete($id);

        $this->flash('success', '帳號已刪除', "{$user['username']} 已移除。");
        $this->redirect('/system/users');
    }

    // ------------------------------------------------------------------
    // Own password — available to EVERY signed-in user, not just system
    // admins. Nobody should have to ask someone else to change their own
    // password; that is how passwords end up shared over WhatsApp.
    // ------------------------------------------------------------------

    /** GET /account/password — both roles */
    public function passwordForm(): void
    {
        $this->requireLogin(true);
        $this->view('system/password', [
            'flash'    => $this->takeFlash(),
            'homePath' => $this->homePath(),
            'forced'   => !empty($_SESSION['must_change_password']),
        ]);
    }

    /** POST /account/password — both roles */
    public function changeOwnPassword(): void
    {
        $this->requireLogin(true);
        $this->requireCsrf();

        $users   = new AdminUser();
        $id      = (int) ($_SESSION['admin_id'] ?? 0);
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        // Prove they are who the session says before letting them change
        // the credential — an unattended logged-in browser should not be
        // enough to lock the real owner out.
        if ($users->verify((string) ($_SESSION['admin_username'] ?? ''), $current) === null) {
            $this->flash('error', '密碼錯誤', '目前的密碼不正確。');
            $this->redirect('/account/password');
        }

        $errors = AdminUser::validatePassword($new, $confirm);
        if ($errors) {
            $this->flash('error', '無法變更密碼', implode(' ', $errors));
            $this->redirect('/account/password');
        }

        $users->updatePassword($id, $new);
        session_regenerate_id(true);
        unset($_SESSION['must_change_password']);   // released from the forced change

        $this->flash('success', '密碼已變更', '您的密碼已更新。');
        $this->redirect($this->homePath());
    }

    // ------------------------------------------------------------------
    // QR generator
    // ------------------------------------------------------------------

    /** GET /system/qr */
    public function qrGenerator(): void
    {
        $this->requireSystemAdmin();

        $eventModel = new Event();
        $event      = $eventModel->active();

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $this->view('system/qr', [
            'event'    => $event,
            'siteUrl'  => $scheme . '://' . $host . (BASE_URL ?: '') . '/',
            'settings' => (new Setting())->all(),
        ]);
    }
}
