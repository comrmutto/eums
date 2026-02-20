<?php
/**
 * Export Report - Summary Electricity
 * Engineering Utility Monitoring System (EUMS)
 */

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
        exportExcel($data, $filename);
        break;
    case 'csv':
        exportCSV($data, $filename);
        break;
    case 'pdf':
        exportPDF($data, $filename, $report_type);
        break;
    default:
        exportExcel($data, $filename);
}

/**
 * Get daily report data
 */
function getDailyData($db, $start_date, $end_date) {
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(record_date, '%d/%m/%Y') as date,
            ee_unit,
            cost_per_unit,
            total_cost,
            pe,
            remarks,
            recorded_by,
            DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as created_at
        FROM electricity_summary
        WHERE record_date BETWEEN ? AND ?
        ORDER BY record_date
    ");
    $stmt->execute([$start_date, $end_date]);
    $records = $stmt->fetchAll();
    
    // Calculate totals
    $total_ee = 0;
    $total_cost = 0;
    foreach ($records as $row) {
        $total_ee += $row['ee_unit'];
        $total_cost += $row['total_cost'];
    }
    
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

/**
 * Get monthly report data
 */
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
    
    // Calculate yearly totals
    $total_ee = 0;
    $total_cost = 0;
    foreach ($records as $row) {
        $total_ee += $row['total_ee'];
        $total_cost += $row['total_cost'];
    }
    
    // Format data for display
    $formatted_records = [];
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 
               'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    
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

/**
 * Get yearly report data
 */
function getYearlyData($db) {
    $stmt = $db->query("
        SELECT 
            YEAR(record_date) as year,
            COUNT(*) as days_count,
            SUM(ee_unit) as total_ee,
            SUM(total_cost) as total_cost,
            AVG(cost_per_unit) as avg_cost
        FROM electricity_summary
        GROUP BY YEAR(record_date)
        ORDER BY year DESC
    ");
    $records = $stmt->fetchAll();
    
    // Calculate growth
    $formatted_records = [];
    $prev_total = null;
    
    foreach ($records as $row) {
        $growth = null;
        if ($prev_total !== null) {
            $growth = $prev_total > 0 ? (($row['total_ee'] - $prev_total) / $prev_total) * 100 : 0;
        }
        
        $formatted_records[] = [
            'ปี' => $row['year'] + 543,
            'จำนวนวัน' => $row['days_count'],
            'หน่วยไฟฟ้ารวม' => $row['total_ee'],
            'ค่าไฟฟ้ารวม' => $row['total_cost'],
            'ค่าไฟเฉลี่ย/หน่วย' => $row['avg_cost'],
            'การเติบโต' => $growth !== null ? number_format($growth, 2) . '%' : '-'
        ];
        
        $prev_total = $row['total_ee'];
    }
    
    // Calculate totals
    $total_ee = 0;
    $total_cost = 0;
    foreach ($records as $row) {
        $total_ee += $row['total_ee'];
        $total_cost += $row['total_cost'];
    }
    
    return [
        'title' => 'รายงานการใช้ไฟฟ้ารายปี',
        'period' => 'ข้อมูลทั้งหมด',
        'headers' => ['ปี', 'จำนวนวัน', 'หน่วยไฟฟ้ารวม (kWh)', 'ค่าไฟฟ้ารวม (บาท)', 'ค่าไฟเฉลี่ย/หน่วย', 'การเติบโต'],
        'data' => $formatted_records,
        'summary' => [
            'รวมหน่วยไฟฟ้าทั้งหมด' => $total_ee,
            'รวมค่าไฟฟ้าทั้งหมด' => $total_cost,
            'ค่าไฟเฉลี่ยต่อหน่วย' => $total_ee > 0 ? $total_cost / $total_ee : 0,
            'จำนวนปีที่มีข้อมูล' => count($records)
        ]
    ];
}

/**
 * Get comparison report data
 */
function getComparisonData($db, $year1, $year2) {
    // Get data for first year
    $stmt = $db->prepare("
        SELECT 
            MONTH(record_date) as month,
            SUM(ee_unit) as total_ee,
            SUM(total_cost) as total_cost
        FROM electricity_summary
        WHERE YEAR(record_date) = ?
        GROUP BY MONTH(record_date)
    ");
    $stmt->execute([$year1]);
    $data1 = $stmt->fetchAll();
    
    // Get data for second year
    $stmt->execute([$year2]);
    $data2 = $stmt->fetchAll();
    
    // Create month lookup
    $monthly1 = [];
    $monthly2 = [];
    foreach ($data1 as $row) {
        $monthly1[$row['month']] = $row;
    }
    foreach ($data2 as $row) {
        $monthly2[$row['month']] = $row;
    }
    
    // Format data
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 
               'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $formatted_records = [];
    $total1 = 0;
    $total2 = 0;
    
    for ($m = 1; $m <= 12; $m++) {
        $ee1 = isset($monthly1[$m]) ? $monthly1[$m]['total_ee'] : 0;
        $ee2 = isset($monthly2[$m]) ? $monthly2[$m]['total_ee'] : 0;
        $cost1 = isset($monthly1[$m]) ? $monthly1[$m]['total_cost'] : 0;
        $cost2 = isset($monthly2[$m]) ? $monthly2[$m]['total_cost'] : 0;
        
        $diff = $ee1 - $ee2;
        $percent = $ee2 > 0 ? ($diff / $ee2) * 100 : 0;
        
        $formatted_records[] = [
            'เดือน' => $months[$m - 1],
            "ปี {$year1}" => $ee1,
            "ปี {$year2}" => $ee2,
            'ความต่าง' => $diff,
            '% เปลี่ยนแปลง' => number_format($percent, 2) . '%',
            "ค่าไฟฟ้า {$year1}" => $cost1,
            "ค่าไฟฟ้า {$year2}" => $cost2
        ];
        
        $total1 += $ee1;
        $total2 += $ee2;
    }
    
    return [
        'title' => 'รายงานเปรียบเทียบการใช้ไฟฟ้า',
        'period' => "เปรียบเทียบระหว่างปี " . ($year1 + 543) . " กับปี " . ($year2 + 543),
        'headers' => ['เดือน', "ปี {$year1} (kWh)", "ปี {$year2} (kWh)", 'ความต่าง', '% เปลี่ยนแปลง', "ค่าไฟฟ้า {$year1}", "ค่าไฟฟ้า {$year2}"],
        'data' => $formatted_records,
        'summary' => [
            'รวมปี ' . ($year1 + 543) => $total1,
            'รวมปี ' . ($year2 + 543) => $total2,
            'ความต่างรวม' => $total1 - $total2,
            '% เปลี่ยนแปลงรวม' => $total2 > 0 ? (($total1 - $total2) / $total2) * 100 : 0
        ]
    ];
}

/**
 * Export to Excel
 */
function exportExcel($data, $filename) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Start HTML table (simple Excel format)
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>' . $data['title'] . '</title>';
    echo '<style>';
    echo 'th { background-color: #f2f2f2; font-weight: bold; text-align: center; }';
    echo 'td { text-align: right; }';
    echo 'td.left { text-align: left; }';
    echo '.summary { margin-top: 20px; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    // Title
    echo '<h2>' . $data['title'] . '</h2>';
    echo '<h3>' . $data['period'] . '</h3>';
    echo '<p>วันที่ส่งออก: ' . date('d/m/Y H:i:s') . ' โดย: ' . $_SESSION['fullname'] . '</p>';
    
    // Data table
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<thead><tr>';
    foreach ($data['headers'] as $header) {
        echo '<th>' . $header . '</th>';
    }
    echo '</tr></thead>';
    echo '<tbody>';
    
    foreach ($data['data'] as $row) {
        echo '<tr>';
        foreach ($row as $key => $value) {
            if (is_numeric($value) && !strpos($key, 'เดือน') && !strpos($key, 'ปี')) {
                echo '<td>' . number_format($value, 2) . '</td>';
            } else {
                echo '<td class="left">' . $value . '</td>';
            }
        }
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    // Summary
    echo '<div class="summary">';
    echo '<h3>สรุป</h3>';
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    foreach ($data['summary'] as $key => $value) {
        echo '<tr>';
        echo '<td><strong>' . $key . '</strong></td>';
        if (is_numeric($value)) {
            echo '<td>' . number_format($value, 2) . '</td>';
        } else {
            echo '<td>' . $value . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    echo '</div>';
    
    echo '</body>';
    echo '</html>';
    
    // Log export
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน Summary Electricity ($filename)");
    exit();
}

/**
 * Export to CSV
 */
function exportCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8 Thai
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Title
    fputcsv($output, [$data['title']]);
    fputcsv($output, [$data['period']]);
    fputcsv($output, ['วันที่ส่งออก: ' . date('d/m/Y H:i:s') . ' โดย: ' . $_SESSION['fullname']]);
    fputcsv($output, []);
    
    // Headers
    fputcsv($output, $data['headers']);
    
    // Data
    foreach ($data['data'] as $row) {
        $row_data = [];
        foreach ($row as $value) {
            if (is_numeric($value)) {
                $row_data[] = number_format($value, 2);
            } else {
                $row_data[] = $value;
            }
        }
        fputcsv($output, $row_data);
    }
    
    // Summary
    fputcsv($output, []);
    fputcsv($output, ['สรุป']);
    foreach ($data['summary'] as $key => $value) {
        if (is_numeric($value)) {
            fputcsv($output, [$key, number_format($value, 2)]);
        } else {
            fputcsv($output, [$key, $value]);
        }
    }
    
    fclose($output);
    
    // Log export
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน Summary Electricity ($filename)");
    exit();
}

/**
 * Export to PDF (using mPDF library)
 */
function exportPDF($data, $filename, $report_type) {
    // Check if mPDF library exists
    $mpdf_path = __DIR__ . '/../../vendor/autoload.php';
    
    if (!file_exists($mpdf_path)) {
        // Fallback to HTML if mPDF not installed
        exportHTML($data, $filename);
        return;
    }
    
    require_once $mpdf_path;
    
    // Create PDF content
    $html = '<html>';
    $html .= '<head>';
    $html .= '<meta charset="UTF-8">';
    $html .= '<style>';
    $html .= 'body { font-family: "Garuda", sans-serif; }';
    $html .= 'h2 { color: #333; }';
    $html .= 'h3 { color: #666; }';
    $html .= 'table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }';
    $html .= 'th { background-color: #4CAF50; color: white; padding: 8px; text-align: center; }';
    $html .= 'td { padding: 6px; border: 1px solid #ddd; }';
    $html .= 'tr:nth-child(even) { background-color: #f2f2f2; }';
    $html .= '.summary { background-color: #e7f3ff; padding: 10px; border-radius: 5px; }';
    $html .= '.footer { text-align: center; margin-top: 30px; font-size: 12px; color: #999; }';
    $html .= '.text-right { text-align: right; }';
    $html .= '</style>';
    $html .= '</head>';
    $html .= '<body>';
    
    // Title
    $html .= '<h2>' . $data['title'] . '</h2>';
    $html .= '<h3>' . $data['period'] . '</h3>';
    $html .= '<p>วันที่ส่งออก: ' . date('d/m/Y H:i:s') . ' โดย: ' . $_SESSION['fullname'] . '</p>';
    
    // Data table
    $html .= '<table>';
    $html .= '<thead><tr>';
    foreach ($data['headers'] as $header) {
        $html .= '<th>' . $header . '</th>';
    }
    $html .= '</tr></thead>';
    $html .= '<tbody>';
    
    foreach ($data['data'] as $row) {
        $html .= '<tr>';
        foreach ($row as $key => $value) {
            if (is_numeric($value) && !strpos($key, 'เดือน') && !strpos($key, 'ปี')) {
                $html .= '<td class="text-right">' . number_format($value, 2) . '</td>';
            } else {
                $html .= '<td>' . $value . '</td>';
            }
        }
        $html .= '</tr>';
    }
    
    $html .= '</tbody>';
    $html .= '</table>';
    
    // Summary
    $html .= '<div class="summary">';
    $html .= '<h3>สรุป</h3>';
    $html .= '<table>';
    foreach ($data['summary'] as $key => $value) {
        $html .= '<tr>';
        $html .= '<td><strong>' . $key . '</strong></td>';
        if (is_numeric($value)) {
            $html .= '<td class="text-right">' . number_format($value, 2) . '</td>';
        } else {
            $html .= '<td>' . $value . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';
    $html .= '</div>';
    
    // Footer
    $html .= '<div class="footer">';
    $html .= 'Engineering Utility Monitoring System (EUMS)';
    $html .= '</div>';
    
    $html .= '</body>';
    $html .= '</html>';
    
    // Create PDF
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'default_font' => 'garuda'
    ]);
    
    $mpdf->WriteHTML($html);
    $mpdf->Output($filename . '.pdf', 'D');
    
    // Log export
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน Summary Electricity ($filename)");
    exit();
}

/**
 * Fallback HTML export
 */
function exportHTML($data, $filename) {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.html"');
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>' . $data['title'] . '</title>';
    echo '<style>';
    echo 'body { font-family: "Sarabun", sans-serif; margin: 20px; }';
    echo 'h2 { color: #333; }';
    echo 'table { width: 100%; border-collapse: collapse; }';
    echo 'th { background-color: #4CAF50; color: white; padding: 8px; }';
    echo 'td { padding: 6px; border: 1px solid #ddd; }';
    echo 'tr:nth-child(even) { background-color: #f2f2f2; }';
    echo '.summary { margin-top: 20px; padding: 10px; background-color: #e7f3ff; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<h2>' . $data['title'] . '</h2>';
    echo '<h3>' . $data['period'] . '</h3>';
    echo '<p>วันที่ส่งออก: ' . date('d/m/Y H:i:s') . ' โดย: ' . $_SESSION['fullname'] . '</p>';
    
    echo '<table>';
    echo '<thead><tr>';
    foreach ($data['headers'] as $header) {
        echo '<th>' . $header . '</th>';
    }
    echo '</tr></thead>';
    echo '<tbody>';
    
    foreach ($data['data'] as $row) {
        echo '<tr>';
        foreach ($row as $value) {
            if (is_numeric($value)) {
                echo '<td style="text-align: right;">' . number_format($value, 2) . '</td>';
            } else {
                echo '<td>' . $value . '</td>';
            }
        }
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    echo '<div class="summary">';
    echo '<h3>สรุป</h3>';
    echo '<table>';
    foreach ($data['summary'] as $key => $value) {
        echo '<tr>';
        echo '<td><strong>' . $key . '</strong></td>';
        if (is_numeric($value)) {
            echo '<td style="text-align: right;">' . number_format($value, 2) . '</td>';
        } else {
            echo '<td>' . $value . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    echo '</div>';
    
    echo '</body>';
    echo '</html>';
    
    // Log export
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน Summary Electricity ($filename)");
    exit();
}
?>