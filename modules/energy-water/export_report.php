<?php
/**
 * Export Report - Energy & Water Module
 * Engineering Utility Monitoring System (EUMS)
 * Form Format: ELECTRIC POWER MDB DIARY REPORT
 * Replicates energy_water-record.xlsx layout exactly
 */

// เริ่มต้นป้องกัน Error ขยะหลุดไปในไฟล์ Excel
ob_start();
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /eums/login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
// เพิ่มไลบรารีสำหรับทำกราฟ
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

$db = getDB();

$format      = isset($_GET['format'])      ? $_GET['format']      : 'excel';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily';

function formatDateForDB($date_str) {
    if (strpos($date_str, '/') !== false) {
        $parts = explode('/', $date_str);
        if (count($parts) == 3) {
            $year = (int)$parts[2];
            if ($year > 2500) $year -= 543;
            return sprintf('%04d-%02d-%02d', $year, $parts[1], $parts[0]);
        }
    }
    return $date_str;
}

$raw_start  = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$raw_end    = isset($_GET['end_date'])   ? $_GET['end_date']   : date('Y-m-d');
$start_date = formatDateForDB($raw_start);
$end_date   = formatDateForDB($raw_end);

$filename = 'energy_water_report_' . date('Ymd_His');

// Fetch all meter readings for the selected month
function getMonthReadings($db, $start_date, $end_date) {
    $sql = "
        SELECT
            r.record_date,
            m.meter_code,
            r.morning_reading,
            r.evening_reading,
            r.usage_amount
        FROM meter_daily_readings r
        JOIN mc_mdb_water m ON r.meter_id = m.id
        WHERE r.record_date BETWEEN ? AND ?
        ORDER BY r.record_date ASC, m.meter_code ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$rawRecords = getMonthReadings($db, $start_date, $end_date);

// Organize by date and meter_code
$byDate = [];
foreach ($rawRecords as $row) {
    $byDate[$row['record_date']][$row['meter_code']] = [
        'read'  => $row['morning_reading'],
        'read2' => $row['evening_reading'],
        'total' => $row['usage_amount'],
    ];
}

// ============================================================
// BUILD EXCEL — matching energy_water-record.xlsx exactly
// ============================================================

$meterStmt = $db->query("
    SELECT meter_code, meter_name, meter_type
    FROM mc_mdb_water
    WHERE status = 1
    ORDER BY meter_type DESC, meter_code ASC
");
$meterList = $meterStmt->fetchAll(PDO::FETCH_ASSOC);

$colLetters = [];
for ($i = 1; $i <= 26; $i++) $colLetters[] = chr(64 + $i);
for ($i = 1; $i <= 26; $i++) for ($j = 1; $j <= 26; $j++) $colLetters[] = chr(64+$i).chr(64+$j);

$meterCols = [];
$colIdx = 2; // เริ่มที่คอลัมน์ C (index 2)
foreach ($meterList as $m) {
    $meterCols[$m['meter_code']] = [
        $colLetters[$colIdx],      // READ col
        $colLetters[$colIdx + 1],  // TOTAL/DAY col
        $m['meter_name'],
        $m['meter_type'],
    ];
    $colIdx += 2;
}
$lastColLetter = $colLetters[$colIdx - 1];

$firstDay    = date('Y-m-01', strtotime($start_date));
$daysInMonth = (int)date('t', strtotime($firstDay));
$monthLabel  = strtoupper(date('F Y', strtotime($firstDay)));

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('MDB DIARY REPORT');
$spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

// ---- COLUMN WIDTHS ----
$sheet->getColumnDimension('A')->setWidth(13.0);
$sheet->getColumnDimension('B')->setWidth(9.0);
foreach ($meterCols as $code => [$readCol, $totalCol, $meterName, $meterType]) {
    $sheet->getColumnDimension($readCol)->setWidth(14.0);
    $sheet->getColumnDimension($totalCol)->setWidth(12.0);
}

$thin   = Border::BORDER_THIN;
$allThin = ['borders' => ['allBorders' => ['borderStyle' => $thin]]];
$centerMid = ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER];

// ============================================================
// ROW 1 to 4 — Headers
// ============================================================
$sheet->getRowDimension(1)->setRowHeight(20.0);
$sheet->setCellValue('A1', 'ELECTRIC POWER MDB DIARY REPORT');
$sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14], 'borders' => ['bottom' => ['borderStyle' => $thin]]]);

$midCode    = array_keys($meterCols)[intdiv(count($meterCols), 2)] ?? array_key_first($meterCols);
$midReadCol = $meterCols[$midCode][0];
$midTotCol  = $meterCols[$midCode][1];
$sheet->mergeCells("{$midReadCol}1:{$midTotCol}1");
$sheet->setCellValue("{$midReadCol}1", 'MONTH   ' . $monthLabel);
$sheet->getStyle("{$midReadCol}1:{$midTotCol}1")->applyFromArray(['font' => ['bold' => true, 'size' => 11], 'alignment' => $centerMid, 'borders' => ['bottom' => ['borderStyle' => $thin]]]);

$lastCodes   = array_keys($meterCols);
$lastCode    = end($lastCodes);
$checkCol    = $meterCols[$lastCode][0];
$sheet->setCellValue("{$checkCol}1", 'CHECK BY……………..APPROVE…………………..'); 
$sheet->getStyle("{$checkCol}1")->getFont()->setBold(true);

$sheet->getRowDimension(2)->setRowHeight(25.0);
$sheet->mergeCells('A2:A4');
$sheet->setCellValue('A2', 'DATE');
$sheet->getStyle('A2:A4')->applyFromArray(['font' => ['bold' => true, 'size' => 11], 'alignment' => $centerMid, 'borders' => $allThin['borders']]);

$sheet->mergeCells('B2:B3');
$sheet->setCellValue('B2', 'ITEM');
$sheet->getStyle('B2:B3')->applyFromArray(['font' => ['bold' => true, 'size' => 11], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_BOTTOM], 'borders' => ['top' => ['borderStyle' => $thin], 'left' => ['borderStyle' => $thin], 'right' => ['borderStyle' => $thin]]]);

foreach ($meterCols as $code => [$readCol, $totalCol, $meterName, $meterType]) {
    $sheet->mergeCells("{$readCol}2:{$totalCol}2");
    $sheet->setCellValue("{$readCol}2", $meterName);
    $sheet->getStyle("{$readCol}2:{$totalCol}2")->applyFromArray(['font' => ['bold' => true], 'alignment' => $centerMid, 'borders' => $allThin['borders']]);
}

$sheet->getRowDimension(3)->setRowHeight(20.0);
foreach ($meterCols as $code => [$readCol, $totalCol, $meterName, $meterType]) {
    $unitLabel = ($meterType === 'electricity') ? 'Energy (kWh)' : 'Water (m3)';
    $sheet->mergeCells("{$readCol}3:{$totalCol}3");
    $sheet->setCellValue("{$readCol}3", $unitLabel);
    $sheet->getStyle("{$readCol}3:{$totalCol}3")->applyFromArray(['font' => ['bold' => true], 'alignment' => $centerMid, 'borders' => ['left' => ['borderStyle' => $thin], 'top' => ['borderStyle' => $thin], 'bottom' => ['borderStyle' => $thin]]]);
    $sheet->getStyle("{$totalCol}3")->applyFromArray(['borders' => ['right' => ['borderStyle' => $thin]]]);
}
$sheet->getStyle('B3')->applyFromArray(['borders' => ['left' => ['borderStyle' => $thin], 'right' => ['borderStyle' => $thin]]]);

$sheet->getRowDimension(4)->setRowHeight(18.0);
$sheet->setCellValue('B4', 'TIME');
$sheet->getStyle('B4')->applyFromArray(['font' => ['bold' => true], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER], 'borders' => $allThin['borders']]);
$sheet->getStyle('A4')->applyFromArray(['borders' => ['left' => ['borderStyle' => $thin], 'right' => ['borderStyle' => $thin], 'bottom' => ['borderStyle' => $thin]]]);

foreach ($meterCols as $code => [$readCol, $totalCol]) {
    $sheet->setCellValue("{$readCol}4", 'READ');
    $sheet->setCellValue("{$totalCol}4", 'TOTAL/DAY');
    $sheet->getStyle("{$readCol}4:{$totalCol}4")->applyFromArray(['font' => ['bold' => true], 'alignment' => $centerMid, 'borders' => $allThin['borders']]);
}

// ============================================================
// DATA ROWS
// ============================================================
$baseRow = 5;
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dateStr  = date('Y-m', strtotime($firstDay)) . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
    $dispDate = date('d/m/Y', strtotime($dateStr));

    $rowD = $baseRow + ($d - 1) * 2;
    $rowN = $rowD + 1;

    $sheet->getRowDimension($rowD)->setRowHeight(20.0);
    $sheet->getRowDimension($rowN)->setRowHeight(20.0);

    $sheet->mergeCells("A{$rowD}:A{$rowN}");
    $sheet->setCellValue("A{$rowD}", $dispDate);
    $sheet->getStyle("A{$rowD}:A{$rowN}")->applyFromArray(['font' => ['bold' => true], 'alignment' => $centerMid, 'borders' => $allThin['borders']]);

    foreach ([[$rowD, '09:00'], [$rowN, '24:00']] as [$row, $time]) {
        $sheet->setCellValue("B{$row}", $time);
        $sheet->getStyle("B{$row}")->applyFromArray(['font' => ['bold' => true], 'alignment' => $centerMid, 'borders' => $allThin['borders']]);
    }

    foreach ($meterCols as $code => [$readCol, $totalCol]) {
        $readMorn = ''; $readEve = ''; $totalDay = '';
        if (isset($byDate[$dateStr][$code])) {
            $rec = $byDate[$dateStr][$code];
            $readMorn = $rec['read'] !== null ? $rec['read'] : '';
            $readEve  = $rec['read2'] !== null ? $rec['read2'] : '';
            $totalDay = $rec['total'] !== null ? $rec['total'] : '';
        }

        $sheet->setCellValue("{$readCol}{$rowD}", $readMorn);
        $sheet->setCellValue("{$readCol}{$rowN}", $readEve);
        $sheet->getStyle("{$readCol}{$rowD}:{$readCol}{$rowN}")->applyFromArray(['alignment' => $centerMid, 'borders' => $allThin['borders']]);

        $sheet->mergeCells("{$totalCol}{$rowD}:{$totalCol}{$rowN}");
        $sheet->setCellValue("{$totalCol}{$rowD}", $totalDay);
        $sheet->getStyle("{$totalCol}{$rowD}:{$totalCol}{$rowN}")->applyFromArray(['alignment' => $centerMid, 'borders' => $allThin['borders']]);
    }
}

$lastDataRow = $baseRow + ($daysInMonth * 2) - 1;

// ============================================================
// 1. ADD SUM ROW (ส่วนที่เพิ่มใหม่)
// ============================================================
$sumRow = $lastDataRow + 1;
$sheet->getRowDimension($sumRow)->setRowHeight(25.0);

$sheet->mergeCells("A{$sumRow}:B{$sumRow}");
$sheet->setCellValue("A{$sumRow}", "TOTAL / SUMMARY");
$sheet->getStyle("A{$sumRow}:B{$sumRow}")->applyFromArray([
    'font'      => ['bold' => true, 'size' => 11],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => $allThin['borders'],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFE699']] // สีเหลืองอ่อน
]);

foreach ($meterCols as $code => [$readCol, $totalCol]) {
    // ปิดกรอบคอลัมน์ Read ให้สวยงาม
    $sheet->getStyle("{$readCol}{$sumRow}")->applyFromArray([
        'borders' => $allThin['borders'],
        'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFE699']]
    ]);

    // ใส่สูตร SUM ในคอลัมน์ TOTAL
    $sheet->setCellValue("{$totalCol}{$sumRow}", "=SUM({$totalCol}5:{$totalCol}{$lastDataRow})");
    $sheet->getStyle("{$totalCol}{$sumRow}")->applyFromArray([
        'font'      => ['bold' => true, 'size' => 11],
        'alignment' => $centerMid,
        'borders'   => $allThin['borders'],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFE699']]
    ]);
    // ฟอร์แมตตัวเลขทศนิยม
    $sheet->getStyle("{$totalCol}{$sumRow}")->getNumberFormat()->setFormatCode('#,##0.00');
}

// ============================================================
// 2. CREATE CHART (ส่วนที่เพิ่มใหม่)
// ============================================================
// ก. สร้างตารางซ่อนเพื่อเก็บข้อมูลให้กราฟดึงไปใช้
$chartDataStartRow = $sumRow + 3;
$sheet->setCellValue("A{$chartDataStartRow}", "Meter Name");
$sheet->setCellValue("B{$chartDataStartRow}", "Total Usage (Sum)");
$sheet->getStyle("A{$chartDataStartRow}:B{$chartDataStartRow}")->applyFromArray(['font' => ['bold' => true], 'borders' => $allThin['borders']]);

$r = $chartDataStartRow + 1;
$catStart = $r;
foreach ($meterCols as $code => [$readCol, $totalCol, $meterName, $meterType]) {
    $sheet->setCellValue("A{$r}", $meterName);
    $sheet->setCellValue("B{$r}", "={$totalCol}{$sumRow}"); // ดึงสูตรผลรวมมาแสดง
    $sheet->getStyle("A{$r}:B{$r}")->applyFromArray(['borders' => $allThin['borders']]);
    $r++;
}
$catEnd = $r - 1;

// ข. วาดกราฟ Bar Chart
$sheetNameForChart = "'" . $sheet->getTitle() . "'";

$dataSeriesLabels = [
    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $sheetNameForChart . '!$B$'.$chartDataStartRow, null, 1),
];
$xAxisTickValues = [
    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $sheetNameForChart . '!$A$'.$catStart.':$A$'.$catEnd, null, count($meterCols)),
];
$dataSeriesValues = [
    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $sheetNameForChart . '!$B$'.$catStart.':$B$'.$catEnd, null, count($meterCols)),
];

$series = new DataSeries(
    DataSeries::TYPE_BARCHART,       // กราฟแท่งแนวนอน
    DataSeries::GROUPING_STANDARD,   
    range(0, count($dataSeriesValues) - 1), 
    $dataSeriesLabels,               
    $xAxisTickValues,                
    $dataSeriesValues                
);
$series->setPlotDirection(DataSeries::DIRECTION_COL); // ตั้งค่าให้แท่งตั้งตรง (Column Chart)

$plotArea = new PlotArea(null, [$series]);
$legend = new Legend(Legend::POSITION_RIGHT, null, false);
$title = new Title('Summary Total Usage (Sum) per Meter');

$chart = new Chart('chart_total_usage', $title, $legend, $plotArea, true, 0, null, null);
$chart->setTopLeftPosition('D' . $chartDataStartRow); // จุดมุมซ้ายบนของกราฟ
$chart->setBottomRightPosition('M' . ($chartDataStartRow + 18)); // จุดมุมขวาล่างของกราฟ

$sheet->addChart($chart);

// ============================================================
// PAGE SETUP
// ============================================================
$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3);
$sheet->getPageSetup()->setFitToPage(true);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);
$sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.4)->setRight(0.4);

// ============================================================
// SEND TO BROWSER
// ============================================================
if (ob_get_length()) {
    ob_end_clean(); // ล้าง Output ป้องกันไฟล์พัง
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1'); 

$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true); // *** สำคัญ: สั่งให้คลาสเปิดการบันทึกกราฟด้วย ***
$writer->save('php://output');

if (function_exists('logActivity')) {
    logActivity($_SESSION['user_id'], 'export_report', "Export Energy & Water Excel ({$filename})");
}
exit();