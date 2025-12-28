<?php
/**
 * Public/modules/car/car_edit_backend.php
 * 功能：車輛基本資料後端（清單、驗證、上傳、建立、更新、刪除、更新到期日）
 * 相依：/config/auth.php, /config/db_connection.php (PDO, 變數 $conn)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../config/auth.php';
require_login();
require_once __DIR__ . '/../../../config/db_connection.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // 視需求限縮網域
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// ======== 可調參數 ========
$CAR_IMAGE_DIR_FS = realpath(__DIR__ . '/../../assets/imgs') . DIRECTORY_SEPARATOR . 'cars' . DIRECTORY_SEPARATOR; // 伺服器檔案系統
$CAR_IMAGE_DIR_PUBLIC = 'assets/imgs/cars/'; // 存入 DB 的相對路徑（相對 /Public）

// 確保圖片資料夾存在
if (!is_dir($CAR_IMAGE_DIR_FS)) {
  @mkdir($CAR_IMAGE_DIR_FS, 0775, true);
}

// ======== 小工具 ========
function jerr(string $msg, array $extra = [], int $code = 400) {
  http_response_code($code);
  echo json_encode(['success' => false, 'message' => $msg] + $extra, JSON_UNESCAPED_UNICODE);
  exit;
}

function jok(array $payload = [], int $code = 200) {
  http_response_code($code);
  echo json_encode(['success' => true] + $payload, JSON_UNESCAPED_UNICODE);
  exit;
}

function read_json_body(): array {
  $raw = file_get_contents('php://input');
  if ($raw === '' || $raw === false) return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

// 接受 "yyyy-mm-dd" 或中文 "不須檢驗" 等，回傳 [date|null, required(0/1)]
function normalize_optional_date($val): array {
  $trim = is_string($val) ? trim($val) : '';
  if ($trim === '' || $trim === '不須檢驗' || $trim === '本車不需檢驗') {
    return [null, 0]; // 不須檢驗
  }
  // 很寬鬆的日期判斷
  $ts = strtotime($trim);
  if ($ts === false) {
    return [null, 0];
  }
  $d = date('Y-m-d', $ts);
  return [$d, 1];
}

// 清除非數字與小數點（價格、噸數等）
function num_clean(?string $s, bool $allowFloat = true) {
  if ($s === null) return null;
  $s = preg_replace('/[^0-9\.\-]/', '', $s);
  if ($s === '' || $s === '.' || $s === '-' || $s === '-.' ) return null;
  return $allowFloat ? (float)$s : (int)round((float)$s);
}

// 僅允許刪檔在 cars 目錄底下
function safe_unlink_in_cars(string $pathFs, string $carsRootFs): void {
  $real = realpath($pathFs);
  if ($real && str_starts_with($real, $carsRootFs) && is_file($real)) {
    @unlink($real);
  }
}

// 檢查檔案副檔名
function is_allowed_image_ext(string $filename): bool {
  $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
  return in_array($ext, ['jpg','jpeg','png','gif','webp']);
}

// 驗證車輛是否存在
function vehicle_exists(PDO $conn, string $vehicle_id): bool {
  $stmt = $conn->prepare('SELECT 1 FROM vehicles WHERE vehicle_id = :id LIMIT 1');
  $stmt->execute([':id' => $vehicle_id]);
  return (bool)$stmt->fetchColumn();
}

// ======== 路由 ========
$action = $_REQUEST['action'] ?? 'list';

try {
  switch ($action) {

    // 1) 取得車輛清單（供頁面渲染）
    case 'list': {
      $stmt = $conn->prepare("
        SELECT vehicle_id, license_plate, vehicle_type, owner, user, tonnage, brand,
               vehicle_year, usage_years, vehicle_price, truck_bed_price, crane_price,
               crane_type, inspection_date, insurance_date, record_date, emission_date,
               image_path, maintenance_times, maintenance_cost, record_required, emission_required
        FROM vehicles
        ORDER BY vehicle_id ASC
      ");
      $stmt->execute();
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      jok(['data' => $rows]);
    }

    // 2) 單一欄位驗證：id / plate / file
    case 'validate': {
      $type = $_GET['type'] ?? '';
      if ($type === 'id') {
        $value = trim($_GET['value'] ?? '');
        $currentId = $_GET['current_id'] ?? ''; // 更新時排除自己
        if ($value === '') jok(['exists' => false]); // 空值交給前端自行判
        $sql = 'SELECT 1 FROM vehicles WHERE vehicle_id = :v';
        $params = [':v' => $value];
        if ($currentId !== '') {
          $sql .= ' AND vehicle_id <> :cid';
          $params[':cid'] = $currentId;
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        jok(['exists' => (bool)$stmt->fetchColumn()]);
      }
      if ($type === 'plate') {
        $value = trim($_GET['value'] ?? '');
        $currentId = $_GET['current_id'] ?? '';
        if ($value === '') jok(['exists' => false]);
        $sql = 'SELECT 1 FROM vehicles WHERE license_plate = :v';
        $params = [':v' => $value];
        if ($currentId !== '') {
          // 以 vehicle_id 排除自己（你舊前端 current_id 傳的是原始 vehicle_id）
          $sql .= ' AND vehicle_id <> :cid';
          $params[':cid'] = $currentId;
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        jok(['exists' => (bool)$stmt->fetchColumn()]);
      }
      if ($type === 'file') {
        $fileName = $_GET['file_name'] ?? '';
        if ($fileName === '' || !is_allowed_image_ext($fileName)) {
          jok(['exists' => false]);
        }
        $target = $CAR_IMAGE_DIR_FS . $fileName;
        jok(['exists' => file_exists($target)]);
      }
      jerr('無效的驗證類型');
    }

    // 3) 單獨上傳圖片（可用於編輯時先傳圖）
    case 'upload_image': {
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') jerr('無效的請求方法', [], 405);
      if (!isset($_FILES['vehicle_image'])) jerr('沒有收到檔案');

      $file = $_FILES['vehicle_image'];
      if ($file['error'] !== UPLOAD_ERR_OK) jerr('上傳錯誤碼：' . $file['error']);

      $orig = $file['name'] ?? '';
      if (!is_allowed_image_ext($orig)) jerr('檔案格式不允許');

      // 若同名存在 → 在檔名加上時間戳，避免覆蓋
      $base = pathinfo($orig, PATHINFO_FILENAME);
      $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
      $finalName = $orig;
      $dest = $CAR_IMAGE_DIR_FS . $finalName;
      if (file_exists($dest)) {
        $finalName = $base . '_' . date('Ymd_His') . '.' . $ext;
        $dest = $CAR_IMAGE_DIR_FS . $finalName;
      }

      if (!move_uploaded_file($file['tmp_name'], $dest)) jerr('移動檔案失敗，請檢查檔案權限');

      // 回傳可存 DB 的路徑（相對 /Public）
      $dbPath = $CAR_IMAGE_DIR_PUBLIC . $finalName;
      jok(['image_path' => $dbPath, 'filename' => $finalName]);
    }

    // 4) 新增車輛（含圖片）
    case 'create': {
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') jerr('無效的請求方法', [], 405);

      // 支援 multipart/form-data 或 application/json
      $data = $_POST ?: read_json_body();

      $vehicle_id    = trim($data['vehicle_id']    ?? '');
      $license_plate = trim($data['license_plate'] ?? '');
      if ($vehicle_id === '' || $license_plate === '') jerr('車輛編號與車牌號碼為必填');

      // 檢查重複
      $stmt = $conn->prepare('SELECT 1 FROM vehicles WHERE vehicle_id = :id OR license_plate = :plate LIMIT 1');
      $stmt->execute([':id' => $vehicle_id, ':plate' => $license_plate]);
      if ($stmt->fetchColumn()) jerr('車輛編號或車牌號碼已存在');

      // 其餘欄位
      $owner        = trim($data['owner'] ?? '');
      $user         = trim($data['user'] ?? '');
      $vehicle_type = trim($data['vehicle_type'] ?? '');
      $tonnage      = num_clean($data['tonnage'] ?? null, true);
      $brand        = trim($data['brand'] ?? '');
      $vehicle_year = (int) num_clean($data['vehicle_year'] ?? null, false);

      $vehicle_price   = (int) num_clean($data['vehicle_price']   ?? null, false);
      $truck_bed_price = (int) num_clean($data['truck_bed_price'] ?? null, false);
      $crane_price     = (int) num_clean($data['crane_price']     ?? null, false);
      $crane_type      = trim($data['crane_type'] ?? '');

      // 日期與是否檢驗
      [$inspection_date, $inspection_required] = normalize_optional_date($data['inspection_date'] ?? '');
      [$insurance_date,  $insurance_required ] = normalize_optional_date($data['insurance_date']  ?? '');
      [$record_date,     $record_required    ] = normalize_optional_date($data['record_date']     ?? '');
      [$emission_date,   $emission_required  ] = normalize_optional_date($data['emission_date']   ?? '');

      // remark: 你資料表 vehicles 沒有 insurance_required 欄，這邊不存；保險一律視為必填日期（若前端真要可另外加欄）

      // 圖片（支援兩種：先用 upload_image 拿到 image_path；或這裡用 multipart 一次上傳）
      $image_path = null;
      if (!empty($data['image_path'])) {
        $image_path = $data['image_path']; // 前端先呼叫 upload_image 後回填
      } elseif (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] === UPLOAD_ERR_OK) {
        $orig = $_FILES['vehicle_image']['name'];
        if (!is_allowed_image_ext($orig)) jerr('圖片格式不允許');
        $base = pathinfo($orig, PATHINFO_FILENAME);
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $finalName = $orig;
        $dest = $CAR_IMAGE_DIR_FS . $finalName;
        if (file_exists($dest)) {
          $finalName = $base . '_' . date('Ymd_His') . '.' . $ext;
          $dest = $CAR_IMAGE_DIR_FS . $finalName;
        }
        if (!move_uploaded_file($_FILES['vehicle_image']['tmp_name'], $dest)) {
          jerr('圖片移動失敗');
        }
        $image_path = $CAR_IMAGE_DIR_PUBLIC . $finalName;
      }

      $sql = "
        INSERT INTO vehicles (
          vehicle_id, license_plate, vehicle_type, owner, user, tonnage, brand,
          vehicle_year, vehicle_price, truck_bed_price, crane_price, crane_type,
          inspection_date, insurance_date, record_date, emission_date, image_path,
          record_required, emission_required
        ) VALUES (
          :vehicle_id, :license_plate, :vehicle_type, :owner, :user, :tonnage, :brand,
          :vehicle_year, :vehicle_price, :truck_bed_price, :crane_price, :crane_type,
          :inspection_date, :insurance_date, :record_date, :emission_date, :image_path,
          :record_required, :emission_required
        )
      ";
      $stmt = $conn->prepare($sql);
      $stmt->bindValue(':vehicle_id', $vehicle_id);
      $stmt->bindValue(':license_plate', $license_plate);
      $stmt->bindValue(':vehicle_type', $vehicle_type);
      $stmt->bindValue(':owner', $owner);
      $stmt->bindValue(':user', $user);
      $stmt->bindValue(':tonnage', $tonnage !== null ? (string)$tonnage : null, $tonnage !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
      $stmt->bindValue(':brand', $brand);
      $stmt->bindValue(':vehicle_year', $vehicle_year ?: null, $vehicle_year ? PDO::PARAM_INT : PDO::PARAM_NULL);
      $stmt->bindValue(':vehicle_price', $vehicle_price ?: null, $vehicle_price ? PDO::PARAM_INT : PDO::PARAM_NULL);
      $stmt->bindValue(':truck_bed_price', $truck_bed_price ?: null, $truck_bed_price ? PDO::PARAM_INT : PDO::PARAM_NULL);
      $stmt->bindValue(':crane_price', $crane_price ?: null, $crane_price ? PDO::PARAM_INT : PDO::PARAM_NULL);
      $stmt->bindValue(':crane_type', $crane_type);
      $stmt->bindValue(':inspection_date', $inspection_date);
      $stmt->bindValue(':insurance_date', $insurance_date);
      $stmt->bindValue(':record_date', $record_date);
      $stmt->bindValue(':emission_date', $emission_date);
      $stmt->bindValue(':image_path', $image_path);
      $stmt->bindValue(':record_required', $record_required, PDO::PARAM_INT);
      $stmt->bindValue(':emission_required', $emission_required, PDO::PARAM_INT);
      $stmt->execute();

      jok(['message' => '新增成功', 'vehicle_id' => $vehicle_id]);
    }

    // 5) 更新車輛（含可更名 vehicle_id_1、可換圖、處理不須檢驗）
    case 'update': {
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') jerr('無效的請求方法', [], 405);

      $data = $_POST ?: read_json_body();

      $vehicle_id   = trim($data['vehicle_id']   ?? '');   // 原始主鍵（隱藏欄位）
      $vehicle_id_1 = trim($data['vehicle_id_1'] ?? '');   // 可能的新主鍵
      if ($vehicle_id === '' || $vehicle_id_1 === '') jerr('缺少 vehicle_id/vehicle_id_1');

      if (!vehicle_exists($conn, $vehicle_id)) jerr('原始車輛不存在');

      // 若有更名：檢查新主鍵是否被占用（不是自己）
      if ($vehicle_id !== $vehicle_id_1) {
        $stmt = $conn->prepare('SELECT 1 FROM vehicles WHERE vehicle_id = :id LIMIT 1');
        $stmt->execute([':id' => $vehicle_id_1]);
        if ($stmt->fetchColumn()) jerr('新的車輛編號已存在');
      }

      // 車牌重複檢查
      $license_plate = trim($data['license_plate'] ?? '');
      if ($license_plate === '') jerr('車牌不得為空');
      $stmt = $conn->prepare('SELECT 1 FROM vehicles WHERE license_plate = :p AND vehicle_id <> :me LIMIT 1');
      $stmt->execute([':p' => $license_plate, ':me' => $vehicle_id]);
      if ($stmt->fetchColumn()) jerr('此車牌號碼已被其他車輛使用');

      $owner        = trim($data['owner'] ?? '');
      $user         = trim($data['user'] ?? '');
      $vehicle_type = trim($data['vehicle_type'] ?? '');
      $tonnage      = num_clean($data['tonnage'] ?? null, true);
      $brand        = trim($data['brand'] ?? '');
      $vehicle_year = (int) num_clean($data['vehicle_year'] ?? null, false);

      $vehicle_price   = (int) num_clean($data['vehicle_price']   ?? null, false);
      $truck_bed_price = (int) num_clean($data['truck_bed_price'] ?? null, false);
      $crane_price     = (int) num_clean($data['crane_price']     ?? null, false);
      $crane_type      = trim($data['crane_type'] ?? '');

      [$inspection_date, $inspection_required] = normalize_optional_date($data['inspection_date'] ?? '');
      [$insurance_date,  $insurance_required ] = normalize_optional_date($data['insurance_date']  ?? ''); // 不存欄位
      [$record_date,     $record_required    ] = normalize_optional_date($data['record_date']     ?? '');
      [$emission_date,   $emission_required  ] = normalize_optional_date($data['emission_date']   ?? '');

      // 先查舊圖
      $stmt = $conn->prepare('SELECT image_path FROM vehicles WHERE vehicle_id = :id');
      $stmt->execute([':id' => $vehicle_id]);
      $old = $stmt->fetch(PDO::FETCH_ASSOC);
      $oldImg = $old['image_path'] ?? null;

      // 可能換圖：支援 upload_image 先回傳的 image_path 或這裡直接傳檔
      $image_path = $data['image_path'] ?? null;
      if (!$image_path && isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] === UPLOAD_ERR_OK) {
        $orig = $_FILES['vehicle_image']['name'];
        if (!is_allowed_image_ext($orig)) jerr('圖片格式不允許');
        $base = pathinfo($orig, PATHINFO_FILENAME);
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $finalName = $orig;
        $dest = $CAR_IMAGE_DIR_FS . $finalName;
        if (file_exists($dest)) {
          $finalName = $base . '_' . date('Ymd_His') . '.' . $ext;
          $dest = $CAR_IMAGE_DIR_FS . $finalName;
        }
        if (!move_uploaded_file($_FILES['vehicle_image']['tmp_name'], $dest)) jerr('圖片移動失敗');
        $image_path = $CAR_IMAGE_DIR_PUBLIC . $finalName;
      }

      $conn->beginTransaction();

      // 更新主體欄位
      $sql = "
        UPDATE vehicles SET
          vehicle_id = :vehicle_id_new,
          license_plate = :license_plate,
          owner = :owner,
          user = :user,
          vehicle_type = :vehicle_type,
          tonnage = :tonnage,
          brand = :brand,
          vehicle_year = :vehicle_year,
          vehicle_price = :vehicle_price,
          truck_bed_price = :truck_bed_price,
          crane_price = :crane_price,
          crane_type = :crane_type,
          inspection_date = :inspection_date,
          insurance_date = :insurance_date,
          record_date = :record_date,
          emission_date = :emission_date,
          record_required = :record_required,
          emission_required = :emission_required
          " . ($image_path ? ", image_path = :image_path" : "") . "
        WHERE vehicle_id = :vehicle_id_old
      ";
      $stmt = $conn->prepare($sql);
      $stmt->bindValue(':vehicle_id_new', $vehicle_id_1);
      $stmt->bindValue(':license_plate', $license_plate);
      $stmt->bindValue(':owner', $owner);
      $stmt->bindValue(':user', $user);
      $stmt->bindValue(':vehicle_type', $vehicle_type);
      $stmt->bindValue(':tonnage', $tonnage !== null ? (string)$tonnage : null, $tonnage !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
      $stmt->bindValue(':brand', $brand);
      $stmt->bindValue(':vehicle_year', $vehicle_year ?: null, $vehicle_year ? PDO::PARAM_INT : PDO::PARAM_NULL);
      $stmt->bindValue(':vehicle_price', $vehicle_price ?: null, $vehicle_price ? PDO::PARAM_INT : PDO::PARAM_NULL);
      $stmt->bindValue(':truck_bed_price', $truck_bed_price ?: null, $truck_bed_price ? PDO::PARAM_INT : PDO::PARAM_NULL);
      $stmt->bindValue(':crane_price', $crane_price ?: null, $crane_price ? PDO::PARAM_INT : PDO::PARAM_NULL);
      $stmt->bindValue(':crane_type', $crane_type);
      $stmt->bindValue(':inspection_date', $inspection_date);
      $stmt->bindValue(':insurance_date', $insurance_date);
      $stmt->bindValue(':record_date', $record_date);
      $stmt->bindValue(':emission_date', $emission_date);
      $stmt->bindValue(':record_required', $record_required, PDO::PARAM_INT);
      $stmt->bindValue(':emission_required', $emission_required, PDO::PARAM_INT);
      if ($image_path) $stmt->bindValue(':image_path', $image_path);
      $stmt->bindValue(':vehicle_id_old', $vehicle_id);
      $stmt->execute();

      // 若主鍵變更，修正 children（例如 repairs.vehicle_id 外鍵）
      if ($vehicle_id !== $vehicle_id_1) {
        $stm2 = $conn->prepare('UPDATE repairs SET vehicle_id = :new WHERE vehicle_id = :old');
        $stm2->execute([':new' => $vehicle_id_1, ':old' => $vehicle_id]);
      }

      $conn->commit();

      // 若有換新圖，刪舊圖（限 cars 目錄）
      if ($image_path && $oldImg) {
        $oldFs = realpath(__DIR__ . '/../../' . $oldImg); // old image 路徑轉 FS
        if ($oldFs) {
          safe_unlink_in_cars($oldFs, $CAR_IMAGE_DIR_FS);
        }
      }

      jok(['message' => '更新成功', 'vehicle_id' => $vehicle_id_1]);
    }

    // 6) 僅更新到期日（你的 car_manage.php 行為）
    case 'update_dates': {
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') jerr('無效的請求方法', [], 405);
      $data = $_POST ?: read_json_body();

      $vehicle_id = trim($data['vehicle_id'] ?? '');
      if ($vehicle_id === '') jerr('缺少 vehicle_id');

      [$inspection_date, $inspection_required] = normalize_optional_date($data['inspection_date'] ?? '');
      [$insurance_date,  $insurance_required ] = normalize_optional_date($data['insurance_date']  ?? '');
      [$record_date,     $record_required    ] = normalize_optional_date($data['record_date']     ?? '');
      [$emission_date,   $emission_required  ] = normalize_optional_date($data['emission_date']   ?? '');

      $sql = "
        UPDATE vehicles SET
          inspection_date = :inspection_date,
          insurance_date  = :insurance_date,
          record_date     = :record_date,
          emission_date   = :emission_date,
          record_required   = :record_required,
          emission_required = :emission_required
        WHERE vehicle_id = :vehicle_id
      ";
      $stmt = $conn->prepare($sql);
      $stmt->execute([
        ':inspection_date'   => $inspection_date,
        ':insurance_date'    => $insurance_date,
        ':record_date'       => $record_date,
        ':emission_date'     => $emission_date,
        ':record_required'   => $record_required,
        ':emission_required' => $emission_required,
        ':vehicle_id'        => $vehicle_id,
      ]);
      jok(['message' => '日期更新成功']);
    }

    // 7) 刪除車輛（含刪圖、on delete cascade repairs 會自動刪）
    case 'delete': {
      if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        jerr('無效的請求方法', [], 405);
      }

      // 允許 x-www-form-urlencoded 或 JSON 或 ?vehicle_id=...
      $vehicle_id = $_POST['vehicle_id'] ?? (read_json_body()['vehicle_id'] ?? ($_GET['vehicle_id'] ?? ''));
      $vehicle_id = trim((string)$vehicle_id);
      if ($vehicle_id === '') jerr('缺少 vehicle_id');

      // 取圖片路徑
      $stmt = $conn->prepare('SELECT image_path FROM vehicles WHERE vehicle_id = :id');
      $stmt->execute([':id' => $vehicle_id]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$row) jerr('車輛不存在', [], 404);

      $img = $row['image_path'] ?? null;

      $stm2 = $conn->prepare('DELETE FROM vehicles WHERE vehicle_id = :id');
      $stm2->execute([':id' => $vehicle_id]);

      if ($stm2->rowCount() > 0 && $img) {
        $imgFs = realpath(__DIR__ . '/../../' . $img);
        if ($imgFs) {
          safe_unlink_in_cars($imgFs, $CAR_IMAGE_DIR_FS);
        }
      }

      jok(['message' => '刪除成功']);
    }

    default:
      jerr('無效的 action 參數：' . $action, [], 404);
  }

} catch (PDOException $e) {
  jerr('資料庫錯誤：' . $e->getMessage(), [], 500);
} catch (Throwable $e) {
  jerr('系統錯誤：' . $e->getMessage(), [], 500);
}

/**
 * 備註：
 * - 若你想延續舊資料的「不須檢驗=0000-00-00」寫法，可把 normalize_optional_date() 中的 null 改為 '0000-00-00'。
 * - 目前僅對保險沒有 required 欄（schema 無），其餘 two flags: record_required, emission_required 已處理。
 * - 若保險也要做 required flag，可在 vehicles 新增欄位，再同樣帶入。
 */
