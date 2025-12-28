<?php
// Public/modules/car/car_statistics_summary_pdf.php
// 半年度總表：中文字型 + 舊版三列表頭樣式 + 多頁才顯示（1）（2）…
// PHP 8 / TCPDF

declare(strict_types=1);

require_once __DIR__ . '/../../../config/auth.php';
require_login();

// ===== 防止任何非 PDF 輸出干擾 =====
@ini_set('zlib.output_compression', '0');
if (ob_get_length()) { @ob_end_clean(); }
header('Content-Type: application/pdf; charset=UTF-8');

// ===== DB（舊站 mysqli）=====
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

// ===== 半年區間與月份陣列 =====
if ($half === '1') {
  $rangeStart = sprintf('%04d-01-01', $year);
  $rangeEnd   = sprintf('%04d-06-30', $year);
  $months     = [1,2,3,4,5,6];
  $periodLabel= sprintf('%d 年 1-6 月', $year);
} else {
  $rangeStart = sprintf('%04d-07-01', $year);
  $rangeEnd   = sprintf('%04d-12-31', $year);
  $months     = [7,8,9,10,11,12];
  $periodLabel= sprintf('%d 年 7-12 月', $year);
}

// ===== 查詢（依車＋月份加總：維修金額 + 公司負擔）=====
$s = mysqli_real_escape_string($db, $rangeStart);
$e = mysqli_real_escape_string($db, $rangeEnd);
$sql = "
  SELECT 
    v.vehicle_id,
    v.license_plate,
    MONTH(r.repair_date) AS m,
    SUM(COALESCE(r.repair_cost,0))    AS amt,
    SUM(COALESCE(r.company_burden,0)) AS bur
  FROM repairs r
  INNER JOIN vehicles v ON r.vehicle_id = v.vehicle_id
  WHERE r.repair_date BETWEEN '$s' AND '$e'
  GROUP BY v.vehicle_id, v.license_plate, m
  ORDER BY v.vehicle_id, m
";
$rs = mysqli_query($db, $sql);

// => $rows[vid] = ['plate'=>..., 'm'=>[month=>amt], 'sumAmt'=>...,'sumBur'=>...]
$rows = [];
$grandByMonth = array_fill_keys($months, 0);
$grandAmt = 0; $grandBur = 0;

if ($rs) {
  while ($r = mysqli_fetch_assoc($rs)) {
    $vid   = (string)$r['vehicle_id'];
    $plate = (string)$r['license_plate'];
    $m     = (int)$r['m'];
    $amt   = (int)$r['amt'];
    $bur   = (int)$r['bur'];

    if (!isset($rows[$vid])) {
      $rows[$vid] = ['plate'=>$plate, 'm'=>array_fill_keys($months, 0), 'sumAmt'=>0, 'sumBur'=>0];
    }
    $rows[$vid]['m'][$m] += $amt;
    $rows[$vid]['sumAmt'] += $amt;
    $rows[$vid]['sumBur'] += $bur;

    $grandByMonth[$m] += $amt;
    $grandAmt         += $amt;
    $grandBur         += $bur;
  }
  mysqli_free_result($rs);
}
$vehicles = array_keys($rows);
natsort($vehicles);
$vehicles = array_values($vehicles);

// ===== TCPDF =====
require_once __DIR__ . '/../../../TCPDF/tcpdf.php';

class CustomPDF extends TCPDF {
  /** @var string 可顯示中文的字型家族名稱（外部注入） */
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
$pdf->SetTitle('境宏工程有限公司車輛維修統計表');
$pdf->SetMargins(10, 12, 10);
$pdf->SetAutoPageBreak(true, 12);

// ---- 字型：優先 TaipeiSansTCBeta-Regular.ttf；次選 TaipeiSansTCBeta.ttf；最後退 cid0ct ----
$fontCandidates = [
  __DIR__ . '/../../../TCPDF/fonts/TaipeiSansTCBeta-Regular.ttf',
  __DIR__ . '/../../../TCPDF/fonts/TaipeiSansTCBeta.ttf',
];
$jhFont = 'cid0ct'; // TCPDF 內建 CJK（繁中）
foreach ($fontCandidates as $fp) {
  if (is_file($fp)) {
    $jhFont = TCPDF_FONTS::addTTFfont($fp, 'TrueTypeUnicode', '', 96);
    break;
  }
}
$pdf->jhFont = $jhFont;
$pdf->SetFont($jhFont, '', 10);

// ===== 欄寬：自動量測「編號／車牌」，其餘平均分配 =====
$totalW = 190; // A4 210 - (10+10)
$pdf->SetFont($jhFont, 'B', 10);
$wHdrId    = $pdf->GetStringWidth('編號') ;
$wHdrPlate = $pdf->GetStringWidth('車牌') ;
$pdf->SetFont($jhFont, '', 10);

$maxId = 0; $maxPlate = 0;
foreach ($vehicles as $vid){
  $maxId    = max($maxId,    $pdf->GetStringWidth($vid));
  $maxPlate = max($maxPlate, $pdf->GetStringWidth($rows[$vid]['plate'] ?? ''));
}
$idW    = max(16, (int)ceil(max($wHdrId,    $maxId   )));
$plateW = max(24, (int)ceil(max($wHdrPlate, $maxPlate)));

// 剩餘寬度平均：6個月份 + 維修金額 + 公司負擔 = 8 欄
$slots   = count($months) + 2;
$remain  = $totalW - ($idW + $plateW);
$unitW   = floor(($remain * 10) / $slots) / 10;
$used    = $idW + $plateW + $unitW * $slots;
$fix     = round($totalW - $used, 1);

$monthW     = $unitW;
$lastMonthW = $unitW;
$sumAmtW    = $unitW;
$sumBurW    = $unitW + $fix;

// ===== 小工具（包一層讓 VS Code 不黃線；參數與 TCPDF::MultiCell 完全一致）=====
/** @param TCPDF $pdf */
function mc(TCPDF $pdf, float $w, float $h, string $txt, $border=1, string $align='C', bool $fill=false, int $ln=0, string $valign='M'): void {
  /** @psalm-suppress MixedArgument */
  $pdf->MultiCell($w, $h, $txt, $border, $align, $fill, $ln, '', '', true, 0, false, true, $h, $valign, false);
}
/** @param TCPDF $pdf */
function mcxy(TCPDF $pdf, float $x, float $y, float $w, float $h, string $txt, $border=1, string $align='C', bool $fill=false, int $ln=0, string $valign='M'): void {
  /** @psalm-suppress MixedArgument */
  $pdf->MultiCell($w, $h, $txt, $border, $align, $fill, $ln, $x, $y, true, 0, false, true, $h, $valign, false);
}
// ↑ 你的這行：$pdf->MultiCell($w,$h,$txt,$border,$align,$fill,$ln,'','',true,0,false,true,$h,$valign,false);
//   參數順序與 TCPDF 定義一致（w,h,txt,border,align,fill,ln,x,y,reseth,stretch,ishtml,autopadding,maxh,valign,fitcell）。
//   黃色波浪多半是靜態分析器對「mixed」型別提醒，已由 wrapper + PHPDoc 壓掉。

// ===== 表頭（三列，與舊版一致）=====
$titleY = 12; // 與上邊距一致
$print_header = function(string $titleLabel = '') use ($pdf,$jhFont,$periodLabel,$months,$idW,$plateW,$monthW,$lastMonthW,$sumAmtW,$sumBurW,$titleY) {
  $h = 8;

  // 標題（單頁不加；多頁會加（2）（3）…，第 1 頁最後回填（1））
  $pdf->SetY($titleY);
  $pdf->SetFont($jhFont, 'B', 16);
  $title = '境宏工程有限公司車輛維修統計表' . $titleLabel;
  $pdf->Cell(0, 8, $title, 0, 1, 'C');
  $pdf->Ln(1);

  // 第1列：統計時間 + 期間（網底）
  $pdf->SetFont($jhFont, 'B', 10);
  $pdf->SetFillColor(240,240,240);
  mc($pdf, $idW + $plateW, $h, '統計時間', 1, 'C', true, 0, 'M');
  $totalPeriodWidth = ($monthW * (count($months)-1)) + $lastMonthW + $sumAmtW + $sumBurW;
  mc($pdf, $totalPeriodWidth, $h, $periodLabel, 1, 'C', true, 1, 'M');

  // 第2列：左「月份」，右側直向合併
  $pdf->SetFillColor(255,255,255);
  $y2 = $pdf->GetY(); $xL = $pdf->GetX();
  mc($pdf, $idW + $plateW, $h, '月份', 1, 'C', false, 0, 'M');
  $x = $pdf->GetX();
  for ($i = 0; $i < count($months); $i++) {
    $w = ($i === count($months)-1) ? $lastMonthW : $monthW;
    mcxy($pdf, $x, $y2, $w, $h * 2, $months[$i] . ' 月', 1, 'C', false, 0, 'M');
    $x += $w;
  }
  mcxy($pdf, $x, $y2, $sumAmtW, $h * 2, '維修金額', 1, 'C', false, 0, 'M'); $x += $sumAmtW;
  mcxy($pdf, $x, $y2, $sumBurW, $h * 2, '公司負擔', 1, 'C', false, 1, 'M');

  // 第3列：只畫左兩格
  $pdf->SetY($y2 + $h); $pdf->SetX($xL);
  mc($pdf, $idW,    $h, '編號', 1, 'C', false, 0, 'M');
  mc($pdf, $plateW, $h, '車牌', 1, 'C', false, 1, 'M');

  // 游標移到表頭底
  $pdf->SetY($y2 + 2 * $h);
  // 🔹 加這行，把字體切回正常
  $pdf->SetFont($jhFont, '', 10);
};

$pdf->AddPage();
$pagesMade = 1;

// 首頁：不加（1）
$print_header('');

// ===== 資料列 =====
$h = 8;
$nf = static function($n) { $n = (int)$n; return $n ? number_format($n) : ''; };

if (count($vehicles) === 0) {
  mc($pdf, 190, $h * 2, '（此期間內無資料）', 1, 'C', false, 1, 'M');
} else {
  foreach ($vehicles as $vid) {
    $bottomY = $pdf->getPageHeight() - $pdf->getBreakMargin();
    if ($pdf->GetY() + $h > $bottomY) {
      $pdf->AddPage();
      $pagesMade++;
      // 從第 2 頁開始顯示（2）（3）…
      $print_header('（'.$pagesMade.'）');
    }

    mc($pdf, $idW,    $h, $vid,                  1, 'C', false, 0, 'M');
    mc($pdf, $plateW, $h, $rows[$vid]['plate'],  1, 'C', false, 0, 'M');

    for ($i=0; $i<count($months); $i++) {
      $m = $months[$i];
      $w = ($i === count($months)-1) ? $lastMonthW : $monthW;
      mc($pdf, $w, $h, $nf($rows[$vid]['m'][$m] ?? 0), 1, 'R', false, 0, 'M');
    }
    mc($pdf, $sumAmtW, $h, $nf($rows[$vid]['sumAmt']), 1, 'R', false, 0, 'M');
    mc($pdf, $sumBurW, $h, $nf($rows[$vid]['sumBur']), 1, 'R', false, 1, 'M');
  }
}

// ===== 合計列 =====
$bottomY = $pdf->getPageHeight() - $pdf->getBreakMargin();
if ($pdf->GetY() + $h > $bottomY) {
  $pdf->AddPage();
  $pagesMade++;
  $print_header('（'.$pagesMade.'）');
}
$pdf->SetFont($jhFont, '', 9);
mc($pdf, $idW + $plateW, $h, '合計', 1, 'C', true, 0, 'M');
for ($i=0; $i<count($months); $i++) {
  $m = $months[$i];
  $w = ($i === count($months)-1) ? $lastMonthW : $monthW;
  mc($pdf, $w, $h, $nf($grandByMonth[$m] ?? 0), 1, 'R', true, 0, 'M');
}
mc($pdf, $sumAmtW, $h, $nf($grandAmt), 1, 'R', true, 0, 'M');
mc($pdf, $sumBurW, $h, $nf($grandBur), 1, 'R', true, 1, 'M');

// ===== 若為多頁，回到第 1 頁補上（1） =====
if ($pagesMade > 1) {
  $pdf->setPage(1);
  $pdf->SetY($titleY);
  $pdf->SetFont($jhFont, 'B', 16);
  // 蓋掉舊標題（用白底），再重畫
  $pdf->SetFillColor(255,255,255);
  $pdf->Cell(0, 8, '', 0, 1, 'C', true);
  $pdf->SetY($titleY);
  $pdf->Cell(0, 8, '境宏工程有限公司車輛維修統計表（1）', 0, 1, 'C');
}

$pdf->lastPage();
$pdf->Output(sprintf('car_stats_summary_%d_H%s.pdf',$year,$half), 'I');
