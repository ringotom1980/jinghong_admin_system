<?php
declare(strict_types=1);

/**
 * 位置：/tools/due_reminder.php
 * 功能：依 vehicles 表的 6 個日期欄位，推播「近期到期」與「已逾期」兩則訊息到所有群組。
 * 欄位：inspection_date / insurance_date / emission_date / record_date / insulation_test_date / xray_test_date
 * 規則：今天(含)起算未來 N=days_ahead 天為「近期到期」；小於今天者為「已逾期」；無效日期(NULL/0000-00-00)忽略。
 * 排程：建議 cron 週一～週六 07:00 觸發；腳本內也有 day-of-week 防呆。
 */

date_default_timezone_set('Asia/Taipei');

// ========== 可調整參數（給管理員改用） ==========
$REMINDER_CONFIG = [
  // 推播時間（資訊/防呆用途；實際排程以 cron 為準）
  'hour'           => 7,
  'minute'         => 0,

  // 一週哪些天要送：1=週一 … 7=週日；你要避開週日 => [1,2,3,4,5,6]
  'days_of_week'   => [1,2,3,4,5,6,7],

  // 提前天數（含當天）
  'days_ahead'     => 30,

  // 是否包含逾期（會另外成為第二則推播）
  'include_overdue'=> true,

  // 無任何項目時是否推播提示（預設不推）
  'send_empty_notice' => false,

  // 文字分頁的每頁最大字數（LINE 文本訊息上限約 2000；保守抓 1800）
  'page_char_limit' => 1800,
];

// === 只在設定的時刻觸發（Asia/Taipei），其餘分鐘全部略過 ===
$tz       = new DateTimeZone('Asia/Taipei');
$now      = new DateTimeImmutable('now', $tz);
$wantHHMM = (int)sprintf('%02d%02d', (int)$REMINDER_CONFIG['hour'], (int)$REMINDER_CONFIG['minute']);
$nowHHMM  = (int)$now->format('Hi');
if ($nowHHMM !== $wantHHMM) {
  echo "[SKIP] Not scheduled minute: now=$nowHHMM want=$wantHHMM\n";
  exit(0);
}

// === 一天只送一次（無論排程跑幾次都不重複） ===
$flagFile = __DIR__ . '/../storage/tmp/due_reminder_last_run.txt';
@mkdir(dirname($flagFile), 0775, true);
$todayFlag = $now->format('Y-m-d');
$lastFlag  = @is_file($flagFile) ? trim((string)@file_get_contents($flagFile)) : '';
if ($lastFlag === $todayFlag) {
  echo "[SKIP] Already sent today $todayFlag\n";
  exit(0);
}


// ========== 載入環境 ==========
$env = require __DIR__ . '/../config/.env.php';
require_once __DIR__ . '/../config/db_connection.php'; // 提供 $conn (PDO)
require_once __DIR__ . '/line_client.php';

// ========== 防呆：今天是否在允許的星期內 ==========
$todayDow = (int)date('N'); // 1~7
if (!in_array($todayDow, $REMINDER_CONFIG['days_of_week'], true)) {
  echo "[SKIP] Not in allowed days_of_week. Today N={$todayDow}\n";
  exit(0);
}

// ========== 支援 CLI/Web 覆寫 days_ahead（選擇性） ==========
$daysAhead = $REMINDER_CONFIG['days_ahead'];
if (PHP_SAPI === 'cli') {
  if (isset($argv[1]) && is_numeric($argv[1])) $daysAhead = max(0, (int)$argv[1]);
} else {
  if (isset($_GET['days']) && is_numeric($_GET['days'])) $daysAhead = max(0, (int)$_GET['days']);
}

// ========== 準備推播 ==========
$token = (string)($env['LINE_CHANNEL_ACCESS_TOKEN'] ?? '');
if ($token === '') {
  fwrite(STDERR, "[ERR] Missing LINE_CHANNEL_ACCESS_TOKEN\n");
  exit(1);
}

// 取推播目標（DB → .env 備援）
$targets = [];
if ($stmt = $conn->query("SELECT group_id FROM line_targets ORDER BY updated_at DESC")) {
  $targets = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
if (!$targets) {
  $targets = array_map('strval', (array)($env['LINE_GROUP_IDS'] ?? []));
}
if (!$targets) {
  echo "[INFO] No targets\n";
  exit(0);
}

// ========== 欄位/顯示名 定義 ==========
$WATCH_FIELDS = [
  'inspection_date'       => '驗車',
  'insurance_date'        => '保險',
  'emission_date'         => '廢檢',
  'record_date'           => '紀錄器',
  'insulation_test_date'  => '絕緣',
  'xray_test_date'        => 'X光',
];

// ========== 期間界線 ==========
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$end   = (new DateTimeImmutable('today +'.$daysAhead.' days'))->format('Y-m-d');

// ========== 撈取 vehicles，組合每車每日的項目 ==========
$sql = "SELECT vehicle_id, license_plate,
        inspection_date, insurance_date, emission_date, record_date,
        insulation_test_date, xray_test_date
        FROM vehicles";
$rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// helper：檢查有效日期（非 NULL 且非 0000-00-00）
$valid = function($d): bool {
  if ($d === null) return false;
  $d = trim((string)$d);
  return ($d !== '' && $d !== '0000-00-00');
};

// keyed by [date]['vehicle_id|license_plate'] = ['date'=>..., 'vid'=>..., 'plate'=>..., 'labels'=>['驗車',...]]
$bucketSoon = []; // 近期
$bucketOver = []; // 逾期

foreach ($rows as $r) {
  $vid   = (string)($r['vehicle_id'] ?? '');
  $plate = (string)($r['license_plate'] ?? '');
  if ($vid === '' && $plate === '') continue;

  // 每個欄位檢查
  foreach ($WATCH_FIELDS as $col => $label) {
    $d = $r[$col] ?? null;
    if (!$valid($d)) continue;

    // 分流：逾期 vs 近期（含今日～end）
    if ($d < $today) {
      // 已逾期
      $kCar = $vid.'|'.$plate;
      if (!isset($bucketOver[$d])) $bucketOver[$d] = [];
      if (!isset($bucketOver[$d][$kCar])) {
        $bucketOver[$d][$kCar] = ['date'=>$d,'vid'=>$vid,'plate'=>$plate,'labels'=>[]];
      }
      $bucketOver[$d][$kCar]['labels'][] = $label;
    } elseif ($d >= $today && $d <= $end) {
      // 近期到期
      $kCar = $vid.'|'.$plate;
      if (!isset($bucketSoon[$d])) $bucketSoon[$d] = [];
      if (!isset($bucketSoon[$d][$kCar])) {
        $bucketSoon[$d][$kCar] = ['date'=>$d,'vid'=>$vid,'plate'=>$plate,'labels'=>[]];
      }
      $bucketSoon[$d][$kCar]['labels'][] = $label;
    } else {
      // 超出近期視窗（未來更遠）→ 不列
      continue;
    }
  }
}

// ========== 排序 & 轉文字 ==========
$buildLines = function(array $bucket): array {
  // 依日期升冪
  ksort($bucket, SORT_STRING);
  $lines = [];
  foreach ($bucket as $d => $cars) {
    // 同日內依車輛編號升冪
    uasort($cars, function($a, $b) {
      return strcmp($a['vid'], $b['vid']);
    });
    foreach ($cars as $it) {
      $vid   = $it['vid'];
      $plate = $it['plate'];
      $labelStr = implode('、', array_values(array_unique($it['labels'])));
      // 同一車、同一日的多個項目 → 合併成一行，日期寫一次
      $lines[] = sprintf("• %s（%s）：%s %s",
        $vid !== '' ? $vid : '—',
        $plate !== '' ? $plate : '—',
        $labelStr,
        $it['date']
      );
    }
  }
  return $lines;
};

$linesSoon = $buildLines($bucketSoon); // 近期
$linesOver = $buildLines($bucketOver); // 逾期

// ========== 如全空，視設定決定是否送「無到期」 ==========
if (!$linesSoon && (!$REMINDER_CONFIG['include_overdue'] || !$linesOver)) {
  if ($REMINDER_CONFIG['send_empty_notice']) {
    $header = "【車輛到期提醒｜近期到期】\n期間：{$today} ～ {$end}（含當天）\n\n今天起 {$REMINDER_CONFIG['days_ahead']} 天內，沒有任何到期項目。";
    pushToAll($token, $targets, [$header]);
  } else {
    echo "[INFO] No due items in both categories. No push.\n";
  }
  exit(0);
}

// ========== 分頁（依字數切頁） ==========
$paginate = function(string $title, array $lines, int $limit, string $subtitle=''): array {
  if (!$lines) return [];
  $pages = [];
  $header = $title . ($subtitle!=='' ? "\n".$subtitle : '');
  $buf = $header;
  foreach ($lines as $ln) {
    $try = ($buf === '' ? $ln : $buf . "\n" . $ln);
    if (mb_strlen($try, 'UTF-8') > $limit) {
      $pages[] = $buf;
      $buf = $header . "\n" . $ln; // 新頁重新帶標頭
      // 若單行就超過 limit（極少見），也直接塞進去
      if (mb_strlen($buf, 'UTF-8') > $limit) {
        $pages[] = $buf;
        $buf = $header;
      }
    } else {
      $buf = $try;
    }
  }
  if (trim($buf) !== '') $pages[] = $buf;

  // 若超過 1 頁，加上 (i/n) 尾標
  $n = count($pages);
  if ($n > 1) {
    foreach ($pages as $i => &$p) {
      $p .= "\n\n(" . ($i+1) . "/" . $n . ")";
    }
    unset($p);
  }
  return $pages;
};

// 近期到期（第一則）
$pagesSoon = [];
if ($linesSoon) {
  $titleSoon = "【車輛到期提醒｜近期到期】";
  $subSoon   = "期間：{$today} ～ {$end}（含當天）";
  $pagesSoon = $paginate($titleSoon, $linesSoon, (int)$REMINDER_CONFIG['page_char_limit'], $subSoon);
}

// 已逾期（第二則）
$pagesOver = [];
if ($REMINDER_CONFIG['include_overdue'] && $linesOver) {
  $titleOver = "【車輛到期提醒｜已逾期】";
  $subOver   = "截止：{$today}（小於今天）";
  $pagesOver = $paginate($titleOver, $linesOver, (int)$REMINDER_CONFIG['page_char_limit'], $subOver);
}

// ========== 依需求：分兩「組」推播（近期 → 逾期），每組內若多頁就分頁 ==========
if ($pagesSoon) pushToAll($token, $targets, $pagesSoon);
if ($pagesOver) pushToAll($token, $targets, $pagesOver);

echo "[DONE] soon_pages=".(count($pagesSoon))." over_pages=".(count($pagesOver))."\n";
@file_put_contents(__DIR__ . '/../storage/tmp/due_reminder_last_run.txt', $todayFlag);


// ========== 發送：對所有目標，按頁分批（每批最多 5 則） ==========
function pushToAll(string $token, array $targets, array $pages): void {
  // LINE 一次 push 最多 5 則 messages；這裡將 pages 以 5 筆為一批送
  $batches = array_chunk($pages, 5);
  foreach ($targets as $to) {
    foreach ($batches as $batch) {
      $payload = ['to' => $to, 'messages' => []];
      foreach ($batch as $txt) $payload['messages'][] = ['type'=>'text','text'=>$txt];
      $res = post_json($token, 'https://api.line.me/v2/bot/message/push', $payload);
      $ok  = $res['http'] === 200 ? 'OK' : 'ERR';
      echo "[{$ok}] to={$to} parts=".count($batch)." http={$res['http']}\n";
    }
  }
}

function post_json(string $token, string $url, array $payload): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
      CURLOPT_POST           => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER     => [
          'Authorization: Bearer ' . $token,
          'Content-Type: application/json',
      ],
      CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
      CURLOPT_TIMEOUT        => 30,
  ]);
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return ['http'=>$http,'resp'=>$resp,'err'=>$err?:null];
}
