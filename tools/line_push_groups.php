<?php
declare(strict_types=1);
/**
 * 用法（SSH 上）：
 *   php /home/你的帳號/jinghong_admin_system/tools/line_push_groups.php "這是測試訊息"
 * 說明：從 DB 的 line_targets 全部撈出 group/room，逐一推播
 */

$env = require __DIR__ . '/../config/.env.php';
require_once __DIR__ . '/../config/db_connection.php'; // $conn (PDO)
require_once __DIR__ . '/line_client.php';

$token = (string)($env['LINE_CHANNEL_ACCESS_TOKEN'] ?? '');
if ($token === '') {
  fwrite(STDERR, "[ERR] missing LINE_CHANNEL_ACCESS_TOKEN in .env.php\n");
  exit(1);
}
$cli  = new LineClient($token);

$text = $argv[1] ?? '✅ DB 推播測試';
$ids  = [];
$stmt = $conn->query("SELECT group_id FROM line_targets ORDER BY updated_at DESC");
if ($stmt) $ids = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));

// 若 DB 還空的，退回 .env（過渡用）
if (!$ids) $ids = array_map('strval', (array)($env['LINE_GROUP_IDS'] ?? []));

if (!$ids) { echo "[INFO] no targets\n"; exit(0); }

foreach ($ids as $id) {
  $r = $cli->pushTo($id, $text);
  $ok = $r['http'] === 200 ? 'OK' : 'ERR';
  echo "[{$ok}] {$id} http={$r['http']} resp={$r['resp']}\n";
}
