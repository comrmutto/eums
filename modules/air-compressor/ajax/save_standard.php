<?php
/**
 * AJAX: Save Air Compressor Inspection Standard
 * Engineering Utility Monitoring System (EUMS)
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
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
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $machine_id = isset($_POST['machine_id']) ? (int)$_POST['machine_id'] : 0;
    $inspection_item = isset($_POST['inspection_item']) ? trim($_POST['inspection_item']) : '';
    $standard_value = isset($_POST['standard_value']) ? (float)$_POST['standard_value'] : 0;
    $unit = isset($_POST['unit']) ? trim($_POST['unit']) : '';
    $min_value = isset($_POST['min_value']) && $_POST['min_value'] !== '' ? (float)$_POST['min_value'] : null;
    $max_value = isset($_POST['max_value']) && $_POST['max_value'] !== '' ? (float)$_POST['max_value'] : null;
    $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
    
    // Validate required fields
    if (!$machine_id) {
        throw new Exception('กรุณาเลือกเครื่องจักร');
    }
    
    if (empty($inspection_item)) {
        throw new Exception('กรุณาระบุหัวข้อตรวจสอบ');
    }
    
    if ($standard_value <= 0 && !$min_value && !$max_value) {
        throw new Exception('กรุณาระบุค่ามาตรฐาน');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Check if machine exists
    $stmt = $db->prepare("SELECT id FROM mc_air WHERE id = ?");
    $stmt->execute([$machine_id]);
    if (!$stmt->fetch()) {
        throw new Exception('ไม่พบเครื่องจักรที่เลือก');
    }
    
    // Check for duplicate item name for this machine
    $sql = "SELECT id FROM air_inspection_standards WHERE machine_id = ? AND inspection_item = ?";
    $params = [$machine_id, $inspection_item];
    
    if ($id > 0) {
        $sql .= " AND id != ?";
        $params[] = $id;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->fetch()) {
        throw new Exception('มีหัวข้อตรวจสอบนี้สำหรับเครื่องนี้อยู่แล้ว');
    }
    
    // Validate min/max values if provided
    if ($min_value !== null && $max_value !== null && $min_value > $max_value) {
        throw new Exception('ค่าต่ำสุดต้องน้อยกว่าค่าสูงสุด');
    }
    
    if ($id > 0) {
        // Update existing standard
        $stmt = $db->prepare("
            UPDATE air_inspection_standards 
            SET machine_id = ?,
                inspection_item = ?,
                standard_value = ?,
                unit = ?,
                min_value = ?,
                max_value = ?,
                sort_order = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$machine_id, $inspection_item, $standard_value, $unit, $min_value, $max_value, $sort_order, $id]);
        
        logActivity($_SESSION['user_id'], 'edit_air_standard', "แก้ไขมาตรฐาน Air ID: $id, หัวข้อ: $inspection_item");
        
    } else {
        // Get max sort order
        if ($sort_order <= 0) {
            $stmt = $db->prepare("SELECT MAX(sort_order) as max_order FROM air_inspection_standards WHERE machine_id = ?");
            $stmt->execute([$machine_id]);
            $max_order = $stmt->fetch()['max_order'];
            $sort_order = ($max_order ?: 0) + 1;
        }
        
        // Insert new standard
        $stmt = $db->prepare("
            INSERT INTO air_inspection_standards 
            (machine_id, inspection_item, standard_value, unit, min_value, max_value, sort_order, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$machine_id, $inspection_item, $standard_value, $unit, $min_value, $max_value, $sort_order]);
        $id = $db->lastInsertId();
        
        logActivity($_SESSION['user_id'], 'add_air_standard', "เพิ่มมาตรฐาน Air ใหม่ ID: $id, หัวข้อ: $inspection_item");
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $id ? 'แก้ไขมาตรฐานเรียบร้อย' : 'เพิ่มมาตรฐานเรียบร้อย',
        'data' => [
            'id' => $id,
            'inspection_item' => $inspection_item,
            'machine_id' => $machine_id
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