<?php
/**
 * AJAX: Validate LPG Item Value
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

try {
    $db = getDB();
    
    $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $value = isset($_POST['value']) ? $_POST['value'] : '';
    
    if (!$item_id || $value === '') {
        echo json_encode(['valid' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
        exit();
    }
    
    // Get item details
    $stmt = $db->prepare("SELECT * FROM lpg_inspection_items WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        echo json_encode(['valid' => false, 'message' => 'ไม่พบรายการ']);
        exit();
    }
    
    $response = ['valid' => true, 'message' => 'ค่าผ่านเกณฑ์'];
    
    if ($item['item_type'] == 'number') {
        // Validate number
        if (!is_numeric($value)) {
            $response['valid'] = false;
            $response['message'] = 'กรุณากรอกตัวเลข';
        } else {
            $numValue = (float)$value;
            $standard = (float)$item['standard_value'];
            $deviation = abs(($numValue - $standard) / $standard * 100);
            
            if ($deviation > 10) {
                $response['valid'] = false;
                $response['message'] = 'ค่าเบี่ยงเบนเกิน 10%';
                $response['deviation'] = round($deviation, 2);
            }
        }
    } else {
        // Validate enum
        $options = json_decode($item['enum_options'], true) ?? ['OK', 'NG'];
        if (!in_array($value, $options)) {
            $response['valid'] = false;
            $response['message'] = 'ค่าไม่ถูกต้อง (ต้องเป็น ' . implode(' หรือ ', $options) . ')';
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'valid' => false,
        'message' => $e->getMessage()
    ]);
}
?>