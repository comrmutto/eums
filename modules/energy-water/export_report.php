<?php
/**
 * Export Report - Energy & Water Module
 * Engineering Utility Monitoring System (EUMS)
 * Form Format: ELECTRIC POWER MDB DIARY REPORT
 * Replicates energy_water-record.xlsx layout exactly
 */

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

$month    = isset($_GET['month'])    ? (int)$_GET['month']    : (int)date('m');
$year     = isset($_GET['year'])     ? (int)$_GET['year']     : (int)date('Y');
$meter_id = isset($_GET['meter_id']) ? (int)$_GET['meter_id'] : 0;

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

// ดึง meter_code จริงจาก DB เพื่อสร้าง column mapping
// เรียงลำดับเหมือนกับที่ query ดึงข้อมูล (meter_type DESC, meter_code ASC)
$meterStmt = $db->query("
    SELECT meter_code, meter_name, meter_type
    FROM mc_mdb_water
    WHERE status = 1
    ORDER BY meter_type DESC, meter_code ASC
");
$meterList = $meterStmt->fetchAll(PDO::FETCH_ASSOC);

// สร้าง column letter list
$colLetters = [];
for ($i = 1; $i <= 26; $i++) $colLetters[] = chr(64 + $i);
for ($i = 1; $i <= 26; $i++) for ($j = 1; $j <= 26; $j++) $colLetters[] = chr(64+$i).chr(64+$j);

// map: meter_code => [readCol, totalCol, meter_name, meter_type]
// เริ่มที่ C (index 2 ใน 0-based)
$meterCols = [];
$colIdx = 2;
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
$sheet->getColumnDimension('A')->setWidth(13.0);  // DATE
$sheet->getColumnDimension('B')->setWidth(9.0);   // TIME
foreach ($meterCols as $code => [$readCol, $totalCol, $meterName, $meterType]) {
    $sheet->getColumnDimension($readCol)->setWidth(14.0);
    $sheet->getColumnDimension($totalCol)->setWidth(12.0);
}

$thin   = Border::BORDER_THIN;
$none   = Border::BORDER_NONE;
$allThin = [
    'borders' => ['allBorders' => ['borderStyle' => $thin]]
];

// ============================================================
// ROW 1 — Title bar
// ============================================================
$sheet->getRowDimension(1)->setRowHeight(20.0);

$sheet->setCellValue('A1', 'ELECTRIC POWER MDB DIARY REPORT 1');
$sheet->getStyle('A1')->applyFromArray([
    'font'    => ['bold' => true, 'size' => 14, 'name' => 'Arial'],
    'borders' => ['bottom' => ['borderStyle' => $thin]],
]);

// MONTH — กึ่งกลาง sheet (ใช้คอลัมน์ที่ 4 จากซ้าย)
$midCode    = array_keys($meterCols)[intdiv(count($meterCols), 2)] ?? array_key_first($meterCols);
$midReadCol = $meterCols[$midCode][0];
$midTotCol  = $meterCols[$midCode][1];
$sheet->mergeCells("{$midReadCol}1:{$midTotCol}1");
$sheet->setCellValue("{$midReadCol}1", 'MONTH   ' . $monthLabel);
$sheet->getStyle("{$midReadCol}1:{$midTotCol}1")->applyFromArray([
    'font'      => ['bold' => true, 'size' => 11, 'name' => 'Arial'],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders'   => ['bottom' => ['borderStyle' => $thin]],
]);

// CHECK BY / APPROVE — คอลัมน์สุดท้าย
$lastCodes   = array_keys($meterCols);
$lastCode    = end($lastCodes);
$checkCol    = $meterCols[$lastCode][0];
$sheet->setCellValue("{$checkCol}1", 'CHECK BY……………..APPROVE…………………..'); 
$sheet->getStyle("{$checkCol}1")->applyFromArray([
    'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
]);

// ============================================================
// ROW 2 — Group headers (meter name)
// ============================================================
$sheet->getRowDimension(2)->setRowHeight(25.0);

// DATE spans A2:A4
$sheet->mergeCells('A2:A4');
$sheet->setCellValue('A2', 'DATE');
$sheet->getStyle('A2:A4')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 11, 'name' => 'Arial'],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER],
    'borders'   => $allThin['borders'],
]);

// ITEM spans B2:B3
$sheet->mergeCells('B2:B3');
$sheet->setCellValue('B2', 'ITEM');
$sheet->getStyle('B2:B3')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 11, 'name' => 'Arial'],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT,
                    'vertical'   => Alignment::VERTICAL_BOTTOM],
    'borders'   => ['top'   => ['borderStyle' => $thin],
                    'left'  => ['borderStyle' => $thin],
                    'right' => ['borderStyle' => $thin]],
]);

// Group header: meter_name (row 2)
foreach ($meterCols as $code => [$readCol, $totalCol, $meterName, $meterType]) {
    $sheet->mergeCells("{$readCol}2:{$totalCol}2");
    $sheet->setCellValue("{$readCol}2", $meterName);
    $sheet->getStyle("{$readCol}2:{$totalCol}2")->applyFromArray([
        'font'      => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                        'wrapText'   => true],
        'borders'   => $allThin['borders'],
    ]);
}

// ============================================================
// ROW 3 — Sub-group: Energy (kWh) / Water (m3)
// ============================================================
$sheet->getRowDimension(3)->setRowHeight(20.0);

foreach ($meterCols as $code => [$readCol, $totalCol, $meterName, $meterType]) {
    $unitLabel = ($meterType === 'electricity') ? 'Energy (kWh)' : 'Water (m3)';
    $sheet->mergeCells("{$readCol}3:{$totalCol}3");
    $sheet->setCellValue("{$readCol}3", $unitLabel);
    $sheet->getStyle("{$readCol}3:{$totalCol}3")->applyFromArray([
        'font'      => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER],
        'borders'   => ['left'   => ['borderStyle' => $thin],
                        'top'    => ['borderStyle' => $thin],
                        'bottom' => ['borderStyle' => $thin]],
    ]);
    $sheet->getStyle("{$totalCol}3")->applyFromArray([
        'borders' => ['right' => ['borderStyle' => $thin]],
    ]);
}
// B3 side borders
$sheet->getStyle('B3')->applyFromArray([
    'borders' => ['left'  => ['borderStyle' => $thin],
                  'right' => ['borderStyle' => $thin]],
]);

// ============================================================
// ROW 4 — TIME, READ, TOTAL/DAY
// ============================================================
$sheet->getRowDimension(4)->setRowHeight(18.0);

$sheet->setCellValue('B4', 'TIME');
$sheet->getStyle('B4')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical'   => Alignment::VERTICAL_CENTER],
    'borders'   => $allThin['borders'],
]);
// A4 — bottom border of DATE merge
$sheet->getStyle('A4')->applyFromArray([
    'borders' => ['left'   => ['borderStyle' => $thin],
                  'right'  => ['borderStyle' => $thin],
                  'bottom' => ['borderStyle' => $thin]],
]);

foreach ($meterCols as $code => [$readCol, $totalCol]) {
    $sheet->setCellValue("{$readCol}4", 'READ');
    $sheet->getStyle("{$readCol}4")->applyFromArray([
        'font'      => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER],
        'borders'   => $allThin['borders'],
    ]);
    $sheet->setCellValue("{$totalCol}4", 'TOTAL/DAY');
    $sheet->getStyle("{$totalCol}4")->applyFromArray([
        'font'      => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER],
        'borders'   => $allThin['borders'],
    ]);
}

// ============================================================
// DATA ROWS — 2 per day (09:00 & 24:00)
// ============================================================
$baseRow   = 5;
$centerMid = ['horizontal' => Alignment::HORIZONTAL_CENTER,
              'vertical'   => Alignment::VERTICAL_CENTER];

for ($d = 1; $d <= $daysInMonth; $d++) {
    $dateStr  = date('Y-m', strtotime($firstDay)) . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
    $dispDate = date('d/m/Y', strtotime($dateStr));

    $rowD = $baseRow + ($d - 1) * 2;   // 09:00
    $rowN = $rowD + 1;                  // 24:00

    $sheet->getRowDimension($rowD)->setRowHeight(20.0);
    $sheet->getRowDimension($rowN)->setRowHeight(20.0);

    // -- A: DATE merged over 2 rows
    $sheet->mergeCells("A{$rowD}:A{$rowN}");
    $sheet->setCellValue("A{$rowD}", $dispDate);
    $sheet->getStyle("A{$rowD}:A{$rowN}")->applyFromArray([
        'font'      => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
        'alignment' => $centerMid,
        'borders'   => $allThin['borders'],
    ]);

    // -- B: TIME
    foreach ([[$rowD, '09:00'], [$rowN, '24:00']] as [$row, $time]) {
        $sheet->setCellValue("B{$row}", $time);
        $sheet->getStyle("B{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'name' => 'Arial'],
            'alignment' => $centerMid,
            'borders'   => $allThin['borders'],
        ]);
    }

    // -- Meter columns
    foreach ($meterCols as $code => [$readCol, $totalCol]) {
        $readMorn  = '';
        $readEve   = '';
        $totalDay  = '';

        if (isset($byDate[$dateStr][$code])) {
            $rec      = $byDate[$dateStr][$code];
            $readMorn = $rec['read']  !== null ? $rec['read']  : '';
            $readEve  = $rec['read2'] !== null ? $rec['read2'] : '';
            $totalDay = $rec['total'] !== null ? $rec['total'] : '';
        }

        // READ — morning (09:00 row)
        $sheet->setCellValue("{$readCol}{$rowD}", $readMorn);
        $sheet->getStyle("{$readCol}{$rowD}")->applyFromArray([
            'font'      => ['size' => 10, 'name' => 'Arial'],
            'alignment' => $centerMid,
            'borders'   => $allThin['borders'],
        ]);

        // READ — evening (24:00 row)
        $sheet->setCellValue("{$readCol}{$rowN}", $readEve);
        $sheet->getStyle("{$readCol}{$rowN}")->applyFromArray([
            'font'      => ['size' => 10, 'name' => 'Arial'],
            'alignment' => $centerMid,
            'borders'   => $allThin['borders'],
        ]);

        // TOTAL/DAY — merge rowD:rowN (แสดงค่าเดียวครอบ 2 แถว)
        $sheet->mergeCells("{$totalCol}{$rowD}:{$totalCol}{$rowN}");
        $sheet->setCellValue("{$totalCol}{$rowD}", $totalDay);
        $sheet->getStyle("{$totalCol}{$rowD}:{$totalCol}{$rowN}")->applyFromArray([
            'font'      => ['size' => 10, 'name' => 'Arial'],
            'alignment' => $centerMid,
            'borders'   => $allThin['borders'],
        ]);
    }
}

// ============================================================
// FOOTER — 3 blank rows (rows 67-69 equivalent)
// ============================================================
$lastRow = $baseRow + $daysInMonth * 2;
for ($r = $lastRow; $r <= $lastRow + 2; $r++) {
    $sheet->getRowDimension($r)->setRowHeight(18.75);
}

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
if (ob_get_contents()) ob_end_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

if (function_exists('logActivity')) {
    logActivity($_SESSION['user_id'], 'export_report', "Export Energy & Water Excel ({$filename})");
}
exit();