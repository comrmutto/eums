<?php
/**
 * Export Reports
 * Engineering Utility Monitoring System (EUMS)
 */

// Load Configurations and Libraries
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php'; 
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$db = getDB();

// รับค่า Parameters
$type = $_GET['type'] ?? 'daily'; // daily, monthly, summary
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

// ดึงรายชื่อเครื่อง Boiler ทั้งหมดที่ Active
$stmt = $db->query("SELECT * FROM mc_boiler WHERE status = 1 ORDER BY machine_code");
$boilers = $stmt->fetchAll();

$spreadsheet = null;

if ($type === 'daily') {
    // ==========================================
    // 1. DAILY REPORT (ดึงจาก Template)
    // ==========================================
    // กำหนด Path ของไฟล์ Template (ต้องสร้างโฟลเดอร์ templates และเอาไฟล์ไปวางไว้)
    $templatePath = __DIR__ . '/../../../eums/assets/templates/boiler_daily.xlsx';
    
    if (!file_exists($templatePath)) {
        die("Error: ไม่พบไฟล์ Template ที่ " . $templatePath);
    }
    
    // โหลดไฟล์ Template
    $spreadsheet = IOFactory::load($templatePath);
    $templateSheet = $spreadsheet->getSheet(0); // สมมติว่า Template อยู่ชีตแรก
    
    foreach ($boilers as $boiler) {
        // Copy ชีต Template สำหรับแต่ละเครื่อง (แก้ปัญหา Boiler 2, 3 หาย)
        $newSheet = clone $templateSheet;
        $newSheet->setTitle(strtoupper($boiler['machine_name']));
        $spreadsheet->addSheet($newSheet);
        
        // ใส่หัวกระดาษ (อ้างอิงตำแหน่งจากไฟล์เดิม)
        $newSheet->setCellValue('A3', strtoupper($boiler['machine_name']));
        $newSheet->setCellValue('H3', "Month: " . date("M' y", strtotime("$year-$month-01")));

        // ดึงข้อมูล
        $stmtData = $db->prepare("
            SELECT * FROM boiler_daily_records 
            WHERE machine_id = ? AND MONTH(record_date) = ? AND YEAR(record_date) = ?
            ORDER BY record_date ASC
        ");
        $stmtData->execute([$boiler['id'], $month, $year]);
        $records = $stmtData->fetchAll();

        // หยอดข้อมูลลงตาราง (สมมติว่าเริ่มแถวที่ 5)
        $row = 5;
        foreach ($records as $record) {
            $newSheet->setCellValue('A' . $row, date('d', strtotime($record['record_date'])));
            $newSheet->setCellValue('B' . $row, '08:00'); // ตัวอย่างเวลา
            $newSheet->setCellValue('C' . $row, $record['steam_pressure']);
            $newSheet->setCellValue('D' . $row, $record['steam_temperature']);
            $newSheet->setCellValue('E' . $row, $record['feed_water_level']);
            $newSheet->setCellValue('F' . $row, $record['fuel_consumption']);
            $newSheet->setCellValue('G' . $row, $record['operating_hours']);
            $newSheet->setCellValue('H' . $row, $record['remarks']);
            $row++;
        }
    }
    
    // ลบชีต Template ต้นฉบับทิ้ง เพื่อให้เหลือแค่ชีตของ Boiler ที่มีข้อมูลจริง
    $spreadsheet->removeSheetByIndex(0);

} elseif ($type === 'monthly') {
    // ==========================================
    // 2. MONTHLY REPORT (ดึงจาก Template)
    // ==========================================
    $templatePath = __DIR__ . '/../../../eums/assets/templates/boiler_monthly.xlsx';
    
    if (!file_exists($templatePath)) {
        die("Error: ไม่พบไฟล์ Template ที่ " . $templatePath);
    }
    
    $spreadsheet = IOFactory::load($templatePath);
    // Logic การดึงและหยอดข้อมูลสำหรับ Monthly 
    // ...

} else {
    // ==========================================
    // 3. OTHER REPORTS (สร้าง Format ใหม่ด้วยโค้ด)
    // ==========================================
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle(strtoupper($type) . ' REPORT');

    // สไตล์หัวตาราง
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']]
    ];

    // สไตล์ข้อมูล
    $dataStyle = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];

    // สร้าง Header
    $sheet->setCellValue('A1', 'MARUGO RUBBER (THAILAND) CO., LTD.');
    $sheet->mergeCells('A1:G1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    
    $sheet->setCellValue('A2', strtoupper($type) . ' REPORT - YEAR ' . $year);
    $sheet->mergeCells('A2:G2');

    $headers = ['No.', 'Machine Code', 'Machine Name', 'Total Fuel (L)', 'Avg Pressure (bar)', 'Total Hours (hr)', 'Status'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '4', $header);
        $col++;
    }
    $sheet->getStyle('A4:G4')->applyFromArray($headerStyle);

    // ดึงข้อมูลภาพรวม (Summary)
    $row = 5;
    foreach ($boilers as $index => $boiler) {
        // ตัวอย่างคิวรีดึงข้อมูลสรุปรายปี
        $stmtSum = $db->prepare("
            SELECT 
                SUM(fuel_consumption) as total_fuel,
                AVG(steam_pressure) as avg_pressure,
                SUM(operating_hours) as total_hours
            FROM boiler_daily_records 
            WHERE machine_id = ? AND YEAR(record_date) = ?
        ");
        $stmtSum->execute([$boiler['id'], $year]);
        $summary = $stmtSum->fetch();

        $sheet->setCellValue('A' . $row, $index + 1);
        $sheet->setCellValue('B' . $row, $boiler['machine_code']);
        $sheet->setCellValue('C' . $row, $boiler['machine_name']);
        $sheet->setCellValue('D' . $row, $summary['total_fuel'] ?? 0);
        $sheet->setCellValue('E' . $row, number_format($summary['avg_pressure'] ?? 0, 2));
        $sheet->setCellValue('F' . $row, $summary['total_hours'] ?? 0);
        $sheet->setCellValue('G' . $row, $boiler['status'] ? 'Active' : 'Inactive');
        
        $sheet->getStyle('A'.$row.':G'.$row)->applyFromArray($dataStyle);
        $row++;
    }

    // จัดความกว้างคอลัมน์อัตโนมัติ
    foreach (range('A', 'G') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }
}

// ==========================================
// 4. ส่งออกไฟล์ (Download)
// ==========================================
$filename = "Boiler_Report_" . ucfirst($type) . "_" . date('Ymd_His') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>