<?php
/**
 * LPG Module - Process Add Record
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
    $db = getDB();
    
    // Get POST data
    $doc_id = isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0;
    $record_date = isset($_POST['record_date']) ? $_POST['record_date'] : '';
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
    
    // Validate that at least one field is filled
    if (empty($numbers) && empty($enums)) {
        throw new Exception('กรุณากรอกข้อมูลอย่างน้อย 1 รายการ');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Check or create document for this month
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
            
            logActivity($_SESSION['user_id'], 'create_document', "Created LPG document: $doc_no");
        }
    }
    
    // Delete existing records for this date (if any)
    $stmt = $db->prepare("DELETE FROM lpg_daily_records WHERE record_date = ?");
    $stmt->execute([$record_date]);
    
    $successCount = 0;
    $warnings = [];
    $processedItems = [];
    
    // Process number items
    foreach ($numbers as $item_id => $value) {
        if ($value !== '' && $value !== null) {
            $item_id = (int)$item_id;
            
            // Get item details for validation
            $stmt = $db->prepare("
                SELECT * FROM lpg_inspection_items WHERE id = ?
            ");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();
            
            if (!$item) {
                $warnings[] = "ไม่พบข้อมูลรายการ ID: $item_id";
                continue;
            }
            
            if ($item['item_type'] != 'number') {
                $warnings[] = "รายการ {$item['item_name']} ไม่ใช่ประเภทตัวเลข";
                continue;
            }
            
            // Validate numeric value
            if (!is_numeric($value)) {
                $warnings[] = "รายการ {$item['item_name']}: ค่าที่กรอกไม่ใช่ตัวเลข";
                continue;
            }
            
            $actual_value = (float)$value;
            
            // Validate against standard
            $standard = floatval($item['standard_value']);
            $tolerance = $standard * 0.1;
            
            if (abs($actual_value - $standard) > $tolerance) {
                $deviation = abs((($actual_value - $standard) / $standard) * 100);
                $warnings[] = "{$item['item_name']}: ค่าเบี่ยงเบน " . number_format($deviation, 2) . "% (เกิน 10%)";
            }
            
            // Insert record
            $stmt = $db->prepare("
                INSERT INTO lpg_daily_records 
                (doc_id, item_id, record_date, number_value, remarks, recorded_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $doc_id,
                $item_id,
                $record_date,
                $actual_value,
                $remarks,
                $_SESSION['username']
            ]);
            
            if ($result) {
                $successCount++;
                $processedItems[] = [
                    'id' => $db->lastInsertId(),
                    'item_name' => $item['item_name'],
                    'type' => 'number',
                    'value' => $actual_value
                ];
            } else {
                $warnings[] = "ไม่สามารถบันทึกรายการ {$item['item_name']} ได้";
            }
        }
    }
    
    // Process enum items
    foreach ($enums as $item_id => $value) {
        if (!empty($value)) {
            $item_id = (int)$item_id;
            
            // Get item details
            $stmt = $db->prepare("
                SELECT * FROM lpg_inspection_items WHERE id = ?
            ");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();
            
            if (!$item) {
                $warnings[] = "ไม่พบข้อมูลรายการ ID: $item_id";
                continue;
            }
            
            if ($item['item_type'] != 'enum') {
                $warnings[] = "รายการ {$item['item_name']} ไม่ใช่ประเภท OK/NG";
                continue;
            }
            
            // Validate enum value
            $options = json_decode($item['enum_options'], true) ?? ['OK', 'NG'];
            if (!in_array($value, $options)) {
                $warnings[] = "รายการ {$item['item_name']}: ค่าไม่ถูกต้อง (ต้องเป็น " . implode(' หรือ ', $options) . ")";
                continue;
            }
            
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
            
            $result = $stmt->execute([
                $doc_id,
                $item_id,
                $record_date,
                $value,
                $remarks,
                $_SESSION['username']
            ]);
            
            if ($result) {
                $successCount++;
                $processedItems[] = [
                    'id' => $db->lastInsertId(),
                    'item_name' => $item['item_name'],
                    'type' => 'enum',
                    'value' => $value
                ];
            } else {
                $warnings[] = "ไม่สามารถบันทึกรายการ {$item['item_name']} ได้";
            }
        }
    }
    
    // Check if any records were inserted
    if ($successCount === 0) {
        throw new Exception('ไม่สามารถบันทึกข้อมูลได้: ' . implode(', ', $warnings));
    }
    
    // Commit transaction
    $db->commit();
    
    // Log activity
    logActivity($_SESSION['user_id'], 'add_lpg_records', 
               "บันทึกข้อมูล LPG วันที่: $record_date, จำนวน: $successCount รายการ");
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => "บันทึกข้อมูลเรียบร้อย $successCount รายการ",
        'data' => [
            'record_date' => $record_date,
            'success_count' => $successCount,
            'processed_items' => $processedItems,
            'doc_id' => $doc_id
        ]
    ];
    
    if (!empty($warnings)) {
        $response['warnings'] = $warnings;
        $response['message'] .= ' (มีคำเตือน)';
    }
    
    if ($isAjax) {
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } else {
        $_SESSION['success'] = $response['message'];
        if (!empty($warnings)) {
            $_SESSION['warning'] = implode('<br>', array_slice($warnings, 0, 5));
        }
        header('Location: index.php?date=' . $record_date);
    }
    
} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Log error
    error_log("Error in LPG process_add.php: " . $e->getMessage());
    
    if ($isAjax) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $_SESSION['error'] = $e->getMessage();
        header('Location: add.php?date=' . ($record_date ?? date('Y-m-d')));
    }
}
?>