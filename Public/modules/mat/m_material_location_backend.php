<?php
// Public/modules/mat/m_material_location_backend.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/auth.php';
require_login();
require_once __DIR__ . '/../../../config/db_connection.php'; // 提供 $conn (PDO)

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? 'fetch';

try {
  switch ($action) {
    case 'fetch': // 讀 B 班材料資料
      $sql = "
        SELECT material_number, name_specification, material_location
        FROM m_data_reconciliation_record
        WHERE shift = 'B'
        ORDER BY
          (material_location IS NULL OR material_location = '') ASC,
          material_location ASC
      ";
      $rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
      echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
      break;

    case 'update': // 更新材料位置
      $input = json_decode(file_get_contents('php://input'), true) ?: [];
      $materialNumber   = (string)($input['material_number'] ?? '');
      $materialLocation = (string)($input['material_location'] ?? '');

      if ($materialNumber === '' || $materialLocation === '') {
        throw new RuntimeException('參數不完整');
      }

      $stmt = $conn->prepare("
        UPDATE m_data_reconciliation_record
        SET material_location = :loc
        WHERE material_number = :num
      ");
      $stmt->execute([':loc' => $materialLocation, ':num' => $materialNumber]);

      echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
      break;

    case 'fetch_mapping': // 讀 D 班對照分組
      // reference_column 末尾數字 = rec_id
      $sql = "
        SELECT
          m_map.reference_column,
          m_map.material_name,
          CAST(REGEXP_SUBSTR(m_map.reference_column, '[0-9]+$') AS UNSIGNED) AS reference_number,
          GROUP_CONCAT(m_rec.material_number ORDER BY m_rec.material_number ASC) AS material_numbers
        FROM m_data_material_mapping AS m_map
        LEFT JOIN m_data_reconciliation_record AS m_rec
          ON CAST(REGEXP_SUBSTR(m_map.reference_column, '[0-9]+$') AS UNSIGNED) = m_rec.rec_id
        GROUP BY m_map.reference_column, m_map.material_name
        ORDER BY m_map.sort_order ASC
      ";
      $rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
      echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
      break;

    case 'fetch_shift_a': // 名稱沿用舊版；實際抓 D 班
      $rows = $conn->query("
        SELECT material_number, name_specification, shift
        FROM m_data_reconciliation_record
        WHERE shift = 'D'
      ")->fetchAll(PDO::FETCH_ASSOC);
      echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
      break;

    case 'save_combination': // 儲存 D 班材料組合
      $input = json_decode(file_get_contents('php://input'), true) ?: [];
      $referenceNumber   = (int)($input['reference_number'] ?? 0);
      $selectedMaterials = isset($input['selected_materials']) && is_array($input['selected_materials'])
        ? array_values(array_filter(array_map('strval', $input['selected_materials'])))
        : [];

      if ($referenceNumber <= 0) {
        echo json_encode(['success' => false, 'message' => '參數不完整（reference_number）'], JSON_UNESCAPED_UNICODE);
        break;
      }

      try {
        $conn->beginTransaction();

        // 清空該組
        $stmt = $conn->prepare("
          UPDATE m_data_reconciliation_record
          SET rec_id = 0
          WHERE rec_id = :rid
        ");
        $stmt->execute([':rid' => $referenceNumber]);

        // 指派新組合
        if ($selectedMaterials) {
          $stmt = $conn->prepare("
            UPDATE m_data_reconciliation_record
            SET rec_id = :rid
            WHERE material_number = :num
          ");
          foreach ($selectedMaterials as $mn) {
            $stmt->execute([':rid' => $referenceNumber, ':num' => $mn]);
          }
        }

        $conn->commit();
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
      } catch (Throwable $tx) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => '儲存失敗: ' . $tx->getMessage()], JSON_UNESCAPED_UNICODE);
      }
      break;

    default:
      echo json_encode(['success' => false, 'message' => '無效的操作類型'], JSON_UNESCAPED_UNICODE);
  }
} catch (Throwable $e) {
  echo json_encode(['success' => false, 'message' => '處理失敗: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
