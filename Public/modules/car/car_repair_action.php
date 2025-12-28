<?php
// Public/modules/car/car_repair_action.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/auth.php';
require_login();
require_once __DIR__ . '/../../../config/db_connection.php';

// ===== 工具 =====
function redirect_back(int $page = 1, int $per_page = 10, string $return_to = ''): void {
  $page = max(1, $page);
  if ($return_to !== '') {
    header('Location: car_repair.php?' . $return_to);
  } else {
    header('Location: car_repair.php?page=' . $page . '&per_page=' . $per_page);
  }
  exit;
}

function to_decimal($v) { if ($v === '' || $v === null) return 0; return (float)$v; }

function calc_totals_from_items($items): array {
  $sum_amount = 0;
  $sum_company = 0;
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
    $vehicle_id     = trim((string)($_POST['vehicle_id'] ?? ''));
    $repair_date    = trim((string)($_POST['repair_date'] ?? ''));
    $vendor         = trim((string)($_POST['vendor'] ?? '')) ;
    $repair_content = trim((string)($_POST['repair_content'] ?? ''));

    $category = trim((string)($_POST['category'] ?? ''));
    $mileage  = trim((string)($_POST['mileage'] ?? ''));

    if (!in_array($category, ['保養','維修'], true)) {
      redirect_back($back_page, $per_page, $return_to);
    }
    if ($category === '保養' && ($mileage === '' || !ctype_digit($mileage))) {
      redirect_back($back_page, $per_page, $return_to);
    }
    $mileage_sql = ($category === '保養') ? (int)$mileage : null;

    $items_json_raw = $_POST['items_json'] ?? '[]';
    $items = json_decode($items_json_raw, true);
    if (!is_array($items)) $items = [];
    $totals = calc_totals_from_items($items);
    $repair_cost    = $totals['repair_cost'];
    $company_burden = $totals['company_burden'];
    $items_json     = json_encode($items, JSON_UNESCAPED_UNICODE);

    $sql = "INSERT INTO repairs
              (vehicle_id, repair_date, vendor, category, mileage, repair_content, repair_cost, company_burden, items_json)
            VALUES
              (:vehicle_id, :repair_date, :vendor, :category, :mileage, :repair_content, :repair_cost, :company_burden, :items_json)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
      ':vehicle_id'     => $vehicle_id,
      ':repair_date'    => $repair_date,
      ':vendor'         => $vendor,
      ':category'       => $category,
      ':mileage'        => $mileage_sql,
      ':repair_content' => $repair_content,
      ':repair_cost'    => $repair_cost,
      ':company_burden' => $company_burden,
      ':items_json'     => $items_json,
    ]);

    redirect_back($back_page, $per_page, $return_to);

  } elseif ($action === 'edit') {
    $repair_id      = trim((string)($_POST['repair_id'] ?? ''));
    $vehicle_id     = trim((string)($_POST['vehicle_id'] ?? ''));
    $repair_date    = trim((string)($_POST['repair_date'] ?? ''));
    $vendor         = trim((string)($_POST['vendor'] ?? ''));
    $repair_content = trim((string)($_POST['repair_content'] ?? ''));

    $category = trim((string)($_POST['category'] ?? ''));
    $mileage  = trim((string)($_POST['mileage'] ?? ''));

    if (!in_array($category, ['保養','維修'], true)) {
      redirect_back($back_page, $per_page, $return_to);
    }
    if ($category === '保養' && ($mileage === '' || !ctype_digit($mileage))) {
      redirect_back($back_page, $per_page, $return_to);
    }
    $mileage_sql = ($category === '保養') ? (int)$mileage : null;

    $items_json_raw = $_POST['items_json'] ?? '[]';
    $items = json_decode($items_json_raw, true);
    if (!is_array($items)) $items = [];
    $totals = calc_totals_from_items($items);
    $repair_cost    = $totals['repair_cost'];
    $company_burden = $totals['company_burden'];
    $items_json     = json_encode($items, JSON_UNESCAPED_UNICODE);

    $sql = "UPDATE repairs SET
              vehicle_id     = :vehicle_id,
              repair_date    = :repair_date,
              vendor         = :vendor,
              category       = :category,
              mileage        = :mileage,
              repair_content = :repair_content,
              repair_cost    = :repair_cost,
              company_burden = :company_burden,
              items_json     = :items_json
            WHERE repair_id   = :repair_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
      ':vehicle_id'     => $vehicle_id,
      ':repair_date'    => $repair_date,
      ':vendor'         => $vendor,
      ':category'       => $category,
      ':mileage'        => $mileage_sql,
      ':repair_content' => $repair_content,
      ':repair_cost'    => $repair_cost,
      ':company_burden' => $company_burden,
      ':items_json'     => $items_json,
      ':repair_id'      => $repair_id,
    ]);

    redirect_back($back_page, $per_page, $return_to);

  } elseif ($action === 'delete') {
    $repair_id = trim((string)($_POST['repair_id'] ?? ''));
    if ($repair_id !== '') {
      $stm = $conn->prepare("DELETE FROM repairs WHERE repair_id = :id");
      $stm->execute([':id' => $repair_id]);
    }

    // 避免回到空頁
    $tot = (int)$conn->query("SELECT COUNT(*) FROM repairs")->fetchColumn();
    $maxPage = max(1, (int)ceil($tot / $per_page));
    if ($back_page > $maxPage) $back_page = $maxPage;

    redirect_back($back_page, $per_page, $return_to);

  } else {
    redirect_back($back_page, $per_page, $return_to);
  }
} else {
  redirect_back($back_page, $per_page, $return_to);
}
