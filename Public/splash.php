<?php
// 已登入就直接去首頁
session_start();
if (!empty($_SESSION['auth']) && $_SESSION['auth'] === true) {
    header('Location: home.php');
    exit;
}

// ========== 可調整秒數 (單位：秒) ==========
$redirectDelay = 5;  // 例如：5 秒後自動跳轉
?>
<!DOCTYPE html>
<html lang="zh">
<head>
  <meta charset="UTF-8">
  <title>境宏工程有限公司</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/imgs/JH_logo.png" type="image/png">
  <style>
    :root{
      --bg1:#0b1426; /* 外圈深藍 */
      --bg2:#2a3550; /* 中心灰藍，比純白柔和 */
    }
    html,body{height:100%;margin:0}
    body{
      display:flex;align-items:center;justify-content:center;gap:24px;
      background: radial-gradient(1000px circle at 50% 40%, var(--bg2), var(--bg1));
      color:#fff; font-family: system-ui, -apple-system, Segoe UI, Roboto, "Noto Sans TC", sans-serif;
      overflow:hidden;
    }
    .wrap{ text-align:center; padding:24px 28px; border-radius:18px; backdrop-filter: blur(8px); }
    .logo{
      width:min(38vw, 180px); height:auto; display:block; margin:0 auto 14px;
      animation: pop-in 900ms cubic-bezier(.2,.9,.2,1) both, float 4.2s ease-in-out 900ms infinite;
    }
    .brand{ font-size:clamp(18px, 2.2vw, 22px); letter-spacing:.2em; opacity:.85 }
    .sub{ font-size:clamp(12px, 1.6vw, 14px); opacity:.65; margin-top:4px }
    .countdown{ margin-top:8px; font-size:14px; opacity:.75 }
    @keyframes pop-in{ 0%{opacity:0; transform:scale(.92); filter:blur(10px)} 100%{opacity:1; transform:scale(1); filter:blur(0)} }
    @keyframes float{ 0%,100%{ transform:translateY(0)} 50%{ transform:translateY(-6px)} }
    .pulse{ animation: pop-in 900ms cubic-bezier(.2,.9,.2,1) both, pulse 2.6s ease-in-out 900ms infinite; }
    @keyframes pulse{ 0%,100%{ transform:scale(1)} 50%{ transform:scale(1.03)} }
    @media (prefers-reduced-motion: reduce){ .logo{ animation: pop-in 500ms ease-out both !important; } }
    .skip{
      position:fixed; right:16px; bottom:14px;
      background:rgba(255,255,255,.1); color:#fff; border:1px solid rgba(255,255,255,.15);
      padding:8px 12px; border-radius:999px; text-decoration:none; font-size:14px;
    }
    .skip:hover{ background:rgba(255,255,255,.18) }
  </style>
  <script>
    let seconds = <?php echo $redirectDelay; ?>;
    const delay = seconds * 1000;

    function updateCountdown(){
      const el = document.getElementById("countdown");
      if(el){
        el.textContent = "將於 " + seconds + " 秒後進入登入頁";
      }
    }

    // 每秒更新
    const timer = setInterval(() => {
      seconds--;
      if (seconds > 0) {
        updateCountdown();
      } else {
        clearInterval(timer);
      }
    }, 1000);

    // 初始化顯示
    window.onload = updateCountdown;

    // 自動跳轉
    setTimeout(function(){ window.location.href = "login.php"; }, delay);
  </script>
</head>
<body>
  <div class="wrap">
    <img src="assets/imgs/JH_logo.png" alt="境宏工程 LOGO" class="logo pulse">
    <div class="brand">境宏工程有限公司 管理系統</div>
    <div class="sub">Jinghong Engineering Co., Ltd. Management System</div>
    <div id="countdown" class="countdown"></div>
  </div>
  <a class="skip" href="login.php" aria-label="跳過前導頁並進入登入">跳過</a>
</body>
</html>
