<?php
/**
 * Summary Electricity Module - Process Add Record (Monthly)
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

// Set header for JSON response
header('Content-Type: application/json');

try {
    $db = getDB();
    
    // Get POST data
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $doc_id = isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0;
    $record_date = isset($_POST['record_date']) ? $_POST['record_date'] : '';
    $ee_unit = isset($_POST['ee_unit']) ? (float)$_POST['ee_unit'] : 0;
    $lpg_unit = isset($_POST['lpg_unit']) ? (float)$_POST['lpg_unit'] : 0;
    $cost_per_unit = isset($_POST['cost_per_unit']) ? (float)$_POST['cost_per_unit'] : 0;
    $lpg_cost_per_unit = isset($_POST['lpg_cost_per_unit']) ? (float)$_POST['lpg_cost_per_unit'] : 0;
    $pe = isset($_POST['pe']) && $_POST['pe'] !== '' ? (float)$_POST['pe'] : null;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    // Validate required fields
    if (empty($record_date)) {
        throw new Exception('กรุณาระบุวันที่บันทึก');
    }
    
    if ($ee_unit <= 0) {
        throw new Exception('กรุณากรอกหน่วยไฟฟ้า (ต้องมากกว่า 0)');
    }
    
    if ($cost_per_unit <= 0) {
        throw new Exception('กรุณากรอกค่าไฟต่อหน่วย (ต้องมากกว่า 0)');
    }
    
    // Validate LPG values (can be zero)
    if ($lpg_unit < 0) {
        throw new Exception('หน่วย LPG ต้องมากกว่าหรือเท่ากับ 0');
    }
    
    if ($lpg_cost_per_unit < 0) {
        throw new Exception('ค่า LPG ต่อหน่วยต้องมากกว่าหรือเท่ากับ 0');
    }
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $record_date)) {
        throw new Exception('รูปแบบวันที่ไม่ถูกต้อง');
    }
    
    // Check if date is first day of month
    $dateObj = new DateTime($record_date);
    if ($dateObj->format('d') != '01') {
        throw new Exception('วันที่ต้องเป็นวันแรกของเดือน (วันที่ 1)');
    }
    
    // Check if date is in future
    $firstDayOfCurrentMonth = date('Y-m-01');
    if ($record_date > $firstDayOfCurrentMonth) {
        throw new Exception('ไม่สามารถบันทึกข้อมูลเดือนในอนาคตได้');
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
            WHERE module_type = 'summary' 
            AND MONTH(start_date) = ? 
            AND YEAR(start_date) = ?
        ");
        $stmt->execute([$month, $year]);
        $existingDoc = $stmt->fetch();
        
        if ($existingDoc) {
            $doc_id = $existingDoc['id'];
        } else {
            // Create new document
            $doc_no = 'SUM-' . ($year + 543) . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
            $start_date = $year . '-' . $month . '-01';
            
            $stmt = $db->prepare("
                INSERT INTO documents (doc_no, module_type, start_date, rev_no, created_at)
                VALUES (?, 'summary', ?, '00', NOW())
            ");
            $stmt->execute([$doc_no, $start_date]);
            $doc_id = $db->lastInsertId();
            
            logActivity($_SESSION['user_id'], 'create_document', "Created summary document: $doc_no");
        }
    }
    
    // Check for duplicate record on same month
    $stmt = $db->prepare("
        SELECT id FROM electricity_summary 
        WHERE record_date = ?
    ");
    $stmt->execute([$record_date]);
    $existingRecord = $stmt->fetch();
    
    if ($existingRecord && !$id) {
        echo json_encode([
            'success' => false,
            'duplicate' => true,
            'message' => 'มีบันทึกข้อมูลสำหรับเดือนนี้อยู่แล้ว ต้องการบันทึกทับหรือไม่?'
        ]);
        $db->rollBack();
        exit();
    }
    
    // Validate PE if provided
    if ($pe !== null) {
        if ($pe < 0 || $pe > 1) {
            throw new Exception('ค่า PE ต้องอยู่ระหว่าง 0 ถึง 1');
        }
    }
    
    if ($id) {
        // Update existing record - ไม่ต้องส่ง total_cost และ total_lpg_cost
        $stmt = $db->prepare("
            UPDATE electricity_summary 
            SET doc_id = ?,
                record_date = ?,
                ee_unit = ?,
                lpg_unit = ?,
                cost_per_unit = ?,
                lpg_cost_per_unit = ?,
                pe = ?,
                remarks = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $doc_id,
            $record_date,
            $ee_unit,
            $lpg_unit,
            $cost_per_unit,
            $lpg_cost_per_unit,
            $pe,
            $remarks,
            $id
        ]);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception('ไม่สามารถอัปเดตข้อมูลได้: ' . ($errorInfo[2] ?? 'Unknown error'));
        }
        
        logActivity($_SESSION['user_id'], 'edit_summary_record', 
                   "Edited summary record ID: $id, Month: $record_date");
        
    } else {
        // Insert new record - ไม่ต้องส่ง total_cost และ total_lpg_cost
        $stmt = $db->prepare("
            INSERT INTO electricity_summary 
            (doc_id, record_date, ee_unit, lpg_unit, cost_per_unit, lpg_cost_per_unit, pe, remarks, recorded_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $doc_id,
            $record_date,
            $ee_unit,
            $lpg_unit,
            $cost_per_unit,
            $lpg_cost_per_unit,
            $pe,
            $remarks,
            $_SESSION['username']
        ]);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception('ไม่สามารถบันทึกข้อมูลได้: ' . ($errorInfo[2] ?? 'Unknown error'));
        }
        
        $id = $db->lastInsertId();
        
        logActivity($_SESSION['user_id'], 'add_summary_record', 
                   "Added summary record ID: $id, Month: $record_date");
    }
    
    // Commit transaction
    $db->commit();
    
    // Get the saved record to return (total_cost จะถูกคำนวณโดยฐานข้อมูล)
    $stmt = $db->prepare("SELECT * FROM electricity_summary WHERE id = ?");
    $stmt->execute([$id]);
    $newRecord = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => $id ? 'บันทึกข้อมูลเรียบร้อย' : 'เพิ่มข้อมูลเรียบร้อย',
        'data' => $newRecord
    ]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Database error in summary process_add.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในฐานข้อมูล: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error in summary process_add.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>