<?php
/**
 * AJAX: Get Boiler Machines List for Dropdown
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
    $with_stats = isset($_GET['with_stats']) ? (bool)$_GET['with_stats'] : false;
    
    $sql = "SELECT * FROM mc_boiler";
    if ($active_only) {
        $sql .= " WHERE status = 1";
    }
    $sql .= " ORDER BY machine_code";
    
    $stmt = $db->query($sql);
    $machines = $stmt->fetchAll();
    
    if ($with_stats) {
        foreach ($machines as &$machine) {
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as record_count,
                    MAX(record_date) as last_used,
                    AVG(steam_pressure) as avg_pressure
                FROM boiler_daily_records 
                WHERE machine_id = ?
            ");
            $stmt->execute([$machine['id']]);
            $stats = $stmt->fetch();
            $machine['stats'] = $stats;
        }
    }
    
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