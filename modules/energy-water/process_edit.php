<?php
/**
 * Energy & Water Module - Process Edit Reading
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

// Set header for JSON response
header('Content-Type: application/json');

try {
    $db = getDB();
    
    // Get POST data
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $meter_id = isset($_POST['meter_id']) ? (int)$_POST['meter_id'] : 0;
    $record_date = isset($_POST['record_date']) ? $_POST['record_date'] : '';
    $morning_reading = isset($_POST['morning_reading']) ? (float)$_POST['morning_reading'] : 0;
    $evening_reading = isset($_POST['evening_reading']) ? (float)$_POST['evening_reading'] : 0;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    // Validate required fields
    if (!$id) {
        throw new Exception('ไม่พบข้อมูลที่ต้องการแก้ไข');
    }
    
    if (!$meter_id) {
        throw new Exception('กรุณาเลือกมิเตอร์');
    }
    
    if (empty($record_date)) {
        throw new Exception('กรุณาระบุวันที่บันทึก');
    }
    
    if ($morning_reading === '' || $morning_reading === null) {
        throw new Exception('กรุณากรอกค่าเช้า');
    }
    
    if ($evening_reading === '' || $evening_reading === null) {
        throw new Exception('กรุณากรอกค่าเย็น');
    }
    
    if ($morning_reading < 0 || $evening_reading < 0) {
        throw new Exception('ค่าที่อ่านได้ต้องมากกว่าหรือเท่ากับ 0');
    }
    
    if ($evening_reading <= $morning_reading) {
        throw new Exception('ค่าเย็นต้องมากกว่าค่าเช้า');
    }
    
    // Convert date from Thai format
    $dateObj = DateTime::createFromFormat('d/m/Y', $record_date);
    if (!$dateObj) {
        // Try alternate format
        $dateObj = DateTime::createFromFormat('Y-m-d', $record_date);
        if (!$dateObj) {
            throw new Exception('รูปแบบวันที่ไม่ถูกต้อง (ต้องเป็น DD/MM/YYYY)');
        }
    }
    $record_date_db = $dateObj->format('Y-m-d');
    
    // Check if date is in future
    if ($record_date_db > date('Y-m-d')) {
        throw new Exception('ไม่สามารถบันทึกข้อมูลในอนาคตได้');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Check if record exists
    $stmt = $db->prepare("
        SELECT r.*, m.meter_type, m.meter_code 
        FROM meter_daily_readings r
        JOIN mc_mdb_water m ON r.meter_id = m.id
        WHERE r.id = ? FOR UPDATE
    ");
    $stmt->execute([$id]);
    $existingRecord = $stmt->fetch();
    
    if (!$existingRecord) {
        throw new Exception('ไม่พบข้อมูลที่ต้องการแก้ไข');
    }
    
    // Check if meter exists and is active
    $stmt = $db->prepare("SELECT * FROM mc_mdb_water WHERE id = ? AND status = 1");
    $stmt->execute([$meter_id]);
    $meter = $stmt->fetch();
    
    if (!$meter) {
        throw new Exception('ไม่พบมิเตอร์หรือมิเตอร์ไม่ได้เปิดใช้งาน');
    }
    
    // Check for duplicate if meter or date changed
    if ($existingRecord['meter_id'] != $meter_id || $existingRecord['record_date'] != $record_date_db) {
        $stmt = $db->prepare("
            SELECT id FROM meter_daily_readings 
            WHERE meter_id = ? AND record_date = ? AND id != ?
        ");
        $stmt->execute([$meter_id, $record_date_db, $id]);
        
        if ($stmt->fetch()) {
            throw new Exception('มีบันทึกข้อมูลสำหรับมิเตอร์และวันนี้อยู่แล้ว');
        }
    }
    
    // Calculate usage
    $usage = $evening_reading - $morning_reading;
    
    // Check for abnormal usage
    $warnings = [];
    if ($usage > 1000) {
        $warnings[] = "ปริมาณการใช้สูงผิดปกติ ($usage)";
    }
    
    // Compare with average
    $stmt = $db->prepare("
        SELECT AVG(usage_amount) as avg_usage 
        FROM meter_daily_readings 
        WHERE meter_id = ? AND id != ? AND usage_amount > 0
    ");
    $stmt->execute([$meter_id, $id]);
    $avg = $stmt->fetch();
    
    if ($avg && $avg['avg_usage'] > 0) {
        $ratio = $usage / $avg['avg_usage'];
        if ($ratio > 3) {
            $warnings[] = "ปริมาณการใช้สูงกว่าค่าเฉลี่ย " . round($ratio, 1) . " เท่า";
        } elseif ($ratio < 0.1 && $usage > 0) {
            $warnings[] = "ปริมาณการใช้ต่ำกว่าค่าเฉลี่ยมาก";
        }
    }
    
    // Update record
    $stmt = $db->prepare("
        UPDATE meter_daily_readings 
        SET meter_id = ?,
            record_date = ?,
            morning_reading = ?,
            evening_reading = ?,
            usage_amount = ?,
            remarks = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $meter_id,
        $record_date_db,
        $morning_reading,
        $evening_reading,
        $usage,
        $remarks,
        $id
    ]);
    
    if (!$result) {
        throw new Exception('ไม่สามารถอัปเดตข้อมูลได้');
    }
    
    // Commit transaction
    $db->commit();
    
    // Log activity
    $logMessage = "Updated meter reading ID: $id, Meter: {$meter['meter_code']}, Date: $record_date_db";
    if (!empty($warnings)) {
        $logMessage .= " (Warnings: " . implode('; ', $warnings) . ")";
    }
    logActivity($_SESSION['user_id'], 'edit_meter_reading', $logMessage);
    
    echo json_encode([
        'success' => true,
        'message' => 'อัปเดตข้อมูลเรียบร้อย' . (!empty($warnings) ? ' (มีคำเตือน)' : ''),
        'warnings' => $warnings,
        'data' => [
            'id' => $id,
            'meter_code' => $meter['meter_code'],
            'usage' => $usage
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>