<?php
// Public/modules/car/car_repair.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/auth.php';
require_login();
require_once __DIR__ . '/../../../config/db_connection.php';

// 頁面標題
$page_title = '車輛管理 - 維修紀錄';

// 版本戳（避免快取）
$cssFs = __DIR__ . '/../../assets/css/car_repair.css';
$cssVer = is_file($cssFs) ? (string)filemtime($cssFs) : (string)time();
$jsFs  = __DIR__ . '/../../assets/js/car_repair.js';
$jsVer = is_file($jsFs) ? (string)filemtime($jsFs) : (string)time();

// 取清單/分頁/下拉（include 只做查詢，不輸出）
include __DIR__ . '/car_repair_backend.php';

// 共用 header
include __DIR__ . '/../../partials/header.php';
?>
<script>window.PUBLIC_BASE = "<?= public_base() ?>";</script>

<link rel="stylesheet" href="<?= public_base() ?>/assets/css/car_repair.css?v=<?= htmlspecialchars($cssVer, ENT_QUOTES) ?>">

<script>
  document.title = '車輛管理｜境宏工程有限公司';
  (function () {
    var l = document.querySelector('link[rel="icon"]') || document.createElement('link');
    l.rel = 'icon'; l.type = 'image/png';
    l.href = "<?= public_base() ?>/assets/imgs/JH_logo.png";
    if (!l.parentNode) document.head.appendChild(l);
  }());
</script>

<div class="container-fluid mt-3">
  <h3 class="text-center">車輛管理－維修紀錄</h3>

  <div class="row align-items-center my-3">
    <div class="col-6">
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRepairModal">新增維修紀錄</button>
    </div>
    <div class="col-6">
      <?php
      $page_window = 10;
      $start_page = (int)floor(($pagination['current_page'] - 1) / $page_window) * $page_window + 1;
      $end_page   = min($start_page + $page_window - 1, $pagination['total_pages']);
      $prev_block_start = max($start_page - $page_window, 1);
      $next_block_start = min($start_page + $page_window, $pagination['total_pages']);
      $has_prev_block = $start_page > 1;
      $has_next_block = $end_page < $pagination['total_pages'];
      ?>
      <nav aria-label="Page navigation">
        <ul class="pagination justify-content-end mb-0">
          <li class="page-item <?= $has_prev_block ? '' : 'disabled' ?>">
            <a class="page-link" href="<?= $has_prev_block ? '?page=' . $prev_block_start . '&per_page=' . $pagination['records_per_page'] : '#' ?>">前10頁</a>
          </li>
          <li class="page-item <?= ($pagination['current_page'] <= 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= ($pagination['current_page'] > 1) ? '?page=' . ($pagination['current_page'] - 1) . '&per_page=' . $pagination['records_per_page'] : '#' ?>" aria-label="Previous">
              <span aria-hidden="true">&laquo;</span>
            </a>
          </li>
          <?php for ($p = $start_page; $p <= $end_page; $p++): ?>
            <li class="page-item <?= ($p == $pagination['current_page']) ? 'active' : '' ?>">
              <a class="page-link" href="?page=<?= $p ?>&per_page=<?= $pagination['records_per_page'] ?>"><?= $p ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= ($pagination['current_page'] >= $pagination['total_pages']) ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= ($pagination['current_page'] < $pagination['total_pages']) ? '?page=' . ($pagination['current_page'] + 1) . '&per_page=' . $pagination['records_per_page'] : '#' ?>" aria-label="Next">
              <span aria-hidden="true">&raquo;</span>
            </a>
          </li>
          <li class="page-item <?= $has_next_block ? '' : 'disabled' ?>">
            <a class="page-link" href="<?= $has_next_block ? '?page=' . $next_block_start . '&per_page=' . $pagination['records_per_page'] : '#' ?>">後10頁</a>
          </li>
        </ul>
      </nav>
    </div>
  </div>

  <div class="table-responsive text-nowrap">
    <table class="table table-bordered table-striped text-center align-middle">
      <thead>
        <tr>
          <th>項次</th>
          <th>車輛編號</th>
          <th>車牌號碼</th>
          <th>維修日期</th>
          <th>維修廠商</th>
          <th>維修類別</th>
          <th>里程數</th>
          <th>使用人</th>
          <th>維修項目</th>
          <th>維修金額</th>
          <th>公司負擔</th>
          <th>備註</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody id="repairTableBody">
      <?php if (!empty($repairs)): ?>
        <?php foreach ($repairs as $index => $repair): ?>
          <?php
          $itemsArr = json_decode($repair['items_json'] ?? '[]', true) ?: [];
          $parts = [];
          $titleLines = [];
          foreach ($itemsArr as $it) {
            $c = trim((string)($it['content'] ?? ''));
            $amt = isset($it['amount']) ? (float)$it['amount'] : null;
            if ($c !== '') {
              if ($amt !== null) {
                $amt_fmt = number_format($amt, 0);
                $parts[] = "{$c}（{$amt_fmt}元）";
                $titleLines[] = "{$c}：{$amt_fmt}元";
              } else {
                $parts[] = $c;
                $titleLines[] = $c;
              }
            }
          }
          $itemsText  = implode('、', $parts);
          $itemsTitle = implode("\n", $titleLines);
          $noteText   = (string)($repair['repair_content'] ?? '');
          $items_b64  = base64_encode($repair['items_json'] ?? '[]');
          ?>
          <tr>
            <td><?= $index + 1 + ($pagination['current_page'] - 1) * $pagination['records_per_page'] ?></td>
            <td><?= htmlspecialchars($repair['vehicle_id']) ?></td>
            <td><?= htmlspecialchars($repair['license_plate']) ?></td>
            <td><?= htmlspecialchars($repair['repair_date']) ?></td>
            <td><?= htmlspecialchars($repair['vendor'] ?? '') ?></td>
            <td><?= htmlspecialchars($repair['category'] ?? '') ?></td>
            <td><?= isset($repair['mileage']) ? number_format((int)$repair['mileage']) : '' ?></td>
            <td><?= htmlspecialchars($repair['user']) ?></td>
            <td class="text-start" title="<?= htmlspecialchars($itemsTitle) ?>">
              <?= htmlspecialchars($itemsText) ?>
            </td>
            <td class="text-end"><?= number_format((float)($repair['repair_cost'] ?: 0), 0) ?>元</td>
            <td class="text-end"><?= number_format((float)($repair['company_burden'] ?? 0), 0) ?>元</td>
            <td class="text-start" title="<?= htmlspecialchars($noteText) ?>">
              <?= htmlspecialchars($noteText) ?>
            </td>
            <td>
              <button class="btn btn-outline-primary btn-sm edit-btn"
                      data-bs-toggle="modal" data-bs-target="#editRepairModal"
                      data-repair_id="<?= htmlspecialchars($repair['repair_id']) ?>"
                      data-vehicle_id="<?= htmlspecialchars($repair['vehicle_id']) ?>"
                      data-repair_date="<?= htmlspecialchars($repair['repair_date']) ?>"
                      data-repair_content="<?= htmlspecialchars($repair['repair_content']) ?>"
                      data-repair_cost="<?= htmlspecialchars($repair['repair_cost']) ?>"
                      data-company_burden="<?= htmlspecialchars($repair['company_burden'] ?? '0') ?>"
                      data-vendor="<?= htmlspecialchars($repair['vendor'] ?? '') ?>"
                      data-category="<?= htmlspecialchars($repair['category'] ?? '') ?>"
                      data-mileage="<?= htmlspecialchars($repair['mileage'] ?? '') ?>"
                      data-items-b64="<?= htmlspecialchars($items_b64, ENT_QUOTES, 'UTF-8') ?>">
                編輯
              </button>
              <button class="btn btn-outline-danger btn-sm delete-btn"
                      data-bs-toggle="modal" data-bs-target="#deleteRepairModal"
                      data-repair_id="<?= htmlspecialchars($repair['repair_id']) ?>">
                刪除
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="13">目前沒有維修紀錄。</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- 新增 Modal -->
<div class="modal fade" id="addRepairModal" tabindex="-1" aria-labelledby="addRepairModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="addRepairModalLabel" class="modal-title">新增維修紀錄</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
      </div>
      <form action="car_repair_action.php" method="post">
        <input type="hidden" name="page" value="<?= (int)$pagination['current_page'] ?>">
        <input type="hidden" name="per_page" value="<?= (int)$pagination['records_per_page'] ?>">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="items_json" id="addItemsJson">
        <div class="modal-body">
          <div class="mb-3">
            <label for="addVehicleId" class="form-label">車輛</label>
            <select class="form-select" id="addVehicleId" name="vehicle_id" required>
              <option value="" disabled selected>請選擇車輛</option>
              <?php foreach ($vehicles as $v): ?>
                <option value="<?= htmlspecialchars($v['vehicle_id']) ?>"><?= htmlspecialchars($v['license_plate']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="addRepairDate">維修日期</label>
              <input type="date" class="form-control" id="addRepairDate" name="repair_date" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="addVendor">維修廠商</label>
              <input type="text" class="form-control" id="addVendor" name="vendor" placeholder="例：XX保修廠" list="vendorList" autocomplete="off" required>
            </div>
          </div>

          <div class="mt-3">
            <label class="form-label d-block">維修類別<span class="text-danger">*</span></label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="category" id="catMaintain" value="保養" required>
              <label class="form-check-label" for="catMaintain">保養</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="category" id="catRepair" value="維修" required>
              <label class="form-check-label" for="catRepair">維修</label>
            </div>
          </div>

          <div class="mt-2" id="mileageBlock" style="display:none;">
            <label class="form-label" for="addMileage">里程數（公里）<span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="addMileage" name="mileage" min="0" step="1">
          </div>

          <div class="mt-3 mb-2 d-flex justify-content-between align-items-center">
            <label class="form-label m-0">維修項目（內容＋金額＋公司負擔，可多筆）</label>
            <button type="button" class="btn btn-sm btn-outline-success" id="btnAddItem">＋新增項目</button>
          </div>
          <div id="itemsContainer">
            <div class="row g-2 align-items-center item-row">
              <div class="col-6">
                <input type="text" class="form-control item-content" placeholder="維修內容" required>
              </div>
              <div class="col-2">
                <input type="number" class="form-control item-amount" placeholder="維修金額" step="1" min="0" required>
              </div>
              <div class="col-3">
                <input type="number" class="form-control item-company" placeholder="公司負擔金額" step="1" min="0">
              </div>
              <div class="col-1">
                <button type="button" class="btn btn-outline-danger w-100 btnRemoveItem" title="刪除此項目">&times;</button>
              </div>
            </div>
          </div>

          <div class="mb-3 mt-3">
            <label class="form-label" for="addRepairContent">備註</label>
            <textarea class="form-control" id="addRepairContent" name="repair_content" rows="3"></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label" for="addRepairCost">維修金額（自動合計）</label>
            <input type="number" class="form-control" id="addRepairCost" name="repair_cost" required readonly>
          </div>
          <div class="mb-3">
            <label class="form-label" for="addCompanyBurden">公司負擔（自動合計）</label>
            <input type="number" class="form-control" id="addCompanyBurden" name="company_burden" required readonly>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">取消</button>
          <button class="btn btn-primary" type="submit">新增</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 編輯 Modal -->
<div class="modal fade" id="editRepairModal" tabindex="-1" aria-labelledby="editRepairModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 id="editRepairModalLabel" class="modal-title">編輯維修紀錄</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
      </div>
      <form action="car_repair_action.php" method="post">
        <input type="hidden" name="page" value="<?= (int)$pagination['current_page'] ?>">
        <input type="hidden" name="per_page" value="<?= (int)$pagination['records_per_page'] ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" id="editRepairId" name="repair_id">
        <input type="hidden" name="items_json" id="editItemsJson">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="editVehicleId">車輛</label>
            <select class="form-select" id="editVehicleId" name="vehicle_id" required>
              <?php foreach ($vehicles as $v): ?>
                <option value="<?= htmlspecialchars($v['vehicle_id']) ?>"><?= htmlspecialchars($v['license_plate']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="editRepairDate">維修日期</label>
              <input type="date" class="form-control" id="editRepairDate" name="repair_date" required>
            </div>
            <div class="col-md-8">
              <label class="form-label" for="editVendor">維修廠商</label>
              <input type="text" class="form-control" id="editVendor" name="vendor" list="vendorList" autocomplete="off" required>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label d-block">維修類別<span class="text-danger">*</span></label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="category" id="editCatMaintain" value="保養" required>
              <label class="form-check-label" for="editCatMaintain">保養</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="category" id="editCatRepair" value="維修" required>
              <label class="form-check-label" for="editCatRepair">維修</label>
            </div>
          </div>
          <div class="mt-2" id="mileageBlockEdit" style="display:none;">
            <label class="form-label" for="editMileage">里程數（公里）<span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="editMileage" name="mileage" min="0" step="1">
          </div>
          <div class="mt-3 mb-2 d-flex justify-content-between align-items-center">
            <label class="form-label m-0">維修項目（內容＋金額＋公司負擔）</label>
            <button type="button" class="btn btn-sm btn-outline-success" id="btnAddItemEdit">＋新增項目</button>
          </div>
          <div id="itemsContainerEdit"></div>
          <div class="mb-3 mt-3">
            <label class="form-label" for="editRepairContent">備註</label>
            <textarea class="form-control" id="editRepairContent" name="repair_content" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label" for="editRepairCost">維修金額（自動合計）</label>
            <input type="number" class="form-control" id="editRepairCost" name="repair_cost" required readonly>
          </div>
          <div class="mb-3">
            <label class="form-label" for="editCompanyBurden">公司負擔（自動合計）</label>
            <input type="number" class="form-control" id="editCompanyBurden" name="company_burden" required readonly>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">取消</button>
          <button class="btn btn-primary" type="submit">儲存修改</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 刪除 Modal -->
<div class="modal fade" id="deleteRepairModal" tabindex="-1" aria-labelledby="deleteRepairModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="car_repair_action.php">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteRepairModalLabel">刪除維修紀錄</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
      </div>
      <div class="modal-body">
        <p>確定要刪除此維修紀錄嗎？</p>
        <input type="hidden" name="page" value="<?= (int)$pagination['current_page'] ?>">
        <input type="hidden" name="per_page" value="<?= (int)$pagination['records_per_page'] ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" id="deleteRepairId" name="repair_id">
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">取消</button>
        <button class="btn btn-danger" type="submit">刪除</button>
      </div>
    </form>
  </div>
</div>

<datalist id="vendorList">
  <?php if (!empty($vendors_list)): ?>
    <?php foreach ($vendors_list as $v): ?>
      <option value="<?= htmlspecialchars($v) ?>"></option>
    <?php endforeach; ?>
  <?php endif; ?>
</datalist>

<?php include __DIR__ . '/../../partials/footer.php'; ?>

<!-- 依照新站資源路徑 -->
<script src="<?= public_base() ?>/assets/js/car_repair.js?v=<?= htmlspecialchars($jsVer, ENT_QUOTES) ?>"></script>
