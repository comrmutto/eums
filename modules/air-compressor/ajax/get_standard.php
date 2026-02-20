<?php
/**
 * AJAX: Get Air Compressor Inspection Standard
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
        throw new Exception('Standard ID required');
    }
    
    // Get standard data
    $stmt = $db->prepare("
        SELECT s.*, m.machine_code, m.machine_name 
        FROM air_inspection_standards s
        JOIN mc_air m ON s.machine_id = m.id
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $standard = $stmt->fetch();
    
    if (!$standard) {
        throw new Exception('Standard not found');
    }
    
    // Get statistics for this standard
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_records,
            AVG(actual_value) as avg_value,
            MAX(actual_value) as max_value,
            MIN(actual_value) as min_value,
            COUNT(CASE 
                WHEN (min_value IS NOT NULL AND actual_value BETWEEN min_value AND max_value)
                     OR (min_value IS NULL AND ABS(actual_value - standard_value) <= standard_value * 0.1)
                THEN 1 END) as pass_count,
            MAX(record_date) as last_record_date
        FROM air_daily_records
        WHERE inspection_item_id = ?
    ");
    $stmt->execute([$id]);
    $statistics = $stmt->fetch();
    
    // Calculate pass rate
    $statistics['pass_rate'] = $statistics['total_records'] > 0 ? 
        round(($statistics['pass_count'] / $statistics['total_records']) * 100, 2) : 0;
    
    // Format dates
    $standard['created_at_thai'] = date('d/m/Y H:i', strtotime($standard['created_at']));
    if ($standard['updated_at']) {
        $standard['updated_at_thai'] = date('d/m/Y H:i', strtotime($standard['updated_at']));
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'standard' => $standard,
            'statistics' => $statistics
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>