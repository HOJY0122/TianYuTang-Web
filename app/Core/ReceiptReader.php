<?php
namespace App\Core;

use App\Models\Setting;
use RuntimeException;

/**
 * ReceiptReader — reads a photo of the temple's handwritten receipt with
 * an AI service and returns the fields as plain PHP values.
 *
 * Two services can be chosen in 網站設定 Site settings → ⑤ AI:
 *
 *  - anthropic: Claude, via the Messages API. The photo goes as an image
 *    block with a JSON schema the answer must follow (structured
 *    outputs), so the reply always decodes.
 *  - nvidia: NVIDIA's hosted models (build.nvidia.com), via their
 *    OpenAI-style chat completions API. Structured output is not
 *    guaranteed there, so the JSON is asked for in the instructions and
 *    picked out of the reply carefully. Open vision models read Chinese
 *    handwriting less reliably than Claude — the draft will need more
 *    checking.
 *
 * Plain cURL rather than an SDK on purpose: this project
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

    /** The services that can read receipts: key => [label, key prefix, where to get a key]. */
    public const PROVIDERS = [
        'anthropic' => ['Anthropic Claude', 'sk-ant-', 'https://console.anthropic.com/settings/keys'],
        'nvidia'    => ['NVIDIA (build.nvidia.com)', 'nvapi-', 'https://build.nvidia.com/'],
        'google'    => ['Google Cloud Vision (OCR)', 'AIza', 'https://console.cloud.google.com/apis/credentials'],
    ];

    /** Vision models on NVIDIA's API that suit this job; the admin may type any other. */
    public const NVIDIA_MODELS = [
        'meta/llama-3.2-90b-vision-instruct',
        'meta/llama-4-maverick-17b-128e-instruct',
        'meta/llama-3.2-11b-vision-instruct',
        'microsoft/phi-4-multimodal-instruct',
    ];

    public static function configured(): bool
    {
        return self::apiKey() !== '';
    }

    /**
     * Why receipt reading is off, in words a system admin can act on:
     * the chosen service has no key — and whether another one does.
     */
    public static function whyOff(): string
    {
        if (self::configured()) {
            return '';
        }
        $chosen = self::PROVIDERS[self::provider()][0];
        $others = [];
        foreach (self::PROVIDERS as $p => [$label]) {
            if ($p !== self::provider() && self::apiKey($p) !== '') {
                $others[] = $label;
            }
        }
        $msg = "目前選用「{$chosen}」，但它沒有已儲存的金鑰。The chosen service, {$chosen}, has no saved key.";
        if ($others) {
            $list = implode('、', $others);
            $msg .= "\n已有金鑰的服務：{$list} — 請在 ⑤ AI 選用它並按「儲存設定」。A key is saved for {$list} — choose it in ⑤ AI and press Save.";
        }
        return $msg;
    }

    /** Which service reads receipts (Site settings → ⑤ AI). */
    public static function provider(): string
    {
        $p = (string) ((new Setting())->all()['ai_provider'] ?? '');
        return isset(self::PROVIDERS[$p]) ? $p : 'anthropic';
    }

    /** The model used for a service. */
    public static function model(?string $provider = null): string
    {
        if (($provider ?? self::provider()) === 'nvidia') {
            $saved = trim((string) ((new Setting())->all()['nvidia_model'] ?? ''));
            return $saved !== '' ? $saved : self::conf('NVIDIA_MODEL', self::NVIDIA_MODELS[0]);
        }
        return self::conf('ANTHROPIC_MODEL', 'claude-opus-5');
    }

    /**
     * The API key for the chosen service, from the first place that has one:
     *   1. config/config.php  define('ANTHROPIC_API_KEY' / 'NVIDIA_API_KEY', '…')
     *   2. the server's environment variable of the same name
     *   3. 網站設定 Site settings → ⑤ AI (saved by the system admin)
     * Spaces, quotes and line breaks picked up while copying are removed.
     */
    public static function apiKey(?string $provider = null): string
    {
        return self::keySource($provider)[1];
    }

    /** @return array{0:string,1:string} [where it came from, the key] */
    public static function keySource(?string $provider = null): array
    {
        $provider = $provider ?? self::provider();
        $name     = ['nvidia' => 'NVIDIA_API_KEY', 'google' => 'GOOGLE_VISION_API_KEY'][$provider] ?? 'ANTHROPIC_API_KEY';
        $candidates = [
            'config'   => defined($name) ? (string) constant($name) : '',
            'env'      => (string) (getenv($name) ?: ''),
            'settings' => (string) ((new Setting())->all()[$provider . '_api_key'] ?? ''),
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
        $provider = self::provider();
        [$label, $prefix] = self::PROVIDERS[$provider];
        [$where, $key] = self::keySource($provider);
        if ($key !== '' && !str_starts_with($key, $prefix)) {
            $hints[] = "目前的金鑰不像 {$label} 金鑰（應以 {$prefix} 開頭）。\nThe key in use does not look like a {$label} key (they start with {$prefix}).";
        }
        if (!function_exists('curl_init') && !ini_get('allow_url_fopen')) {
            $hints[] = "伺服器的 PHP 沒有 cURL，也不允許連外網址，無法連線。請在 php.ini 啟用 extension=curl。\n"
                     . "This server's PHP has neither cURL nor allow_url_fopen, so it cannot connect. Enable extension=curl in php.ini.";
        }
        return $hints;
    }

    /**
     * Check everything a receipt scan needs, in two steps:
     *   1. the key is valid and may use the model (Models API — free)
     *   2. the account can actually run a request: one tiny message
     *      (a few dozen tokens, well under RM 0.01). Step 1 alone passes
     *      even when the account has no credit, which is misleading.
     * @return array{ok:bool, message:string}
     */
    public function testConnection(?string $key = null, ?string $provider = null, ?string $model = null): array
    {
        $provider = $provider ?? self::provider();
        $key      = self::tidyKey($key ?? self::apiKey($provider));
        $model    = $model ?: self::model($provider);
        if ($key === '') {
            return ['ok' => false, 'message' => "還沒有金鑰。\nNo API key yet."];
        }
        if ($provider === 'google') {
            // A blank 1×1 picture: checks the key, that the Vision API is
            // switched on for the project, and billing — within the free tier.
            try {
                $this->request(self::googleUrl(), ['requests' => [[
                    'image'    => ['content' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg=='],
                    'features' => [['type' => 'TEXT_DETECTION']],
                ]]], self::headers('google', $key));
            } catch (RuntimeException $e) {
                return ['ok' => false, 'message' => "Google Vision 測試失敗 Test failed:\n" . $e->getMessage()];
            }
            return ['ok' => true, 'message' => "✓ 一切正常：Google Cloud Vision 可以使用。每月首 1,000 張免費。\n"
                . "All good — Google Cloud Vision works. The first 1,000 scans each month are free."];
        }
        if ($provider === 'nvidia') {
            // NVIDIA has no free "does this key work" call, so one tiny
            // text request checks the key, the model and the account together.
            try {
                $this->request(self::nvidiaUrl(), [
                    'model' => $model, 'max_tokens' => 8, 'temperature' => 0,
                    'messages' => [['role' => 'user', 'content' => 'Reply with the word OK.']],
                ], self::headers('nvidia', $key));
            } catch (RuntimeException $e) {
                return ['ok' => false, 'message' => "NVIDIA 測試失敗 Test failed ({$model}):\n" . $e->getMessage()];
            }
            return ['ok' => true, 'message' => "✓ 一切正常：NVIDIA 金鑰有效，可以使用 {$model}。\n"
                . "All good — the NVIDIA key works with {$model}. You can scan receipts now."];
        }
        $base = self::conf('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1/messages');
        try {
            $this->request(preg_replace('#/v1/messages$#', '/v1/models/' . rawurlencode($model), $base), null, self::headers('anthropic', $key));
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => "① 金鑰檢查失敗 Key check failed:\n" . $e->getMessage()];
        }
        try {
            $reply = $this->request($base, [
                'model'      => $model,
                'max_tokens' => 16,
                'messages'   => [['role' => 'user', 'content' => 'Reply with the word OK.']],
            ], self::headers('anthropic', $key));
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => "✓ 金鑰正確，但無法執行讀取。\nThe key is valid, but the account cannot run requests yet.\n" . $e->getMessage()];
        }
        if (($reply['stop_reason'] ?? '') === 'refusal') {
            return ['ok' => false, 'message' => "金鑰正確，但 AI 拒絕了測試要求，請稍後再試。\nThe key is valid, but the test request was declined — please try again later."];
        }
        return ['ok' => true, 'message' => "✓ 一切正常：金鑰有效、帳戶可以使用 {$model}，可以開始掃描收據。\n"
            . "All good — the key works and the account can use {$model}. You can scan receipts now."];
    }

    private static function googleUrl(): string
    {
        return self::conf('GOOGLE_VISION_URL', 'https://vision.googleapis.com/v1/images:annotate');
    }

    private static function nvidiaUrl(): string
    {
        return self::conf('NVIDIA_API_URL', 'https://integrate.api.nvidia.com/v1/chat/completions');
    }

    /** Request headers for a service. */
    private static function headers(string $provider, string $key): array
    {
        if ($provider === 'nvidia') {
            return ['content-type: application/json', 'accept: application/json', 'authorization: Bearer ' . $key];
        }
        if ($provider === 'google') {
            // In a header rather than ?key= so the key never appears in logged URLs.
            return ['content-type: application/json', 'x-goog-api-key: ' . $key];
        }
        return [
            'content-type: application/json',
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
            'anthropic-beta: server-side-fallback-2026-07-01',
        ];
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
        if (self::provider() === 'nvidia') {
            return $this->readWithNvidia($file);
        }
        if (self::provider() === 'google') {
            return $this->readWithGoogle($file);
        }

        $body = [
            'model'      => self::model('anthropic'),
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
        return $this->request(self::conf('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1/messages'), $body,
            self::headers('anthropic', self::apiKey('anthropic')));
    }

    /**
     * Read the photo with an NVIDIA-hosted vision model. The answer is
     * asked for as JSON in the instructions (not enforced by the API), so
     * the first {...} in the reply is taken and then tidied like Claude's.
     */
    private function readWithNvidia(string $file): array
    {
        $shape = json_encode(self::example(), JSON_UNESCAPED_UNICODE);
        $reply = $this->request(self::nvidiaUrl(), [
            'model'       => self::model('nvidia'),
            'max_tokens'  => 1500,
            'temperature' => 0.1,
            'messages'    => [
                ['role' => 'system', 'content' => self::INSTRUCTIONS
                    . "\n\nReply with ONE JSON object only — no other text, no code fences — shaped exactly like this example:\n" . $shape],
                ['role' => 'user', 'content' => [
                    ['type' => 'text', 'text' => 'Read this receipt and fill in the fields.'],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . self::smallJpegBase64($file)]],
                ]],
            ],
        ], self::headers('nvidia', self::apiKey('nvidia')));

        $choice = $reply['choices'][0] ?? [];
        if (($choice['finish_reason'] ?? '') === 'length') {
            throw new RuntimeException("AI 回覆不完整，請再試一次或手動輸入。\nThe AI's answer was cut short — try again or type it in.");
        }
        $text = (string) ($choice['message']['content'] ?? '');
        $data = self::firstJsonObject($text);
        if ($data === null) {
            error_log('ReceiptReader NVIDIA non-JSON reply: ' . mb_substr($text, 0, 500));
            throw new RuntimeException("AI 回覆格式不正確，請再試一次或換一個模型。\nThe AI's answer could not be understood — try again or choose another model."
                . "\n（詳細 Detail: " . mb_substr(trim($text), 0, 150) . '）');
        }
        return self::clean($data);
    }

    /**
     * Read the photo with Google Cloud Vision (DOCUMENT_TEXT_DETECTION —
     * its handwriting-capable OCR), then let ReceiptOcrParser work out the
     * fields from where each word sits. The full text comes back as 'text'
     * so staff can see everything that was read.
     */
    private function readWithGoogle(string $file): array
    {
        $reply = $this->request(self::googleUrl(), ['requests' => [[
            'image'        => ['content' => base64_encode((string) file_get_contents($file))],
            'features'     => [['type' => 'DOCUMENT_TEXT_DETECTION']],
            'imageContext' => ['languageHints' => ['zh-Hant', 'en']],
        ]]], self::headers('google', self::apiKey('google')));

        $res = $reply['responses'][0] ?? [];
        if (!empty($res['error'])) {
            throw new RuntimeException("Google 無法讀取這張相片。\nGoogle could not read this photo.\n（詳細 Detail: "
                . mb_substr((string) ($res['error']['message'] ?? ''), 0, 200) . '）');
        }
        $words = [];
        foreach ($res['fullTextAnnotation']['pages'] ?? [] as $page) {
            foreach ($page['blocks'] ?? [] as $block) {
                foreach ($block['paragraphs'] ?? [] as $para) {
                    foreach ($para['words'] ?? [] as $word) {
                        $text = implode('', array_map(static fn($sym) => (string) ($sym['text'] ?? ''), $word['symbols'] ?? []));
                        $xs = array_map(static fn($v) => (int) ($v['x'] ?? 0), $word['boundingBox']['vertices'] ?? []);
                        $ys = array_map(static fn($v) => (int) ($v['y'] ?? 0), $word['boundingBox']['vertices'] ?? []);
                        if ($text === '' || !$xs) {
                            continue;
                        }
                        $words[] = ['text' => $text, 'x1' => min($xs), 'x2' => max($xs), 'y1' => min($ys), 'y2' => max($ys)];
                    }
                }
            }
        }
        if (!$words) {
            throw new RuntimeException("相片中找不到文字，請拍清楚一點再試。\nNo text was found in the photo — please retake it more clearly.");
        }
        $parsed = ReceiptOcrParser::parse($words);
        $text   = $parsed['text'];
        $clean  = self::clean($parsed);
        $clean['text'] = mb_substr($text, 0, 4000);
        return $clean;
    }

    /** The first {...} object in a reply, ignoring code fences and chatter around it. */
    private static function firstJsonObject(string $text): ?array
    {
        $text  = preg_replace('/```(?:json)?/i', '', $text);
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        return is_array($data) ? $data : null;
    }

    /**
     * The photo as a JPEG small enough to send inline. NVIDIA's hosted
     * API accepts images inside the request only up to about 180 KB of
     * base64, so the photo is scaled down step by step until it fits
     * (a receipt stays readable at 1000–1400 px).
     */
    private static function smallJpegBase64(string $file): string
    {
        $raw = (string) file_get_contents($file);
        $img = @imagecreatefromstring($raw);
        if ($img === false) {
            return base64_encode($raw);
        }
        foreach ([[1400, 80], [1200, 75], [1000, 70], [850, 65], [700, 60]] as [$max, $quality]) {
            $w = imagesx($img);
            $h = imagesy($img);
            $k = min(1, $max / max($w, $h));
            $out = imagecreatetruecolor(max(1, (int) round($w * $k)), max(1, (int) round($h * $k)));
            imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
            imagecopyresampled($out, $img, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
            ob_start();
            imagejpeg($out, null, $quality);
            $b64 = base64_encode((string) ob_get_clean());
            imagedestroy($out);
            if (strlen($b64) < 175000) {
                break;
            }
        }
        imagedestroy($img);
        return $b64;
    }

    /** A filled-in example of the answer, for services without schema enforcement. */
    private static function example(): array
    {
        $amounts = array_fill_keys(array_keys(self::CATEGORIES), 0);
        $amounts['donation'] = 100;
        return ['receipt_no' => '26432', 'date' => '2026-10-16', 'item' => '', 'name' => '陳大文', 'amounts' => $amounts,
                'other_label' => '', 'payment' => 'cash', 'total' => 100, 'issued_by' => '', 'unsure' => ['issued_by'], 'notes' => ''];
    }

    /**
     * One HTTPS request (POST with $body, or GET when $body is null).
     * If this server cannot check HTTPS certificates — common on Windows
     * XAMPP, whose PHP ships without a certificate list — it tries once
     * more with the list bundled here (app/Core/cacert.pem, Mozilla's).
     */
    private function request(string $url, ?array $body, array $headers): array
    {
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
            $type   = is_array($reply) ? (string) ($reply['error']['type'] ?? ($reply['title'] ?? '')) : '';
            // Anthropic: {"error":{"message"}}; NVIDIA / OpenAI-style: {"error":{"message"}} or {"detail"} / {"title"}.
            $detail = is_array($reply)
                ? (string) ($reply['error']['message'] ?? (is_string($reply['error'] ?? null) ? $reply['error'] : ($reply['detail'] ?? ($reply['title'] ?? ''))))
                : mb_substr((string) $raw, 0, 200);
            $detail = is_string($detail) ? $detail : json_encode($detail);
            error_log("ReceiptReader HTTP {$status} {$type}: " . substr((string) $raw, 0, 500));
            throw new RuntimeException(match (true) {
                $status === 400 && stripos($detail, 'API key not valid') !== false
                                                 => "Google 金鑰無效，請檢查是否貼錯。\nThe Google API key is not valid — check it was pasted correctly.",
                $status === 403 && (stripos($detail, 'has not been used') !== false || stripos($detail, 'disabled') !== false)
                                                 => "這個 Google 專案還沒有啟用 Cloud Vision API：到 Google Cloud →「API 和服務」→ 啟用「Cloud Vision API」，幾分鐘後再試。\nCloud Vision API is not enabled for this Google project: Google Cloud → APIs & Services → enable “Cloud Vision API”, then try again in a few minutes.",
                $status === 403 && stripos($detail, 'billing') !== false
                                                 => "這個 Google 專案還沒有啟用帳單。請在 Google Cloud →「帳單」連結付款方式（每月首 1,000 張免費，不會收費）。\nBilling is not enabled for this Google project. Link a payment method under Google Cloud → Billing (the first 1,000 scans a month are free).",
                $status === 403 && (stripos($detail, 'referer') !== false || stripos($detail, 'IP address') !== false || stripos($detail, 'blocked') !== false)
                                                 => "這個 Google 金鑰設了「網站」或「IP」限制，伺服器被擋住了。請在金鑰設定把「應用程式限制」改為「無」，只保留「API 限制：Cloud Vision API」。\nThis Google key has a website or IP restriction that blocks the server. In the key's settings set Application restrictions to None and keep API restrictions: Cloud Vision API.",
                $status === 401                  => "AI 金鑰無效（打錯、已刪除或已停用），請系統管理員到「網站設定」檢查。\nThe AI key was refused (mistyped, deleted or disabled) — the system admin can check it in Site settings.",
                $status === 403                  => "這個金鑰沒有使用權限，請檢查 AI 帳戶。\nThis key is not allowed to do this — check the AI account.",
                $status === 404                  => "找不到這個 AI 模型，或此金鑰不能使用它。請換一個模型名稱。\nThis AI model was not found, or this key cannot use it — try another model name.",
                $status === 402 || (in_array($status, [400, 403, 429], true) && stripos($detail, 'credit') !== false)
                                                 => "AI 帳戶的額度已用完，請先儲值或換一個服務。\nThe AI account is out of credit — top it up or switch service.",
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
