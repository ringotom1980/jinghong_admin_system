<?php
declare(strict_types=1);
/**
 * 用法（瀏覽器或 curl）：
 *   https://你的網域/Public/tools/push_now.php?key=你的密鑰&text=測試
 *   可選 to= 指定單一 groupId/roomId（不帶則推 DB 全部）
 */

$env = require __DIR__ . '/../../config/.env.php';
require_once __DIR__ . '/../../config/db_connection.php';
require_once __DIR__ . '/../../tools/line_client.php';

$key = $_GET['key']  ?? '';
$to  = $_GET['to']   ?? '';
$txt = $_GET['text'] ?? '';

if ($key === '' || $key !== (string)($env['ADMIN_WEBHOOK_SECRET'] ?? '')) {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}
if ($txt === '') $txt = '✅ Web 端推播測試';

$token = (string)($env['LINE_CHANNEL_ACCESS_TOKEN'] ?? '');
if ($token === '') { http_response_code(500); echo 'Missing token'; exit; }

$cli = new LineClient($token);

$targets = [];
if ($to !== '') {
  $targets = [$to];
} else {
  $stmt = $conn->query("SELECT group_id FROM line_targets ORDER BY updated_at DESC");
  if ($stmt) $targets = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
  if (!$targets) $targets = array_map('strval', (array)($env['LINE_GROUP_IDS'] ?? []));
}

if (!$targets) { echo "No targets.\n"; exit; }

$results = [];
foreach ($targets as $id) {
  $r = $cli->pushTo($id, $txt);
  $results[] = [$id, $r['http'], $r['resp']];
}
header('Content-Type: text/plain; charset=utf-8');
foreach ($results as [$id,$code,$resp]) {
  echo ($code===200?'[OK]':'[ERR]')." {$id} http={$code} resp={$resp}\n";
}
