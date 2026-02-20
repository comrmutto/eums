<?php
/**
 * Export Report - Energy & Water Module
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
$meter_id = isset($_GET['meter_id']) ? (int)$_GET['meter_id'] : 0;
$meter_type = isset($_GET['meter_type']) ? $_GET['meter_type'] : '';

// Set filename
$filename = "energy_water_report_{$report_type}_" . date('Ymd_His');

// Prepare data based on report type
switch ($report_type) {
    case 'daily':
        $data = getDailyData($db, $start_date, $end_date, $meter_id, $meter_type);
        $filename .= "_daily";
        break;
    case 'monthly':
        $data = getMonthlyData($db, $year);
        $filename .= "_{$year}";
        break;
    case 'meter_detail':
        $data = getMeterDetailData($db, $start_date, $end_date, $meter_id);
        $filename .= "_meter_{$meter_id}";
        break;
    case 'comparison':
        $compare_year = isset($_GET['compare_year']) ? (int)$_GET['compare_year'] : ($year - 1);
        $data = getComparisonData($db, $year, $compare_year);
        $filename .= "_compare_{$year}_vs_{$compare_year}";
        break;
    case 'summary':
        $data = getSummaryData($db, $start_date, $end_date);
        $filename .= "_summary";
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
function getDailyData($db, $start_date, $end_date, $meter_id = 0, $meter_type = '') {
    $sql = "
        SELECT 
            DATE_FORMAT(r.record_date, '%d/%m/%Y') as date,
            m.meter_code,
            m.meter_name,
            CASE 
                WHEN m.meter_type = 'electricity' THEN 'ไฟฟ้า'
                ELSE 'น้ำ'
            END as meter_type_text,
            m.location,
            r.morning_reading,
            r.evening_reading,
            r.usage_amount,
            CASE 
                WHEN m.meter_type = 'electricity' THEN 'kWh'
                ELSE 'm³'
            END as unit,
            r.remarks,
            r.recorded_by,
            DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as created_at
        FROM meter_daily_readings r
        JOIN mc_mdb_water m ON r.meter_id = m.id
        WHERE r.record_date BETWEEN ? AND ?
    ";
    $params = [$start_date, $end_date];
    
    if ($meter_id > 0) {
        $sql .= " AND r.meter_id = ?";
        $params[] = $meter_id;
    }
    
    if (!empty($meter_type)) {
        $sql .= " AND m.meter_type = ?";
        $params[] = $meter_type;
    }
    
    $sql .= " ORDER BY r.record_date DESC, m.meter_type, m.meter_code";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    
    // Calculate statistics
    $total_electricity = 0;
    $total_water = 0;
    $total_records = count($records);
    $meter_stats = [];
    
    foreach ($records as $row) {
        if ($row['meter_type_text'] == 'ไฟฟ้า') {
            $total_electricity += $row['usage_amount'];
        } else {
            $total_water += $row['usage_amount'];
        }
        
        if (!isset($meter_stats[$row['meter_code']])) {
            $meter_stats[$row['meter_code']] = [
                'count' => 0,
                'total' => 0,
                'type' => $row['meter_type_text']
            ];
        }
        $meter_stats[$row['meter_code']]['count']++;
        $meter_stats[$row['meter_code']]['total'] += $row['usage_amount'];
    }
    
    return [
        'title' => 'รายงานการบันทึกค่ามิเตอร์รายวัน',
        'period' => 'ระหว่างวันที่ ' . date('d/m/Y', strtotime($start_date)) . ' ถึง ' . date('d/m/Y', strtotime($end_date)),
        'headers' => ['วันที่', 'รหัสมิเตอร์', 'ชื่อมิเตอร์', 'ประเภท', 'ตำแหน่ง', 'ค่าเช้า', 'ค่าเย็น', 'ปริมาณการใช้', 'หน่วย', 'หมายเหตุ', 'ผู้บันทึก', 'วันที่บันทึก'],
        'data' => $records,
        'summary' => [
            'จำนวนบันทึกทั้งหมด' => $total_records,
            'รวมไฟฟ้า (kWh)' => $total_electricity,
            'รวมน้ำ (m³)' => $total_water,
            'รวมทั้งสิ้น' => $total_electricity + $total_water,
            'จำนวนมิเตอร์ที่ใช้งาน' => count($meter_stats)
        ],
        'meter_stats' => $meter_stats
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
            SUM(CASE WHEN m.meter_type = 'electricity' THEN r.usage_amount ELSE 0 END) as total_electricity,
            SUM(CASE WHEN m.meter_type = 'water' THEN r.usage_amount ELSE 0 END) as total_water,
            AVG(CASE WHEN m.meter_type = 'electricity' THEN r.usage_amount ELSE NULL END) as avg_electricity,
            AVG(CASE WHEN m.meter_type = 'water' THEN r.usage_amount ELSE NULL END) as avg_water,
            MAX(CASE WHEN m.meter_type = 'electricity' THEN r.usage_amount ELSE 0 END) as max_electricity,
            MAX(CASE WHEN m.meter_type = 'water' THEN r.usage_amount ELSE 0 END) as max_water
        FROM meter_daily_readings r
        JOIN mc_mdb_water m ON r.meter_id = m.id
        WHERE YEAR(r.record_date) = ?
        GROUP BY MONTH(r.record_date)
        ORDER BY month
    ");
    $stmt->execute([$year]);
    $records = $stmt->fetchAll();
    
    // Calculate yearly totals
    $total_electricity = 0;
    $total_water = 0;
    $total_records = 0;
    
    foreach ($records as $row) {
        $total_electricity += $row['total_electricity'];
        $total_water += $row['total_water'];
        $total_records += $row['total_records'];
    }
    
    // Format data for display
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 
               'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $formatted_records = [];
    
    foreach ($records as $row) {
        $formatted_records[] = [
            'เดือน' => $months[$row['month'] - 1] . ' ' . ($year + 543),
            'จำนวนวัน' => $row['days_count'],
            'จำนวนบันทึก' => $row['total_records'],
            'ไฟฟ้ารวม (kWh)' => $row['total_electricity'],
            'น้ำรวม (m³)' => $row['total_water'],
            'ไฟฟ้าเฉลี่ย/วัน' => round($row['avg_electricity'], 2),
            'น้ำเฉลี่ย/วัน' => round($row['avg_water'], 2),
            'ไฟฟ้าสูงสุด' => $row['max_electricity'],
            'น้ำสูงสุด' => $row['max_water']
        ];
    }
    
    return [
        'title' => 'รายงานสรุปการใช้ไฟฟ้าและน้ำรายเดือน',
        'period' => 'ปี ' . ($year + 543),
        'headers' => ['เดือน', 'จำนวนวัน', 'จำนวนบันทึก', 'ไฟฟ้ารวม (kWh)', 'น้ำรวม (m³)', 'ไฟฟ้าเฉลี่ย/วัน', 'น้ำเฉลี่ย/วัน', 'ไฟฟ้าสูงสุด', 'น้ำสูงสุด'],
        'data' => $formatted_records,
        'summary' => [
            'รวมไฟฟ้าทั้งปี' => $total_electricity,
            'รวมน้ำทั้งปี' => $total_water,
            'รวมทั้งสิ้น' => $total_electricity + $total_water,
            'รวมบันทึกทั้งปี' => $total_records,
            'จำนวนเดือนที่มีข้อมูล' => count($records)
        ]
    ];
}

/**
 * Get meter detail data
 */
function getMeterDetailData($db, $start_date, $end_date, $meter_id) {
    if ($meter_id <= 0) {
        return [
            'title' => 'รายงานรายละเอียดมิเตอร์',
            'period' => 'ไม่พบข้อมูล',
            'headers' => [],
            'data' => [],
            'summary' => []
        ];
    }
    
    // Get meter info
    $stmt = $db->prepare("SELECT * FROM mc_mdb_water WHERE id = ?");
    $stmt->execute([$meter_id]);
    $meter = $stmt->fetch();
    
    if (!$meter) {
        return [
            'title' => 'รายงานรายละเอียดมิเตอร์',
            'period' => 'ไม่พบข้อมูล',
            'headers' => [],
            'data' => [],
            'summary' => []
        ];
    }
    
    $unit = $meter['meter_type'] == 'electricity' ? 'kWh' : 'm³';
    $type_text = $meter['meter_type'] == 'electricity' ? 'ไฟฟ้า' : 'น้ำ';
    
    // Get readings for this meter
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(record_date, '%d/%m/%Y') as date,
            morning_reading,
            evening_reading,
            usage_amount,
            CASE 
                WHEN usage_amount > (SELECT AVG(usage_amount) * 1.5 FROM meter_daily_readings WHERE meter_id = ?)
                THEN 'สูง'
                WHEN usage_amount < (SELECT AVG(usage_amount) * 0.5 FROM meter_daily_readings WHERE meter_id = ?)
                THEN 'ต่ำ'
                ELSE 'ปกติ'
            END as comparison,
            remarks,
            recorded_by
        FROM meter_daily_readings
        WHERE meter_id = ? AND record_date BETWEEN ? AND ?
        ORDER BY record_date DESC
    ");
    $stmt->execute([$meter_id, $meter_id, $meter_id, $start_date, $end_date]);
    $records = $stmt->fetchAll();
    
    // Calculate statistics
    $total_usage = 0;
    $max_usage = 0;
    $min_usage = PHP_INT_MAX;
    $count = count($records);
    
    foreach ($records as $row) {
        $total_usage += $row['usage_amount'];
        $max_usage = max($max_usage, $row['usage_amount']);
        $min_usage = min($min_usage, $row['usage_amount']);
    }
    
    if ($count == 0) $min_usage = 0;
    
    // Get average for comparison
    $stmt = $db->prepare("SELECT AVG(usage_amount) as avg_usage FROM meter_daily_readings WHERE meter_id = ?");
    $stmt->execute([$meter_id]);
    $avg_usage = $stmt->fetch()['avg_usage'] ?: 0;
    
    return [
        'title' => 'รายงานรายละเอียดมิเตอร์: ' . $meter['meter_name'],
        'period' => 'ระหว่างวันที่ ' . date('d/m/Y', strtotime($start_date)) . ' ถึง ' . date('d/m/Y', strtotime($end_date)),
        'headers' => ['วันที่', 'ค่าเช้า', 'ค่าเย็น', 'ปริมาณการใช้', 'เทียบกับค่าเฉลี่ย', 'หมายเหตุ', 'ผู้บันทึก'],
        'data' => $records,
        'summary' => [
            'รหัสมิเตอร์' => $meter['meter_code'],
            'ชื่อมิเตอร์' => $meter['meter_name'],
            'ประเภท' => $type_text,
            'ตำแหน่ง' => $meter['location'] ?: '-',
            'หน่วย' => $unit,
            'จำนวนบันทึก' => $count,
            'ปริมาณการใช้รวม' => $total_usage,
            'ค่าเฉลี่ย' => round($avg_usage, 2),
            'สูงสุด' => $max_usage,
            'ต่ำสุด' => $min_usage
        ]
    ];
}

/**
 * Get comparison data between two years
 */
function getComparisonData($db, $year1, $year2) {
    // Get monthly data for both years
    $stmt = $db->prepare("
        SELECT 
            MONTH(r.record_date) as month,
            SUM(CASE WHEN m.meter_type = 'electricity' THEN r.usage_amount ELSE 0 END) as electricity,
            SUM(CASE WHEN m.meter_type = 'water' THEN r.usage_amount ELSE 0 END) as water
        FROM meter_daily_readings r
        JOIN mc_mdb_water m ON r.meter_id = m.id
        WHERE YEAR(r.record_date) = ?
        GROUP BY MONTH(r.record_date)
    ");
    
    $stmt->execute([$year1]);
    $data1 = $stmt->fetchAll();
    
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
    $total1_elec = 0;
    $total2_elec = 0;
    $total1_water = 0;
    $total2_water = 0;
    
    for ($m = 1; $m <= 12; $m++) {
        $elec1 = isset($monthly1[$m]) ? $monthly1[$m]['electricity'] : 0;
        $elec2 = isset($monthly2[$m]) ? $monthly2[$m]['electricity'] : 0;
        $water1 = isset($monthly1[$m]) ? $monthly1[$m]['water'] : 0;
        $water2 = isset($monthly2[$m]) ? $monthly2[$m]['water'] : 0;
        
        $elec_diff = $elec1 - $elec2;
        $elec_percent = $elec2 > 0 ? ($elec_diff / $elec2) * 100 : 0;
        $water_diff = $water1 - $water2;
        $water_percent = $water2 > 0 ? ($water_diff / $water2) * 100 : 0;
        
        $formatted_records[] = [
            'เดือน' => $months[$m - 1],
            "ไฟฟ้า {$year1}" => $elec1,
            "ไฟฟ้า {$year2}" => $elec2,
            'ต่างไฟฟ้า' => $elec_diff,
            '% ไฟฟ้า' => round($elec_percent, 2) . '%',
            "น้ำ {$year1}" => $water1,
            "น้ำ {$year2}" => $water2,
            'ต่างน้ำ' => $water_diff,
            '% น้ำ' => round($water_percent, 2) . '%'
        ];
        
        $total1_elec += $elec1;
        $total2_elec += $elec2;
        $total1_water += $water1;
        $total2_water += $water2;
    }
    
    $total_elec_diff = $total1_elec - $total2_elec;
    $total_elec_percent = $total2_elec > 0 ? ($total_elec_diff / $total2_elec) * 100 : 0;
    $total_water_diff = $total1_water - $total2_water;
    $total_water_percent = $total2_water > 0 ? ($total_water_diff / $total2_water) * 100 : 0;
    
    return [
        'title' => 'รายงานเปรียบเทียบการใช้ไฟฟ้าและน้ำ',
        'period' => "เปรียบเทียบระหว่างปี " . ($year1 + 543) . " กับปี " . ($year2 + 543),
        'headers' => ['เดือน', "ไฟฟ้า {$year1}", "ไฟฟ้า {$year2}", 'ต่างไฟฟ้า', '% ไฟฟ้า', "น้ำ {$year1}", "น้ำ {$year2}", 'ต่างน้ำ', '% น้ำ'],
        'data' => $formatted_records,
        'summary' => [
            'รวมไฟฟ้าปี ' . ($year1 + 543) => $total1_elec,
            'รวมไฟฟ้าปี ' . ($year2 + 543) => $total2_elec,
            'ต่างไฟฟ้ารวม' => $total_elec_diff,
            '% ไฟฟ้ารวม' => round($total_elec_percent, 2) . '%',
            'รวมน้ำปี ' . ($year1 + 543) => $total1_water,
            'รวมน้ำปี ' . ($year2 + 543) => $total2_water,
            'ต่างน้ำรวม' => $total_water_diff,
            '% น้ำรวม' => round($total_water_percent, 2) . '%'
        ]
    ];
}

/**
 * Get summary data (daily totals)
 */
function getSummaryData($db, $start_date, $end_date) {
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(r.record_date, '%d/%m/%Y') as date,
            COUNT(DISTINCT r.meter_id) as meter_count,
            SUM(CASE WHEN m.meter_type = 'electricity' THEN r.usage_amount ELSE 0 END) as total_electricity,
            SUM(CASE WHEN m.meter_type = 'water' THEN r.usage_amount ELSE 0 END) as total_water,
            COUNT(r.id) as record_count
        FROM meter_daily_readings r
        JOIN mc_mdb_water m ON r.meter_id = m.id
        WHERE r.record_date BETWEEN ? AND ?
        GROUP BY r.record_date
        ORDER BY r.record_date
    ");
    $stmt->execute([$start_date, $end_date]);
    $records = $stmt->fetchAll();
    
    // Calculate totals
    $total_elec = 0;
    $total_water = 0;
    $total_records = 0;
    $max_elec_day = 0;
    $max_water_day = 0;
    $max_elec_date = '';
    $max_water_date = '';
    
    foreach ($records as $row) {
        $total_elec += $row['total_electricity'];
        $total_water += $row['total_water'];
        $total_records += $row['record_count'];
        
        if ($row['total_electricity'] > $max_elec_day) {
            $max_elec_day = $row['total_electricity'];
            $max_elec_date = $row['date'];
        }
        
        if ($row['total_water'] > $max_water_day) {
            $max_water_day = $row['total_water'];
            $max_water_date = $row['date'];
        }
    }
    
    return [
        'title' => 'รายงานสรุปการใช้ไฟฟ้าและน้ำรายวัน',
        'period' => 'ระหว่างวันที่ ' . date('d/m/Y', strtotime($start_date)) . ' ถึง ' . date('d/m/Y', strtotime($end_date)),
        'headers' => ['วันที่', 'จำนวนมิเตอร์', 'ไฟฟ้ารวม (kWh)', 'น้ำรวม (m³)', 'จำนวนบันทึก'],
        'data' => $records,
        'summary' => [
            'รวมไฟฟ้าทั้งหมด' => $total_elec,
            'รวมน้ำทั้งหมด' => $total_water,
            'รวมทั้งสิ้น' => $total_elec + $total_water,
            'รวมบันทึกทั้งหมด' => $total_records,
            'จำนวนวัน' => count($records),
            'วันใช้ไฟฟ้าสูงสุด' => $max_elec_date . ' (' . $max_elec_day . ' kWh)',
            'วันใช้น้ำสูงสุด' => $max_water_date . ' (' . $max_water_day . ' m³)'
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
    echo '.electricity { color: #ffc107; font-weight: bold; }';
    echo '.water { color: #17a2b8; font-weight: bold; }';
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
                if (isset($row['meter_type_text'])) {
                    if ($row['meter_type_text'] == 'ไฟฟ้า') {
                        $class = 'electricity';
                    } elseif ($row['meter_type_text'] == 'น้ำ') {
                        $class = 'water';
                    }
                }
                
                if (is_numeric($value) && !strpos($key, 'วันที่') && !strpos($key, 'รหัส') && !strpos($key, 'ชื่อ') && !strpos($key, 'ตำแหน่ง')) {
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
            if (is_numeric($value) && !strpos($key, 'วันที่') && !strpos($key, 'วัน')) {
                echo '<td>' . number_format($value, 2) . '</td>';
            } else {
                echo '<td>' . $value . '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
    }
    
    // Meter statistics (for daily report)
    if (isset($data['meter_stats']) && !empty($data['meter_stats'])) {
        echo '<div class="summary">';
        echo '<h3>สรุปแยกรายมิเตอร์</h3>';
        echo '<table border="1" cellpadding="5" cellspacing="0">';
        echo '<tr><th>รหัสมิเตอร์</th><th>ประเภท</th><th>จำนวนครั้ง</th><th>ปริมาณรวม</th></tr>';
        foreach ($data['meter_stats'] as $code => $stat) {
            $class = $stat['type'] == 'ไฟฟ้า' ? 'electricity' : 'water';
            echo '<tr>';
            echo '<td>' . $code . '</td>';
            echo '<td class="' . $class . '">' . $stat['type'] . '</td>';
            echo '<td>' . $stat['count'] . '</td>';
            echo '<td>' . number_format($stat['total'], 2) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
    }
    
    echo '</body>';
    echo '</html>';
    
    // Log export
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน Energy & Water ($filename)");
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
        if (is_numeric($value) && !strpos($key, 'วันที่') && !strpos($key, 'วัน')) {
            fputcsv($output, [$key, number_format($value, 2)]);
        } else {
            fputcsv($output, [$key, $value]);
        }
    }
    
    // Meter statistics (for daily report)
    if (isset($data['meter_stats']) && !empty($data['meter_stats'])) {
        fputcsv($output, []);
        fputcsv($output, ['สรุปแยกรายมิเตอร์']);
        fputcsv($output, ['รหัสมิเตอร์', 'ประเภท', 'จำนวนครั้ง', 'ปริมาณรวม']);
        foreach ($data['meter_stats'] as $code => $stat) {
            fputcsv($output, [$code, $stat['type'], $stat['count'], number_format($stat['total'], 2)]);
        }
    }
    
    fclose($output);
    
    // Log export
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน Energy & Water ($filename)");
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
    $html .= '.electricity { color: #ffc107; font-weight: bold; }';
    $html .= '.water { color: #17a2b8; font-weight: bold; }';
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
                
                if (isset($row['meter_type_text'])) {
                    if ($row['meter_type_text'] == 'ไฟฟ้า') {
                        $style = ' class="electricity"';
                    } elseif ($row['meter_type_text'] == 'น้ำ') {
                        $style = ' class="water"';
                    }
                }
                
                if (is_numeric($value) && !strpos($key, 'วันที่') && !strpos($key, 'รหัส') && !strpos($key, 'ชื่อ') && !strpos($key, 'ตำแหน่ง')) {
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
            if (is_numeric($value) && !strpos($key, 'วันที่') && !strpos($key, 'วัน')) {
                $html .= '<td class="text-right">' . number_format($value, 2) . '</td>';
            } else {
                $html .= '<td class="text-right">' . $value . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';
        $html .= '</div>';
    }
    
    // Meter statistics (for daily report)
    if (isset($data['meter_stats']) && !empty($data['meter_stats'])) {
        $html .= '<div class="summary">';
        $html .= '<h3>สรุปแยกรายมิเตอร์</h3>';
        $html .= '<table>';
        $html .= '<tr><th>รหัสมิเตอร์</th><th>ประเภท</th><th>จำนวนครั้ง</th><th>ปริมาณรวม</th></tr>';
        foreach ($data['meter_stats'] as $code => $stat) {
            $class = $stat['type'] == 'ไฟฟ้า' ? 'electricity' : 'water';
            $html .= '<tr>';
            $html .= '<td>' . $code . '</td>';
            $html .= '<td class="' . $class . '">' . $stat['type'] . '</td>';
            $html .= '<td class="text-right">' . $stat['count'] . '</td>';
            $html .= '<td class="text-right">' . number_format($stat['total'], 2) . '</td>';
            $html .= '</tr>';
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
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน Energy & Water ($filename)");
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
    echo '.electricity { color: #ffc107; font-weight: bold; }';
    echo '.water { color: #17a2b8; font-weight: bold; }';
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
                if (isset($row['meter_type_text'])) {
                    if ($row['meter_type_text'] == 'ไฟฟ้า') {
                        $class = 'electricity';
                    } elseif ($row['meter_type_text'] == 'น้ำ') {
                        $class = 'water';
                    }
                }
                
                if (is_numeric($value) && !strpos($key, 'วันที่') && !strpos($key, 'รหัส') && !strpos($key, 'ชื่อ') && !strpos($key, 'ตำแหน่ง')) {
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
            if (is_numeric($value) && !strpos($key, 'วันที่') && !strpos($key, 'วัน')) {
                echo '<td class="text-right">' . number_format($value, 2) . '</td>';
            } else {
                echo '<td class="text-right">' . $value . '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
    }
    
    if (isset($data['meter_stats']) && !empty($data['meter_stats'])) {
        echo '<div class="summary">';
        echo '<h3>สรุปแยกรายมิเตอร์</h3>';
        echo '<table>';
        echo '<tr><th>รหัสมิเตอร์</th><th>ประเภท</th><th>จำนวนครั้ง</th><th>ปริมาณรวม</th></tr>';
        foreach ($data['meter_stats'] as $code => $stat) {
            $class = $stat['type'] == 'ไฟฟ้า' ? 'electricity' : 'water';
            echo '<tr>';
            echo '<td>' . $code . '</td>';
            echo '<td class="' . $class . '">' . $stat['type'] . '</td>';
            echo '<td class="text-right">' . $stat['count'] . '</td>';
            echo '<td class="text-right">' . number_format($stat['total'], 2) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
    }
    
    echo '</body>';
    echo '</html>';
    
    // Log export
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน Energy & Water ($filename)");
    exit();
}
?>