<?php
/**
 * API: Get Notifications
 * Engineering Utility Monitoring System (EUMS)
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Load required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Set header
header('Content-Type: application/json');

try {
    $db = getDB();
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['user_role'];
    
    $notifications = [];
    
    // 1. แจ้งเตือนค่าที่ผิดปกติจาก Air Compressor
    $stmt = $db->query("
        SELECT 
            'air' as module,
            r.id,
            r.record_date,
            m.machine_name,
            s.inspection_item,
            r.actual_value,
            s.standard_value,
            s.unit,
            CASE 
                WHEN s.min_value IS NOT NULL AND s.max_value IS NOT NULL THEN
                    CONCAT('ค่า ', s.inspection_item, ' ของเครื่อง ', m.machine_name, 
                           ' วันที่ ', DATE_FORMAT(r.record_date, '%d/%m/%Y'), 
                           ' = ', r.actual_value, ' ', s.unit,
                           ' (ค่ามาตรฐาน ', s.min_value, '-', s.max_value, ' ', s.unit, ')')
                ELSE
                    CONCAT('ค่า ', s.inspection_item, ' ของเครื่อง ', m.machine_name,
                           ' วันที่ ', DATE_FORMAT(r.record_date, '%d/%m/%Y'),
                           ' = ', r.actual_value, ' ', s.unit,
                           ' (ค่ามาตรฐาน ', s.standard_value, ' ', s.unit, 
                           ' เบี่ยงเบน ', ROUND(ABS((r.actual_value - s.standard_value) / s.standard_value * 100), 2), '%)')
            END as message,
            CASE 
                WHEN s.min_value IS NOT NULL AND s.max_value IS NOT NULL THEN
                    CASE WHEN r.actual_value < s.min_value THEN 'ต่ำกว่าเกณฑ์'
                         WHEN r.actual_value > s.max_value THEN 'สูงกว่าเกณฑ์'
                         ELSE 'ปกติ' END
                ELSE
                    CASE WHEN ABS(r.actual_value - s.standard_value) <= s.standard_value * 0.1 THEN 'ปกติ'
                         ELSE 'ผิดปกติ' END
            END as status,
            '/eums/modules/air-compressor/view.php?id=' as link,
            r.created_at
        FROM air_daily_records r
        JOIN mc_air m ON r.machine_id = m.id
        JOIN air_inspection_standards s ON r.inspection_item_id = s.id
        WHERE (s.min_value IS NOT NULL AND (r.actual_value < s.min_value OR r.actual_value > s.max_value))
           OR (s.min_value IS NULL AND ABS(r.actual_value - s.standard_value) > s.standard_value * 0.1)
        ORDER BY r.record_date DESC
        LIMIT 10
    ");
    $airAlerts = $stmt->fetchAll();
    
    foreach ($airAlerts as $alert) {
        $notifications[] = [
            'id' => 'air_' . $alert['id'],
            'module' => 'air',
            'type' => 'danger',
            'icon' => 'exclamation-triangle',
            'message' => $alert['message'],
            'link' => $alert['link'] . $alert['id'],
            'time' => $alert['created_at'],
            'status' => $alert['status']
        ];
    }
    
    // 2. แจ้งเตือน NG จาก LPG
    $stmt = $db->query("
        SELECT 
            'lpg' as module,
            r.id,
            r.record_date,
            i.item_name,
            r.enum_value,
            i.standard_value,
            CONCAT('รายการ ', i.item_name, ' วันที่ ', DATE_FORMAT(r.record_date, '%d/%m/%Y'),
                   ' = ', r.enum_value, ' (ค่ามาตรฐาน ', i.standard_value, ')') as message,
            '/eums/modules/lpg/view.php?date=' as link,
            r.created_at
        FROM lpg_daily_records r
        JOIN lpg_inspection_items i ON r.item_id = i.id
        WHERE i.item_type = 'enum' AND r.enum_value = 'NG'
        ORDER BY r.record_date DESC
        LIMIT 10
    ");
    $lpgAlerts = $stmt->fetchAll();
    
    foreach ($lpgAlerts as $alert) {
        $notifications[] = [
            'id' => 'lpg_' . $alert['id'],
            'module' => 'lpg',
            'type' => 'warning',
            'icon' => 'times-circle',
            'message' => $alert['message'],
            'link' => $alert['link'] . date('Y-m-d', strtotime($alert['record_date'])),
            'time' => $alert['created_at'],
            'status' => 'NG'
        ];
    }
    
    // 3. แจ้งเตือนค่า Boiler ที่ผิดปกติ
    $stmt = $db->query("
        SELECT 
            'boiler' as module,
            r.id,
            r.record_date,
            m.machine_name,
            r.steam_pressure,
            r.steam_temperature,
            r.feed_water_level,
            CONCAT('เครื่อง ', m.machine_name, ' วันที่ ', DATE_FORMAT(r.record_date, '%d/%m/%Y'),
                   CASE 
                       WHEN r.steam_pressure < 8 OR r.steam_pressure > 12 
                            THEN CONCAT(' แรงดัน=', r.steam_pressure, ' bar')
                       WHEN r.steam_temperature < 170 OR r.steam_temperature > 190 
                            THEN CONCAT(' อุณหภูมิ=', r.steam_temperature, ' °C')
                       WHEN r.feed_water_level < 0.5 OR r.feed_water_level > 1.5 
                            THEN CONCAT(' ระดับน้ำ=', r.feed_water_level, ' m')
                       ELSE '' END) as message,
            '/eums/modules/boiler/view.php?id=' as link,
            r.created_at
        FROM boiler_daily_records r
        JOIN mc_boiler m ON r.machine_id = m.id
        WHERE (r.steam_pressure < 8 OR r.steam_pressure > 12)
           OR (r.steam_temperature < 170 OR r.steam_temperature > 190)
           OR (r.feed_water_level < 0.5 OR r.feed_water_level > 1.5)
        ORDER BY r.record_date DESC
        LIMIT 10
    ");
    $boilerAlerts = $stmt->fetchAll();
    
    foreach ($boilerAlerts as $alert) {
        $notifications[] = [
            'id' => 'boiler_' . $alert['id'],
            'module' => 'boiler',
            'type' => 'danger',
            'icon' => 'thermometer-half',
            'message' => $alert['message'],
            'link' => $alert['link'] . $alert['id'],
            'time' => $alert['created_at'],
            'status' => 'ผิดปกติ'
        ];
    }
    
    // 4. แจ้งเตือนมิเตอร์ที่ไม่ได้บันทึก (เกิน 3 วัน)
    $stmt = $db->query("
        SELECT 
            'meter' as module,
            m.id,
            m.meter_code,
            m.meter_name,
            m.meter_type,
            MAX(r.record_date) as last_record,
            CONCAT('มิเตอร์ ', m.meter_code, ' - ', m.meter_name,
                   ' ไม่ได้บันทึกค่า ', 
                   DATEDIFF(CURDATE(), MAX(r.record_date)), ' วันแล้ว') as message,
            '/eums/modules/energy-water/meters.php' as link,
            NOW() as created_at
        FROM mc_mdb_water m
        LEFT JOIN meter_daily_readings r ON m.id = r.meter_id
        WHERE m.status = 1
        GROUP BY m.id
        HAVING last_record IS NULL OR DATEDIFF(CURDATE(), last_record) > 3
        ORDER BY last_record ASC
        LIMIT 5
    ");
    $meterAlerts = $stmt->fetchAll();
    
    foreach ($meterAlerts as $alert) {
        $days = $alert['last_record'] ? (int)((strtotime(date('Y-m-d')) - strtotime($alert['last_record'])) / 86400) : 'ไม่เคย';
        $notifications[] = [
            'id' => 'meter_' . $alert['id'],
            'module' => 'energy',
            'type' => 'warning',
            'icon' => 'clock',
            'message' => $alert['message'],
            'link' => $alert['link'],
            'time' => $alert['created_at'],
            'status' => 'ค้างบันทึก'
        ];
    }
    
    // 5. แจ้งเตือนเอกสารใกล้หมดอายุ (ถ้ามี)
    $stmt = $db->query("
        SELECT 
            'document' as module,
            d.id,
            d.doc_no,
            d.module_type,
            d.start_date,
            CONCAT('เอกสาร ', d.doc_no, ' (', 
                   CASE d.module_type
                       WHEN 'air' THEN 'Air Compressor'
                       WHEN 'energy_water' THEN 'Energy & Water'
                       WHEN 'lpg' THEN 'LPG'
                       WHEN 'boiler' THEN 'Boiler'
                       WHEN 'summary' THEN 'Summary Electricity'
                   END, ') เริ่มใช้ ', DATE_FORMAT(d.start_date, '%d/%m/%Y')) as message,
            '/eums/settings/documents.php' as link,
            d.created_at
        FROM documents d
        WHERE d.start_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
          AND d.start_date >= CURDATE()
        ORDER BY d.start_date
        LIMIT 5
    ");
    $docAlerts = $stmt->fetchAll();
    
    foreach ($docAlerts as $alert) {
        $notifications[] = [
            'id' => 'doc_' . $alert['id'],
            'module' => 'document',
            'type' => 'info',
            'icon' => 'file-alt',
            'message' => $alert['message'],
            'link' => $alert['link'],
            'time' => $alert['created_at'],
            'status' => 'กำลังจะเริ่มใช้'
        ];
    }
    
    // 6. แจ้งเตือนระบบ (สำหรับ admin)
    if ($userRole === 'admin') {
        // ตรวจสอบพื้นที่ว่างใน server
        $backupDir = __DIR__ . '/../backups/';
        if (file_exists($backupDir)) {
            $totalSize = 0;
            $files = glob($backupDir . '*');
            foreach ($files as $file) {
                $totalSize += filesize($file);
            }
            
            // ถ้าพื้นที่เกิน 500MB
            if ($totalSize > 500 * 1024 * 1024) {
                $notifications[] = [
                    'id' => 'system_space',
                    'module' => 'system',
                    'type' => 'warning',
                    'icon' => 'hdd',
                    'message' => 'พื้นที่จัดเก็บไฟล์สำรองใกล้เต็ม (' . formatBytes($totalSize) . ')',
                    'link' => '/eums/settings/backup.php',
                    'time' => date('Y-m-d H:i:s'),
                    'status' => 'warning'
                ];
            }
        }
        
        // ตรวจสอบ error logs
        $logDir = __DIR__ . '/../logs/';
        if (file_exists($logDir)) {
            $errorLogs = glob($logDir . 'error_log_*.log');
            if (count($errorLogs) > 10) {
                $notifications[] = [
                    'id' => 'system_logs',
                    'module' => 'system',
                    'type' => 'info',
                    'icon' => 'exclamation-circle',
                    'message' => 'มีไฟล์ error log จำนวนมาก กรุณาตรวจสอบ',
                    'link' => '/eums/settings/backup.php',
                    'time' => date('Y-m-d H:i:s'),
                    'status' => 'info'
                ];
            }
        }
    }
    
    // 7. แจ้งเตือนสำหรับผู้ปฏิบัติงาน (งานที่ต้องทำ)
    if ($userRole === 'operator' || $userRole === 'admin') {
        // ตรวจสอบวันที่ไม่ได้บันทึก
        $today = date('Y-m-d');
        
        // ตรวจสอบ Air Compressor
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT machine_id) as count
            FROM mc_air
            WHERE status = 1
        ");
        $stmt->execute();
        $totalAirMachines = $stmt->fetch()['count'];
        
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT machine_id) as count
            FROM air_daily_records
            WHERE record_date = ?
        ");
        $stmt->execute([$today]);
        $airRecorded = $stmt->fetch()['count'];
        
        if ($airRecorded < $totalAirMachines) {
            $notifications[] = [
                'id' => 'task_air',
                'module' => 'task',
                'type' => 'info',
                'icon' => 'tasks',
                'message' => "บันทึก Air Compressor วันนี้ $airRecorded/$totalAirMachines เครื่อง",
                'link' => '/eums/modules/air-compressor/index.php',
                'time' => date('Y-m-d H:i:s'),
                'status' => 'pending'
            ];
        }
        
        // ตรวจสอบ Energy & Water
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT meter_id) as count
            FROM meter_daily_readings
            WHERE record_date = ?
        ");
        $stmt->execute([$today]);
        $meterRecorded = $stmt->fetch()['count'];
        
        if ($meterRecorded < 5) { // สมมติว่าควรบันทึกอย่างน้อย 5 มิเตอร์
            $notifications[] = [
                'id' => 'task_energy',
                'module' => 'task',
                'type' => 'info',
                'icon' => 'tasks',
                'message' => "บันทึก Energy & Water วันนี้ $meterRecorded รายการ",
                'link' => '/eums/modules/energy-water/index.php',
                'time' => date('Y-m-d H:i:s'),
                'status' => 'pending'
            ];
        }
    }
    
    // เรียงลำดับตามเวลา (ใหม่สุดขึ้นก่อน)
    usort($notifications, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });
    
    // จำกัดจำนวนไม่เกิน 20 รายการ
    $notifications = array_slice($notifications, 0, 20);
    
    // นับจำนวนแจ้งเตือนแยกตามประเภท
    $counts = [
        'danger' => 0,
        'warning' => 0,
        'info' => 0,
        'success' => 0,
        'total' => count($notifications)
    ];
    
    foreach ($notifications as $notif) {
        $counts[$notif['type']] = ($counts[$notif['type']] ?? 0) + 1;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $notifications,
        'counts' => $counts,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Error in get-notifications.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>