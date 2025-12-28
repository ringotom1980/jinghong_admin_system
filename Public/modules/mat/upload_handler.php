<?php

declare(strict_types=1);

/**
 * 位置：/Public/modules/mat/upload_handler.php
 * 功能：處理「資料編輯」的 Excel 上傳，寫入 m_data_material_number
 * 依賴：PhpSpreadsheet、auth.php、db_connection.php
 */

// ---- 登入與資料庫（新站結構） ---- //
require_once __DIR__ . '/../../../config/auth.php';
require_login();
require_once __DIR__ . '/../../../config/db_connection.php'; // 提供 $conn (PDO)

// ---- 載入 PhpSpreadsheet ---- //
//require 'Autoloader.php';
require_once __DIR__ . '/Autoloader.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// 立刻檢查是否真的載入到了
if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '未找到 PhpSpreadsheet：請確認 /Public/modules/mat/vendor/ 是否齊全（或新站的 /vendor/）。'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}


try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Method Not Allowed');
    }
    if (empty($_FILES['upload_s']) || !is_array($_FILES['upload_s']['name'])) {
        throw new Exception('請選擇要上傳的檔案');
    }

    // 領退料時間（日期）
    $withdrawDate = $_POST['withdraw_time'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $withdrawDate)) {
        throw new Exception('請提供正確的領退料時間（YYYY-MM-DD）');
    }

    // 解析＋寫入的暫存容器
$rows = []; // 由各處理器填入：每筆含 voucher, material_number, material_name, 數量欄位...
$countUploaded = 0;

// 同批檔案成功才提交
$conn->beginTransaction();

// 關閉本連線的 safe updates（保險）
$conn->exec("SET SESSION sql_safe_updates = 0");

$files = $_FILES['upload_s'];
$N = count($files['name']);

for ($i = 0; $i < $N; $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK || !is_uploaded_file($files['tmp_name'][$i])) {
        $res['messages'][] = "檔案 {$files['name'][$i]} 上傳失敗，錯誤碼：{$files['error'][$i]}";
        continue;
    }

    $fileName     = $files['name'][$i];
    $fileTmpPath  = $files['tmp_name'][$i];
    $fileBaseName = pathinfo($fileName, PATHINFO_FILENAME); // voucher_base
    $fileType     = strtoupper(substr($fileBaseName, 0, 1)); // L/K/W/T/S

    // 先嘗試：原條件刪除 + LIMIT（safe updates 可通過）
    $deleted = 0;
    try {
        $del = $conn->prepare("
            DELETE FROM m_data_material_number
            WHERE withdraw_date = :d
              AND voucher LIKE :vb
            LIMIT 1000000
        ");
        $del->execute([':d' => $withdrawDate, ':vb' => $fileBaseName . '%']);
        $deleted = $del->rowCount();

    } catch (PDOException $ex) {
        // 若仍被 1175 擋，fallback：查 id → IN 常數分批刪
        if (strpos($ex->getMessage(), '1175') !== false) {
            $sel = $conn->prepare("
                SELECT id
                FROM m_data_material_number
                WHERE withdraw_date = :d
                  AND voucher LIKE :vb
            ");
            $sel->execute([':d' => $withdrawDate, ':vb' => $fileBaseName . '%']);
            $ids = $sel->fetchAll(PDO::FETCH_COLUMN, 0);

            if (!empty($ids)) {
                foreach (array_chunk($ids, 1000) as $chunk) {
                    $ph = implode(',', array_fill(0, count($chunk), '?'));
                    $sql = "DELETE FROM m_data_material_number WHERE id IN ($ph)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($chunk);
                    $deleted += $stmt->rowCount();
                }
            }
        } else {
            // 其他錯誤丟出去讓外層捕捉
            throw $ex;
        }
    }

    $res['messages'][] = '刪除舊資料：voucher_base=' . $fileBaseName . '%，日期=' . $withdrawDate . '，筆數=' . $deleted;

    // 解析
    switch ($fileType) {
        case 'L':
        case 'K':
        case 'W':
            processFile_LKW($fileTmpPath, $fileType, $rows, $fileBaseName);
            $res['messages'][] = "解析成功：{$fileName}";
            $countUploaded++;
            break;
        case 'T':
            processFile_T($fileTmpPath, $rows, $fileBaseName);
            $res['messages'][] = "解析成功：{$fileName}";
            $countUploaded++;
            break;
        case 'S':
            processFile_S($fileTmpPath, $rows, $fileBaseName);
            $res['messages'][] = "解析成功：{$fileName}";
            $countUploaded++;
            break;
        default:
            $res['messages'][] = "略過不支援檔案：{$fileName}";
            break;
    }
}


    // 寫入 DB
    if (empty($rows)) {
        throw new Exception('未解析到有效數據（請檢查上傳檔內容/表頭是否正確）');
    }

    // 事先準備 insert statement
    $ins = $conn->prepare("
    INSERT INTO m_data_material_number
      (voucher, material_number, material_name,
       collar_New, collar_Old, recede_New, recede_Old, scrap, footprint,
       shift, withdraw_date)
    VALUES
      (:voucher, :material_number, :material_name,
       :collar_New, :collar_Old, :recede_New, :recede_Old, :scrap, :footprint,
       :shift, :withdraw_date)
  ");

    // 依序寫入；shift 若在 m_data_reconciliation_record 找不到則給空字串
    $qShift = $conn->prepare("SELECT shift FROM m_data_reconciliation_record WHERE material_number = :mn LIMIT 1");

    $inserted = 0;
    foreach ($rows as $r) {
        // 取 shift
        $qShift->execute([':mn' => $r['material_number']]);
        $shiftRow = $qShift->fetch(PDO::FETCH_ASSOC);
        $shift    = $shiftRow && isset($shiftRow['shift']) ? (string)$shiftRow['shift'] : '';

        // 綁定與數值預設（字串存 decimal 也可，由 PDO 負責轉）
        $ins->bindValue(':voucher',         $r['voucher'], PDO::PARAM_STR);
        $ins->bindValue(':material_number', $r['material_number'], PDO::PARAM_STR);
        $ins->bindValue(':material_name',   $r['material_name'] ?? '', PDO::PARAM_STR);

        $ins->bindValue(':collar_New',  isset($r['collar_New'])  ? number_format((float)$r['collar_New'],  2, '.', '') : '0.00', PDO::PARAM_STR);
        $ins->bindValue(':collar_Old',  isset($r['collar_Old'])  ? number_format((float)$r['collar_Old'],  2, '.', '') : '0.00', PDO::PARAM_STR);
        $ins->bindValue(':recede_New',  isset($r['recede_New'])  ? number_format((float)$r['recede_New'],  2, '.', '') : '0.00', PDO::PARAM_STR);
        $ins->bindValue(':recede_Old',  isset($r['recede_Old'])  ? number_format((float)$r['recede_Old'],  2, '.', '') : '0.00', PDO::PARAM_STR);
        $ins->bindValue(':scrap',       isset($r['scrap'])       ? number_format((float)$r['scrap'],       2, '.', '') : '0.00', PDO::PARAM_STR);
        $ins->bindValue(':footprint',   isset($r['footprint'])   ? number_format((float)$r['footprint'],   2, '.', '') : '0.00', PDO::PARAM_STR);

        $ins->bindValue(':shift', $shift, PDO::PARAM_STR);
        $ins->bindValue(':withdraw_date', $withdrawDate, PDO::PARAM_STR);

        $ins->execute();
        $inserted++;
    }

    // 補：shift 為空的材料，回傳給前端開分班視窗
    $miss = $conn->query("
    SELECT material_number, name_specification
    FROM m_data_reconciliation_record
    WHERE shift = ''
  ")->fetchAll(PDO::FETCH_ASSOC);

    $conn->commit();

    $res['success'] = true;
    $res['messages'][] = "完成：處理檔案 {$countUploaded} 份，寫入 {$inserted} 筆";
    $res['missingShiftRecords'] = $miss ?: [];
} catch (Throwable $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $res['success']  = false;
    $res['messages'][] = '處理檔案時發生錯誤：' . $e->getMessage();
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);

/* =========================
 *  檔案解析：L/K/W
 * ========================= */
function processFile_LKW(string $fileTmpPath, string $fileType, array &$rows, string $voucherBase): void
{
    $spreadsheet = IOFactory::load($fileTmpPath);
    $ws = $spreadsheet->getActiveSheet();

    // 動態找表頭
    $headers = [];
    $headerRowIndex = null;
    foreach ($ws->getRowIterator() as $row) {
        $iter = $row->getCellIterator();
        $iter->setIterateOnlyExistingCells(false);
        foreach ($iter as $idx => $cell) {
            $val = trim((string)$cell->getValue());
            if (in_array($val, ['領料批號', '材料編號', '材料名稱', '新料數量', '舊料數量'], true)) {
                $headers[$idx] = $val;
                $headerRowIndex = $row->getRowIndex();
            }
        }
        if ($headerRowIndex !== null) break;
    }
    if ($headerRowIndex === null) throw new Exception("檔案類型 {$fileType} 未找到標題行");

    $seq = 1;
    foreach ($ws->getRowIterator($headerRowIndex + 1) as $row) {
        $iter = $row->getCellIterator();
        $iter->setIterateOnlyExistingCells(false);
        $r = [];
        foreach ($iter as $idx => $cell) {
            if (!isset($headers[$idx])) continue;
            $h = $headers[$idx];
            $v = trim((string)$cell->getValue());
            switch ($h) {
                case '領料批號':
                    $r['voucher'] = $voucherBase . '_' . $seq++;
                    break;
                case '材料編號':
                    $r['material_number'] = $v;
                    break;
                case '材料名稱':
                    $r['material_name'] = $v;
                    break;
                case '新料數量':
                    $r['collar_New'] = is_numeric($v) ? (float)$v : 0.0;
                    break;
                case '舊料數量':
                    $r['collar_Old'] = is_numeric($v) ? (float)$v : 0.0;
                    break;
            }
        }
        if (!empty($r['voucher']) && !empty($r['material_number'])) $rows[] = $r;
    }
}

/* =========================
 *  檔案解析：T（退料 + 廢料/下腳）
 * ========================= */
function processFile_T(string $fileTmpPath, array &$rows, string $voucherBase): void
{
    $spreadsheet = IOFactory::load($fileTmpPath);
    $ws = $spreadsheet->getActiveSheet();

    $headers = [];
    $headerRowIndex = null;
    foreach ($ws->getRowIterator() as $row) {
        $iter = $row->getCellIterator();
        $iter->setIterateOnlyExistingCells(false);
        foreach ($iter as $idx => $cell) {
            $v = trim((string)$cell->getValue());
            if (in_array($v, ['憑證批號', '拆除原材料編號', '拆除原材料名稱', '拆除良數量', '材料編號', '材料名稱及規範', '廢料數量', '下腳數量'], true)) {
                $headers[$idx] = $v;
                $headerRowIndex = $row->getRowIndex();
            }
        }
        if ($headerRowIndex !== null) break;
    }
    if ($headerRowIndex === null) throw new Exception('T 單未找到標題行');

    $seq = 1;
    foreach ($ws->getRowIterator($headerRowIndex + 1) as $row) {
        $iter = $row->getCellIterator();
        $iter->setIterateOnlyExistingCells(false);
        $tmp = [];
        foreach ($iter as $idx => $cell) {
            if (!isset($headers[$idx])) continue;
            $h = $headers[$idx];
            $v = trim((string)$cell->getValue());
            $tmp[$h] = is_numeric($v) ? (float)$v : ($v === '' ? 0 : $v);
        }

        // 1) 拆除良數量 -> recede_Old
        $qty = $tmp['拆除良數量'] ?? 0;
        if (is_numeric($qty) && (float)$qty != 0.0) {
            $rows[] = [
                'voucher'         => $voucherBase . '_' . $seq++,
                'material_number' => (string)($tmp['拆除原材料編號'] ?? ''),
                'material_name'   => (string)($tmp['拆除原材料名稱'] ?? ''),
                'recede_Old'      => (float)$qty,
            ];
        }

        // 2) 廢料/下腳 -> scrap / footprint
        $scrap     = $tmp['廢料數量']   ?? 0;
        $footprint = $tmp['下腳數量']   ?? 0;
        $scrapValid = is_numeric($scrap) && (float)$scrap != 0.0;
        $footValid  = is_numeric($footprint) && (float)$footprint != 0.0;

        if ($scrapValid || $footValid) {
            $rows[] = [
                'voucher'         => $voucherBase . '_' . $seq++,
                'material_number' => (string)($tmp['材料編號'] ?? ''),
                'material_name'   => (string)($tmp['材料名稱及規範'] ?? ''),
                'scrap'           => $scrapValid ? (float)$scrap : 0.0,
                'footprint'       => $footValid  ? (float)$footprint : 0.0,
            ];
        }
    }
}

/* =========================
 *  檔案解析：S（用餘）
 * ========================= */
function processFile_S(string $fileTmpPath, array &$rows, string $voucherBase): void
{
    $spreadsheet = IOFactory::load($fileTmpPath);
    $ws = $spreadsheet->getActiveSheet();

    $headers = [];
    $headerRowIndex = null;
    foreach ($ws->getRowIterator() as $row) {
        $iter = $row->getCellIterator();
        $iter->setIterateOnlyExistingCells(false);
        foreach ($iter as $idx => $cell) {
            $v = trim((string)$cell->getValue());
            if (in_array($v, ['憑證編號', '材料編號', '材料名稱及規範', '新料', '舊料', '廢料', '下腳'], true)) {
                $headers[$idx] = $v;
                $headerRowIndex = $row->getRowIndex();
            }
        }
        if ($headerRowIndex !== null) break;
    }
    if ($headerRowIndex === null) throw new Exception('S 單未找到標題行');

    $seq = 1;
    foreach ($ws->getRowIterator($headerRowIndex + 1) as $row) {
        $iter = $row->getCellIterator();
        $iter->setIterateOnlyExistingCells(false);
        $r = [];
        $has = false;
        foreach ($iter as $idx => $cell) {
            if (!isset($headers[$idx])) continue;
            $h = $headers[$idx];
            $v = trim((string)$cell->getValue());
            switch ($h) {
                case '憑證編號':
                    $r['voucher'] = $voucherBase . '_' . $seq++;
                    $has = true;
                    break;
                case '材料編號':
                    $r['material_number'] = $v;
                    $has = true;
                    break;
                case '材料名稱及規範':
                    $r['material_name']   = $v;
                    break;
                case '新料':
                    $r['recede_New']      = is_numeric($v) ? (float)$v : 0.0;
                    break;
                case '舊料':
                    $r['recede_Old']      = is_numeric($v) ? (float)$v : 0.0;
                    break;
                case '廢料':
                    $r['scrap']           = is_numeric($v) ? (float)$v : 0.0;
                    break;
                case '下腳':
                    $r['footprint']       = is_numeric($v) ? (float)$v : 0.0;
                    break;
            }
        }
        if ($has && !empty($r['voucher']) && !empty($r['material_number'])) $rows[] = $r;
    }
}
