<?php
// 📂 Public/login.php
require_once __DIR__ . '/../config/auth.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = verify_csrf($_POST['_csrf'] ?? '');
    if (!$csrfOk) {
        $err = '系統安全驗證失敗，請重新載入頁面再試';
        http_response_code(400);
    } else {
        $user = trim((string)($_POST['username'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        $remember = !empty($_POST['remember']);

        $res = handle_login($user, $pass, $remember);
        if (!empty($res['ok'])) {
            // ✅ 登入成功：用 public_base() 取得正確 Public 根
            $base = public_base();

            // 安全回跳：僅允許本站的絕對路徑（/開頭）
            $to = $_GET['redirect'] ?? $_POST['redirect'] ?? '/home.php';
            if (!is_string($to) || !preg_match('#^/[\w\-/\.]*$#', $to)) {
                $to = '/home.php';
            }

            header('Location: ' . $base . $to);
            exit;
        } else {
            // ❌ 登入失敗：依 reason 顯示清楚訊息
            $reason = $res['reason'] ?? 'bad_creds';
            if ($reason === 'inactive') {
                $err = '此帳號已被停用，請聯絡管理者';
                http_response_code(403);
            } elseif ($reason === 'rate_limited') {
                $err = $res['msg'] ?? '嘗試過多，請稍後再試';
                http_response_code(429);
            } else {
                $err = '帳號或密碼錯誤，請再試一次';
                http_response_code(401);
            }
        }
    }
}

$csrf = generate_csrf();
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <title>登入｜境宏工程有限公司</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/imgs/JH_logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --bg1:#0b1426; --bg2:#2a3550; --card:#101826ee; --border:#2f3a57; --accent:#4ea1ff; }
        html, body { height:100% }
        body {
            margin:0; display:flex; align-items:center; justify-content:center;
            background: radial-gradient(900px circle at 50% 35%, var(--bg2), var(--bg1));
            color:#e9eefb; font-family: system-ui,"Noto Sans TC",Segoe UI,Roboto,sans-serif;
        }
        .login-card {
            width:min(92vw, 420px); background:var(--card); border:1px solid var(--border);
            border-radius:18px; padding:28px 24px; box-shadow:0 10px 30px rgba(0,0,0,.25);
        }
        .brand { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
        .brand img { width:46px; height:auto; }
        .brand h1 { font-size:18px; margin:0; letter-spacing:.1em; color:#fff; }
        .form-label { font-size:13px; color:#bfc9e6 }
        .form-control { background:#0e1726; border:1px solid #2c3650; color:#e9eefb; }
        .form-control:focus { border-color:var(--accent); box-shadow:0 0 0 .2rem rgba(78,161,255,.2); }
        .btn-primary { background:var(--accent); border-color:var(--accent); }
        .btn-primary:hover { filter:brightness(1.05) }
        .err { background:#2a1b1b; border:1px solid #6b3131; color:#ffd9d9; padding:8px 12px; border-radius:10px; margin-bottom:10px; font-size:14px; }
        .muted { color:#a9b5d8; font-size:12px }
        .form-check-label { color:#cfd8f6; font-size:13px }
        .footer { text-align:center; margin-top:10px; color:#99a6cc; font-size:12px }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand">
            <img src="assets/imgs/JH_logo.png" alt="境宏工程有限公司">
            <h1>境宏工程有限公司</h1>
        </div>

        <?php if ($err): ?>
            <div class="err" role="alert" aria-live="assertive"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <!-- 🪤 誘捕欄位：攔截瀏覽器自動填入 -->
        <form method="post" style="height:0; overflow:hidden" tabindex="-1" aria-hidden="true" autocomplete="off">
            <input type="text" name="fake_user" autocomplete="username">
            <input type="password" name="fake_pass" autocomplete="current-password">
        </form>

        <form method="post" novalidate autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <?php if (!empty($_GET['redirect'])): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($_GET['redirect']) ?>">
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label" for="username">帳號</label>
                <input required autofocus class="form-control" id="username" name="username" type="text"
                       inputmode="email" autocapitalize="off" spellcheck="false" autocomplete="off">
            </div>

            <div class="mb-2">
                <label class="form-label" for="password">密碼</label>
                <input required class="form-control" id="password" name="password" type="password"
                       autocomplete="new-password">
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                    <label class="form-check-label" for="remember">記住我（14 天）</label>
                </div>
                <span class="muted">多次錯誤將暫時鎖定</span>
            </div>

            <button class="btn btn-primary w-100" type="submit">登入</button>
        </form>

        <div class="footer mt-3">© <?= date('Y') ?> 境宏工程有限公司</div>
    </div>

    <!-- 🧹 最後保險：載入後清掉可能被瀏覽器帶入的值 -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const u = document.getElementById('username');
        const p = document.getElementById('password');
        if (u) u.value = '';
        if (p) p.value = '';
        setTimeout(() => { try { u && u.focus(); } catch(e){} }, 0);
    });
    </script>
</body>
</html>
