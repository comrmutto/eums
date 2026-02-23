<?php
/**
 * LPG Module - Export Report to Excel (PhpSpreadsheet)
 * Form Format: QP-ED-001 (FM-ED-003)
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized access');
}

// Load Composer Autoloader (ปรับ Path ให้ตรงกับโฟลเดอร์ vendor ของคุณ)
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Get database connection
$db = getDB();

// 1. รับค่าและแปลงวันที่จาก DD/MM/YYYY เป็น YYYY-MM-DD
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

// 2. ดึงข้อมูลหัวข้อตรวจสอบทั้งหมด (เพื่อทำเป็น Column Header)
$stmt = $db->query("SELECT * FROM lpg_inspection_items ORDER BY item_no");
$items = $stmt->fetchAll();

// 3. ดึงข้อมูลการบันทึกในช่วงวันที่เลือก
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

// จัดกลุ่มข้อมูลตามวันที่
$dataByDate = [];
foreach ($records as $row) {
    $date = $row['record_date'];
    if (!isset($dataByDate[$date])) {
        $dataByDate[$date] = [
            'records' => [],
            'recorder' => $row['recorded_by'],
            'remarks' => $row['remarks']
        ];
    }
    
    $val = $row['item_type'] == 'number' ? $row['number_value'] : $row['enum_value'];
    $dataByDate[$date]['records'][$row['item_id']] = $val;
}

// 4. สร้างไฟล์ Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('LPG Record');

// กำหนดฟอนต์เริ่มต้น
$spreadsheet->getDefaultStyle()->getFont()->setName('Sarabun')->setSize(10);

// --- สร้างส่วนหัวของเอกสาร (Header) ---
$sheet->setCellValue('A1', 'บันทึกการตรวจเช็คและการใช้งาน LPG ประจำวัน');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

// ดึงข้อมูลเลขที่เอกสาร (ถ้ามี)
$docStmt = $db->prepare("SELECT * FROM documents WHERE module_type = 'lpg' AND start_date <= ? ORDER BY start_date DESC LIMIT 1");
$docStmt->execute([$startDate]);
$docInfo = $docStmt->fetch();

if ($docInfo) {
    $sheet->setCellValue('A2', 'Doc No: ' . $docInfo['doc_no'] . ' | Rev: ' . $docInfo['rev_no'] . ' | Start Date: ' . date('d/m/Y', strtotime($docInfo['start_date'])));
} else {
    $sheet->setCellValue('A2', 'QP-ED-001 (FM-ED-003)');
}

// --- สร้างหัวตาราง (Table Headers) ---
$headerRow = 4;
$sheet->setCellValue('A' . $headerRow, 'วันที่');

$colIndex = 2; // เริ่มที่คอลัมน์ B
$itemColumns = []; // เก็บ Mapping ว่า item_id ไหนอยู่คอลัมน์ไหน

foreach ($items as $item) {
    $colLetter = Coordinate::stringFromColumnIndex($colIndex);
    
    // จัดรูปแบบชื่อคอลัมน์ เช่น "แรงดันแก๊ส\n(ค่ามาตรฐาน 4-5 kg/cm2)"
    $headerText = $item['item_name'];
    if (!empty($item['standard_value'])) {
        $headerText .= "\n(" . $item['standard_value'] . ($item['unit'] ? ' ' . $item['unit'] : '') . ")";
    }
    
    $sheet->setCellValue($colLetter . $headerRow, $headerText);
    $itemColumns[$item['id']] = $colIndex;
    $colIndex++;
}

// เพิ่มคอลัมน์หมายเหตุและผู้บันทึก
$remarkColLetter = Coordinate::stringFromColumnIndex($colIndex);
$sheet->setCellValue($remarkColLetter . $headerRow, 'หมายเหตุ');
$colIndex++;

$recorderColLetter = Coordinate::stringFromColumnIndex($colIndex);
$sheet->setCellValue($recorderColLetter . $headerRow, 'ผู้บันทึก');
$lastColIndex = $colIndex;

// Merge เซลล์หัวเอกสารให้คลุมถึงคอลัมน์สุดท้าย
$lastColLetter = Coordinate::stringFromColumnIndex($lastColIndex);
$sheet->mergeCells("A1:{$lastColLetter}1");
$sheet->mergeCells("A2:{$lastColLetter}2");
$sheet->getStyle("A1:{$lastColLetter}2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// --- วนลูปใส่วันที่และข้อมูล ---
$currentRow = $headerRow + 1;
$currentDateTs = strtotime($startDate);
$endDateTs = strtotime($endDate);

// วนลูปสร้างแถวตามจำนวนวัน (เพื่อให้เป็นฟอร์มตารางแบบปฏิทิน)
while ($currentDateTs <= $endDateTs) {
    $dateStr = date('Y-m-d', $currentDateTs);
    $displayDate = date('d/m/Y', $currentDateTs);
    
    $sheet->setCellValue('A' . $currentRow, $displayDate);
    
    if (isset($dataByDate[$dateStr])) {
        // ใส่ข้อมูลแต่ละไอเทม
        foreach ($items as $item) {
            $cId = $item['id'];
            if (isset($dataByDate[$dateStr]['records'][$cId])) {
                $colLet = Coordinate::stringFromColumnIndex($itemColumns[$cId]);
                $val = $dataByDate[$dateStr]['records'][$cId];
                $sheet->setCellValue($colLet . $currentRow, $val);
                
                // ถ้าระบบเจอ NG ให้ไฮไลต์สีแดงอ่อน
                if ($val === 'NG') {
                    $sheet->getStyle($colLet . $currentRow)->getFont()->getColor()->setARGB('FFFF0000');
                    $sheet->getStyle($colLet . $currentRow)->getFont()->setBold(true);
                }
            }
        }
        // ใส่หมายเหตุและผู้บันทึก
        $sheet->setCellValue($remarkColLetter . $currentRow, $dataByDate[$dateStr]['remarks']);
        $sheet->setCellValue($recorderColLetter . $currentRow, $dataByDate[$dateStr]['recorder']);
    }
    
    $currentRow++;
    $currentDateTs = strtotime('+1 day', $currentDateTs);
}

// --- ตกแต่งความสวยงาม (Styling) ---
$lastRow = $currentRow - 1;
$tableRange = 'A' . $headerRow . ':' . $lastColLetter . $lastRow;

// ใส่กรอบ (Borders) และจัดกึ่งกลาง
$styleArray = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FF000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true // ตัดบรรทัดอัตโนมัติ
    ],
];
$sheet->getStyle($tableRange)->applyFromArray($styleArray);

// ไฮไลต์สีพื้นหลังแถวหัวตาราง
$sheet->getStyle('A' . $headerRow . ':' . $lastColLetter . $headerRow)->applyFromArray([
    'font' => ['bold' => true],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FFE2EFDA'] // สีเขียวอ่อนๆ
    ]
]);

// ปรับความกว้างของคอลัมน์อัตโนมัติ
for ($i = 1; $i <= $lastColIndex; $i++) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}
// ปรับคอลัมน์หมายเหตุให้กว้างหน่อย
$sheet->getColumnDimension($remarkColLetter)->setAutoSize(false);
$sheet->getColumnDimension($remarkColLetter)->setWidth(30);

// --- ส่งออกไฟล์ Excel ---
$filename = 'LPG_Record_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();