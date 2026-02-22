<?php
/**
 * Energy & Water Module - Process Add/Edit Reading
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
    $meter_id = isset($_POST['meter_id']) ? (int)$_POST['meter_id'] : 0;
    $record_date = isset($_POST['record_date']) ? $_POST['record_date'] : '';
    $morning_reading = isset($_POST['morning_reading']) ? (float)$_POST['morning_reading'] : 0;
    $evening_reading = isset($_POST['evening_reading']) ? (float)$_POST['evening_reading'] : 0;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    // Validate required fields
    if (!$meter_id) {
        throw new Exception('กรุณาเลือกมิเตอร์');
    }
    
    if (empty($record_date)) {
        throw new Exception('กรุณาระบุวันที่บันทึก');
    }
    
    if ($morning_reading === '' || $morning_reading === null) {
        throw new Exception('กรุณากรอกค่าเช้า');
    }
    
    if ($evening_reading === '' || $evening_reading === null) {
        throw new Exception('กรุณากรอกค่าเย็น');
    }
    
    // Validate readings
    if ($evening_reading <= $morning_reading) {
        throw new Exception('ค่าเย็นต้องมากกว่าค่าเช้า');
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
            WHERE module_type = 'energy_water' 
            AND MONTH(start_date) = ? 
            AND YEAR(start_date) = ?
        ");
        $stmt->execute([$month, $year]);
        $existingDoc = $stmt->fetch();
        
        if ($existingDoc) {
            $doc_id = $existingDoc['id'];
        } else {
            // Create new document
            $doc_no = 'EW-' . ($year + 543) . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
            $start_date = $year . '-' . $month . '-01';
            
            $stmt = $db->prepare("
                INSERT INTO documents (doc_no, module_type, start_date, rev_no, created_at)
                VALUES (?, 'energy_water', ?, '00', NOW())
            ");
            $stmt->execute([$doc_no, $start_date]);
            $doc_id = $db->lastInsertId();
        }
    }
    
    // Check for duplicate or overlapping readings
    if ($id) {
        // Editing - check if another record exists for same meter and date (excluding current)
        $stmt = $db->prepare("
            SELECT id FROM meter_daily_readings 
            WHERE meter_id = ? AND record_date = ? AND id != ?
        ");
        $stmt->execute([$meter_id, $record_date_db, $id]);
    } else {
        // Adding - check if record exists for same meter and date
        $stmt = $db->prepare("
            SELECT id FROM meter_daily_readings 
            WHERE meter_id = ? AND record_date = ?
        ");
        $stmt->execute([$meter_id, $record_date_db]);
    }
    
    if ($stmt->fetch()) {
        throw new Exception('มีบันทึกข้อมูลสำหรับมิเตอร์และวันนี้อยู่แล้ว');
    }
    
    // Check for abnormal usage
    $usage = $evening_reading - $morning_reading;
    $warning = null;
    
    // Get average usage for this meter
    $stmt = $db->prepare("
        SELECT AVG(usage_amount) as avg_usage 
        FROM meter_daily_readings 
        WHERE meter_id = ? AND id != ? AND usage_amount > 0
        GROUP BY meter_id
    ");
    $stmt->execute([$meter_id, $id ?: 0]);
    $avg = $stmt->fetch();
    
    if ($avg && $avg['avg_usage'] > 0) {
        $ratio = $usage / $avg['avg_usage'];
        if ($ratio > 3) {
            $warning = "ปริมาณการใช้สูงกว่าค่าเฉลี่ย " . number_format($ratio, 1) . " เท่า";
        } elseif ($ratio < 0.1 && $usage > 0) {
            $warning = "ปริมาณการใช้ต่ำกว่าค่าเฉลี่ยมาก";
        }
    }
    
    // Save or update reading
    if ($id) {
        $stmt = $db->prepare("
            UPDATE meter_daily_readings 
            SET meter_id = ?,
                record_date = ?,
                morning_reading = ?,
                evening_reading = ?,
                remarks = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$meter_id, $record_date_db, $morning_reading, $evening_reading, $remarks, $id]);
        
        logActivity($_SESSION['user_id'], 'edit_meter_reading', "Updated reading ID: $id");
    } else {
        $stmt = $db->prepare("
            INSERT INTO meter_daily_readings 
            (doc_id, meter_id, record_date, morning_reading, evening_reading, remarks, recorded_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$doc_id, $meter_id, $record_date_db, $morning_reading, $evening_reading, $remarks, $_SESSION['username']]);
        $id = $db->lastInsertId();
        
        logActivity($_SESSION['user_id'], 'add_meter_reading', "Added reading ID: $id");
    }
    
    // Commit transaction
    $db->commit();
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => 'บันทึกข้อมูลเรียบร้อย',
        'data' => [
            'id' => $id,
            'usage' => $usage
        ]
    ];
    
    if ($warning) {
        $response['warning'] = $warning;
    }
    
    echo json_encode($response);
    
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