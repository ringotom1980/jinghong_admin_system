<?php
session_start();

if (!empty($_SESSION['auth']) && $_SESSION['auth'] === true) {
    // 已登入 → 直接進首頁
    header("Location: home.php");
    exit;
} else {
    // 未登入 → 先進前導頁
    header("Location: splash.php");
    exit;
}
