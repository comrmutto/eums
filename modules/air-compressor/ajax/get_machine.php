<?php
/**
 * AJAX: Get Air Compressor Machine Details
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
        throw new Exception('Machine ID required');
    }
    
    // Get machine data
    $stmt = $db->prepare("SELECT * FROM mc_air WHERE id = ?");
    $stmt->execute([$id]);
    $machine = $stmt->fetch();
    
    if (!$machine) {
        throw new Exception('Machine not found');
    }
    
    // Get inspection standards for this machine
    $stmt = $db->prepare("
        SELECT * FROM air_inspection_standards 
        WHERE machine_id = ? 
        ORDER BY sort_order
    ");
    $stmt->execute([$id]);
    $standards = $stmt->fetchAll();
    
    // Get recent records
    $stmt = $db->prepare("
        SELECT 
            r.*,
            s.inspection_item,
            DATE_FORMAT(r.record_date, '%d/%m/%Y') as record_date_thai
        FROM air_daily_records r
        JOIN air_inspection_standards s ON r.inspection_item_id = s.id
        WHERE r.machine_id = ?
        ORDER BY r.record_date DESC
        LIMIT 10
    ");
    $stmt->execute([$id]);
    $recent_records = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_records,
            AVG(r.actual_value) as avg_value,
            MAX(r.actual_value) as max_value,
            MIN(r.actual_value) as min_value,
            COUNT(DISTINCT r.record_date) as total_days,
            MAX(r.record_date) as last_record_date
        FROM air_daily_records r
        WHERE r.machine_id = ?
    ");
    $stmt->execute([$id]);
    $statistics = $stmt->fetch();
    
    // Format dates
    $machine['created_at_thai'] = date('d/m/Y H:i', strtotime($machine['created_at']));
    if ($machine['updated_at']) {
        $machine['updated_at_thai'] = date('d/m/Y H:i', strtotime($machine['updated_at']));
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'machine' => $machine,
            'standards' => $standards,
            'recent_records' => $recent_records,
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