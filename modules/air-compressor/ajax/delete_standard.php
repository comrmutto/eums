<?php
/**
 * AJAX: Delete Air Compressor Inspection Standard
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
        throw new Exception('ไม่พบหัวข้อที่ต้องการลบ');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Get standard details
    $stmt = $db->prepare("
        SELECT s.*, m.machine_code 
        FROM air_inspection_standards s
        JOIN mc_air m ON s.machine_id = m.id
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $standard = $stmt->fetch();
    
    if (!$standard) {
        throw new Exception('ไม่พบข้อมูลมาตรฐาน');
    }
    
    // Check for related records
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM air_daily_records WHERE inspection_item_id = ?");
    $stmt->execute([$id]);
    $recordCount = $stmt->fetch()['count'];
    
    if ($recordCount > 0 && !$force) {
        // If has records and not force delete, return warning
        echo json_encode([
            'success' => false,
            'has_records' => true,
            'message' => "ไม่สามารถลบได้เนื่องจากมีข้อมูลการบันทึกแล้ว $recordCount รายการ",
            'data' => [
                'record_count' => $recordCount,
                'inspection_item' => $standard['inspection_item'],
                'machine_code' => $standard['machine_code']
            ]
        ]);
        $db->rollBack();
        exit();
    }
    
    if ($recordCount > 0 && $force) {
        // Force delete - delete all related records
        $stmt = $db->prepare("DELETE FROM air_daily_records WHERE inspection_item_id = ?");
        $stmt->execute([$id]);
    }
    
    // Delete the standard
    $stmt = $db->prepare("DELETE FROM air_inspection_standards WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log activity
    logActivity($_SESSION['user_id'], 'delete_air_standard', 
               "ลบมาตรฐาน Air ID: $id, หัวข้อ: {$standard['inspection_item']}, เครื่อง: {$standard['machine_code']}" . 
               ($recordCount > 0 ? " (ลบบันทึกที่เกี่ยวข้อง $recordCount รายการ)" : ""));
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'ลบมาตรฐานเรียบร้อย' . ($recordCount > 0 ? " และข้อมูลที่เกี่ยวข้อง $recordCount รายการ" : ""),
        'data' => [
            'id' => $id,
            'inspection_item' => $standard['inspection_item'],
            'deleted_records' => $recordCount
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