<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\FormRules;
use App\Models\Setting;

/**
 * System → 表單與字體 Forms & fonts (system admin only).
 *
 *   Registration  individual / organisation, the organisation group size,
 *                 the age rule worked out from the IC
 *   Donation      the quick-amount buttons (public page and counter)
 *   Fonts         heading and body typefaces, text size
 *   Albums        which year's photos the home page shows
 *
 * While the system admin changes things, the page sends the unsaved
 * values to /system/forms/draft; the preview beside it shows the real
 * public pages with those values laid over (?draft=1 — see
 * Setting::isDraftPreview), so what they see is exactly what visitors
 * will get once they press Save.
 */
class FormsController extends Controller
{
    /** GET /system/forms */
    public function index(): void
    {
        $this->requireSystemAdmin();
        $_SESSION['settings_draft'] = [];             // a fresh visit starts from the saved values
        $this->view('system/forms', [
            'pageTitle' => '表單與字體 Forms & fonts',
            'nav'       => 'forms',
            'site'      => (new Setting())->site(),
            'flash'     => $this->takeFlash(),
        ]);
    }

    /** POST /system/forms/draft — unsaved values for the live preview (JSON reply). */
    public function draft(): void
    {
        $this->requireSystemAdmin();
        $this->requireCsrf();
        $_SESSION['settings_draft'] = $this->clean($_POST, (new Setting())->site());
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
        exit;
    }

    /** POST /system/forms */
    public function save(): void
    {
        $this->requireSystemAdmin();
        $this->requireCsrf();

        if (isset($_POST['donate_amounts']) && FormRules::parseAmounts((string) $_POST['donate_amounts']) === []) {
            $this->flash('error', '無法儲存 Not saved',
                "快速金額至少要有一個 1 至 100,000 的整數，例如：1, 5, 10, 50。\nQuick amounts need at least one whole number from 1 to 100,000, e.g. 1, 5, 10, 50.");
            $this->redirect('/system/forms');
        }

        $setting = new Setting();
        foreach ($this->clean($_POST, $setting->site()) as $key => $value) {
            $setting->set($key, $value);
        }
        unset($_SESSION['settings_draft']);
        $this->flash('success', '已儲存 Saved', "表單與字體設定已更新，網站即時生效。\nForms & fonts saved — the site uses them straight away.");
        $this->redirect('/system/forms');
    }

    /**
     * Every value checked and tidied; anything unknown falls back to the
     * current setting, so a crafted request can only pick listed choices.
     * @return array<string,string>
     */
    private function clean(array $in, array $site): array
    {
        $pick = static fn(string $key, array $allowed): string
            => is_string($in[$key] ?? null) && in_array($in[$key], $allowed, true) ? $in[$key] : (string) $site[$key];
        $num = static fn(string $key, array $range): string
            => is_string($in[$key] ?? null) && is_numeric($in[$key])
                ? (string) max($range[0], min($range[1], (int) $in[$key])) : (string) $site[$key];

        $out = [
            'rsvp_types'        => $pick('rsvp_types', array_keys(FormRules::TYPES)),
            'rsvp_org_max'      => $num('rsvp_org_max', [0, 200]),
            'rsvp_age_on'       => $pick('rsvp_age_on', ['0', '1']),
            'rsvp_age_min'      => $num('rsvp_age_min', FormRules::AGE_RANGE),
            'rsvp_age_basis'    => $pick('rsvp_age_basis', array_keys(FormRules::AGE_BASIS)),
            'rsvp_age_who'      => $pick('rsvp_age_who', array_keys(FormRules::AGE_WHO)),
            'rsvp_age_other'    => $pick('rsvp_age_other', array_keys(FormRules::AGE_OTHER)),
            'body_font'         => $pick('body_font', array_keys(FormRules::BODY_FONTS)),
            'body_size'         => $num('body_size', FormRules::BODY_SIZE),
            'heading_font'      => $pick('heading_font', array_keys(Setting::HEADING_FONTS)),
            'home_albums'       => $pick('home_albums', ['previous', 'latest', 'recent']),
            'home_album_photos' => $num('home_album_photos', [4, 40]),
            'protect_photos'    => $pick('protect_photos', ['0', '1']),
            'protect_albums'    => $pick('protect_albums', ['0', '1']),
            'protect_news'      => $pick('protect_news', ['0', '1']),
            'protect_banner'    => $pick('protect_banner', ['0', '1']),
            'protect_about'     => $pick('protect_about', ['0', '1']),
            'protect_qr'        => $pick('protect_qr', ['0', '1']),
            'protect_keys'      => $pick('protect_keys', ['0', '1']),
            'photo_watermark'   => is_string($in['photo_watermark'] ?? null)
                ? mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $in['photo_watermark'])), 0, 40) : (string) $site['photo_watermark'],
        ];
        // Amounts are stored tidied ("1, 5, 10"); an unusable list keeps the old one.
        foreach (['donate_amounts', 'counter_amounts'] as $key) {
            $list = FormRules::parseAmounts(is_string($in[$key] ?? null) ? $in[$key] : '');
            $out[$key] = $list ? implode(', ', $list) : (string) $site[$key];
        }
        return $out;
    }
}
