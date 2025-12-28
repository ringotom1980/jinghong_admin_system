<?php
declare(strict_types=1);

/**
 * 位置：/tools/line_client.php
 * 功能：封裝 LINE Push/Reply 與簽章驗證 + 可靠的標頭擷取
 */
final class LineClient
{
    private string $token;
    private ?string $secret;

    public function __construct(string $token, ?string $secret = null)
    {
        if ($token === '') {
            throw new RuntimeException('LINE access token missing');
        }
        $this->token  = $token;
        $this->secret = $secret;
    }

    /** 驗證 X-Line-Signature（Webhook 用） */
    public function verifySignature(string $rawBody, ?string $signature): bool
    {
        if (!$this->secret || !$signature) return false;
        $calc = base64_encode(hash_hmac('sha256', $rawBody, $this->secret, true));
        // 使用 hash_equals 防時序攻擊
        return hash_equals($calc, trim((string)$signature));
    }

    /** 推播到任一 groupId/roomId（純文字） */
    public function pushTo(string $id, string $text): array
    {
        return $this->postJson('https://api.line.me/v2/bot/message/push', [
            'to' => $id,
            'messages' => [['type' => 'text', 'text' => $text]],
        ]);
    }

    /** Reply API（用 replyToken 回覆訊息） */
    public function reply(string $replyToken, array $messages): array
    {
        return $this->postJson('https://api.line.me/v2/bot/message/reply', [
            'replyToken' => $replyToken,
            'messages'   => $messages,
        ]);
    }

    private function postJson(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 20,
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['http' => $http, 'resp' => $resp, 'err' => $err ?: null];
    }

    /**
     * 取得所有 HTTP 標頭（跨環境相容）
     * - 先嘗試 getallheaders()
     * - 再從 $_SERVER 重建
     * - 專門回補 X-Line-Signature（某些環境只在 HTTP_X_LINE_SIGNATURE）
     * - 最後將值 trim() 去除前後空白
     */
    public static function getAllHeadersCompat(): array
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            $h = getallheaders();
            if (is_array($h)) {
                // 有些環境會回傳大小寫混合，統一保留原樣（core 會做小寫化）
                $headers = $h;
            }
        }

        // 後備：從 $_SERVER 重建
        foreach ($_SERVER as $name => $value) {
            if (strpos($name, 'HTTP_') === 0) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
            }
        }

        // 最保險：專門補 X-Line-Signature
        if (!isset($headers['X-Line-Signature']) && isset($_SERVER['HTTP_X_LINE_SIGNATURE'])) {
            $headers['X-Line-Signature'] = $_SERVER['HTTP_X_LINE_SIGNATURE'];
        }

        // 去除頭尾空白
        foreach ($headers as $k => $v) {
            if (is_string($v)) $headers[$k] = trim($v);
        }

        return $headers;
    }
}
