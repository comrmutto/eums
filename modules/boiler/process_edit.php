<?php
/**
 * Boiler Module - Process Edit Record
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
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $machine_id = isset($_POST['machine_id']) ? (int)$_POST['machine_id'] : 0;
    $record_date = isset($_POST['record_date']) ? $_POST['record_date'] : '';
    $steam_pressure = isset($_POST['steam_pressure']) ? (float)$_POST['steam_pressure'] : 0;
    $steam_temperature = isset($_POST['steam_temperature']) ? (float)$_POST['steam_temperature'] : 0;
    $feed_water_level = isset($_POST['feed_water_level']) ? (float)$_POST['feed_water_level'] : 0;
    $fuel_consumption = isset($_POST['fuel_consumption']) ? (float)$_POST['fuel_consumption'] : 0;
    $operating_hours = isset($_POST['operating_hours']) ? (float)$_POST['operating_hours'] : 0;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    // Validate required fields
    if (!$id) {
        throw new Exception('ไม่พบข้อมูลที่ต้องการแก้ไข');
    }
    
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
    
    // Check if record exists and get current data
    $stmt = $db->prepare("
        SELECT r.*, m.machine_code 
        FROM boiler_daily_records r
        JOIN mc_boiler m ON r.machine_id = m.id
        WHERE r.id = ? FOR UPDATE
    ");
    $stmt->execute([$id]);
    $existingRecord = $stmt->fetch();
    
    if (!$existingRecord) {
        throw new Exception('ไม่พบข้อมูลที่ต้องการแก้ไข');
    }
    
    // Check if machine exists and is active
    $stmt = $db->prepare("SELECT * FROM mc_boiler WHERE id = ? AND status = 1");
    $stmt->execute([$machine_id]);
    $machine = $stmt->fetch();
    
    if (!$machine) {
        throw new Exception('ไม่พบเครื่อง Boiler หรือเครื่องไม่ได้เปิดใช้งาน');
    }
    
    // Check for duplicate if machine or date changed
    if ($existingRecord['machine_id'] != $machine_id || $existingRecord['record_date'] != $record_date) {
        $stmt = $db->prepare("
            SELECT id FROM boiler_daily_records 
            WHERE machine_id = ? AND record_date = ? AND id != ?
        ");
        $stmt->execute([$machine_id, $record_date, $id]);
        
        if ($stmt->fetch()) {
            throw new Exception('มีบันทึกข้อมูลสำหรับเครื่องนี้ในวันที่นี้อยู่แล้ว');
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
    
    // Check for abnormal changes from previous record
    if ($existingRecord['machine_id'] == $machine_id) {
        $pressure_change = abs($steam_pressure - $existingRecord['steam_pressure']);
        $temp_change = abs($steam_temperature - $existingRecord['steam_temperature']);
        
        if ($pressure_change > 3) {
            $warnings[] = "แรงดันไอน้ำเปลี่ยนแปลงมากกว่า 3 bar จากบันทึกก่อนหน้า";
        }
        
        if ($temp_change > 20) {
            $warnings[] = "อุณหภูมิไอน้ำเปลี่ยนแปลงมากกว่า 20°C จากบันทึกก่อนหน้า";
        }
    }
    
    // Update record
    $stmt = $db->prepare("
        UPDATE boiler_daily_records 
        SET machine_id = ?,
            record_date = ?,
            steam_pressure = ?,
            steam_temperature = ?,
            feed_water_level = ?,
            fuel_consumption = ?,
            operating_hours = ?,
            remarks = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $machine_id,
        $record_date,
        $steam_pressure,
        $steam_temperature,
        $feed_water_level,
        $fuel_consumption,
        $operating_hours,
        $remarks,
        $id
    ]);
    
    if (!$result) {
        throw new Exception('ไม่สามารถอัปเดตข้อมูลได้');
    }
    
    // Commit transaction
    $db->commit();
    
    // Log activity
    $logMessage = "Updated boiler record ID: $id, Machine: {$machine['machine_code']}, Date: $record_date";
    if (!empty($warnings)) {
        $logMessage .= " (Warnings: " . implode('; ', $warnings) . ")";
    }
    logActivity($_SESSION['user_id'], 'edit_boiler_record', $logMessage);
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => 'อัปเดตข้อมูลเรียบร้อย',
        'data' => [
            'id' => $id,
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
        header('Location: view.php?id=' . $id);
    }
    
} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Log error
    error_log("Error in boiler process_edit.php: " . $e->getMessage());
    
    if ($isAjax) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $_SESSION['error'] = $e->getMessage();
        header('Location: edit.php?id=' . $id);
    }
}
?>