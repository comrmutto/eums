<?php
/**
 * AJAX: Delete Reading
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
        throw new Exception('Reading ID required');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Get reading details for logging
    $stmt = $db->prepare("
        SELECT r.*, m.meter_name, m.meter_code 
        FROM meter_daily_readings r
        JOIN mc_mdb_water m ON r.meter_id = m.id
        WHERE r.id = ?
    ");
    $stmt->execute([$id]);
    $reading = $stmt->fetch();
    
    if (!$reading) {
        throw new Exception('Reading not found');
    }
    
    // Delete reading
    $stmt = $db->prepare("DELETE FROM meter_daily_readings WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log activity
    logActivity($_SESSION['user_id'], 'delete_meter_reading', 
               "Deleted reading ID: $id, Meter: {$reading['meter_code']}, Date: {$reading['record_date']}");
    
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