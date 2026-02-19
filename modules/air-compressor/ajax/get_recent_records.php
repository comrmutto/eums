<?php
/**
 * AJAX: Get Recent Records
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
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
    
    $stmt = $db->prepare("
        SELECT 
            r.*,
            DATE_FORMAT(r.record_date, '%d/%m/%Y') as record_date_thai,
            m.machine_name,
            s.inspection_item,
            s.standard_value,
            s.min_value,
            s.max_value,
            CASE 
                WHEN s.min_value IS NOT NULL AND s.max_value IS NOT NULL THEN
                    CASE WHEN r.actual_value BETWEEN s.min_value AND s.max_value THEN 'success' ELSE 'danger' END
                ELSE
                    CASE WHEN ABS(r.actual_value - s.standard_value) <= s.standard_value * 0.1 THEN 'success' ELSE 'danger' END
            END as status,
            CASE 
                WHEN s.min_value IS NOT NULL AND s.max_value IS NOT NULL THEN
                    CASE WHEN r.actual_value BETWEEN s.min_value AND s.max_value THEN 'ผ่าน' ELSE 'ไม่ผ่าน' END
                ELSE
                    CASE WHEN ABS(r.actual_value - s.standard_value) <= s.standard_value * 0.1 THEN 'ผ่าน' ELSE 'ไม่ผ่าน' END
            END as status_text
        FROM air_daily_records r
        JOIN mc_air m ON r.machine_id = m.id
        JOIN air_inspection_standards s ON r.inspection_item_id = s.id
        ORDER BY r.record_date DESC, r.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $records = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $records
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>