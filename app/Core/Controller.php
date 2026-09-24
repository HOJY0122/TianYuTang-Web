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
        if ($sent === '' || $held === '' || !hash_equals($held, $sent)) {
            http_response_code(419);
            die('表單已過期，請返回重新提交。 / Form expired, please go back and submit again.');
        }
    }

    /** Send the visitor to the admin login page unless already signed in. */
    protected function requireAdmin(): void
    {
        if (empty($_SESSION['admin_id'])) {
            $this->redirect('/admin/login');
        }
    }

    /**
     * Restrict a page to system administrators.
     *
     * Checked on the SERVER for every system route, not just by hiding
     * the menu item. Hiding a link stops an honest person wandering in;
     * it does nothing about someone who types the URL, and the session
     * already tells the browser which role it holds.
     */
    protected function requireSystemAdmin(): void
    {
        $this->requireAdmin();

        if (!$this->isSystemAdmin()) {
            http_response_code(403);
            $this->flash(
                'error',
                '權限不足',
                '此功能僅限系統管理員使用。如需存取，請聯絡系統管理員。'
            );
            $this->redirect('/admin/dashboard');
        }
    }

    /** Is the signed-in user a system administrator? */
    protected function isSystemAdmin(): bool
    {
        return ($_SESSION['admin_role'] ?? 'admin') === 'system_admin';
    }
}
