<?php
// 自動計算相對 /Public/ 前綴 $R
$R = '';
$path = $_SERVER['PHP_SELF'] ?? '';
if (($pos = strpos($path, '/Public/')) !== false) {
  $after = substr($path, $pos + 8);
  $dir   = rtrim(substr($after, 0, strrpos($after, '/')),'/');
  if ($dir !== '') {
    $depth = substr_count($dir,'/') + 1;
    $R = str_repeat('../', $depth);
  }
}
?>
<footer class="site-footer text-center">
  © <?= date('Y') ?> 境宏工程有限公司 ｜ Jinghong Engineering Co., Ltd.
</footer>

<style>
.site-footer {
  background: linear-gradient(90deg, #263a72 0%, #0b1426 100%);
  color: #ccc;
  padding: 12px;
  font-size: 13px;
  border-top: 1px solid #222;
  letter-spacing: 0.5px;
}
</style>

<!-- Bootstrap 5.3.3 JS (bundle 內含 Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

<!-- 其他共用套件 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- 共用導覽行為 -->
<script defer src="<?= $R ?>assets/js/header-nav.js"></script>

<script>window.PUBLIC_BASE = <?= json_encode($R) ?>;</script>
</body>
</html>
