<?php
/**
 * AJAX: Delete Boiler Machine
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
    $force = isset($_POST['force']) ? (bool)$_POST['force'] : false;
    
    if (!$id) {
        throw new Exception('Machine ID required');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Get machine details
    $stmt = $db->prepare("SELECT * FROM mc_boiler WHERE id = ?");
    $stmt->execute([$id]);
    $machine = $stmt->fetch();
    
    if (!$machine) {
        throw new Exception('Machine not found');
    }
    
    // Check if machine has records
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM boiler_daily_records WHERE machine_id = ?");
    $stmt->execute([$id]);
    $result = $stmt->fetch();
    $hasRecords = $result['count'] > 0;
    
    if ($hasRecords && !$force) {
        // If has records and not force delete, return warning with details
        $stmt = $db->prepare("
            SELECT 
                MIN(record_date) as first_date,
                MAX(record_date) as last_date,
                COUNT(*) as record_count
            FROM boiler_daily_records 
            WHERE machine_id = ?
        ");
        $stmt->execute([$id]);
        $recordInfo = $stmt->fetch();
        
        echo json_encode([
            'success' => false,
            'has_records' => true,
            'message' => "ไม่สามารถลบได้เนื่องจากมีข้อมูลการบันทึกแล้ว {$recordInfo['record_count']} รายการ",
            'data' => [
                'record_count' => $recordInfo['record_count'],
                'first_date' => $recordInfo['first_date'],
                'last_date' => $recordInfo['last_date'],
                'machine_code' => $machine['machine_code'],
                'machine_name' => $machine['machine_name']
            ]
        ]);
        $db->rollBack();
        exit();
    }
    
    if ($hasRecords && $force) {
        // Force delete - also delete all related records
        $stmt = $db->prepare("DELETE FROM boiler_daily_records WHERE machine_id = ?");
        $stmt->execute([$id]);
        $deletedRecords = $stmt->rowCount();
        
        logActivity($_SESSION['user_id'], 'delete_boiler_records', 
                   "Deleted $deletedRecords records for machine ID: $id ({$machine['machine_code']})");
    }
    
    // Delete the machine
    $stmt = $db->prepare("DELETE FROM mc_boiler WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log activity
    logActivity($_SESSION['user_id'], 'delete_boiler_machine', 
               "Deleted boiler machine ID: $id, Code: {$machine['machine_code']}, Name: {$machine['machine_name']}" . 
               ($hasRecords ? " (with $deletedRecords records)" : ""));
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'ลบเครื่อง Boiler เรียบร้อย' . ($hasRecords ? " และข้อมูลที่เกี่ยวข้อง $deletedRecords รายการ" : ""),
        'data' => [
            'id' => $id,
            'machine_code' => $machine['machine_code'],
            'deleted_records' => $hasRecords ? $deletedRecords : 0
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