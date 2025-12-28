<?php
// 📂 Public/home.php
require_once __DIR__ . '/../config/auth.php';
require_login(); // 一般使用者/管理者都可看

// 頁面標題（交給 header.php 使用）
$page_title = '首頁｜境宏工程有限公司';

// 讀取輪播圖清單（依檔名排序）
$slideDir = __DIR__ . '/assets/slides';
$slides = [];
if (is_dir($slideDir)) {
  foreach (glob($slideDir . '/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) as $p) {
    $slides[] = 'assets/slides/' . basename($p);
  }
}

// 引入共用抬頭（已載入 Bootstrap 5.3.3 CSS、icons、共用樣式，並開啟 <body> 與網站導覽）
// include __DIR__ . '/partials/header.php';

?>
<!-- Bootstrap 5.3.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <!-- 自訂頁籤標題與 favicon -->
<script>
  document.title = '首頁｜境宏工程有限公司';
  (function() {
    var l = document.querySelector('link[rel="icon"]') || document.createElement('link');
    l.rel = 'icon';
    l.type = 'image/png';
    l.href = "<?= public_base() ?>/assets/imgs/JH_logo.png";
    if (!l.parentNode) document.head.appendChild(l);
  }());
</script>
<style>
  body { margin:0; background:#0b1426; color:#fff; font-family:"Noto Sans TC",system-ui,sans-serif; }
  .carousel-item img { object-fit:cover; height:100vh; width:100%; }
  .overlay-buttons {
    position:absolute; bottom:40px; left:50%; transform:translateX(-50%);
    display:flex; gap:20px; z-index:10;
  }
  .overlay-buttons a {
    padding:12px 28px; border-radius:30px;
    background:rgba(0,0,0,.6); color:#fff; text-decoration:none; transition:.3s;
    border:1px solid rgba(255,255,255,.2);
  }
  .overlay-buttons a:hover { background:#4ea1ff; color:#fff; }
</style>

<div id="homeCarousel" class="carousel slide" data-bs-ride="carousel">
  <div class="carousel-inner">
    <?php if (!empty($slides)): ?>
      <?php foreach ($slides as $i => $src): ?>
        <div class="carousel-item <?= $i===0 ? 'active' : '' ?>">
          <img src="<?= htmlspecialchars($src) ?>" class="d-block w-100" alt="slide<?= $i+1 ?>">
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <!-- 沒有任何圖時的預設三張（可改圖檔或文字） -->
      <div class="carousel-item active"><img src="assets/imgs/slide1.jpg" class="d-block w-100" alt="slide1"></div>
      <div class="carousel-item"><img src="assets/imgs/slide2.jpg" class="d-block w-100" alt="slide2"></div>
      <div class="carousel-item"><img src="assets/imgs/slide3.jpg" class="d-block w-100" alt="slide3"></div>
    <?php endif; ?>
  </div>
  <?php if (count($slides) > 1): ?>
    <button class="carousel-control-prev" type="button" data-bs-target="#homeCarousel" data-bs-slide="prev">
      <span class="carousel-control-prev-icon"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#homeCarousel" data-bs-slide="next">
      <span class="carousel-control-next-icon"></span>
    </button>
  <?php endif; ?>
</div>

<div class="overlay-buttons">
  <a href="modules/mat/m_data_editing.php" class="btn btn-dark">領退料管理</a>
  <a href="modules/car/car_edit.php" class="btn btn-dark">車輛管理</a>
  <a href="modules/equ/equ_repair.php" class="btn btn-dark">機具管理</a>
  <a href="slider_upload.php" class="btn btn-dark">輪播圖片管理</a>
  <?php if (!empty($_SESSION['admin']) && $_SESSION['admin'] === env('ADMIN_USER','')): ?>
    <a href="admin/index.php" class="btn btn-dark">管理中心</a>
  <?php endif; ?>
</div>
<!-- Bootstrap 5.3.3 JS (bundle 內含 Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

<!-- 其他共用套件 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
// 引入共用頁尾（已載入 Bootstrap 5.3.3 bundle + 共用 JS，並關閉 </body></html>）
include __DIR__ . '/partials/footer.php';
