<?php
/**
 * AJAX: Check if Meter Has Usage Data
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
    
    // Check for readings
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as reading_count,
            MIN(record_date) as first_reading,
            MAX(record_date) as last_reading
        FROM meter_daily_readings 
        WHERE meter_id = ?
    ");
    $stmt->execute([$id]);
    $result = $stmt->fetch();
    
    echo json_encode([
        'has_usage' => $result['reading_count'] > 0,
        'reading_count' => $result['reading_count'],
        'first_reading' => $result['first_reading'],
        'last_reading' => $result['last_reading']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'has_usage' => false,
        'error' => $e->getMessage()
    ]);
}
?>