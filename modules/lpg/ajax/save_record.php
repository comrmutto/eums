<?php
/**
 * AJAX: Save LPG Daily Records
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
    $record_date = isset($_POST['record_date']) ? $_POST['record_date'] : '';
    $doc_id = isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0;
    $numbers = isset($_POST['numbers']) ? $_POST['numbers'] : [];
    $enums = isset($_POST['enums']) ? $_POST['enums'] : [];
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    // Validate date
    if (empty($record_date)) {
        throw new Exception('กรุณาระบุวันที่บันทึก');
    }
    
    // Check if date is in future
    if ($record_date > date('Y-m-d')) {
        throw new Exception('ไม่สามารถบันทึกข้อมูลในอนาคตได้');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Check or create document
    if (!$doc_id) {
        $dateObj = new DateTime($record_date);
        $month = $dateObj->format('m');
        $year = $dateObj->format('Y');
        
        // Check if document exists for this month
        $stmt = $db->prepare("
            SELECT id FROM documents 
            WHERE module_type = 'lpg' 
            AND MONTH(start_date) = ? 
            AND YEAR(start_date) = ?
        ");
        $stmt->execute([$month, $year]);
        $existingDoc = $stmt->fetch();
        
        if ($existingDoc) {
            $doc_id = $existingDoc['id'];
        } else {
            // Create new document
            $doc_no = 'LPG-' . ($year + 543) . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
            $start_date = $year . '-' . $month . '-01';
            
            $stmt = $db->prepare("
                INSERT INTO documents (doc_no, module_type, start_date, rev_no, created_at)
                VALUES (?, 'lpg', ?, '00', NOW())
            ");
            $stmt->execute([$doc_no, $start_date]);
            $doc_id = $db->lastInsertId();
        }
    }
    
    // Delete existing records for this date
    $stmt = $db->prepare("DELETE FROM lpg_daily_records WHERE record_date = ?");
    $stmt->execute([$record_date]);
    
    // Insert number items
    $warnings = [];
    foreach ($numbers as $item_id => $value) {
        if ($value !== '' && $value !== null) {
            // Get item details for validation
            $stmt = $db->prepare("
                SELECT * FROM lpg_inspection_items WHERE id = ?
            ");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();
            
            if ($item) {
                // Validate against standard
                $standard = floatval($item['standard_value']);
                $actual = floatval($value);
                $tolerance = $standard * 0.1;
                
                if (abs($actual - $standard) > $tolerance) {
                    $deviation = abs((($actual - $standard) / $standard) * 100);
                    $warnings[] = "{$item['item_name']}: ค่าเบี่ยงเบน " . number_format($deviation, 2) . "% (เกิน 10%)";
                }
                
                // Insert record
                $stmt = $db->prepare("
                    INSERT INTO lpg_daily_records 
                    (doc_id, item_id, record_date, number_value, remarks, recorded_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$doc_id, $item_id, $record_date, $value, $remarks, $_SESSION['username']]);
            }
        }
    }
    
    // Insert enum items
    foreach ($enums as $item_id => $value) {
        if ($value) {
            // Get item details
            $stmt = $db->prepare("
                SELECT * FROM lpg_inspection_items WHERE id = ?
            ");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();
            
            if ($item) {
                // Check if value matches standard
                if ($value != $item['standard_value']) {
                    $warnings[] = "{$item['item_name']}: ค่าไม่ตรงตามมาตรฐาน (ควรเป็น {$item['standard_value']})";
                }
                
                // Insert record
                $stmt = $db->prepare("
                    INSERT INTO lpg_daily_records 
                    (doc_id, item_id, record_date, enum_value, remarks, recorded_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$doc_id, $item_id, $record_date, $value, $remarks, $_SESSION['username']]);
            }
        }
    }
    
    // Commit transaction
    $db->commit();
    
    // Log activity
    logActivity($_SESSION['user_id'], 'save_lpg_records', 
               "Saved LPG records for date: $record_date");
    
    echo json_encode([
        'success' => true,
        'message' => 'บันทึกข้อมูลเรียบร้อย',
        'warnings' => $warnings
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