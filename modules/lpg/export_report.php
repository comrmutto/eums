<?php
/**
 * LPG Module - Export Report to Excel (PhpSpreadsheet)
 * Form Format: QP-ED-001 (FM-ED-003) Rev.01
 * Replicates exact form layout from 3_LPG-record.xlsx
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized access');
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$db = getDB();

// --- 1. Parse dates ---
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

// --- 2. Fetch inspection items ---
$stmt = $db->query("SELECT * FROM lpg_inspection_items ORDER BY item_no");
$items = $stmt->fetchAll();

// --- 3. Fetch records in date range ---
$sql = "
    SELECT r.*, i.item_type 
    FROM lpg_daily_records r
    JOIN lpg_inspection_items i ON r.item_id = i.id
    WHERE r.record_date BETWEEN ? AND ?
    ORDER BY r.record_date ASC
";
$stmt = $db->prepare($sql);
$stmt->execute([$startDate, $endDate]);
$records = $stmt->fetchAll();

// Organize data by date
$dataByDate = [];
foreach ($records as $row) {
    $date = $row['record_date'];
    if (!isset($dataByDate[$date])) {
        $dataByDate[$date] = [
            'records_D'  => [], // Day shift
            'records_N'  => [], // Night shift
            'recorder_D' => '',
            'recorder_N' => '',
            'checker_D'  => '',
            'checker_N'  => '',
            'remarks'    => $row['remarks']
        ];
    }
    $val   = $row['item_type'] == 'number' ? $row['number_value'] : $row['enum_value'];
    $shift = isset($row['shift']) ? strtoupper($row['shift']) : 'D';
    if ($shift === 'N') {
        $dataByDate[$date]['records_N'][$row['item_id']] = $val;
        if (!empty($row['recorded_by']))  $dataByDate[$date]['recorder_N'] = $row['recorded_by'];
        if (!empty($row['checked_by']))   $dataByDate[$date]['checker_N']  = $row['checked_by'];
    } else {
        $dataByDate[$date]['records_D'][$row['item_id']] = $val;
        if (!empty($row['recorded_by']))  $dataByDate[$date]['recorder_D'] = $row['recorded_by'];
        if (!empty($row['checked_by']))   $dataByDate[$date]['checker_D']  = $row['checked_by'];
    }
}

// --- 4. Fetch document info ---
$docStmt = $db->prepare("SELECT * FROM documents WHERE module_type = 'lpg' AND start_date <= ? ORDER BY start_date DESC LIMIT 1");
$docStmt->execute([$startDate]);
$docInfo = $docStmt->fetch();
$docNo   = $docInfo ? $docInfo['doc_no']  : 'QP-ED-001(FM-ED-003)Rev.01';

// Month/Year from startDate
$monthYear = date('F Y', strtotime($startDate));
$monthTH   = date('m/Y', strtotime($startDate));

// --- 5. Build Spreadsheet ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('QP-ED-001(FM-ED-003)Rev.01');

// Default font: Tahoma 10
$spreadsheet->getDefaultStyle()->getFont()->setName('Tahoma')->setSize(10);

// Helper functions
function applyBorder($sheet, $range, $top='thin', $bottom='thin', $left='thin', $right='thin') {
    $borderStyles = [
        'borders' => [
            'top'    => ['borderStyle' => $top    === 'none' ? Border::BORDER_NONE : ($top    === 'medium' ? Border::BORDER_MEDIUM : Border::BORDER_THIN)],
            'bottom' => ['borderStyle' => $bottom === 'none' ? Border::BORDER_NONE : ($bottom === 'medium' ? Border::BORDER_MEDIUM : Border::BORDER_THIN)],
            'left'   => ['borderStyle' => $left   === 'none' ? Border::BORDER_NONE : ($left   === 'medium' ? Border::BORDER_MEDIUM : Border::BORDER_THIN)],
            'right'  => ['borderStyle' => $right  === 'none' ? Border::BORDER_NONE : ($right  === 'medium' ? Border::BORDER_MEDIUM : Border::BORDER_THIN)],
        ]
    ];
    $sheet->getStyle($range)->applyFromArray($borderStyles);
}

function applyOuterBorder($sheet, $range) {
    $sheet->getStyle($range)->applyFromArray([
        'borders' => [
            'outline' => ['borderStyle' => Border::BORDER_MEDIUM],
            'allBorders' => ['borderStyle' => Border::BORDER_THIN],
        ]
    ]);
}

function setCell($sheet, $coord, $value, $bold=false, $fontSize=null, $hAlign=null, $vAlign=null, $wrap=false) {
    $sheet->setCellValue($coord, $value);
    $style = $sheet->getStyle($coord);
    if ($bold) $style->getFont()->setBold(true);
    if ($fontSize) $style->getFont()->setSize($fontSize);
    if ($hAlign) $style->getAlignment()->setHorizontal($hAlign);
    if ($vAlign) $style->getAlignment()->setVertical($vAlign);
    if ($wrap)  $style->getAlignment()->setWrapText(true);
}

// =========================================================
// ROW HEIGHTS (matching original)
// =========================================================
$rowHeights = [
    1=>20.15, 2=>5.15, 3=>20.15, 4=>20.15, 5=>20.15, 6=>9.9,
    7=>20.15, 8=>20.15, 9=>20.15, 10=>20.15, 11=>9.9,
    12=>39.9, 13=>27.0, 14=>28.5, 15=>23.0, 16=>26.0, 17=>24.5, 18=>27.0,
    19=>23.5, 20=>26.0, 21=>26.0, 22=>29.0, 23=>28.5, 24=>29.0,
    25=>26.5, 26=>26.0, 27=>27.5, 28=>27.0, 29=>29.5, 30=>31.0,
    31=>40.5, 32=>43.5, 33=>28.5, 34=>25.5, 35=>27.5, 36=>25.5,
    37=>24.5, 38=>27.5,
    39=>20.15, 40=>20.15, 41=>20.15, 42=>20.15, 43=>20.15, 44=>20.15,
    45=>20.15, 46=>20.15, 47=>20.15, 48=>20.15, 49=>20.15, 50=>20.15,
    51=>20.15, 52=>20.15, 53=>20.15, 54=>20.15, 55=>20.15, 56=>20.15,
    57=>20.15, 58=>20.15,
];
foreach ($rowHeights as $row => $height) {
    $sheet->getRowDimension($row)->setRowHeight($height);
}

// =========================================================
// COLUMN WIDTHS (matching original)
// =========================================================
$colWidths = [
    'B'=>4.63, 'C'=>13.0, 'D'=>13.0, 'E'=>5.09, 'F'=>4.63,
    'G'=>13.0, 'H'=>13.0, 'I'=>13.0, 'J'=>13.0, 'K'=>13.0, 'L'=>13.0,
    'M'=>13.0, 'N'=>13.0, 'O'=>13.0, 'P'=>13.0, 'Q'=>13.0, 'R'=>13.0,
    'S'=>13.0, 'T'=>13.0, 'U'=>13.0, 'V'=>13.0, 'W'=>13.0, 'X'=>13.0,
    'Y'=>13.0, 'Z'=>13.0, 'AA'=>13.0, 'AB'=>13.0, 'AC'=>13.0,
    'AD'=>5.82, 'AE'=>6.54, 'AF'=>4.63, 'AG'=>13.0, 'AH'=>13.0,
    'AI'=>13.0, 'AJ'=>13.0, 'AK'=>7.54, 'AL'=>4.63,
    'AM'=>6.09, 'AN'=>5.63, 'AO'=>5.82, 'AP'=>6.09, 'AQ'=>5.82,
    'AR'=>6.09, 'AS'=>5.63, 'AT'=>5.82, 'AU'=>13.0, 'AV'=>5.91,
    'AW'=>6.18, 'AX'=>6.09, 'AY'=>13.0, 'AZ'=>6.18, 'BA'=>6.45,
    'BB'=>6.36, 'BC'=>6.63, 'BD'=>6.91, 'BE'=>7.54, 'BF'=>6.36,
    'BG'=>5.91, 'BH'=>6.36, 'BI'=>6.45, 'BJ'=>6.09, 'BK'=>6.18,
    'BL'=>6.45, 'BM'=>13.0, 'BN'=>6.36, 'BO'=>5.91, 'BP'=>6.18, 'BQ'=>6.63,
];
foreach ($colWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// =========================================================
// ROW 1-5: Document Title Header
// =========================================================
$sheet->mergeCells('B1:BE5');
$sheet->setCellValue('B1', "เอกสารตรวจสอบเครื่องจักรประจำวัน\n( Machine Daily Check Sheet)");
$sheet->getStyle('B1')->applyFromArray([
    'font'      => ['bold'=>true, 'size'=>18, 'name'=>'Tahoma'],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER, 'vertical'=>Alignment::VERTICAL_CENTER, 'wrapText'=>true],
    'borders'   => ['outline' => ['borderStyle'=>Border::BORDER_MEDIUM]],
]);

// Page label area top-right
$sheet->mergeCells('BM1:BN1');
$sheet->setCellValue('BM1', 'Page:');
$sheet->mergeCells('BO1:BP1');
$sheet->getStyle('BM1:BP1')->applyFromArray([
    'borders'   => ['outline' => ['borderStyle'=>Border::BORDER_THIN]],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER, 'vertical'=>Alignment::VERTICAL_CENTER],
]);

// Approve / Checking / Prepare boxes (rows 3-5, right side)
foreach (['BF3:BI3'=>'Approve', 'BJ3:BM3'=>'Checking', 'BN3:BQ3'=>'Prepare'] as $range => $label) {
    $sheet->mergeCells($range);
    $cell = explode(':', $range)[0];
    $sheet->setCellValue($cell, $label);
    $sheet->getStyle($range)->applyFromArray([
        'font'      => ['bold'=>true],
        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER, 'vertical'=>Alignment::VERTICAL_CENTER],
        'borders'   => ['outline'=>['borderStyle'=>Border::BORDER_THIN], 'allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
    ]);
}
// Signature boxes rows 4-5
foreach (['BF4:BI5', 'BJ4:BM5', 'BN4:BQ5'] as $range) {
    $sheet->mergeCells($range);
    $sheet->getStyle($range)->applyFromArray([
        'borders' => ['outline'=>['borderStyle'=>Border::BORDER_THIN]],
    ]);
}

// =========================================================
// ROW 7-10: Info fields (Dept, MC name, Month, Doc No, MC No, Year)
// =========================================================
$infoFields = [
    // [merge, label_merge, label, value_merge, value]
    ['B7:E8',  null,       'แผนก\n(Dept.)',             'F7:W8',  'Engineering / Maintenance'],
    ['X7:AA8', null,       'ชื่อเครื่องจักร\n(M/C name)', 'AB7:AJ8','LPG Station'],
    ['AQ7:AV8',null,       'เดือน\n(Month)',             null,     null],
    ['B9:E10', null,       'เลขที่เอกสาร\n(Doc. No.)',   'F9:W10', $docNo],
    ['X9:AA10',null,       'เลขเครื่องจักร\n(M/C No.)',  'AB9:AJ10',''],
    ['AQ9:AV10',null,      'ปี\n(Year)',                 null,     null],
];

// Dept row
$sheet->mergeCells('B7:E8');
$sheet->setCellValue('B7', "แผนก\n(Dept.)");
$sheet->getStyle('B7')->applyFromArray([
    'font'=>['bold'=>true], 'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM]],
]);
$sheet->mergeCells('F7:W8');
$sheet->setCellValue('F7', 'Engineering / Maintenance');
$sheet->getStyle('F7')->applyFromArray([
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM]],
]);

$sheet->mergeCells('X7:AA8');
$sheet->setCellValue('X7', "ชื่อเครื่องจักร\n(M/C name)");
$sheet->getStyle('X7')->applyFromArray([
    'font'=>['bold'=>true],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM]],
]);
$sheet->mergeCells('AB7:AJ8');
$sheet->setCellValue('AB7', 'LPG Station');
$sheet->getStyle('AB7')->applyFromArray([
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM]],
]);

$sheet->mergeCells('AQ7:AV8');
$sheet->setCellValue('AQ7', "เดือน\n(Month)");
$sheet->getStyle('AQ7')->applyFromArray([
    'font'=>['bold'=>true],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM]],
]);
// Month value box
$sheet->mergeCells('AW7:BQ8');
$sheet->setCellValue('AW7', date('m/Y', strtotime($startDate)));
$sheet->getStyle('AW7')->applyFromArray([
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM]],
]);

// Doc No row
$sheet->mergeCells('B9:E10');
$sheet->setCellValue('B9', "เลขที่เอกสาร\n(Doc. No.)");
$sheet->getStyle('B9')->applyFromArray([
    'font'=>['bold'=>true],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM]],
]);
$sheet->mergeCells('F9:W10');
$sheet->setCellValue('F9', $docNo);
$sheet->getStyle('F9')->applyFromArray([
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM]],
]);

$sheet->mergeCells('X9:AA10');
$sheet->setCellValue('X9', "เลขเครื่องจักร\n(M/C No.)");
$sheet->getStyle('X9')->applyFromArray([
    'font'=>['bold'=>true],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM]],
]);
$sheet->mergeCells('AB9:AJ10');
$sheet->getStyle('AB9')->applyFromArray([
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM]],
]);

$sheet->mergeCells('AQ9:AV10');
$sheet->setCellValue('AQ9', "ปี\n(Year)");
$sheet->getStyle('AQ9')->applyFromArray([
    'font'=>['bold'=>true],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM]],
]);
$sheet->mergeCells('AW9:BQ10');
$sheet->setCellValue('AW9', date('Y', strtotime($startDate)));
$sheet->getStyle('AW9')->applyFromArray([
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM]],
]);

// =========================================================
// ROW 12: Table column headers
// =========================================================
// No., Detail, Standard, Freq, Day numbers 1-31
$headerStyle = [
    'font'      => ['bold'=>true, 'name'=>'Tahoma', 'size'=>10],
    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
    'fill'      => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['argb'=>'FFD9EAD3']],
    'borders'   => ['outline'=>['borderStyle'=>Border::BORDER_MEDIUM], 'allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
];

$sheet->mergeCells('V12:W12');
$sheet->setCellValue('V12', "ลำดับ\nNo.");
$sheet->getStyle('V12:W12')->applyFromArray($headerStyle);

$sheet->mergeCells('X12:AE12');
$sheet->setCellValue('X12', "รายละเอียด\n(Detail)");
$sheet->getStyle('X12:AE12')->applyFromArray($headerStyle);

$sheet->mergeCells('AF12:AJ12');
$sheet->setCellValue('AF12', "มาตรฐาน\n(Standard)");
$sheet->getStyle('AF12:AJ12')->applyFromArray($headerStyle);

$sheet->mergeCells('AK12:AL12');
$sheet->setCellValue('AK12', "ความถี่\n(Freq.)");
$sheet->getStyle('AK12:AL12')->applyFromArray($headerStyle);

// Day columns 1-31 (AM=1, AN=2, ... BQ=31)
$dayCols = ['AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ',
            'BA','BB','BC','BD','BE','BF','BG','BH','BI','BJ','BK','BL','BM','BN','BO','BP','BQ'];
for ($d = 1; $d <= 31; $d++) {
    $col = $dayCols[$d-1];
    $sheet->setCellValue($col . '12', $d);
    $sheet->getStyle($col . '12')->applyFromArray($headerStyle);
}

// =========================================================
// INSPECTION ITEMS (rows 13-38, each item takes 2 rows: D and N shift)
// =========================================================
$itemDefinitions = [
    // [no, detail_th, detail_en, standard_th, standard_en, freq]
    [1,  "ปริมาณ LPG\nLPG quantity",
         "อยู่ใน Green Zone\nIn the Green Zone", '1 time start shift'],
    [2,  "อุณหภูมิในถัง\ntemperature in tank",
         "อยู่ใน Green Zone\nIn the Green Zone", '1 time start shift'],
    [3,  "แรงดันในถัง\nPressure in the tank",
         "อยู่ใน Green Zone\nIn the Green Zone", '1 time start shift'],
    [4,  "ระดับน้ำระบายความร้อนใน Vaporizer\nCooling water level in the vaporizer",
         "อยู่ใน Green Zone\nIn the Green Zone", '1 time start shift'],
    [5,  "อุณหภูมิในถัง Vaporizer\nTemperature in the Vaporizer tank",
         "อยู่ใน Green Zone\nIn the Green Zone", '1 time start shift'],
    [6,  "วาล์วระบายแรงดัน (Pressure Relief Valve)\nPressure Relief Valve",
         "ไม่มีฟองอากาศ\nno bubbles", '1 time start shift'],
    [7,  "บอลวาล์ว(Ball Valve)\nBall Valve",
         "ไม่มีฟองอากาศ\nno bubbles", '1 time start shift'],
    [8,  "อุปกรณ์ปรับแรงดัน (Pressure Regulator)\nPressure Regulator",
         "ไม่มีฟองอากาศ\nno bubbles", '1 time start shift'],
    [9,  "ชุดดักกากก๊าซ (Oil Trap)\nOil Trap",
         "ไม่มีสีสิ่งปลอมปน\nNo artificial colors or impurities", '1 time start shift'],
    [10, "วาล์วปิดฉุกเฉิน (Emergency Shut Off Vale)\nEmergency Shut Off Vale",
         "ไม่มีฟองอากาศ วาล์วต้อง ปิดทันที\nno bubbles , The valve must be closed immediately.", '1 time start shift'],
    [11, "เครื่องช่วยระเหย gas(Vaporizer)\nVaporizer",
         "ไม่มีฟองอากาศ\nno bubbles", '1 time start shift'],
    [12, "แรงดันไอGAS ในVaporizer\nGas pressure in vaporizer",
         "อยู่ใน Green Zone\nIn the Green Zone", '1 time start shift'],
    [13, "สภาพน้ำใน Vaporizer\nWater condition in the vaporizer",
         "ไม่มีสีสิ่งปลอมปน\nNo artificial colors or impurities", '1 time start shift'],
];

// Map item_no to item id from DB
$itemMap = [];
foreach ($items as $item) {
    $itemMap[$item['item_no']] = $item['id'];
}

$itemRowBgLight = 'FFFFFFFF';
$itemRowBgAlt   = 'FFF2F2F2';

$baseRow = 13;
foreach ($itemDefinitions as $idx => $itemDef) {
    $no     = $itemDef[0];
    $detail = $itemDef[1];
    $std    = $itemDef[2];
    $freq   = $itemDef[3];

    $rowD = $baseRow + ($no - 1) * 2;      // Day shift row
    $rowN = $rowD + 1;                      // Night shift row

    // No. column (merged D+N rows)
    $sheet->mergeCells("V{$rowD}:W{$rowN}");
    $sheet->setCellValue("V{$rowD}", $no);
    $sheet->getStyle("V{$rowD}:W{$rowN}")->applyFromArray([
        'font'=>['bold'=>true],
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
        'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM],'allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
    ]);

    // Detail column (merged D+N rows)
    $sheet->mergeCells("X{$rowD}:AE{$rowN}");
    $sheet->setCellValue("X{$rowD}", $detail);
    $sheet->getStyle("X{$rowD}:AE{$rowN}")->applyFromArray([
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
        'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM],'allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
    ]);

    // Standard column (merged D+N rows)
    $sheet->mergeCells("AF{$rowD}:AJ{$rowN}");
    $sheet->setCellValue("AF{$rowD}", $std);
    $sheet->getStyle("AF{$rowD}:AJ{$rowN}")->applyFromArray([
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
        'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM],'allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
    ]);

    // Freq column (merged D+N rows)
    $sheet->mergeCells("AK{$rowD}:AK{$rowN}");
    $sheet->setCellValue("AK{$rowD}", $freq);
    $sheet->getStyle("AK{$rowD}:AK{$rowN}")->applyFromArray([
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
        'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM],'allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
    ]);

    // Shift label (AL column)
    $sheet->setCellValue("AL{$rowD}", 'D');
    $sheet->setCellValue("AL{$rowN}", 'N');
    $sheet->getStyle("AL{$rowD}")->applyFromArray([
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
        'borders'=>['outline'=>['borderStyle'=>Border::BORDER_THIN],'allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
    ]);
    $sheet->getStyle("AL{$rowN}")->applyFromArray([
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
        'borders'=>['outline'=>['borderStyle'=>Border::BORDER_THIN],'allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
    ]);

    // Day data columns AM-BQ
    $itemId = isset($itemMap[$no]) ? $itemMap[$no] : null;

    for ($d = 1; $d <= 31; $d++) {
        $col     = $dayCols[$d - 1];
        $dateStr = date('Y-m', strtotime($startDate)) . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);

        // Day shift
        $valD = '';
        if ($itemId && isset($dataByDate[$dateStr]['records_D'][$itemId])) {
            $valD = $dataByDate[$dateStr]['records_D'][$itemId];
        }
        $sheet->setCellValue("{$col}{$rowD}", $valD);
        $cellStyleD = [
            'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
            'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
        ];
        if ($valD === 'NG') {
            $cellStyleD['font'] = ['color'=>['argb'=>'FFFF0000'],'bold'=>true];
        }
        $sheet->getStyle("{$col}{$rowD}")->applyFromArray($cellStyleD);

        // Night shift
        $valN = '';
        if ($itemId && isset($dataByDate[$dateStr]['records_N'][$itemId])) {
            $valN = $dataByDate[$dateStr]['records_N'][$itemId];
        }
        $sheet->setCellValue("{$col}{$rowN}", $valN);
        $cellStyleN = [
            'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
            'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
        ];
        if ($valN === 'NG') {
            $cellStyleN['font'] = ['color'=>['argb'=>'FFFF0000'],'bold'=>true];
        }
        $sheet->getStyle("{$col}{$rowN}")->applyFromArray($cellStyleN);
    }
}

// Apply outer medium border for the entire table body
$sheet->getStyle('V12:BQ38')->applyFromArray([
    'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM]],
]);

// =========================================================
// ROW 39-40: Symbol legend (left) + Recorder row (right)
// =========================================================
$sheet->mergeCells('B39:AE40');
$sheet->setCellValue('B39', "สัญลักษ์ในการตรวจสอบ :  ให้ทำเครื่องหมายลงในช่องที่ทำการตรวจสอบ\nSymbols : Record into the check box.");
$sheet->getStyle('B39:AE40')->applyFromArray([
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM]],
]);

$sheet->mergeCells('AF39:AJ42');
$sheet->setCellValue('AF39', "ผู้บันทึก\n(Record by)");
$sheet->getStyle('AF39:AJ42')->applyFromArray([
    'font'=>['bold'=>true],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM],'allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
]);

// Shift D label + day data cells for recorder (rows 39-40)
$sheet->setCellValue('AK39', 'D');
$sheet->getStyle('AK39:AL40')->applyFromArray([
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
]);
$sheet->mergeCells('AK39:AL40');

// Recorder D data cells AM-BQ rows 39-40
for ($d = 1; $d <= 31; $d++) {
    $col     = $dayCols[$d - 1];
    $dateStr = date('Y-m', strtotime($startDate)) . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
    $val     = isset($dataByDate[$dateStr]['recorder_D']) ? $dataByDate[$dateStr]['recorder_D'] : '';
    $sheet->mergeCells("{$col}39:{$col}40");
    $sheet->setCellValue("{$col}39", $val);
    $sheet->getStyle("{$col}39:{$col}40")->applyFromArray([
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
        'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
    ]);
}

// =========================================================
// ROW 41-42: Normal symbol + Recorder Night shift
// =========================================================
$sheet->setCellValue('E41', '= ปกติ (Normal)');
$sheet->getStyle('E41')->applyFromArray([
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_CENTER],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_THIN]],
]);

$sheet->mergeCells('AK41:AL42');
$sheet->setCellValue('AK41', 'N');
$sheet->getStyle('AK41:AL42')->applyFromArray([
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
]);

for ($d = 1; $d <= 31; $d++) {
    $col     = $dayCols[$d - 1];
    $dateStr = date('Y-m', strtotime($startDate)) . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
    $val     = isset($dataByDate[$dateStr]['recorder_N']) ? $dataByDate[$dateStr]['recorder_N'] : '';
    $sheet->mergeCells("{$col}41:{$col}42");
    $sheet->setCellValue("{$col}41", $val);
    $sheet->getStyle("{$col}41:{$col}42")->applyFromArray([
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
        'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
    ]);
}

// =========================================================
// ROW 43-44: Abnormal symbol + Checker Day shift
// =========================================================
$sheet->setCellValue('E43', '= ผิดปกติ (Abnormal)');
$sheet->getStyle('E43')->applyFromArray([
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_CENTER],
]);

$sheet->mergeCells('AF43:AJ46');
$sheet->setCellValue('AF43', "ผู้ตรวจสอบ\n(Checking by)");
$sheet->getStyle('AF43:AJ46')->applyFromArray([
    'font'=>['bold'=>true],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM],'allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
]);

$sheet->mergeCells('AK43:AL44');
$sheet->setCellValue('AK43', 'D');
$sheet->getStyle('AK43:AL44')->applyFromArray([
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
]);

for ($d = 1; $d <= 31; $d++) {
    $col     = $dayCols[$d - 1];
    $dateStr = date('Y-m', strtotime($startDate)) . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
    $val     = isset($dataByDate[$dateStr]['checker_D']) ? $dataByDate[$dateStr]['checker_D'] : '';
    $sheet->mergeCells("{$col}43:{$col}44");
    $sheet->setCellValue("{$col}43", $val);
    $sheet->getStyle("{$col}43:{$col}44")->applyFromArray([
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
        'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
    ]);
}

// =========================================================
// ROW 45-46: Abnormal corrected + Checker Night shift
// =========================================================
$sheet->setCellValue('E45', "= ผิดปกติ แก้ไขเรียบร้อยแล้ว \n(Abnormal and corrective)");
$sheet->getStyle('E45')->applyFromArray([
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
]);

$sheet->mergeCells('AK45:AL46');
$sheet->setCellValue('AK45', 'N');
$sheet->getStyle('AK45:AL46')->applyFromArray([
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
]);

for ($d = 1; $d <= 31; $d++) {
    $col     = $dayCols[$d - 1];
    $dateStr = date('Y-m', strtotime($startDate)) . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
    $val     = isset($dataByDate[$dateStr]['checker_N']) ? $dataByDate[$dateStr]['checker_N'] : '';
    $sheet->mergeCells("{$col}45:{$col}46");
    $sheet->setCellValue("{$col}45", $val);
    $sheet->getStyle("{$col}45:{$col}46")->applyFromArray([
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
        'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
    ]);
}

// Apply outer border for recorder/checker section
$sheet->getStyle('AF39:BQ46')->applyFromArray([
    'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM]],
]);

// =========================================================
// ROW 47-48: Not in use symbol
// =========================================================
$sheet->setCellValue('E47', '= ไม่ได้ใช้งาน (Not use)');
$sheet->getStyle('E47')->applyFromArray([
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_CENTER],
]);

// =========================================================
// ROW 49-50: Numeric value note + Remark
// =========================================================
$sheet->setCellValue('E49', '= ให้บันทึกค่าเป็นตัวเลขในหัวข้อที่มีสัญลักษณ์');
$sheet->getStyle('E49')->applyFromArray([
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_CENTER],
]);

$sheet->mergeCells('AG49:BP50');
$sheet->setCellValue('AG49', 'หมายเหตุ (Remark)');
$sheet->getStyle('AG49:BP50')->applyFromArray([
    'font'=>['bold'=>true],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_TOP,'wrapText'=>true],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM]],
]);

// Remark content row 51-52
$sheet->mergeCells('AG51:BP52');
// Collect remarks
$remarkTexts = [];
$dts = $dataByDate;
ksort($dts);
foreach ($dts as $dateKey => $dayData) {
    if (!empty($dayData['remarks'])) {
        $remarkTexts[] = date('d/m/Y', strtotime($dateKey)) . ': ' . $dayData['remarks'];
    }
}
$sheet->setCellValue('AG51', implode("\n", $remarkTexts));
$sheet->getStyle('AG51:BP52')->applyFromArray([
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_TOP,'wrapText'=>true],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_MEDIUM]],
]);

// =========================================================
// ROW 52-56: Document revision table
// =========================================================
$sheet->mergeCells('C52:U52');
$sheet->setCellValue('C52', 'Detail Revised');
$sheet->getStyle('C52:U52')->applyFromArray([
    'font'=>['bold'=>true],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_THIN],'allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
]);

$sheet->mergeCells('V52:X53');
$sheet->setCellValue('V52', 'Prepared');
$sheet->getStyle('V52:X53')->applyFromArray([
    'font'=>['bold'=>true],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_THIN],'allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
]);

$sheet->mergeCells('Y52:AA53');
$sheet->setCellValue('Y52', 'Check');
$sheet->getStyle('Y52:AA53')->applyFromArray([
    'font'=>['bold'=>true],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_THIN],'allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
]);

$sheet->mergeCells('AB52:AD53');
$sheet->setCellValue('AB52', 'Approved');
$sheet->getStyle('AB52:AD53')->applyFromArray([
    'font'=>['bold'=>true],
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_THIN],'allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
]);

// Sub-headers
$sheet->setCellValue('C53', 'Rev');
$sheet->mergeCells('D53:E53');
$sheet->setCellValue('D53', 'Date');
$sheet->mergeCells('F53:U53');
$sheet->setCellValue('F53', 'Reason');
foreach (['C53','D53','F53'] as $c) {
    $sheet->getStyle($c)->applyFromArray([
        'font'=>['bold'=>true],
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
        'borders'=>['outline'=>['borderStyle'=>Border::BORDER_THIN]],
    ]);
}

// Rev 00 row
$sheet->setCellValue('C54', '00');
$sheet->mergeCells('D54:E54');
$sheet->setCellValue('D54', '30/10/2025');
$sheet->mergeCells('F54:U54');
$sheet->setCellValue('F54', 'จัดทำใหม่ (New Release)');
foreach (['C54','D54','F54'] as $c) {
    $sheet->getStyle($c)->applyFromArray([
        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
        'borders'=>['outline'=>['borderStyle'=>Border::BORDER_THIN]],
    ]);
}

// Empty revision rows 55-56
foreach (['55','56'] as $r) {
    $sheet->setCellValue("C{$r}", '');
    $sheet->mergeCells("D{$r}:E{$r}");
    $sheet->mergeCells("F{$r}:U{$r}");
    foreach (["C{$r}","D{$r}","F{$r}"] as $c) {
        $sheet->getStyle($c)->applyFromArray([
            'borders'=>['outline'=>['borderStyle'=>Border::BORDER_THIN]],
        ]);
    }
}

// =========================================================
// ROW 58: Footer
// =========================================================
$sheet->setCellValue('B58', 'Efft.30-05-2025');
$sheet->getStyle('B58')->applyFromArray([
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_LEFT,'vertical'=>Alignment::VERTICAL_CENTER],
]);

$sheet->mergeCells('BI58:BQ58');
$sheet->setCellValue('BI58', 'QP-ED-001(FM-ED-003)  Rev.01');
$sheet->getStyle('BI58:BQ58')->applyFromArray([
    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_RIGHT,'vertical'=>Alignment::VERTICAL_CENTER],
]);

// =========================================================
// Set print area and page setup
// =========================================================
$sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3);
$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setFitToPage(true);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);
$sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.5)->setRight(0.5);

// =========================================================
// Output
// =========================================================
$filename = 'LPG_Record_' . date('Ym') . '_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();