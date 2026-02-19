<?php
/**
 * AJAX: Get Meters List for Dropdown
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
    
    $type = isset($_GET['type']) ? $_GET['type'] : '';
    $status = isset($_GET['status']) ? (int)$_GET['status'] : 1;
    
    $sql = "SELECT id, meter_code, meter_name, meter_type, location FROM mc_mdb_water WHERE status = ?";
    $params = [$status];
    
    if ($type && in_array($type, ['electricity', 'water'])) {
        $sql .= " AND meter_type = ?";
        $params[] = $type;
    }
    
    $sql .= " ORDER BY meter_type, meter_code";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $meters = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $meters,
        'count' => count($meters)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>