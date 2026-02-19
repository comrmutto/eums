<?php
/**
 * AJAX: Validate Boiler Machine Data
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
    
    $field = isset($_POST['field']) ? $_POST['field'] : '';
    $value = isset($_POST['value']) ? trim($_POST['value']) : '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    $response = ['valid' => true, 'message' => ''];
    
    switch ($field) {
        case 'machine_code':
            if (empty($value)) {
                $response['valid'] = false;
                $response['message'] = 'กรุณาระบุรหัสเครื่อง';
            } else {
                $sql = "SELECT id FROM mc_boiler WHERE machine_code = ?";
                $params = [$value];
                
                if ($id) {
                    $sql .= " AND id != ?";
                    $params[] = $id;
                }
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                if ($stmt->fetch()) {
                    $response['valid'] = false;
                    $response['message'] = 'รหัสเครื่องนี้มีอยู่ในระบบแล้ว';
                }
            }
            break;
            
        case 'capacity':
            if (!is_numeric($value) || $value < 0) {
                $response['valid'] = false;
                $response['message'] = 'ความจุต้องเป็นตัวเลขที่มากกว่าหรือเท่ากับ 0';
            }
            break;
            
        case 'pressure_rating':
            if (!is_numeric($value) || $value < 0) {
                $response['valid'] = false;
                $response['message'] = 'แรงดันสูงสุดต้องเป็นตัวเลขที่มากกว่าหรือเท่ากับ 0';
            }
            break;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'valid' => false,
        'message' => $e->getMessage()
    ]);
}
?>