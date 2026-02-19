<?php
/**
 * AJAX: Get Chart Data for LPG
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
    
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    
    // Get usage data
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(r.record_date, '%Y-%m-%d') as date,
            DATE_FORMAT(r.record_date, '%d/%m') as display_date,
            SUM(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE 0 END) as total_usage,
            COUNT(CASE WHEN i.item_type = 'enum' AND r.enum_value = 'NG' THEN 1 END) as ng_count,
            COUNT(CASE WHEN i.item_type = 'enum' AND r.enum_value = 'OK' THEN 1 END) as ok_count
        FROM lpg_daily_records r
        JOIN lpg_inspection_items i ON r.item_id = i.id
        WHERE r.record_date >= DATE_SUB(?, INTERVAL ? DAY)
        GROUP BY r.record_date
        ORDER BY r.record_date
    ");
    $stmt->execute([$endDate, $days]);
    $data = $stmt->fetchAll();
    
    // Get summary statistics
    $stmt = $db->prepare("
        SELECT 
            SUM(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE 0 END) as total_usage,
            AVG(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE NULL END) as avg_usage,
            MAX(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE 0 END) as max_usage,
            MIN(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE 0 END) as min_usage,
            COUNT(CASE WHEN i.item_type = 'enum' AND r.enum_value = 'NG' THEN 1 END) as total_ng,
            COUNT(CASE WHEN i.item_type = 'enum' AND r.enum_value = 'OK' THEN 1 END) as total_ok
        FROM lpg_daily_records r
        JOIN lpg_inspection_items i ON r.item_id = i.id
        WHERE r.record_date >= DATE_SUB(?, INTERVAL ? DAY)
    ");
    $stmt->execute([$endDate, $days]);
    $summary = $stmt->fetch();
    
    // Get top NG items
    $stmt = $db->prepare("
        SELECT 
            i.item_name,
            COUNT(*) as ng_count
        FROM lpg_daily_records r
        JOIN lpg_inspection_items i ON r.item_id = i.id
        WHERE r.record_date >= DATE_SUB(?, INTERVAL ? DAY)
        AND i.item_type = 'enum'
        AND r.enum_value = 'NG'
        GROUP BY i.id, i.item_name
        ORDER BY ng_count DESC
        LIMIT 5
    ");
    $stmt->execute([$endDate, $days]);
    $topNgItems = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'summary' => $summary,
        'top_ng_items' => $topNgItems
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>