<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
require_admin(); // 只有 admin 本身可進來

$envPath = __DIR__ . '/../../config/.env.php';
$env = is_file($envPath) ? include $envPath : [];

$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newpw = trim($_POST['new_password'] ?? '');
    if ($newpw !== '') {
        $env['ADMIN_PASS_HASH'] = password_hash($newpw, PASSWORD_BCRYPT);
        $code = "<?php\nreturn " . var_export($env, true) . ";\n";
        file_put_contents($envPath, $code);
        $msg = "管理者密碼已更新！";
    } else {
        $msg = "密碼不可為空。";
    }
}
?>
<!-- 這兩行是關鍵：把 CSS 指回 /Public/ 下，不用動其他頁 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"><!-- 若有用 bi-* 圖示 -->
<!-- 設定分頁標題與 favicon（不改結構、不新增檔案） -->
<script>
  document.title = '管理中心｜境宏工程有限公司';
  (function() {
    var l = document.querySelector('link[rel="icon"]') || document.createElement('link');
    l.rel = 'icon';
    l.type = 'image/png';
    l.href = '../assets/imgs/JH_logo.png'; // 這一頁在 /Public/admin/，退一層剛好對到 /Public/assets/...
    if (!l.parentNode) document.head.appendChild(l);
  }());
</script>
<?php include __DIR__ . '/../partials/header.php'; ?>

<div class="container mt-4">
  <h2>管理者密碼變更</h2>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <form method="post">
    <div class="mb-3">
      <label class="form-label">新密碼</label>
      <input type="password" name="new_password" class="form-control" required>
    </div>
    <button class="btn btn-primary">更新</button>
  </form>
</div>
<div class="container my-4 text-center">
  <a href="index.php" class="btn btn-secondary">
    ← 回管理中心
  </a>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
