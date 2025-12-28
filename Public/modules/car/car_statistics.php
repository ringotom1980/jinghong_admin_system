<?php
// Public/modules/car/car_statistics.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/auth.php';
require_login();

$page_title = '車輛管理 - 維修統計';

// cache-busting
$cssFs = __DIR__ . '/../../assets/css/car_statistics.css';
$cssVer = is_file($cssFs) ? (string)filemtime($cssFs) : (string)time();
$jsFs  = __DIR__ . '/../../assets/js/car_statistics.js';
$jsVer = is_file($jsFs) ? (string)filemtime($jsFs) : (string)time();

include __DIR__ . '/../../partials/header.php';
?>
<script>window.PUBLIC_BASE = "<?= public_base() ?>";</script>

<link rel="stylesheet" href="<?= public_base() ?>/assets/css/car_statistics.css?v=<?= htmlspecialchars($cssVer, ENT_QUOTES) ?>">

<script>
  document.title = '車輛管理｜境宏工程有限公司';
  (function() {
    var l = document.querySelector('link[rel="icon"]') || document.createElement('link');
    l.rel = 'icon';
    l.type = 'image/png';
    l.href = "<?= public_base() ?>/assets/imgs/JH_logo.png";
    if (!l.parentNode) document.head.appendChild(l);
  }());
</script>

<div class="container-fluid mt-3">
  <h3 class="text-center mb-4">車輛管理-維修統計</h3>

  <!-- 查詢條件（精簡版：全部 / 年度 / 半年度 / 月份；任何變更即觸發查詢） -->
  <div class="mb-3">
    <form id="filterForm" class="row g-3 align-items-center">
      <div class="col-auto"><label for="filterType" class="col-form-label">查詢類型</label></div>
      <div class="col-auto">
        <select id="filterType" class="form-select">
          <option value="all">全部</option>
          <option value="year">年度</option>
          <option value="half_year">半年度</option>
          <option value="month">月份</option>
        </select>
      </div>

      <div id="secondaryFilter" class="col-auto"></div>
      <div id="tertiaryFilter" class="col-auto"></div>

      <!-- 不再需要查詢按鈕，改為動態載入 -->
      <div class="col-auto">
        <button type="button" class="btn btn-outline-secondary" id="btnPrint" data-bs-toggle="modal" data-bs-target="#printModal">列印</button>
      </div>
    </form>
  </div>

  <div class="row">
    <!-- 左：維修統計 -->
    <div class="col-lg-6 col-12">
      <table class="table table-bordered border-primary table-hover text-center" id="statisticsTable">
        <thead>
          <tr>
            <th>車輛編號</th>
            <th>車牌號碼</th>
            <th>維修總次數</th>
            <th>維修總金額</th>
            <th>公司負擔</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
      <div id="totalAmount" class="alert alert-primary text-end mt-3 font-weight-bold">
        維修金額 0 元｜公司負擔 0 元
      </div>
    </div>

    <!-- 右：維修詳細 -->
    <div class="col-lg-6 col-12">
      <table class="table table-striped table-borderless border-primary text-center table-font-size" id="repairTable" style="table-layout:fixed;">
        <colgroup>
          <col style="width:10%">
          <col style="width:15%">
          <col style="width:15%">
          <col style="width:35%">
          <col style="width:12.5%">
          <col style="width:12.5%">
        </colgroup>
        <thead>
          <tr>
            <th>車輛編號</th>
            <th>車牌號碼</th>
            <th>維修日期</th>
            <th>維修內容</th>
            <th>維修金額</th>
            <th>公司負擔</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<!-- 列印 Modal（原樣保留） -->
<div class="modal fade" id="printModal" tabindex="-1" aria-labelledby="printModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="printModalLabel">選擇列印內容</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
      </div>
      <div class="modal-body">
        <div class="form-check">
          <input class="form-check-input" type="radio" name="printType" id="printTypeSummary" value="summary" checked>
          <label class="form-check-label" for="printTypeSummary">維修統計表</label>
        </div>
        <div class="form-check mt-2">
          <input class="form-check-input" type="radio" name="printType" id="printTypeDetails" value="details">
          <label class="form-check-label" for="printTypeDetails">各車維修明細</label>
        </div>
        <div id="printCurrentVehicleWrap" class="mt-3" style="display:none;">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="printCurrentVehicle">
            <label class="form-check-label" for="printCurrentVehicle">列印目前車輛維修明細</label>
          </div>
          <div class="form-text" id="currentVehicleHint">尚未選擇車輛（請在左側列表點選一列）</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
        <button type="button" class="btn btn-primary" id="printGo">產生 PDF</button>
      </div>
    </div>
  </div>
</div>
<!-- 回頂端 -->
<button id="btnBackToTop" type="button" class="btn btn-primary" aria-label="回頂端">↑</button>


<?php include __DIR__ . '/../../partials/footer.php'; ?>
<script src="<?= public_base() ?>/assets/js/car_statistics.js?v=<?= htmlspecialchars($jsVer, ENT_QUOTES) ?>"></script>
