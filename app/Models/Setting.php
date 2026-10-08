<?php
namespace App\Models;

use App\Core\Model;

/**
 * Setting — site-level values that outlive any single event.
 *
 * The header name is the clear case: the temple's name does not change
 * from year to year, so putting it on an event row would mean retyping
 * it every time a new event is created, with the chance of a typo each
 * time. Branding lives here too — the banner and the favicon belong to
 * the site, and are the system administrator's to change, not something
 * re-uploaded on every new event.
 */
class Setting extends Model
{
    /**
     * Every site-level setting the pages use, with its default. The system
     * admin's configuration page is built from the same keys, so a value
     * can only be shown if it can also be edited.
     */
    public const DEFAULTS = [
        // Identity
        'site_name'         => '天玉堂',
        'site_name_en'      => 'Tian Yu Tang',
        'site_tagline'      => '感恩您的參與與支持　｜　Thank you for your kind support',
        'site_tagline_on'   => '1',          // the switch beside it: '1' shown, '0' hidden
        'site_logo_path'    => null,
        // Home page banner slideshow (pictures: see Banner / site_banners).
        // Heights are % of the page width; 0 = the whole first picture.
        'banner_height_desktop' => '0',
        'banner_height_mobile'  => '0',
        'banner_interval'       => '6',      // seconds per picture
        'banner_effect'         => 'fade',   // fade | slide
        'banner_controls'       => '1',      // arrows and dots
        'site_favicon_path' => null,
        // Heading typeface — see HEADING_FONTS
        'heading_font'      => 'brush',
        // Footer
        'footer_org'        => 'PERSATUAN PENGANUT DEWA TAI ZHI KUALA LUMPUR',
        'footer_address'    => '',
        'footer_contact'    => '',
        'footer_note_zh'    => '如有任何疑問，敬請於活動當日親臨櫃台詢問。',
        'footer_note_en'    => 'For enquiries, kindly visit the on-site counter on the event day.',
        // null = never set: the footer / letterhead works it out for you.
        // Once saved, the text is used exactly as typed — empty hides the line.
        'footer_title'      => null,         // default: the site name
        'footer_copyright'  => null,         // default: "© {year} " + organisation
        // 收據紀錄 Receipts feature: '0' hides it from admins (system admins keep access; data is kept)
        'receipts_enabled'    => '1',
        // UAT test mode (System → UAT): switched by a system admin only
        'uat_mode'            => '0',
        'uat_interval'        => '4',        // seconds per announcement
        'uat_size'            => '100',      // bar size, % (80–160)
        'uat_messages'        => null,       // one per line, "中文 | English"; null = the built-in ones
        'uat_event_id'        => null,       // the test copy used while UAT is on
        'uat_return_event_id' => null,       // the real event to bring back
        // Footer look (Site settings → ③ Footer → 外觀 Look)
        'footer_pad'        => '38',         // px of space above the text; the space below follows
        'footer_size'       => '100',        // text size, %
        'footer_align'      => 'center',     // center | left
        'footer_theme'      => 'red',        // see FOOTER_THEMES
        'pdf_name'          => null,         // default: site name
        'pdf_name_en'       => null,         // default: English name
        'pdf_line1'         => null,         // default: footer organisation
        'pdf_line2'         => null,         // default: footer address
        'pdf_line3'         => null,         // default: footer contact
        'pdf_show_logo'     => '1',
        // One device per account (Site settings): a new sign-in signs the older device out
        'single_device'     => '0',
        // System → 表單與字體 Forms & fonts (see App\Core\FormRules)
        'rsvp_types'        => 'individual', // individual | both | organisation
        'rsvp_org_max'      => '0',          // most people in an organisation group; 0 = the event's limit
        'rsvp_age_on'       => '0',
        'rsvp_age_min'      => '66',
        'rsvp_age_basis'    => 'year',       // year | event
        'rsvp_age_who'      => 'all',        // all | any
        'rsvp_age_other'    => 'allow',      // passports: allow | block
        'donate_amounts'    => '50, 100, 200, 500',
        'counter_amounts'   => '10, 20, 50, 100, 200, 500, 1000',
        'body_font'         => 'noto_sans',  // see FormRules::BODY_FONTS
        'body_size'         => '100',        // %
        'home_albums'       => 'previous',   // previous | latest | recent
        'home_album_photos' => '12',
        // Photo protection on the public site (see js/protect.js, App\Core\Media)
        'protect_photos'    => '1',
        'photo_watermark'   => '',           // text tiled over the full-size viewer; empty = none
    ];

    /**
     * Keys System → Forms & fonts may preview before saving. A system
     * admin's unsaved values (kept in their own session) are laid over the
     * real ones only on pages opened with ?draft=1 in that same signed-in
     * browser — visitors never see them.
     */
    public const DRAFT_KEYS = [
        'rsvp_types', 'rsvp_org_max', 'rsvp_age_on', 'rsvp_age_min', 'rsvp_age_basis', 'rsvp_age_who', 'rsvp_age_other',
        'donate_amounts', 'body_font', 'body_size', 'heading_font', 'home_albums', 'home_album_photos',
        'protect_photos', 'photo_watermark',
    ];

    /** Is this request a system admin's live preview (see DRAFT_KEYS)? */
    public static function isDraftPreview(): bool
    {
        return isset($_GET['draft'])
            && ($_SESSION['admin_role'] ?? '') === 'system_admin'
            && \App\Core\Session::isStaff()
            && is_array($_SESSION['settings_draft'] ?? null);
    }

    /**
     * Heading typefaces the system admin can choose from.
     * key => [label, CSS family, Google Fonts family parameter]
     *
     * All three contain every Traditional character the site uses
     * (壇 帥 寶 誕 …); Chinese brush fonts on Google Fonts are Simplified
     * only, so their headings came out half brush, half plain. Any rare
     * character a font lacks falls back to LXGW WenKai TC.
     */
    public const HEADING_FONTS = [
        'brush'       => ['毛筆（粗）Brush — bold, closest to the banner', 'Yuji Boku',      'Yuji+Boku', 400],
        'brush_light' => ['毛筆（細）Brush — light',                       'Yuji Syuku',     'Yuji+Syuku', 400],
        'kai'         => ['楷書 Kai — clean and easy to read',            'LXGW WenKai TC', 'LXGW+WenKai+TC:wght@400;700', 700],
        'serif'       => ['宋體（粗）Song — bold and formal',             'Noto Serif TC',  'Noto+Serif+TC:wght@900', 900],
        'sung'        => ['書卷宋體 Book Song — traditional',             'Chiron Sung HK', 'Chiron+Sung+HK:wght@800', 800],
    ];

    /** Footer colours: key => [label, background, text, accent (links, heading)]. */
    public const FOOTER_THEMES = [
        'red'   => ['深紅 Deep red',   '#711711', '#fff3c2', '#fff3c2'],
        'brown' => ['深棕 Dark brown', '#3b2417', '#f5e6c8', '#e8c77a'],
        'dark'  => ['墨黑 Ink black',  '#1c1612', '#efe3c7', '#d9b25f'],
        'gold'  => ['金色 Gold',       '#9a7428', '#fffbe9', '#fff1c1'],
        'cream' => ['米色 Cream',      '#f3e6c4', '#4a2c1a', '#9f211b'],
    ];

    /** Footer limits, shared by the settings page and the save check. */
    public const FOOTER_PAD = [8, 96], FOOTER_SIZE = [80, 140];

    /** The chosen heading font: [CSS family, Google Fonts parameter, weight]. */
    public function headingFont(): array
    {
        $key = $this->site()['heading_font'];
        $f   = self::HEADING_FONTS[$key] ?? self::HEADING_FONTS['brush'];
        return [$f[1], $f[2], $f[3]];
    }

    /**
     * Every site setting with defaults filled in — what the layouts use.
     * A value saved as empty text stays empty (the admin cleared it on
     * purpose); only a setting never saved falls back to its default.
     */
    public function site(): array
    {
        $all = $this->all();
        $out = [];
        foreach (self::DEFAULTS as $key => $default) {
            $out[$key] = array_key_exists($key, $all) ? $all[$key] : $default;
        }
        if (self::isDraftPreview()) {
            foreach (array_intersect_key($_SESSION['settings_draft'], array_flip(self::DRAFT_KEYS)) as $key => $value) {
                $out[$key] = is_string($value) ? $value : $out[$key];
            }
        }
        return $out;
    }

    /** Shared per-request cache, cleared whenever a value is written. */
    private static ?array $cache = null;

    /** Everything, as key => value. Cached per request. */
    public function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $rows = $this->fetchAll('SELECT setting_key, setting_value FROM settings');
        $out  = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = in_array($r['setting_key'], self::SECRET_KEYS, true)
                ? \App\Core\Crypto::decrypt($r['setting_value'])
                : $r['setting_value'];
        }
        return self::$cache = $out;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $all = $this->all();
        $value = $all[$key] ?? null;
        return ($value === null || $value === '') ? $default : $value;
    }

    /** What a never-set footer / letterhead line shows, for a given set of site values. */
    public static function fallback(string $key, array $site): string
    {
        return match ($key) {
            'footer_title'     => $site['site_name'],
            'footer_copyright' => '© {year} ' . ($site['footer_org'] !== '' ? $site['footer_org'] : $site['site_name']),
            'pdf_name'         => $site['site_name'],
            'pdf_name_en'      => $site['site_name_en'],
            'pdf_line1'        => $site['footer_org'],
            'pdf_line2'        => $site['footer_address'],
            'pdf_line3'        => $site['footer_contact'],
            default            => '',
        };
    }

    /** The value the site shows: the saved text (even empty), or the fallback if never set. */
    public static function effective(string $key, array $site): string
    {
        return $site[$key] ?? self::fallback($key, $site);
    }

    /** Remove a setting so its default applies again. */
    public function delete(string $key): void
    {
        $this->execute('DELETE FROM settings WHERE setting_key = ?', [$key]);
        self::$cache = null;
    }

    /**
     * Settings stored encrypted (App\Core\Crypto): the AI service keys.
     * A copy of the database alone does not give them away.
     */
    public const SECRET_KEYS = ['anthropic_api_key', 'nvidia_api_key', 'google_api_key'];

    /** Write one setting, creating it if it does not exist. */
    public function set(string $key, ?string $value): void
    {
        if (in_array($key, self::SECRET_KEYS, true)) {
            $value = \App\Core\Crypto::encrypt($value);
        }
        $this->execute(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            [$key, $value]
        );

        // Drop the cache, or anything reading a setting later in this
        // same request would still see the old value — which is exactly
        // what happens when two settings are saved by one form.
        self::$cache = null;
    }
}
