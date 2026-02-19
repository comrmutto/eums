<?php
/**
 * AJAX: Save Inspection Item
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
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $item_no = isset($_POST['item_no']) ? (int)$_POST['item_no'] : 0;
    $item_type = isset($_POST['item_type']) ? $_POST['item_type'] : '';
    $item_name = isset($_POST['item_name']) ? trim($_POST['item_name']) : '';
    $standard_value = isset($_POST['standard_value']) ? trim($_POST['standard_value']) : '';
    $unit = isset($_POST['unit']) ? trim($_POST['unit']) : '';
    $enum_options = isset($_POST['enum_options']) ? $_POST['enum_options'] : '';
    
    // Validate required fields
    if (!$item_no) {
        throw new Exception('กรุณาระบุลำดับที่');
    }
    
    if (!$item_type) {
        throw new Exception('กรุณาเลือกประเภท');
    }
    
    if (empty($item_name)) {
        throw new Exception('กรุณาระบุหัวข้อตรวจสอบ');
    }
    
    if ($item_type == 'enum' && empty($enum_options)) {
        throw new Exception('กรุณาระบุตัวเลือกสำหรับแบบ OK/NG');
    }
    
    // Process enum options
    $enum_json = null;
    if ($item_type == 'enum' && $enum_options) {
        $options = array_map('trim', explode(',', $enum_options));
        $enum_json = json_encode($options);
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Check for duplicate item number
    $sql = "SELECT id FROM lpg_inspection_items WHERE item_no = ?";
    $params = [$item_no];
    
    if ($id) {
        $sql .= " AND id != ?";
        $params[] = $id;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->fetch()) {
        throw new Exception('ลำดับที่นี้มีอยู่ในระบบแล้ว');
    }
    
    if ($id) {
        // Update existing item
        $stmt = $db->prepare("
            UPDATE lpg_inspection_items 
            SET item_no = ?,
                item_type = ?,
                item_name = ?,
                standard_value = ?,
                unit = ?,
                enum_options = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$item_no, $item_type, $item_name, $standard_value, $unit, $enum_json, $id]);
        
        logActivity($_SESSION['user_id'], 'edit_lpg_item', "Updated item ID: $id");
        
    } else {
        // Insert new item
        $stmt = $db->prepare("
            INSERT INTO lpg_inspection_items 
            (item_no, item_type, item_name, standard_value, unit, enum_options, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$item_no, $item_type, $item_name, $standard_value, $unit, $enum_json]);
        $id = $db->lastInsertId();
        
        logActivity($_SESSION['user_id'], 'add_lpg_item', "Added new item ID: $id");
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $id ? 'แก้ไขหัวข้อตรวจสอบเรียบร้อย' : 'เพิ่มหัวข้อตรวจสอบเรียบร้อย',
        'data' => ['id' => $id]
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