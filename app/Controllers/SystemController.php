<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\AdminUser;
use App\Models\Event;
use App\Models\Setting;

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
    /** GET /system — landing page */
    public function index(): void
    {
        $this->requireSystemAdmin();

        $users = new AdminUser();

        $this->view('system/index', [
            'settings'    => (new Setting())->all(),
            'users'       => $users->all(),
            'systemCount' => $users->countSystemAdmins(),
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
        $this->requireCsrf();

        $setting = new Setting();

        $siteName = trim((string) ($_POST['site_name'] ?? ''));
        $tagline  = trim((string) ($_POST['site_tagline'] ?? ''));

        if ($siteName === '' || mb_strlen($siteName) > 80) {
            $this->flash('error', '無法儲存', '網站名稱不可空白，且不可超過 80 字。');
            $this->redirect('/system');
        }

        $setting->set('site_name', $siteName);
        $setting->set('site_tagline', mb_substr($tagline, 0, 255));

        $this->flash('success', '已儲存', '網站設定已更新。');
        $this->redirect('/system');
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
            $this->redirect('/system');
        }

        $users->create($username, $password, $role, $displayName !== '' ? $displayName : null);

        $this->flash(
            'success',
            '帳號已建立',
            "{$username} 已建立（" . ($role === AdminUser::ROLE_SYSTEM ? '系統管理員' : '管理員') . '）。'
            . '請將密碼親自交給對方，不要用訊息傳送。'
        );
        $this->redirect('/system');
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
            $this->redirect('/system');
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
            $this->redirect('/system');
        }

        $users->updateRole($id, $role);

        // Changing your OWN role has to take effect immediately, or the
        // session would keep claiming a privilege the database no longer
        // grants.
        if ($id === (int) ($_SESSION['admin_id'] ?? 0)) {
            $_SESSION['admin_role'] = $role;
        }

        $this->flash('success', '已更新', "{$user['username']} 的權限已變更。");
        $this->redirect('/system');
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
            $this->redirect('/system');
        }

        $errors = AdminUser::validatePassword($password, $confirm);
        if ($errors) {
            $this->flash('error', '無法重設密碼', implode(' ', $errors));
            $this->redirect('/system');
        }

        $users->updatePassword($id, $password);

        $this->flash('success', '密碼已重設', "{$user['username']} 的密碼已更新。請親自交給對方。");
        $this->redirect('/system');
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
            $this->redirect('/system');
        }

        // Deleting yourself mid-session leaves a logged-in browser with
        // an account that no longer exists.
        if ($id === (int) ($_SESSION['admin_id'] ?? 0)) {
            $this->flash('error', '無法刪除', '不能刪除自己目前登入的帳號。');
            $this->redirect('/system');
        }

        if ($user['role'] === AdminUser::ROLE_SYSTEM && $users->countSystemAdmins() <= 1) {
            $this->flash('error', '無法刪除', '這是最後一位系統管理員，無法刪除。');
            $this->redirect('/system');
        }

        $users->delete($id);

        $this->flash('success', '帳號已刪除', "{$user['username']} 已移除。");
        $this->redirect('/system');
    }

    // ------------------------------------------------------------------
    // Own password — available to EVERY signed-in user, not just system
    // admins. Nobody should have to ask someone else to change their own
    // password; that is how passwords end up shared over WhatsApp.
    // ------------------------------------------------------------------

    /** GET /admin/password */
    public function passwordForm(): void
    {
        $this->requireAdmin();
        $this->view('system/password', ['flash' => $this->takeFlash()]);
    }

    /** POST /admin/password */
    public function changeOwnPassword(): void
    {
        $this->requireAdmin();
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
            $this->redirect('/admin/password');
        }

        $errors = AdminUser::validatePassword($new, $confirm);
        if ($errors) {
            $this->flash('error', '無法變更密碼', implode(' ', $errors));
            $this->redirect('/admin/password');
        }

        $users->updatePassword($id, $new);
        session_regenerate_id(true);

        $this->flash('success', '密碼已變更', '您的密碼已更新。');
        $this->redirect('/admin/dashboard');
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
