<?php
/**
 * AJAX: Delete Document
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
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

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
        throw new Exception('ไม่พบเอกสารที่ต้องการลบ');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Check if document is being used
    $tables = [
        'air_daily_records' => 'doc_id',
        'meter_daily_readings' => 'doc_id',
        'lpg_daily_records' => 'doc_id',
        'boiler_daily_records' => 'doc_id',
        'electricity_summary' => 'doc_id'
    ];
    
    $used = false;
    foreach ($tables as $table => $field) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM $table WHERE $field = ?");
        $stmt->execute([$id]);
        if ($stmt->fetch()['count'] > 0) {
            $used = true;
            break;
        }
    }
    
    if ($used) {
        throw new Exception('ไม่สามารถลบเอกสารได้เนื่องจากมีการใช้งานอยู่');
    }
    
    // Get document details for logging
    $stmt = $db->prepare("SELECT doc_no FROM documents WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    
    if (!$doc) {
        throw new Exception('ไม่พบเอกสาร');
    }
    
    // Delete document
    $stmt = $db->prepare("DELETE FROM documents WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log activity
    logActivity($_SESSION['user_id'], 'delete_document', "ลบเอกสาร ID: $id, เลขที่: {$doc['doc_no']}");
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'ลบเอกสารเรียบร้อย'
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