<?php
// Public/modules/equ/equ_repair_backend.php
// 只做查詢，產生 $repairs, $vendors_list, $machines_list, $pagination

require_once __DIR__ . '/../../../config/auth.php';
require_login();
require_once __DIR__ . '/../../../config/db_connection.php'; // 提供 PDO $conn；此檔用 mysqli 查清單

// === 讀 .env.php 建立 mysqli（沿用慣例）===
$envPath = __DIR__ . '/../../../config/.env.php';
$env = is_file($envPath) ? require $envPath : [];
$db = mysqli_connect($env['DB_HOST'] ?? '', $env['DB_USER'] ?? '', $env['DB_PASS'] ?? '', $env['DB_NAME'] ?? '');
if (!$db) { die('MySQL connect error'); }
mysqli_set_charset($db, 'utf8mb4');

// === 分頁參數 ===
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if ($records_per_page <= 0) $records_per_page = 10;
if ($records_per_page > 200) $records_per_page = 200;

$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max($current_page, 1);
$offset = ($current_page - 1) * $records_per_page;

// === 主清單 ===
$repairs = [];
$off = (int)$offset;
$cnt = (int)$records_per_page;

$sql_list = "
  SELECT
    mr.repair_id,
    mr.repair_date,
    mr.category,
    mr.vendor_name,
    mr.machine_name,
    mr.repair_content,
    mr.repair_cost,
    mr.company_burden,
    mr.items_json
  FROM machine_repairs AS mr
  ORDER BY mr.repair_date DESC, mr.repair_id DESC
  LIMIT $off, $cnt
";
if ($res = mysqli_query($db, $sql_list)) {
  if (function_exists('mysqli_fetch_all')) $repairs = mysqli_fetch_all($res, MYSQLI_ASSOC);
  else { while ($row = mysqli_fetch_assoc($res)) $repairs[] = $row; }
}

// === 總筆數 / 總頁數 ===
$total_records = 0;
if ($rc = mysqli_query($db, "SELECT COUNT(*) AS total FROM machine_repairs")) {
  $row = mysqli_fetch_assoc($rc);
  $total_records = (int)($row['total'] ?? 0);
}
$total_pages = (int)ceil($total_records / $records_per_page);
if ($total_pages < 1) $total_pages = 1;

// === 廠商清單（常用優先）===
$vendors_list = [];
$sql_vendors = "
  SELECT v AS vendor_name
  FROM (
    SELECT TRIM(vendor_name) AS v, COUNT(*) AS cnt
    FROM machine_repairs
    WHERE vendor_name IS NOT NULL AND TRIM(vendor_name) <> ''
    GROUP BY TRIM(vendor_name)
  ) t
  ORDER BY cnt DESC, v ASC
";
if ($vd = mysqli_query($db, $sql_vendors)) {
  while ($row = mysqli_fetch_assoc($vd)) {
    if (($row['vendor_name'] ?? '') !== '') $vendors_list[] = $row['vendor_name'];
  }
}

// === 機具清單（常用優先）===
$machines_list = [];
$sql_machines = "
  SELECT m AS machine_name
  FROM (
    SELECT TRIM(machine_name) AS m, COUNT(*) AS cnt
    FROM machine_repairs
    WHERE machine_name IS NOT NULL AND TRIM(machine_name) <> ''
    GROUP BY TRIM(machine_name)
  ) t
  ORDER BY cnt DESC, m ASC
";
if ($mm = mysqli_query($db, $sql_machines)) {
  while ($row = mysqli_fetch_assoc($mm)) {
    if (($row['machine_name'] ?? '') !== '') $machines_list[] = $row['machine_name'];
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
