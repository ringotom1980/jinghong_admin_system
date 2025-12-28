<?php
declare(strict_types=1);

/**
 * 路徑：/Public/modules/mat/update_shift.php
 * 功能：
 *   GET  -> 取得 m_data_personnel（shift_name, personnel_name）
 *   POST -> 批次更新材料班別（m_data_reconciliation_record、m_data_material_number）
 * 權限：需登入
 */

require_once __DIR__ . '/../../../config/auth.php';
require_login();
require_once __DIR__ . '/../../../config/db_connection.php'; // 提供 $conn (PDO)

// ---- 回應格式 & CORS ---- //
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// CORS 預檢
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$res = ['success' => false, 'message' => '', 'data' => null];

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // 僅影響本連線，避免 SQL_SAFE_UPDATES 擋到更新
    $conn->exec("SET SESSION sql_safe_updates = 0");

    if ($method === 'GET') {
        // 讀取承辦人員（可用來顯示既有班別/人員）
        $stmt = $conn->prepare("SELECT id, shift_name, personnel_name FROM m_data_personnel ORDER BY shift_name, personnel_name");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $res['success'] = true;
        $res['data'] = $rows;

    } elseif ($method === 'POST') {
        // 期待 Body: { "shifts": { "M-0001": "A", "M-0002": "C", ... } }
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload) || !isset($payload['shifts']) || !is_array($payload['shifts'])) {
            http_response_code(400);
            throw new InvalidArgumentException('缺少班別數據（shifts）或格式錯誤');
        }

        // 如果你允許 A~F 以外的值，改這裡（或直接註解檢核）
        $allowedShifts = ['A','B','C','D','E','F'];

        // 預先準備 SQL
        $sqlReco = "UPDATE m_data_reconciliation_record SET shift = :shift WHERE material_number = :material_number";
        $sqlMat  = "UPDATE m_data_material_number       SET shift = :shift WHERE material_number = :material_number";
        $stmtReco = $conn->prepare($sqlReco);
        $stmtMat  = $conn->prepare($sqlMat);

        $conn->beginTransaction();

        $updatedReco = 0;
        $updatedMat  = 0;
        $countInput  = 0;

        foreach ($payload['shifts'] as $materialNumber => $shift) {
            $materialNumber = trim((string)$materialNumber);
            $shift = strtoupper(trim((string)$shift));

            if ($materialNumber === '') {
                throw new InvalidArgumentException('material_number 不可為空字串');
            }
            if (!in_array($shift, $allowedShifts, true)) {
                throw new InvalidArgumentException("material_number={$materialNumber} 的班別值不合法：{$shift}（允許 A-F）");
            }

            // 更新對帳紀錄
            $stmtReco->execute([
                ':shift' => $shift,
                ':material_number' => $materialNumber,
            ]);
            $updatedReco += $stmtReco->rowCount();

            // 更新材料主檔
            $stmtMat->execute([
                ':shift' => $shift,
                ':material_number' => $materialNumber,
            ]);
            $updatedMat += $stmtMat->rowCount();

            $countInput++;
        }

        $conn->commit();

        $res['success'] = true;
        $res['message'] = '班別更新成功';
        $res['data'] = [
            'count_input' => $countInput,
            'updated_reconciliation' => $updatedReco,
            'updated_material_number' => $updatedMat,
        ];

    } else {
        http_response_code(405);
        $res['message'] = '無效的請求方法';
    }

} catch (Throwable $e) {
    if ($conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    $res['success'] = false;
    $res['message'] = '操作失敗：' . $e->getMessage();
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
