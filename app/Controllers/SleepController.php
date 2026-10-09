<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Sleep;
use App\Models\Setting;

/**
 * System → 休眠模式 Sleep mode (system admin only). See App\Core\Sleep.
 */
class SleepController extends Controller
{
    private const LIMITS = ['sleep_title_zh' => 80, 'sleep_title_en' => 120, 'sleep_msg_zh' => 600, 'sleep_msg_en' => 900, 'sleep_next' => 160];

    /** GET /system/sleep */
    public function index(): void
    {
        $this->requireSystemAdmin();
        $site = (new Setting())->site();
        $this->view('system/sleep', [
            'pageTitle' => '休眠模式 Sleep mode',
            'nav'       => 'sleep',
            'site'      => $site,
            'on'        => Sleep::isOn($site),
            'text'      => Sleep::text($site),
            'flash'     => $this->takeFlash(),
        ]);
    }

    /** POST /system/sleep — the wording, and (to=on|off) the switch. */
    public function save(): void
    {
        $this->requireSystemAdmin();
        $this->requireCsrf();
        $setting = new Setting();
        foreach ($this->clean($_POST) as $k => $v) {
            $setting->set($k, $v);
        }
        $to = $_POST['to'] ?? '';
        if ($to === 'on' || $to === 'off') {
            $setting->set('sleep_mode', $to === 'on' ? '1' : '0');
            $this->flash('success', $to === 'on' ? '網站已進入休眠 The site is asleep' : '網站已喚醒 The site is awake', $to === 'on'
                ? "訪客現在只會看到休眠頁面。後台照常運作，您登入時仍可查看網站。\nVisitors now see only the sleep page. The admin side works as normal, and you can still view the site while signed in."
                : "網站已恢復正常，訪客可以看到所有頁面。\nThe website is back to normal for everyone.");
        } else {
            $this->flash('success', '已儲存 Saved', "休眠頁面的文字已更新。\nThe sleep page wording is saved.");
        }
        $this->redirect('/system/sleep');
    }

    /** POST /system/sleep/preview — the page with the unsaved wording (for the preview frame). */
    public function preview(): void
    {
        $this->requireSystemAdmin();
        $this->requireCsrf();
        header('Content-Type: text/html; charset=utf-8');
        echo Sleep::html(false, $this->clean($_POST));
        exit;
    }

    /** GET /system/sleep/download — the page as one file, for Cloudflare while the server is off. */
    public function download(): void
    {
        $this->requireSystemAdmin();
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="sleep-page.html"');
        echo Sleep::html(true);
        exit;
    }

    /** @return array<string,string> */
    private function clean(array $in): array
    {
        $out = [];
        foreach (self::LIMITS as $k => $max) {
            if (array_key_exists($k, $in) && is_string($in[$k])) {
                $v = str_replace("\r\n", "\n", trim($in[$k]));
                $out[$k] = mb_substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v), 0, $max);
            }
        }
        return $out;
    }
}
