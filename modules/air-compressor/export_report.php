<?php
/**
 * Export Report - Air Compressor Module
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

// แปลงวันที่จาก DD/MM/YYYY (datepicker) → Y-m-d (สำหรับ query DB)
function convertDateInput($dateStr, $default) {
    if (empty($dateStr)) return $default;
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateStr, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return $dateStr;
}

// Get parameters
$format      = isset($_GET['format'])      ? $_GET['format']      : 'excel';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily';
$machine_id  = isset($_GET['machine_id'])  ? (int)$_GET['machine_id'] : 0;
$month       = isset($_GET['month'])       ? (int)$_GET['month']  : (int)date('m');
$year        = isset($_GET['year'])        ? (int)$_GET['year']   : (int)date('Y');
$start_date  = convertDateInput(isset($_GET['start_date']) ? $_GET['start_date'] : '', date('Y-m-01'));
$end_date    = convertDateInput(isset($_GET['end_date'])   ? $_GET['end_date']   : '', date('Y-m-d'));

// Set filename
$filename = "air_compressor_report_{$report_type}_" . date('Ymd_His');

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
    case 'quality':
        $data = getQualityData($db, $start_date, $end_date, $machine_id);
        $filename .= "_quality";
        break;
    case 'machine_detail':
        $data = getMachineDetailData($db, $start_date, $end_date, $machine_id);
        $filename .= "_machine_{$machine_id}";
        break;
    case 'statistics':
        $data = getStatisticsData($db, $machine_id);
        $filename .= "_statistics";
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
            s.inspection_item,
            s.standard_value,
            s.min_value,
            s.max_value,
            s.unit,
            r.actual_value,
            CASE 
                WHEN s.min_value IS NOT NULL AND s.max_value IS NOT NULL THEN
                    CASE WHEN r.actual_value BETWEEN s.min_value AND s.max_value THEN 'ผ่าน' ELSE 'ไม่ผ่าน' END
                ELSE
                    CASE WHEN ABS(r.actual_value - s.standard_value) <= s.standard_value * 0.1 THEN 'ผ่าน' ELSE 'ไม่ผ่าน' END
            END as status,
            CASE 
                WHEN s.min_value IS NOT NULL AND s.max_value IS NOT NULL THEN
                    CASE 
                        WHEN r.actual_value < s.min_value THEN 'ต่ำกว่าเกณฑ์'
                        WHEN r.actual_value > s.max_value THEN 'สูงกว่าเกณฑ์'
                        ELSE 'อยู่ในเกณฑ์'
                    END
                ELSE
                    CONCAT(
                        ROUND(ABS((r.actual_value - s.standard_value) / s.standard_value * 100), 2),
                        '%'
                    )
            END as deviation,
            r.remarks,
            r.recorded_by,
            DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as created_at
        FROM air_daily_records r
        JOIN mc_air m ON r.machine_id = m.id
        JOIN air_inspection_standards s ON r.inspection_item_id = s.id
        WHERE r.record_date BETWEEN ? AND ?
    ";
    $params = [$start_date, $end_date];
    
    if ($machine_id > 0) {
        $sql .= " AND r.machine_id = ?";
        $params[] = $machine_id;
    }
    
    $sql .= " ORDER BY r.record_date DESC, m.machine_code, s.sort_order";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    
    // Calculate statistics
    $total_records = count($records);
    $pass_count = 0;
    $fail_count = 0;
    $machine_stats = [];
    
    foreach ($records as $row) {
        if ($row['status'] == 'ผ่าน') {
            $pass_count++;
        } else {
            $fail_count++;
        }
        
        if (!isset($machine_stats[$row['machine_code']])) {
            $machine_stats[$row['machine_code']] = [
                'count' => 0,
                'pass' => 0,
                'fail' => 0,
                'name' => $row['machine_name']
            ];
        }
        $machine_stats[$row['machine_code']]['count']++;
        if ($row['status'] == 'ผ่าน') {
            $machine_stats[$row['machine_code']]['pass']++;
        } else {
            $machine_stats[$row['machine_code']]['fail']++;
        }
    }
    
    return [
        'title' => 'รายงานการตรวจสอบ Air Compressor รายวัน',
        'period' => 'ระหว่างวันที่ ' . (DateTime::createFromFormat('Y-m-d', $start_date))->format('d/m/Y') . ' ถึง ' . (DateTime::createFromFormat('Y-m-d', $end_date))->format('d/m/Y'),
        'headers' => ['วันที่', 'รหัสเครื่อง', 'ชื่อเครื่อง', 'หัวข้อตรวจสอบ', 'ค่ามาตรฐาน', 'ค่าต่ำสุด', 'ค่าสูงสุด', 'หน่วย', 'ค่าที่วัดได้', 'สถานะ', 'ค่าเบี่ยงเบน', 'หมายเหตุ', 'ผู้บันทึก', 'วันที่บันทึก'],
        'data' => $records,
        'summary' => [
            'จำนวนบันทึกทั้งหมด' => $total_records,
            'จำนวนผ่าน' => $pass_count,
            'จำนวนไม่ผ่าน' => $fail_count,
            'อัตราผ่าน' => $total_records > 0 ? round(($pass_count / $total_records) * 100, 2) . '%' : '0%',
            'จำนวนเครื่องที่ใช้งาน' => count($machine_stats),
            'จำนวนวัน' => count(array_unique(array_column($records, 'date')))
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
            COUNT(DISTINCT r.machine_id) as machine_count,
            SUM(CASE 
                WHEN (s.min_value IS NOT NULL AND r.actual_value BETWEEN s.min_value AND s.max_value)
                     OR (s.min_value IS NULL AND ABS(r.actual_value - s.standard_value) <= s.standard_value * 0.1)
                THEN 1 ELSE 0 END) as pass_count,
            SUM(r.actual_value) as total_value,
            AVG(r.actual_value) as avg_value,
            MAX(r.actual_value) as max_value,
            MIN(r.actual_value) as min_value
        FROM air_daily_records r
        JOIN air_inspection_standards s ON r.inspection_item_id = s.id
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
    $total_records = 0;
    $total_pass = 0;
    $total_value = 0;
    
    foreach ($records as $row) {
        $total_records += $row['total_records'];
        $total_pass += $row['pass_count'];
        $total_value += $row['total_value'];
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
            'จำนวนเครื่อง' => $row['machine_count'],
            'จำนวนผ่าน' => $row['pass_count'],
            'จำนวนไม่ผ่าน' => $row['total_records'] - $row['pass_count'],
            'อัตราผ่าน' => $row['total_records'] > 0 ? round(($row['pass_count'] / $row['total_records']) * 100, 2) . '%' : '0%',
            'ค่ารวม' => round($row['total_value'], 2),
            'ค่าเฉลี่ย' => round($row['avg_value'], 2),
            'ค่าสูงสุด' => round($row['max_value'], 2),
            'ค่าต่ำสุด' => round($row['min_value'], 2)
        ];
    }
    
    return [
        'title' => 'รายงานสรุปการตรวจสอบ Air Compressor รายเดือน',
        'period' => 'ปี ' . ($year + 543),
        'headers' => ['เดือน', 'จำนวนวัน', 'จำนวนบันทึก', 'จำนวนเครื่อง', 'จำนวนผ่าน', 'จำนวนไม่ผ่าน', 'อัตราผ่าน', 'ค่ารวม', 'ค่าเฉลี่ย', 'ค่าสูงสุด', 'ค่าต่ำสุด'],
        'data' => $formatted_records,
        'summary' => [
            'รวมบันทึกทั้งปี' => $total_records,
            'รวมผ่านทั้งปี' => $total_pass,
            'รวมไม่ผ่านทั้งปี' => $total_records - $total_pass,
            'อัตราผ่านเฉลี่ย' => $total_records > 0 ? round(($total_pass / $total_records) * 100, 2) . '%' : '0%',
            'ค่ารวมทั้งปี' => round($total_value, 2),
            'จำนวนเดือนที่มีข้อมูล' => count($records)
        ]
    ];
}

/**
 * Get quality data (pass/fail by machine and item)
 */
function getQualityData($db, $start_date, $end_date, $machine_id = 0) {
    $sql = "
        SELECT 
            m.machine_code,
            m.machine_name,
            s.inspection_item,
            s.standard_value,
            s.unit,
            COUNT(r.id) as total,
            SUM(CASE 
                WHEN (s.min_value IS NOT NULL AND r.actual_value BETWEEN s.min_value AND s.max_value)
                     OR (s.min_value IS NULL AND ABS(r.actual_value - s.standard_value) <= s.standard_value * 0.1)
                THEN 1 ELSE 0 END) as pass_count,
            SUM(CASE 
                WHEN (s.min_value IS NOT NULL AND (r.actual_value < s.min_value OR r.actual_value > s.max_value))
                     OR (s.min_value IS NULL AND ABS(r.actual_value - s.standard_value) > s.standard_value * 0.1)
                THEN 1 ELSE 0 END) as fail_count
        FROM air_daily_records r
        JOIN mc_air m ON r.machine_id = m.id
        JOIN air_inspection_standards s ON r.inspection_item_id = s.id
        WHERE r.record_date BETWEEN ? AND ?
    ";
    $params = [$start_date, $end_date];
    
    if ($machine_id > 0) {
        $sql .= " AND r.machine_id = ?";
        $params[] = $machine_id;
    }
    
    $sql .= " GROUP BY m.id, s.id ORDER BY m.machine_code, s.sort_order";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    
    // Calculate totals
    $total_pass = 0;
    $total_fail = 0;
    $machine_totals = [];
    
    foreach ($records as $row) {
        $total_pass += $row['pass_count'];
        $total_fail += $row['fail_count'];
        
        if (!isset($machine_totals[$row['machine_code']])) {
            $machine_totals[$row['machine_code']] = [
                'name' => $row['machine_name'],
                'pass' => 0,
                'fail' => 0,
                'total' => 0
            ];
        }
        $machine_totals[$row['machine_code']]['pass'] += $row['pass_count'];
        $machine_totals[$row['machine_code']]['fail'] += $row['fail_count'];
        $machine_totals[$row['machine_code']]['total'] += $row['total'];
    }
    
    return [
        'title' => 'รายงานคุณภาพ Air Compressor',
        'period' => 'ระหว่างวันที่ ' . (DateTime::createFromFormat('Y-m-d', $start_date))->format('d/m/Y') . ' ถึง ' . (DateTime::createFromFormat('Y-m-d', $end_date))->format('d/m/Y'),
        'headers' => ['รหัสเครื่อง', 'ชื่อเครื่อง', 'หัวข้อตรวจสอบ', 'ค่ามาตรฐาน', 'หน่วย', 'ทั้งหมด', 'ผ่าน', 'ไม่ผ่าน', 'อัตราผ่าน'],
        'data' => $records,
        'summary' => [
            'รวมผ่านทั้งหมด' => $total_pass,
            'รวมไม่ผ่านทั้งหมด' => $total_fail,
            'รวมทั้งสิ้น' => $total_pass + $total_fail,
            'อัตราผ่านรวม' => ($total_pass + $total_fail) > 0 ? round(($total_pass / ($total_pass + $total_fail)) * 100, 2) . '%' : '0%',
            'จำนวนเครื่อง' => count($machine_totals),
            'จำนวนหัวข้อ' => count($records)
        ],
        'machine_totals' => $machine_totals
    ];
}

/**
 * Get machine detail data
 */
function getMachineDetailData($db, $start_date, $end_date, $machine_id) {
    if ($machine_id <= 0) {
        return [
            'title' => 'รายงานรายละเอียดเครื่อง Air Compressor',
            'period' => 'ไม่พบข้อมูล',
            'headers' => [],
            'data' => [],
            'summary' => []
        ];
    }
    
    // Get machine info
    $stmt = $db->prepare("SELECT * FROM mc_air WHERE id = ?");
    $stmt->execute([$machine_id]);
    $machine = $stmt->fetch();
    
    if (!$machine) {
        return [
            'title' => 'รายงานรายละเอียดเครื่อง Air Compressor',
            'period' => 'ไม่พบข้อมูล',
            'headers' => [],
            'data' => [],
            'summary' => []
        ];
    }
    
    // Get standards for this machine
    $stmt = $db->prepare("SELECT * FROM air_inspection_standards WHERE machine_id = ? ORDER BY sort_order");
    $stmt->execute([$machine_id]);
    $standards = $stmt->fetchAll();
    
    // Get readings for this machine
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(r.record_date, '%d/%m/%Y') as date,
            s.inspection_item,
            s.standard_value,
            s.unit,
            r.actual_value,
            CASE 
                WHEN s.min_value IS NOT NULL AND s.max_value IS NOT NULL THEN
                    CASE WHEN r.actual_value BETWEEN s.min_value AND s.max_value THEN 'ผ่าน' ELSE 'ไม่ผ่าน' END
                ELSE
                    CASE WHEN ABS(r.actual_value - s.standard_value) <= s.standard_value * 0.1 THEN 'ผ่าน' ELSE 'ไม่ผ่าน' END
            END as status,
            CASE 
                WHEN s.min_value IS NOT NULL AND s.max_value IS NOT NULL THEN
                    CASE 
                        WHEN r.actual_value < s.min_value THEN 'ต่ำกว่า'
                        WHEN r.actual_value > s.max_value THEN 'สูงกว่า'
                        ELSE 'ปกติ'
                    END
                ELSE
                    CONCAT(ROUND(((r.actual_value - s.standard_value) / s.standard_value) * 100, 2), '%')
            END as deviation,
            r.remarks,
            r.recorded_by
        FROM air_daily_records r
        JOIN air_inspection_standards s ON r.inspection_item_id = s.id
        WHERE r.machine_id = ? AND r.record_date BETWEEN ? AND ?
        ORDER BY r.record_date DESC, s.sort_order
    ");
    $stmt->execute([$machine_id, $start_date, $end_date]);
    $records = $stmt->fetchAll();
    
    // Calculate statistics
    $total_records = count($records);
    $pass_count = 0;
    $item_stats = [];
    
    foreach ($records as $row) {
        if ($row['status'] == 'ผ่าน') {
            $pass_count++;
        }
        
        if (!isset($item_stats[$row['inspection_item']])) {
            $item_stats[$row['inspection_item']] = [
                'total' => 0,
                'pass' => 0
            ];
        }
        $item_stats[$row['inspection_item']]['total']++;
        if ($row['status'] == 'ผ่าน') {
            $item_stats[$row['inspection_item']]['pass']++;
        }
    }
    
    return [
        'title' => 'รายงานรายละเอียดเครื่อง Air Compressor: ' . $machine['machine_name'],
        'period' => 'ระหว่างวันที่ ' . (DateTime::createFromFormat('Y-m-d', $start_date))->format('d/m/Y') . ' ถึง ' . (DateTime::createFromFormat('Y-m-d', $end_date))->format('d/m/Y'),
        'headers' => ['วันที่', 'หัวข้อตรวจสอบ', 'ค่ามาตรฐาน', 'หน่วย', 'ค่าที่วัดได้', 'สถานะ', 'ค่าเบี่ยงเบน', 'หมายเหตุ', 'ผู้บันทึก'],
        'data' => $records,
        'summary' => [
            'รหัสเครื่อง' => $machine['machine_code'],
            'ชื่อเครื่อง' => $machine['machine_name'],
            'ยี่ห้อ' => $machine['brand'] ?: '-',
            'รุ่น' => $machine['model'] ?: '-',
            'ความจุ' => $machine['capacity'] . ' ' . $machine['unit'],
            'จำนวนบันทึก' => $total_records,
            'จำนวนผ่าน' => $pass_count,
            'จำนวนไม่ผ่าน' => $total_records - $pass_count,
            'อัตราผ่าน' => $total_records > 0 ? round(($pass_count / $total_records) * 100, 2) . '%' : '0%',
            'จำนวนหัวข้อ' => count($standards)
        ],
        'item_stats' => $item_stats
    ];
}

/**
 * Get statistics data
 */
function getStatisticsData($db, $machine_id = 0) {
    // Overall statistics
    $sql = "
        SELECT 
            COUNT(DISTINCT r.record_date) as total_days,
            COUNT(r.id) as total_records,
            COUNT(DISTINCT r.machine_id) as total_machines,
            SUM(r.actual_value) as total_value,
            AVG(r.actual_value) as avg_value,
            MAX(r.actual_value) as max_value,
            MIN(r.actual_value) as min_value,
            SUM(CASE 
                WHEN (s.min_value IS NOT NULL AND r.actual_value BETWEEN s.min_value AND s.max_value)
                     OR (s.min_value IS NULL AND ABS(r.actual_value - s.standard_value) <= s.standard_value * 0.1)
                THEN 1 ELSE 0 END) as pass_count
        FROM air_daily_records r
        JOIN air_inspection_standards s ON r.inspection_item_id = s.id
    ";
    
    if ($machine_id > 0) {
        $sql .= " WHERE r.machine_id = ?";
        $params = [$machine_id];
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    } else {
        $stmt = $db->query($sql);
    }
    
    $overall = $stmt->fetch();
    
    // Monthly trends
    $sql = "
        SELECT 
            DATE_FORMAT(r.record_date, '%Y-%m') as month,
            COUNT(r.id) as records,
            SUM(r.actual_value) as total,
            AVG(r.actual_value) as avg
        FROM air_daily_records r
    ";
    
    if ($machine_id > 0) {
        $sql .= " WHERE r.machine_id = ?";
        $params = [$machine_id];
        $sql .= " GROUP BY DATE_FORMAT(r.record_date, '%Y-%m') ORDER BY month DESC LIMIT 12";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    } else {
        $sql .= " GROUP BY DATE_FORMAT(r.record_date, '%Y-%m') ORDER BY month DESC LIMIT 12";
        $stmt = $db->query($sql);
    }
    
    $trends = $stmt->fetchAll();
    
    // Machine rankings if all machines
    $machine_rankings = [];
    if ($machine_id == 0) {
        $stmt = $db->query("
            SELECT 
                m.machine_code,
                m.machine_name,
                COUNT(r.id) as records,
                SUM(r.actual_value) as total,
                SUM(CASE 
                    WHEN (s.min_value IS NOT NULL AND r.actual_value BETWEEN s.min_value AND s.max_value)
                         OR (s.min_value IS NULL AND ABS(r.actual_value - s.standard_value) <= s.standard_value * 0.1)
                    THEN 1 ELSE 0 END) as pass_count
            FROM mc_air m
            LEFT JOIN air_daily_records r ON m.id = r.machine_id
            LEFT JOIN air_inspection_standards s ON r.inspection_item_id = s.id
            GROUP BY m.id
            ORDER BY total DESC
        ");
        $machine_rankings = $stmt->fetchAll();
    }
    
    return [
        'title' => 'รายงานสถิติ Air Compressor',
        'period' => $machine_id > 0 ? 'เฉพาะเครื่องที่เลือก' : 'ทั้งหมด',
        'headers' => ['สถิติ', 'ค่า'],
        'data' => [
            ['จำนวนวันที่มีข้อมูล', $overall['total_days']],
            ['จำนวนบันทึกทั้งหมด', $overall['total_records']],
            ['จำนวนเครื่อง', $overall['total_machines']],
            ['ค่ารวมทั้งหมด', round($overall['total_value'], 2)],
            ['ค่าเฉลี่ย', round($overall['avg_value'], 2)],
            ['ค่าสูงสุด', round($overall['max_value'], 2)],
            ['ค่าต่ำสุด', round($overall['min_value'], 2)],
            ['จำนวนผ่าน', $overall['pass_count']],
            ['จำนวนไม่ผ่าน', $overall['total_records'] - $overall['pass_count']],
            ['อัตราผ่าน', $overall['total_records'] > 0 ? round(($overall['pass_count'] / $overall['total_records']) * 100, 2) . '%' : '0%']
        ],
        'trends' => $trends,
        'machine_rankings' => $machine_rankings
    ];
}

/**
 * Export to Excel (.xlsx) using PhpSpreadsheet
 */
function colLetter($n) {
    return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($n);
}

function exportExcel($data, $filename) {
    $autoload = __DIR__ . '/../../vendor/autoload.php';
    if (!file_exists($autoload)) {
        exportCSV($data, $filename);
        return;
    }
    require_once $autoload;

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

    // ---- Style definitions ----
    $stTitle = [
        'font' => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FF1F3864']],
    ];
    $stSub = [
        'font' => ['size' => 10, 'color' => ['argb' => 'FF595959']],
    ];
    $stHeader = [
        'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF2E75B6']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                        'wrapText'   => true],
        'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                         'color' => ['argb' => 'FFB8CCE4']]],
    ];
    $stOdd  = [
        'fill'    => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                      'startColor' => ['argb' => 'FFFFFFFF']],
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                       'color' => ['argb' => 'FFDDDDDD']]],
        'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
    ];
    $stEven = [
        'fill'    => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                      'startColor' => ['argb' => 'FFD9E1F2']],
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                       'color' => ['argb' => 'FFDDDDDD']]],
        'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
    ];
    $stPass    = ['font' => ['bold' => true, 'color' => ['argb' => 'FF006400']]];
    $stFail    = ['font' => ['bold' => true, 'color' => ['argb' => 'FFC00000']]];
    $stSection = [
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                   'startColor' => ['argb' => 'FF375623']],
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                       'color' => ['argb' => 'FFDDDDDD']]],
    ];

    // ================================================================
    // Sheet 1 : รายงานหลัก
    // ================================================================
    $sheet    = $spreadsheet->getActiveSheet()->setTitle('รายงาน');
    $colCount = max(count($data['headers']), 2);
    $lastCol  = colLetter($colCount);

    // Row 1-3 : Title / Period / Export info
    $sheet->mergeCells("A1:{$lastCol}1");
    $sheet->getCell('A1')->setValue($data['title']);
    $sheet->getStyle('A1')->applyFromArray($stTitle);
    $sheet->getRowDimension(1)->setRowHeight(22);

    $sheet->mergeCells("A2:{$lastCol}2");
    $sheet->getCell('A2')->setValue($data['period']);
    $sheet->getStyle('A2')->applyFromArray($stSub);

    $sheet->mergeCells("A3:{$lastCol}3");
    $sheet->getCell('A3')->setValue('วันที่ส่งออก: ' . date('d/m/Y H:i:s') . '  โดย: ' . ($_SESSION['fullname'] ?? ''));
    $sheet->getStyle('A3')->applyFromArray($stSub);
    $sheet->getRowDimension(4)->setRowHeight(6);

    // Row 5 : Column headers
    foreach ($data['headers'] as $i => $h) {
        $sheet->getCell(colLetter($i + 1) . '5')->setValue($h);
    }
    $sheet->getStyle("A5:{$lastCol}5")->applyFromArray($stHeader);
    $sheet->getRowDimension(5)->setRowHeight(22);

    // Rows 6+ : Data
    $row = 6;
    foreach ($data['data'] as $rowData) {
        $values  = array_values((array)$rowData);
        $keys    = array_keys((array)$rowData);
        $isEven  = ($row % 2 === 0);

        foreach ($values as $ci => $val) {
            $coord = colLetter($ci + 1) . $row;
            $cell  = $sheet->getCell($coord);
            if (is_numeric($val) && strpos((string)$val, '%') === false) {
                $cell->setValue((float)$val);
                $sheet->getStyle($coord)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle($coord)->getAlignment()
                      ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            } else {
                $cell->setValue($val);
            }
        }

        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray($isEven ? $stEven : $stOdd);

        // สีผ่าน/ไม่ผ่าน
        $si = array_search('status', $keys);
        if ($si !== false) {
            $sc = colLetter($si + 1) . $row;
            if ($values[$si] === 'ผ่าน')     $sheet->getStyle($sc)->applyFromArray($stPass);
            elseif ($values[$si] === 'ไม่ผ่าน') $sheet->getStyle($sc)->applyFromArray($stFail);
        }

        $sheet->getRowDimension($row)->setRowHeight(18);
        $row++;
    }

    for ($c = 1; $c <= $colCount; $c++) {
        $sheet->getColumnDimension(colLetter($c))->setAutoSize(true);
    }

    // สรุป (ต่อท้าย Sheet 1)
    if (!empty($data['summary'])) {
        $row += 2;
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->getCell("A{$row}")->setValue('สรุป');
        $sheet->getStyle("A{$row}:B{$row}")->applyFromArray($stSection);
        $row++;
        foreach ($data['summary'] as $k => $v) {
            $sheet->getCell("A{$row}")->setValue($k);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $cell = $sheet->getCell("B{$row}");
            if (is_numeric($v) && strpos((string)$v, '%') === false) {
                $cell->setValue((float)$v);
                $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            } else {
                $cell->setValue($v);
            }
            $sheet->getStyle("A{$row}:B{$row}")->getBorders()->getAllBorders()
                  ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $row++;
        }
    }

    // ================================================================
    // Helper: สร้าง sheet ย่อยแบบตาราง
    // ================================================================
    $makeSheet = function($title, $headers, $rows) use ($spreadsheet, &$stHeader, &$stOdd, &$stEven, &$stTitle) {
        $sh  = $spreadsheet->createSheet()->setTitle($title);
        $n   = count($headers);
        $lc  = colLetter($n);
        $sh->mergeCells("A1:{$lc}1");
        $sh->getCell('A1')->setValue($title);
        $sh->getStyle('A1')->applyFromArray($stTitle);
        $sh->getRowDimension(1)->setRowHeight(20);
        foreach ($headers as $i => $h) {
            $sh->getCell(colLetter($i + 1) . '2')->setValue($h);
        }
        $sh->getStyle("A2:{$lc}2")->applyFromArray($stHeader);
        $r = 3;
        foreach ($rows as $rowArr) {
            $sh->fromArray($rowArr, null, "A{$r}");
            $sh->getStyle("A{$r}:{$lc}{$r}")->applyFromArray(($r % 2 === 0) ? $stEven : $stOdd);
            $r++;
        }
        for ($c = 1; $c <= $n; $c++) {
            $sh->getColumnDimension(colLetter($c))->setAutoSize(true);
        }
    };

    // ================================================================
    // Sheet: สรุปรายเครื่อง (daily)
    // ================================================================
    if (!empty($data['machine_stats'])) {
        $rows = [];
        foreach ($data['machine_stats'] as $code => $s) {
            $rate  = $s['count'] > 0 ? round(($s['pass'] / $s['count']) * 100, 2) : 0;
            $rows[] = [$code, $s['name'], $s['count'], $s['pass'], $s['fail'], $rate . '%'];
        }
        $makeSheet('สรุปรายเครื่อง', ['รหัสเครื่อง','ชื่อเครื่อง','จำนวนทั้งหมด','ผ่าน','ไม่ผ่าน','อัตราผ่าน'], $rows);
    }

    // ================================================================
    // Sheet: สรุปรายเครื่อง (quality)
    // ================================================================
    if (!empty($data['machine_totals'])) {
        $rows = [];
        foreach ($data['machine_totals'] as $code => $s) {
            $rate  = $s['total'] > 0 ? round(($s['pass'] / $s['total']) * 100, 2) : 0;
            $rows[] = [$code, $s['name'], $s['pass'], $s['fail'], $s['total'], $rate . '%'];
        }
        $makeSheet('สรุปรายเครื่อง', ['รหัสเครื่อง','ชื่อเครื่อง','ผ่าน','ไม่ผ่าน','รวม','อัตราผ่าน'], $rows);
    }

    // ================================================================
    // Sheet: แนวโน้ม
    // ================================================================
    if (!empty($data['trends'])) {
        $rows = [];
        foreach ($data['trends'] as $t) {
            $rows[] = [$t['month'], $t['records'], round($t['total'], 2), round($t['avg'], 2)];
        }
        $makeSheet('แนวโน้ม', ['เดือน','จำนวนบันทึก','ค่ารวม','ค่าเฉลี่ย'], $rows);
    }

    // ================================================================
    // Sheet: อันดับเครื่อง
    // ================================================================
    if (!empty($data['machine_rankings'])) {
        $rows = [];
        $rank = 1;
        foreach ($data['machine_rankings'] as $m) {
            $rate  = $m['records'] > 0 ? round(($m['pass_count'] / $m['records']) * 100, 2) : 0;
            $rows[] = [$rank++, $m['machine_code'], $m['machine_name'], $m['records'], round($m['total'], 2), $rate . '%'];
        }
        $makeSheet('อันดับเครื่อง', ['อันดับ','รหัสเครื่อง','ชื่อเครื่อง','จำนวนบันทึก','ค่ารวม','อัตราผ่าน'], $rows);
    }

    // ================================================================
    // Output
    // ================================================================
    $spreadsheet->setActiveSheetIndex(0);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    header('Expires: 0');
    header('Pragma: public');

    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');

    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน Air Compressor ($filename)");
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
    
    // Machine statistics (for daily report)
    if (isset($data['machine_stats']) && !empty($data['machine_stats'])) {
        fputcsv($output, []);
        fputcsv($output, ['สรุปแยกรายเครื่อง']);
        fputcsv($output, ['รหัสเครื่อง', 'ชื่อเครื่อง', 'จำนวน', 'ผ่าน', 'ไม่ผ่าน', 'อัตราผ่าน']);
        foreach ($data['machine_stats'] as $code => $stat) {
            $rate = $stat['count'] > 0 ? round(($stat['pass'] / $stat['count']) * 100, 2) : 0;
            fputcsv($output, [$code, $stat['name'], $stat['count'], $stat['pass'], $stat['fail'], $rate . '%']);
        }
    }
    
    // Machine totals (for quality report)
    if (isset($data['machine_totals']) && !empty($data['machine_totals'])) {
        fputcsv($output, []);
        fputcsv($output, ['สรุปแยกรายเครื่อง']);
        fputcsv($output, ['รหัสเครื่อง', 'ชื่อเครื่อง', 'ผ่าน', 'ไม่ผ่าน', 'รวม', 'อัตราผ่าน']);
        foreach ($data['machine_totals'] as $code => $stat) {
            $rate = $stat['total'] > 0 ? round(($stat['pass'] / $stat['total']) * 100, 2) : 0;
            fputcsv($output, [$code, $stat['name'], $stat['pass'], $stat['fail'], $stat['total'], $rate . '%']);
        }
    }
    
    fclose($output);
    
    // Log export
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน Air Compressor ($filename)");
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
    $html .= 'table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 9pt; }';
    $html .= 'th { background-color: #4CAF50; color: white; padding: 4px; text-align: center; }';
    $html .= 'td { padding: 3px; border: 1px solid #ddd; }';
    $html .= 'tr:nth-child(even) { background-color: #f2f2f2; }';
    $html .= '.pass { color: green; font-weight: bold; }';
    $html .= '.fail { color: red; font-weight: bold; }';
    $html .= '.summary { background-color: #e7f3ff; padding: 8px; border-radius: 5px; margin-top: 15px; }';
    $html .= '.footer { text-align: center; margin-top: 20px; font-size: 8pt; color: #999; }';
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
                
                // Check status for coloring
                if (isset($row['status'])) {
                    if ($row['status'] == 'ผ่าน') {
                        $style = ' class="pass"';
                    } elseif ($row['status'] == 'ไม่ผ่าน') {
                        $style = ' class="fail"';
                    }
                }
                
                if (is_numeric($value) && !strpos($key, 'รหัส') && !strpos($key, 'ชื่อ') && !strpos($key, 'หัวข้อ') && !strpos($key, 'หน่วย')) {
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
    
    // Machine statistics (for daily report)
    if (isset($data['machine_stats']) && !empty($data['machine_stats'])) {
        $html .= '<div class="summary">';
        $html .= '<h3>สรุปแยกรายเครื่อง</h3>';
        $html .= '<table>';
        $html .= '<tr><th>รหัสเครื่อง</th><th>ชื่อเครื่อง</th><th>จำนวน</th><th>ผ่าน</th><th>ไม่ผ่าน</th><th>อัตราผ่าน</th></tr>';
        foreach ($data['machine_stats'] as $code => $stat) {
            $rate = $stat['count'] > 0 ? round(($stat['pass'] / $stat['count']) * 100, 2) : 0;
            $html .= '<tr>';
            $html .= '<td>' . $code . '</td>';
            $html .= '<td>' . $stat['name'] . '</td>';
            $html .= '<td class="text-right">' . $stat['count'] . '</td>';
            $html .= '<td class="text-right pass">' . $stat['pass'] . '</td>';
            $html .= '<td class="text-right fail">' . $stat['fail'] . '</td>';
            $html .= '<td class="text-right">' . $rate . '%</td>';
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
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน Air Compressor ($filename)");
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
    echo '.pass { color: green; font-weight: bold; }';
    echo '.fail { color: red; font-weight: bold; }';
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
                
                if (isset($row['status'])) {
                    if ($row['status'] == 'ผ่าน') {
                        $style = ' class="pass"';
                    } elseif ($row['status'] == 'ไม่ผ่าน') {
                        $style = ' class="fail"';
                    }
                }
                
                if (is_numeric($value) && !strpos($key, 'รหัส') && !strpos($key, 'ชื่อ') && !strpos($key, 'หัวข้อ') && !strpos($key, 'หน่วย')) {
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
    
    if (isset($data['machine_stats']) && !empty($data['machine_stats'])) {
        echo '<div class="summary">';
        echo '<h3>สรุปแยกรายเครื่อง</h3>';
        echo '<table>';
        echo '<tr><th>รหัสเครื่อง</th><th>ชื่อเครื่อง</th><th>จำนวน</th><th>ผ่าน</th><th>ไม่ผ่าน</th><th>อัตราผ่าน</th></tr>';
        foreach ($data['machine_stats'] as $code => $stat) {
            $rate = $stat['count'] > 0 ? round(($stat['pass'] / $stat['count']) * 100, 2) : 0;
            echo '<tr>';
            echo '<td>' . $code . '</td>';
            echo '<td>' . $stat['name'] . '</td>';
            echo '<td class="text-right">' . $stat['count'] . '</td>';
            echo '<td class="text-right pass">' . $stat['pass'] . '</td>';
            echo '<td class="text-right fail">' . $stat['fail'] . '</td>';
            echo '<td class="text-right">' . $rate . '%</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';
    }
    
    echo '</body>';
    echo '</html>';
    
    // Log export
    logActivity($_SESSION['user_id'], 'export_report', "ส่งออกรายงาน Air Compressor ($filename)");
    exit();
}
?>