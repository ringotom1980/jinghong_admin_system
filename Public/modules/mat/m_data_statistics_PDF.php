<?php
// Public/modules/mat/m_data_statistics_PDF.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/auth.php';
require_login();
require_once __DIR__ . '/../../../config/db_connection.php';
require_once __DIR__ . '/../../../TCPDF/tcpdf.php';

// 開啟輸出緩衝區
ob_start();

// 禁用錯誤顯示，記錄到日誌
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');

// 獲取 POST 傳送的 JSON 資料
$data = json_decode(file_get_contents('php://input'), true);
$classData = $data['classData'] ?? []; // 班別數據
$titles = $data['titles'] ?? []; // 標題數據
$date = $data['date'] ?? ''; // 獲取日期

// 確認是否接收到日期
if (empty($classData) || empty($date)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '無有效數據或日期！']);
    exit;
}

class CustomPDF extends TCPDF
{
    // 自定義頁尾
    public function Footer()
    {
        // 設置頁尾字體
        $this->SetY(-15); // 距離頁底 15mm
        $this->SetFont('TaipeiSansTCBeta', '', 10);

        // 添加頁碼，格式為 1, 2, 3...
        $pageNo = $this->PageNo(); // 獲取當前頁碼
        $this->Cell(0, 10, "第 {$pageNo} 頁", 0, 0, 'C'); // 頁碼居中顯示
    }
}


try {
    // 初始化 TCPDF
    $pdf = new CustomPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('領退料管理系統');
    $pdf->SetTitle('班別領退料報表');
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(TRUE, 10);
    $pdf->setPrintHeader(false); // 禁用頁眉

    // 渲染表頭函式
    function renderTableHeader($pdf, $className)
    {
        $pdf->SetFont('TaipeiSansTCBeta', 'B', 8);
        $pdf->SetFillColor(240, 240, 240);

        if (in_array($className, ['A班', 'C班'])) {
            // A班與C班
            //表頭第一行
            $pdf->Cell(10, 14, '項次', 1, 0, 'C', true);
            $pdf->Cell(150, 14, '材料名稱', 1, 0, 'C', true);
            $pdf->Cell(40, 7, '領料', 1, 0, 'C', true);
            $pdf->Cell(40, 7, '退料', 1, 0, 'C', true);
            $pdf->Cell(40, 7, '領退合計', 1, 1, 'C', true);
            //表頭第二行
            $pdf->Cell(10, 0, '', 0, 0); //對齊項次
            $pdf->Cell(150, 0, '', 0, 0); //對齊材料名稱
            $pdf->Cell(20, 7, '新', 1, 0, 'C', true); //領料
            $pdf->Cell(20, 7, '舊', 1, 0, 'C', true); //領料

            $pdf->Cell(20, 7, '新', 1, 0, 'C', true); //退料
            $pdf->Cell(20, 7, '舊', 1, 0, 'C', true); //退料

            $pdf->Cell(20, 7, '新', 1, 0, 'C', true); //合計
            $pdf->Cell(20, 7, '舊', 1, 1, 'C', true); //合計
        } elseif (in_array($className, ['E班'])) {
            // E班與F班
            //表頭第一行
            $pdf->Cell(10, 14, '項次', 1, 0, 'C', true);
            $pdf->Cell(150, 14, '材料名稱', 1, 0, 'C', true);
            $pdf->Cell(60, 7, '領料', 1, 0, 'C', true);
            $pdf->Cell(60, 7, '退料', 1, 1, 'C', true);
            //表頭第二行
            $pdf->Cell(10, 0, '', 0, 0); //對齊項次
            $pdf->Cell(150, 0, '', 0, 0); //對齊材料名稱
            $pdf->Cell(30, 7, '新', 1, 0, 'C', true); //領料
            $pdf->Cell(30, 7, '舊', 1, 0, 'C', true); //領料

            $pdf->Cell(30, 7, '新', 1, 0, 'C', true); //退料
            $pdf->Cell(30, 7, '舊', 1, 1, 'C', true); //退料
        } elseif (in_array($className, ['F班'])) {
            // E班與F班
            //表頭第一行
            $pdf->Cell(10, 14, '項次', 1, 0, 'C', true);
            $pdf->Cell(150, 14, '材料名稱', 1, 0, 'C', true);
            $pdf->Cell(60, 7, '領料', 1, 0, 'C', true);
            $pdf->Cell(60, 7, '退料', 1, 1, 'C', true);
            //表頭第二行
            $pdf->Cell(10, 0, '', 0, 0); //對齊項次
            $pdf->Cell(150, 0, '', 0, 0); //對齊材料名稱
            $pdf->Cell(30, 7, '新', 1, 0, 'C', true); //領料
            $pdf->Cell(30, 7, '舊', 1, 0, 'C', true); //領料

            $pdf->Cell(30, 7, '新', 1, 0, 'C', true); //退料
            $pdf->Cell(30, 7, '舊', 1, 1, 'C', true); //退料
        } elseif ($className === 'B班') {
            // B班
            //表頭第一行
            $pdf->Cell(10, 14, '項次', 1, 0, 'C', true);
            $pdf->Cell(90, 14, '材料名稱', 1, 0, 'C', true);
            $pdf->Cell(80, 7, '領料', 1, 0, 'C', true);
            $pdf->Cell(80, 7, '退料', 1, 0, 'C', true);
            $pdf->Cell(20, 7, '領退合計', 1, 1, 'C', true);
            //表頭第二行
            $pdf->Cell(10, 0, '', 0, 0); //對齊項次
            $pdf->Cell(90, 0, '', 0, 0); //對齊材料名稱
            $pdf->Cell(10, 7, '新', 1, 0, 'C', true); //領料
            $pdf->Cell(30, 7, '筆數', 1, 0, 'C', true); //領料
            $pdf->Cell(10, 7, '舊', 1, 0, 'C', true); //領料
            $pdf->Cell(30, 7, '筆數', 1, 0, 'C', true); //領料

            $pdf->Cell(10, 7, '新', 1, 0, 'C', true); //退料
            $pdf->Cell(30, 7, '筆數', 1, 0, 'C', true); //退料
            $pdf->Cell(10, 7, '舊', 1, 0, 'C', true); //退料
            $pdf->Cell(30, 7, '筆數', 1, 0, 'C', true); //退料

            $pdf->Cell(10, 7, '新', 1, 0, 'C', true); //合計
            $pdf->Cell(10, 7, '舊', 1, 1, 'C', true); //合計
        } elseif ($className === 'D班') {
            // D班
            //表頭第一行
            $pdf->Cell(10, 14, '項次', 1, 0, 'C', true);
            $pdf->Cell(130, 14, '材料名稱', 1, 0, 'C', true);
            $pdf->Cell(40, 7, '領料', 1, 0, 'C', true);
            $pdf->Cell(40, 7, '退料', 1, 0, 'C', true);
            $pdf->Cell(20, 7, '對帳資料', 1, 0, 'C', true);
            $pdf->Cell(40, 7, '領退合計', 1, 1, 'C', true);
            //表頭第二行
            $pdf->Cell(10, 0, '', 0, 0); //對齊項次
            $pdf->Cell(130, 0, '', 0, 0); //對齊材料名稱
            $pdf->Cell(20, 7, '新', 1, 0, 'C', true); //領料
            $pdf->Cell(20, 7, '舊', 1, 0, 'C', true); //領料
            $pdf->Cell(20, 7, '新', 1, 0, 'C', true); //退料
            $pdf->Cell(20, 7, '舊', 1, 0, 'C', true); //退料
            $pdf->Cell(20, 7, '數值', 1, 0, 'C', true); //對帳資料(加入日期)
            $pdf->Cell(20, 7, '新', 1, 0, 'C', true); //合計
            $pdf->Cell(20, 7, '舊', 1, 1, 'C', true); //合計
        }
    }

    // 渲染內容函式
    function renderTableContent($pdf, $tableData, $className, $titleText, $date)
    {
        $pdf->SetFont('TaipeiSansTCBeta', '', 8);

        foreach ($tableData as $rowIndex => $row) {
            // 設置背景色（奇數行為淺灰色，偶數行為白色）
            if ($rowIndex % 2 === 0) {
                $pdf->SetFillColor(245, 245, 245); // 淺灰色
            } else {
                $pdf->SetFillColor(255, 255, 255); // 白色
            }
            // 檢查是否需要新增頁面
            if ($pdf->GetY() > 190) { // 調整 190 為合適的分頁高度
                $pdf->AddPage();

                // 重繪公司名稱
                $pdf->SetFont('TaipeiSansTCBeta', 'B', 16);
                $pdf->Cell(0, 10, '境宏工程有限公司', 0, 1, 'C');

                // 重繪班別標題與日期
                $pdf->SetFont('TaipeiSansTCBeta', '', 12);
                $pdf->Cell(0, 10, $titleText, 0, 0, 'L');
                $pdf->Cell(0, 10, "領退日期：{$date}", 0, 1, 'R');

                // 重繪表頭
                renderTableHeader($pdf, $className);
                // 重設表格內容的字體（確保內容不為粗體）
                $pdf->SetFont('TaipeiSansTCBeta', '', 8);
            }

            if (in_array($className, ['A班', 'C班'])) {

                $pdf->Cell(10, 7, $row[0], 1, 0, 'C', 1); // 項次
                $pdf->Cell(150, 7, $row[2], 1, 0, 'L', 1); // 材料名稱
                $pdf->Cell(20, 7, $row[3], 1, 0, 'C', 1); // 領料（新）
                $pdf->Cell(20, 7, $row[4], 1, 0, 'C', 1); // 領料（舊）
                $pdf->Cell(20, 7, $row[5], 1, 0, 'C', 1); // 退料（新）
                $pdf->Cell(20, 7, $row[6], 1, 0, 'C', 1); // 退料（舊）
                // 檢查負數，負數紅色，大於0藍色
                $pdf->SetTextColor(
                    $row[7] < 0 ? 255 : ($row[7] > 0 ? 0 : 0),
                    $row[7] < 0 ? 0 : ($row[7] > 0 ? 0 : 0),
                    $row[7] > 0 ? 255 : 0
                );
                $pdf->Cell(20, 7, $row[7], 1, 0, 'C', 1); // 合計（新）
                // 檢查負數，負數紅字體
                $pdf->SetTextColor($row[8] < 0 ? 255 : 0, 0, 0); // 列索引 8
                $pdf->Cell(20, 7, $row[8], 1, 1, 'C', 1); // 合計（舊）
                // 恢復字體顏色
                $pdf->SetTextColor(0, 0, 0);
            } elseif (in_array($className, ['E班','F班'])) {
                $pdf->Cell(10, 7, $row[0], 1, 0, 'C', 1); // 項次
                $pdf->Cell(150, 7, $row[2], 1, 0, 'L', 1); // 材料名稱
                // 檢查負數，負數紅色，大於0藍色
                $pdf->SetTextColor(
                    $row[3] < 0 ? 255 : ($row[3] > 0 ? 0 : 0),
                    $row[3] < 0 ? 0 : ($row[3] > 0 ? 0 : 0),
                    $row[3] > 0 ? 255 : 0
                );
                $pdf->Cell(30, 7, $row[3], 1, 0, 'C', 1); // 領料（新）
                // 恢復字體顏色
                $pdf->SetTextColor(0, 0, 0);
                $pdf->Cell(30, 7, $row[4], 1, 0, 'C', 1); // 領料（舊）
                // 檢查負數，負數紅字體
                $pdf->SetTextColor($row[5] > 0 ? 255 : 0, 0, 0); // 列索引 5
                $pdf->Cell(30, 7, $row[5], 1, 0, 'C', 1); // 退料（新）
                // 檢查負數，負數紅字體
                $pdf->SetTextColor($row[6] > 0 ? 255 : 0, 0, 0); // 列索引 6
                $pdf->Cell(30, 7, $row[6], 1, 1, 'C', 1); // 退料（舊）
                // 恢復字體顏色
                $pdf->SetTextColor(0, 0, 0);
            } elseif ($className === 'B班') {
                $pdf->Cell(10, 7, $row[0], 1, 0, 'C', 1); // 項次
                $pdf->Cell(90, 7, $row[2], 1, 0, 'L', 1); // 材料名稱
                $pdf->Cell(10, 7, $row[3], 1, 0, 'C', 1); // 領料（新）
                $pdf->Cell(30, 7, $row[4], 1, 0, 'C', 1); // 筆數
                $pdf->Cell(10, 7, $row[5], 1, 0, 'C', 1); // 領料（舊）
                $pdf->Cell(30, 7, $row[6], 1, 0, 'C', 1); // 筆數
                $pdf->Cell(10, 7, $row[7], 1, 0, 'C', 1); // 退料（新）
                $pdf->Cell(30, 7, $row[8], 1, 0, 'C', 1); // 筆數
                $pdf->Cell(10, 7, $row[9], 1, 0, 'C', 1); // 退料（舊）
                $pdf->Cell(30, 7, $row[10], 1, 0, 'C', 1); // 筆數
                // 檢查負數，負數紅色，大於0藍色
                $pdf->SetTextColor(
                    $row[11] < 0 ? 255 : ($row[11] > 0 ? 0 : 0),
                    $row[11] < 0 ? 0 : ($row[11] > 0 ? 0 : 0),
                    $row[11] > 0 ? 255 : 0
                );
                $pdf->Cell(10, 7, $row[11], 1, 0, 'C', 1); // 合計（新）
                // 檢查負數，負數紅字體
                $pdf->SetTextColor($row[12] < 0 ? 255 : 0, 0, 0); // 列索引 12
                $pdf->Cell(10, 7, $row[12], 1, 1, 'C', 1); // 合計（舊）
                // 恢復字體顏色
                $pdf->SetTextColor(0, 0, 0);
            } elseif ($className === 'D班') {
                $pdf->Cell(10, 7, $row[0], 1, 0, 'C', 1); // 項次
                $pdf->Cell(130, 7, $row[2], 1, 0, 'L', 1); // 材料名稱
                $pdf->Cell(20, 7, $row[3], 1, 0, 'C', 1); // 領料（新）
                $pdf->Cell(20, 7, $row[4], 1, 0, 'C', 1); // 領料（舊）
                $pdf->Cell(20, 7, $row[5], 1, 0, 'C', 1); // 退料（新）
                $pdf->Cell(20, 7, $row[6], 1, 0, 'C', 1); // 退料（舊）
                $pdf->Cell(20, 7, $row[7], 1, 0, 'C', 1); // 對帳資料
                // 檢查負數，負數紅色，大於0藍色
                $pdf->SetTextColor(
                    $row[8] < 0 ? 255 : ($row[8] > 0 ? 0 : 0),
                    $row[8] < 0 ? 0 : ($row[8] > 0 ? 0 : 0),
                    $row[8] > 0 ? 255 : 0
                );
                $pdf->Cell(20, 7, $row[8], 1, 0, 'C', 1); // 合計（新）
                // 檢查負數，負數紅字體
                $pdf->SetTextColor($row[9] < 0 ? 255 : 0, 0, 0); // 列索引 9
                $pdf->Cell(20, 7, $row[9], 1, 1, 'C', 1); // 合計（舊）
                // 恢復字體顏色
                $pdf->SetTextColor(0, 0, 0);
            }
        }
    }

    // 逐班別生成報表
    foreach ($classData as $className => $tableData) {
        $pdf->AddPage();

        // 添加公司名稱
        $pdf->SetFont('TaipeiSansTCBeta', 'B', 16);
        $pdf->Cell(0, 10, '境宏工程有限公司', 0, 1, 'C');

        // 從 $titles 中找到對應的標題
        $title = array_filter($titles, fn($t) => $t['className'] === $className);
        $titleText = !empty($title) ? $title[array_key_first($title)]['title'] : "{$className} 報表";

        // 添加班別標題與日期
        $pdf->SetFont('TaipeiSansTCBeta', '', 12);
        $pdf->Cell(0, 10, $titleText, 0, 0, 'L');
        $pdf->Cell(0, 10, "領退日期：{$date}", 0, 1, 'R');

        // 渲染表頭與內容
        renderTableHeader($pdf, $className);
        renderTableContent($pdf, $tableData, $className, $titleText, $date);
    }

    ob_clean(); // 清除多餘輸出
    $pdf->Output('班別_領退料_報表.pdf', 'D');
} catch (Exception $e) {
    file_put_contents('error_log.txt', $e->getMessage());
    exit('Error generating PDF.');
}