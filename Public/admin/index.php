<?php
require_once __DIR__ . '/../../config/auth.php';
require_admin();
$page_title = '管理中心｜境宏工程';
?>

<!-- 這兩行是關鍵：把 CSS 指回 /Public/ 下，不用動其他頁 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"><!-- 若有用 bi-* 圖示 -->
<!-- 設定分頁標題與 favicon（不改結構、不新增檔案） -->
<script>
  document.title = '管理中心｜境宏工程';
  (function() {
    var l = document.querySelector('link[rel="icon"]') || document.createElement('link');
    l.rel = 'icon';
    l.type = 'image/png';
    l.href = '../assets/imgs/JH_logo.png'; // 這一頁在 /Public/admin/，退一層剛好對到 /Public/assets/...
    if (!l.parentNode) document.head.appendChild(l);
  }());
</script>
<?php include __DIR__ . '/../partials/header.php'; ?>

<div class="container py-5">
  <div class="text-center mb-5">
    <h1 class="fw-bold text-primary">管理中心</h1>
    <p class="text-muted">請選擇您要進行的管理操作</p>
  </div>

  <div class="row g-4 justify-content-center">
    <div class="col-md-4">
      <div class="card shadow-sm h-100 border-0">
        <div class="card-body text-center">
          <i class="bi bi-key fs-1 text-primary mb-3"></i>
          <h5 class="card-title">Admin 密碼變更</h5>
          <p class="card-text text-muted">修改管理者登入密碼以確保系統安全。</p>
          <a href="change_admin_password.php" class="btn btn-primary">前往</a>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card shadow-sm h-100 border-0">
        <div class="card-body text-center">
          <i class="bi bi-people fs-1 text-success mb-3"></i>
          <h5 class="card-title">一般使用者管理</h5>
          <p class="card-text text-muted">新增、刪除或修改一般使用者帳號。</p>
          <a href="users.php" class="btn btn-success">前往</a>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card shadow-sm h-100 border-0">
        <div class="card-body text-center">
          <i class="bi bi-house fs-1 text-secondary mb-3"></i>
          <h5 class="card-title">回首頁</h5>
          <p class="card-text text-muted">返回網站首頁。</p>
          <a href="../home.php" class="btn btn-secondary">返回</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>