<?php
/**
 * Export Report - LPG Module
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
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Set filename
$filename = "lpg_report_{$report_type}_" . date('Ymd_His');

// Prepare data based on report type
switch ($report_type) {
    case 'daily':
        $data = getDailyData($db, $start_date, $end_date, $item_id);
        $filename .= "_daily";
        break;
    case 'monthly':
        $data = getMonthlyData($db, $year);
        $filename .= "_{$year}";
        break;
    case 'quality':
        $data = getQualityData($db, $start_date, $end_date);
        $filename .= "_quality";
        break;
    case 'ng_analysis':
        $data = getNGAnalysisData($db, $start_date, $end_date, $status);
        $filename .= "_ng_analysis";
        break;
    case 'item_detail':
        $data = getItemDetailData($db, $start_date, $end_date, $item_id);
        $filename .= "_item_{$item_id}";
        break;
    default:
        $data = getDailyData($db, $start_date, $end_date);
        $filename .= "_daily";
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
function getDailyData($db, $start_date, $end_date, $item_id = 0) {
    $sql = "
        SELECT 
            DATE_FORMAT(r.record_date, '%d/%m/%Y') as date,
            i.item_no,
            i.item_name,
            CASE 
                WHEN i.item_type = 'number' THEN 'ตัวเลข'
                ELSE 'OK/NG'
            END as item_type,
            i.standard_value,
            i.unit,
            CASE 
                WHEN i.item_type = 'number' THEN r.number_value
                ELSE r.enum_value
            END as actual_value,
            CASE 
                WHEN i.item_type = 'number' THEN 
                    CASE WHEN ABS(r.number_value - i.standard_value) <= i.standard_value * 0.1 THEN 'OK' ELSE 'NG' END
                ELSE r.enum_value
            END as status,
            r.remarks,
            r.recorded_by,
            DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as created_at
        FROM lpg_daily_records r
        JOIN lpg_inspection_items i ON r.item_id = i.id
        WHERE r.record_date BETWEEN ? AND ?
    ";
    $params = [$start_date, $end_date];
    
    if ($item_id > 0) {
        $sql .= " AND r.item_id = ?";
        $params[] = $item_id;
    }
    
    $sql .= " ORDER BY r.record_date DESC, i.item_no";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    
    // Calculate statistics
    $total_records = count($records);
    $total_ok = 0;
    $total_ng = 0;
    $total_usage = 0;
    
    foreach ($records as $row) {
        if ($row['status'] == 'OK') {
            $total_ok++;
        } else {
            $total_ng++;
        }
        
        if (is_numeric($row['actual_value'])) {
            $total_usage += (float)$row['actual_value'];
        }
    }
    
    return [
        'title' => 'รายงานการตรวจสอบ LPG รายวัน',
        'period' => 'ระหว่างวันที่ ' . date('d/m/Y', strtotime($start_date)) . ' ถึง ' . date('d/m/Y', strtotime($end_date)),
        'headers' => ['วันที่', 'ลำดับ', 'รายการ', 'ประเภท', 'ค่ามาตรฐาน', 'หน่วย', 'ค่าที่วัดได้', 'สถานะ', 'หมายเหตุ', 'ผู้บันทึก', 'วันที่บันทึก'],
        'data' => $records,
        'summary' => [
            'จำนวนรายการทั้งหมด' => $total_records,
            'จำนวน OK' => $total_ok,
            'จำนวน NG' => $total_ng,
            'อัตราผ่าน' => $total_records > 0 ? round(($total_ok / $total_records) * 100, 2) . '%' : '0%',
            'ปริมาณการใช้รวม' => $total_usage,
            'จำนวนวัน' => count(array_unique(array_column($records, 'date')))
        ]
    ];
}

/**
 * Get monthly report data
 */
function getMonthlyData($db, $year) {
    $stmt = $db->prepare("
        SELECT 
            MONTH(r.record_date) as month,
            COUNT(DISTINCT r.record_date) as days_count,
            COUNT(r.id) as total_records,
            SUM(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE 0 END) as total_usage,
            SUM(CASE WHEN i.item_type = 'enum' AND r.enum_value = 'OK' THEN 1 ELSE 0 END) as ok_count,
            SUM(CASE WHEN i.item_type = 'enum' AND r.enum_value = 'NG' THEN 1 ELSE 0 END) as ng_count,
            AVG(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE NULL END) as avg_usage,
            MAX(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE 0 END) as max_usage,
            MIN(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE 0 END) as min_usage
        FROM lpg_daily_records r
        JOIN lpg_inspection_items i ON r.item_id = i.id
        WHERE YEAR(r.record_date) = ?
        GROUP BY MONTH(r.record_date)
        ORDER BY month
    ");
    $stmt->execute([$year]);
    $records = $stmt->fetchAll();
    
    // Calculate yearly totals
    $total_records = 0;
    $total_ok = 0;
    $total_ng = 0;
    $total_usage = 0;
    
    foreach ($records as $row) {
        $total_records += $row['total_records'];
        $total_ok += $row['ok_count'];
        $total_ng += $row['ng_count'];
        $total_usage += $row['total_usage'];
    }
    
    // Format data for display
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 
               'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $formatted_records = [];
    
    foreach ($records as $row) {
        $formatted_records[] = [
            'เดือน' => $months[$row['month'] - 1] . ' ' . ($year + 543),
            'จำนวนวัน' => $row['days_count'],
            'จำนวนรายการ' => $row['total_records'],
            'OK' => $row['ok_count'],
            'NG' => $row['ng_count'],
            'อัตราผ่าน' => $row['ok_count'] + $row['ng_count'] > 0 ? 
                           round(($row['ok_count'] / ($row['ok_count'] + $row['ng_count'])) * 100, 2) . '%' : '0%',
            'ปริมาณการใช้' => $row['total_usage'],
            'ค่าเฉลี่ย' => round($row['avg_usage'], 2),
            'สูงสุด' => $row['max_usage'],
            'ต่ำสุด' => $row['min_usage']
        ];
    }
    
    return [
        'title' => 'รายงานการตรวจสอบ LPG รายเดือน',
        'period' => 'ปี ' . ($year + 543),
        'headers' => ['เดือน', 'จำนวนวัน', 'จำนวนรายการ', 'OK', 'NG', 'อัตราผ่าน', 'ปริมาณการใช้', 'ค่าเฉลี่ย', 'สูงสุด', 'ต่ำสุด'],
        'data' => $formatted_records,
        'summary' => [
            'รวมรายการทั้งปี' => $total_records,
            'รวม OK' => $total_ok,
            'รวม NG' => $total_ng,
            'อัตราผ่านเฉลี่ย' => $total_ok + $total_ng > 0 ? 
                                 round(($total_ok / ($total_ok + $total_ng)) * 100, 2) . '%' : '0%',
            'ปริมาณการใช้รวม' => $total_usage,
            'จำนวนเดือนที่มีข้อมูล' => count($records)
        ]
    ];
}

/**
 * Get quality report data (OK/NG summary by item)
 */
function getQualityData($db, $start_date, $end_date) {
    $stmt = $db->prepare("
        SELECT 
            i.item_no,
            i.item_name,
            i.standard_value,
            COUNT(CASE WHEN r.enum_value = 'OK' THEN 1 END) as ok_count,
            COUNT(CASE WHEN r.enum_value = 'NG' THEN 1 END) as ng_count,
            COUNT(r.id) as total
        FROM lpg_inspection_items i
        LEFT JOIN lpg_daily_records r ON i.id = r.item_id 
            AND r.record_date BETWEEN ? AND ?
            AND i.item_type = 'enum'
        WHERE i.item_type = 'enum'
        GROUP BY i.id, i.item_no, i.item_name, i.standard_value
        ORDER BY i.item_no
    ");
    $stmt->execute([$start_date, $end_date]);
    $records = $stmt->fetchAll();
    
    // Calculate totals
    $total_ok = 0;
    $total_ng = 0;
    
    foreach ($records as $row) {
        $total_ok += $row['ok_count'];
        $total_ng += $row['ng_count'];
    }
    
    $formatted_records = [];
    foreach ($records as $row) {
        $formatted_records[] = [
            'ลำดับ' => $row['item_no'],
            'รายการ' => $row['item_name'],
            'ค่ามาตรฐาน' => $row['standard_value'],
            'จำนวน OK' => $row['ok_count'],
            'จำนวน NG' => $row['ng_count'],
            'รวม' => $row['total'],
            'อัตราผ่าน' => $row['total'] > 0 ? round(($row['ok_count'] / $row['total']) * 100, 2) . '%' : '0%'
        ];
    }
    
    return [
        'title' => 'รายงานคุณภาพ LPG (OK/NG)',
        'period' => 'ระหว่างวันที่ ' . date('d/m/Y', strtotime($start_date)) . ' ถึง ' . date('d/m/Y', strtotime($end_date)),
        'headers' => ['ลำดับ', 'รายการ', 'ค่ามาตรฐาน', 'จำนวน OK', 'จำนวน NG', 'รวม', 'อัตราผ่าน'],
        'data' => $formatted_records,
        'summary' => [
            'รวม OK ทั้งหมด' => $total_ok,
            'รวม NG ทั้งหมด' => $total_ng,
            'รวมทั้งหมด' => $total_ok + $total_ng,
            'อัตราผ่านรวม' => ($total_ok + $total_ng) > 0 ? 
                               round(($total_ok / ($total_ok + $total_ng)) * 100, 2) . '%' : '0%',
            'จำนวนรายการ' => count($records)
        ]
    ];
}

/**
 * Get NG analysis data
 */
function getNGAnalysisData($db, $start_date, $end_date, $status = '') {
    $sql = "
        SELECT 
            DATE_FORMAT(r.record_date, '%d/%m/%Y') as date,
            i.item_no,
            i.item_name,
            i.standard_value,
            r.enum_value,
            r.remarks,
            r.recorded_by
        FROM lpg_daily_records r
        JOIN lpg_inspection_items i ON r.item_id = i.id
        WHERE r.record_date BETWEEN ? AND ?
        AND i.item_type = 'enum'
    ";
    $params = [$start_date, $end_date];
    
    if ($status == 'NG') {
        $sql .= " AND r.enum_value = 'NG'";
    } elseif ($status == 'OK') {
        $sql .= " AND r.enum_value = 'OK'";
    }
    
    $sql .= " ORDER BY r.record_date DESC, i.item_no";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    
    // Count NG by item
    $ng_by_item = [];
    $total_ng = 0;
    
    foreach ($records as $row) {
        if ($row['enum_value'] == 'NG') {
            $total_ng++;
            if (!isset($ng_by_item[$row['item_name']])) {
                $ng_by_item[$row['item_name']] = 0;
            }
            $ng_by_item[$row['item_name']]++;
        }
    }
    
    return [
        'title' => 'รายงานวิเคราะห์ NG - LPG',
        'period' => 'ระหว่างวันที่ ' . date('d/m/Y', strtotime($start_date)) . ' ถึง ' . date('d/m/Y', strtotime($end_date)),
        'headers' => ['วันที่', 'ลำดับ', 'รายการ', 'ค่ามาตรฐาน', 'สถานะ', 'หมายเหตุ', 'ผู้บันทึก'],
        'data' => $records,
        'summary' => [
            'จำนวน NG ทั้งหมด' => $total_ng,
            'จำนวน OK' => count($records) - $total_ng,
            'รวมทั้งหมด' => count($records),
            'อัตรา NG' => count($records) > 0 ? round(($total_ng / count($records)) * 100, 2) . '%' : '0%',
            'รายการที่พบ NG บ่อย' => !empty($ng_by_item) ? 
                                    array_search(max($ng_by_item), $ng_by_item) : '-',
            'จำนวน NG สูงสุดในรายการ' => !empty($ng_by_item) ? max($ng_by_item) : 0
        ],
        'ng_by_item' => $ng_by_item
    ];
}

/**
 * Get item detail data
 */
function getItemDetailData($db, $start_date, $end_date, $item_id) {
    if ($item_id <= 0) {
        return [
            'title' => 'รายงานรายละเอียดรายการ LPG',
            'period' => 'ไม่พบข้อมูล',
            'headers' => [],
            'data' => [],
            'summary' => []
        ];
    }
    
    // Get item info
    $stmt = $db->prepare("SELECT * FROM lpg_inspection_items WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        return [
            'title' => 'รายงานรายละเอียดรายการ LPG',
            'period' => 'ไม่พบข้อมูล',
            'headers' => [],
            'data' => [],
            'summary' => []
        ];
    }
    
    // Get records for this item
    if ($item['item_type'] == 'number') {
        $stmt = $db->prepare("
            SELECT 
                DATE_FORMAT(r.record_date, '%d/%m/%Y') as date,
                r.number_value as value,
                i.standard_value,
                i.unit,
                CASE 
                    WHEN ABS(r.number_value - i.standard_value) <= i.standard_value * 0.1 THEN 'OK'
                    ELSE 'NG'
                END as status,
                r.remarks,
                r.recorded_by
            FROM lpg_daily_records r
            JOIN lpg_inspection_items i ON r.item_id = i.id
            WHERE r.item_id = ? AND r.record_date BETWEEN ? AND ?
            ORDER BY r.record_date DESC
        ");
        $stmt->execute([$item_id, $start_date, $end_date]);
        $records = $stmt->fetchAll();
        
        $headers = ['วันที่', 'ค่าที่วัดได้', 'ค่ามาตรฐาน', 'หน่วย', 'สถานะ', 'หมายเหตุ', 'ผู้บันทึก'];
    } else {
        $stmt = $db->prepare("
            SELECT 
                DATE_FORMAT(r.record_date, '%d/%m/%Y') as date,
                r.enum_value as value,
                i.standard_value,
                '-' as unit,
                r.enum_value as status,
                r.remarks,
                r.recorded_by
            FROM lpg_daily_records r
            JOIN lpg_inspection_items i ON r.item_id = i.id
            WHERE r.item_id = ? AND r.record_date BETWEEN ? AND ?
            ORDER BY r.record_date DESC
        ");
        $stmt->execute([$item_id, $start_date, $end_date]);
        $records = $stmt->fetchAll();
        
        $headers = ['วันที่', 'สถานะ', 'ค่ามาตรฐาน', 'หน่วย', 'สถานะ', 'หมายเหตุ', 'ผู้บันทึก'];
    }
    
    // Calculate statistics
    $total_records = count($records);
    $ok_count = 0;
    $ng_count = 0;
    $total_value = 0;
    
    foreach ($records as $row) {
        if ($row['status'] == 'OK') {
            $ok_count++;
        } else {
            $ng_count++;
        }
        
        if (is_numeric($row['value'])) {
            $total_value += (float)$row['value'];
        }
    }
    
    return [
        'title' => 'รายงานรายละเอียดรายการ: ' . $item['item_name'],
        'period' => 'ระหว่างวันที่ ' . date('d/m/Y', strtotime($start_date)) . ' ถึง ' . date('d/m/Y', strtotime($end_date)),
        'headers' => $headers,
        'data' => $records,
        'summary' => [
            'ชื่อรายการ' => $item['item_name'],
            'ประเภท' => $item['item_type'] == 'number' ? 'ตัวเลข' : 'OK/NG',
            'ค่ามาตรฐาน' => $item['standard_value'] . ($item['unit'] ? ' ' . $item['unit'] : ''),
            'จำนวนบันทึก' => $total_records,
            'จำนวน OK' => $ok_count,
            'จำนวน NG' => $ng_count,
            'อัตราผ่าน' => $total_records > 0 ? round(($ok_count / $total_records) * 100, 2) . '%' : '0%',
            'ปริมาณรวม' => $total_value
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
    echo '.ok { color: green; font-weight: bold; }';
    echo '.ng { color: red; font-weight: bold; }';
    echo '.summary { margin-top: 20px; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    // Title
    echo '<h2>' . $data['title'] . '</h2>';
    echo '<h3>' . $data['period'] . '</h3>';
    echo '<p>วันที่ส่งออก: ' . date('d/m/Y H:i:s') . ' โดย: ' . $_SESSION['fullname'] . '</p>';
    
    // Data table
    if (!empty($data['data'])) {
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
                $class = '';
                if (isset($row['status'])) {
                    if ($row['status'] == 'OK') {
                        $class = 'ok';
                    } elseif ($row['status'] == 'NG') {
                        $class = 'ng';
                    }
                }
                
                if (is_numeric($value) && !strpos($key, 'เดือน') && !strpos($key, 'รายการ') && !strpos($key, 'ชื่อ')) {
                    echo '<td class="' . $class . '">' . number_format($value, 2) . '</td>';
                } else {
                    echo '<td class="left ' . $class . '">' . $value . '</td>';
                }
            }
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>ไม่มีข้อมูล</p>';
    }
    
    // Summary
    if (!empty($data['summary'])) {
        echo '<div class="summary">';
        echo '<h3>สรุป</h3>';
        echo '<table border="1" cellpadding="5" cellspacing="0">';
        foreach ($data['summary'] as $key => $value) {
            echo '<tr>';
            echo '<td><strong>' . $key . '</strong></td>';
            if (is_numeric($value) && !strpos($value, '%')) {
                echo '<td>' . number_format($value, 2) . '</td>';
            } else {
                echo '<td>' . $value . '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
    }
    
    // NG by item (for NG analysis)
    if (isset($data['ng_by_item']) && !empty($data['ng_by_item'])) {
        echo '<div class="summary">';
        echo '<h3>สรุป NG แยกรายการ</h3>';
        echo '<table border="1" cellpadding="5" cellspacing="0">';
        echo '<tr><th>รายการ</th><th>จำนวน NG</th></tr>';
        foreach ($data['ng_by_item'] as $item => $count) {
            echo '<tr><td>' . $item . '</td><td>' . $count . '</td></tr>';
        }
        echo '</table>';
        echo '</div>';
    }
    
    echo '</body>';
    echo '</html>';
    
    // Log export
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน LPG ($filename)");
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
        if (is_numeric($value) && !strpos($value, '%')) {
            fputcsv($output, [$key, number_format($value, 2)]);
        } else {
            fputcsv($output, [$key, $value]);
        }
    }
    
    // NG by item (for NG analysis)
    if (isset($data['ng_by_item']) && !empty($data['ng_by_item'])) {
        fputcsv($output, []);
        fputcsv($output, ['สรุป NG แยกรายการ']);
        fputcsv($output, ['รายการ', 'จำนวน NG']);
        foreach ($data['ng_by_item'] as $item => $count) {
            fputcsv($output, [$item, $count]);
        }
    }
    
    fclose($output);
    
    // Log export
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน LPG ($filename)");
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
    $html .= '.ok { color: green; font-weight: bold; }';
    $html .= '.ng { color: red; font-weight: bold; }';
    $html .= '.summary { background-color: #e7f3ff; padding: 10px; border-radius: 5px; margin-top: 20px; }';
    $html .= '.footer { text-align: center; margin-top: 30px; font-size: 12px; color: #999; }';
    $html .= '.text-right { text-align: right; }';
    $html .= '.text-center { text-align: center; }';
    $html .= '</style>';
    $html .= '</head>';
    $html .= '<body>';
    
    // Title
    $html .= '<h2>' . $data['title'] . '</h2>';
    $html .= '<h3>' . $data['period'] . '</h3>';
    $html .= '<p>วันที่ส่งออก: ' . date('d/m/Y H:i:s') . ' โดย: ' . $_SESSION['fullname'] . '</p>';
    
    // Data table
    if (!empty($data['data'])) {
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
                $class = 'text-right';
                $style = '';
                
                if (isset($row['status'])) {
                    if ($row['status'] == 'OK') {
                        $style = ' class="ok"';
                    } elseif ($row['status'] == 'NG') {
                        $style = ' class="ng"';
                    }
                }
                
                if (is_numeric($value) && !strpos($key, 'เดือน') && !strpos($key, 'รายการ') && !strpos($key, 'ชื่อ')) {
                    $html .= '<td class="text-right"' . $style . '>' . number_format($value, 2) . '</td>';
                } else {
                    $html .= '<td class="text-center"' . $style . '>' . $value . '</td>';
                }
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
    } else {
        $html .= '<p>ไม่มีข้อมูล</p>';
    }
    
    // Summary
    if (!empty($data['summary'])) {
        $html .= '<div class="summary">';
        $html .= '<h3>สรุป</h3>';
        $html .= '<table>';
        foreach ($data['summary'] as $key => $value) {
            $html .= '<tr>';
            $html .= '<td><strong>' . $key . '</strong></td>';
            if (is_numeric($value) && !strpos($value, '%')) {
                $html .= '<td class="text-right">' . number_format($value, 2) . '</td>';
            } else {
                $html .= '<td class="text-right">' . $value . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';
        $html .= '</div>';
    }
    
    // NG by item (for NG analysis)
    if (isset($data['ng_by_item']) && !empty($data['ng_by_item'])) {
        $html .= '<div class="summary">';
        $html .= '<h3>สรุป NG แยกรายการ</h3>';
        $html .= '<table>';
        $html .= '<tr><th>รายการ</th><th>จำนวน NG</th></tr>';
        foreach ($data['ng_by_item'] as $item => $count) {
            $html .= '<tr><td>' . $item . '</td><td class="text-right">' . $count . '</td></tr>';
        }
        $html .= '</table>';
        $html .= '</div>';
    }
    
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
        'default_font' => 'garuda',
        'margin_top' => 20,
        'margin_bottom' => 20,
        'margin_left' => 15,
        'margin_right' => 15
    ]);
    
    $mpdf->WriteHTML($html);
    $mpdf->Output($filename . '.pdf', 'D');
    
    // Log export
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน LPG ($filename)");
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
    echo 'table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }';
    echo 'th { background-color: #4CAF50; color: white; padding: 8px; }';
    echo 'td { padding: 6px; border: 1px solid #ddd; }';
    echo 'tr:nth-child(even) { background-color: #f2f2f2; }';
    echo '.ok { color: green; font-weight: bold; }';
    echo '.ng { color: red; font-weight: bold; }';
    echo '.summary { margin-top: 20px; padding: 10px; background-color: #e7f3ff; border-radius: 5px; }';
    echo '.text-right { text-align: right; }';
    echo '.text-center { text-align: center; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<h2>' . $data['title'] . '</h2>';
    echo '<h3>' . $data['period'] . '</h3>';
    echo '<p>วันที่ส่งออก: ' . date('d/m/Y H:i:s') . ' โดย: ' . $_SESSION['fullname'] . '</p>';
    
    if (!empty($data['data'])) {
        echo '<table>';
        echo '<thead><tr>';
        foreach ($data['headers'] as $header) {
            echo '<th>' . $header . '</th>';
        }
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($data['data'] as $row) {
            echo '<tr>';
            foreach ($row as $key => $value) {
                $class = '';
                if (isset($row['status'])) {
                    if ($row['status'] == 'OK') {
                        $class = 'ok';
                    } elseif ($row['status'] == 'NG') {
                        $class = 'ng';
                    }
                }
                
                if (is_numeric($value) && !strpos($key, 'เดือน') && !strpos($key, 'รายการ') && !strpos($key, 'ชื่อ')) {
                    echo '<td class="text-right ' . $class . '">' . number_format($value, 2) . '</td>';
                } else {
                    echo '<td class="text-center ' . $class . '">' . $value . '</td>';
                }
            }
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>ไม่มีข้อมูล</p>';
    }
    
    if (!empty($data['summary'])) {
        echo '<div class="summary">';
        echo '<h3>สรุป</h3>';
        echo '<table>';
        foreach ($data['summary'] as $key => $value) {
            echo '<tr>';
            echo '<td><strong>' . $key . '</strong></td>';
            if (is_numeric($value) && !strpos($value, '%')) {
                echo '<td class="text-right">' . number_format($value, 2) . '</td>';
            } else {
                echo '<td class="text-right">' . $value . '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
    }
    
    if (isset($data['ng_by_item']) && !empty($data['ng_by_item'])) {
        echo '<div class="summary">';
        echo '<h3>สรุป NG แยกรายการ</h3>';
        echo '<table>';
        echo '<tr><th>รายการ</th><th>จำนวน NG</th></tr>';
        foreach ($data['ng_by_item'] as $item => $count) {
            echo '<tr><td>' . $item . '</td><td class="text-right">' . $count . '</td></tr>';
        }
        echo '</table>';
        echo '</div>';
    }
    
    echo '</body>';
    echo '</html>';
    
    // Log export
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน LPG ($filename)");
    exit();
}
?>