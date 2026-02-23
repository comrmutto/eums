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

// ---------------------------------------------------------
// เพิ่มฟังก์ชันแปลงวันที่ DD/MM/YYYY เป็น YYYY-MM-DD 
function formatDateForDB($date_str) {
    if (strpos($date_str, '/') !== false) {
        $parts = explode('/', $date_str);
        if (count($parts) == 3) {
            $year = (int)$parts[2];
            // เผื่อกรณีมีการส่งมาเป็นปี พ.ศ. ให้แปลงเป็น ค.ศ.
            if ($year > 2500) $year -= 543; 
            return sprintf('%04d-%02d-%02d', $year, $parts[1], $parts[0]);
        }
    }
    return $date_str;
}

// รับค่าวันที่และแปลง format ให้ Database เข้าใจ
$raw_start = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$raw_end = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

$start_date = formatDateForDB($raw_start);
$end_date = formatDateForDB($raw_end);
// ---------------------------------------------------------

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
 * Export to Excel (.xlsx) using PhpSpreadsheet
 */
function exportExcel($data, $filename) {
    // กำหนด Path ของ autoload (เหมือนกับตอนเรียกใช้ mPDF)
    $autoload_path = __DIR__ . '/../../vendor/autoload.php';
    
    if (file_exists($autoload_path)) {
        require_once $autoload_path;
    }
    
    // ตรวจสอบว่ามีคลาส PhpSpreadsheet หรือไม่
    if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        // ถ้าไม่มีไลบรารี ให้กลับไปใช้แบบ HTML จำแลงเป็น xls (Legacy)
        exportExcelLegacy($data, $filename);
        return;
    }

    // สร้าง Spreadsheet object
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    
    // ==========================================
    // SHEET 1: ข้อมูลรายละเอียด (Data)
    // ==========================================
    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('ข้อมูลรายละเอียด');

    // หัวรายงาน
    $sheet1->setCellValue('A1', $data['title']);
    $sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet1->setCellValue('A2', $data['period']);
    $sheet1->setCellValue('A3', 'วันที่ส่งออก: ' . date('d/m/Y H:i:s') . ' โดย: ' . $_SESSION['fullname']);

    // สร้าง Header ของตาราง
    $col = 'A';
    foreach ($data['headers'] as $header) {
        $sheet1->setCellValue($col . '5', $header);
        
        // ตกแต่ง Header
        $sheet1->getStyle($col . '5')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet1->getStyle($col . '5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
               ->getStartColor()->setARGB('FF4CAF50'); // สีเขียว
        $sheet1->getStyle($col . '5')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $col++;
    }

    // ใส่ข้อมูล
    $rowNum = 6;
    if (!empty($data['data'])) {
        foreach ($data['data'] as $row) {
            $col = 'A';
            foreach ($row as $key => $value) {
                // เช็คว่าเป็นตัวเลขและไม่ใช่รหัส/วันที่ เพื่อจัดฟอร์แมตตัวเลข 2 ตำแหน่ง
                if (is_numeric($value) && !strpos($key, 'วันที่') && !strpos($key, 'รหัส') && !strpos($key, 'ชื่อ') && !strpos($key, 'ตำแหน่ง')) {
                    $sheet1->setCellValue($col . $rowNum, $value);
                    $sheet1->getStyle($col . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
                } else {
                    $sheet1->setCellValueExplicit($col . $rowNum, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                $col++;
            }
            $rowNum++;
        }
    } else {
        $sheet1->setCellValue('A6', 'ไม่มีข้อมูล');
    }

    // ปรับขนาดความกว้างคอลัมน์อัตโนมัติ Sheet 1
    $lastCol = $sheet1->getHighestColumn();
    for ($c = 'A'; $c <= $lastCol; $c++) {
        $sheet1->getColumnDimension($c)->setAutoSize(true);
    }

    // ==========================================
    // SHEET 2: สรุปข้อมูลรวม (Summary)
    // ==========================================
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('สรุปข้อมูลรวม');

    $sheet2->setCellValue('A1', 'สรุปข้อมูลการใช้พลังงาน');
    $sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(16);

    $rowNum = 3;
    if (!empty($data['summary'])) {
        foreach ($data['summary'] as $key => $value) {
            $sheet2->setCellValue('A' . $rowNum, $key);
            $sheet2->getStyle('A' . $rowNum)->getFont()->setBold(true);
            
            if (is_numeric($value) && !strpos($key, 'วันที่') && !strpos($key, 'วัน')) {
                $sheet2->setCellValue('B' . $rowNum, $value);
                $sheet2->getStyle('B' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
            } else {
                $sheet2->setCellValueExplicit('B' . $rowNum, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $rowNum++;
        }
    }

    // เพิ่มส่วนสรุปแยกรายมิเตอร์ (ถ้ามี)
    $rowNum += 2;
    if (isset($data['meter_stats']) && !empty($data['meter_stats'])) {
        $sheet2->setCellValue('A' . $rowNum, 'สรุปแยกรายมิเตอร์');
        $sheet2->getStyle('A' . $rowNum)->getFont()->setBold(true)->setSize(14);
        $rowNum++;

        // Header
        $headers = ['รหัสมิเตอร์', 'ประเภท', 'จำนวนครั้ง', 'ปริมาณรวม'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet2->setCellValue($col . $rowNum, $header);
            $sheet2->getStyle($col . $rowNum)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet2->getStyle($col . $rowNum)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                   ->getStartColor()->setARGB('FF17A2B8'); // สีฟ้า (Info)
            $col++;
        }
        $rowNum++;

        // Data
        foreach ($data['meter_stats'] as $code => $stat) {
            $sheet2->setCellValueExplicit('A' . $rowNum, $code, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet2->setCellValueExplicit('B' . $rowNum, $stat['type'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet2->setCellValue('C' . $rowNum, $stat['count']);
            
            $sheet2->setCellValue('D' . $rowNum, $stat['total']);
            $sheet2->getStyle('D' . $rowNum)->getNumberFormat()->setFormatCode('#,##0.00');
            $rowNum++;
        }
    }

    // ปรับขนาดความกว้างคอลัมน์อัตโนมัติ Sheet 2
    $sheet2->getColumnDimension('A')->setAutoSize(true);
    $sheet2->getColumnDimension('B')->setAutoSize(true);
    $sheet2->getColumnDimension('C')->setAutoSize(true);
    $sheet2->getColumnDimension('D')->setAutoSize(true);

    // กำหนดให้ Sheet 1 เป็น Sheet ที่เปิดขึ้นมาตอนแรก
    $spreadsheet->setActiveSheetIndex(0);

    // เคลียร์ Buffer เพื่อป้องกันปัญหาไฟล์เสีย
    if (ob_get_contents()) ob_end_clean();

    // ==========================================
    // ส่งออกไฟล์ .xlsx
    // ==========================================
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    
    // Log activity
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน Energy & Water เป็น Excel ($filename)");
    exit();
}

/**
 * Fallback Excel Export (HTML based .xls)
 * หากเซิร์ฟเวอร์ไม่ได้ติดตั้ง PhpSpreadsheet ระบบจะเรียกใช้ฟังก์ชันนี้แทน
 */
function exportExcelLegacy($data, $filename) {
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
?>