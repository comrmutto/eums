<?php
/**
 * Summary Electricity Module - Process Edit Record
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
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $record_date = isset($_POST['record_date']) ? $_POST['record_date'] : '';
    $ee_unit = isset($_POST['ee_unit']) ? (float)$_POST['ee_unit'] : 0;
    $cost_per_unit = isset($_POST['cost_per_unit']) ? (float)$_POST['cost_per_unit'] : 0;
    $pe = isset($_POST['pe']) ? (float)$_POST['pe'] : null;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    // Validate required fields
    if (!$id) {
        throw new Exception('ไม่พบข้อมูลที่ต้องการแก้ไข');
    }
    
    if (empty($record_date)) {
        throw new Exception('กรุณาระบุวันที่บันทึก');
    }
    
    if ($ee_unit <= 0) {
        throw new Exception('กรุณากรอกหน่วยไฟฟ้า (ต้องมากกว่า 0)');
    }
    
    if ($cost_per_unit <= 0) {
        throw new Exception('กรุณากรอกค่าไฟต่อหน่วย (ต้องมากกว่า 0)');
    }
    
    // Convert date from Thai format
    $dateObj = DateTime::createFromFormat('d/m/Y', $record_date);
    if (!$dateObj) {
        // Try alternate format
        $dateObj = DateTime::createFromFormat('Y-m-d', $record_date);
        if (!$dateObj) {
            throw new Exception('รูปแบบวันที่ไม่ถูกต้อง (ต้องเป็น DD/MM/YYYY)');
        }
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
        SELECT * FROM electricity_summary WHERE id = ? FOR UPDATE
    ");
    $stmt->execute([$id]);
    $existingRecord = $stmt->fetch();
    
    if (!$existingRecord) {
        throw new Exception('ไม่พบข้อมูลที่ต้องการแก้ไข');
    }
    
    // Check for duplicate if date changed
    if ($existingRecord['record_date'] != $record_date_db) {
        $stmt = $db->prepare("
            SELECT id FROM electricity_summary 
            WHERE record_date = ? AND id != ?
        ");
        $stmt->execute([$record_date_db, $id]);
        
        if ($stmt->fetch()) {
            throw new Exception('มีบันทึกข้อมูลสำหรับวันนี้อยู่แล้ว');
        }
    }
    
    // Calculate total cost
    $total_cost = $ee_unit * $cost_per_unit;
    
    // Validate PE if provided
    if ($pe !== null && $pe !== '') {
        if ($pe < 0 || $pe > 1) {
            throw new Exception('ค่า PE ต้องอยู่ระหว่าง 0 ถึง 1');
        }
    } else {
        $pe = null;
    }
    
    // Update record
    $stmt = $db->prepare("
        UPDATE electricity_summary 
        SET record_date = ?,
            ee_unit = ?,
            cost_per_unit = ?,
            total_cost = ?,
            pe = ?,
            remarks = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $record_date_db,
        $ee_unit,
        $cost_per_unit,
        $total_cost,
        $pe,
        $remarks,
        $id
    ]);
    
    if (!$result) {
        throw new Exception('ไม่สามารถอัปเดตข้อมูลได้');
    }
    
    // Check for anomalies after update
    $warnings = [];
    
    // Check if usage is unusually high or low compared to month average
    $month = $dateObj->format('m');
    $year = $dateObj->format('Y');
    
    $stmt = $db->prepare("
        SELECT 
            AVG(ee_unit) as avg_daily,
            STDDEV(ee_unit) as stddev_daily
        FROM electricity_summary
        WHERE MONTH(record_date) = ? AND YEAR(record_date) = ?
        AND id != ?
    ");
    $stmt->execute([$month, $year, $id]);
    $stats = $stmt->fetch();
    
    if ($stats && $stats['avg_daily'] > 0 && $stats['stddev_daily'] > 0) {
        $z_score = abs($ee_unit - $stats['avg_daily']) / $stats['stddev_daily'];
        
        if ($z_score > 2) {
            $warnings[] = "หน่วยไฟฟ้าวันนี้สูง/ต่ำกว่าค่าเฉลี่ยค่อนข้างมาก (ค่าเบี่ยงเบนมาตรฐาน: " . number_format($z_score, 2) . ")";
        }
    }
    
    // Compare with same day last year if available
    $lastYear = $year - 1;
    $stmt = $db->prepare("
        SELECT ee_unit FROM electricity_summary
        WHERE DAY(record_date) = ? AND MONTH(record_date) = ? AND YEAR(record_date) = ?
    ");
    $stmt->execute([$dateObj->format('d'), $month, $lastYear]);
    $lastYearData = $stmt->fetch();
    
    if ($lastYearData) {
        $change = (($ee_unit - $lastYearData['ee_unit']) / $lastYearData['ee_unit']) * 100;
        if (abs($change) > 30) {
            $warnings[] = "หน่วยไฟฟ้าเปลี่ยนแปลงจากปีที่แล้ว " . number_format($change, 1) . "%";
        }
    }
    
    // Check for significant change from previous value
    if ($existingRecord['ee_unit'] > 0) {
        $changeFromPrev = (($ee_unit - $existingRecord['ee_unit']) / $existingRecord['ee_unit']) * 100;
        if (abs($changeFromPrev) > 50) {
            $warnings[] = "หน่วยไฟฟ้าเปลี่ยนแปลงจากบันทึกเดิม " . number_format($changeFromPrev, 1) . "%";
        }
    }
    
    // Commit transaction
    $db->commit();
    
    // Log activity
    $logMessage = "Updated summary record ID: $id, Date: $record_date_db, EE: $ee_unit, Cost: $total_cost";
    if (!empty($warnings)) {
        $logMessage .= " (Warnings: " . implode('; ', $warnings) . ")";
    }
    logActivity($_SESSION['user_id'], 'edit_summary_record', $logMessage);
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => 'อัปเดตข้อมูลเรียบร้อย',
        'data' => [
            'id' => $id,
            'record_date' => $record_date_db,
            'ee_unit' => $ee_unit,
            'total_cost' => $total_cost
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
            $_SESSION['warning'] = implode('<br>', array_slice($warnings, 0, 3));
        }
        header('Location: view.php?id=' . $id);
    }
    
} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Log error
    error_log("Error in summary process_edit.php: " . $e->getMessage());
    
    if ($isAjax) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $_SESSION['error'] = $e->getMessage();
        header('Location: edit.php?id=' . $id);
    }
}
?>