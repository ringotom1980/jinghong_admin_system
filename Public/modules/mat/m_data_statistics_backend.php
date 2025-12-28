<?php
// Public/modules/mat/m_data_statistics_backend.php
declare(strict_types=1);

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

$shift = $_REQUEST['shift'] ?? '';
$date  = $_REQUEST['date'] ?? '';

try {
    switch ($shift) {
        case 'A':
        case 'C':
        case 'E':
        case 'F':
            $sql = "
                SELECT material_number, material_name,
                       SUM(collar_New) AS total_collar_New,
                       SUM(collar_Old) AS total_collar_Old,
                       SUM(recede_New) AS total_recede_New,
                       SUM(recede_Old + scrap + footprint) AS total_recede_Old
                FROM m_data_material_number
                WHERE shift = :shift AND withdraw_date = :d
                GROUP BY material_number, material_name
                ORDER BY material_number
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':shift' => $shift, ':d' => $date]);
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'B':
            $sql = "
                SELECT m.material_number,
                       CONCAT(COALESCE(r.material_location,''),' - ',m.material_name) AS new_material_name,
                       SUM(m.collar_New) AS total_collar_New,
                       GROUP_CONCAT(m.collar_New) AS details_collar_New,
                       SUM(m.collar_Old) AS total_collar_Old,
                       GROUP_CONCAT(m.collar_Old) AS details_collar_Old,
                       SUM(m.recede_New) AS total_recede_New,
                       GROUP_CONCAT(m.recede_New) AS details_recede_New,
                       SUM(m.recede_Old+m.scrap+m.footprint) AS total_recede_Old,
                       GROUP_CONCAT(m.recede_Old+m.scrap+m.footprint) AS details_recede_Old
                FROM m_data_material_number m
                LEFT JOIN m_data_reconciliation_record r ON m.material_number=r.material_number
                WHERE m.shift=:shift AND m.withdraw_date=:d
                GROUP BY m.material_number,m.material_name,r.material_location
                ORDER BY new_material_name ASC
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':shift' => $shift, ':d' => $date]);
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'D': // D 班邏輯
            // 先把當天的時間區間算好（避免在 SQL 裡對欄位呼叫 DATE()）
            $date = trim($date);
            $start = $date . ' 00:00:00';
            $end   = date('Y-m-d H:i:s', strtotime($date . ' +1 day'));

            $sql = "
        SELECT 
            CASE 
                WHEN r.rec_id IS NULL OR r.rec_id = 0 THEN n.material_number
                ELSE CONCAT('rec_id_', r.rec_id)
            END AS material_number,
            COALESCE(m.material_name, n.material_name) AS material_name,
            SUM(n.collar_New) AS total_collar_New,
            SUM(n.collar_Old) AS total_collar_Old,
            SUM(n.recede_New) AS total_recede_New,
            SUM(n.recede_Old + n.scrap + n.footprint) AS total_recede_Old,
            COALESCE(
                CASE
                    WHEN r.rec_id = 1 THEN rl.rec_id_1
                    WHEN r.rec_id = 2 THEN rl.rec_id_2
                    WHEN r.rec_id = 3 THEN rl.rec_id_3
                    WHEN r.rec_id = 4 THEN rl.rec_id_4
                    WHEN r.rec_id = 5 THEN rl.rec_id_5
                    WHEN r.rec_id = 6 THEN rl.rec_id_6
                    WHEN r.rec_id = 7 THEN rl.rec_id_7
                    WHEN r.rec_id = 8 THEN rl.rec_id_8
                    WHEN r.rec_id = 9 THEN rl.rec_id_9
                    WHEN r.rec_id = 10 THEN rl.rec_id_10
                    WHEN r.rec_id = 11 THEN rl.rec_id_11
                    WHEN r.rec_id = 12 THEN rl.rec_id_12
                    ELSE 0
                END, 0
            ) AS reconciliation_value
        FROM m_data_material_number n
        LEFT JOIN m_data_reconciliation_record r
            ON n.material_number = r.material_number
        LEFT JOIN m_data_material_mapping m
            ON r.rec_id <> 0 AND CONCAT('rec_id_', r.rec_id) = m.reference_column
        LEFT JOIN m_data_reconciliation_log rl
            ON rl.withdraw_time >= :start AND rl.withdraw_time < :end
        WHERE n.shift = :shift
          AND DATE(n.withdraw_date) = :withdraw_date
        GROUP BY 
            CASE 
                WHEN r.rec_id IS NULL OR r.rec_id = 0 THEN n.material_number
                ELSE CONCAT('rec_id_', r.rec_id)
            END,
            COALESCE(m.material_name, n.material_name)
        ORDER BY n.material_number;
    ";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':shift'         => $shift,
                ':withdraw_date' => $date,
                ':start'         => $start,
                ':end'           => $end,
            ]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['status' => 'success', 'data' => $data]);
            break;


        default:
            echo json_encode(['status' => 'error', 'message' => '無效的班別']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
