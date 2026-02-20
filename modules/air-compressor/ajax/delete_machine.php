<?php
/**
 * AJAX: Delete Air Compressor Machine
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
        throw new Exception('ไม่พบเครื่องที่ต้องการลบ');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Get machine details
    $stmt = $db->prepare("SELECT * FROM mc_air WHERE id = ?");
    $stmt->execute([$id]);
    $machine = $stmt->fetch();
    
    if (!$machine) {
        throw new Exception('ไม่พบข้อมูลเครื่อง');
    }
    
    // Check for related inspection standards
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM air_inspection_standards WHERE machine_id = ?");
    $stmt->execute([$id]);
    $standardCount = $stmt->fetch()['count'];
    
    // Check for related records
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM air_daily_records WHERE machine_id = ?");
    $stmt->execute([$id]);
    $recordCount = $stmt->fetch()['count'];
    
    $hasRelated = ($standardCount > 0 || $recordCount > 0);
    
    if ($hasRelated && !$force) {
        // If has related data and not force delete, return warning
        echo json_encode([
            'success' => false,
            'has_related' => true,
            'message' => "ไม่สามารถลบได้เนื่องจากมีข้อมูลที่เกี่ยวข้อง",
            'data' => [
                'standard_count' => $standardCount,
                'record_count' => $recordCount,
                'machine_code' => $machine['machine_code'],
                'machine_name' => $machine['machine_name']
            ]
        ]);
        $db->rollBack();
        exit();
    }
    
    if ($hasRelated && $force) {
        // Force delete - delete all related data
        if ($standardCount > 0) {
            $stmt = $db->prepare("DELETE FROM air_inspection_standards WHERE machine_id = ?");
            $stmt->execute([$id]);
        }
        
        if ($recordCount > 0) {
            $stmt = $db->prepare("DELETE FROM air_daily_records WHERE machine_id = ?");
            $stmt->execute([$id]);
        }
    }
    
    // Delete the machine
    $stmt = $db->prepare("DELETE FROM mc_air WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log activity
    logActivity($_SESSION['user_id'], 'delete_air_machine', 
               "ลบเครื่อง Air Compressor ID: $id, รหัส: {$machine['machine_code']}, ชื่อ: {$machine['machine_name']}" . 
               ($hasRelated ? " (ลบข้อมูลที่เกี่ยวข้อง: มาตรฐาน $standardCount รายการ, บันทึก $recordCount รายการ)" : ""));
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'ลบเครื่องจักรเรียบร้อย' . ($hasRelated ? " และข้อมูลที่เกี่ยวข้อง" : ""),
        'data' => [
            'id' => $id,
            'machine_code' => $machine['machine_code'],
            'deleted_standards' => $standardCount,
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