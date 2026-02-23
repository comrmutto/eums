<?php
/**
 * Air Compressor Module - Export Running Hours Report to Excel
 */

// เริ่มต้นป้องกัน Error ขยะหลุดไปในไฟล์ Excel
ob_start();
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized access');
}

// โหลดไลบรารี PhpSpreadsheet
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

$db = getDB();

// 1. รับค่าและแปลงวันที่
$rawStartDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
if (strpos($rawStartDate, '/') !== false) {
    $parts = explode('/', $rawStartDate);
    $startDate = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
} else {
    $startDate = $rawStartDate;
}

$rawEndDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
if (strpos($rawEndDate, '/') !== false) {
    $parts = explode('/', $rawEndDate);
    $endDate = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
} else {
    $endDate = $rawEndDate;
}

// 2. ดึงรายชื่อเครื่อง Air Compressor ทั้งหมด
$stmt = $db->query("SELECT * FROM mc_air WHERE status = 1 ORDER BY machine_code ASC");
$machines = $stmt->fetchAll();

if (empty($machines)) {
    $machines = [
        ['id' => 1, 'machine_code' => 'Air Comp.No.1'],
        ['id' => 2, 'machine_code' => 'Air Comp.No.2'],
        ['id' => 3, 'machine_code' => 'Air Comp.No.3'],
        ['id' => 4, 'machine_code' => 'Air Comp.No.4']
    ];
}

// 3. ดึงข้อมูลบันทึกรายวัน
$sql = "SELECT * FROM air_daily_records WHERE record_date BETWEEN ? AND ?";
$stmt = $db->prepare($sql);
$stmt->execute([$startDate, $endDate]);
$records = $stmt->fetchAll();

// จัดกลุ่มข้อมูล
$dataByDate = [];
foreach ($records as $row) {
    $date = $row['record_date'];
    $mId = $row['machine_id'];
    
    if (!isset($dataByDate[$date])) {
        $dataByDate[$date] = [];
    }
    if (!isset($dataByDate[$date][$mId])) {
        $dataByDate[$date][$mId] = ['before' => 0, 'after' => 0];
    }
    
    if ($row['before_value'] > 0 || $row['after_value'] > 0) {
        $dataByDate[$date][$mId] = [
            'before' => floatval($row['before_value']),
            'after' => floatval($row['after_value'])
        ];
    }
}

// 4. สร้าง Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Air Compressor Record');
$spreadsheet->getDefaultStyle()->getFont()->setName('Sarabun')->setSize(10);

// --- แถวที่ 1: ชื่อรายงาน ---
$lastColCount = count($machines) * 3 + 1;
$lastColLetter = Coordinate::stringFromColumnIndex($lastColCount);

$sheet->setCellValue('A1', 'Air Compressor Record (' . date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate)) . ')');
$sheet->mergeCells("A1:{$lastColLetter}1");
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F4E78'); // สีน้ำเงินเข้ม
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

// --- แถวที่ 2-4: สร้างหัวตาราง (Headers) ---
$sheet->setCellValue('A2', 'Date');
$sheet->mergeCells('A2:A4');
$sheet->getStyle('A2:A4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9D9D9'); // สีเทา

$colIndex = 2; // เริ่มคอลัมน์ B
foreach ($machines as $machine) {
    $startCol = Coordinate::stringFromColumnIndex($colIndex);       // B
    $midCol = Coordinate::stringFromColumnIndex($colIndex + 1);     // C
    $endCol = Coordinate::stringFromColumnIndex($colIndex + 2);     // D
    
    // ชื่อเครื่อง
    $sheet->setCellValue($startCol . '2', $machine['machine_code']);
    $sheet->mergeCells("{$startCol}2:{$endCol}2");
    $sheet->getStyle("{$startCol}2:{$endCol}2")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4472C4'); // สีน้ำเงิน
    $sheet->getStyle("{$startCol}2:{$endCol}2")->getFont()->getColor()->setARGB('FFFFFFFF');
    
    // Data record & Operate time
    $sheet->setCellValue($startCol . '3', 'Data record');
    $sheet->mergeCells("{$startCol}3:{$midCol}3");
    $sheet->setCellValue($endCol . '3', 'Operate time');
    $sheet->getStyle("{$startCol}3:{$endCol}3")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFB4C6E7'); // สีฟ้าอ่อน
    
    // before / after / hr
    $sheet->setCellValue($startCol . '4', 'before');
    $sheet->setCellValue($midCol . '4', 'after');
    $sheet->setCellValue($endCol . '4', 'hr');
    $sheet->getStyle("{$startCol}4:{$endCol}4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9E1F2'); // สีฟ้าจางๆ
    
    $colIndex += 3;
}

// จัดรูปแบบหัวตาราง
$headerRange = "A2:{$lastColLetter}4";
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle($headerRange)->getFont()->setBold(true);

// 5. วนลูปใส่วันที่และข้อมูล
$currentRow = 5;
$currentDateTs = strtotime($startDate);
$endDateTs = strtotime($endDate);

while ($currentDateTs <= $endDateTs) {
    $dateStr = date('Y-m-d', $currentDateTs);
    $sheet->setCellValue('A' . $currentRow, $dateStr);
    $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $cIdx = 2;
    foreach ($machines as $machine) {
        $mId = $machine['id'];
        $beforeCol = Coordinate::stringFromColumnIndex($cIdx);
        $afterCol = Coordinate::stringFromColumnIndex($cIdx + 1);
        $hrCol = Coordinate::stringFromColumnIndex($cIdx + 2);
        
        $b = isset($dataByDate[$dateStr][$mId]) ? $dataByDate[$dateStr][$mId]['before'] : 0;
        $a = isset($dataByDate[$dateStr][$mId]) ? $dataByDate[$dateStr][$mId]['after'] : 0;
        $hr = ($a > 0 && $b > 0) ? ($a - $b) : 0;
        
        // เงื่อนไขที่ 1: ถ้าเป็น 0 ให้ปล่อยเป็นค่าว่าง ('') 
        $sheet->setCellValue($beforeCol . $currentRow, $b > 0 ? $b : '');
        $sheet->setCellValue($afterCol . $currentRow, $a > 0 ? $a : '');
        $sheet->setCellValue($hrCol . $currentRow, $hr > 0 ? $hr : '');
        
        $cIdx += 3;
    }
    $currentRow++;
    $currentDateTs = strtotime('+1 day', $currentDateTs);
}

// ขอบเขตตารางข้อมูลหลัก
$lastDataRow = $currentRow - 1;

// --- 6. เพิ่มตาราง Sum และ Average ด้านล่าง ---
$sumRow = $currentRow;
$avgRow = $currentRow + 1;

$sheet->setCellValue("A{$sumRow}", 'SUM (ผลรวม)');
$sheet->setCellValue("A{$avgRow}", 'AVERAGE (ค่าเฉลี่ย)');
$sheet->getStyle("A{$sumRow}:A{$avgRow}")->getFont()->setBold(true);
$sheet->getStyle("A{$sumRow}:A{$avgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

$cIdx = 2;
foreach ($machines as $machine) {
    $hrCol = Coordinate::stringFromColumnIndex($cIdx + 2);
    
    // ใส่สูตรคำนวณลงใน Excel
    $sheet->setCellValue("{$hrCol}{$sumRow}", "=SUM({$hrCol}5:{$hrCol}{$lastDataRow})");
    $sheet->setCellValue("{$hrCol}{$avgRow}", "=IFERROR(AVERAGEIF({$hrCol}5:{$hrCol}{$lastDataRow}, \">0\"), 0)");
    
    // กำหนดฟอร์แมตทศนิยม 2 ตำแหน่ง
    $sheet->getStyle("{$hrCol}{$sumRow}:{$hrCol}{$avgRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    
    $cIdx += 3;
}

// ตกแต่งสีสันแถว Sum/Avg
$sheet->getStyle("A{$sumRow}:{$lastColLetter}{$avgRow}")->applyFromArray([
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFE699']], // สีเหลืองอ่อน
    'font' => ['bold' => true]
]);

// ตีเส้นขอบทั้งหมด
$sheet->getStyle("A2:{$lastColLetter}{$avgRow}")->applyFromArray([
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
]);

// 1. ปรับคอลัมน์ A (วันที่) ให้ขยายอัตโนมัติตามเนื้อหา
$sheet->getColumnDimension('A')->setAutoSize(true);

// 2. บังคับคอลัมน์ B เป็นต้นไป ให้มีความกว้างเท่ากันทั้งหมด (เพื่อให้ตารางสมมาตร)
for ($i = 2; $i <= $lastColCount; $i++) {
    $colLetter = Coordinate::stringFromColumnIndex($i);
    $sheet->getColumnDimension($colLetter)->setAutoSize(false); 
    $sheet->getColumnDimension($colLetter)->setWidth(13); 
}

// เปิดระบบตัดบรรทัด (Wrap Text) ให้กับหัวตาราง เผื่อหน้าจอบางเครื่องฟอนต์ใหญ่จะได้ไม่ล้นกรอบ
$sheet->getStyle("A2:{$lastColLetter}4")->getAlignment()->setWrapText(true);

// --- 7. สร้างตารางซ่อนเพื่อดึงข้อมูลไปทำกราฟ ---
$tableGraphRowStart = $avgRow + 4;
$sheet->setCellValue("A{$tableGraphRowStart}", 'Machine');
$sheet->setCellValue("B{$tableGraphRowStart}", 'Avg Operate Time (hr)');

$r = $tableGraphRowStart + 1;
$cIdx = 2;
$catStart = $r;
foreach ($machines as $machine) {
    $hrCol = Coordinate::stringFromColumnIndex($cIdx + 2);
    $sheet->setCellValue("A{$r}", $machine['machine_code']);
    $sheet->setCellValue("B{$r}", "={$hrCol}{$avgRow}"); // อ้างอิงเซลล์ค่าเฉลี่ยด้านบน
    $r++;
    $cIdx += 3;
}
$catEnd = $r - 1;

// --- 8. สร้างกราฟ (Bar Chart) ---
$sheetNameForChart = "'" . $sheet->getTitle() . "'"; 

$dataSeriesLabels = [
    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $sheetNameForChart . '!$B$'.$tableGraphRowStart, null, 1),
];
$xAxisTickValues = [
    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $sheetNameForChart . '!$A$'.$catStart.':$A$'.$catEnd, null, count($machines)),
];
$dataSeriesValues = [
    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $sheetNameForChart . '!$B$'.$catStart.':$B$'.$catEnd, null, count($machines)),
];

$series = new DataSeries(
    DataSeries::TYPE_BARCHART,       // กราฟแท่ง
    DataSeries::GROUPING_STANDARD,   
    range(0, count($dataSeriesValues) - 1), 
    $dataSeriesLabels,               
    $xAxisTickValues,                
    $dataSeriesValues                
);
$series->setPlotDirection(DataSeries::DIRECTION_COL);

$plotArea = new PlotArea(null, [$series]);
$legend = new Legend(Legend::POSITION_RIGHT, null, false);
$title = new Title('Average Operate Time per Machine');

$chart = new Chart('chart1', $title, $legend, $plotArea, true, 0, null, null);
$chart->setTopLeftPosition('D' . $tableGraphRowStart); // ตำแหน่งมุมซ้ายบนของกราฟ
$chart->setBottomRightPosition('L' . ($tableGraphRowStart + 15)); // ตำแหน่งมุมขวาล่างของกราฟ

$sheet->addChart($chart);

// --- 9. ส่งออกเป็น Excel (.xlsx) ---
$filename = 'AirCompressor_Record_' . date('Ym') . '.xlsx';

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1'); 

$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true); // *** สำคัญมาก: ต้องเปิดคำสั่งนี้เพื่อเซฟกราฟติดไปด้วย ***
$writer->save('php://output');
exit();