<?php
/**
 * Export Reports (Daily, Monthly, Summary/Others)
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

// ดึงรายชื่อเครื่อง Boiler ทั้งหมดที่ Active (จัดเรียงตามรหัสเครื่อง)
$stmt = $db->query("SELECT * FROM mc_boiler WHERE status = 1 ORDER BY machine_code");
$boilers = $stmt->fetchAll();

$spreadsheet = null;

if ($type === 'daily') {
    // ==========================================
    // 1. DAILY REPORT (ใช้ 1 Sheet, เรียงข้อมูลแต่ละเครื่องลงมาในแนวตั้ง)
    // ==========================================
    $templatePath = __DIR__ . '/../../assets/templates/boiler_daily.xlsx';
    
    if (!file_exists($templatePath)) {
        die("Error: ไม่พบไฟล์ Template รายวัน ที่ " . $templatePath);
    }
    
    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getActiveSheet();
    
    // แก้ไขหัวรายงาน
    $sheet->setTitle("Daily Report");
    $sheet->setCellValue('A3', "ALL BOILER MACHINES");
    $sheet->setCellValue('H3', "Month: " . date("M' y", strtotime("$year-$month-01")));
    
    
    $sheet->setCellValue('B4', 'Machine Name');

    // หาจำนวนวันในเดือนที่เลือก
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    
    $row = 5; // กำหนดแถวเริ่มต้นที่แถว 5
    
    // วนลูปวันที่ 1 ถึง สิ้นเดือน
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $d);
        
        // วนลูปแต่ละเครื่องให้อยู่ในวันเดียวกันแต่คนละบรรทัด
        foreach ($boilers as $boiler) {
            
            $stmtData = $db->prepare("SELECT * FROM boiler_daily_records WHERE machine_id = ? AND record_date = ?");
            $stmtData->execute([$boiler['id'], $dateStr]);
            $record = $stmtData->fetch();

            // ใส่ วันที่ ในคอลัมน์ A และ ชื่อเครื่อง ในคอลัมน์ B
            $sheet->setCellValue('A' . $row, $d);
            $sheet->setCellValue('B' . $row, $boiler['machine_name']); 
            
            // ถ้ามีข้อมูลในวันนั้น ก็นำมาหยอดใส่คอลัมน์ C-H
            if ($record) {
                $sheet->setCellValue('C' . $row, $record['steam_pressure']);
                $sheet->setCellValue('D' . $row, $record['steam_temperature']);
                $sheet->setCellValue('E' . $row, $record['feed_water_level']);
                $sheet->setCellValue('F' . $row, $record['fuel_consumption']);
                $sheet->setCellValue('G' . $row, $record['operating_hours']);
                $sheet->setCellValue('H' . $row, $record['remarks']);
            }
            
            // ขยับแถวลง 1 บรรทัด สำหรับเครื่องต่อไป หรือวันต่อไป
            $row++; 
        }
    }
} elseif ($type === 'monthly') {
    // ==========================================
    // 2. MONTHLY REPORT (ดึงจาก Template Monthly)
    // ==========================================
    $templatePath = __DIR__ . '/../../../assets/templates/boiler_monthly.xlsx';
    
    if (!file_exists($templatePath)) {
        die("Error: ไม่พบไฟล์ Template รายเดือน ที่ " . $templatePath);
    }
    
    $spreadsheet = IOFactory::load($templatePath);
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle("Monthly Report " . $year);
    
    // วนลูปเดือน 1-12
    for ($m = 1; $m <= 12; $m++) {
        $row = 4 + $m; // สมมติว่าตารางข้อมูลเริ่มที่แถว 5
        
        // วนลูปข้อมูลแต่ละเครื่องในเดือนนั้นๆ
        foreach ($boilers as $index => $boiler) {
            $stmtSum = $db->prepare("
                SELECT SUM(fuel_consumption) as total_fuel, 
                       AVG(steam_pressure) as avg_pressure, 
                       SUM(operating_hours) as total_hours
                FROM boiler_daily_records 
                WHERE machine_id = ? AND MONTH(record_date) = ? AND YEAR(record_date) = ?
            ");
            $stmtSum->execute([$boiler['id'], $m, $year]);
            $sum = $stmtSum->fetch();

            // กำหนดจุดเริ่มต้นคอลัมน์ของแต่ละเครื่อง (สมมติเครื่องละ 3 คอลัมน์: Fuel, Pressure, Hours)
            // เครื่องที่ 1: คอลัมน์ที่ 3 (C), เครื่องที่ 2: คอลัมน์ที่ 6 (F)
            $startCol = 3 + ($index * 3);
            
            if ($sum['total_hours'] > 0) {
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startCol) . $row, $sum['total_fuel'] ?? 0);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startCol + 1) . $row, round($sum['avg_pressure'] ?? 0, 2));
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startCol + 2) . $row, $sum['total_hours'] ?? 0);
            }
        }
    }

} else {
    // ==========================================
    // 3. OTHER REPORTS (สร้าง Format ใหม่จากศูนย์ ไม่ใช้ Template)
    // ==========================================
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle(strtoupper($type) . ' REPORT');

    // กำหนดสไตล์
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2C3E50']] // สีน้ำเงินเข้ม
    ];

    $dataStyle = [
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];

    // สร้าง Header ของหน้า
    $sheet->setCellValue('A1', 'MARUGO RUBBER (THAILAND) CO., LTD.');
    $sheet->mergeCells('A1:G1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    
    $sheet->setCellValue('A2', strtoupper($type) . " REPORT - YEAR $year");
    $sheet->mergeCells('A2:G2');
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);

    // สร้างคอลัมน์หัวตาราง
    $headers = ['No.', 'Machine Code', 'Machine Name', 'Total Fuel (Liters)', 'Average Pressure (bar)', 'Total Run Hours', 'Machine Status'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '4', $header);
        $col++;
    }
    $sheet->getStyle('A4:G4')->applyFromArray($headerStyle);
    $sheet->getRowDimension(4)->setRowHeight(30);

    // ดึงข้อมูลสรุป
    $row = 5;
    foreach ($boilers as $index => $boiler) {
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

        // ใส่ข้อมูล
        $sheet->setCellValue('A' . $row, $index + 1);
        $sheet->setCellValue('B' . $row, $boiler['machine_code']);
        $sheet->setCellValue('C' . $row, $boiler['machine_name']);
        $sheet->setCellValue('D' . $row, number_format($summary['total_fuel'] ?? 0, 2));
        $sheet->setCellValue('E' . $row, number_format($summary['avg_pressure'] ?? 0, 2));
        $sheet->setCellValue('F' . $row, number_format($summary['total_hours'] ?? 0, 2));
        $sheet->setCellValue('G' . $row, $boiler['status'] ? 'Active' : 'Inactive');
        
        $sheet->getStyle('A'.$row.':G'.$row)->applyFromArray($dataStyle);
        
        // จัดตัวเลขให้อยู่ตรงกลาง (เฉพาะคอลัมน์ A, D, E, F, G)
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D'.$row.':F'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('G'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $row++;
    }

    // ปรับความกว้างคอลัมน์ให้อัตโนมัติ
    foreach (range('A', 'G') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }
}

// ==========================================
// ส่งออกไฟล์เป็น Excel (Download)
// ==========================================
$filename = "Boiler_Report_" . ucfirst($type) . "_" . date('Ymd_His') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>