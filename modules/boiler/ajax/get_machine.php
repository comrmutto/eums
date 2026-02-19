<?php
/**
 * AJAX: Get Boiler Machine Details
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
    
    $stmt = $db->prepare("SELECT * FROM mc_boiler WHERE id = ?");
    $stmt->execute([$id]);
    $machine = $stmt->fetch();
    
    if (!$machine) {
        throw new Exception('Machine not found');
    }
    
    // Get additional statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_records,
            AVG(steam_pressure) as avg_pressure,
            AVG(steam_temperature) as avg_temperature,
            AVG(fuel_consumption) as avg_fuel,
            SUM(operating_hours) as total_hours,
            MAX(record_date) as last_used,
            MIN(record_date) as first_used
        FROM boiler_daily_records 
        WHERE machine_id = ?
    ");
    $stmt->execute([$id]);
    $stats = $stmt->fetch();
    
    // Get recent records
    $stmt = $db->prepare("
        SELECT 
            record_date,
            steam_pressure,
            steam_temperature,
            fuel_consumption,
            operating_hours
        FROM boiler_daily_records 
        WHERE machine_id = ?
        ORDER BY record_date DESC
        LIMIT 5
    ");
    $stmt->execute([$id]);
    $recent = $stmt->fetchAll();
    
    $machine['statistics'] = $stats;
    $machine['recent_records'] = $recent;
    
    echo json_encode([
        'success' => true,
        'data' => $machine
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>