<?php
declare(strict_types=1);

/**
 * 位置：/Public/tools/run_due_reminder.php
 * 用法：
 *   https://你的網域/Public/tools/run_due_reminder.php?key=PUT_A_LONG_RANDOM_STRING_HERE&days=30
 */
$env = require __DIR__ . '/../../config/.env.php';

// 可在 .env.php 放：'ADMIN_WEBHOOK_SECRET' => 'PUT_A_LONG_RANDOM_STRING_HERE'
$needKey = (string)($env['ADMIN_WEBHOOK_SECRET'] ?? '');
if ($needKey === '') {
  http_response_code(403);
  echo 'Forbidden: ADMIN_WEBHOOK_SECRET not set.';
  exit;
}
$key = (string)($_GET['key'] ?? '');
if ($key !== $needKey) {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}


// 將 GET days 傳遞給核心檔
if (isset($_GET['days'])) {
  $_GET['days'] = (string)(int)$_GET['days']; // 簡單正規化
}

require __DIR__ . '/../../tools/due_reminder.php';
