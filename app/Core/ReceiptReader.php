<?php
namespace App\Core;

use App\Models\Setting;
use RuntimeException;

/**
 * ReceiptReader — reads a photo of the temple's handwritten receipt with
 * Claude (Anthropic's AI) and returns the fields as plain PHP values.
 *
 * One HTTPS request to the Messages API: the photo as an image block,
 * a short description of the receipt's layout, and a JSON schema the
 * answer must follow (structured outputs), so the reply can always be
 * decoded — no fishing for JSON in free text.
 *
 * Plain cURL rather than the Anthropic PHP SDK on purpose: this project
 * runs on shared hosting without Composer (see XlsxWriter for the same
 * choice). The request is small and the reply is one JSON document.
 *
 * The AI's reading is a DRAFT. Staff always see it next to the photo and
 * correct it before anything is saved.
 */
class ReceiptReader
{
    /** The boxes printed on the receipt, in print order: key => [中文, English]. */
    public const CATEGORIES = [
        'donation' => ['布施', 'Donation'],
        'blessing' => ['祈福', 'Blessing'],
        'lotus'    => ['蓮花燈', 'Lotus lamp'],
        'oil'      => ['添油', 'Oil offering'],
        'dragon'   => ['龍香', 'Dragon incense'],
        'tower'    => ['塔香', 'Tower incense'],
        'gift'     => ['樂捐', 'Contribution'],
        'meal'     => ['施齋', 'Meal offering'],
        'other'    => ['其他', 'Other'],
    ];

    public static function configured(): bool
    {
        return self::apiKey() !== '';
    }

    /**
     * The API key, from the first place that has one:
     *   1. config/config.php  define('ANTHROPIC_API_KEY', '…')
     *   2. the server's ANTHROPIC_API_KEY environment variable
     *   3. 網站設定 Site settings → AI (saved by the system admin)
     * Spaces, quotes and line breaks picked up while copying are removed.
     */
    public static function apiKey(): string
    {
        return self::keySource()[1];
    }

    /** @return array{0:string,1:string} [where it came from, the key] */
    public static function keySource(): array
    {
        $candidates = [
            'config'   => defined('ANTHROPIC_API_KEY') ? (string) constant('ANTHROPIC_API_KEY') : '',
            'env'      => (string) (getenv('ANTHROPIC_API_KEY') ?: ''),
            'settings' => (string) ((new Setting())->all()['anthropic_api_key'] ?? ''),
        ];
        foreach ($candidates as $where => $key) {
            $key = self::tidyKey($key);
            if ($key !== '') {
                return [$where, $key];
            }
        }
        return ['', ''];
    }

    public static function tidyKey(string $key): string
    {
        $key = preg_replace('/^\s*(Bearer\s+|x-api-key:\s*)/i', '', $key);
        return trim($key, " \t\n\r\0\x0B'\"`");
    }

    /** "sk-ant-api03-…wxyz" — enough to recognise a key, useless to anyone who sees it. */
    public static function mask(string $key): string
    {
        return $key === '' ? '' : substr($key, 0, 12) . '…' . substr($key, -4);
    }

    /**
     * Common set-up mistakes, found by looking at config.php itself.
     * @return string[] bilingual hints for the system admin
     */
    public static function setupHints(): array
    {
        $hints = [];
        $file = BASE_PATH . '/config/config.php';
        $text = is_readable($file) ? (string) file_get_contents($file) : '';
        if (preg_match("/getenv\\(\\s*['\"](sk-ant-[^'\"]+)['\"]\\s*\\)/", $text)) {
            $hints[] = "config.php：金鑰被貼在 getenv('…') 裡面了，這樣讀不到。請改成 define('ANTHROPIC_API_KEY', 'sk-ant-…'); 或直接在下方貼上。\n"
                     . "config.php: the key was pasted inside getenv('…'), where it is never read. Use define('ANTHROPIC_API_KEY', 'sk-ant-…'); or paste it below instead.";
        }
        if (preg_match("/define\\(\\s*['\"]sk-ant-/", $text)) {
            $hints[] = "config.php：金鑰被貼在設定名稱的位置。第一組引號要保持 'ANTHROPIC_API_KEY'，金鑰放在第二組引號。\n"
                     . "config.php: the key replaced the setting's NAME. Keep 'ANTHROPIC_API_KEY' in the first quotes and put the key in the second.";
        }
        [$where, $key] = self::keySource();
        if ($key !== '' && !str_starts_with($key, 'sk-ant-')) {
            $hints[] = "目前的金鑰不像 Anthropic 金鑰（應以 sk-ant- 開頭）。\nThe key in use does not look like an Anthropic key (they start with sk-ant-).";
        }
        if (!function_exists('curl_init') && !ini_get('allow_url_fopen')) {
            $hints[] = "伺服器的 PHP 沒有 cURL，也不允許連外網址，無法連線。請在 php.ini 啟用 extension=curl。\n"
                     . "This server's PHP has neither cURL nor allow_url_fopen, so it cannot connect. Enable extension=curl in php.ini.";
        }
        return $hints;
    }

    /**
     * A free check that the key works and can use the model: asks the
     * Models API about the model (no tokens are used, nothing is billed).
     * @return array{ok:bool, message:string}
     */
    public function testConnection(?string $key = null): array
    {
        $key = self::tidyKey($key ?? self::apiKey());
        if ($key === '') {
            return ['ok' => false, 'message' => "還沒有金鑰。\nNo API key yet."];
        }
        $model = self::conf('ANTHROPIC_MODEL', 'claude-opus-5');
        $url   = preg_replace('#/v1/messages$#', '/v1/models/' . rawurlencode($model), self::conf('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1/messages'));
        try {
            $this->request($url, null, $key);
            return ['ok' => true, 'message' => "✓ 連線成功，金鑰可以使用 {$model}。\nConnected — the key works and can use {$model}."];
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** A setting from config.php or the environment, or a default. */
    private static function conf(string $name, string $default): string
    {
        if (defined($name) && (string) constant($name) !== '') {
            return (string) constant($name);
        }
        return getenv($name) ?: $default;
    }

    /**
     * @param string $file absolute path of a JPEG/PNG/GIF/WebP photo
     * @return array{receipt_no:string, date:string, item:string, name:string,
     *               amounts:array<string,float>, other_label:string, payment:string,
     *               total:float, issued_by:string, unsure:string[], notes:string}
     * @throws RuntimeException with a bilingual message staff can act on
     */
    public function read(string $file): array
    {
        if (!self::configured()) {
            throw new RuntimeException("尚未設定 AI 金鑰，請手動輸入。\nAI reading is not set up (no API key) — please type the receipt in.");
        }
        $info = @getimagesize($file);
        if ($info === false || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            throw new RuntimeException("無法讀取這張相片。\nThis photo could not be read.");
        }

        $body = [
            'model'      => self::conf('ANTHROPIC_MODEL', 'claude-opus-5'),
            'max_tokens' => 16000,
            // If Claude Opus 5's safety check ever declines a request, the
            // API retries it on Anthropic's recommended fallback model.
            'fallbacks'  => 'default',
            'system'     => self::INSTRUCTIONS,
            'messages'   => [[
                'role'    => 'user',
                'content' => [
                    ['type' => 'image', 'source' => [
                        'type' => 'base64', 'media_type' => $info['mime'],
                        'data' => base64_encode((string) file_get_contents($file)),
                    ]],
                    ['type' => 'text', 'text' => 'Read this receipt and fill in the fields.'],
                ],
            ]],
            'output_config' => ['format' => ['type' => 'json_schema', 'schema' => self::schema()]],
        ];

        $reply = $this->post($body);

        // Always check why the model stopped before reading its answer.
        $stop = $reply['stop_reason'] ?? '';
        if ($stop === 'refusal') {
            throw new RuntimeException("AI 無法處理這張相片，請手動輸入。\nThe AI declined to read this photo — please type it in.");
        }
        if ($stop === 'max_tokens') {
            throw new RuntimeException("AI 回覆不完整，請再試一次或手動輸入。\nThe AI's answer was cut short — try again or type it in.");
        }
        $text = '';
        foreach ($reply['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        $data = json_decode($text, true);
        if (!is_array($data)) {
            throw new RuntimeException("AI 回覆格式不正確，請再試一次。\nThe AI's answer could not be understood — please try again.");
        }
        return self::clean($data);
    }

    /** POST to the Messages API; returns the decoded reply or throws. */
    private function post(array $body): array
    {
        return $this->request(self::conf('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1/messages'), $body, self::apiKey());
    }

    /**
     * One HTTPS request (POST with $body, or GET when $body is null).
     * If this server cannot check HTTPS certificates — common on Windows
     * XAMPP, whose PHP ships without a certificate list — it tries once
     * more with the list bundled here (app/Core/cacert.pem, Mozilla's).
     */
    private function request(string $url, ?array $body, string $key): array
    {
        $headers = [
            'content-type: application/json',
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
            'anthropic-beta: server-side-fallback-2026-07-01',
        ];
        $json   = $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $bundle = __DIR__ . '/cacert.pem';

        if (function_exists('curl_init')) {
            $send = function (?string $caFile) use ($url, $json, $headers): array {
                $ch = curl_init($url);
                $opt = [
                    CURLOPT_HTTPHEADER     => $headers,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 15,
                    CURLOPT_TIMEOUT        => 180,   // reading a photo can take a minute
                ];
                if ($json !== null) {
                    $opt[CURLOPT_POST] = true;
                    $opt[CURLOPT_POSTFIELDS] = $json;
                }
                if ($caFile !== null) {
                    $opt[CURLOPT_CAINFO] = $caFile;
                }
                curl_setopt_array($ch, $opt);
                $raw = curl_exec($ch);
                $out = [$raw, (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), curl_errno($ch), curl_error($ch)];
                curl_close($ch);
                return $out;
            };
            [$raw, $status, $errno, $netErr] = $send(null);
            // 60 / 77: no usable certificate list on this server.
            if ($raw === false && in_array($errno, [60, 77], true) && is_readable($bundle)) {
                [$raw, $status, $errno, $netErr] = $send($bundle);
            }
        } else {
            $ctx = stream_context_create([
                'http' => ['method' => $json === null ? 'GET' : 'POST', 'header' => implode("\r\n", $headers),
                           'content' => (string) $json, 'timeout' => 180, 'ignore_errors' => true],
                'ssl'  => is_readable($bundle) ? ['cafile' => $bundle] : [],
            ]);
            $raw    = @file_get_contents($url, false, $ctx);
            $status = 0;
            foreach ($http_response_header ?? [] as $h) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                    $status = (int) $m[1];
                }
            }
            $netErr = $raw === false ? (error_get_last()['message'] ?? 'connection failed') : '';
        }

        if ($raw === false || $status === 0) {
            error_log('ReceiptReader network error: ' . $netErr);
            throw new RuntimeException("連不上 AI 服務，請檢查網絡後再試。\nCould not reach the AI service — check the connection and try again."
                . "\n（詳細 Detail: " . mb_substr($netErr, 0, 200) . '）');
        }
        $reply = json_decode((string) $raw, true);
        if ($status !== 200 || !is_array($reply)) {
            $type   = is_array($reply) ? (string) ($reply['error']['type'] ?? '') : '';
            $detail = is_array($reply) ? (string) ($reply['error']['message'] ?? '') : mb_substr((string) $raw, 0, 200);
            error_log("ReceiptReader HTTP {$status} {$type}: " . substr((string) $raw, 0, 500));
            throw new RuntimeException(match (true) {
                $status === 401                  => "AI 金鑰無效（打錯、已刪除或已停用），請系統管理員到「網站設定」檢查。\nThe AI key was refused (mistyped, deleted or disabled) — the system admin can check it in Site settings.",
                $status === 403                  => "這個金鑰沒有使用權限，請檢查 Anthropic 帳戶。\nThis key is not allowed to do this — check the Anthropic account.",
                $status === 404                  => "此金鑰無法使用這個 AI 模型。\nThis key cannot use this AI model.",
                $status === 400 && str_contains($detail, 'credit')
                                                 => "Anthropic 帳戶餘額不足，請先儲值。\nThe Anthropic account is out of credit — please top it up.",
                $status === 429                  => "AI 服務忙碌或已達用量上限，請一分鐘後再試。\nThe AI service is busy or at its limit — please try again in a minute.",
                $status === 413                  => "相片太大，請縮小後再試。\nThe photo is too large — please use a smaller one.",
                $status >= 500                   => "AI 服務暫時無法使用，請稍後再試。\nThe AI service is having trouble — please try again shortly.",
                default                          => "AI 無法處理這個要求（{$status}）。\nThe AI could not process this request ({$status}).",
            } . ($detail !== '' ? "\n（詳細 Detail: " . mb_substr($detail, 0, 200) . '）' : ''));
        }
        return $reply;
    }

    /** Tidy whatever came back into safe, bounded values. */
    private static function clean(array $d): array
    {
        $str = static fn($v, int $max = 150): string => mb_substr(trim(is_scalar($v) ? (string) $v : ''), 0, $max);
        $num = static fn($v): float => max(0, min(9999999, round(is_numeric($v) ? (float) $v : 0, 2)));

        $amounts = [];
        foreach (array_keys(self::CATEGORIES) as $k) {
            $amounts[$k] = $num($d['amounts'][$k] ?? 0);
        }
        $date = $str($d['date'] ?? '', 10);
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = '';
        }
        $payment = in_array($d['payment'] ?? '', ['cash', 'bank'], true) ? $d['payment'] : '';

        return [
            'receipt_no'  => $str($d['receipt_no'] ?? '', 30),
            'date'        => $date,
            'item'        => $str($d['item'] ?? '', 255),
            'name'        => $str($d['name'] ?? ''),
            'amounts'     => $amounts,
            'other_label' => $str($d['other_label'] ?? '', 100),
            'payment'     => $payment,
            'total'       => $num($d['total'] ?? 0),
            'issued_by'   => $str($d['issued_by'] ?? '', 100),
            'unsure'      => array_values(array_filter(array_map(
                static fn($u) => mb_substr(is_scalar($u) ? (string) $u : '', 0, 120),
                is_array($d['unsure'] ?? null) ? $d['unsure'] : []
            ))),
            'notes'       => $str($d['notes'] ?? '', 500),
        ];
    }

    /** The shape Claude's answer must take. Every object closes with additionalProperties:false. */
    private static function schema(): array
    {
        $amounts = [];
        foreach (array_keys(self::CATEGORIES) as $k) {
            $amounts[$k] = ['type' => 'number'];
        }
        $str = ['type' => 'string'];
        return [
            'type' => 'object',
            'properties' => [
                'receipt_no'  => $str,
                'date'        => $str,
                'item'        => $str,
                'name'        => $str,
                'amounts'     => ['type' => 'object', 'properties' => $amounts,
                                  'required' => array_keys($amounts), 'additionalProperties' => false],
                'other_label' => $str,
                'payment'     => ['type' => 'string', 'enum' => ['cash', 'bank', '']],
                'total'       => ['type' => 'number'],
                'issued_by'   => $str,
                'unsure'      => ['type' => 'array', 'items' => $str],
                'notes'       => $str,
            ],
            'required' => ['receipt_no', 'date', 'item', 'name', 'amounts', 'other_label',
                           'payment', 'total', 'issued_by', 'unsure', 'notes'],
            'additionalProperties' => false,
        ];
    }

    private const INSTRUCTIONS = <<<'TXT'
You read photos of a Malaysian Chinese temple's paper receipts (天玉堂 中壇元帥府, Batu Caves) so staff can store them. The printed form has: a red receipt number top right ("No. 26432"); 日期 Date; 項目 (what it was for); 姓名 Name; nine boxes, each with a tick box and an RM amount — 布施 donation, 祈福 blessing, 蓮花燈 lotus, 添油 oil, 龍香 dragon, 塔香 tower, 樂捐 gift, 施齋 meal, 其他 other; tick boxes for Cash and Bank-In; 總數 RM total; and 發據人 Issued By (a signature or name). Everything except the printed labels is handwritten, in Chinese or English.

Staff will check your reading against the photo, so an honest blank is far more useful than a confident guess:
- Copy names and text as written (Chinese characters as Chinese). Leave a field "" and amounts 0 when it is blank or unreadable, and add the field name to "unsure" whenever you are not certain of a value you did fill in.
- Dates are written day first (16/10/26 = 2026-10-16). Return YYYY-MM-DD, or "" if unclear.
- Amounts are Ringgit; return plain numbers (RM 1,200.50 → 1200.5). Put an amount under the box it is written beside. If "其他" is used, put what it was for in other_label.
- payment is "cash" or "bank" from the ticked box, or "" if neither is ticked.
- total is the 總數 figure as written, even if it does not match the boxes; mention a mismatch in notes.
- receipt_no is the printed red number, digits only.
- notes: anything else staff should know (crossed-out figures, a second receipt in the photo, a blurry area), in one short sentence, or "".
TXT;
}
