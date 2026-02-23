<?php
/**
 * Export Report - Summary Electricity (Using Excel Template & PhpSpreadsheet)
 * Engineering Utility Monitoring System (EUMS)
 */

// เริ่มต้นป้องกัน Error ขยะหลุดไปในไฟล์ Excel
ob_start();
ini_set('display_errors', 0);
error_reporting(0);

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /eums/login.php');
    exit();
}

// Load required files
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Get database connection
$db = getDB();

// Get parameters
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'monthly';
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Set filename
$filename = "summary_electricity_{$report_type}_" . date('Ymd_His');

// Prepare data based on report type
switch ($report_type) {
    case 'daily':
        $data = getDailyData($db, $start_date, $end_date);
        $filename .= "_daily";
        break;
    case 'monthly':
        $data = getMonthlyData($db, $year);
        $filename .= "_{$year}";
        break;
    case 'yearly':
        $data = getYearlyData($db);
        $filename .= "_yearly";
        break;
    case 'comparison':
        $compare_year = isset($_GET['compare_year']) ? (int)$_GET['compare_year'] : ($year - 1);
        $data = getComparisonData($db, $year, $compare_year);
        $filename .= "_compare_{$year}_vs_{$compare_year}";
        break;
    default:
        $data = getMonthlyData($db, $year);
        $filename .= "_{$year}";
}

// Export based on format
switch ($format) {
    case 'excel':
        exportExcelTemplate($db, $data, $filename, $report_type, $year);
        break;
    case 'csv':
        exportCSV($data, $filename);
        break;
    default:
        exportExcelTemplate($db, $data, $filename, $report_type, $year);
}

/**
 * Main Export Excel Function (Handles Template and Generic generation)
 */
function exportExcelTemplate($db, $data, $filename, $report_type, $year) {
    // 1. ถ้ารายงานเป็นแบบรายเดือน (แสดงครบ 12 เดือนของปี) ให้ใช้ Excel Template
    if ($report_type === 'monthly') {
        $templatePath = __DIR__ . '/../../assets/templates/summary-electricity-record.xlsx';
        
        if (file_exists($templatePath)) {
            $spreadsheet = IOFactory::load($templatePath);
            
            // ----------------------------------------------------
            // SHEET 1: จัดการข้อมูลในหน้าแรก (Energy Factory 2024)
            // ----------------------------------------------------
            $sheet = $spreadsheet->getSheet(0); 
            
            // อัปเดตปีที่หัวตาราง
            $sheet->setCellValue('B3', "Monthly   " . $year);
            
            // ดึงข้อมูล 12 เดือนจากฐานข้อมูล (เพิ่มฟิลด์ LPG เข้ามาตามเงื่อนไข)
            $stmt = $db->prepare("
                SELECT 
                    MONTH(record_date) as month,
                    SUM(ee_unit) as total_ee,
                    SUM(total_cost) as total_cost,
                    SUM(lpg_unit) as total_lpg,
                    SUM(total_lpg_cost) as total_lpg_cost
                FROM electricity_summary
                WHERE YEAR(record_date) = ?
                GROUP BY MONTH(record_date)
            ");
            $stmt->execute([$year]);
            $records = $stmt->fetchAll();
            
            // Map เดือน (1-12) เข้ากับคอลัมน์ (C-N) ใน Excel 
            $colMap = [1=>'C', 2=>'D', 3=>'E', 4=>'F', 5=>'G', 6=>'H', 7=>'I', 8=>'J', 9=>'K', 10=>'L', 11=>'M', 12=>'N'];
            
            foreach ($records as $row) {
                $m = (int)$row['month'];
                if (isset($colMap[$m])) {
                    $c = $colMap[$m];
                    
                    $ee = $row['total_ee'] ?? 0;
                    $ee_cost = $row['total_cost'] ?? 0;
                    $lpg = $row['total_lpg'] ?? 0;
                    $lpg_cost = $row['total_lpg_cost'] ?? 0;
                    
                    // --- ลงข้อมูลไฟฟ้า (EE) ---
                    $sheet->setCellValue($c . '6', $ee);           // EE ( KWh. ) = ee_unit
                    $sheet->setCellValue($c . '7', $ee_cost);      // EE ( MJ. ) = total_cost
                    $sheet->setCellValue($c . '8', $ee_cost);      // EE ( Baht. ) = total_cost
                    $sheet->setCellValue($c . '9', $ee > 0 ? ($ee_cost / $ee) : 0); // EE ( Baht. / KWh. )

                    // --- ลงข้อมูลก๊าซ (LPG) ---
                    $sheet->setCellValue($c . '11', $lpg);         // LPG ( kg. ) = lpg_unit
                    $sheet->setCellValue($c . '12', $lpg_cost);    // LPG ( MJ. ) = total_lpg_cost
                    $sheet->setCellValue($c . '13', $lpg_cost);    // LPG ( Baht. ) = total_lpg_cost
                    $sheet->setCellValue($c . '14', $lpg > 0 ? ($lpg_cost / $lpg) : 0); // LPG ( Baht. / kg. )
                }
            }

            // ----------------------------------------------------
            // SHEET 2: จัดการข้อมูลหน้าสรุปสัดส่วนพลังงาน
            // ----------------------------------------------------
            $sheet2 = $spreadsheet->getSheetByName('สรุปสัดส่วนพลังงาน');
            if ($sheet2) {
                // อัปเดตข้อความปี
                $sheet2->setCellValue('B3', ' January - December ' . $year);
                $sheet2->setCellValue('B4', 'Power Type / ' . $year);
                
                // ดึงผลรวมทั้งปีสำหรับหน้าสรุป
                $stmtTotal = $db->prepare("
                    SELECT 
                        SUM(ee_unit) as total_ee, 
                        SUM(total_cost) as total_cost,
                        SUM(lpg_unit) as total_lpg,
                        SUM(total_lpg_cost) as total_lpg_cost
                    FROM electricity_summary 
                    WHERE YEAR(record_date) = ?
                ");
                $stmtTotal->execute([$year]);
                $totalYear = $stmtTotal->fetch();
                
                // ลงข้อมูลหน้าสรุป (อิงตามไฟล์ต้นฉบับแถวที่ 6 และ 7)
                // ส่วนไฟฟ้า (Electric Power)
                $sheet2->setCellValue('C6', $totalYear['total_ee'] ?? 0);   // quantity
                $sheet2->setCellValue('G6', $totalYear['total_cost'] ?? 0); // Cost Bath
                
                // ส่วนแก๊ส (Gas Fuel LPG)
                $sheet2->setCellValue('C7', $totalYear['total_lpg'] ?? 0);       // quantity
                $sheet2->setCellValue('G7', $totalYear['total_lpg_cost'] ?? 0);  // Cost Bath
            }

            outputSpreadsheet($spreadsheet, $filename);
            return;
        }
    }

    // 2. ถ้าไม่ใช่ Monthly หรือไม่มีไฟล์ Template ให้สร้างตาราง Excel แบบเรียบร้อย (Generic)
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Summary Report');
    $spreadsheet->getDefaultStyle()->getFont()->setName('Tahoma')->setSize(10);

    // ใส่ Header
    $sheet->setCellValue('A1', $data['title']);
    $sheet->setCellValue('A2', $data['period']);
    $sheet->setCellValue('A3', 'วันที่ส่งออก: ' . date('d/m/Y H:i:s') . ' โดย: ' . $_SESSION['fullname']);
    $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(14);

    // ใส่ชื่อคอลัมน์
    $colIndex = 1;
    foreach ($data['headers'] as $header) {
        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
        $sheet->setCellValue($colLetter . '5', $header);
        $sheet->getStyle($colLetter . '5')->getFont()->setBold(true);
        $sheet->getStyle($colLetter . '5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9D9D9');
        $colIndex++;
    }
    $lastColLetter = Coordinate::stringFromColumnIndex($colIndex - 1);

    // วนลูปข้อมูลลงตาราง
    $rowIdx = 6;
    foreach ($data['data'] as $row) {
        $colIndex = 1;
        foreach ($row as $val) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($colLetter . $rowIdx, $val);
            if (is_numeric($val) && !strpos($colLetter, 'ปี') && !strpos($colLetter, 'เดือน')) {
                $sheet->getStyle($colLetter . $rowIdx)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $colIndex++;
        }
        $rowIdx++;
    }

    // ส่วนสรุป (Summary) ด้านล่าง
    $rowIdx += 2;
    $sheet->setCellValue('A' . $rowIdx, 'สรุปข้อมูล');
    $sheet->getStyle('A' . $rowIdx)->getFont()->setBold(true)->setSize(12);
    $rowIdx++;
    foreach ($data['summary'] as $key => $val) {
        $sheet->setCellValue('A' . $rowIdx, $key);
        $sheet->setCellValue('B' . $rowIdx, is_numeric($val) ? round($val, 2) : $val);
        $rowIdx++;
    }

    // ขยายความกว้างคอลัมน์ให้อัตโนมัติ
    for ($i = 1; $i <= count($data['headers']); $i++) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
    }

    outputSpreadsheet($spreadsheet, $filename);
}

/**
 * Helper: ส่งออกไฟล์ PhpSpreadsheet สู่เบราว์เซอร์
 */
function outputSpreadsheet($spreadsheet, $filename) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน Summary Electricity ({$filename})");
    exit();
}

// ----------------------------------------------------
// ข้อมูล Data Fetcher 
// ----------------------------------------------------

function getDailyData($db, $start_date, $end_date) {
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(record_date, '%d/%m/%Y') as date,
            ee_unit, cost_per_unit, total_cost, pe, remarks, recorded_by,
            DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as created_at
        FROM electricity_summary
        WHERE record_date BETWEEN ? AND ?
        ORDER BY record_date
    ");
    $stmt->execute([$start_date, $end_date]);
    $records = $stmt->fetchAll();
    
    $total_ee = 0; $total_cost = 0;
    foreach ($records as $row) { $total_ee += $row['ee_unit']; $total_cost += $row['total_cost']; }
    
    return [
        'title' => 'รายงานการใช้ไฟฟ้ารายวัน',
        'period' => 'ระหว่างวันที่ ' . date('d/m/Y', strtotime($start_date)) . ' ถึง ' . date('d/m/Y', strtotime($end_date)),
        'headers' => ['วันที่', 'หน่วยไฟฟ้า (kWh)', 'ค่าไฟต่อหน่วย (บาท)', 'ค่าไฟฟ้า (บาท)', 'PE', 'หมายเหตุ', 'ผู้บันทึก', 'วันที่บันทึก'],
        'data' => $records,
        'summary' => [
            'รวมหน่วยไฟฟ้า' => $total_ee,
            'รวมค่าไฟฟ้า' => $total_cost,
            'ค่าไฟเฉลี่ยต่อหน่วย' => $total_ee > 0 ? $total_cost / $total_ee : 0,
            'จำนวนวัน' => count($records)
        ]
    ];
}

function getMonthlyData($db, $year) {
    $stmt = $db->prepare("
        SELECT 
            MONTH(record_date) as month,
            COUNT(*) as days_count,
            SUM(ee_unit) as total_ee,
            SUM(total_cost) as total_cost,
            AVG(cost_per_unit) as avg_cost,
            MAX(ee_unit) as max_ee,
            MIN(ee_unit) as min_ee
        FROM electricity_summary
        WHERE YEAR(record_date) = ?
        GROUP BY MONTH(record_date)
        ORDER BY month
    ");
    $stmt->execute([$year]);
    $records = $stmt->fetchAll();
    
    $total_ee = 0; $total_cost = 0;
    foreach ($records as $row) { $total_ee += $row['total_ee']; $total_cost += $row['total_cost']; }
    
    $formatted_records = [];
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    foreach ($records as $row) {
        $formatted_records[] = [
            'เดือน' => $months[$row['month'] - 1] . ' ' . ($year + 543),
            'จำนวนวัน' => $row['days_count'],
            'หน่วยไฟฟ้ารวม' => $row['total_ee'],
            'ค่าไฟฟ้ารวม' => $row['total_cost'],
            'ค่าไฟเฉลี่ย/หน่วย' => $row['avg_cost'],
            'สูงสุดรายวัน' => $row['max_ee'],
            'ต่ำสุดรายวัน' => $row['min_ee']
        ];
    }
    
    return [
        'title' => 'รายงานการใช้ไฟฟ้ารายเดือน',
        'period' => 'ปี ' . ($year + 543),
        'headers' => ['เดือน', 'จำนวนวัน', 'หน่วยไฟฟ้ารวม (kWh)', 'ค่าไฟฟ้ารวม (บาท)', 'ค่าไฟเฉลี่ย/หน่วย', 'สูงสุดรายวัน', 'ต่ำสุดรายวัน'],
        'data' => $formatted_records,
        'summary' => [
            'รวมหน่วยไฟฟ้าทั้งปี' => $total_ee,
            'รวมค่าไฟฟ้าทั้งปี' => $total_cost,
            'ค่าไฟเฉลี่ยต่อหน่วยทั้งปี' => $total_ee > 0 ? $total_cost / $total_ee : 0,
            'จำนวนเดือนที่มีข้อมูล' => count($records)
        ]
    ];
}

function getYearlyData($db) {
    $stmt = $db->query("
        SELECT 
            YEAR(record_date) as year, COUNT(*) as days_count,
            SUM(ee_unit) as total_ee, SUM(total_cost) as total_cost, AVG(cost_per_unit) as avg_cost
        FROM electricity_summary GROUP BY YEAR(record_date) ORDER BY year DESC
    ");
    $records = $stmt->fetchAll();
    
    $formatted_records = []; $prev_total = null;
    $total_ee = 0; $total_cost = 0;
    
    foreach ($records as $row) {
        $growth = null;
        if ($prev_total !== null) { $growth = $prev_total > 0 ? (($row['total_ee'] - $prev_total) / $prev_total) * 100 : 0; }
        $formatted_records[] = [
            'ปี' => $row['year'] + 543, 'จำนวนวัน' => $row['days_count'],
            'หน่วยไฟฟ้ารวม' => $row['total_ee'], 'ค่าไฟฟ้ารวม' => $row['total_cost'],
            'ค่าไฟเฉลี่ย/หน่วย' => $row['avg_cost'], 'การเติบโต' => $growth !== null ? number_format($growth, 2) . '%' : '-'
        ];
        $prev_total = $row['total_ee'];
        $total_ee += $row['total_ee']; $total_cost += $row['total_cost'];
    }
    
    return [
        'title' => 'รายงานการใช้ไฟฟ้ารายปี', 'period' => 'ข้อมูลทั้งหมด',
        'headers' => ['ปี', 'จำนวนวัน', 'หน่วยไฟฟ้ารวม (kWh)', 'ค่าไฟฟ้ารวม (บาท)', 'ค่าไฟเฉลี่ย/หน่วย', 'การเติบโต'],
        'data' => $formatted_records,
        'summary' => [
            'รวมหน่วยไฟฟ้าทั้งหมด' => $total_ee, 'รวมค่าไฟฟ้าทั้งหมด' => $total_cost,
            'ค่าไฟเฉลี่ยต่อหน่วย' => $total_ee > 0 ? $total_cost / $total_ee : 0, 'จำนวนปีที่มีข้อมูล' => count($records)
        ]
    ];
}

function getComparisonData($db, $year1, $year2) {
    $stmt = $db->prepare("SELECT MONTH(record_date) as month, SUM(ee_unit) as total_ee, SUM(total_cost) as total_cost FROM electricity_summary WHERE YEAR(record_date) = ? GROUP BY MONTH(record_date)");
    $stmt->execute([$year1]); $data1 = $stmt->fetchAll();
    $stmt->execute([$year2]); $data2 = $stmt->fetchAll();
    
    $monthly1 = []; $monthly2 = [];
    foreach ($data1 as $row) $monthly1[$row['month']] = $row;
    foreach ($data2 as $row) $monthly2[$row['month']] = $row;
    
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $formatted_records = []; $total1 = 0; $total2 = 0;
    
    for ($m = 1; $m <= 12; $m++) {
        $ee1 = $monthly1[$m]['total_ee'] ?? 0; $cost1 = $monthly1[$m]['total_cost'] ?? 0;
        $ee2 = $monthly2[$m]['total_ee'] ?? 0; $cost2 = $monthly2[$m]['total_cost'] ?? 0;
        $diff = $ee1 - $ee2;
        $percent = $ee2 > 0 ? ($diff / $ee2) * 100 : 0;
        
        $formatted_records[] = [
            'เดือน' => $months[$m - 1], "ปี {$year1}" => $ee1, "ปี {$year2}" => $ee2,
            'ความต่าง' => $diff, '% เปลี่ยนแปลง' => number_format($percent, 2) . '%',
            "ค่าไฟฟ้า {$year1}" => $cost1, "ค่าไฟฟ้า {$year2}" => $cost2
        ];
        $total1 += $ee1; $total2 += $ee2;
    }
    
    return [
        'title' => 'รายงานเปรียบเทียบการใช้ไฟฟ้า',
        'period' => "เปรียบเทียบระหว่างปี " . ($year1 + 543) . " กับปี " . ($year2 + 543),
        'headers' => ['เดือน', "ปี {$year1} (kWh)", "ปี {$year2} (kWh)", 'ความต่าง', '% เปลี่ยนแปลง', "ค่าไฟฟ้า {$year1}", "ค่าไฟฟ้า {$year2}"],
        'data' => $formatted_records,
        'summary' => [
            'รวมปี ' . ($year1 + 543) => $total1, 'รวมปี ' . ($year2 + 543) => $total2,
            'ความต่างรวม' => $total1 - $total2, '% เปลี่ยนแปลงรวม' => $total2 > 0 ? (($total1 - $total2) / $total2) * 100 : 0
        ]
    ];
}

function exportCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM สำหรับภาษาไทย
    
    fputcsv($output, [$data['title']]);
    fputcsv($output, [$data['period']]);
    fputcsv($output, ['วันที่ส่งออก: ' . date('d/m/Y H:i:s') . ' โดย: ' . $_SESSION['fullname']]);
    fputcsv($output, []);
    fputcsv($output, $data['headers']);
    
    foreach ($data['data'] as $row) {
        $row_data = [];
        foreach ($row as $value) { $row_data[] = is_numeric($value) ? number_format($value, 2) : $value; }
        fputcsv($output, $row_data);
    }
    
    fputcsv($output, []); fputcsv($output, ['สรุปข้อมูล']);
    foreach ($data['summary'] as $key => $value) {
        fputcsv($output, [$key, is_numeric($value) ? number_format($value, 2) : $value]);
    }
    fclose($output); exit();
}