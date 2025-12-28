<?php
// Public/modules/car/car_statistics_backend.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/auth.php';
require_login();

// 讀 .env.php 建立 mysqli（沿用舊站）
$envPath = __DIR__ . '/../../../config/.env.php';
$env = is_file($envPath) ? require $envPath : [];
$db = mysqli_connect($env['DB_HOST'] ?? '', $env['DB_USER'] ?? '', $env['DB_PASS'] ?? '', $env['DB_NAME'] ?? '');
if (!$db) {
  header('Content-Type: application/json; charset=utf-8');
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'MySQL connect error'], JSON_UNESCAPED_UNICODE);
  exit;
}
mysqli_set_charset($db, 'utf8mb4');

header('Content-Type: application/json; charset=utf-8');

// ------- 解析參數（精簡 + 防空） -------
$filterType = $_POST['filterType'] ?? 'all';
$half       = isset($_POST['half'])  ? (string)$_POST['half']  : '';
$month      = isset($_POST['month']) ? (string)$_POST['month'] : '';

// 可能是數字的車輛編號或「車牌字串」
$vehicleIdRaw   = trim((string)($_POST['vehicleId'] ?? ''));      // 可能是 "12" 或 "AA-1234"
$vehiclePlateIn = trim((string)($_POST['vehiclePlate'] ?? ''));   // 額外保險：直接帶車牌

// $year：從 year / halfYear / monthYear 擇一取「非空」值
$year = '';
foreach (['year', 'halfYear', 'monthYear'] as $k) {
  if (!empty($_POST[$k])) { $year = trim((string)$_POST[$k]); break; }
}

// ------- 計算日期區間（含端點）-------
$start = null; $end = null;
if ($filterType === 'year' && $year !== '') {
  $y = (int)$year; $start = sprintf('%04d-01-01',$y); $end = sprintf('%04d-12-31',$y);
} elseif ($filterType === 'half_year' && $year !== '' && ($half === '1' || $half === '2')) {
  $y = (int)$year; if ($half === '1') { $start="$y-01-01"; $end="$y-06-30"; } else { $start="$y-07-01"; $end="$y-12-31"; }
} elseif ($filterType === 'month' && $year !== '' && $month !== '') {
  $y = (int)$year; $m = (int)$month; $start = sprintf('%04d-%02d-01',$y,$m); $end = date('Y-m-t', strtotime($start));
}

// ------- WHERE 片段 -------
$rangeJoin  = '';
$rangeWhere = '1=1';
if ($start && $end) {
  $s = mysqli_real_escape_string($db, $start);
  $e = mysqli_real_escape_string($db, $end);
  $rangeJoin  = " AND r.repair_date BETWEEN '{$s}' AND '{$e}' ";
  $rangeWhere = " r.repair_date BETWEEN '{$s}' AND '{$e}' ";
}

// ✅ 支援「車輛編號」或「車牌」，若兩者都有就以 OR 命中任一即可
$vehicleWhere = '';
$vid   = null;
$plate = '';

if ($vehicleIdRaw !== '' && ctype_digit($vehicleIdRaw)) {
  $vid = (int)$vehicleIdRaw;
}
if ($vehiclePlateIn !== '') {
  $plate = mysqli_real_escape_string($db, $vehiclePlateIn);
}

if ($vid !== null && $plate !== '') {
  $vehicleWhere = " AND (r.vehicle_id = {$vid} OR v.license_plate = '{$plate}') ";
} elseif ($vid !== null) {
  $vehicleWhere = " AND r.vehicle_id = {$vid} ";
} elseif ($plate !== '') {
  $vehicleWhere = " AND v.license_plate = '{$plate}' ";
}
// 若兩者皆無，$vehicleWhere 保持空字串 => 不過濾（右表顯示全部或依日期）


// ------- 統計 -------
$statistics = [];
$sqlStat = "
  SELECT
    v.vehicle_id,
    v.license_plate,
    COUNT(r.repair_id) AS total_repairs,
    COALESCE(SUM(r.repair_cost),0) AS total_repair_cost,
    COALESCE(SUM(COALESCE(r.company_burden,0)),0) AS total_company_burden
  FROM vehicles v
  LEFT JOIN repairs r
    ON r.vehicle_id = v.vehicle_id
   {$rangeJoin}
  GROUP BY v.vehicle_id, v.license_plate
  HAVING total_repairs > 0
  ORDER BY total_repairs DESC, total_repair_cost DESC, v.vehicle_id ASC
";

if ($rs = mysqli_query($db, $sqlStat)) {
  if (function_exists('mysqli_fetch_all')) $statistics = mysqli_fetch_all($rs, MYSQLI_ASSOC);
  else while ($row = mysqli_fetch_assoc($rs)) $statistics[] = $row;
}

// ------- 明細（未選車 = 全部車） -------
$details = [];
$sqlDet = "
  SELECT
    r.repair_id,
    r.repair_date,
    r.repair_content,
    r.repair_cost,
    r.company_burden,
    r.items_json,
    v.vehicle_id,
    v.license_plate
  FROM repairs r
  JOIN vehicles v ON r.vehicle_id = v.vehicle_id
  WHERE {$rangeWhere} {$vehicleWhere}
  ORDER BY r.repair_date DESC, r.repair_id DESC
";
if ($rd = mysqli_query($db, $sqlDet)) {
  while ($row = mysqli_fetch_assoc($rd)) {
    $itemsArr = json_decode($row['items_json'] ?? '[]', true) ?: [];
    $parts = []; $titleLines = [];
    foreach ($itemsArr as $it) {
      $c = trim((string)($it['content'] ?? ''));
      $amt = isset($it['amount']) ? (float)$it['amount'] : null;
      if ($c !== '') {
        if ($amt !== null) {
          $amt_fmt = number_format($amt, 0);
          $parts[] = "{$c}（{$amt_fmt}元）";
          $titleLines[] = "{$c}：{$amt_fmt}元";
        } else {
          $parts[] = $c; $titleLines[] = $c;
        }
      }
    }
    $row['itemsSummary'] = implode('、', $parts);
    $row['itemsTitle']   = implode("\n", $titleLines);
    $details[] = $row;
  }
}

// ------- 合計 -------
$totalRepairCost = 0;
$totalCompanyBurden = 0;
foreach ($statistics as $s) {
  $totalRepairCost    += (int)($s['total_repair_cost'] ?? 0);
  $totalCompanyBurden += (int)($s['total_company_burden'] ?? 0);
}

echo json_encode([
  'statistics' => $statistics,
  'details' => $details,
  'totalRepairCost' => (int)$totalRepairCost,
  'totalCompanyBurden' => (int)$totalCompanyBurden,
], JSON_UNESCAPED_UNICODE);
