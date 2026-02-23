<?php
/**
 * LPG Module - Export Report to Excel (Using Template Method + Images)
 * Form Format: QP-ED-001 (FM-ED-003) Rev.01
 */

ob_start();
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized access');
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing; // นำเข้าคลาสสำหรับจัดการรูปภาพ

$db = getDB();

// --- 1. รับค่าวันที่ ---
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

// --- 2. โหลดไฟล์ Excel ต้นฉบับ (Template) ---
$templatePath = __DIR__ . '/../../assets/templates/3.LPG-record.xlsx';

if (!file_exists($templatePath)) {
    die("ไม่พบไฟล์ต้นฉบับ กรุณาสร้างโฟลเดอร์ assets/templates/ และนำไฟล์ 3.LPG-record.xlsx ไปวางไว้ครับ");
}

$spreadsheet = IOFactory::load($templatePath);
$sheet = $spreadsheet->getSheetByName('QP-ED-001(FM-ED-003)Rev.01');
if (!$sheet) {
    $sheet = $spreadsheet->getActiveSheet();
}

// --- 3. ดึงข้อมูลจาก Database ---
$stmt = $db->query("SELECT * FROM lpg_inspection_items ORDER BY item_no");
$items = $stmt->fetchAll();

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

$dataByDate = [];
foreach ($records as $row) {
    $date = $row['record_date'];
    if (!isset($dataByDate[$date])) {
        $dataByDate[$date] = [
            'records_D'  => [], 'records_N'  => [],
            'recorder_D' => '', 'recorder_N' => '',
            'checker_D'  => '', 'checker_N'  => '',
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

// ดึงข้อมูลเลขที่เอกสาร
$docStmt = $db->prepare("SELECT * FROM documents WHERE module_type = 'lpg' AND start_date <= ? ORDER BY start_date DESC LIMIT 1");
$docStmt->execute([$startDate]);
$docInfo = $docStmt->fetch();
$docNo   = $docInfo ? $docInfo['doc_no']  : 'QP-ED-001(FM-ED-003)Rev.01';

// --- 4. เติมข้อมูลลงในช่องว่างของ Template ---

// ข้อมูลส่วนหัว (Header)
$sheet->setCellValue('AW7', date('m/Y', strtotime($startDate)));
$sheet->mergeCells('AW9:AX9');
$sheet->getStyle('AW9:AX9')
    ->getAlignment()
    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$sheet->setCellValue('AW9', date('Y', strtotime($startDate)));
$sheet->setCellValue('F9', $docNo);

// ตัวแปรคอลัมน์ของวันที่ 1-31 
$dayCols = ['AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ',
            'BA','BB','BC','BD','BE','BF','BG','BH','BI','BJ','BK','BL','BM','BN','BO','BP','BQ'];

$itemMap = [];
foreach ($items as $item) {
    $itemMap[$item['item_no']] = $item['id'];
}

for ($d = 1; $d <= 31; $d++) {
    $col = $dayCols[$d - 1];
    $dateStr = date('Y-m', strtotime($startDate)) . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);

    $baseRow = 13;
    for ($no = 1; $no <= 13; $no++) {
        $rowD = $baseRow + ($no - 1) * 2;
        $rowN = $rowD + 1;

        $itemId = isset($itemMap[$no]) ? $itemMap[$no] : null;
        $valD = ($itemId && isset($dataByDate[$dateStr]['records_D'][$itemId])) ? $dataByDate[$dateStr]['records_D'][$itemId] : '';
        $valN = ($itemId && isset($dataByDate[$dateStr]['records_N'][$itemId])) ? $dataByDate[$dateStr]['records_N'][$itemId] : '';

        if ($valD === 'OK') $valD = '/';
        if ($valN === 'OK') $valN = '/';
        if ($valD === 'NG') $valD = 'X';
        if ($valN === 'NG') $valN = 'X';

        $sheet->setCellValue("{$col}{$rowD}", $valD);
        $sheet->setCellValue("{$col}{$rowN}", $valN);

        if ($valD === 'X' || $valD === 'NG') $sheet->getStyle("{$col}{$rowD}")->getFont()->getColor()->setARGB('FFFF0000');
        if ($valN === 'X' || $valN === 'NG') $sheet->getStyle("{$col}{$rowN}")->getFont()->getColor()->setARGB('FFFF0000');
    }

    $sheet->setCellValue("{$col}39", $dataByDate[$dateStr]['recorder_D'] ?? '');
    $sheet->setCellValue("{$col}41", $dataByDate[$dateStr]['recorder_N'] ?? '');
    $sheet->setCellValue("{$col}43", $dataByDate[$dateStr]['checker_D'] ?? '');
    $sheet->setCellValue("{$col}45", $dataByDate[$dateStr]['checker_N'] ?? '');
}

$remarkTexts = [];
ksort($dataByDate);
foreach ($dataByDate as $dateKey => $dayData) {
    if (!empty($dayData['remarks'])) {
        $remarkTexts[] = date('d/m/Y', strtotime($dateKey)) . ': ' . $dayData['remarks'];
    }
}
if (!empty($remarkTexts)) {
    $sheet->setCellValue('AG49', implode("\n", $remarkTexts));
}

// --- 5. แนบรูปภาพ LPG Diagram และ Symbols ---

// รูปที่ 1: LPG Diagram
$imagePath = __DIR__ . '/../../assets/images/lpg_diagram.png';
if (file_exists($imagePath)) {
    $drawing = new Drawing();
    $drawing->setName('LPG Diagram');
    $drawing->setPath($imagePath);
    $drawing->setCoordinates('B12');
    $drawing->setResizeProportional(false);
    $drawing->setWidth(552);  // กว้าง 4.5 นิ้ว
    $drawing->setHeight(926); // สูง 5.5 นิ้ว
    $drawing->setOffsetX(650); // ขยับกึ่งกลาง แนวนอน
    $drawing->setOffsetY(40);  // ขยับกึ่งกลาง แนวตั้ง
    $drawing->setWorksheet($sheet);
}

// รูปที่ 2: LPG Symbols
$symbolImagePath = __DIR__ . '/../../assets/images/lpg_symbols.png';
if (file_exists($symbolImagePath)) {
    $drawingSym = new Drawing();
    $drawingSym->setName('LPG Symbols');
    $drawingSym->setPath($symbolImagePath);
    $drawingSym->setCoordinates('B39');
    $drawingSym->setOffsetX(5);
    $drawingSym->setOffsetY(5);
    $drawingSym->setHeight(180); 
    $drawingSym->setWorksheet($sheet);
}


// --- 6. ส่งออกไฟล์ Excel ---
if (ob_get_length()) ob_end_clean();

$filename = 'LPG_Record_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1');

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
exit();