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
        ORDER BY r.record_date ASC, m.sort_order ASC
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

// Meter code => [READ_col, TOTAL/DAY_col]
$meterCols = [
    'MDB1'    => ['C', 'D'],
    'MDB2'    => ['E', 'F'],
    'MDB3'    => ['G', 'H'],
    'SUBMDB4' => ['I', 'J'],
    'SUBMDB5' => ['K', 'L'],
    'WATER'   => ['M', 'N'],
];

$firstDay    = date('Y-m-01', strtotime($start_date));
$daysInMonth = (int)date('t', strtotime($firstDay));
$monthLabel  = strtoupper(date('F Y', strtotime($firstDay)));

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('MDB DIARY REPORT');
$spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

// ---- COLUMN WIDTHS ----
$sheet->getColumnDimension('A')->setWidth(18.86);
$sheet->getColumnDimension('B')->setWidth(13.0);
$sheet->getColumnDimension('C')->setWidth(31.0);
$sheet->getColumnDimension('D')->setWidth(13.0);
$sheet->getColumnDimension('E')->setWidth(31.0);
$sheet->getColumnDimension('F')->setWidth(13.0);
$sheet->getColumnDimension('G')->setWidth(13.0);
$sheet->getColumnDimension('H')->setWidth(13.0);
$sheet->getColumnDimension('I')->setWidth(13.0);
$sheet->getColumnDimension('J')->setWidth(13.0);
$sheet->getColumnDimension('K')->setWidth(13.0);
$sheet->getColumnDimension('L')->setWidth(13.0);
$sheet->getColumnDimension('M')->setWidth(13.0);
$sheet->getColumnDimension('N')->setWidth(13.0);

$thin   = Border::BORDER_THIN;
$none   = Border::BORDER_NONE;
$allThin = [
    'borders' => ['allBorders' => ['borderStyle' => $thin]]
];

// ============================================================
// ROW 1 — Title bar
// ============================================================
$sheet->getRowDimension(1)->setRowHeight(27.0);

$sheet->setCellValue('A1', 'ELECTRIC POWER MDB DIARY REPORT 1');
$sheet->getStyle('A1')->applyFromArray([
    'font'    => ['bold' => true, 'size' => 24, 'name' => 'Arial'],
    'borders' => ['bottom' => ['borderStyle' => $thin]],
]);

$sheet->mergeCells('G1:H1');
$sheet->setCellValue('G1', 'MONTH   ' . $monthLabel);
$sheet->getStyle('G1:H1')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 24, 'name' => 'Arial'],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders'   => ['bottom' => ['borderStyle' => $thin]],
]);

$sheet->setCellValue('J1', 'CHECK BY……………..APPROVE…………………..'); 
$sheet->getStyle('J1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 24, 'name' => 'Arial'],
]);

// ============================================================
// ROW 2 — Group headers
// ============================================================
$sheet->getRowDimension(2)->setRowHeight(37.5);

// DATE spans A2:A4
$sheet->mergeCells('A2:A4');
$sheet->setCellValue('A2', 'DATE');
$sheet->getStyle('A2:A4')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 26, 'name' => 'Arial'],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER],
    'borders'   => $allThin['borders'],
]);

// ITEM spans B2:B3
$sheet->mergeCells('B2:B3');
$sheet->setCellValue('B2', 'ITEM');
$sheet->getStyle('B2:B3')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 26, 'name' => 'Arial'],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT,
                    'vertical'   => Alignment::VERTICAL_BOTTOM],
    'borders'   => ['top'   => ['borderStyle' => $thin],
                    'left'  => ['borderStyle' => $thin],
                    'right' => ['borderStyle' => $thin]],
]);

// Group header cells
$groups = [
    'C2:D2' => 'MDB1 3P 4W 400/230V',
    'E2:F2' => 'MDB2 3P 3W 210V',
    'G2:H2' => 'MDB3 3P 4W 400/230V',
    'I2:J2' => 'Sub MDB4 (Water plant1)',
    'K2:L2' => 'Sub MDB5 (Water plant2)',
    'M2:N2' => ' Input Water (m3)',
];
foreach ($groups as $range => $label) {
    $sheet->mergeCells($range);
    $sheet->setCellValue(explode(':', $range)[0], $label);
    $sheet->getStyle($range)->applyFromArray([
        'font'      => ['bold' => true, 'size' => 26, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER],
        'borders'   => $allThin['borders'],
    ]);
}

// ============================================================
// ROW 3 — Sub-group headers Energy / Water
// ============================================================
$sheet->getRowDimension(3)->setRowHeight(47.25);

$subGroups = [
    'C3:D3' => 'Energy (kWh)',
    'E3:F3' => 'Energy (kWh)',
    'G3:H3' => 'Energy (kWh)',
    'I3:J3' => 'Energy (kWh)',
    'K3:L3' => 'Energy (kWh)',
    'M3:N3' => 'Water (m3)',
];
foreach ($subGroups as $range => $label) {
    $sheet->mergeCells($range);
    $sheet->setCellValue(explode(':', $range)[0], $label);
    $sheet->getStyle($range)->applyFromArray([
        'font'      => ['bold' => true, 'size' => 26, 'name' => 'Arial'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER],
        'borders'   => ['left'   => ['borderStyle' => $thin],
                        'top'    => ['borderStyle' => $thin],
                        'bottom' => ['borderStyle' => $thin]],
    ]);
    // right border on second col of each pair
    $endCell = explode(':', $range)[1];
    $sheet->getStyle("{$endCell}3")->applyFromArray([
        'borders' => ['right' => ['borderStyle' => $thin]],
    ]);
}
// B3 side borders (continuation of ITEM merge)
$sheet->getStyle('B3')->applyFromArray([
    'borders' => ['left'  => ['borderStyle' => $thin],
                  'right' => ['borderStyle' => $thin]],
]);

// ============================================================
// ROW 4 — Column detail headers: TIME, READ, TOTAL/DAY
// ============================================================
$sheet->getRowDimension(4)->setRowHeight(33.75);

$sheet->setCellValue('B4', 'TIME');
$sheet->getStyle('B4')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 26, 'name' => 'Arial'],
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

$detailCols = ['C4'=>'READ','D4'=>'TOTAL/DAY',
               'E4'=>'READ','F4'=>'TOTAL/DAY',
               'G4'=>'READ','H4'=>'TOTAL/DAY',
               'I4'=>'READ','J4'=>'TOTAL/DAY',
               'K4'=>'READ','L4'=>'TOTAL/DAY',
               'M4'=>'READ','N4'=>'TOTAL/DAY'];
foreach ($detailCols as $cell => $label) {
    $sheet->setCellValue($cell, $label);
    $sheet->getStyle($cell)->applyFromArray([
        'font'      => ['bold' => true, 'size' => 26, 'name' => 'Arial'],
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

    $sheet->getRowDimension($rowD)->setRowHeight(51.0);
    $sheet->getRowDimension($rowN)->setRowHeight(51.0);

    // -- A: DATE merged over 2 rows
    $sheet->mergeCells("A{$rowD}:A{$rowN}");
    $sheet->setCellValue("A{$rowD}", $dispDate);
    $sheet->getStyle("A{$rowD}:A{$rowN}")->applyFromArray([
        'font'      => ['bold' => true, 'size' => 28, 'name' => 'Arial'],
        'alignment' => $centerMid,
        'borders'   => $allThin['borders'],
    ]);

    // -- B: TIME
    foreach ([[$rowD, '09:00'], [$rowN, '24:00']] as [$row, $time]) {
        $sheet->setCellValue("B{$row}", $time);
        $sheet->getStyle("B{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 28, 'name' => 'Arial'],
            'alignment' => $centerMid,
            'borders'   => $allThin['borders'],
        ]);
    }

    // -- Meter columns C-N
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
            'font'      => ['size' => 14, 'name' => 'Arial'],
            'alignment' => $centerMid,
            'borders'   => $allThin['borders'],
        ]);

        // READ — evening (24:00 row)
        $sheet->setCellValue("{$readCol}{$rowN}", $readEve);
        $sheet->getStyle("{$readCol}{$rowN}")->applyFromArray([
            'font'      => ['size' => 14, 'name' => 'Arial'],
            'alignment' => $centerMid,
            'borders'   => $allThin['borders'],
        ]);

        // TOTAL/DAY — 09:00 row (value), 24:00 row (blank with border)
        $sheet->setCellValue("{$totalCol}{$rowD}", $totalDay);
        $sheet->getStyle("{$totalCol}{$rowD}")->applyFromArray([
            'font'      => ['size' => 14, 'name' => 'Arial'],
            'alignment' => $centerMid,
            'borders'   => $allThin['borders'],
        ]);
        $sheet->getStyle("{$totalCol}{$rowN}")->applyFromArray([
            'borders' => $allThin['borders'],
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