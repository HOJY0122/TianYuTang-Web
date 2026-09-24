<?php
namespace App\Core;

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
        return self::conf('ANTHROPIC_API_KEY', '') !== '';
    }

    /**
     * A setting from config.php — or the environment, or a default — so an
     * older config.php without the AI lines still works (AI simply off).
     */
    private static function conf(string $name, string $default): string
    {
        if (defined($name)) {
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
        $headers = [
            'content-type: application/json',
            'x-api-key: ' . self::conf('ANTHROPIC_API_KEY', ''),
            'anthropic-version: 2023-06-01',
            'anthropic-beta: server-side-fallback-2026-07-01',
        ];
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $url  = self::conf('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1/messages');

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $json,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT        => 180,   // reading a photo can take a minute
            ]);
            $raw    = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $netErr = $raw === false ? curl_error($ch) : '';
            curl_close($ch);
        } else {
            $ctx = stream_context_create(['http' => [
                'method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $json,
                'timeout' => 180, 'ignore_errors' => true,
            ]]);
            $raw    = @file_get_contents($url, false, $ctx);
            $status = 0;
            foreach ($http_response_header ?? [] as $h) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                    $status = (int) $m[1];
                }
            }
            $netErr = $raw === false ? 'connection failed' : '';
        }

        if ($raw === false || $status === 0) {
            error_log('ReceiptReader network error: ' . $netErr);
            throw new RuntimeException("連不上 AI 服務，請檢查網絡後再試。\nCould not reach the AI service — check the connection and try again.");
        }
        $reply = json_decode((string) $raw, true);
        if ($status !== 200 || !is_array($reply)) {
            $type = is_array($reply) ? (string) ($reply['error']['type'] ?? '') : '';
            error_log("ReceiptReader HTTP {$status} {$type}: " . substr((string) $raw, 0, 500));
            throw new RuntimeException(match (true) {
                $status === 401, $status === 403 => "AI 金鑰無效，請聯絡系統管理員。\nThe AI key was refused — please tell the system admin.",
                $status === 429                  => "AI 服務忙碌，請一分鐘後再試。\nThe AI service is busy — please try again in a minute.",
                $status === 413                  => "相片太大，請縮小後再試。\nThe photo is too large — please use a smaller one.",
                $status >= 500                   => "AI 服務暫時無法使用，請稍後再試。\nThe AI service is having trouble — please try again shortly.",
                default                          => "AI 無法處理這張相片（{$status}），請手動輸入。\nThe AI could not process this photo ({$status}) — please type it in.",
            });
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
