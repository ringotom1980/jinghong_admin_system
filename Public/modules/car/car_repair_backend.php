<?php
// Public/modules/car/car_repair_backend.php
// 只做查詢，產生 $repairs, $vehicles, $vendors_list, $pagination

require_once __DIR__ . '/../../../config/auth.php';
require_login();
require_once __DIR__ . '/../../../config/db_connection.php'; // 提供 PDO $conn；此檔同時用 mysqli 讀清單（相容舊站）

// === 讀 .env.php 建立 mysqli 連線（沿用舊站做法）===
$envPath = __DIR__ . '/../../../config/.env.php';
$env = is_file($envPath) ? require $envPath : [];
$db = mysqli_connect($env['DB_HOST'] ?? '', $env['DB_USER'] ?? '', $env['DB_PASS'] ?? '', $env['DB_NAME'] ?? '');
if (!$db) { die('MySQL connect error'); }
mysqli_set_charset($db, 'utf8mb4');

// === 分頁參數 ===
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if ($records_per_page <= 0) $records_per_page = 10;
if ($records_per_page > 200) $records_per_page = 200; // 合理上限避免誤用

$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max($current_page, 1);
$offset = ($current_page - 1) * $records_per_page;

// === 主清單 ===
// === 主清單（LIMIT 直接帶整數，避免 prepared LIMIT 被吃成 1 筆）===
$repairs = [];
$off = (int)$offset;
$cnt = (int)$records_per_page;

$sql_list = "
  SELECT
    r.repair_id,
    r.vehicle_id,
    r.repair_date,
    r.vendor,
    r.category,
    r.mileage,
    r.repair_content,
    r.repair_cost,
    r.company_burden,
    r.items_json,
    v.license_plate,
    v.user
  FROM repairs AS r
  JOIN vehicles AS v ON r.vehicle_id = v.vehicle_id
  ORDER BY r.repair_date DESC, r.repair_id DESC
  LIMIT $off, $cnt
";

$res = mysqli_query($db, $sql_list);
if ($res) {
  if (function_exists('mysqli_fetch_all')) {
    $repairs = mysqli_fetch_all($res, MYSQLI_ASSOC);
  } else {
    while ($row = mysqli_fetch_assoc($res)) $repairs[] = $row;
  }
}


// === 總筆數 / 總頁數 ===
$total_records = 0;
if ($rc = mysqli_query($db, "SELECT COUNT(*) AS total FROM repairs")) {
  $row = mysqli_fetch_assoc($rc);
  $total_records = (int)($row['total'] ?? 0);
}
$total_pages = (int)ceil($total_records / $records_per_page);
if ($total_pages < 1) $total_pages = 1;

// === 車輛下拉（英文字母先、數字後）===
$vehicles = [];
$sql_vehicles = "
  SELECT vehicle_id, license_plate
  FROM vehicles
  ORDER BY
    CASE WHEN license_plate REGEXP '^[0-9]' THEN 1 ELSE 0 END ASC,
    license_plate ASC
";
if ($vr = mysqli_query($db, $sql_vehicles)) {
  if (function_exists('mysqli_fetch_all')) {
    $vehicles = mysqli_fetch_all($vr, MYSQLI_ASSOC);
  } else {
    while ($row = mysqli_fetch_assoc($vr)) $vehicles[] = $row;
  }
}

// === 廠商清單（常用優先）===
$vendors_list = [];
$sql_vendors = "
  SELECT v AS vendor
  FROM (
    SELECT TRIM(vendor) AS v, COUNT(*) AS cnt
    FROM repairs
    WHERE vendor IS NOT NULL AND TRIM(vendor) <> ''
    GROUP BY TRIM(vendor)
  ) t
  ORDER BY cnt DESC, v ASC
";
if ($vd = mysqli_query($db, $sql_vendors)) {
  while ($row = mysqli_fetch_assoc($vd)) {
    if (($row['vendor'] ?? '') !== '') $vendors_list[] = $row['vendor'];
  }
}

// === 輸出給前端使用 ===
$pagination = [
  'records_per_page' => $records_per_page,
  'current_page'     => $current_page,
  'total_records'    => $total_records,
  'total_pages'      => $total_pages,
  'offset'           => $offset,
];

// 可選：頁面合計
// $page_total_repair_cost = array_sum(array_map(fn($r)=>(int)$r['repair_cost'],$repairs));
// $page_total_company_burden = array_sum(array_map(fn($r)=>(int)($r['company_burden']??0),$repairs));
