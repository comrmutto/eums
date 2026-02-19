<?php
/**
 * AJAX: Get Reading Details
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
        throw new Exception('Reading ID required');
    }
    
    $stmt = $db->prepare("
        SELECT 
            r.*,
            DATE_FORMAT(r.record_date, '%d/%m/%Y') as record_date_thai,
            m.meter_name,
            m.meter_code,
            m.meter_type
        FROM meter_daily_readings r
        JOIN mc_mdb_water m ON r.meter_id = m.id
        WHERE r.id = ?
    ");
    $stmt->execute([$id]);
    $reading = $stmt->fetch();
    
    if (!$reading) {
        throw new Exception('Reading not found');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $reading
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>