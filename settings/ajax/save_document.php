<?php
/**
 * AJAX: Save Document
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
    
    // Get POST data
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $module_type = isset($_POST['module_type']) ? $_POST['module_type'] : '';
    $doc_no = isset($_POST['doc_no']) ? trim($_POST['doc_no']) : '';
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
    $rev_no = isset($_POST['rev_no']) ? trim($_POST['rev_no']) : '';
    $details = isset($_POST['details']) ? trim($_POST['details']) : '';
    
    // Validate required fields
    if (empty($module_type)) {
        throw new Exception('กรุณาเลือกโมดูล');
    }
    
    if (empty($doc_no)) {
        throw new Exception('กรุณาระบุเลขที่เอกสาร');
    }
    
    if (empty($start_date)) {
        throw new Exception('กรุณาระบุวันที่เริ่มใช้');
    }
    
    // Convert date from Thai format
    $dateObj = DateTime::createFromFormat('d/m/Y', $start_date);
    if (!$dateObj) {
        throw new Exception('รูปแบบวันที่ไม่ถูกต้อง');
    }
    $start_date_db = $dateObj->format('Y-m-d');
    
    // Begin transaction
    $db->beginTransaction();
    
    // Check duplicate document number
    $sql = "SELECT id FROM documents WHERE doc_no = ?";
    $params = [$doc_no];
    
    if ($id > 0) {
        $sql .= " AND id != ?";
        $params[] = $id;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->fetch()) {
        throw new Exception('เลขที่เอกสารนี้มีอยู่ในระบบแล้ว');
    }
    
    if ($id > 0) {
        // Update existing document
        $stmt = $db->prepare("
            UPDATE documents 
            SET module_type = ?,
                doc_no = ?,
                start_date = ?,
                rev_no = ?,
                details = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$module_type, $doc_no, $start_date_db, $rev_no, $details, $id]);
        
        logActivity($_SESSION['user_id'], 'edit_document', "แก้ไขเอกสาร ID: $id");
        
    } else {
        // Insert new document
        $stmt = $db->prepare("
            INSERT INTO documents (module_type, doc_no, start_date, rev_no, details, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$module_type, $doc_no, $start_date_db, $rev_no, $details]);
        
        $new_id = $db->lastInsertId();
        logActivity($_SESSION['user_id'], 'add_document', "เพิ่มเอกสารใหม่ ID: $new_id");
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $id ? 'แก้ไขเอกสารเรียบร้อย' : 'เพิ่มเอกสารเรียบร้อย'
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