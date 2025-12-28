<?php
declare(strict_types=1);
require_once __DIR__ . '/db_connection.php';  // 只負責引入連線

function __user_pdo(): PDO {
  if (!isset($GLOBALS['conn']) || !($GLOBALS['conn'] instanceof PDO)) {
    throw new RuntimeException('DB connection ($conn) is not initialized.');
  }
  return $GLOBALS['conn'];
}

function user_all(): array {
  $pdo = __user_pdo();
  $sql = "SELECT id, username, display_name, is_active, created_at, updated_at
          FROM users ORDER BY id ASC";
  return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function user_find_by_username(string $username): ?array {
  $pdo = __user_pdo();
  $st = $pdo->prepare("SELECT * FROM users WHERE username=? LIMIT 1");
  $st->execute([$username]);
  return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function user_create(string $username, ?string $display_name, string $password): bool {
  $pdo = __user_pdo();
  $hash = password_hash($password, PASSWORD_BCRYPT);
  $st = $pdo->prepare("INSERT INTO users(username, display_name, password_hash) VALUES(?,?,?)");
  return $st->execute([$username, $display_name, $hash]);
}

function user_update_profile(int $id, ?string $display_name, bool $is_active): bool {
  $pdo = __user_pdo();
  $st = $pdo->prepare("UPDATE users SET display_name=?, is_active=? WHERE id=?");
  return $st->execute([$display_name, $is_active ? 1 : 0, $id]);
}

function user_update_password(int $id, string $new_password): bool {
  $pdo = __user_pdo();
  $hash = password_hash($new_password, PASSWORD_BCRYPT);
  $st = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
  return $st->execute([$hash, $id]);
}

function user_delete(int $id): bool {
  $pdo = __user_pdo();
  $st = $pdo->prepare("DELETE FROM users WHERE id=?");
  return $st->execute([$id]);
}
