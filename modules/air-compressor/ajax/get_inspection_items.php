<?php
/**
 * AJAX: Get Inspection Items for Machine
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
    
    $machine_id = isset($_GET['machine_id']) ? (int)$_GET['machine_id'] : 0;
    
    if (!$machine_id) {
        echo json_encode(['success' => false, 'message' => 'Machine ID required']);
        exit();
    }
    
    $stmt = $db->prepare("
        SELECT * FROM air_inspection_standards 
        WHERE machine_id = ? 
        ORDER BY sort_order
    ");
    $stmt->execute([$machine_id]);
    $items = $stmt->fetchAll();
    
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