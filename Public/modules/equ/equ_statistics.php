<?php
// Public/modules/equ/equ_statistics.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/auth.php';
require_login();

$page_title = '機具管理 - 維修統計';

// cache-busting
$cssFs = __DIR__ . '/../../assets/css/equ_statistics.css';
$cssVer = is_file($cssFs) ? (string)filemtime($cssFs) : (string)time();
$jsFs  = __DIR__ . '/../../assets/js/equ_statistics.js';
$jsVer = is_file($jsFs) ? (string)filemtime($jsFs) : (string)time();

include __DIR__ . '/../../partials/header.php';
?>
<script>window.PUBLIC_BASE = "<?= public_base() ?>";</script>

<link rel="stylesheet" href="<?= public_base() ?>/assets/css/equ_statistics.css?v=<?= htmlspecialchars($cssVer, ENT_QUOTES) ?>">

<script>
  document.title = '機具管理｜境宏工程有限公司';
  (function() {
    var l = document.querySelector('link[rel="icon"]') || document.createElement('link');
    l.rel = 'icon'; l.type = 'image/png';
    l.href = "<?= public_base() ?>/assets/imgs/JH_logo.png";
    if (!l.parentNode) document.head.appendChild(l);
  }());
</script>

<div class="container-fluid mt-3">
  <h3 class="text-center mb-4">機具管理－維修統計</h3>

  <!-- 查詢條件（全部 / 年度 / 半年度 / 月份；PDF 僅半年度可列印） -->
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

      <div class="col-auto">
        <button type="button" class="btn btn-outline-secondary" id="btnPrint">列印</button>
      </div>
    </form>
  </div>

  <div class="row">
    <!-- 左：依廠商統計（整列可點選；置中） -->
    <div class="col-lg-6 col-12">
      <table class="table table-bordered border-primary table-hover text-center" id="statisticsTable">
        <thead>
          <tr>
            <th style="width:10%">項次</th>
            <th class="text-center">廠商</th>
            <th>維修總次數</th>
            <th>維修總金額</th>
            <th>公司負擔</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
      <div id="totalAmount" class="alert alert-primary text-end mt-3 fw-bold">
        維修金額 0 元｜公司負擔 0 元
      </div>
    </div>

    <!-- 右：維修詳細 -->
    <div class="col-lg-6 col-12">
      <table class="table table-striped table-borderless border-primary text-center table-font-size" id="repairTable" style="table-layout:fixed;">
        <colgroup>
          <col style="width:18%">
          <col style="width:10%">
          <col style="width:18%">
          <col style="width:28%">
          <col style="width:13%">
          <col style="width:13%">
        </colgroup>
        <thead>
          <tr>
            <th class="text-center">日期</th>
            <th class="text-center">廠商</th>
            <th class="text-center">機具</th>
            <th class="text-center">維修內容</th>
            <th class="text-center">維修金額</th>
            <th class="text-center">公司負擔</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>
<!-- 回頂端按鈕 -->
<button id="btnBackToTop" type="button" class="btn btn-primary" aria-label="回頂端">↑</button>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
<script src="<?= public_base() ?>/assets/js/equ_statistics.js?v=<?= htmlspecialchars($jsVer, ENT_QUOTES) ?>"></script>
