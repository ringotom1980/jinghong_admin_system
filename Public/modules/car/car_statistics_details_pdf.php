<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/auth.php';
require_login();

/* 防止干擾輸出 */
@ini_set('zlib.output_compression', '0');
if (ob_get_length()) { @ob_end_clean(); }
header('Content-Type: application/pdf; charset=UTF-8');

/* DB */
$env = is_file(__DIR__ . '/../../../config/.env.php') ? require __DIR__ . '/../../../config/.env.php' : [];
$db  = mysqli_connect($env['DB_HOST'] ?? '', $env['DB_USER'] ?? '', $env['DB_PASS'] ?? '', $env['DB_NAME'] ?? '');
if (!$db) { http_response_code(500); exit('DB connect error'); }
mysqli_set_charset($db, 'utf8mb4');

/* 參數與期間 */
$filterType  = $_GET['filterType'] ?? 'all';
$year        = (int)($_GET['year'] ?? $_GET['halfYear'] ?? $_GET['quarterYear'] ?? $_GET['monthYear'] ?? 0);
$half        = (string)($_GET['half'] ?? '');
$quarter     = (string)($_GET['quarter'] ?? '');
$month       = (string)($_GET['month'] ?? '');
$startDateIn = trim((string)($_GET['startDate'] ?? ''));
$endDateIn   = trim((string)($_GET['endDate'] ?? ''));
$vehicleIdIn = trim((string)($_GET['vehicleId'] ?? ''));

$start = null; $end = null; $periodLabel = '全部';
if ($filterType === 'year' && $year) {
  $start = sprintf('%04d-01-01', $year); $end = sprintf('%04d-12-31', $year);
  $periodLabel = sprintf('%d 年', $year);
} elseif ($filterType === 'half_year' && $year && ($half==='1'||$half==='2')) {
  if ($half==='1') { $start = "$year-01-01"; $end = "$year-06-30"; $periodLabel = sprintf('%d 年上半年', $year); }
  else { $start = "$year-07-01"; $end = "$year-12-31"; $periodLabel = sprintf('%d 年下半年', $year); }
} elseif ($filterType === 'quarter' && $year && in_array($quarter, ['1','2','3','4'], true)) {
  $map = [1=>['01-01','03-31'],2=>['04-01','06-30'],3=>['07-01','09-30'],4=>['10-01','12-31']];
  [$s,$e] = $map[(int)$quarter]; $start = "$year-$s"; $end = "$year-$e";
  $periodLabel = sprintf('%d 年第 %d 季', $year, (int)$quarter);
} elseif ($filterType === 'month' && $year && $month) {
  $start = sprintf('%04d-%02d-01', $year, (int)$month);
  $end   = date('Y-m-t', strtotime($start));
  $periodLabel = sprintf('%d 年 %d 月', $year, (int)$month);
} elseif ($filterType === 'range' && $startDateIn !== '' && $endDateIn !== '') {
  $start = $startDateIn; $end = $endDateIn;
  $periodLabel = $start.' ～ '.$end;
}
$rangeStr = ($start && $end) ? '（'.$start.' ～ '.$end.'）' : '';

/* where */
$where = '1=1';
if ($start && $end) {
  $s = mysqli_real_escape_string($db, $start);
  $e = mysqli_real_escape_string($db, $end);
  $where = "r.repair_date BETWEEN '$s' AND '$e'";
}
if ($vehicleIdIn !== '') {
  $vid = mysqli_real_escape_string($db, $vehicleIdIn);
  $where .= " AND r.vehicle_id = '$vid'";
}

/* 查詢 */
$sql = "
  SELECT
    r.repair_id, r.vehicle_id, v.license_plate, r.repair_date,
    r.vendor, r.category, r.mileage, r.items_json,
    r.repair_cost, r.company_burden
  FROM repairs r
  INNER JOIN vehicles v ON r.vehicle_id = v.vehicle_id
  WHERE $where
  ORDER BY v.vehicle_id, r.repair_date ASC, r.repair_id ASC
";
$rs = mysqli_query($db, $sql);

/* 整理 */
$byVehicle = [];
if ($rs) {
  while ($r = mysqli_fetch_assoc($rs)) {
    $vid = (string)$r['vehicle_id'];
    if (!isset($byVehicle[$vid])) {
      $byVehicle[$vid] = ['plate'=>$r['license_plate'], 'rows'=>[], 'sumAmt'=>0, 'sumBur'=>0];
    }
    $items = json_decode($r['items_json'] ?? '[]', true) ?: [];
    $parts = [];
    foreach ($items as $it) {
      $c = trim((string)($it['content'] ?? ''));
      $a = isset($it['amount']) ? (int)round((float)$it['amount']) : null;
      if ($c === '') continue;
      $parts[] = $a !== null ? ($c.'（'.number_format($a,0).'元）') : $c;
    }
    $byVehicle[$vid]['rows'][] = [
      'date'=>$r['repair_date'],
      'vendor'=>(string)($r['vendor'] ?? ''),
      'cat'=>(string)($r['category'] ?? ''),
      'mile'=>($r['mileage'] !== null && $r['mileage'] !== '') ? (int)$r['mileage'] : '',
      'summary'=>implode('、', $parts),
      'amt'=>(int)$r['repair_cost'],
      'bur'=>(int)($r['company_burden'] ?? 0),
    ];
    $byVehicle[$vid]['sumAmt'] += (int)$r['repair_cost'];
    $byVehicle[$vid]['sumBur'] += (int)($r['company_burden'] ?? 0);
  }
  mysqli_free_result($rs);
}

/* TCPDF */
require_once __DIR__ . '/../../../TCPDF/tcpdf.php';

class CustomPDF extends TCPDF {
  public string $jhFont = 'cid0ct';
  public float  $hdrY_itemno = 0.0; // 編號那一行的 Y，供回填 (1) 用

  public function Footer() {
    $this->SetY(-15);
    $this->SetFont($this->jhFont, '', 9);
    $this->Cell(0, 10, '第 '.$this->getAliasNumPage().' / '.$this->getAliasNbPages().' 頁', 0, 0, 'C');
  }
}

$pdf = new CustomPDF('P','mm','A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setFontSubsetting(true);
$pdf->SetCreator('Jinghong Admin');
$pdf->SetAuthor('Jinghong');
$pdf->SetTitle('境宏工程有限公司車輛維修明細');
$pdf->SetMargins(10, 12, 10);
$pdf->SetAutoPageBreak(true, 12);

/* 字型 */
$fontPath = __DIR__ . '/../../../TCPDF/fonts/TaipeiSansTCBeta-Regular.ttf';
$jhFont = 'cid0ct';
if (is_file($fontPath)) $jhFont = TCPDF_FONTS::addTTFfont($fontPath, 'TrueTypeUnicode', '', 96);
$pdf->jhFont = $jhFont;
$pdf->SetFont($jhFont, '', 10);

/* 工具 */
function mc(TCPDF $pdf, float $w, float $h, string $txt, $border=1, string $align='C', bool $fill=false, int $ln=0, string $valign='M'): void {
  $pdf->MultiCell($w,$h,$txt,$border,$align,$fill,$ln,'','',true,0,false,true,$h,$valign,false);
}

/* 表頭（每車每頁重印；partNo 放在【編號】後） */
function print_vehicle_header(CustomPDF $pdf, string $vid, string $plate, string $periodLabel, string $rangeStr, int $partNo,
                              float $wIdx,float $wDate,float $wVendor,float $wCat,float $wMileage,float $wItems,float $wAmt,float $wBur): void {
  $pdf->SetY(12); // 讓位置固定，方便回填（1）
  $pdf->SetFont($pdf->jhFont, 'B', 16);
  $pdf->Cell(0, 8, '境宏工程有限公司車輛維修明細', 0, 1, 'C');

  $pdf->SetFont($pdf->jhFont, '', 10);
  $pdf->hdrY_itemno = $pdf->GetY(); // 記錄「編號」行的 Y
  $lab = '車輛編號：'.$vid.($partNo>1 ? '（'.$partNo.'）' : '');
  $pdf->Cell(0, 7, $lab, 0, 1, 'L');
  $pdf->Cell(0, 7, '車牌號碼：'.$plate, 0, 1, 'L');
  $pdf->Cell(0, 7, '統計時間：'.$periodLabel.$rangeStr, 0, 1, 'L');
  $pdf->Ln(2);

  $pdf->SetFont($pdf->jhFont, 'B', 10);
  $pdf->SetFillColor(240,240,240);
  mc($pdf,$wIdx,    8,'項次',    1,'C',true,0,'M');
  mc($pdf,$wDate,   8,'維修時間',1,'C',true,0,'M');
  mc($pdf,$wVendor, 8,'維修廠商',1,'C',true,0,'M');
  mc($pdf,$wCat,    8,'維修類型',1,'C',true,0,'M');
  mc($pdf,$wMileage,8,'里程數',  1,'C',true,0,'M');
  mc($pdf,$wItems,  8,'維修項目',1,'C',true,0,'M');
  mc($pdf,$wAmt,    8,'維修金額',1,'C',true,0,'M');
  mc($pdf,$wBur,    8,'公司負擔',1,'C',true,1,'M');
  $pdf->SetFont($pdf->jhFont, '', 9); // 之後資料列用一般字重
}

/* 欄寬（總 190） */
$wIdx=10; $wDate=20; $wVendor=20; $wCat=18; $wMileage=18; $wItems=64; $wAmt=20; $wBur=20;

$pdf->AddPage();

if (empty($byVehicle)) {
  print_vehicle_header($pdf, '－', '－', $periodLabel, $rangeStr, 1, $wIdx,$wDate,$wVendor,$wCat,$wMileage,$wItems,$wAmt,$wBur);
  $pdf->Cell(0, 10, '（此期間內無維修明細）', 0, 1, 'C');
} else {
  $firstVehicle = true;
  foreach ($byVehicle as $vid => $info) {
    if (!$firstVehicle) { $pdf->AddPage(); }
    $firstVehicle = false;

    $startPage = $pdf->getPage();   // 這台車第一頁
    $part = 1;

    print_vehicle_header($pdf, (string)$vid, (string)$info['plate'], $periodLabel, $rangeStr, /*partNo*/1, $wIdx,$wDate,$wVendor,$wCat,$wMileage,$wItems,$wAmt,$wBur);

    $idx = 1;
    foreach ($info['rows'] as $r) {
      $txtDate   = $r['date'] ?: '－';
      $txtVendor = $r['vendor'] !== '' ? $r['vendor'] : '－';
      $txtCat    = $r['cat']    !== '' ? $r['cat']    : '－';
      $txtMile   = ($r['mile'] !== '' && $r['mile'] !== null) ? number_format((int)$r['mile']) : '－';
      $txtItems  = $r['summary'] !== '' ? $r['summary'] : '－';
      $txtAmt    = number_format((int)$r['amt']);
      $txtBur    = number_format((int)$r['bur']);

      $hItems = $pdf->getStringHeight($wItems, $txtItems);
      $rowH   = max(8, $hItems);

      $bottomY = $pdf->getPageHeight() - $pdf->getBreakMargin();
      if ($pdf->GetY() + $rowH > $bottomY) {
        $pdf->AddPage();
        $part++;
        print_vehicle_header($pdf, (string)$vid, (string)$info['plate'], $periodLabel, $rangeStr, $part, $wIdx,$wDate,$wVendor,$wCat,$wMileage,$wItems,$wAmt,$wBur);
      }

      mc($pdf,$wIdx,    $rowH,(string)$idx,  1,'C',false,0,'M');
      mc($pdf,$wDate,   $rowH,$txtDate,      1,'C',false,0,'M');
      mc($pdf,$wVendor, $rowH,$txtVendor,    1,'C',false,0,'M');
      mc($pdf,$wCat,    $rowH,$txtCat,       1,'C',false,0,'M');
      mc($pdf,$wMileage,$rowH,$txtMile,      1,'C',false,0,'M');
      mc($pdf,$wItems,  $rowH,$txtItems,     1,'L',false,0,'M');
      mc($pdf,$wAmt,    $rowH,$txtAmt,       1,'R',false,0,'M');
      mc($pdf,$wBur,    $rowH,$txtBur,       1,'R',false,1,'M');
      $idx++;
    }

    /* 小計（非粗體） */
    $pdf->SetFillColor(245,245,245);
    $pdf->SetFont($pdf->jhFont, '', 9);
    mc($pdf,$wIdx + $wDate + $wVendor + $wCat + $wMileage + $wItems, 8, '小計', 1,'C',true,0,'M');
    mc($pdf,$wAmt, 8, number_format((int)$info['sumAmt']), 1,'R',true,0,'M');
    mc($pdf,$wBur, 8, number_format((int)$info['sumBur']), 1,'R',true,1,'M');

    /* 若跨頁：回到該車第一頁，把「編號：A-01」覆蓋成「編號：A-01（1）」 */
    if ($part > 1) {
      $pdf->setPage($startPage);
      $pdf->SetY($pdf->hdrY_itemno);
      $pdf->SetFont($pdf->jhFont, '', 10);
      $pdf->SetFillColor(255,255,255);
      // 先蓋白，再寫入含（1）的版本
      $pdf->Cell(0, 7, '', 0, 1, 'L', true);
      $pdf->SetY($pdf->hdrY_itemno);
      $pdf->Cell(0, 7, '車輛編號：'.$vid.'（1）', 0, 1, 'L');
      // 回到最後一頁繼續
      $pdf->setPage($pdf->getNumPages());
    }
  }
}

$pdf->lastPage();
$pdf->Output('car_statistics_details.pdf', 'I');
