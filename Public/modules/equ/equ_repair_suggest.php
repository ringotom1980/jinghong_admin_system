<?php
// Public/modules/equ/equ_repair_suggest.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/auth.php';
require_login();
require_once __DIR__ . '/../../../config/db_connection.php'; // $conn (PDO)

header('Content-Type: application/json; charset=utf-8');

$kind = strtolower(trim((string)($_GET['kind'] ?? '')));
$q    = trim((string)($_GET['q'] ?? ''));

$col = $kind === 'machines' ? 'machine_name' : 'vendor_name';
$items = [];

try {
  if ($q === '' || $q === '*') {
    // TOP N by count
    $sql = "SELECT $col AS v FROM (
              SELECT TRIM($col) AS $col, COUNT(*) AS cnt
              FROM machine_repairs
              WHERE $col IS NOT NULL AND TRIM($col) <> ''
              GROUP BY TRIM($col)
            ) t
            ORDER BY cnt DESC, $col ASC
            LIMIT 30";
    $stmt = $conn->query($sql);
    $items = array_values(array_filter(array_map(fn($r)=> (string)$r['v'], $stmt->fetchAll(PDO::FETCH_ASSOC))));
  } else {
    // 模糊查 / 前綴優先
    $like = '%' . $q . '%';
    $sql = "SELECT DISTINCT TRIM($col) AS v
            FROM machine_repairs
            WHERE $col IS NOT NULL AND TRIM($col) <> '' AND $col LIKE :kw
            ORDER BY
              CASE WHEN $col LIKE :prefix THEN 0 ELSE 1 END,
              $col ASC
            LIMIT 30";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':kw'=>$like, ':prefix'=>$q.'%']);
    $items = array_values(array_filter(array_map(fn($r)=> (string)$r['v'], $stmt->fetchAll(PDO::FETCH_ASSOC))));
  }
} catch (\Throwable $e) {
  echo json_encode(['items'=>[]], JSON_UNESCAPED_UNICODE);
  exit;
}
echo json_encode(['items'=>$items], JSON_UNESCAPED_UNICODE);
