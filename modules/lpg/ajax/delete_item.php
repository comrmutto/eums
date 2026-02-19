<?php
/**
 * AJAX: Delete Inspection Item
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
        throw new Exception('Item ID required');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Check if item has records
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM lpg_daily_records WHERE item_id = ?
    ");
    $stmt->execute([$id]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        throw new Exception('ไม่สามารถลบหัวข้อได้เนื่องจากมีข้อมูลการบันทึกแล้ว');
    }
    
    // Get item details for logging
    $stmt = $db->prepare("SELECT * FROM lpg_inspection_items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        throw new Exception('Item not found');
    }
    
    // Delete item
    $stmt = $db->prepare("DELETE FROM lpg_inspection_items WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log activity
    logActivity($_SESSION['user_id'], 'delete_lpg_item', 
               "Deleted item ID: $id, Name: {$item['item_name']}");
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'ลบหัวข้อตรวจสอบเรียบร้อย'
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