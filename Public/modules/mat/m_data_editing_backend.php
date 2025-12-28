<?php
declare(strict_types=1);

/**
 * Public/modules/mat/m_data_editing_backend.php
 * 依你提供版本，僅整齊化。
 */

require_once __DIR__ . '/../../../config/auth.php';
require_login();
require_once __DIR__ . '/../../../config/db_connection.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'fetch';

try {
    switch ($action) {

        // 承辦人員
        case 'fetch':
            $stmt = $conn->prepare("SELECT id, shift_name, personnel_name FROM m_data_personnel");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'update':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $id = isset($_POST['id']) ? $_POST['id'] : null;
                $personnel_name = isset($_POST['personnel_name']) ? $_POST['personnel_name'] : null;

                if (!empty($id) && !empty($personnel_name)) {
                    $stmt = $conn->prepare("UPDATE m_data_personnel SET personnel_name = :personnel_name WHERE id = :id");
                    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                    $stmt->bindParam(':personnel_name', $personnel_name, PDO::PARAM_STR);
                    $stmt->execute();

                    if ($stmt->rowCount() > 0) {
                        echo json_encode(['success' => true, 'message' => '更新成功'], JSON_UNESCAPED_UNICODE);
                    } else {
                        echo json_encode(['success' => false, 'message' => '更新失敗，可能是ID無效或資料未變更'], JSON_UNESCAPED_UNICODE);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => '請提供完整資料'], JSON_UNESCAPED_UNICODE);
                }
            } else {
                echo json_encode(['error' => '無效的請求方法'], JSON_UNESCAPED_UNICODE);
            }
            break;

        // 材料對照
        case 'fetch_material':
            $stmt = $conn->prepare("SELECT id, reference_column, material_name FROM m_data_material_mapping ORDER BY sort_order ASC");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'reorder_materials':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                echo json_encode(['success' => false, 'message' => '無效的數據格式'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            try {
                $conn->beginTransaction();
                foreach ($data as $index => $item) {
                    if (!isset($item['id'], $item['material_name'])) continue;
                    $new_sort_order = $index + 1;
                    $stmt = $conn->prepare("
                        UPDATE m_data_material_mapping
                        SET material_name = :material_name, sort_order = :new_sort_order
                        WHERE id = :id
                    ");
                    $stmt->bindParam(':material_name', $item['material_name'], PDO::PARAM_STR);
                    $stmt->bindParam(':new_sort_order', $new_sort_order, PDO::PARAM_INT);
                    $stmt->bindParam(':id', $item['id'], PDO::PARAM_INT);
                    $stmt->execute();
                }
                $conn->commit();
                echo json_encode(['success' => true, 'message' => '材料名稱與順序已更新'], JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                $conn->rollBack();
                echo json_encode(['success' => false, 'message' => '更新失敗: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            break;

        // 更新對帳資料
                // 更新對帳資料（以 withdraw_time 區間比對，避免 SAFE UPDATE 1175）
        case 'update_reconciliation': {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data) || !isset($data['withdraw_time'])) {
                echo json_encode(['success' => false, 'message' => '無效的數據格式或缺少 withdraw_time'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            try {
                // === 以區間判斷當日，避免在欄位上使用 DATE() 函式 ===
                $d = date('Y-m-d', strtotime($data['withdraw_time']));
                $start = $d . ' 00:00:00';
                $end   = date('Y-m-d H:i:s', strtotime($d . ' +1 day')); // 次日 00:00:00

                // 取出欄位清單（僅允許 rec_id_* 類型）
                $colsStmt = $conn->query("SHOW COLUMNS FROM m_data_reconciliation_log");
                $columns = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
                $updateColumns = [];
                foreach ($columns as $c) {
                    if ($c === 'id' || $c === 'withdraw_time' || $c === 'createtime') continue;
                    if (preg_match('/^rec_id_\d+$/', $c) && array_key_exists($c, $data)) {
                        $updateColumns[] = $c;
                    }
                }

                if (empty($updateColumns)) {
                    echo json_encode(['success' => false, 'message' => '沒有可更新的欄位'], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                // 檢查是否已有當日紀錄
                $chk = $conn->prepare("
                    SELECT id FROM m_data_reconciliation_log
                    WHERE withdraw_time >= :start AND withdraw_time < :end
                    LIMIT 1
                ");
                $chk->execute([':start' => $start, ':end' => $end]);
                $rowId = $chk->fetchColumn();

                if ($rowId) {
                    // --- UPDATE by PK，完全避開 1175 爆雷 ---
                    $sets = [];
                    foreach ($updateColumns as $c) $sets[] = "`$c` = :$c";
                    $sql = "UPDATE m_data_reconciliation_log SET " . implode(', ', $sets) . " WHERE id = :id";
                    $stmt = $conn->prepare($sql);
                    foreach ($updateColumns as $c) $stmt->bindValue(":$c", $data[$c] ?? 0, PDO::PARAM_STR);
                    $stmt->bindValue(':id', $rowId, PDO::PARAM_INT);
                    $stmt->execute();
                } else {
                    // --- INSERT ---
                    $cols = $updateColumns;
                    $phs  = array_map(fn($c) => ":$c", $updateColumns);

                    $cols[] = 'withdraw_time';
                    $phs[]  = ':withdraw_time';

                    // createtime（若存在）
                    $hasCreate = $conn->query("SHOW COLUMNS FROM m_data_reconciliation_log LIKE 'createtime'")->fetch(PDO::FETCH_ASSOC);
                    if ($hasCreate) {
                        $cols[] = 'createtime';
                        $phs[]  = 'NOW()';
                    }

                    $sql = "INSERT INTO m_data_reconciliation_log (" . implode(', ', array_map(fn($c) => "`$c`", $cols)) . ")
                            VALUES (" . implode(', ', $phs) . ")";
                    $stmt = $conn->prepare($sql);
                    foreach ($updateColumns as $c) $stmt->bindValue(":$c", $data[$c] ?? 0, PDO::PARAM_STR);
                    $stmt->bindValue(':withdraw_time', $start, PDO::PARAM_STR);
                    $stmt->execute();
                }

                echo json_encode(['success' => true, 'message' => '對帳資料已成功處理'], JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => '操作失敗: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            break;
        }


        // 查詢對帳資料（含 withdraw_time）
        case 'fetch_reconciliation':
            $withdraw_time = isset($_REQUEST['withdraw_time']) ? $_REQUEST['withdraw_time'] : null;
            if (!$withdraw_time) {
                echo json_encode(['success' => false, 'message' => '缺少 withdraw_time'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            try {
                $stmt = $conn->prepare("
                    SELECT 
                        rec_id_1, rec_id_2, rec_id_3, rec_id_4, rec_id_5, 
                        rec_id_6, rec_id_7, rec_id_8, rec_id_9, rec_id_10, 
                        rec_id_11, rec_id_12,
                        withdraw_time
                    FROM m_data_reconciliation_log
                    WHERE DATE(withdraw_time) = :withdraw_date
                ");
                $withdraw_date = date('Y-m-d', strtotime($withdraw_time));
                $stmt->bindParam(':withdraw_date', $withdraw_date, PDO::PARAM_STR);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result) {
                    echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode(['success' => false, 'message' => '未找到相符數據'], JSON_UNESCAPED_UNICODE);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => '查詢失敗: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            break;

        // 新增材料
        case 'add_material':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['material_name']) || empty($data['material_name'])) {
                echo json_encode(['success' => false, 'message' => '缺少材料名稱'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            try {
                $stmt = $conn->prepare("
                    SELECT CONCAT('rec_id_', MIN(n)) AS next_reference_column
                    FROM (
                        SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL
                        SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL
                        SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL
                        SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20
                    ) numbers
                    WHERE CONCAT('rec_id_', n) NOT IN (
                        SELECT reference_column FROM m_data_material_mapping
                    )
                ");
                $stmt->execute();
                $nextReferenceColumn = $stmt->fetchColumn();
                if (!$nextReferenceColumn) {
                    echo json_encode(['success' => false, 'message' => '無法生成新的 reference_column'], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                $stmt = $conn->prepare("
                    INSERT INTO m_data_material_mapping (reference_column, material_name)
                    VALUES (:reference_column, :material_name)
                ");
                $stmt->bindParam(':reference_column', $nextReferenceColumn, PDO::PARAM_STR);
                $stmt->bindParam(':material_name', $data['material_name'], PDO::PARAM_STR);
                $stmt->execute();

                // DDL 不綁參
                if (!preg_match('/^rec_id_\d+$/', $nextReferenceColumn)) throw new Exception('欄位代號非法');
                $comment = str_replace("'", "''", $data['material_name']);
                $sql = "ALTER TABLE `m_data_reconciliation_log` 
                        ADD COLUMN `{$nextReferenceColumn}` FLOAT DEFAULT 0 COMMENT '{$comment}'";
                $conn->exec($sql);

                echo json_encode(['success' => true, 'message' => '材料及對應欄位已成功新增'], JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => '新增失敗: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            break;

        // 刪除材料
        case 'delete_material':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['id']) || !is_numeric($data['id'])) {
                echo json_encode(['success' => false, 'message' => '無效的 ID'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            try {
                $stmt = $conn->prepare("SELECT reference_column FROM m_data_material_mapping WHERE id = :id");
                $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
                $stmt->execute();
                $referenceColumn = $stmt->fetchColumn();
                if (!$referenceColumn) {
                    echo json_encode(['success' => false, 'message' => '未找到對應的 reference_column'], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                $stmt = $conn->prepare("DELETE FROM m_data_material_mapping WHERE id = :id");
                $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
                $stmt->execute();

                $sql = "ALTER TABLE m_data_reconciliation_log DROP COLUMN $referenceColumn";
                $stmt = $conn->prepare($sql);
                $stmt->execute();

                echo json_encode(['success' => true, 'message' => '材料及對應欄位已成功刪除'], JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => '刪除失敗: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            break;

        // 近三個月日期 + voucher_base
        case 'fetch_dates_and_voucher_base':
            try {
                $stmt = $conn->prepare("
                    SELECT DISTINCT 
                        DATE(withdraw_date) AS unique_date, 
                        SUBSTRING_INDEX(voucher, '_', 1) AS voucher_base
                    FROM m_data_material_number
                    WHERE withdraw_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
                    ORDER BY unique_date DESC, voucher_base ASC
                ");
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => '查詢失敗: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            break;

        // 指定日期的 voucher_base
        case 'fetch_voucher_by_date':
            try {
                $withdraw_date = isset($_GET['withdraw_date']) ? $_GET['withdraw_date'] : null;
                if (!$withdraw_date) {
                    echo json_encode(['success' => false, 'message' => '缺少 withdraw_date'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $stmt = $conn->prepare("
                    SELECT DISTINCT SUBSTRING_INDEX(voucher, '_', 1) AS voucher_base
                    FROM m_data_material_number
                    WHERE DATE(withdraw_date) = :withdraw_date
                    ORDER BY voucher_base ASC
                ");
                $stmt->bindValue(':withdraw_date', $withdraw_date, PDO::PARAM_STR);
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => '查詢失敗: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            break;

        // 刪除 voucher（以主鍵批次）
        case 'delete_voucher':
            try {
                $withdraw_date = isset($_POST['withdraw_date']) ? $_POST['withdraw_date'] : null;
                $voucher_base  = isset($_POST['voucher']) ? $_POST['voucher'] : null;
                if (!$withdraw_date || !$voucher_base) {
                    echo json_encode(['success' => false, 'message' => '缺少必要參數'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $withdraw_date = date('Y-m-d', strtotime($withdraw_date));

                $sel = $conn->prepare("
                    SELECT id
                    FROM m_data_material_number
                    WHERE DATE(withdraw_date) = :d
                      AND voucher LIKE :vb
                ");
                $sel->execute([':d' => $withdraw_date, ':vb' => $voucher_base . '%']);
                $ids = $sel->fetchAll(PDO::FETCH_COLUMN, 0);

                if (empty($ids)) {
                    echo json_encode(['success' => false, 'message' => '未找到相符的記錄'], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                $conn->beginTransaction();
                $total = 0;
                foreach (array_chunk($ids, 1000) as $chunk) {
                    $ph = implode(',', array_fill(0, count($chunk), '?'));
                    $sql = "DELETE FROM m_data_material_number WHERE id IN ($ph)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($chunk);
                    $total += $stmt->rowCount();
                }
                $conn->commit();

                echo json_encode(['success' => true, 'message' => "刪除成功，共 {$total} 筆"], JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                if ($conn && $conn->inTransaction()) $conn->rollBack();
                echo json_encode(['success' => false, 'message' => '刪除失敗: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            break;

        // 狀態
        case 'check_status':
            $withdraw_date = isset($_GET['withdraw_time']) ? $_GET['withdraw_time'] : null;
            if (!$withdraw_date) {
                echo json_encode(['success' => false, 'message' => '缺少 withdraw_time'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            try {
                $withdraw_date = date('Y-m-d', strtotime($withdraw_date));

                $stmt = $conn->prepare("
                    SELECT COUNT(*) 
                    FROM m_data_reconciliation_log 
                    WHERE DATE(withdraw_time) = :withdraw_date
                ");
                $stmt->bindParam(':withdraw_date', $withdraw_date, PDO::PARAM_STR);
                $stmt->execute();
                $reconciliationExists = $stmt->fetchColumn() > 0;

                $stmt = $conn->prepare("
                    SELECT COUNT(*) 
                    FROM m_data_material_number 
                    WHERE DATE(withdraw_date) = :withdraw_date 
                      AND LEFT(voucher, 1) IN ('L', 'K', 'W')
                ");
                $stmt->bindParam(':withdraw_date', $withdraw_date, PDO::PARAM_STR);
                $stmt->execute();
                $pickupExists = $stmt->fetchColumn() > 0;

                $stmt = $conn->prepare("
                    SELECT COUNT(*) 
                    FROM m_data_material_number 
                    WHERE DATE(withdraw_date) = :withdraw_date 
                      AND LEFT(voucher, 1) = 'T'
                ");
                $stmt->bindParam(':withdraw_date', $withdraw_date, PDO::PARAM_STR);
                $stmt->execute();
                $returnExists = $stmt->fetchColumn() > 0;

                $stmt = $conn->prepare("
                    SELECT COUNT(*) 
                    FROM m_data_material_number 
                    WHERE DATE(withdraw_date) = :withdraw_date 
                      AND LEFT(voucher, 1) = 'S'
                ");
                $stmt->bindParam(':withdraw_date', $withdraw_date, PDO::PARAM_STR);
                $stmt->execute();
                $scrapExists = $stmt->fetchColumn() > 0;

                echo json_encode([
                    'success' => true,
                    'data' => [
                        'reconciliation' => $reconciliationExists,
                        'pickup'         => $pickupExists,
                        'return'         => $returnExists,
                        'scrap'          => $scrapExists,
                    ],
                ], JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => '查詢失敗: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            break;

        default:
            echo json_encode(['error' => '無效的操作類型'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
