<?php
/**
 * Boiler Module - Process Add Record
 * Engineering Utility Monitoring System (EUMS)
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
        exit();
    } else {
        header('Location: /eums/login.php');
        exit();
    }
}

// Load required files
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Set header for JSON response if AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json');
}

try {
    $db = getDB();
    
    // Get POST data
    $doc_id = isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0;
    $machine_id = isset($_POST['machine_id']) ? (int)$_POST['machine_id'] : 0;
    $record_date = isset($_POST['record_date']) ? $_POST['record_date'] : '';
    $steam_pressure = isset($_POST['steam_pressure']) ? (float)$_POST['steam_pressure'] : 0;
    $steam_temperature = isset($_POST['steam_temperature']) ? (float)$_POST['steam_temperature'] : 0;
    $feed_water_level = isset($_POST['feed_water_level']) ? (float)$_POST['feed_water_level'] : 0;
    $fuel_consumption = isset($_POST['fuel_consumption']) ? (float)$_POST['fuel_consumption'] : 0;
    $operating_hours = isset($_POST['operating_hours']) ? (float)$_POST['operating_hours'] : 0;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    // Validate required fields
    if (!$machine_id) {
        throw new Exception('กรุณาเลือกเครื่อง Boiler');
    }
    
    if (empty($record_date)) {
        throw new Exception('กรุณาระบุวันที่บันทึก');
    }
    
    if ($steam_pressure <= 0) {
        throw new Exception('กรุณากรอกแรงดันไอน้ำ');
    }
    
    if ($steam_temperature <= 0) {
        throw new Exception('กรุณากรอกอุณหภูมิไอน้ำ');
    }
    
    if ($feed_water_level <= 0) {
        throw new Exception('กรุณากรอกระดับน้ำในหม้อ');
    }
    
    if ($fuel_consumption < 0) {
        throw new Exception('กรุณากรอกปริมาณเชื้อเพลิง');
    }
    
    if ($operating_hours < 0) {
        throw new Exception('กรุณากรอกชั่วโมงการทำงาน');
    }
    
    // Validate date
    if (!validateDate($record_date)) {
        throw new Exception('รูปแบบวันที่ไม่ถูกต้อง');
    }
    
    // Check if date is in future
    if ($record_date > date('Y-m-d')) {
        throw new Exception('ไม่สามารถบันทึกข้อมูลในอนาคตได้');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Check if machine exists and is active
    $stmt = $db->prepare("SELECT * FROM mc_boiler WHERE id = ? AND status = 1");
    $stmt->execute([$machine_id]);
    $machine = $stmt->fetch();
    
    if (!$machine) {
        throw new Exception('ไม่พบเครื่อง Boiler หรือเครื่องไม่ได้เปิดใช้งาน');
    }
    
    // Check for duplicate record on same date
    $stmt = $db->prepare("
        SELECT id FROM boiler_daily_records 
        WHERE machine_id = ? AND record_date = ?
    ");
    $stmt->execute([$machine_id, $record_date]);
    
    if ($stmt->fetch()) {
        throw new Exception('มีบันทึกข้อมูลสำหรับเครื่องนี้ในวันที่นี้แล้ว');
    }
    
    // Check or create document
    if (!$doc_id) {
        $dateObj = new DateTime($record_date);
        $month = $dateObj->format('m');
        $year = $dateObj->format('Y');
        
        // Check if document exists for this month
        $stmt = $db->prepare("
            SELECT id FROM documents 
            WHERE module_type = 'boiler' 
            AND MONTH(start_date) = ? 
            AND YEAR(start_date) = ?
        ");
        $stmt->execute([$month, $year]);
        $existingDoc = $stmt->fetch();
        
        if ($existingDoc) {
            $doc_id = $existingDoc['id'];
        } else {
            // Create new document
            $doc_no = 'BLR-' . ($year + 543) . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
            $start_date = $year . '-' . $month . '-01';
            
            $stmt = $db->prepare("
                INSERT INTO documents (doc_no, module_type, start_date, rev_no, created_at)
                VALUES (?, 'boiler', ?, '00', NOW())
            ");
            $stmt->execute([$doc_no, $start_date]);
            $doc_id = $db->lastInsertId();
            
            logActivity($_SESSION['user_id'], 'create_document', "Created boiler document: $doc_no");
        }
    }
    
    // Validate values against standards and collect warnings
    $warnings = [];
    
    // Pressure standard (8-12 bar)
    if ($steam_pressure < 8 || $steam_pressure > 12) {
        $warnings[] = "แรงดันไอน้ำ ({$steam_pressure} bar) อยู่นอกเกณฑ์มาตรฐาน (8-12 bar)";
    }
    
    // Temperature standard (170-190 °C)
    if ($steam_temperature < 170 || $steam_temperature > 190) {
        $warnings[] = "อุณหภูมิไอน้ำ ({$steam_temperature} °C) อยู่นอกเกณฑ์มาตรฐาน (170-190 °C)";
    }
    
    // Water level standard (0.5-1.5 m)
    if ($feed_water_level < 0.5 || $feed_water_level > 1.5) {
        $warnings[] = "ระดับน้ำในหม้อ ({$feed_water_level} m) อยู่นอกเกณฑ์มาตรฐาน (0.5-1.5 m)";
    }
    
    // Operating hours check
    if ($operating_hours > 24) {
        $warnings[] = "ชั่วโมงการทำงาน ({$operating_hours} hr) เกิน 24 ชั่วโมง";
    }
    
    // Calculate efficiency (optional)
    $efficiency = 0;
    if ($fuel_consumption > 0 && $operating_hours > 0) {
        $efficiency = ($steam_pressure * $steam_temperature) / ($fuel_consumption * $operating_hours);
    }
    
    // Insert record
    $stmt = $db->prepare("
        INSERT INTO boiler_daily_records 
        (doc_id, machine_id, record_date, steam_pressure, steam_temperature, 
         feed_water_level, fuel_consumption, operating_hours, remarks, recorded_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([
        $doc_id,
        $machine_id,
        $record_date,
        $steam_pressure,
        $steam_temperature,
        $feed_water_level,
        $fuel_consumption,
        $operating_hours,
        $remarks,
        $_SESSION['username']
    ]);
    
    if (!$result) {
        throw new Exception('ไม่สามารถบันทึกข้อมูลได้');
    }
    
    $record_id = $db->lastInsertId();
    
    // Commit transaction
    $db->commit();
    
    // Log activity
    logActivity($_SESSION['user_id'], 'add_boiler_record', 
               "Added boiler record ID: $record_id, Machine: {$machine['machine_code']}, Date: $record_date");
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => 'บันทึกข้อมูลเรียบร้อย',
        'data' => [
            'id' => $record_id,
            'machine_code' => $machine['machine_code'],
            'record_date' => $record_date
        ]
    ];
    
    if (!empty($warnings)) {
        $response['warnings'] = $warnings;
        $response['message'] .= ' (มีคำเตือน)';
    }
    
    if ($isAjax) {
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } else {
        $_SESSION['success'] = $response['message'];
        if (!empty($warnings)) {
            $_SESSION['warning'] = implode('<br>', array_slice($warnings, 0, 3));
        }
        header('Location: view.php?id=' . $record_id);
    }
    
} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Log error
    error_log("Error in boiler process_add.php: " . $e->getMessage());
    
    if ($isAjax) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $_SESSION['error'] = $e->getMessage();
        header('Location: add.php' . (isset($machine_id) ? '?machine_id=' . $machine_id : ''));
    }
}
?>