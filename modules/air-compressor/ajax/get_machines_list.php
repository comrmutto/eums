<?php
/**
 * AJAX: Get Air Compressor Machines List
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
    
    $active_only = isset($_GET['active_only']) ? (bool)$_GET['active_only'] : true;
    
    $sql = "SELECT id, machine_code, machine_name, brand, model, capacity, unit, status FROM mc_air";
    if ($active_only) {
        $sql .= " WHERE status = 1";
    }
    $sql .= " ORDER BY machine_code";
    
    $stmt = $db->query($sql);
    $machines = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $machines,
        'count' => count($machines)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>