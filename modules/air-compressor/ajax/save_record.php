<?php
/**
 * AJAX: Save Daily Record for Air Compressor
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

// Get POST data
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$doc_id = isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0;
$machine_id = isset($_POST['machine_id']) ? (int)$_POST['machine_id'] : 0;
$inspection_item_id = isset($_POST['inspection_item_id']) ? (int)$_POST['inspection_item_id'] : 0;
$record_date = isset($_POST['record_date']) ? DateTime::createFromFormat('d/m/Y', $_POST['record_date']) : null;
$actual_value = isset($_POST['actual_value']) ? (float)$_POST['actual_value'] : 0;
$remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

// Validate required fields
if (!$machine_id || !$inspection_item_id || !$record_date) {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
    exit();
}

// Format date for database
$record_date_db = $record_date->format('Y-m-d');

try {
    $db = getDB();
    
    // Check if document exists, if not create one
    if (!$doc_id) {
        // Get current month and year from record date
        $month = $record_date->format('m');
        $year = $record_date->format('Y');
        
        // Check if document exists for this month
        $stmt = $db->prepare("
            SELECT id FROM documents 
            WHERE module_type = 'air' 
            AND MONTH(start_date) = ? 
            AND YEAR(start_date) = ?
        ");
        $stmt->execute([$month, $year]);
        $existingDoc = $stmt->fetch();
        
        if ($existingDoc) {
            $doc_id = $existingDoc['id'];
        } else {
            // Create new document
            $doc_no = 'AC-' . ($year + 543) . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
            $start_date = $year . '-' . $month . '-01';
            
            $stmt = $db->prepare("
                INSERT INTO documents (doc_no, module_type, start_date, rev_no, created_at)
                VALUES (?, 'air', ?, '00', NOW())
            ");
            $stmt->execute([$doc_no, $start_date]);
            $doc_id = $db->lastInsertId();
        }
    }
    
    // Check for duplicate record on same date
    if (!$id) {
        $stmt = $db->prepare("
            SELECT id FROM air_daily_records 
            WHERE machine_id = ? AND inspection_item_id = ? AND record_date = ?
        ");
        $stmt->execute([$machine_id, $inspection_item_id, $record_date_db]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'มีบันทึกข้อมูลสำหรับวันนี้แล้ว']);
            exit();
        }
    }
    
    // Get standard value for validation
    $stmt = $db->prepare("
        SELECT standard_value, min_value, max_value 
        FROM air_inspection_standards 
        WHERE id = ?
    ");
    $stmt->execute([$inspection_item_id]);
    $standard = $stmt->fetch();
    
    // Validate value against standard
    if ($standard) {
        if ($standard['min_value'] && $standard['max_value']) {
            if ($actual_value < $standard['min_value'] || $actual_value > $standard['max_value']) {
                // Log warning but still save
                error_log("Warning: Value outside standard range for record");
            }
        } else {
            $tolerance = $standard['standard_value'] * 0.1;
            if (abs($actual_value - $standard['standard_value']) > $tolerance) {
                error_log("Warning: Value deviation > 10% for record");
            }
        }
    }
    
    if ($id) {
        // Update existing record
        $stmt = $db->prepare("
            UPDATE air_daily_records 
            SET machine_id = ?, 
                inspection_item_id = ?, 
                record_date = ?, 
                actual_value = ?, 
                remarks = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$machine_id, $inspection_item_id, $record_date_db, $actual_value, $remarks, $id]);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'update_air_record', "Updated record ID: $id");
        
    } else {
        // Insert new record
        $stmt = $db->prepare("
            INSERT INTO air_daily_records 
            (doc_id, machine_id, inspection_item_id, record_date, actual_value, remarks, recorded_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$doc_id, $machine_id, $inspection_item_id, $record_date_db, $actual_value, $remarks, $_SESSION['username']]);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'add_air_record', "Added new record for machine ID: $machine_id");
    }
    
    echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลเรียบร้อย']);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>