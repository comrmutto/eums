<?php
/**
 * AJAX: Delete Air Compressor Daily Record
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
    
    if (!$id) {
        throw new Exception('Record ID required');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Get record details for logging
    $stmt = $db->prepare("
        SELECT r.*, m.machine_code, s.inspection_item 
        FROM air_daily_records r
        JOIN mc_air m ON r.machine_id = m.id
        JOIN air_inspection_standards s ON r.inspection_item_id = s.id
        WHERE r.id = ?
    ");
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    
    if (!$record) {
        throw new Exception('Record not found');
    }
    
    // Check permission (optional: allow users to delete only their own records)
    if ($_SESSION['user_role'] !== 'admin' && $record['recorded_by'] !== $_SESSION['username']) {
        throw new Exception('คุณไม่มีสิทธิ์ลบข้อมูลนี้');
    }
    
    // Delete record
    $stmt = $db->prepare("DELETE FROM air_daily_records WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log activity
    logActivity($_SESSION['user_id'], 'delete_air_record', 
               "ลบบันทึก Air ID: $id, เครื่อง: {$record['machine_code']}, รายการ: {$record['inspection_item']}, วันที่: {$record['record_date']}");
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'ลบข้อมูลเรียบร้อย'
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