<?php
// Public/modules/mat/m_material_location.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/auth.php';
require_login();

$page_title = '領退料料管理｜境宏工程';

// 版本號（避免快取）
$cssFs  = __DIR__ . '/../../assets/css/m_material_location.css';
$jsFs   = __DIR__ . '/../../assets/js/m_material_location.js';
$cssVer = is_file($cssFs) ? (string)filemtime($cssFs) : (string)time();
$jsVer  = is_file($jsFs)  ? (string)filemtime($jsFs)  : (string)time();

include __DIR__ . '/../../partials/header.php';
?>
<script>
  // 給前端 JS 用的根路徑（如 /jinghong_admin_system/Public）
  window.PUBLIC_BASE = "<?= public_base() ?>";
  // 頁籤標題與 favicon（走絕對路徑）
  document.title = '領退料管理｜境宏工程有限公司';
  (function () {
    var l = document.querySelector('link[rel="icon"]') || document.createElement('link');
    l.rel = 'icon'; l.type = 'image/png';
    l.href = "<?= public_base() ?>/assets/imgs/JH_logo.png";
    if (!l.parentNode) document.head.appendChild(l);
  }());
</script>

<!-- 僅載本頁所需 CSS（cache busting） -->
<link rel="stylesheet" href="<?= public_base() ?>/assets/css/m_material_location.css?v=<?= htmlspecialchars($cssVer, ENT_QUOTES) ?>">

<div class="container-fluid scroll my-4">
  <h3 class="text-center mb-4">材料管理（B、D 班）</h3>

  <div class="row">
    <!-- 左側：B班材料位置管理 -->
    <div class="col-lg-6 col-12">
      <div class="card shadow border-0">
        <div class="card-header bg-secondary text-white text-center">
          <h5 class="mb-0">B班材料位置管理</h5>
        </div>
        <div class="card-body table-responsive text-nowrap">
          <table class="table table-bordered table-striped table-hover text-center rounded">
            <thead class="bg-light text-dark">
              <tr>
                <th class="hidden">材料編號</th>
                <th style="width:70%;">材料名稱</th>
                <th style="width:20%;">位置</th>
                <th style="width:10%;">編輯</th>
              </tr>
            </thead>
            <tbody id="materialTableBody"><!-- 動態插入 --></tbody>
          </table>
        </div>
      </div>

      <!-- 左下：查看材料位置地圖 -->
      <div class="fixed-icon">
        <img src="<?= public_base() ?>/assets/imgs/search_zoom_icon.png" alt="" class="icon-image">
        <div class="hover-image">
          <img src="<?= public_base() ?>/assets/imgs/map.jpg" alt="地圖圖片">
        </div>
        <div>
          <span class="text-center d-block" style="font-size: 12px; color: red;">
            查看<br>材料位置
          </span>
        </div>
      </div>

      <!-- 回到頂部 -->
      <div id="backToTop" class="back-to-top hidden">
        <i class="fas fa-arrow-up"></i>
      </div>
    </div>

    <!-- 右側：D班對帳資料分組 -->
    <div class="col-lg-6 col-12">
      <div class="card shadow border-0">
        <div class="card-header bg-secondary text-white text-center">
          <h5 class="mb-0">D班對帳資料分組</h5>
        </div>
        <div class="card-body table-responsive text-nowrap">
          <table class="table table-bordered table-striped table-hover text-center rounded">
            <thead class="bg-light text-dark">
              <tr>
                <th class="hidden">對照欄位</th>
                <th style="width:50%;">對帳資料名稱</th>
                <th style="width:40%;">材料編號組合</th>
                <th style="width:10%;">編輯</th>
              </tr>
            </thead>
            <tbody id="personnelTableBody" class="rounded-bottom"><!-- 動態插入 --></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>

<!-- 僅載本頁所需 JS（cache busting） -->
<script src="<?= public_base() ?>/assets/js/m_​material_​location.js"></script>
