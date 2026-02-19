<?php
/**
 * AJAX: Get Meter Details
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
        throw new Exception('Meter ID required');
    }
    
    $stmt = $db->prepare("
        SELECT * FROM mc_mdb_water WHERE id = ?
    ");
    $stmt->execute([$id]);
    $meter = $stmt->fetch();
    
    if (!$meter) {
        throw new Exception('Meter not found');
    }
    
    // Get additional statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_readings,
            AVG(usage_amount) as avg_usage,
            MAX(usage_amount) as max_usage,
            MIN(usage_amount) as min_usage,
            MAX(record_date) as last_reading_date
        FROM meter_daily_readings 
        WHERE meter_id = ?
    ");
    $stmt->execute([$id]);
    $stats = $stmt->fetch();
    
    $meter['statistics'] = $stats;
    
    echo json_encode([
        'success' => true,
        'data' => $meter
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>