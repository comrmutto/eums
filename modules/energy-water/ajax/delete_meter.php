<?php
/**
 * AJAX: Delete Meter
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
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if (!$id) {
        throw new Exception('Meter ID required');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Check if meter has readings
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM meter_daily_readings WHERE meter_id = ?
    ");
    $stmt->execute([$id]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        throw new Exception('ไม่สามารถลบมิเตอร์ได้เนื่องจากมีข้อมูลการบันทึกแล้ว');
    }
    
    // Get meter details for logging
    $stmt = $db->prepare("SELECT * FROM mc_mdb_water WHERE id = ?");
    $stmt->execute([$id]);
    $meter = $stmt->fetch();
    
    if (!$meter) {
        throw new Exception('Meter not found');
    }
    
    // Delete meter
    $stmt = $db->prepare("DELETE FROM mc_mdb_water WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log activity
    logActivity($_SESSION['user_id'], 'delete_meter', 
               "Deleted meter ID: $id, Code: {$meter['meter_code']}, Type: {$meter['meter_type']}");
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'ลบมิเตอร์เรียบร้อย'
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