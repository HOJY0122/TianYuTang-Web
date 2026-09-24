<?php
namespace App\Core;

/**
 * Controller — base class for every controller.
 *
 * Responsibilities kept here (so no controller repeats them):
 *   - rendering a View inside a layout
 *   - redirecting
 *   - flash messages (one-shot messages surviving a redirect)
 *   - CSRF token issue/verify
 */
abstract class Controller
{
    /**
     * Render a view file from app/Views.
     *
     * @param string $view  dot-free path, e.g. 'home/index'
     * @param array  $data  variables made available inside the view
     */
    protected function view(string $view, array $data = []): void
    {
        $file = BASE_PATH . '/app/Views/' . $view . '.php';
        if (!is_file($file)) {
            http_response_code(500);
            die(DEBUG_MODE ? "View not found: {$view}" : '頁面載入失敗。');
        }
        // Make each key available as its own variable inside the view.
        extract($data, EXTR_SKIP);
        require $file;
    }

    /** Redirect to a route path (relative to the app root), then stop. */
    protected function redirect(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }

    /** Store a one-shot message shown on the next page load. */
    protected function flash(string $type, string $title, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'title' => $title, 'message' => $message];
    }

    /** Read and clear the flash message. */
    protected function takeFlash(): ?array
    {
        if (empty($_SESSION['flash'])) {
            return null;
        }
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }

    /**
     * Reject the request unless it carries a valid CSRF token.
     * Protects every POST route from cross-site form submission.
     */
    protected function requireCsrf(): void
    {
        $sent = $_POST['csrf_token'] ?? '';
        $held = $_SESSION['csrf_token'] ?? '';
        // is_string(): a crafted csrf_token[]=x arrives as an ARRAY, and
        // hash_equals() throws a TypeError on anything but a string —
        // a 500 page instead of a clean refusal.
        if (!is_string($sent) || $sent === '' || $held === '' || !hash_equals($held, $sent)) {
            http_response_code(419);
            die('表單已過期，請返回重新提交。 / Form expired, please go back and submit again.');
        }
    }

    // ------------------------------------------------------------------
    // Roles
    //
    // The two roles are separate areas, not two levels of the same one:
    //
    //   admin         /admin/*   runs the event
    //   system_admin  /system/*  runs the site
    //
    // Neither can open the other's pages. A system admin who needs
    // something changed on the event asks an admin, and an admin who
    // needs the banner replaced asks a system admin — which is the point
    // of separating them. Nobody gets stranded: a system admin can reset
    // any admin's password and create accounts of either role.
    //
    // All three checks below run on the SERVER. Hiding a menu item stops
    // an honest person wandering in; it does nothing about someone who
    // types the URL, and the browser's session already knows its role.
    // ------------------------------------------------------------------

    /**
     * Signed in at all, either role. For pages both roles share.
     *
     * Someone who signed in with the published default password is held
     * on the change-password page until they pick a new one. Only that
     * page passes $passwordPage = true — every other page redirects there.
     */
    protected function requireLogin(bool $passwordPage = false): void
    {
        if (empty($_SESSION['admin_id'])) {
            $this->redirect('/admin/login');
        }
        if (!$passwordPage && !empty($_SESSION['must_change_password'])) {
            $this->redirect('/account/password');
        }
    }

    /** Restrict a page to the event administrators' area. */
    protected function requireAdmin(): void
    {
        $this->requireLogin();

        if ($this->isSystemAdmin()) {
            http_response_code(403);
            $this->flash(
                'error',
                '這是管理員頁面',
                '系統管理員帳號負責網站設定，不處理報名與布施資料。請改用管理員帳號登入。'
            );
            $this->redirect('/system');
        }
    }

    /** Restrict a page to the system administrators' area. */
    protected function requireSystemAdmin(): void
    {
        $this->requireLogin();

        if (!$this->isSystemAdmin()) {
            http_response_code(403);
            $this->flash(
                'error',
                '權限不足',
                '此功能僅限系統管理員使用。如需更改網站設定，請聯絡系統管理員。'
            );
            $this->redirect('/admin/dashboard');
        }
    }

    /** Is the signed-in user a system administrator? */
    protected function isSystemAdmin(): bool
    {
        return ($_SESSION['admin_role'] ?? 'admin') === 'system_admin';
    }

    /**
     * Where a signed-in user belongs. Used by the login page and by
     * anything that needs a "back to where I came from" link, so neither
     * role is ever sent to a page it will immediately bounce off.
     */
    protected function homePath(): string
    {
        return $this->isSystemAdmin() ? '/system' : '/admin/dashboard';
    }
}
