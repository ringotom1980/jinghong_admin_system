<?php
declare(strict_types=1);

/**
 * 位置：/tools/line_webhook_core.php
 * 功能：
 * - 依 .env APP_ENV 切換 Debug（local/production）
 * - 驗簽 + 事件處理（join 收集群組/room ID → DB）
 * - message：在 user/room/group 都支援 "ping" → "pong"
 * - 詳細 log：/storage/tmp/line_webhook.log
 *
 * 強化點：
 * - 以 LineClient::getAllHeadersCompat() 取標頭，並小寫化 key 後再 trim()
 * - production 下：缺簽章/驗證失敗都詳細記錄（含本地 calc 值，方便對照）
 * - 避免 log 暴增：production 只記 raw 前 400 字
 */

date_default_timezone_set('Asia/Taipei');

// ========== 載入環境與相依 ==========
$env = require __DIR__ . '/../config/.env.php';
require_once __DIR__ . '/../config/db_connection.php'; // $conn (PDO)
require_once __DIR__ . '/line_client.php';

$appEnv = (string)($env['APP_ENV'] ?? 'local'); // local / production
$token  = (string)($env['LINE_CHANNEL_ACCESS_TOKEN'] ?? '');
$secret = (string)($env['LINE_CHANNEL_SECRET']       ?? '');

$cli  = new LineClient($token, $secret);

// 只讀一次原始 body
$raw  = file_get_contents('php://input') ?: '';

// 取得所有 HTTP 標頭（跨環境相容）
$hdrs = LineClient::getAllHeadersCompat();
$hdrs_lc = array_change_key_case($hdrs, CASE_LOWER);

// 取簽章並去除頭尾空白
$sig  = isset($hdrs_lc['x-line-signature']) ? trim((string)$hdrs_lc['x-line-signature']) : null;

// ========== Logging ==========
$logDir = __DIR__ . '/../storage/tmp';
if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
$logFile = $logDir . '/line_webhook.log';

function log_line(string $m): void
{
    global $logFile;
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $m . PHP_EOL, FILE_APPEND);
}

log_line('--- incoming ---');
// 全標頭原樣記錄（避免大小寫差異）
log_line('headers=' . json_encode($hdrs, JSON_UNESCAPED_UNICODE));

// raw 在 production 縮短避免暴增
$RAW_LOG_LIMIT = 400;
if ($appEnv === 'production' && mb_strlen($raw, 'UTF-8') > $RAW_LOG_LIMIT) {
    $cut = mb_substr($raw, 0, $RAW_LOG_LIMIT, 'UTF-8');
    log_line('raw(truncated)=' . $cut . ' ...(+' . (mb_strlen($raw, 'UTF-8') - $RAW_LOG_LIMIT) . ')');
} else {
    log_line('raw=' . $raw);
}

// 附帶 .env 檢查（只記長度避免洩漏）
log_line('env.APP_ENV=' . $appEnv . ' secret.len=' . strlen((string)$secret));

// ========== 簽章驗證 ==========
// 在 production 必須通過簽章；在 local 若沒有簽章，允許跳過（方便用 curl/模擬）
$skipSig = ($appEnv !== 'production') && (!$sig);

if (!$skipSig) {
    if (!$sig) {
        log_line('signature missing (production mode)');
        http_response_code(400);
        echo 'Bad signature';
        exit;
    }
    // 若驗證失敗，輸出 got/calc 以便比對（不輸出 secret）
    $calc = base64_encode(hash_hmac('sha256', $raw, (string)$secret, true));
    if (!$cli->verifySignature($raw, $sig)) {
        log_line('signature verify FAILED; got=' . $sig . ' calc=' . $calc);
        http_response_code(400);
        echo 'Bad signature';
        exit;
    }
}
log_line('signature verify OK (skip=' . ($skipSig ? 'yes' : 'no') . ')');

// ========== 解析 JSON ==========
$data = json_decode($raw, true);
if (!is_array($data)) {
    log_line('bad json');
    http_response_code(400);
    echo 'Bad JSON';
    exit;
}

// ========== 準備 SQL（收集/刪除 targets） ==========
$ins = $conn->prepare("
    INSERT INTO line_targets (group_id, src_type)
    VALUES (:gid, :typ)
    ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
");
$del = $conn->prepare("DELETE FROM line_targets WHERE group_id = :gid");

// ========== 事件處理 ==========
try {
    foreach ($data['events'] ?? [] as $ev) {
        $type = (string)($ev['type'] ?? '');
        $src  = (array)  ($ev['source'] ?? []);
        $rpt  = (string) ($ev['replyToken'] ?? '');

        $srcTyp = (string)($src['type'] ?? '');   // 'user' | 'group' | 'room'
        $uid    = (string)($src['userId'] ?? '');
        $gid    = (string)($src['groupId'] ?? '');
        $rid    = (string)($src['roomId']  ?? '');

        // 正規化：id + typ
        $id = '';
        $typ = '';
        if ($srcTyp === 'group' && $gid !== '') {
            $id = $gid;
            $typ = 'group';
        } elseif ($srcTyp === 'room' && $rid !== '') {
            $id = $rid;
            $typ = 'room';
        }

        log_line("event type={$type} srcTyp={$srcTyp} id={$id}");

        // A) join：被邀進群組/room → 寫入 DB
        if ($type === 'join' && $id !== '' && $typ !== '') {
            try {
                $ins->execute([':gid' => $id, ':typ' => $typ]);
                log_line("saved target {$typ}:{$id}");
            } catch (Throwable $e) {
                log_line('DB save error: ' . $e->getMessage());
            }
            $cli->reply($rpt, [['type' => 'text', 'text' => "群組已綁定：{$id}"]]);
            continue;
        }

        // B) leave/memberLeft：離開 → 移除 DB
        if (in_array($type, ['leave', 'memberLeft'], true) && $id !== '') {
            try {
                $del->execute([':gid' => $id]);
                log_line("deleted target {$id}");
            } catch (Throwable $e) {
                log_line('DB delete error: ' . $e->getMessage());
            }
            continue;
        }

        // C) 一般訊息（在 user/room/group 都處理 ping→pong；群組/room 也補寫 DB）
        if ($type === 'message') {
            $msg = (array)($ev['message'] ?? []);
            $mt  = (string)($msg['type']   ?? '');
            $tx  = (string)($msg['text']   ?? '');

            // 只要是 group/room 的訊息，就確保目標已寫入
            if ($id !== '' && $typ !== '') {
                try {
                    $ins->execute([':gid' => $id, ':typ' => $typ]);
                } catch (Throwable $e) {
                    // 已存在等情況，靜默忽略
                }
            }

            if ($mt === 'text' && strtolower(trim($tx)) === 'ping') {
                $cli->reply($rpt, [['type' => 'text', 'text' => 'pong']]);
                log_line("replied pong (srcTyp={$srcTyp})");
            }
        }
    }

    http_response_code(200);
    echo 'OK';
    log_line('responded 200 OK');

} catch (Throwable $e) {
    // 任何未捕捉錯誤：記錄並回 500
    log_line('unhandled error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Server error';
}
