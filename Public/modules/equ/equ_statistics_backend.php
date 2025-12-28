<?php
// Public/modules/equ/equ_statistics_backend.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/auth.php';
require_login();

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

$filterType = $_POST['filterType'] ?? 'all';
$half       = isset($_POST['half']) ? (string)$_POST['half'] : '';
$year       = '';
foreach (['year', 'halfYear', 'monthYear'] as $k) {
  if (!empty($_POST[$k])) { $year = trim((string)$_POST[$k]); break; }
}
$month      = isset($_POST['month']) ? (string)$_POST['month'] : '';
$vendorKey  = trim((string)($_POST['vendorKey'] ?? ''));

$start = null; $end = null;
if ($filterType === 'year' && $year !== '') {
  $y = (int)$year; $start = sprintf('%04d-01-01',$y); $end = sprintf('%04d-12-31',$y);
} elseif ($filterType === 'half_year' && $year !== '' && ($half === '1' || $half === '2')) {
  $y = (int)$year;
  if ($half === '1') { $start="$y-01-01"; $end="$y-06-30"; }
  else { $start="$y-07-01"; $end="$y-12-31"; }
} elseif ($filterType === 'month' && $year !== '' && $month !== '') {
  $y = (int)$year; $m = (int)$month;
  $start = sprintf('%04d-%02d-01', $y, $m);
  $end   = date('Y-m-t', strtotime($start));
}

$rangeWhere = '1=1';
if ($start && $end) {
  $s = mysqli_real_escape_string($db, $start);
  $e = mysqli_real_escape_string($db, $end);
  $rangeWhere = " mr.repair_date BETWEEN '{$s}' AND '{$e}' ";
}
$vendorWhere = '1=1';
if ($vendorKey !== '') {
  $k = mysqli_real_escape_string($db, $vendorKey);
  $vendorWhere = " mr.vendor_name_norm = '{$k}' ";
}

$statistics = [];
$sqlStat = "
  SELECT
    mr.vendor_name_norm AS vendor_key,
    MIN(mr.vendor_name) AS vendor_name,
    COUNT(mr.repair_id) AS total_repairs,
    COALESCE(SUM(mr.repair_cost),0) AS total_repair_cost,
    COALESCE(SUM(COALESCE(mr.company_burden,0)),0) AS total_company_burden
  FROM machine_repairs mr
  WHERE {$rangeWhere}
  GROUP BY mr.vendor_name_norm
  HAVING total_repairs > 0
  ORDER BY total_repairs DESC, total_repair_cost DESC,
           CASE WHEN vendor_name REGEXP '^[0-9]' THEN 0 ELSE 1 END ASC,
           vendor_name ASC
";
if ($rs = mysqli_query($db, $sqlStat)) {
  if (function_exists('mysqli_fetch_all')) $statistics = mysqli_fetch_all($rs, MYSQLI_ASSOC);
  else { while ($row = mysqli_fetch_assoc($rs)) $statistics[] = $row; }
}

$details = [];
$sqlDet = "
  SELECT
    mr.repair_id,
    mr.repair_date,
    mr.repair_content,
    mr.repair_cost,
    mr.company_burden,
    mr.items_json,
    mr.vendor_name,
    mr.machine_name
  FROM machine_repairs mr
  WHERE {$rangeWhere} AND {$vendorWhere}
  ORDER BY mr.repair_date DESC, mr.repair_id DESC
";
if ($rd = mysqli_query($db, $sqlDet)) {
  while ($row = mysqli_fetch_assoc($rd)) {
    $itemsArr = json_decode($row['items_json'] ?? '[]', true) ?: [];
    $parts = []; $titleLines = [];
    foreach ($itemsArr as $it) {
      $c = trim((string)($it['content'] ?? ''));
      $amt = isset($it['amount']) ? (float)$it['amount'] : null;
      if ($c !== '') {
        if ($amt !== null) { $amt_fmt = number_format($amt, 0); $parts[] = "{$c}（{$amt_fmt}元）"; $titleLines[] = "{$c}：{$amt_fmt}元"; }
        else { $parts[] = $c; $titleLines[] = $c; }
      }
    }
    $row['itemsSummary'] = implode('、', $parts);
    $row['itemsTitle']   = implode("\n", $titleLines);
    $details[] = $row;
  }
}

$totalRepairCost = 0; $totalCompanyBurden = 0;
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
