<?php
/**
 * Export Report - Boiler Module
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
$machine_id = isset($_GET['machine_id']) ? (int)$_GET['machine_id'] : 0;
$parameter = isset($_GET['parameter']) ? $_GET['parameter'] : 'all';

// Set filename
$filename = "boiler_report_{$report_type}_" . date('Ymd_His');

// Prepare data based on report type
switch ($report_type) {
    case 'daily':
        $data = getDailyData($db, $start_date, $end_date, $machine_id);
        $filename .= "_daily";
        break;
    case 'monthly':
        $data = getMonthlyData($db, $year, $machine_id);
        $filename .= "_{$year}";
        break;
    case 'efficiency':
        $data = getEfficiencyData($db, $start_date, $end_date, $machine_id);
        $filename .= "_efficiency";
        break;
    case 'parameter':
        $data = getParameterData($db, $start_date, $end_date, $machine_id, $parameter);
        $filename .= "_parameter";
        break;
    case 'machine_detail':
        $data = getMachineDetailData($db, $start_date, $end_date, $machine_id);
        $filename .= "_machine_{$machine_id}";
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
function getDailyData($db, $start_date, $end_date, $machine_id = 0) {
    $sql = "
        SELECT 
            DATE_FORMAT(r.record_date, '%d/%m/%Y') as date,
            m.machine_code,
            m.machine_name,
            r.steam_pressure,
            r.steam_temperature,
            r.feed_water_level,
            r.fuel_consumption,
            r.operating_hours,
            CASE 
                WHEN r.operating_hours > 0 THEN ROUND(r.fuel_consumption / r.operating_hours, 2)
                ELSE 0
            END as fuel_rate,
            CASE 
                WHEN r.steam_pressure BETWEEN 8 AND 12 THEN 'ปกติ'
                WHEN r.steam_pressure < 8 THEN 'ต่ำ'
                ELSE 'สูง'
            END as pressure_status,
            CASE 
                WHEN r.steam_temperature BETWEEN 170 AND 190 THEN 'ปกติ'
                WHEN r.steam_temperature < 170 THEN 'ต่ำ'
                ELSE 'สูง'
            END as temp_status,
            CASE 
                WHEN r.feed_water_level BETWEEN 0.5 AND 1.5 THEN 'ปกติ'
                WHEN r.feed_water_level < 0.5 THEN 'ต่ำ'
                ELSE 'สูง'
            END as water_status,
            r.remarks,
            r.recorded_by,
            DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as created_at
        FROM boiler_daily_records r
        JOIN mc_boiler m ON r.machine_id = m.id
        WHERE r.record_date BETWEEN ? AND ?
    ";
    $params = [$start_date, $end_date];
    
    if ($machine_id > 0) {
        $sql .= " AND r.machine_id = ?";
        $params[] = $machine_id;
    }
    
    $sql .= " ORDER BY r.record_date DESC, m.machine_code";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    
    // Calculate statistics
    $total_fuel = 0;
    $total_hours = 0;
    $total_pressure = 0;
    $total_temp = 0;
    $total_water = 0;
    $count = count($records);
    $machine_stats = [];
    
    foreach ($records as $row) {
        $total_fuel += $row['fuel_consumption'];
        $total_hours += $row['operating_hours'];
        $total_pressure += $row['steam_pressure'];
        $total_temp += $row['steam_temperature'];
        $total_water += $row['feed_water_level'];
        
        if (!isset($machine_stats[$row['machine_code']])) {
            $machine_stats[$row['machine_code']] = [
                'count' => 0,
                'fuel' => 0,
                'hours' => 0
            ];
        }
        $machine_stats[$row['machine_code']]['count']++;
        $machine_stats[$row['machine_code']]['fuel'] += $row['fuel_consumption'];
        $machine_stats[$row['machine_code']]['hours'] += $row['operating_hours'];
    }
    
    return [
        'title' => 'รายงานการทำงานของ Boiler รายวัน',
        'period' => 'ระหว่างวันที่ ' . date('d/m/Y', strtotime($start_date)) . ' ถึง ' . date('d/m/Y', strtotime($end_date)),
        'headers' => ['วันที่', 'รหัสเครื่อง', 'ชื่อเครื่อง', 'แรงดัน (bar)', 'อุณหภูมิ (°C)', 'ระดับน้ำ (m)', 'เชื้อเพลิง (L)', 'ชั่วโมง', 'อัตราสิ้นเปลือง', 'สถานะแรงดัน', 'สถานะอุณหภูมิ', 'สถานะน้ำ', 'หมายเหตุ', 'ผู้บันทึก', 'วันที่บันทึก'],
        'data' => $records,
        'summary' => [
            'จำนวนบันทึกทั้งหมด' => $count,
            'เชื้อเพลิงรวม (L)' => $total_fuel,
            'ชั่วโมงรวม' => $total_hours,
            'แรงดันเฉลี่ย' => $count > 0 ? round($total_pressure / $count, 2) : 0,
            'อุณหภูมิเฉลี่ย' => $count > 0 ? round($total_temp / $count, 1) : 0,
            'ระดับน้ำเฉลี่ย' => $count > 0 ? round($total_water / $count, 2) : 0,
            'อัตราสิ้นเปลืองเฉลี่ย' => $total_hours > 0 ? round($total_fuel / $total_hours, 2) : 0,
            'จำนวนเครื่องที่ใช้งาน' => count($machine_stats)
        ],
        'machine_stats' => $machine_stats
    ];
}

/**
 * Get monthly report data
 */
function getMonthlyData($db, $year, $machine_id = 0) {
    $sql = "
        SELECT 
            MONTH(r.record_date) as month,
            COUNT(DISTINCT r.record_date) as days_count,
            COUNT(r.id) as total_records,
            SUM(r.fuel_consumption) as total_fuel,
            SUM(r.operating_hours) as total_hours,
            AVG(r.steam_pressure) as avg_pressure,
            AVG(r.steam_temperature) as avg_temperature,
            AVG(r.feed_water_level) as avg_water,
            MAX(r.steam_pressure) as max_pressure,
            MAX(r.steam_temperature) as max_temperature,
            MIN(r.steam_pressure) as min_pressure,
            MIN(r.steam_temperature) as min_temperature
        FROM boiler_daily_records r
        WHERE YEAR(r.record_date) = ?
    ";
    $params = [$year];
    
    if ($machine_id > 0) {
        $sql .= " AND r.machine_id = ?";
        $params[] = $machine_id;
    }
    
    $sql .= " GROUP BY MONTH(r.record_date) ORDER BY month";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    
    // Calculate yearly totals
    $total_fuel = 0;
    $total_hours = 0;
    $total_records = 0;
    
    foreach ($records as $row) {
        $total_fuel += $row['total_fuel'];
        $total_hours += $row['total_hours'];
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
            'เชื้อเพลิงรวม (L)' => $row['total_fuel'],
            'ชั่วโมงรวม' => $row['total_hours'],
            'แรงดันเฉลี่ย' => round($row['avg_pressure'], 2),
            'อุณหภูมิเฉลี่ย' => round($row['avg_temperature'], 1),
            'ระดับน้ำเฉลี่ย' => round($row['avg_water'], 2),
            'แรงดันสูงสุด' => $row['max_pressure'],
            'อุณหภูมิสูงสุด' => $row['max_temperature'],
            'แรงดันต่ำสุด' => $row['min_pressure'],
            'อุณหภูมิต่ำสุด' => $row['min_temperature']
        ];
    }
    
    return [
        'title' => 'รายงานสรุปการทำงานของ Boiler รายเดือน',
        'period' => 'ปี ' . ($year + 543),
        'headers' => ['เดือน', 'จำนวนวัน', 'จำนวนบันทึก', 'เชื้อเพลิงรวม (L)', 'ชั่วโมงรวม', 'แรงดันเฉลี่ย', 'อุณหภูมิเฉลี่ย', 'ระดับน้ำเฉลี่ย', 'แรงดันสูงสุด', 'อุณหภูมิสูงสุด', 'แรงดันต่ำสุด', 'อุณหภูมิต่ำสุด'],
        'data' => $formatted_records,
        'summary' => [
            'เชื้อเพลิงรวมทั้งปี' => $total_fuel,
            'ชั่วโมงรวมทั้งปี' => $total_hours,
            'รวมบันทึกทั้งปี' => $total_records,
            'จำนวนเดือนที่มีข้อมูล' => count($records)
        ]
    ];
}

/**
 * Get efficiency data
 */
function getEfficiencyData($db, $start_date, $end_date, $machine_id = 0) {
    $sql = "
        SELECT 
            DATE_FORMAT(r.record_date, '%d/%m/%Y') as date,
            m.machine_code,
            m.machine_name,
            r.steam_pressure,
            r.steam_temperature,
            r.fuel_consumption,
            r.operating_hours,
            CASE 
                WHEN r.fuel_consumption > 0 THEN ROUND(r.steam_pressure / r.fuel_consumption, 3)
                ELSE 0
            END as fuel_efficiency,
            CASE 
                WHEN r.fuel_consumption > 0 AND r.operating_hours > 0 
                THEN ROUND((r.steam_pressure * r.steam_temperature) / (r.fuel_consumption * r.operating_hours), 2)
                ELSE 0
            END as thermal_efficiency,
            CASE 
                WHEN r.operating_hours > 0 THEN ROUND((r.operating_hours / 24) * 100, 1)
                ELSE 0
            END as operating_efficiency
        FROM boiler_daily_records r
        JOIN mc_boiler m ON r.machine_id = m.id
        WHERE r.record_date BETWEEN ? AND ?
    ";
    $params = [$start_date, $end_date];
    
    if ($machine_id > 0) {
        $sql .= " AND r.machine_id = ?";
        $params[] = $machine_id;
    }
    
    $sql .= " ORDER BY r.record_date DESC, m.machine_code";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    
    // Calculate average efficiency
    $total_fuel_eff = 0;
    $total_thermal_eff = 0;
    $total_operating_eff = 0;
    $count = count($records);
    
    foreach ($records as $row) {
        $total_fuel_eff += $row['fuel_efficiency'];
        $total_thermal_eff += $row['thermal_efficiency'];
        $total_operating_eff += $row['operating_efficiency'];
    }
    
    return [
        'title' => 'รายงานประสิทธิภาพ Boiler',
        'period' => 'ระหว่างวันที่ ' . date('d/m/Y', strtotime($start_date)) . ' ถึง ' . date('d/m/Y', strtotime($end_date)),
        'headers' => ['วันที่', 'รหัสเครื่อง', 'ชื่อเครื่อง', 'แรงดัน (bar)', 'เชื้อเพลิง (L)', 'ชั่วโมง', 'ประสิทธิภาพเชื้อเพลิง', 'ประสิทธิภาพความร้อน', 'ประสิทธิภาพการทำงาน'],
        'data' => $records,
        'summary' => [
            'จำนวนบันทึก' => $count,
            'ประสิทธิภาพเชื้อเพลิงเฉลี่ย' => $count > 0 ? round($total_fuel_eff / $count, 3) : 0,
            'ประสิทธิภาพความร้อนเฉลี่ย' => $count > 0 ? round($total_thermal_eff / $count, 2) : 0,
            'ประสิทธิภาพการทำงานเฉลี่ย' => $count > 0 ? round($total_operating_eff / $count, 1) . '%' : '0%',
            'ประสิทธิภาพสูงสุด' => $count > 0 ? max(array_column($records, 'thermal_efficiency')) : 0,
            'ประสิทธิภาพต่ำสุด' => $count > 0 ? min(array_column($records, 'thermal_efficiency')) : 0
        ]
    ];
}

/**
 * Get parameter data with standard comparison
 */
function getParameterData($db, $start_date, $end_date, $machine_id = 0, $parameter = 'all') {
    $sql = "
        SELECT 
            DATE_FORMAT(r.record_date, '%d/%m/%Y') as date,
            m.machine_code,
            m.machine_name,
            r.steam_pressure,
            r.steam_temperature,
            r.feed_water_level,
            CASE 
                WHEN r.steam_pressure BETWEEN 8 AND 12 THEN 'ผ่าน'
                ELSE 'ไม่ผ่าน'
            END as pressure_pass,
            CASE 
                WHEN r.steam_temperature BETWEEN 170 AND 190 THEN 'ผ่าน'
                ELSE 'ไม่ผ่าน'
            END as temp_pass,
            CASE 
                WHEN r.feed_water_level BETWEEN 0.5 AND 1.5 THEN 'ผ่าน'
                ELSE 'ไม่ผ่าน'
            END as water_pass
        FROM boiler_daily_records r
        JOIN mc_boiler m ON r.machine_id = m.id
        WHERE r.record_date BETWEEN ? AND ?
    ";
    $params = [$start_date, $end_date];
    
    if ($machine_id > 0) {
        $sql .= " AND r.machine_id = ?";
        $params[] = $machine_id;
    }
    
    $sql .= " ORDER BY r.record_date DESC, m.machine_code";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    
    // Filter by parameter if needed
    if ($parameter != 'all') {
        $filtered_records = [];
        $headers = ['วันที่', 'รหัสเครื่อง', 'ชื่อเครื่อง'];
        
        switch ($parameter) {
            case 'pressure':
                $headers[] = 'แรงดัน (bar)';
                $headers[] = 'สถานะ';
                $param_field = 'steam_pressure';
                $pass_field = 'pressure_pass';
                $standard = '8-12 bar';
                break;
            case 'temperature':
                $headers[] = 'อุณหภูมิ (°C)';
                $headers[] = 'สถานะ';
                $param_field = 'steam_temperature';
                $pass_field = 'temp_pass';
                $standard = '170-190 °C';
                break;
            case 'water_level':
                $headers[] = 'ระดับน้ำ (m)';
                $headers[] = 'สถานะ';
                $param_field = 'feed_water_level';
                $pass_field = 'water_pass';
                $standard = '0.5-1.5 m';
                break;
        }
        
        foreach ($records as $row) {
            $filtered_records[] = [
                'date' => $row['date'],
                'machine_code' => $row['machine_code'],
                'machine_name' => $row['machine_name'],
                'value' => $row[$param_field],
                'status' => $row[$pass_field]
            ];
        }
        
        $data_records = $filtered_records;
        $data_headers = $headers;
    } else {
        $data_records = $records;
        $data_headers = ['วันที่', 'รหัสเครื่อง', 'ชื่อเครื่อง', 'แรงดัน', 'สถานะแรงดัน', 'อุณหภูมิ', 'สถานะอุณหภูมิ', 'ระดับน้ำ', 'สถานะน้ำ'];
    }
    
    // Calculate pass rates
    $total = count($records);
    $pressure_pass = 0;
    $temp_pass = 0;
    $water_pass = 0;
    
    foreach ($records as $row) {
        if ($row['pressure_pass'] == 'ผ่าน') $pressure_pass++;
        if ($row['temp_pass'] == 'ผ่าน') $temp_pass++;
        if ($row['water_pass'] == 'ผ่าน') $water_pass++;
    }
    
    return [
        'title' => 'รายงานวิเคราะห์พารามิเตอร์ Boiler' . ($parameter != 'all' ? ' - ' . getParameterName($parameter) : ''),
        'period' => 'ระหว่างวันที่ ' . date('d/m/Y', strtotime($start_date)) . ' ถึง ' . date('d/m/Y', strtotime($end_date)),
        'headers' => $data_headers,
        'data' => $data_records,
        'summary' => [
            'จำนวนบันทึก' => $total,
            'แรงดันผ่าน' => $pressure_pass . ' (' . ($total > 0 ? round(($pressure_pass / $total) * 100, 2) : 0) . '%)',
            'อุณหภูมิผ่าน' => $temp_pass . ' (' . ($total > 0 ? round(($temp_pass / $total) * 100, 2) : 0) . '%)',
            'ระดับน้ำผ่าน' => $water_pass . ' (' . ($total > 0 ? round(($water_pass / $total) * 100, 2) : 0) . '%)',
            'ผ่านทั้งหมด' => ($pressure_pass + $temp_pass + $water_pass) / 3,
            'ค่ามาตรฐาน' => $parameter != 'all' ? $standard : 'แรงดัน:8-12, อุณหภูมิ:170-190, น้ำ:0.5-1.5'
        ]
    ];
}

/**
 * Get machine detail data
 */
function getMachineDetailData($db, $start_date, $end_date, $machine_id) {
    if ($machine_id <= 0) {
        return [
            'title' => 'รายงานรายละเอียดเครื่อง Boiler',
            'period' => 'ไม่พบข้อมูล',
            'headers' => [],
            'data' => [],
            'summary' => []
        ];
    }
    
    // Get machine info
    $stmt = $db->prepare("SELECT * FROM mc_boiler WHERE id = ?");
    $stmt->execute([$machine_id]);
    $machine = $stmt->fetch();
    
    if (!$machine) {
        return [
            'title' => 'รายงานรายละเอียดเครื่อง Boiler',
            'period' => 'ไม่พบข้อมูล',
            'headers' => [],
            'data' => [],
            'summary' => []
        ];
    }
    
    // Get readings for this machine
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(record_date, '%d/%m/%Y') as date,
            steam_pressure,
            steam_temperature,
            feed_water_level,
            fuel_consumption,
            operating_hours,
            CASE 
                WHEN steam_pressure BETWEEN 8 AND 12 THEN 'ปกติ'
                ELSE 'ผิดปกติ'
            END as pressure_status,
            CASE 
                WHEN steam_temperature BETWEEN 170 AND 190 THEN 'ปกติ'
                ELSE 'ผิดปกติ'
            END as temp_status,
            CASE 
                WHEN feed_water_level BETWEEN 0.5 AND 1.5 THEN 'ปกติ'
                ELSE 'ผิดปกติ'
            END as water_status,
            remarks,
            recorded_by
        FROM boiler_daily_records
        WHERE machine_id = ? AND record_date BETWEEN ? AND ?
        ORDER BY record_date DESC
    ");
    $stmt->execute([$machine_id, $start_date, $end_date]);
    $records = $stmt->fetchAll();
    
    // Calculate statistics
    $total_fuel = 0;
    $total_hours = 0;
    $count = count($records);
    $pressure_ok = 0;
    $temp_ok = 0;
    $water_ok = 0;
    
    foreach ($records as $row) {
        $total_fuel += $row['fuel_consumption'];
        $total_hours += $row['operating_hours'];
        if ($row['pressure_status'] == 'ปกติ') $pressure_ok++;
        if ($row['temp_status'] == 'ปกติ') $temp_ok++;
        if ($row['water_status'] == 'ปกติ') $water_ok++;
    }
    
    return [
        'title' => 'รายงานรายละเอียดเครื่อง Boiler: ' . $machine['machine_name'],
        'period' => 'ระหว่างวันที่ ' . date('d/m/Y', strtotime($start_date)) . ' ถึง ' . date('d/m/Y', strtotime($end_date)),
        'headers' => ['วันที่', 'แรงดัน', 'อุณหภูมิ', 'ระดับน้ำ', 'เชื้อเพลิง', 'ชั่วโมง', 'สถานะแรงดัน', 'สถานะอุณหภูมิ', 'สถานะน้ำ', 'หมายเหตุ', 'ผู้บันทึก'],
        'data' => $records,
        'summary' => [
            'รหัสเครื่อง' => $machine['machine_code'],
            'ชื่อเครื่อง' => $machine['machine_name'],
            'ยี่ห้อ' => $machine['brand'] ?: '-',
            'รุ่น' => $machine['model'] ?: '-',
            'ความจุ (T/hr)' => $machine['capacity'],
            'แรงดันสูงสุด (bar)' => $machine['pressure_rating'],
            'จำนวนบันทึก' => $count,
            'เชื้อเพลิงรวม' => $total_fuel,
            'ชั่วโมงรวม' => $total_hours,
            'อัตราความปกติ - แรงดัน' => $count > 0 ? round(($pressure_ok / $count) * 100, 2) . '%' : '0%',
            'อัตราความปกติ - อุณหภูมิ' => $count > 0 ? round(($temp_ok / $count) * 100, 2) . '%' : '0%',
            'อัตราความปกติ - ระดับน้ำ' => $count > 0 ? round(($water_ok / $count) * 100, 2) . '%' : '0%'
        ]
    ];
}

/**
 * Get parameter name helper
 */
function getParameterName($param) {
    switch ($param) {
        case 'pressure':
            return 'แรงดันไอน้ำ';
        case 'temperature':
            return 'อุณหภูมิไอน้ำ';
        case 'water_level':
            return 'ระดับน้ำ';
        default:
            return 'ทั้งหมด';
    }
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
    echo '.abnormal { color: red; font-weight: bold; }';
    echo '.normal { color: green; }';
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
                $class = 'left';
                $style = '';
                
                // Check for abnormal values
                if (isset($row['pressure_status']) && $row['pressure_status'] != 'ปกติ' && $key == 'pressure_status') {
                    $style = ' class="abnormal"';
                } elseif (isset($row['temp_status']) && $row['temp_status'] != 'ปกติ' && $key == 'temp_status') {
                    $style = ' class="abnormal"';
                } elseif (isset($row['water_status']) && $row['water_status'] != 'ปกติ' && $key == 'water_status') {
                    $style = ' class="abnormal"';
                } elseif (isset($row['status']) && $row['status'] == 'ไม่ผ่าน') {
                    $style = ' class="abnormal"';
                }
                
                if (is_numeric($value) && !strpos($key, 'รหัส') && !strpos($key, 'ชื่อ') && !strpos($key, 'สถานะ')) {
                    echo '<td class="text-right"' . $style . '>' . number_format($value, 2) . '</td>';
                } else {
                    echo '<td class="left"' . $style . '>' . $value . '</td>';
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
            if (is_numeric($value) && !strpos($key, 'วันที่') && !strpos($key, 'รหัส') && !strpos($key, 'ชื่อ')) {
                echo '<td>' . number_format($value, 2) . '</td>';
            } else {
                echo '<td>' . $value . '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
    }
    
    // Machine statistics (for daily report)
    if (isset($data['machine_stats']) && !empty($data['machine_stats'])) {
        echo '<div class="summary">';
        echo '<h3>สรุปแยกรายเครื่อง</h3>';
        echo '<table border="1" cellpadding="5" cellspacing="0">';
        echo '<tr><th>รหัสเครื่อง</th><th>จำนวนครั้ง</th><th>เชื้อเพลิงรวม (L)</th><th>ชั่วโมงรวม</th></tr>';
        foreach ($data['machine_stats'] as $code => $stat) {
            echo '<tr>';
            echo '<td>' . $code . '</td>';
            echo '<td>' . $stat['count'] . '</td>';
            echo '<td>' . number_format($stat['fuel'], 2) . '</td>';
            echo '<td>' . number_format($stat['hours'], 1) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
    }
    
    echo '</body>';
    echo '</html>';
    
    // Log export
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน Boiler ($filename)");
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
        if (is_numeric($value) && !strpos($key, 'รหัส') && !strpos($key, 'ชื่อ')) {
            fputcsv($output, [$key, number_format($value, 2)]);
        } else {
            fputcsv($output, [$key, $value]);
        }
    }
    
    // Machine statistics (for daily report)
    if (isset($data['machine_stats']) && !empty($data['machine_stats'])) {
        fputcsv($output, []);
        fputcsv($output, ['สรุปแยกรายเครื่อง']);
        fputcsv($output, ['รหัสเครื่อง', 'จำนวนครั้ง', 'เชื้อเพลิงรวม (L)', 'ชั่วโมงรวม']);
        foreach ($data['machine_stats'] as $code => $stat) {
            fputcsv($output, [$code, $stat['count'], number_format($stat['fuel'], 2), number_format($stat['hours'], 1)]);
        }
    }
    
    fclose($output);
    
    // Log export
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน Boiler ($filename)");
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
    $html .= 'table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10pt; }';
    $html .= 'th { background-color: #4CAF50; color: white; padding: 5px; text-align: center; }';
    $html .= 'td { padding: 4px; border: 1px solid #ddd; }';
    $html .= 'tr:nth-child(even) { background-color: #f2f2f2; }';
    $html .= '.abnormal { color: red; font-weight: bold; }';
    $html .= '.normal { color: green; }';
    $html .= '.summary { background-color: #e7f3ff; padding: 8px; border-radius: 5px; margin-top: 15px; }';
    $html .= '.footer { text-align: center; margin-top: 20px; font-size: 9pt; color: #999; }';
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
                $style = '';
                
                // Check for abnormal values
                if (isset($row['pressure_status']) && $row['pressure_status'] != 'ปกติ' && $key == 'pressure_status') {
                    $style = ' class="abnormal"';
                } elseif (isset($row['temp_status']) && $row['temp_status'] != 'ปกติ' && $key == 'temp_status') {
                    $style = ' class="abnormal"';
                } elseif (isset($row['water_status']) && $row['water_status'] != 'ปกติ' && $key == 'water_status') {
                    $style = ' class="abnormal"';
                } elseif (isset($row['status']) && $row['status'] == 'ไม่ผ่าน') {
                    $style = ' class="abnormal"';
                }
                
                if (is_numeric($value) && !strpos($key, 'รหัส') && !strpos($key, 'ชื่อ') && !strpos($key, 'สถานะ')) {
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
            if (is_numeric($value) && !strpos($key, 'รหัส') && !strpos($key, 'ชื่อ')) {
                $html .= '<td class="text-right">' . number_format($value, 2) . '</td>';
            } else {
                $html .= '<td class="text-right">' . $value . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';
        $html .= '</div>';
    }
    
    // Machine statistics (for daily report)
    if (isset($data['machine_stats']) && !empty($data['machine_stats'])) {
        $html .= '<div class="summary">';
        $html .= '<h3>สรุปแยกรายเครื่อง</h3>';
        $html .= '<table>';
        $html .= '<tr><th>รหัสเครื่อง</th><th>จำนวนครั้ง</th><th>เชื้อเพลิงรวม (L)</th><th>ชั่วโมงรวม</th></tr>';
        foreach ($data['machine_stats'] as $code => $stat) {
            $html .= '<tr>';
            $html .= '<td>' . $code . '</td>';
            $html .= '<td class="text-right">' . $stat['count'] . '</td>';
            $html .= '<td class="text-right">' . number_format($stat['fuel'], 2) . '</td>';
            $html .= '<td class="text-right">' . number_format($stat['hours'], 1) . '</td>';
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
        'format' => 'A4-L', // Landscape for better table display
        'default_font' => 'garuda',
        'margin_top' => 15,
        'margin_bottom' => 15,
        'margin_left' => 10,
        'margin_right' => 10
    ]);
    
    $mpdf->WriteHTML($html);
    $mpdf->Output($filename . '.pdf', 'D');
    
    // Log export
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน Boiler ($filename)");
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
    echo '.abnormal { color: red; font-weight: bold; }';
    echo '.normal { color: green; }';
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
                $style = '';
                
                if (isset($row['pressure_status']) && $row['pressure_status'] != 'ปกติ' && $key == 'pressure_status') {
                    $style = ' class="abnormal"';
                } elseif (isset($row['temp_status']) && $row['temp_status'] != 'ปกติ' && $key == 'temp_status') {
                    $style = ' class="abnormal"';
                } elseif (isset($row['water_status']) && $row['water_status'] != 'ปกติ' && $key == 'water_status') {
                    $style = ' class="abnormal"';
                }
                
                if (is_numeric($value) && !strpos($key, 'รหัส') && !strpos($key, 'ชื่อ') && !strpos($key, 'สถานะ')) {
                    echo '<td class="text-right"' . $style . '>' . number_format($value, 2) . '</td>';
                } else {
                    echo '<td class="text-center"' . $style . '>' . $value . '</td>';
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
            if (is_numeric($value) && !strpos($key, 'รหัส') && !strpos($key, 'ชื่อ')) {
                echo '<td class="text-right">' . number_format($value, 2) . '</td>';
            } else {
                echo '<td class="text-right">' . $value . '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
    }
    
    if (isset($data['machine_stats']) && !empty($data['machine_stats'])) {
        echo '<div class="summary">';
        echo '<h3>สรุปแยกรายเครื่อง</h3>';
        echo '<table>';
        echo '<tr><th>รหัสเครื่อง</th><th>จำนวนครั้ง</th><th>เชื้อเพลิงรวม (L)</th><th>ชั่วโมงรวม</th></tr>';
        foreach ($data['machine_stats'] as $code => $stat) {
            echo '<tr>';
            echo '<td>' . $code . '</td>';
            echo '<td class="text-right">' . $stat['count'] . '</td>';
            echo '<td class="text-right">' . number_format($stat['fuel'], 2) . '</td>';
            echo '<td class="text-right">' . number_format($stat['hours'], 1) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
    }
    
    echo '</body>';
    echo '</html>';
    
    // Log export
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน Boiler ($filename)");
    exit();
}
?>