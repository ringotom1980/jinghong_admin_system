<?php
// 登入與權限（從 /Public/modules/mat 返回 3 層到 /config）
require_once __DIR__ . '/../../../config/auth.php';
require_login();
$page_title = '領退料管理 - 資料編輯';

// 準備 CSS 版本號（避免快取）
$cssFileFsPath = __DIR__ . '/../../assets/css/m_data_editing.css'; // 檔案系統路徑
$cssVer = is_file($cssFileFsPath) ? (string)filemtime($cssFileFsPath) : time(); // 找不到檔就用現在時間
?>

<?php include __DIR__ . '/../../partials/header.php'; ?>

<!-- 提供 /Public/ 根給前端 JS（例如 /jinghong_admin_system/Public/） -->
<script>window.PUBLIC_BASE = "<?= public_base() ?>";</script>



<!-- 你的頁面 CSS（絕對路徑 + cache busting）-->
<link rel="stylesheet" href="<?= public_base() ?>/assets/css/m_data_editing.css?v=<?= htmlspecialchars($cssVer, ENT_QUOTES) ?>">

<!-- 頁籤標題與 favicon（用絕對路徑到 /Public/assets/imgs/...） -->
<script>
  document.title = '領退料管理｜境宏工程有限公司';
  (function () {
    var l = document.querySelector('link[rel="icon"]') || document.createElement('link');
    l.rel = 'icon';
    l.type = 'image/png';
    l.href = "<?= public_base() ?>/assets/imgs/JH_logo.png";
    if (!l.parentNode) document.head.appendChild(l);
  }());
</script>

<div class="container-fluid scroll my-4">
  <h3 class="text-center mb-4">資料編輯</h3>

  <div class="row">
    <!-- 左側區域 -->
    <div class="col-lg-6 col-12">
      <div class="card shadow border-0">
        <div class="card-header bg-secondary text-white text-center">
          <h5 class="mb-0">
            <span id="dynamicPersonnelName"></span>對帳資料
            <small style="font-size:0.9rem; color:#ffffff;" id="dynamicDate">(資料時間：)</small>
          </h5>
        </div>
        <div class="card-body table-responsive text-nowrap">
          <table class="table table-bordered table-striped table-hover text-center rounded">
            <thead class="bg-light text-dark">
              <tr>
                <th class="rounded-top-left">材料名稱</th>
                <th>對帳數量</th>
              </tr>
            </thead>
            <tbody id="reconciliationTableBody" class="rounded-bottom">
              <!-- 動態插入資料 -->
            </tbody>
          </table>
          <div class="text-center mt-3">
            <div class="d-flex flex-wrap justify-content-center">
              <button class="btn btn-outline-secondary mx-2 mb-2" id="manageMaterialButton">新增、刪除項目</button>
              <button class="btn btn-outline-success mx-2 mb-2" id="editMaterialsButton">變更材料名稱或順序</button>
              <button class="btn btn-outline-primary mx-2 mb-2" id="updateReconciliationButton">更新對帳資料</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 右側區域 -->
    <div class="col-lg-6 col-12">
      <!-- 領退料時間 -->
      <div class="card shadow border-0 mb-4">
        <div class="card-header bg-secondary text-white text-center">
          <h5 class="mb-0">領退料時間</h5>
        </div>
        <div class="mx-3 my-3 d-flex flex-column flex-md-row align-items-center">
          <input type="date" class="form-control" id="withdraw_time" name="withdraw_time" style="width:100%; max-width:250px;">
          <div id="statusContainer" class="mt-3 mt-md-0 ms-md-3 d-flex flex-wrap justify-content-center"></div>
        </div>
      </div>

      <!-- 資料上傳 -->
      <div class="card shadow border-0 mb-4">
        <div class="card-header bg-secondary text-white text-center">
          <h5 class="mb-0">資料上傳</h5>
        </div>
        <div class="card-body">
          <div class="row mb-3 align-items-center border-bottom pb-3">
            <div class="col-12 col-md-3 text-nowrap text-md-end mb-2 mb-md-0">
              <label for="upload_s" class="form-label mt-2">領退料單資料上傳 (限EXCEL檔)</label>
            </div>
            <div class="col-9 col-md-6">
              <input type="file" class="form-control" id="upload_s" name="upload_s[]" multiple>
            </div>
            <div class="col-3 col-md-3 text-md-start text-center">
              <button class="btn btn-gradient-primary w-100" id="uploadButton">上傳</button>
            </div>
          </div>

          <div class="pt-1">
            <h6 class="text-center text-muted">近3個月資料</h6>
            <div class="row g-3" id="dateCardContainer">
              <!-- 動態生成小卡片 -->
            </div>
          </div>
        </div>
      </div>

      <!-- 承辦人員 -->
      <div class="card shadow border-0">
        <div class="card-header bg-secondary text-white text-center">
          <h5 class="mb-0">承辦人員</h5>
        </div>
        <div class="card-body table-responsive text-nowrap">
          <table class="table table-bordered table-striped table-hover text-center rounded">
            <thead class="bg-light text-dark">
              <tr>
                <th class="rounded-top-left">班別</th>
                <th>承辦人</th>
                <th class="rounded-top-right">編輯</th>
              </tr>
            </thead>
            <tbody id="personnelTableBody" class="rounded-bottom">
              <!-- 動態插入資料 -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>



<!-- 自訂 JS -->
<script src="<?= public_base() ?>/assets/js/m_data_editing.js"></script>
<script src="<?= public_base() ?>/assets/js/upload_handler.js"></script>
