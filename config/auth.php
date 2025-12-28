<?php
// 📂 config/auth.php
// 單一管理者 + 一般使用者 的登入/驗證 + CSRF + 限速 + 記住我
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

# ===== 依賴 =====
require_once __DIR__ . '/db_connection.php';  // 提供 $conn（PDO）
require_once __DIR__ . '/user_model.php';     // users 資料表 CRUD（登入用）

# ===== 讀取 .env.php =====
$envFile = __DIR__ . '/.env.php';
$env = is_file($envFile) ? include $envFile : [];
function env($key, $default=''){ global $env; return $env[$key] ?? getenv($key) ?: $default; }

# ===== 自動偵測 Public 網站根（避免寫死前綴） =====
function public_base(): string {
  // 例：/jinghong_admin_system/Public/admin/users.php -> /jinghong_admin_system/Public
  //     /login.php（上線 Public 為 root）           -> ''
  $sn = $_SERVER['SCRIPT_NAME'] ?? '/';
  if (preg_match('#^(.*/Public)(?:/.*)?$#', $sn, $m)) return $m[1]; // 含 /Public
  return ''; // Public 是網站根目錄
}

# ===== 常數設定 =====
const RL_MAX_FAILS    = 5;                         // 連續失敗次數
const RL_LOCK_MINUTE  = 10;                        // 鎖多久（分鐘）
const RL_FILE_DIR     = __DIR__ . '/../storage/tmp'; // 需可寫入
const REMEMBER_COOKIE = 'JH_REMEMBER';
const REMEMBER_DAYS   = 14;

# 確保暫存資料夾存在
if (!is_dir(RL_FILE_DIR)) @mkdir(RL_FILE_DIR, 0775, true);

# ===== CSRF =====
function generate_csrf(): string {
  if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf_token'];
}
function verify_csrf($token): bool {
  return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

# ===== 限速 / 防爆破：安全讀寫（按 IP 分檔） =====
function rl_path(string $ip): string {
  return RL_FILE_DIR . '/auth_rl_' . preg_replace('/[^0-9a-f:\.]/i','_', $ip) . '.json';
}

/** 安全讀：檔案不存在/空檔/壞檔都回預設 */
function rl_load(string $ip): array {
  $p = rl_path($ip);
  if (!is_file($p)) return ['fail'=>0,'reset_at'=>0];
  $raw = @file_get_contents($p);
  if ($raw === false || trim($raw) === '') return ['fail'=>0,'reset_at'=>0];
  $data = json_decode($raw, true);
  return (is_array($data) ? $data : ['fail'=>0,'reset_at'=>0]) + ['fail'=>0,'reset_at'=>0];
}

/** 安全寫：flock 鎖定、失敗不炸流程 */
function rl_save(string $ip, array $data): void {
  $p = rl_path($ip);
  @mkdir(dirname($p), 0775, true);
  $fp = @fopen($p, 'c+'); // 有就開、沒有就建
  if ($fp === false) return;
  if (@flock($fp, LOCK_EX)) {
    ftruncate($fp, 0);
    fwrite($fp, json_encode(
      ['fail' => (int)($data['fail'] ?? 0), 'reset_at' => (int)($data['reset_at'] ?? 0)],
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ));
    fflush($fp);
    @flock($fp, LOCK_UN);
  }
  fclose($fp);
}

function rate_limit_status(string $ip): array {
  $data = rl_load($ip);
  $now = time();
  if (!empty($data['reset_at']) && $now < (int)$data['reset_at']) {
    return ['blocked'=>true, 'fail'=>(int)$data['fail'], 'reset_at'=>(int)$data['reset_at']];
  }
  return ['blocked'=>false,'fail'=>0,'reset_at'=>0];
}

function rate_limit_fail(string $ip): void {
  $now  = time();
  $data = rl_load($ip);
  $data['fail'] = ((int)($data['fail'] ?? 0)) + 1;
  if ((int)$data['fail'] >= RL_MAX_FAILS) {
    $data['reset_at'] = $now + RL_LOCK_MINUTE * 60;
  }
  rl_save($ip, $data);
}

function rate_limit_reset(string $ip): void {
  // 清零而非刪檔，避免瞬時競態
  rl_save($ip, ['fail'=>0,'reset_at'=>0]);
}

# ===== 記住我（簽名 token，支援 admin / user）=====
function sign_payload(string $payload){
  $secret = env('APP_SECRET', '');
  return base64_encode(hash_hmac('sha256', $payload, $secret, true));
}

function set_remember_cookie(string $role, string $username, ?int $userId = null){
  $exp = time() + REMEMBER_DAYS*86400;
  $nonce = bin2hex(random_bytes(8));
  $data = [
    'r'=>$role,         // admin | user
    'u'=>$username,     // admin: 帳號（.env）；user: users.username
    'uid'=>$userId,     // user 才會有
    'exp'=>$exp,
    'n'=>$nonce
  ];
  $b64  = rtrim(strtr(base64_encode(json_encode($data, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
  $sig  = sign_payload($b64);
  $val  = $b64 . '.' . $sig;
  setcookie(REMEMBER_COOKIE, $val, [
    'expires'  => $exp,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  ]);
}
function clear_remember_cookie(){
  setcookie(REMEMBER_COOKIE, '', time()-3600, '/');
}

function try_restore_session_from_cookie(){
  if (!empty($_SESSION['user_role'])) return; // 已登入
  $val = $_COOKIE[REMEMBER_COOKIE] ?? '';
  if (!$val) return;
  $parts = explode('.', $val, 2);
  if (count($parts) !== 2) return;
  [$b64, $sig] = $parts;
  if (!hash_equals(sign_payload($b64), $sig)) return;
  $json = base64_decode(strtr($b64, '-_', '+/'), true);
  if ($json === false) return;
  $data = json_decode($json, true);
  if (!is_array($data) || ($data['exp'] ?? 0) < time()) return;

  $role = $data['r'] ?? '';
  $u    = $data['u'] ?? '';
  $uid  = isset($data['uid']) ? (int)$data['uid'] : null;

  if ($role === 'admin') {
    if ($u !== env('ADMIN_USER','')) return;
    $_SESSION['user_role']    = 'admin';
    $_SESSION['admin']        = $u;
    $_SESSION['user_id']      = null;
    $_SESSION['username']     = $u;
    $_SESSION['display_name'] = '管理者';
    session_regenerate_id(true);
    return;
  }

  if ($role === 'user' && $u !== '') {
    $row = user_find_by_username($u);
    if (!$row || (int)($row['is_active'] ?? 0) !== 1) return;
    $_SESSION['user_role']    = 'user';
    $_SESSION['admin']        = null;
    $_SESSION['user_id']      = (int)$row['id'];
    $_SESSION['username']     = $row['username'];
    $_SESSION['display_name'] = $row['display_name'] ?? $row['username'];
    session_regenerate_id(true);
  }
}

# ===== 入口保護（不寫死前綴） =====
function require_login(){
  try_restore_session_from_cookie();
  if (!empty($_SESSION['user_role'])) return;

  $path = $_SERVER['REQUEST_URI'] ?? '/home.php';
  $redirect = (preg_match('#^/[\w\-/\.]+$#', $path) ? $path : '/home.php');

  $base = public_base();
  header('Location: ' . $base . '/login.php?redirect=' . urlencode($redirect));
  exit;
}

# ===== 登入與登出（同時支援 admin / user）=====
/**
 * 回傳：
 *   ['ok'=>true,  'role'=>'admin'|'user']
 *   ['ok'=>false, 'reason'=>'inactive'|'bad_creds'|'rate_limited', 'msg'=>'...']
 */
function handle_login(string $user, string $pass, bool $remember=false): array {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
  $st = rate_limit_status($ip);
  if (!empty($st['blocked'])) {
    $remain = max(1, (int)ceil(((int)$st['reset_at'] - time())/60));
    return ['ok'=>false, 'reason'=>'rate_limited', 'msg'=>"嘗試過多，請稍後再試（約 {$remain} 分鐘）"];
  }

  $ADMIN_USER = env('ADMIN_USER', '');
  $ADMIN_HASH = env('ADMIN_PASS_HASH', '');

  // 1) 先試 admin（.env）
  if ($ADMIN_USER !== '' && $user === $ADMIN_USER && $ADMIN_HASH !== '' && password_verify($pass, $ADMIN_HASH)) {
    rate_limit_reset($ip);
    $_SESSION['user_role']    = 'admin';
    $_SESSION['admin']        = $ADMIN_USER;
    $_SESSION['user_id']      = null;
    $_SESSION['username']     = $ADMIN_USER;
    $_SESSION['display_name'] = '管理者';
    session_regenerate_id(true);
    if ($remember) set_remember_cookie('admin', $ADMIN_USER, null);
    return ['ok'=>true, 'role'=>'admin'];
  }

  // 2) 再試一般使用者（users 表）
  $row = user_find_by_username($user);
  if ($row && (int)($row['is_active'] ?? 0) === 1 && !empty($row['password_hash']) && password_verify($pass, $row['password_hash'])) {
    rate_limit_reset($ip);
    $_SESSION['user_role']    = 'user';
    $_SESSION['admin']        = null;
    $_SESSION['user_id']      = (int)$row['id'];
    $_SESSION['username']     = $row['username'];
    $_SESSION['display_name'] = $row['display_name'] ?? $row['username'];
    session_regenerate_id(true);
    if ($remember) set_remember_cookie('user', $row['username'], (int)$row['id']);
    return ['ok'=>true, 'role'=>'user'];
  }

  // 若有該使用者但被停用，回傳專屬 reason
  if ($row && (int)($row['is_active'] ?? 0) !== 1) {
    rate_limit_fail($ip);
    return ['ok'=>false, 'reason'=>'inactive', 'msg'=>'此帳號已被停用'];
  }

  // 其餘：帳密錯
  rate_limit_fail($ip);
  return ['ok'=>false, 'reason'=>'bad_creds', 'msg'=>'帳號或密碼錯誤'];
}

function handle_logout(){
  clear_remember_cookie();

  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
  }
  session_destroy();

  $base = public_base();
  header('Location: ' . $base . '/login.php');
  exit;
}

# ===== 權限保護 =====
/** 需要 admin 身分（例如：Public/admin/*） */
function require_admin(){
  try_restore_session_from_cookie();
  $isAdmin = !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin' && (($_SESSION['admin'] ?? '') === env('ADMIN_USER',''));
  if ($isAdmin) return;

  $want = $_SERVER['REQUEST_URI'] ?? '/home.php';
  $base = public_base();
  header('Location: ' . $base . '/login.php?redirect=' . urlencode($want));
  exit;
}

/** 需要登入即可（一般模組頁可用） */
function require_user(){
  require_login(); // 目前登入即可；若要限定「非 admin 的一般使用者」可改判斷
}

// 取得目前登入者要顯示的名稱（優先 display_name，否則 username）
function current_display_name(): string {
  try_restore_session_from_cookie();
  $name = $_SESSION['display_name'] ?? ($_SESSION['username'] ?? '');
  return is_string($name) ? $name : '';
}

// ===== CSRF 工具 =====
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}
