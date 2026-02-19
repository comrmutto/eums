<?php
/**
 * Air Compressor Module - Process Add Record
 * Engineering Utility Monitoring System (EUMS)
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
        exit();
    } else {
        header('Location: /eums/login.php');
        exit();
    }
}

// Load required files
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Set header for JSON response if AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json');
}

try {
    // Get database connection
    $db = getDB();
    
    // Validate CSRF token (if using)
    if (isset($_POST['csrf_token']) && !verifyCSRFToken($_POST['csrf_token'])) {
        throw new Exception('CSRF token validation failed');
    }
    
    // Get and validate POST data
    $doc_id = isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0;
    $record_date = isset($_POST['record_date']) ? $_POST['record_date'] : '';
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    $values = isset($_POST['values']) ? $_POST['values'] : [];
    
    // Validate required fields
    if (empty($record_date)) {
        throw new Exception('กรุณาระบุวันที่บันทึก');
    }
    
    if (empty($values)) {
        throw new Exception('กรุณากรอกข้อมูลการตรวจสอบ');
    }
    
    // Convert date from Thai format to database format
    $dateObj = DateTime::createFromFormat('d/m/Y', $record_date);
    if (!$dateObj) {
        throw new Exception('รูปแบบวันที่ไม่ถูกต้อง');
    }
    $record_date_db = $dateObj->format('Y-m-d');
    
    // Check if date is in future
    if ($record_date_db > date('Y-m-d')) {
        throw new Exception('ไม่สามารถบันทึกข้อมูลในอนาคตได้');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Check or create document for this month
    if (!$doc_id) {
        $month = $dateObj->format('m');
        $year = $dateObj->format('Y');
        
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
            
            // Log document creation
            logActivity($_SESSION['user_id'], 'create_document', "Created document: $doc_no");
        }
    }
    
    // Validate and prepare data for each inspection item
    $successCount = 0;
    $errorItems = [];
    $warningItems = [];
    
    foreach ($values as $item_id => $value) {
        $item_id = (int)$item_id;
        
        if (empty($value) && $value !== '0') {
            $errorItems[] = "รายการที่ $item_id: ไม่ได้กรอกค่า";
            continue;
        }
        
        if (!is_numeric($value)) {
            $errorItems[] = "รายการที่ $item_id: ค่าที่กรอกไม่ใช่ตัวเลข";
            continue;
        }
        
        $actual_value = (float)$value;
        
        // Get machine_id and standard for this item
        $stmt = $db->prepare("
            SELECT s.*, s.machine_id 
            FROM air_inspection_standards s
            WHERE s.id = ?
        ");
        $stmt->execute([$item_id]);
        $standard = $stmt->fetch();
        
        if (!$standard) {
            $errorItems[] = "รายการที่ $item_id: ไม่พบมาตรฐานการตรวจสอบ";
            continue;
        }
        
        // Check for duplicate record on same date
        $stmt = $db->prepare("
            SELECT id FROM air_daily_records 
            WHERE machine_id = ? AND inspection_item_id = ? AND record_date = ?
        ");
        $stmt->execute([$standard['machine_id'], $item_id, $record_date_db]);
        
        if ($stmt->fetch()) {
            $errorItems[] = "รายการที่ $item_id: มีบันทึกข้อมูลสำหรับวันนี้แล้ว";
            continue;
        }
        
        // Validate against standard
        if ($standard['min_value'] && $standard['max_value']) {
            if ($actual_value < $standard['min_value'] || $actual_value > $standard['max_value']) {
                $warningItems[] = "รายการ {$standard['inspection_item']}: ค่าอยู่นอกช่วงมาตรฐาน";
            }
        } else {
            $tolerance = $standard['standard_value'] * 0.1;
            if (abs($actual_value - $standard['standard_value']) > $tolerance) {
                $warningItems[] = "รายการ {$standard['inspection_item']}: ค่าเบี่ยงเบนเกิน 10%";
            }
        }
        
        // Insert record
        $stmt = $db->prepare("
            INSERT INTO air_daily_records 
            (doc_id, machine_id, inspection_item_id, record_date, actual_value, remarks, recorded_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $doc_id,
            $standard['machine_id'],
            $item_id,
            $record_date_db,
            $actual_value,
            $remarks,
            $_SESSION['username']
        ]);
        
        if ($result) {
            $successCount++;
        } else {
            $errorItems[] = "รายการที่ $item_id: ไม่สามารถบันทึกข้อมูลได้";
        }
    }
    
    // If no records were inserted successfully, rollback
    if ($successCount === 0) {
        $db->rollBack();
        throw new Exception('ไม่สามารถบันทึกข้อมูลได้: ' . implode(', ', $errorItems));
    }
    
    // Commit transaction
    $db->commit();
    
    // Log activity
    logActivity($_SESSION['user_id'], 'add_air_records', 
                "Added $successCount records for date: $record_date_db");
    
    // Prepare response message
    $message = "บันทึกข้อมูลสำเร็จ $successCount รายการ";
    if (!empty($warningItems)) {
        $message .= " (มีคำเตือน: " . implode(', ', array_slice($warningItems, 0, 3)) . ")";
    }
    if (!empty($errorItems)) {
        $message .= " (ไม่สำเร็จ: " . implode(', ', array_slice($errorItems, 0, 3)) . ")";
    }
    
    // Return response
    if ($isAjax) {
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => [
                'success_count' => $successCount,
                'warnings' => $warningItems,
                'errors' => $errorItems,
                'doc_id' => $doc_id
            ]
        ]);
    } else {
        $_SESSION['success'] = $message;
        if (!empty($warningItems)) {
            $_SESSION['warning'] = implode('<br>', array_slice($warningItems, 0, 5));
        }
        header('Location: index.php');
    }
    
} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Log error
    error_log("Error in process_add.php: " . $e->getMessage());
    
    // Return error response
    if ($isAjax) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    } else {
        $_SESSION['error'] = $e->getMessage();
        header('Location: add.php');
    }
}
?>