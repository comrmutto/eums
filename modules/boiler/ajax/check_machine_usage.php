<?php
/**
 * AJAX: Check if Boiler Machine Has Usage Records
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
        echo json_encode(['has_records' => false]);
        exit();
    }
    
    // Check for records and get statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as record_count,
            MIN(record_date) as first_date,
            MAX(record_date) as last_date,
            AVG(steam_pressure) as avg_pressure,
            AVG(steam_temperature) as avg_temperature,
            SUM(fuel_consumption) as total_fuel,
            SUM(operating_hours) as total_hours
        FROM boiler_daily_records 
        WHERE machine_id = ?
    ");
    $stmt->execute([$id]);
    $stats = $stmt->fetch();
    
    // Get machine details
    $stmt = $db->prepare("SELECT machine_code, machine_name FROM mc_boiler WHERE id = ?");
    $stmt->execute([$id]);
    $machine = $stmt->fetch();
    
    echo json_encode([
        'has_records' => $stats['record_count'] > 0,
        'record_count' => $stats['record_count'],
        'first_date' => $stats['first_date'],
        'last_date' => $stats['last_date'],
        'avg_pressure' => round($stats['avg_pressure'], 2),
        'avg_temperature' => round($stats['avg_temperature'], 1),
        'total_fuel' => round($stats['total_fuel'], 2),
        'total_hours' => round($stats['total_hours'], 1),
        'machine_code' => $machine ? $machine['machine_code'] : null,
        'machine_name' => $machine ? $machine['machine_name'] : null
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'has_records' => false,
        'error' => $e->getMessage()
    ]);
}
?>