<?php
// Public/modules/equ/equ_repair_action.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/auth.php';
require_login();
require_once __DIR__ . '/../../../config/db_connection.php';

// ===== 工具 =====
function redirect_back(int $page = 1, int $per_page = 10, string $return_to = ''): void {
  $page = max(1, $page);
  if ($return_to !== '') {
    header('Location: equ_repair.php?' . $return_to);
  } else {
    header('Location: equ_repair.php?page=' . $page . '&per_page=' . $per_page);
  }
  exit;
}
function to_decimal($v) { if ($v === '' || $v === null) return 0; return (float)$v; }
function calc_totals_from_items($items): array {
  $sum_amount = 0; $sum_company = 0;
  if (is_array($items)) {
    foreach ($items as $it) {
      $sum_amount  += to_decimal($it['amount']  ?? 0);
      $sum_company += to_decimal($it['company'] ?? 0);
    }
  }
  return [
    'repair_cost'    => (int)round($sum_amount, 0),
    'company_burden' => (int)round($sum_company, 0),
  ];
}

$back_page   = max(1, (int)($_POST['page'] ?? $_GET['page'] ?? 1));
$per_page    = max(1, (int)($_POST['per_page'] ?? $_GET['per_page'] ?? 10));
$return_to   = trim((string)($_POST['return_to'] ?? $_GET['return_to'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $action = $_POST['action'];

  if ($action === 'add') {
    $machine_name   = trim((string)($_POST['machine_name'] ?? ''));
    $repair_date    = trim((string)($_POST['repair_date'] ?? ''));
    $vendor_name    = trim((string)($_POST['vendor_name'] ?? ''));
    $repair_content = trim((string)($_POST['repair_content'] ?? ''));
    $category       = trim((string)($_POST['category'] ?? ''));

    if (!in_array($category, ['保養','維修'], true)) {
      redirect_back($back_page, $per_page, $return_to);
    }

    $items_json_raw = $_POST['items_json'] ?? '[]';
    $items = json_decode($items_json_raw, true);
    if (!is_array($items)) $items = [];
    $totals = calc_totals_from_items($items);
    $repair_cost    = $totals['repair_cost'];
    $company_burden = $totals['company_burden'];
    $items_json     = json_encode($items, JSON_UNESCAPED_UNICODE);

    $sql = "INSERT INTO machine_repairs
              (machine_name, repair_date, category, vendor_name, items_json, repair_cost, company_burden, repair_content)
            VALUES
              (:machine_name, :repair_date, :category, :vendor_name, :items_json, :repair_cost, :company_burden, :repair_content)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
      ':machine_name'   => $machine_name,
      ':repair_date'    => $repair_date,
      ':category'       => $category,
      ':vendor_name'    => $vendor_name,
      ':items_json'     => $items_json,
      ':repair_cost'    => $repair_cost,
      ':company_burden' => $company_burden,
      ':repair_content' => $repair_content,
    ]);

    redirect_back($back_page, $per_page, $return_to);

  } elseif ($action === 'edit') {
    $repair_id      = trim((string)($_POST['repair_id'] ?? ''));
    $machine_name   = trim((string)($_POST['machine_name'] ?? ''));
    $repair_date    = trim((string)($_POST['repair_date'] ?? ''));
    $vendor_name    = trim((string)($_POST['vendor_name'] ?? ''));
    $repair_content = trim((string)($_POST['repair_content'] ?? ''));
    $category       = trim((string)($_POST['category'] ?? ''));

    if (!in_array($category, ['保養','維修'], true)) {
      redirect_back($back_page, $per_page, $return_to);
    }

    $items_json_raw = $_POST['items_json'] ?? '[]';
    $items = json_decode($items_json_raw, true);
    if (!is_array($items)) $items = [];
    $totals = calc_totals_from_items($items);
    $repair_cost    = $totals['repair_cost'];
    $company_burden = $totals['company_burden'];
    $items_json     = json_encode($items, JSON_UNESCAPED_UNICODE);

    $sql = "UPDATE machine_repairs SET
              machine_name    = :machine_name,
              repair_date     = :repair_date,
              category        = :category,
              vendor_name     = :vendor_name,
              items_json      = :items_json,
              repair_cost     = :repair_cost,
              company_burden  = :company_burden,
              repair_content  = :repair_content
            WHERE repair_id    = :repair_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
      ':machine_name'   => $machine_name,
      ':repair_date'    => $repair_date,
      ':category'       => $category,
      ':vendor_name'    => $vendor_name,
      ':items_json'     => $items_json,
      ':repair_cost'    => $repair_cost,
      ':company_burden' => $company_burden,
      ':repair_content' => $repair_content,
      ':repair_id'      => $repair_id,
    ]);

    redirect_back($back_page, $per_page, $return_to);

  } elseif ($action === 'delete') {
    $repair_id = trim((string)($_POST['repair_id'] ?? ''));
    if ($repair_id !== '') {
      $stm = $conn->prepare("DELETE FROM machine_repairs WHERE repair_id = :id");
      $stm->execute([':id' => $repair_id]);
    }

    // 避免回到空頁
    $tot = (int)$conn->query("SELECT COUNT(*) FROM machine_repairs")->fetchColumn();
    $maxPage = max(1, (int)ceil($tot / $per_page));
    if ($back_page > $maxPage) $back_page = $maxPage;

    redirect_back($back_page, $per_page, $return_to);

  } else {
    redirect_back($back_page, $per_page, $return_to);
  }
} else {
  redirect_back($back_page, $per_page, $return_to);
}
