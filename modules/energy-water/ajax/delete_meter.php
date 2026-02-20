<?php
/**
 * AJAX: Delete Energy & Water Meter
 * Engineering Utility Monitoring System (EUMS)
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit();
}

// Load required files
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Set header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $db = getDB();
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $force = isset($_POST['force']) ? (bool)$_POST['force'] : false;
    
    if (!$id) {
        throw new Exception('ไม่พบมิเตอร์ที่ต้องการลบ');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Get meter details
    $stmt = $db->prepare("SELECT * FROM mc_mdb_water WHERE id = ?");
    $stmt->execute([$id]);
    $meter = $stmt->fetch();
    
    if (!$meter) {
        throw new Exception('ไม่พบข้อมูลมิเตอร์');
    }
    
    // Check for related daily readings
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM meter_daily_readings WHERE meter_id = ?");
    $stmt->execute([$id]);
    $readingCount = $stmt->fetch()['count'];
    
    // Get date range of readings if any
    $dateRange = null;
    if ($readingCount > 0) {
        $stmt = $db->prepare("
            SELECT 
                MIN(record_date) as first_date,
                MAX(record_date) as last_date
            FROM meter_daily_readings 
            WHERE meter_id = ?
        ");
        $stmt->execute([$id]);
        $dateRange = $stmt->fetch();
    }
    
    $hasRelated = ($readingCount > 0);
    
    if ($hasRelated && !$force) {
        // If has readings and not force delete, return warning with details
        echo json_encode([
            'success' => false,
            'has_readings' => true,
            'message' => "ไม่สามารถลบได้เนื่องจากมีข้อมูลการบันทึกแล้ว $readingCount รายการ",
            'data' => [
                'reading_count' => $readingCount,
                'first_date' => $dateRange ? $dateRange['first_date'] : null,
                'first_date_thai' => $dateRange ? date('d/m/Y', strtotime($dateRange['first_date'])) : null,
                'last_date' => $dateRange ? $dateRange['last_date'] : null,
                'last_date_thai' => $dateRange ? date('d/m/Y', strtotime($dateRange['last_date'])) : null,
                'meter_code' => $meter['meter_code'],
                'meter_name' => $meter['meter_name'],
                'meter_type' => $meter['meter_type']
            ]
        ]);
        $db->rollBack();
        exit();
    }
    
    if ($hasRelated && $force) {
        // Force delete - delete all related readings
        $stmt = $db->prepare("DELETE FROM meter_daily_readings WHERE meter_id = ?");
        $stmt->execute([$id]);
        
        // Log the deletion of readings
        logActivity($_SESSION['user_id'], 'delete_meter_readings', 
                   "ลบบันทึกค่ามิเตอร์ทั้งหมดของมิเตอร์ ID: $id, รหัส: {$meter['meter_code']}, จำนวน: $readingCount รายการ");
    }
    
    // Delete the meter
    $stmt = $db->prepare("DELETE FROM mc_mdb_water WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log activity
    $meterTypeText = $meter['meter_type'] == 'electricity' ? 'ไฟฟ้า' : 'น้ำ';
    logActivity($_SESSION['user_id'], 'delete_meter', 
               "ลบมิเตอร์ ID: $id, รหัส: {$meter['meter_code']}, ชื่อ: {$meter['meter_name']}, ประเภท: $meterTypeText" . 
               ($hasRelated ? " (ลบบันทึกที่เกี่ยวข้อง $readingCount รายการ)" : ""));
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'ลบมิเตอร์เรียบร้อย' . ($hasRelated ? " และข้อมูลที่เกี่ยวข้อง $readingCount รายการ" : ""),
        'data' => [
            'id' => $id,
            'meter_code' => $meter['meter_code'],
            'meter_name' => $meter['meter_name'],
            'meter_type' => $meter['meter_type'],
            'meter_type_text' => $meterTypeText,
            'deleted_readings' => $readingCount
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