<?php
require_once __DIR__ . '/../config/auth.php';
handle_logout(); // 會清除 session 與 remember cookie，並導回 login.php
