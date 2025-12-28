<?php
// Public/modules/mat/m_data_statistics.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/auth.php';
require_login();

$page_title = '領退料管理｜各班領退統計';

// cache busting
$cssFs = __DIR__ . '/../../assets/css/m_data_statistics.css';
$jsFs  = __DIR__ . '/../../assets/js/m_data_statistics.js';
$cssVer = is_file($cssFs) ? (string)filemtime($cssFs) : (string)time();
$jsVer  = is_file($jsFs)  ? (string)filemtime($jsFs) : (string)time();

include __DIR__ . '/../../partials/header.php';
?>
<script>
  window.PUBLIC_BASE = "<?= public_base() ?>";
  document.title = '領退料管理｜境宏工程有限公司';
  (function () {
    var l = document.querySelector('link[rel="icon"]') || document.createElement('link');
    l.rel = 'icon'; l.type = 'image/png';
    l.href = "<?= public_base() ?>/assets/imgs/JH_logo.png";
    if (!l.parentNode) document.head.appendChild(l);
  }());
</script>

<!-- page css -->
<link rel="stylesheet" href="<?= public_base() ?>/assets/css/m_data_statistics.css?v=<?= $cssVer ?>">

<div class="container-fluid scroll my-4">
  <h3 class="text-center">各班領退統計</h3>
  <div class="text-center mb-4">
    <p>領退料時間：<span id="displayDate"></span></p>
    <input type="date" id="datePicker" class="form-control w-auto mx-auto d-inline-block">
    <button id="exportPDF" class="btn btn-secondary ms-2">列印報表</button>
  </div>

  <?php foreach (['A','B','C','D','E','F'] as $shift): ?>
    <div class="card shadow border-0 mb-3">
      <div class="card-header bg-secondary text-white text-center">
        <h5 id="class_<?= $shift ?>" class="mb-0">動態生成標題</h5>
      </div>
      <div class="card-body table-responsive text-nowrap">
        <table class="table table-bordered table-striped table-hover text-center rounded">
          <thead class="bg-light text-dark">
            <?php if ($shift === 'A' || $shift === 'C'): ?>
              <tr>
                <th style="width:5%;" rowspan="2">項次</th>
                <th class="hidden" rowspan="2">材料編號</th>
                <th style="width:65%;" rowspan="2">材料名稱</th>
                <th style="width:10%;" colspan="2">領料</th>
                <th style="width:10%;" colspan="2">退料</th>
                <th style="width:10%;" colspan="2">領、退料合計</th>
              </tr>
              <tr>
                <th style="width:5%;">新</th>
                <th style="width:5%;">舊</th>
                <th style="width:5%;">新</th>
                <th style="width:5%;">舊</th>
                <th style="width:5%;">新</th>
                <th style="width:5%;">舊</th>
              </tr>
            <?php elseif ($shift === 'B'): ?>
              <tr>
                <th style="width:5%;" rowspan="2">項次</th>
                <th class="hidden" rowspan="2">材料編號</th>
                <th style="width:30%;" rowspan="2">材料名稱</th>
                <th style="width:29%;" colspan="4">領料</th>
                <th style="width:29%;" colspan="4">退料</th>
                <th style="width:7%;" colspan="2">領、退料合計</th>
              </tr>
              <tr>
                <th style="width:15%;" colspan="2">新</th>
                <th style="width:14%;" colspan="2">舊</th>
                <th style="width:15%;" colspan="2">新</th>
                <th style="width:14%;" colspan="2">舊</th>
                <th style="width:4%;">新</th>
                <th style="width:3%;">舊</th>
              </tr>
            <?php elseif ($shift === 'D'): ?>
              <tr>
                <th style="width:5%;" rowspan="2">項次</th>
                <th class="hidden" rowspan="2">材料編號</th>
                <th style="width:40%;" rowspan="2">材料名稱</th>
                <th style="width:16%;" colspan="2">領料</th>
                <th style="width:16%;" colspan="2">退料</th>
                <th style="width:7%;" rowspan="2">對帳資料</th>
                <th style="width:16%;" colspan="2">領、退料合計</th>
              </tr>
              <tr>
                <th style="width:8%;">新</th>
                <th style="width:8%;">舊</th>
                <th style="width:8%;">新</th>
                <th style="width:8%;">舊</th>
                <th style="width:8%;">新</th>
                <th style="width:8%;">舊</th>
              </tr>
            <?php else: /* E / F */ ?>
              <tr>
                <th style="width:5%;" rowspan="2">項次</th>
                <th class="hidden" rowspan="2">材料編號</th>
                <th style="width:55%;" rowspan="2">材料名稱</th>
                <th style="width:20%;" colspan="2">領料</th>
                <th style="width:20%;" colspan="2">退料</th>
              </tr>
              <tr>
                <th style="width:10%;">新</th>
                <th style="width:10%;">舊</th>
                <th style="width:10%;">新</th>
                <th style="width:10%;">舊</th>
              </tr>
            <?php endif; ?>
          </thead>
          <tbody id="statistics-body-<?= strtolower($shift) ?>"><!-- 動態插入 --></tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- ICON -->
<div id="dynamic-icons" class="position-fixed bottom-0 end-0 m-3">
  <?php foreach (['A','B','C','D','E','F'] as $s): ?>
    <button id="icon-<?= $s ?>" class="btn btn-primary btn-sm rounded-circle mb-2" style="display:none;"><?= $s ?></button>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
<script src="<?= public_base() ?>/assets/js/m_data_statistics.js?v=<?= $jsVer ?>"></script>
