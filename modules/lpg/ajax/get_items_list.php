<?php
/**
 * AJAX: Get LPG Items List
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
    
    $type = isset($_GET['type']) ? $_GET['type'] : '';
    $active_only = isset($_GET['active_only']) ? (bool)$_GET['active_only'] : false;
    
    $sql = "SELECT * FROM lpg_inspection_items";
    $params = [];
    
    if ($type && in_array($type, ['number', 'enum'])) {
        $sql .= " WHERE item_type = ?";
        $params[] = $type;
    }
    
    $sql .= " ORDER BY item_no";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
    
    // Get usage statistics for each item
    foreach ($items as &$item) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as record_count,
                MIN(record_date) as first_used,
                MAX(record_date) as last_used
            FROM lpg_daily_records 
            WHERE item_id = ?
        ");
        $stmt->execute([$item['id']]);
        $stats = $stmt->fetch();
        
        $item['record_count'] = $stats['record_count'];
        $item['first_used'] = $stats['first_used'];
        $item['last_used'] = $stats['last_used'];
        
        // For enum items, get OK/NG stats
        if ($item['item_type'] == 'enum') {
            $stmt = $db->prepare("
                SELECT 
                    COUNT(CASE WHEN enum_value = 'OK' THEN 1 END) as ok_count,
                    COUNT(CASE WHEN enum_value = 'NG' THEN 1 END) as ng_count
                FROM lpg_daily_records 
                WHERE item_id = ?
            ");
            $stmt->execute([$item['id']]);
            $enumStats = $stmt->fetch();
            
            $item['ok_count'] = $enumStats['ok_count'];
            $item['ng_count'] = $enumStats['ng_count'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $items,
        'count' => count($items)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>