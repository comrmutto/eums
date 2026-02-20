<?php
/**
 * AJAX: Save Air Compressor Machine
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
    
    // Get POST data
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $machine_code = isset($_POST['machine_code']) ? trim($_POST['machine_code']) : '';
    $machine_name = isset($_POST['machine_name']) ? trim($_POST['machine_name']) : '';
    $brand = isset($_POST['brand']) ? trim($_POST['brand']) : '';
    $model = isset($_POST['model']) ? trim($_POST['model']) : '';
    $capacity = isset($_POST['capacity']) ? (float)$_POST['capacity'] : 0;
    $unit = isset($_POST['unit']) ? trim($_POST['unit']) : '';
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    
    // Validate required fields
    if (empty($machine_code)) {
        throw new Exception('กรุณาระบุรหัสเครื่อง');
    }
    
    if (empty($machine_name)) {
        throw new Exception('กรุณาระบุชื่อเครื่อง');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Check for duplicate machine code
    $sql = "SELECT id FROM mc_air WHERE machine_code = ?";
    $params = [$machine_code];
    
    if ($id > 0) {
        $sql .= " AND id != ?";
        $params[] = $id;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->fetch()) {
        throw new Exception('รหัสเครื่องนี้มีอยู่ในระบบแล้ว');
    }
    
    if ($id > 0) {
        // Update existing machine
        $stmt = $db->prepare("
            UPDATE mc_air 
            SET machine_code = ?,
                machine_name = ?,
                brand = ?,
                model = ?,
                capacity = ?,
                unit = ?,
                status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$machine_code, $machine_name, $brand, $model, $capacity, $unit, $status, $id]);
        
        logActivity($_SESSION['user_id'], 'edit_air_machine', "แก้ไขเครื่อง Air Compressor ID: $id, รหัส: $machine_code");
        
    } else {
        // Insert new machine
        $stmt = $db->prepare("
            INSERT INTO mc_air 
            (machine_code, machine_name, brand, model, capacity, unit, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$machine_code, $machine_name, $brand, $model, $capacity, $unit, $status]);
        $id = $db->lastInsertId();
        
        logActivity($_SESSION['user_id'], 'add_air_machine', "เพิ่มเครื่อง Air Compressor ใหม่ ID: $id, รหัส: $machine_code");
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $id ? 'แก้ไขเครื่องจักรเรียบร้อย' : 'เพิ่มเครื่องจักรเรียบร้อย',
        'data' => [
            'id' => $id,
            'machine_code' => $machine_code,
            'machine_name' => $machine_name
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