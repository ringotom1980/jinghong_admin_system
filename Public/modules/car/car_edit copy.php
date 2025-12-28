<?php
// Public/modules/car/car_edit.php
declare(strict_types=1);

// 權限與 DB（與 mat 模組相同層級）
require_once __DIR__ . '/../../../config/auth.php';
require_login();
require_once __DIR__ . '/../../../config/db_connection.php';

$page_title = '車輛管理 - 基本資料';

// Cache busting for CSS/JS
$cssFs = __DIR__ . '/../../assets/css/car_edit.css';
$cssVer = is_file($cssFs) ? (string)filemtime($cssFs) : (string)time();
$jsFs  = __DIR__ . '/../../assets/js/car_edit.js';
$jsVer = is_file($jsFs) ? (string)filemtime($jsFs) : (string)time();

// 取車輛清單（以車輛編號排序）
$stmt = $conn->prepare('SELECT * FROM vehicles ORDER BY vehicle_id ASC');
$stmt->execute();
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 小工具：價格顯示
$fmtPrice = function ($v): string {
  if ($v === null) return '';
  $n = (int)$v;
  return $n > 0 ? number_format($n) : '';
};
// 小工具：圖片 URL（兼容舊資料 uploads/... 與新路徑 assets/imgs/cars/...）
$imgUrl = function (?string $p): string {
  if (!$p) return '';
  $p = ltrim($p, '/');
  return public_base() . '/' . $p;
};
// 小工具：日期顯示（行車紀錄/廢氣：若旗標為0或日期空/0000-00-00 → 顯示「不須檢驗」）
$showDate = function (?string $date, ?int $requiredFlag = 1): string {
  if ((int)$requiredFlag === 0) return '不須檢驗';
  if (!$date || $date === '0000-00-00') return '不須檢驗';
  return htmlspecialchars($date, ENT_QUOTES);
};
?>
<?php include __DIR__ . '/../../partials/header.php'; ?>

<script>window.PUBLIC_BASE = "<?= public_base() ?>";</script>

<!-- 頁面專用 CSS -->
<link rel="stylesheet" href="<?= public_base() ?>/assets/css/car_edit.css?v=<?= htmlspecialchars($cssVer, ENT_QUOTES) ?>">

<!-- 自訂頁籤標題與 favicon -->
<script>
  document.title = '車輛管理｜境宏工程有限公司';
  (function () {
    var l = document.querySelector('link[rel="icon"]') || document.createElement('link');
    l.rel = 'icon'; l.type = 'image/png';
    l.href = "<?= public_base() ?>/assets/imgs/JH_logo.png";
    if (!l.parentNode) document.head.appendChild(l);
  }());
</script>

<div class="container-fluid my-4">
  <h3 class="text-center mb-3">車輛管理－基本資料</h3>

  <!-- 工具列 -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVehicleModal" id="btnOpenAdd">新增車輛</button>
    </div>
    <small class="text-muted">共 <?= count($vehicles) ?> 輛</small>
  </div>

  <!-- 車輛列表 -->
  <div class="table-wrap text-nowrap" id="fleetTableWrap">
    <table id="vehicleTable" class="table table-bordered table-striped table-hover align-middle text-center mb-0">
      <thead>
        <tr>
          <th>項次</th>
          <th class="th-2line"><span>車輛</span><span>編號</span></th>
          <th>車牌號碼</th>
          <th class="wrap-owner">車主登記</th>
          <th>使用人</th>
          <th>車輛類型</th>
          <th>噸數</th>
          <th>車輛廠牌</th>
          <th class="th-2line"><span>車輛</span><span>年份</span></th>
          <th>車輛價格</th>
          <th>車斗價格</th>
          <th>吊臂價格</th>
          <th>吊臂型式</th>
          <th class="th-2line"><span>驗車</span><span>到期日</span></th>
          <th class="th-2line"><span>保險</span><span>到期日</span></th>
          <th class="th-2line"><span>行車紀錄</span><span>到期日</span></th>
          <th class="th-2line"><span>廢氣檢查</span><span>到期日</span></th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($vehicles as $i => $v): ?>
        <?php
          $image_path = $v['image_path'] ?? '';
          $image_url  = $image_path ? $imgUrl($image_path) : '';
        ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($v['vehicle_id'] ?? '', ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($v['license_plate'] ?? '', ENT_QUOTES) ?></td>
          <td class="wrap-owner"><span class="cell-wrap"><?= htmlspecialchars($v['owner'] ?? '', ENT_QUOTES) ?></span></td>
          <td><?= htmlspecialchars($v['user'] ?? '', ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($v['vehicle_type'] ?? '', ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($v['tonnage'] ?? '', ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($v['brand'] ?? '', ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars((string)($v['vehicle_year'] ?? ''), ENT_QUOTES) ?></td>
          <td><?= $fmtPrice($v['vehicle_price'] ?? null) ?></td>
          <td><?= $fmtPrice($v['truck_bed_price'] ?? null) ?></td>
          <td><?= $fmtPrice($v['crane_price'] ?? null) ?></td>
          <td><?= htmlspecialchars($v['crane_type'] ?? '', ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($v['inspection_date'] ?? '', ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($v['insurance_date'] ?? '', ENT_QUOTES) ?></td>
          <td><?= $showDate($v['record_date'] ?? null, (int)($v['record_required'] ?? 1)) ?></td>
          <td><?= $showDate($v['emission_date'] ?? null, (int)($v['emission_required'] ?? 1)) ?></td>
          <td>
            <button
              class="btn btn-outline-primary btn-sm btn-edit"
              data-bs-toggle="modal"
              data-bs-target="#editVehicleModal"
              data-image-url="<?= htmlspecialchars($image_url, ENT_QUOTES) ?>"
              data-image-path="<?= htmlspecialchars($image_path, ENT_QUOTES) ?>"
              data-vehicle_id="<?= htmlspecialchars($v['vehicle_id'] ?? '', ENT_QUOTES) ?>"
              data-license_plate="<?= htmlspecialchars($v['license_plate'] ?? '', ENT_QUOTES) ?>"
              data-owner="<?= htmlspecialchars($v['owner'] ?? '', ENT_QUOTES) ?>"
              data-user="<?= htmlspecialchars($v['user'] ?? '', ENT_QUOTES) ?>"
              data-vehicle_type="<?= htmlspecialchars($v['vehicle_type'] ?? '', ENT_QUOTES) ?>"
              data-tonnage="<?= htmlspecialchars((string)($v['tonnage'] ?? ''), ENT_QUOTES) ?>"
              data-brand="<?= htmlspecialchars($v['brand'] ?? '', ENT_QUOTES) ?>"
              data-vehicle_year="<?= htmlspecialchars((string)($v['vehicle_year'] ?? ''), ENT_QUOTES) ?>"
              data-vehicle_price="<?= htmlspecialchars((string)($v['vehicle_price'] ?? ''), ENT_QUOTES) ?>"
              data-truck_bed_price="<?= htmlspecialchars((string)($v['truck_bed_price'] ?? ''), ENT_QUOTES) ?>"
              data-crane_price="<?= htmlspecialchars((string)($v['crane_price'] ?? ''), ENT_QUOTES) ?>"
              data-crane_type="<?= htmlspecialchars($v['crane_type'] ?? '', ENT_QUOTES) ?>"
              data-inspection_date="<?= htmlspecialchars($v['inspection_date'] ?? '', ENT_QUOTES) ?>"
              data-insurance_date="<?= htmlspecialchars($v['insurance_date'] ?? '', ENT_QUOTES) ?>"
              data-record_required="<?= (int)($v['record_required'] ?? 1) ?>"
              data-record_date="<?= htmlspecialchars($v['record_date'] ?? '', ENT_QUOTES) ?>"
              data-emission_required="<?= (int)($v['emission_required'] ?? 1) ?>"
              data-emission_date="<?= htmlspecialchars($v['emission_date'] ?? '', ENT_QUOTES) ?>"
            >編輯</button>

            <button
              class="btn btn-outline-danger btn-sm btn-delete"
              data-bs-toggle="modal"
              data-bs-target="#deleteVehicleModal"
              data-vehicle_id="<?= htmlspecialchars($v['vehicle_id'] ?? '', ENT_QUOTES) ?>"
              data-image-url="<?= htmlspecialchars($image_url, ENT_QUOTES) ?>"
            >刪除</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- 新增車輛 Modal -->
<div class="modal fade" id="addVehicleModal" tabindex="-1" aria-labelledby="addVehicleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post" action="car_edit_backend.php?action=create" enctype="multipart/form-data" id="addVehicleForm" novalidate>
      <div class="modal-header">
        <h5 class="modal-title" id="addVehicleModalLabel">新增車輛</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-12">
            <label class="form-label">車輛圖片</label>
            <input type="file" class="form-control" id="add_image" name="vehicle_image" accept="image/*" required>
            <div class="invalid-feedback">請選擇圖片</div>
            <div class="text-danger small" id="add_image_err"></div>
          </div>
          <div class="col-6">
            <label class="form-label">車輛編號</label>
            <input type="text" class="form-control" id="add_vehicle_id" name="vehicle_id" required>
            <div class="text-danger small" id="add_vehicle_id_err"></div>
          </div>
          <div class="col-6">
            <label class="form-label">車牌號碼</label>
            <input type="text" class="form-control" id="add_license_plate" name="license_plate" required>
            <div class="text-danger small" id="add_license_plate_err"></div>
          </div>
          <div class="col-6">
            <label class="form-label">車主登記</label>
            <input type="text" class="form-control" name="owner" id="add_owner" required>
          </div>
          <div class="col-6">
            <label class="form-label">使用人</label>
            <input type="text" class="form-control" name="user" id="add_user" required>
          </div>
          <div class="col-6">
            <label class="form-label">車輛類型</label>
            <input type="text" class="form-control" name="vehicle_type" id="add_vehicle_type" required>
          </div>
          <div class="col-6">
            <label class="form-label">噸數</label>
            <input type="number" step="0.01" class="form-control" name="tonnage" id="add_tonnage" required>
          </div>
          <div class="col-6">
            <label class="form-label">車輛廠牌</label>
            <input type="text" class="form-control" name="brand" id="add_brand" required>
          </div>
          <div class="col-6">
            <label class="form-label">車輛年份</label>
            <input type="number" class="form-control" name="vehicle_year" id="add_vehicle_year" required>
          </div>
          <div class="col-4">
            <label class="form-label">車輛價格</label>
            <input type="number" class="form-control" name="vehicle_price" id="add_vehicle_price">
          </div>
          <div class="col-4">
            <label class="form-label">車斗價格</label>
            <input type="number" class="form-control" name="truck_bed_price" id="add_truck_bed_price">
          </div>
          <div class="col-4">
            <label class="form-label">吊臂價格</label>
            <input type="number" class="form-control" name="crane_price" id="add_crane_price">
          </div>
          <div class="col-6">
            <label class="form-label">吊臂型式</label>
            <input type="text" class="form-control" name="crane_type" id="add_crane_type">
          </div>
          <div class="col-6">
            <label class="form-label">驗車到期日</label>
            <input type="date" class="form-control" name="inspection_date" id="add_inspection_date" required>
          </div>
          <div class="col-6">
            <label class="form-label">保險到期日</label>
            <input type="date" class="form-control" name="insurance_date" id="add_insurance_date" required>
          </div>

          <!-- 行車紀錄（須/不須 + 日期） -->
          <div class="col-12">
            <label class="form-label d-block">行車紀錄檢驗</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="record_required" id="add_record_yes" value="1" required>
              <label class="form-check-label" for="add_record_yes">須檢驗</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="record_required" id="add_record_no" value="0" required>
              <label class="form-check-label" for="add_record_no">不須檢驗</label>
            </div>
            <div class="mt-2" id="add_record_date_wrap"><!-- 動態插入 date --></div>
          </div>

          <!-- 廢氣檢查（須/不須 + 日期） -->
          <div class="col-12">
            <label class="form-label d-block">廢氣檢查</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="emission_required" id="add_emission_yes" value="1" required>
              <label class="form-check-label" for="add_emission_yes">須檢驗</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="emission_required" id="add_emission_no" value="0" required>
              <label class="form-check-label" for="add_emission_no">不須檢驗</label>
            </div>
            <div class="mt-2" id="add_emission_date_wrap"><!-- 動態插入 date --></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <div class="me-auto text-danger small" id="add_form_err"></div>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
        <button type="submit" class="btn btn-primary" id="add_submit">新增</button>
      </div>
    </form>
  </div>
</div>

<!-- 編輯車輛 Modal -->
<div class="modal fade" id="editVehicleModal" tabindex="-1" aria-labelledby="editVehicleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" method="post" action="car_edit_backend.php?action=update" enctype="multipart/form-data" id="editVehicleForm" novalidate>
      <div class="modal-header">
        <h5 class="modal-title" id="editVehicleModalLabel">編輯車輛</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="vehicle_id" id="edit_original_vehicle_id">

        <div class="text-center mb-3">
          <img id="edit_preview_image" src="" alt="車輛圖片" class="img-fluid" style="max-height:200px;border:1px solid #ccc;border-radius:6px;">
        </div>

        <div class="row g-2">
          <div class="col-12">
            <label class="form-label">更換車輛圖片</label>
            <input type="file" class="form-control" id="edit_image" name="vehicle_image" accept="image/*">
            <div class="text-danger small" id="edit_image_err"></div>
          </div>
          <div class="col-6">
            <label class="form-label">車輛編號</label>
            <input type="text" class="form-control" id="edit_vehicle_id" name="vehicle_id_1" required>
            <div class="text-danger small" id="edit_vehicle_id_err"></div>
          </div>
          <div class="col-6">
            <label class="form-label">車牌號碼</label>
            <input type="text" class="form-control" id="edit_license_plate" name="license_plate" required>
            <div class="text-danger small" id="edit_license_plate_err"></div>
          </div>
          <div class="col-6">
            <label class="form-label">車主登記</label>
            <input type="text" class="form-control" id="edit_owner" name="owner" required>
          </div>
          <div class="col-6">
            <label class="form-label">使用人</label>
            <input type="text" class="form-control" id="edit_user" name="user" required>
          </div>
          <div class="col-6">
            <label class="form-label">車輛類型</label>
            <input type="text" class="form-control" id="edit_vehicle_type" name="vehicle_type" required>
          </div>
          <div class="col-6">
            <label class="form-label">噸數</label>
            <input type="number" step="0.01" class="form-control" id="edit_tonnage" name="tonnage" required>
          </div>
          <div class="col-6">
            <label class="form-label">車輛廠牌</label>
            <input type="text" class="form-control" id="edit_brand" name="brand" required>
          </div>
          <div class="col-6">
            <label class="form-label">車輛年份</label>
            <input type="number" class="form-control" id="edit_vehicle_year" name="vehicle_year" required>
          </div>
          <div class="col-4">
            <label class="form-label">車輛價格</label>
            <input type="number" class="form-control" id="edit_vehicle_price" name="vehicle_price">
          </div>
          <div class="col-4">
            <label class="form-label">車斗價格</label>
            <input type="number" class="form-control" id="edit_truck_bed_price" name="truck_bed_price">
          </div>
          <div class="col-4">
            <label class="form-label">吊臂價格</label>
            <input type="number" class="form-control" id="edit_crane_price" name="crane_price">
          </div>
          <div class="col-6">
            <label class="form-label">吊臂型式</label>
            <input type="text" class="form-control" id="edit_crane_type" name="crane_type">
          </div>
          <div class="col-6">
            <label class="form-label">驗車到期日</label>
            <input type="date" class="form-control" id="edit_inspection_date" name="inspection_date" required>
          </div>
          <div class="col-6">
            <label class="form-label">保險到期日</label>
            <input type="date" class="form-control" id="edit_insurance_date" name="insurance_date" required>
          </div>

          <!-- 行車紀錄（須/不須 + 日期） -->
          <div class="col-12">
            <label class="form-label d-block">行車紀錄檢驗</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="record_required" id="edit_record_yes" value="1" required>
              <label class="form-check-label" for="edit_record_yes">須檢驗</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="record_required" id="edit_record_no" value="0" required>
              <label class="form-check-label" for="edit_record_no">不須檢驗</label>
            </div>
            <div class="mt-2" id="edit_record_date_wrap">
              <!-- 若須檢驗，JS 會插入 <input type="date" name="record_date"> -->
            </div>
          </div>

          <!-- 廢氣檢查（須/不須 + 日期） -->
          <div class="col-12">
            <label class="form-label d-block">廢氣檢查</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="emission_required" id="edit_emission_yes" value="1" required>
              <label class="form-check-label" for="edit_emission_yes">須檢驗</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="emission_required" id="edit_emission_no" value="0" required>
              <label class="form-check-label" for="edit_emission_no">不須檢驗</label>
            </div>
            <div class="mt-2" id="edit_emission_date_wrap">
              <!-- 若須檢驗，JS 會插入 <input type="date" name="emission_date"> -->
            </div>
          </div>

        </div>
      </div>
      <div class="modal-footer">
        <div class="me-auto text-danger small" id="edit_form_err"></div>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
        <button type="submit" class="btn btn-primary" id="edit_submit">儲存修改</button>
      </div>
    </form>
  </div>
</div>

<!-- 刪除車輛 Modal -->
<div class="modal fade" id="deleteVehicleModal" tabindex="-1" aria-labelledby="deleteVehicleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="car_edit_backend.php?action=delete" id="deleteVehicleForm">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteVehicleModalLabel">刪除車輛</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">確定要刪除此車輛嗎？</p>
        <img id="delete_preview_image" src="" alt="車輛圖片" style="display:none;max-width:100%;height:auto;border:1px solid #ddd;border-radius:6px;">
        <input type="hidden" name="vehicle_id" id="delete_vehicle_id">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
        <button type="submit" class="btn btn-danger">刪除</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>

<!-- 本頁 JS（含事件綁定、Ajax 驗證、動態欄位） -->
<script>
  // 讓 JS 知道 backend 在同資料夾
  window.CAR_BACKEND_URL = 'car_edit_backend.php';
</script>
<script src="<?= public_base() ?>/assets/js/car_edit.js?v=<?= htmlspecialchars($jsVer, ENT_QUOTES) ?>"></script>
