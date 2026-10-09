<?php
namespace App\Core;

use App\Models\Setting;

/**
 * Sleep mode (System → 休眠模式 Sleep mode): between celebrations the
 * public site shows ONE quiet page — the event has ended, see you next
 * year — in Chinese and English, with the wording the system admin sets.
 * Nothing else is shown or accepted: no forms, no photos, no news.
 *
 *   - admin, system and sign-in pages keep working, so the committee can
 *     prepare the next year while visitors see the sleep page
 *   - signed-in staff still see the real site, with a banner saying it is
 *     asleep (so it can be checked before waking it)
 *   - visitors get HTTP 503 + Retry-After: search engines treat that as
 *     "resting, come back later" and do not drop the address
 *   - standalone(): the same page as one self-contained file, to keep it
 *     showing on Cloudflare while the server itself is switched off
 */
final class Sleep
{
    public const DEFAULTS = [
        'sleep_title_zh' => '{site}圓滿結束',
        'sleep_title_en' => 'Our celebration has ended',
        'sleep_msg_zh'   => "感恩各位善信的護持與參與。\n網站現正休息，明年再會！",
        'sleep_msg_en'   => "Thank you for your kind support and participation.\nThe website is resting — see you again next year!",
        'sleep_next'     => '',
    ];

    /** Paths that keep working while asleep (the committee's own pages). */
    private const STAFF_PREFIXES = ['/admin', '/system', '/account'];

    public static function isOn(?array $site = null): bool
    {
        $site ??= (new Setting())->site();
        return ($site['sleep_mode'] ?? '0') === '1';
    }

    /** Should this request get the sleep page instead? */
    public static function blocks(string $path): bool
    {
        if (!self::isOn()) {
            return false;
        }
        foreach (self::STAFF_PREFIXES as $p) {
            if ($path === $p || str_starts_with($path, $p . '/')) {
                return false;
            }
        }
        // Signed-in staff see the real site (with a banner) to check it.
        return !Session::isStaff();
    }

    /** The wording to show: saved text, or the default, with {site} filled in. */
    public static function text(array $site, ?array $draft = null): array
    {
        $out = [];
        foreach (self::DEFAULTS as $k => $default) {
            $v = $draft !== null && array_key_exists($k, $draft) ? (string) $draft[$k] : ($site[$k] ?? null);
            $v = ($v === null || trim((string) $v) === '') && $k !== 'sleep_next' ? $default : (string) $v;
            $out[$k] = strtr($v, ['{site}' => (string) $site['site_name'], '{site_en}' => (string) $site['site_name_en']]);
        }
        return $out;
    }

    /** Send the sleep page (503) and stop. */
    public static function render(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(503);
        header('Retry-After: 86400');
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=utf-8');
        echo self::html(false);
        exit;
    }

    /**
     * The page. $standalone = true: logo inlined, no links back to this
     * server — one file that works anywhere (Cloudflare Pages / Worker).
     */
    public static function html(bool $standalone, ?array $draft = null): string
    {
        $site = (new Setting())->site();
        $t    = self::text($site, $draft);
        $logo = '';
        if (!empty($site['site_logo_path']) && is_file(BASE_PATH . '/public/' . $site['site_logo_path'])) {
            $file = BASE_PATH . '/public/' . $site['site_logo_path'];
            $src  = $standalone
                ? 'data:' . (mime_content_type($file) ?: 'image/png') . ';base64,' . base64_encode((string) file_get_contents($file))
                : BASE_URL . '/' . $site['site_logo_path'];
            $logo = '<img class="logo" src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt="">';
        }
        $e  = static fn(string $s): string => nl2br(htmlspecialchars($s, ENT_QUOTES, 'UTF-8'), false);
        $name = htmlspecialchars((string) $site['site_name'], ENT_QUOTES, 'UTF-8');
        $nameEn = htmlspecialchars((string) $site['site_name_en'], ENT_QUOTES, 'UTF-8');
        $next = trim($t['sleep_next']) !== '' ? '<p class="next">' . $e($t['sleep_next']) . '</p>' : '';
        $fonts = 'https://fonts.googleapis.com/css2?family=LXGW+WenKai+TC:wght@400;700&family=Noto+Sans+TC:wght@400;500;700&family=Noto+Sans:wght@400;600&display=swap';
        return <<<HTML
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{$e($t['sleep_title_zh'])}｜{$name}</title>
<link rel="stylesheet" href="{$fonts}">
<style>
*{box-sizing:border-box}
html,body{height:100%;margin:0}
body{display:grid;place-items:center;padding:24px 18px;background:radial-gradient(ellipse at top,#fff8e6,#f6ead0 60%,#efdfbb);
  color:#3a2a20;font-family:"Noto Sans","Noto Sans TC","Microsoft JhengHei","PingFang TC",sans-serif;text-align:center;line-height:1.7}
main{max-width:34rem;width:100%}
.logo{width:84px;height:84px;object-fit:contain;margin-bottom:10px}
.site{margin:0 0 26px;font-family:"Noto Sans","LXGW WenKai TC",serif;font-weight:700;color:#9f211b;font-size:20px;letter-spacing:.12em}
.site small{display:block;font-family:"Noto Sans",sans-serif;font-weight:600;font-size:12px;letter-spacing:.18em;color:#a07a3a;text-transform:uppercase}
.line{width:64px;height:2px;margin:0 auto 26px;background:linear-gradient(90deg,transparent,#c89432,transparent)}
h1{margin:0;font-family:"Noto Sans","LXGW WenKai TC",serif;font-weight:700;font-size:clamp(26px,6vw,36px);color:#711711;letter-spacing:.06em}
h2{margin:6px 0 22px;font-weight:600;font-size:clamp(16px,3.6vw,19px);color:#7a5a2a}
.zh{margin:0 0 10px;font-size:17px}
.en{margin:0;font-size:15px;color:#6b5c50}
.next{margin:26px auto 0;display:inline-block;padding:8px 18px;border-radius:999px;background:#9f211b;color:#fff8df;font-weight:600;font-size:15px}
</style>
</head>
<body>
<main>
  {$logo}
  <p class="site">{$name}<small>{$nameEn}</small></p>
  <div class="line"></div>
  <h1>{$e($t['sleep_title_zh'])}</h1>
  <h2>{$e($t['sleep_title_en'])}</h2>
  <p class="zh">{$e($t['sleep_msg_zh'])}</p>
  <p class="en">{$e($t['sleep_msg_en'])}</p>
  {$next}
</main>
</body>
</html>
HTML;
    }
}
