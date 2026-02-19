<?php
/**
 * AJAX: Update Document Info for Boiler
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
    
    // Get POST data
    $doc_no = isset($_POST['doc_no']) ? trim($_POST['doc_no']) : '';
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
    $rev_no = isset($_POST['rev_no']) ? trim($_POST['rev_no']) : '';
    $details = isset($_POST['details']) ? trim($_POST['details']) : '';
    $module = isset($_POST['module']) ? $_POST['module'] : 'boiler';
    
    // Validate required fields
    if (empty($doc_no)) {
        throw new Exception('กรุณาระบุเลขที่เอกสาร');
    }
    
    if (empty($start_date)) {
        throw new Exception('กรุณาระบุวันที่เริ่มใช้');
    }
    
    // Convert date from Thai format
    $dateObj = DateTime::createFromFormat('d/m/Y', $start_date);
    if (!$dateObj) {
        // Try alternate format
        $dateObj = DateTime::createFromFormat('Y-m-d', $start_date);
        if (!$dateObj) {
            throw new Exception('รูปแบบวันที่ไม่ถูกต้อง (ต้องเป็น DD/MM/YYYY)');
        }
    }
    $start_date_db = $dateObj->format('Y-m-d');
    
    // Begin transaction
    $db->beginTransaction();
    
    // Get month and year from start date
    $month = $dateObj->format('m');
    $year = $dateObj->format('Y');
    
    // Check if document exists for this month
    $stmt = $db->prepare("
        SELECT id FROM documents 
        WHERE module_type = ? 
        AND MONTH(start_date) = ? 
        AND YEAR(start_date) = ?
    ");
    $stmt->execute([$module, $month, $year]);
    $existingDoc = $stmt->fetch();
    
    if ($existingDoc) {
        // Update existing document
        $stmt = $db->prepare("
            UPDATE documents 
            SET doc_no = ?,
                start_date = ?,
                rev_no = ?,
                details = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$doc_no, $start_date_db, $rev_no, $details, $existingDoc['id']]);
        $doc_id = $existingDoc['id'];
        
        logActivity($_SESSION['user_id'], 'update_document', "Updated boiler document ID: $doc_id");
        
    } else {
        // Check if document with same doc_no exists
        $stmt = $db->prepare("SELECT id FROM documents WHERE doc_no = ?");
        $stmt->execute([$doc_no]);
        $docByNo = $stmt->fetch();
        
        if ($docByNo) {
            throw new Exception('เลขที่เอกสารนี้มีอยู่ในระบบแล้ว');
        }
        
        // Create new document
        $stmt = $db->prepare("
            INSERT INTO documents 
            (doc_no, module_type, start_date, rev_no, details, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$doc_no, $module, $start_date_db, $rev_no, $details]);
        $doc_id = $db->lastInsertId();
        
        logActivity($_SESSION['user_id'], 'create_document', "Created boiler document ID: $doc_id");
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $existingDoc ? 'อัปเดตเอกสารเรียบร้อย' : 'สร้างเอกสารใหม่เรียบร้อย',
        'data' => [
            'doc_id' => $doc_id,
            'doc_no' => $doc_no,
            'start_date' => $start_date_db,
            'start_date_thai' => date('d/m/Y', strtotime($start_date_db))
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