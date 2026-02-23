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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

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

// 2. ดึงรายชื่อเครื่อง Air Compressor ทั้งหมด (เพื่อมาทำหัวตารางแนวนอน)
$stmt = $db->query("SELECT * FROM mc_air WHERE status = 1 ORDER BY machine_code ASC");
$machines = $stmt->fetchAll();

// หากไม่มีเครื่องในฐานข้อมูล ให้จำลองหัวตาราง 4 เครื่องตามฟอร์มต้นฉบับ
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

// จัดกลุ่มข้อมูลตามวันที่ และ รหัสเครื่อง
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
    
    // ดึงค่า before_value และ after_value (เลือกเอาข้อมูลที่มีการบันทึกตัวเลข)
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
$sheet->mergeCells('A1:' . Coordinate::stringFromColumnIndex(count($machines) * 3 + 1) . '1');
$sheet->getStyle('A1')->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);
$spreadsheet->getDefaultStyle()->getFont()->setName('Sarabun')->setSize(10);

// --- แถวที่ 1: ชื่อรายงาน ---
$sheet->setCellValue('A1', 'Air Compressor No.1-' . count($machines));
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

// --- แถวที่ 2-4: สร้างหัวตาราง (Headers) ---
$sheet->setCellValue('A2', 'Date');
$sheet->mergeCells('A2:A4');

$colIndex = 2; // เริ่มคอลัมน์ B
foreach ($machines as $machine) {
    $startCol = Coordinate::stringFromColumnIndex($colIndex);       // B
    $midCol = Coordinate::stringFromColumnIndex($colIndex + 1);     // C
    $endCol = Coordinate::stringFromColumnIndex($colIndex + 2);     // D
    
    // ชื่อเครื่อง
    $sheet->setCellValue($startCol . '2', $machine['machine_code']);
    $sheet->mergeCells("{$startCol}2:{$endCol}2");
    
    // Data record & Operate time
    $sheet->setCellValue($startCol . '3', 'Data record');
    $sheet->mergeCells("{$startCol}3:{$midCol}3");
    $sheet->setCellValue($endCol . '3', 'Operate time');
    
    // before / after / hr
    $sheet->setCellValue($startCol . '4', 'before');
    $sheet->setCellValue($midCol . '4', 'after');
    $sheet->setCellValue($endCol . '4', 'hr');
    
    $colIndex += 3; // ขยับไปทำเครื่องถัดไปทีละ 3 คอลัมน์
}

$lastColLetter = Coordinate::stringFromColumnIndex($colIndex - 1);

// จัดรูปแบบหัวตารางให้ตัวหนาและกึ่งกลาง
$headerRange = "A2:{$lastColLetter}4";
$sheet->getStyle($headerRange)->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle($headerRange)->getFont()->setBold(true);

// 5. วนลูปใส่วันที่และข้อมูลการทำงาน
$currentRow = 5;
$currentDateTs = strtotime($startDate);
$endDateTs = strtotime($endDate);

while ($currentDateTs <= $endDateTs) {
    // ฟอร์แมตวันที่แบบ YYYY-MM-DD ตามตัวอย่างในไฟล์ CSV ของคุณ
    $dateStr = date('Y-m-d', $currentDateTs);
    $sheet->setCellValue('A' . $currentRow, $dateStr);
    
    $cIdx = 2; // เริ่มวนลูปใส่ข้อมูลตั้งแต่คอลัมน์ B
    foreach ($machines as $machine) {
        $mId = $machine['id'];
        $beforeCol = Coordinate::stringFromColumnIndex($cIdx);
        $afterCol = Coordinate::stringFromColumnIndex($cIdx + 1);
        $hrCol = Coordinate::stringFromColumnIndex($cIdx + 2);
        
        $b = isset($dataByDate[$dateStr][$mId]) ? $dataByDate[$dateStr][$mId]['before'] : 0;
        $a = isset($dataByDate[$dateStr][$mId]) ? $dataByDate[$dateStr][$mId]['after'] : 0;
        
        // คำนวณ Operate time (hr) = after - before
        $hr = ($a > 0 && $b > 0) ? ($a - $b) : 0;
        
        // ถ้าไม่มีข้อมูล หรือเป็น 0 ให้แสดงเลข 0 (หรือปล่อยว่างก็ได้) ตามฟอร์ม CSV
        $sheet->setCellValue($beforeCol . $currentRow, $b > 0 ? $b : 0);
        $sheet->setCellValue($afterCol . $currentRow, $a > 0 ? $a : 0);
        $sheet->setCellValue($hrCol . $currentRow, $hr > 0 ? $hr : 0);
        
        $cIdx += 3;
    }
    
    $currentRow++;
    $currentDateTs = strtotime('+1 day', $currentDateTs);
}

// 6. ตกแต่งตาราง
$lastRow = $currentRow - 1;
// ใส่เส้นขอบ
$sheet->getStyle("A2:{$lastColLetter}{$lastRow}")->applyFromArray([
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
]);

// ขยายความกว้างของคอลัมน์ให้พอดีตัวอักษร
for ($i = 1; $i <= ($colIndex - 1); $i++) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}

// 7. ส่งออกเป็น Excel (.xlsx)
$filename = 'AirCompressor_Record_' . date('Ym') . '.xlsx';

// เคลียร์บัฟเฟอร์ ล้างขยะตกค้าง ป้องกันไฟล์เสีย
if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1'); 

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();