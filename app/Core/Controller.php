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

    /**
     * Send the visitor to the admin login page unless already signed in.
     *
     * An admin still on the default password is held on the change-
     * password page until they pick a new one. Only that page passes
     * $passwordPage = true — every other admin page redirects there.
     */
    protected function requireAdmin(bool $passwordPage = false): void
    {
        if (empty($_SESSION['admin_id'])) {
            $this->redirect('/admin/login');
        }
        if (!$passwordPage && !empty($_SESSION['must_change_password'])) {
            $this->redirect('/admin/password');
        }
    }
}
