<?php
// Public/modules/equ/equ_statistics_summary_pdf.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/auth.php';
require_login();

// ===== 防止任何非 PDF 輸出干擾 =====
@ini_set('zlib.output_compression', '0');
if (ob_get_length()) { @ob_end_clean(); }
header('Content-Type: application/pdf; charset=UTF-8');

// ===== DB（mysqli）=====
$env = is_file(__DIR__ . '/../../../config/.env.php') ? require __DIR__ . '/../../../config/.env.php' : [];
$db  = mysqli_connect($env['DB_HOST'] ?? '', $env['DB_USER'] ?? '', $env['DB_PASS'] ?? '', $env['DB_NAME'] ?? '');
if (!$db) { http_response_code(500); exit('DB connect error'); }
mysqli_set_charset($db, 'utf8mb4');

// ===== 僅允許半年度 =====
$filterType = $_GET['filterType'] ?? ($_POST['filterType'] ?? '');
$year  = (int)($_GET['halfYear'] ?? $_POST['halfYear'] ?? $_GET['year'] ?? $_POST['year'] ?? 0);
$half  = (string)($_GET['half'] ?? $_POST['half'] ?? '');
if ($filterType !== 'half_year' || !$year || ($half !== '1' && $half !== '2')) {
  http_response_code(400);
  exit('BAD_REQUEST');
}

// ===== 半年區間與月份陣列（標籤僅顯示「上半年／下半年」）=====
if ($half === '1') {
  $rangeStart = sprintf('%04d-01-01', $year);
  $rangeEnd   = sprintf('%04d-06-30', $year);
  $months     = [1,2,3,4,5,6];
  $periodLabel= sprintf('%d 年 上半年 (1-6月)', $year);   // ← 調整文字
} else {
  $rangeStart = sprintf('%04d-07-01', $year);
  $rangeEnd   = sprintf('%04d-12-31', $year);
  $months     = [7,8,9,10,11,12];
  $periodLabel= sprintf('%d 年 下半年 (7-12月)', $year);   // ← 調整文字
}

// ===== 查詢（依「廠商 × 月份」加總）=====
$s = mysqli_real_escape_string($db, $rangeStart);
$e = mysqli_real_escape_string($db, $rangeEnd);
$sql = "
  SELECT 
    mr.vendor_name_norm AS vkey,
    MIN(mr.vendor_name) AS vendor_name,
    MONTH(mr.repair_date) AS m,
    SUM(COALESCE(mr.repair_cost,0))    AS amt,
    SUM(COALESCE(mr.company_burden,0)) AS bur
  FROM machine_repairs mr
  WHERE mr.repair_date BETWEEN '$s' AND '$e'
  GROUP BY vkey, m
  ORDER BY vendor_name, m
";
$rs = mysqli_query($db, $sql);

// => $rows[vkey] = ['vendor'=>..., 'm'=>[month=>amt], 'sumAmt'=>...,'sumBur'=>...]
$rows = [];
$grandByMonth = array_fill_keys($months, 0);
$grandAmt = 0; $grandBur = 0;

if ($rs) {
  while ($r = mysqli_fetch_assoc($rs)) {
    $key   = (string)$r['vkey'];
    $ven   = (string)$r['vendor_name'];
    $m     = (int)$r['m'];
    $amt   = (int)$r['amt'];
    $bur   = (int)$r['bur'];

    if (!isset($rows[$key])) {
      $rows[$key] = ['vendor'=>$ven, 'm'=>array_fill_keys($months, 0), 'sumAmt'=>0, 'sumBur'=>0];
    }
    $rows[$key]['m'][$m] += $amt;
    $rows[$key]['sumAmt'] += $amt;
    $rows[$key]['sumBur'] += $bur;

    if (isset($grandByMonth[$m])) $grandByMonth[$m] += $amt;
    $grandAmt += $amt;
    $grandBur += $bur;
  }
  mysqli_free_result($rs);
}
$vendors = array_keys($rows);
natsort($vendors);
$vendors = array_values($vendors);

// ===== TCPDF =====
require_once __DIR__ . '/../../../TCPDF/tcpdf.php';

class CustomPDF extends TCPDF {
  public $jhFont = 'cid0ct';
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
$pdf->SetTitle('境宏工程有限公司機具維修統計表');
$pdf->SetMargins(10, 12, 10);
$pdf->SetAutoPageBreak(true, 12);

// ---- 字型（標題粗、表格內一般字重）----
$fontCandidates = [
  __DIR__ . '/../../../TCPDF/fonts/TaipeiSansTCBeta-Regular.ttf',
  __DIR__ . '/../../../TCPDF/fonts/TaipeiSansTCBeta.ttf',
];
$jhFont = 'cid0ct';
foreach ($fontCandidates as $fp) {
  if (is_file($fp)) { $jhFont = TCPDF_FONTS::addTTFfont($fp, 'TrueTypeUnicode', '', 96); break; }
}
$pdf->jhFont = $jhFont;

// ===== 欄寬：加上「項次」欄 =====
$totalW = 190;
$pdf->SetFont($jhFont, '', 10);

$wHdrIdx   = max(12, (int)ceil($pdf->GetStringWidth('項次') + 6));
$wHdrVendor= max(24, (int)ceil($pdf->GetStringWidth('維修廠商') + 10));
$slots     = count($months) + 2;   // 月份 + 維修金額 + 公司負擔
$remain    = $totalW - ($wHdrIdx + $wHdrVendor);
$unitW     = floor(($remain * 10) / $slots) / 10;
$monthW    = $unitW;
$sumAmtW   = $unitW;
$sumBurW   = $unitW;

// ===== 小工具 =====
function mc(TCPDF $pdf, float $w, float $h, string $txt, $border=1, string $align='C', bool $fill=false, int $ln=0, string $valign='M'): void {
  $pdf->MultiCell($w, $h, $txt, $border, $align, $fill, $ln, '', '', true, 0, false, true, $h, $valign, false);
}
function mcxy(TCPDF $pdf, float $x, float $y, float $w, float $h, string $txt, $border=1, string $align='C', bool $fill=false, int $ln=0, string $valign='M'): void {
  $pdf->MultiCell($w, $h, $txt, $border, $align, $fill, $ln, $x, $y, true, 0, false, true, $h, $valign, false);
}

// ===== 表頭（3 列；標題粗體，其餘一般字重）=====
$titleY = 12;
$print_header = function(string $titleSuffix = '') use ($pdf,$jhFont,$periodLabel,$months,$wHdrIdx,$wHdrVendor,$monthW,$sumAmtW,$sumBurW,$titleY) {
  $h = 8;

  // 標題（粗體；多頁加（2）（3）…；第1頁不加）
  $pdf->SetY($titleY);
  $pdf->SetFont($jhFont, 'B', 16);
  $pdf->Cell(0, 8, '境宏工程有限公司機具維修統計表' . $titleSuffix, 0, 1, 'C');
  $pdf->Ln(1);

  // 第1列：統計時間 + 期間（網底）
  $pdf->SetFont($jhFont, '', 10); // ← 表格內一般字重
  $pdf->SetFillColor(240,240,240);
  mc($pdf, $wHdrIdx + $wHdrVendor, $h, '統計時間', 1, 'C', true, 0, 'M');
  $totalPeriodWidth = ($monthW * count($months)) + $sumAmtW + $sumBurW;
  mc($pdf, $totalPeriodWidth, $h, $periodLabel, 1, 'C', true, 1, 'M');

  // 第2列：左「月份」標題，右側直向合併（月份＋金額＋公司負擔）
  $pdf->SetFillColor(255,255,255);
  $y2 = $pdf->GetY(); $xL = $pdf->GetX();
  mc($pdf, $wHdrIdx + $wHdrVendor, $h, '月份', 1, 'C', false, 0, 'M');
  $x = $pdf->GetX();
  foreach ($months as $m) { mcxy($pdf, $x, $y2, $monthW, $h * 2, $m.' 月', 1, 'C', false, 0, 'M'); $x += $monthW; }
  mcxy($pdf, $x, $y2, $sumAmtW, $h * 2, '維修金額', 1, 'C', false, 0, 'M'); $x += $sumAmtW;
  mcxy($pdf, $x, $y2, $sumBurW, $h * 2, '公司負擔', 1, 'C', false, 1, 'M');

  // 第3列：項次、維修廠商
  $pdf->SetY($y2 + $h); $pdf->SetX($xL);
  mc($pdf, $wHdrIdx,    $h, '項次',   1, 'C', false, 0, 'M');
  mc($pdf, $wHdrVendor, $h, '維修廠商', 1, 'C', false, 1, 'M');

  $pdf->SetY($y2 + 2 * $h);
};

$pdf->AddPage();
$pagesMade = 1;
$print_header(''); // 首頁不加（1）

// ===== 資料列 =====
$h = 8;
$nf = static function($n) { $n = (int)$n; return $n ? number_format($n) : ''; };

if (count($vendors) === 0) {
  mc($pdf, 190, $h * 2, '（此期間內無資料）', 1, 'C', false, 1, 'M');
} else {
  $idx = 0;
  foreach ($vendors as $key) {
    $idx++;

    $bottomY = $pdf->getPageHeight() - $pdf->getBreakMargin();
    if ($pdf->GetY() + $h > $bottomY) {
      $pdf->AddPage();
      $pagesMade++;
      $print_header('（'.$pagesMade.'）'); // 第2頁起加註（2）（3）…
    }

    mc($pdf, $wHdrIdx,    $h, (string)$idx,           1, 'C', false, 0, 'M');
    mc($pdf, $wHdrVendor, $h, $rows[$key]['vendor'],  1, 'C', false, 0, 'M');

    foreach ($months as $m) { mc($pdf, $monthW, $h, $nf($rows[$key]['m'][$m] ?? 0), 1, 'R', false, 0, 'M'); }
    mc($pdf, $sumAmtW, $h, $nf($rows[$key]['sumAmt']), 1, 'R', false, 0, 'M');
    mc($pdf, $sumBurW, $h, $nf($rows[$key]['sumBur']), 1, 'R', false, 1, 'M');
  }
}

// ===== 合計列 =====
$bottomY = $pdf->getPageHeight() - $pdf->getBreakMargin();
if ($pdf->GetY() + $h > $bottomY) {
  $pdf->AddPage();
  $pagesMade++;
  $print_header('（'.$pagesMade.'）');
}
$pdf->SetFillColor(240,240,240);
mc($pdf, $wHdrIdx + $wHdrVendor, $h, '合計', 1, 'C', true, 0, 'M');
foreach ($months as $m) { mc($pdf, $monthW, $h, $nf($grandByMonth[$m] ?? 0), 1, 'R', true, 0, 'M'); }
mc($pdf, $sumAmtW, $h, $nf($grandAmt), 1, 'R', true, 0, 'M');
mc($pdf, $sumBurW, $h, $nf($grandBur), 1, 'R', true, 1, 'M');

// ===== 若為多頁，回到第 1 頁補上（1） =====
if ($pagesMade > 1) {
  $pdf->setPage(1);
  $pdf->SetY($titleY);
  $pdf->SetFont($jhFont, 'B', 16);
  $pdf->SetFillColor(255,255,255);
  $pdf->Cell(0, 8, '', 0, 1, 'C', true);
  $pdf->SetY($titleY);
  $pdf->Cell(0, 8, '境宏工程有限公司機具維修統計表（1）', 0, 1, 'C');
}

$pdf->lastPage();
$pdf->Output(sprintf('equ_stats_summary_%d_H%s.pdf',$year,$half), 'I');
