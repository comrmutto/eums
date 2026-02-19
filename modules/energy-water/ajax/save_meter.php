<?php
/**
 * AJAX: Save Meter (Add/Edit)
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
    
    // Get POST data
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $meter_type = isset($_POST['meter_type']) ? $_POST['meter_type'] : '';
    $meter_code = isset($_POST['meter_code']) ? trim($_POST['meter_code']) : '';
    $meter_name = isset($_POST['meter_name']) ? trim($_POST['meter_name']) : '';
    $location = isset($_POST['location']) ? trim($_POST['location']) : '';
    $initial_reading = isset($_POST['initial_reading']) ? (float)$_POST['initial_reading'] : 0;
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    
    // Validate required fields
    if (empty($meter_type)) {
        throw new Exception('กรุณาเลือกประเภทมิเตอร์');
    }
    
    if (!in_array($meter_type, ['electricity', 'water'])) {
        throw new Exception('ประเภทมิเตอร์ไม่ถูกต้อง');
    }
    
    if (empty($meter_code)) {
        throw new Exception('กรุณาระบุรหัสมิเตอร์');
    }
    
    if (empty($meter_name)) {
        throw new Exception('กรุณาระบุชื่อมิเตอร์');
    }
    
    if ($initial_reading < 0) {
        throw new Exception('ค่าเริ่มต้นต้องมากกว่าหรือเท่ากับ 0');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Check for duplicate meter code
    $sql = "SELECT id FROM mc_mdb_water WHERE meter_code = ?";
    $params = [$meter_code];
    
    if ($id) {
        $sql .= " AND id != ?";
        $params[] = $id;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->fetch()) {
        throw new Exception('รหัสมิเตอร์นี้มีอยู่ในระบบแล้ว');
    }
    
    if ($id) {
        // Update existing meter
        $stmt = $db->prepare("
            UPDATE mc_mdb_water 
            SET meter_type = ?,
                meter_code = ?,
                meter_name = ?,
                location = ?,
                initial_reading = ?,
                status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$meter_type, $meter_code, $meter_name, $location, $initial_reading, $status, $id]);
        
        logActivity($_SESSION['user_id'], 'edit_meter', "Updated meter ID: $id, Code: $meter_code");
        
    } else {
        // Insert new meter
        $stmt = $db->prepare("
            INSERT INTO mc_mdb_water 
            (meter_type, meter_code, meter_name, location, initial_reading, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$meter_type, $meter_code, $meter_name, $location, $initial_reading, $status]);
        $id = $db->lastInsertId();
        
        logActivity($_SESSION['user_id'], 'add_meter', "Added new meter ID: $id, Code: $meter_code");
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $id ? 'แก้ไขมิเตอร์เรียบร้อย' : 'เพิ่มมิเตอร์เรียบร้อย',
        'data' => [
            'id' => $id,
            'meter_code' => $meter_code
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