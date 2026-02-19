<?php
/**
 * Air Compressor Module - Process Edit Record
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
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $machine_id = isset($_POST['machine_id']) ? (int)$_POST['machine_id'] : 0;
    $inspection_item_id = isset($_POST['inspection_item_id']) ? (int)$_POST['inspection_item_id'] : 0;
    $record_date = isset($_POST['record_date']) ? $_POST['record_date'] : '';
    $actual_value = isset($_POST['actual_value']) ? (float)$_POST['actual_value'] : 0;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    // Validate required fields
    if (!$id) {
        throw new Exception('ไม่พบข้อมูลที่ต้องการแก้ไข');
    }
    
    if (!$machine_id) {
        throw new Exception('กรุณาเลือกเครื่องจักร');
    }
    
    if (!$inspection_item_id) {
        throw new Exception('กรุณาเลือกหัวข้อตรวจสอบ');
    }
    
    if (empty($record_date)) {
        throw new Exception('กรุณาระบุวันที่บันทึก');
    }
    
    if ($actual_value === '' || $actual_value === null) {
        throw new Exception('กรุณากรอกค่าที่วัดได้');
    }
    
    // Convert date from Thai format to database format
    $dateObj = DateTime::createFromFormat('d/m/Y', $record_date);
    if (!$dateObj) {
        throw new Exception('รูปแบบวันที่ไม่ถูกต้อง (ต้องเป็น DD/MM/YYYY)');
    }
    $record_date_db = $dateObj->format('Y-m-d');
    
    // Check if date is in future
    if ($record_date_db > date('Y-m-d')) {
        throw new Exception('ไม่สามารถบันทึกข้อมูลในอนาคตได้');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Check if record exists and get current data
    $stmt = $db->prepare("
        SELECT * FROM air_daily_records WHERE id = ? FOR UPDATE
    ");
    $stmt->execute([$id]);
    $existingRecord = $stmt->fetch();
    
    if (!$existingRecord) {
        throw new Exception('ไม่พบข้อมูลที่ต้องการแก้ไข');
    }
    
    // Check for duplicate if date or item changed
    if ($existingRecord['record_date'] != $record_date_db || 
        $existingRecord['inspection_item_id'] != $inspection_item_id) {
        
        $stmt = $db->prepare("
            SELECT id FROM air_daily_records 
            WHERE machine_id = ? AND inspection_item_id = ? AND record_date = ?
            AND id != ?
        ");
        $stmt->execute([$machine_id, $inspection_item_id, $record_date_db, $id]);
        
        if ($stmt->fetch()) {
            throw new Exception('มีบันทึกข้อมูลสำหรับเครื่องจักร หัวข้อตรวจสอบ และวันนี้อยู่แล้ว');
        }
    }
    
    // Get standard for validation
    $stmt = $db->prepare("
        SELECT * FROM air_inspection_standards WHERE id = ?
    ");
    $stmt->execute([$inspection_item_id]);
    $standard = $stmt->fetch();
    
    if (!$standard) {
        throw new Exception('ไม่พบมาตรฐานการตรวจสอบ');
    }
    
    // Validate value against standard
    $warning = null;
    if ($standard['min_value'] && $standard['max_value']) {
        if ($actual_value < $standard['min_value'] || $actual_value > $standard['max_value']) {
            $warning = "ค่าอยู่นอกช่วงมาตรฐาน (ต้องอยู่ระหว่าง {$standard['min_value']} - {$standard['max_value']} {$standard['unit']})";
        }
    } else {
        $tolerance = $standard['standard_value'] * 0.1;
        if (abs($actual_value - $standard['standard_value']) > $tolerance) {
            $deviation = abs((($actual_value - $standard['standard_value']) / $standard['standard_value']) * 100);
            $warning = "ค่าเบี่ยงเบนจากมาตรฐาน " . number_format($deviation, 2) . "% (เกิน 10%)";
        }
    }
    
    // Update record
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
    
    $result = $stmt->execute([
        $machine_id,
        $inspection_item_id,
        $record_date_db,
        $actual_value,
        $remarks,
        $id
    ]);
    
    if (!$result) {
        throw new Exception('ไม่สามารถอัปเดตข้อมูลได้');
    }
    
    // Commit transaction
    $db->commit();
    
    // Log activity
    $logMessage = "Updated record ID: $id";
    if ($warning) {
        $logMessage .= " (Warning: $warning)";
    }
    logActivity($_SESSION['user_id'], 'edit_air_record', $logMessage);
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => 'อัปเดตข้อมูลเรียบร้อย',
        'data' => [
            'id' => $id,
            'warning' => $warning
        ]
    ];
    
    if ($warning) {
        $response['warning'] = $warning;
    }
    
    // Return response
    if ($isAjax) {
        echo json_encode($response);
    } else {
        $_SESSION['success'] = $response['message'];
        if ($warning) {
            $_SESSION['warning'] = $warning;
        }
        header('Location: view.php?id=' . $id);
    }
    
} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Log error
    error_log("Error in process_edit.php: " . $e->getMessage());
    
    // Return error response
    if ($isAjax) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    } else {
        $_SESSION['error'] = $e->getMessage();
        header('Location: edit.php?id=' . $id);
    }
}
?>