<?php
/**
 * AJAX: Check if LPG Item has Usage Records
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
    
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$id) {
        echo json_encode(['has_usage' => false]);
        exit();
    }
    
    // Check for records
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as record_count,
            MIN(record_date) as first_date,
            MAX(record_date) as last_date,
            COUNT(CASE WHEN enum_value = 'NG' THEN 1 END) as ng_count
        FROM lpg_daily_records 
        WHERE item_id = ?
    ");
    $stmt->execute([$id]);
    $result = $stmt->fetch();
    
    // Get item details
    $stmt = $db->prepare("SELECT item_name, item_type FROM lpg_inspection_items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    
    echo json_encode([
        'has_usage' => $result['record_count'] > 0,
        'record_count' => $result['record_count'],
        'first_date' => $result['first_date'],
        'last_date' => $result['last_date'],
        'ng_count' => $result['ng_count'],
        'item_name' => $item ? $item['item_name'] : null,
        'item_type' => $item ? $item['item_type'] : null
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'has_usage' => false,
        'error' => $e->getMessage()
    ]);
}
?>