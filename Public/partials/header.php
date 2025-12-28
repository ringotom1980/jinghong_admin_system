<?php
require_once __DIR__ . '/../../config/auth.php';

/* 計算相對 /Public/ 前綴 $R */
$R = '';
$path = $_SERVER['PHP_SELF'] ?? '';
if (($pos = strpos($path, '/Public/')) !== false) {
  $after = substr($path, $pos + 8);
  $dir   = rtrim(substr($after, 0, strrpos($after, '/')), '/');
  if ($dir !== '') {
    $depth = substr_count($dir, '/') + 1;
    $R = str_repeat('../', $depth);
  }
}

/* 顯示名稱與權限 */
if (function_exists('current_display_name')) {
  $rawName = current_display_name();
} else {
  $rawName = $_SESSION['display_name'] ?? ($_SESSION['username'] ?? ($_SESSION['admin'] ?? 'user'));
}
$who = htmlspecialchars((string)$rawName, ENT_QUOTES, 'UTF-8');
$isAdmin = !empty($_SESSION['admin']);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
  <meta charset="UTF-8">
  <title><?= !empty($page_title) ? htmlspecialchars($page_title,ENT_QUOTES,'UTF-8') : '境宏工程有限公司' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="<?= $R ?>assets/imgs/JH_logo.png" type="image/png">

  <!-- Bootstrap 5.3.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

  <!-- 導覽樣式 -->
  <link rel="stylesheet" href="<?= $R ?>assets/css/header-nav.css">
</head>
<body>
<header class="jh-header" role="banner">
  <div class="jh-top d-flex align-items-center justify-content-between">
    <a href="<?= $R ?>home.php" class="jh-brand text-decoration-none d-flex align-items-center gap-2">
      <img src="<?= $R ?>assets/imgs/JH_logo.png" alt="境宏工程有限公司" height="46" loading="eager" decoding="async">
      <span class="jh-brand-text">境宏工程有限公司</span>
    </a>
    <div class="jh-user">
      您好，<?= $who ?> ｜ <a href="<?= $R ?>logout.php">登出</a>
    </div>
  </div>

  <nav class="jh-nav2" aria-label="次導覽">
    <ul class="jh-nav2-list" role="menubar">
      <!-- 材料管理 -->
      <li class="jh-nav2-item" data-key="mat" role="none">
        <button class="jh-nav2-btn" role="menuitem" aria-haspopup="true" aria-expanded="false">領退管理</button>
        <div class="jh-pop" data-panel="mat" hidden>
          <a href="<?= $R ?>modules/mat/m_data_editing.php">資料編輯</a>
          <a href="<?= $R ?>modules/mat/m_material_location.php">材料管理</a>
          <a href="<?= $R ?>modules/mat/m_data_statistics.php">領退統計</a>
        </div>
      </li>

      <!-- 車輛管理 -->
      <li class="jh-nav2-item" data-key="car" role="none">
        <button class="jh-nav2-btn" role="menuitem" aria-haspopup="true" aria-expanded="false">車輛管理</button>
        <div class="jh-pop" data-panel="car" hidden>
          <a href="<?= $R ?>modules/car/car_edit.php">基本資料</a>
          <a href="<?= $R ?>modules/car/car_repair.php">維修紀錄</a>
          <a href="<?= $R ?>modules/car/car_statistics.php">維修統計</a>
        </div>
      </li>

      <!-- 機具管理 -->
      <li class="jh-nav2-item" data-key="equ" role="none">
        <button class="jh-nav2-btn" role="menuitem" aria-haspopup="true" aria-expanded="false">機具管理</button>
        <div class="jh-pop" data-panel="equ" hidden>
          <a href="<?= $R ?>modules/equ/equ_repair.php">維修紀錄</a>
          <a href="<?= $R ?>modules/equ/equ_statistics.php">維修統計</a>
        </div>
      </li>

      <!-- 管理中心（僅管理員） -->
      <?php if ($isAdmin): ?>
      <li class="jh-nav2-item" data-key="admin" role="none">
        <button class="jh-nav2-btn" role="menuitem" aria-haspopup="true" aria-expanded="false">管理中心</button>
        <div class="jh-pop" data-panel="admin" hidden>
          <a href="<?= $R ?>admin/index.php">管理中心首頁</a>
          <a href="<?= $R ?>admin/change_admin_password.php">變更管理者密碼</a>
          <a href="<?= $R ?>admin/users.php">一般使用者管理</a>
        </div>
      </li>
      <?php endif; ?>
    </ul>
  </nav>
</header>
