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
        'site_tagline'      => '🙏 感恩您的參與與支持　｜　Thank you for your kind support',
        'site_logo_path'    => null,
        'site_banner_path'  => null,
        'site_favicon_path' => null,
        // Heading typeface — see HEADING_FONTS
        'heading_font'      => 'brush',
        // Footer
        'footer_org'        => 'PERSATUAN PENGANUT DEWA TAI ZHI KUALA LUMPUR',
        'footer_address'    => '',
        'footer_contact'    => '',
        'footer_note_zh'    => '如有任何疑問，敬請於活動當日親臨櫃台詢問。',
        'footer_note_en'    => 'For enquiries, kindly visit the on-site counter on the event day.',
    ];

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
        'brush'       => ['毛筆（粗）Brush — bold, closest to the banner', 'Yuji Boku',      'Yuji+Boku'],
        'brush_light' => ['毛筆（細）Brush — light',                       'Yuji Syuku',     'Yuji+Syuku'],
        'kai'         => ['楷書 Kai — clean and easy to read',            'LXGW WenKai TC', 'LXGW+WenKai+TC:wght@400;700'],
    ];

    /** The chosen heading font: [CSS family, Google Fonts parameter]. */
    public function headingFont(): array
    {
        $key = $this->site()['heading_font'];
        $f   = self::HEADING_FONTS[$key] ?? self::HEADING_FONTS['brush'];
        return [$f[1], $f[2]];
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
            $out[$r['setting_key']] = $r['setting_value'];
        }
        return self::$cache = $out;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $all = $this->all();
        $value = $all[$key] ?? null;
        return ($value === null || $value === '') ? $default : $value;
    }

    /** Write one setting, creating it if it does not exist. */
    public function set(string $key, ?string $value): void
    {
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
