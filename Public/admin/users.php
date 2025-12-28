<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
require_admin(); // 確保只有 admin 可進入

require_once __DIR__ . '/../../config/user_model.php';

// 處理表單
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['create'])) {
    user_create($_POST['username'], $_POST['display_name'], $_POST['password']);
  }
  if (isset($_POST['update'])) {
    user_update_profile((int)$_POST['id'], $_POST['display_name'], isset($_POST['is_active']));
  }
  if (isset($_POST['changepw'])) {
    user_update_password((int)$_POST['id'], $_POST['new_password']);
  }
  if (isset($_POST['delete'])) {
    user_delete((int)$_POST['id']);
  }
  header('Location: users.php');
  exit;
}

$users = user_all();
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
<style>
  /* 操作欄：保留足夠寬度，內容自動換行、間距一致 */
  .col-actions {
    width: 380px;
  }

  /* 桌機寬度，可視需要調整 */
  @media (max-width: 992px) {
    .col-actions {
      width: auto;
    }
  }

  /* 小螢幕放寬 */
  .col-actions .actions {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    justify-content: center;
    align-items: center;
  }

  .col-actions .form-control {
    min-width: 140px;
  }

  /* 姓名輸入框下限，避免太窄 */
  .col-actions .btn {
    min-width: 64px;
  }

  /* 按鈕不會縮得太小 */
</style>

<div class="container mt-4">
  <h2>使用者管理</h2>

  <!-- 新增 -->
  <form method="post" class="mb-3" autocomplete="off">
    <input type="hidden" name="create" value="1">
    <div class="row g-2">
      <div class="col">
        <input type="text"
          name="username"
          class="form-control"
          placeholder="帳號"
          required
          autocomplete="off"
          autocapitalize="off"
          spellcheck="false">
      </div>
      <div class="col">
        <input type="text"
          name="display_name"
          class="form-control"
          placeholder="姓名"
          autocomplete="off">
      </div>
      <div class="col">
        <input type="password"
          name="password"
          class="form-control"
          placeholder="密碼"
          required
          autocomplete="new-password">
      </div>
      <div class="col-auto"><button class="btn btn-primary">新增</button></div>
    </div>
  </form>


  <!-- 清單 -->
  <table class="table table-striped text-center align-middle">
    <thead>
      <tr>
        <th>ID</th>
        <th>帳號</th>
        <th>姓名</th>
        <th>狀態</th>
        <th>建立時間</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr class="text-center align-middle">
          <td><?= htmlspecialchars($u['id']) ?></td>
          <td><?= htmlspecialchars($u['username']) ?></td>
          <td><?= htmlspecialchars($u['display_name']) ?></td>
          <td><?= $u['is_active'] ? '啟用' : '停用' ?></td>
          <td><?= htmlspecialchars($u['created_at']) ?></td>
          <td class="col-actions">
            <div class="actions">
              <!-- 更新 -->
              <form method="post" class="d-inline-flex align-items-center gap-2">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <input type="hidden" name="update" value="1">
                <input type="text" name="display_name"
                  value="<?= htmlspecialchars($u['display_name']) ?>"
                  class="form-control form-control-sm">
                <div class="form-check">
                  <input type="checkbox" class="form-check-input" name="is_active" id="active-<?= $u['id'] ?>"
                    <?= $u['is_active'] ? 'checked' : '' ?>>
                  <label class="form-check-label" for="active-<?= $u['id'] ?>">啟用</label>
                </div>
                <button class="btn btn-sm btn-success">修改</button>
              </form>

              <!-- 換密碼 -->
              <form method="post" class="d-inline-flex align-items-center gap-2" autocomplete="off">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <input type="hidden" name="changepw" value="1">
                <input type="password" name="new_password" placeholder="新密碼"
                  class="form-control form-control-sm" autocomplete="new-password">
                <button class="btn btn-sm btn-warning">改密碼</button>
              </form>

              <!-- 刪除 -->
              <form method="post" class="d-inline" onsubmit="return confirm('確定刪除？')">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <input type="hidden" name="delete" value="1">
                <button class="btn btn-sm btn-danger">刪除</button>
              </form>
            </div>
          </td>

        </tr>
      <?php endforeach; ?>

    </tbody>
  </table>
</div>
<div class="container my-4 text-center">
  <a href="index.php" class="btn btn-secondary">
    ← 回管理中心
  </a>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>