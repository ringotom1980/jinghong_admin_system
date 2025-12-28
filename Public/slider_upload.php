<?php
// 📂 Public/slider_upload.php
require_once __DIR__ . '/../config/auth.php';
require_login(); // 一般使用者就能操作（不是 require_admin）

$err = $ok = '';
$csrf = generate_csrf();
$slideDir = __DIR__ . '/assets/slides';
if (!is_dir($slideDir)) @mkdir($slideDir, 0775, true);

// 刪除圖片
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
  if (!verify_csrf($_POST['_csrf'] ?? '')) {
    $err = '安全驗證失敗，請重新操作';
  } else {
    $name = basename($_POST['delete']); // 防止路徑穿越
    $path = $slideDir . '/' . $name;
    if (is_file($path)) {
      @unlink($path);
      $ok = '圖片已刪除';
    } else {
      $err = '檔案不存在';
    }
  }
}

// 上傳圖片
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['slide'])) {
  if (!verify_csrf($_POST['_csrf'] ?? '')) {
    $err = '安全驗證失敗，請重新操作';
  } elseif (!isset($_FILES['slide']) || $_FILES['slide']['error'] !== UPLOAD_ERR_OK) {
    $err = '請選擇檔案或上傳失敗';
  } else {
    $tmp  = $_FILES['slide']['tmp_name'];
    $name = $_FILES['slide']['name'];

    // 檔名與副檔名檢核
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp','gif'];
    if (!in_array($ext, $allowed, true)) {
      $err = '只允許上傳：jpg、jpeg、png、webp、gif';
    } else {
      // MIME 檢測
      $fi = new finfo(FILEINFO_MIME_TYPE);
      $mime = $fi->file($tmp);
      $okMime = in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'], true);
      if (!$okMime) {
        $err = '檔案格式不正確';
      } else {
        // 產生安全檔名：YYYYMMDD_HHMMSS_8hex.ext
        $safe = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($tmp, $slideDir . '/' . $safe)) {
          $ok = '上傳完成';
        } else {
          $err = '無法儲存檔案（請確認目錄權限）';
        }
      }
    }
  }
}

// 讀取目前清單
$list = [];
foreach (glob($slideDir . '/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) as $p) {
  $list[] = basename($p);
}
sort($list); // 依檔名排序
?>
<!DOCTYPE html>
<html lang="zh">
<head>
  <meta charset="UTF-8">
  <title>輪播圖片管理｜境宏工程</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/imgs/JH_logo.png" type="image/png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#0b1426; color:#e9eefb; font-family:"Noto Sans TC",system-ui,sans-serif; }
    .card { background:#101826ee; border:1px solid #2f3a57; }
    .thumb { width:100%; height:160px; object-fit:cover; border-radius:8px; }
    a, .btn-link { color:#9fd1ff; }
  </style>
</head>
<body class="py-4">

<div class="container" style="max-width:980px">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 m-0">輪播圖片管理</h1>
    <div>
      <a class="btn btn-outline-light btn-sm" href="home.php">回首頁</a>
      <a class="btn btn-outline-warning btn-sm" href="logout.php">登出</a>
    </div>
  </div>

  <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if ($ok):  ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

  <!-- 上傳表單 -->
  <div class="card mb-4">
    <div class="card-body">
      <form method="post" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <div class="col-md-6">
          <input class="form-control" type="file" name="slide" accept=".jpg,.jpeg,.png,.webp,.gif" required>
          <div class="form-text text-secondary">建議尺寸：1920×1080 以上；僅接受 jpg / png / webp / gif</div>
        </div>
        <div class="col-md-3">
          <button class="btn btn-primary w-100">上傳</button>
        </div>
        <div class="col-md-3 text-secondary small">
          目前可用容量：<?= ini_get('upload_max_filesize') ?> / <?= ini_get('post_max_size') ?>
        </div>
      </form>
    </div>
  </div>

  <!-- 圖片清單 -->
  <?php if (empty($list)): ?>
    <div class="text-secondary">目前沒有任何輪播圖片，請先上傳。</div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($list as $fname): ?>
        <div class="col-md-4">
          <div class="card p-2">
            <img class="thumb" src="assets/slides/<?= htmlspecialchars($fname) ?>" alt="<?= htmlspecialchars($fname) ?>">
            <div class="d-flex justify-content-between align-items-center mt-2">
              <code class="small text-secondary"><?= htmlspecialchars($fname) ?></code>
              <form method="post" onsubmit="return confirm('確定刪除此圖片？');">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="delete" value="<?= htmlspecialchars($fname) ?>">
                <button class="btn btn-sm btn-outline-danger">刪除</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
