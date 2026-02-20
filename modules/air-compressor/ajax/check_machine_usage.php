<?php
/**
 * AJAX: Check if Air Compressor Machine has Usage Data
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
    
    // Check standards
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM air_inspection_standards WHERE machine_id = ?");
    $stmt->execute([$id]);
    $standardCount = $stmt->fetch()['count'];
    
    // Check records
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM air_daily_records WHERE machine_id = ?");
    $stmt->execute([$id]);
    $recordCount = $stmt->fetch()['count'];
    
    // Get last used date
    $stmt = $db->prepare("SELECT MAX(record_date) as last_used FROM air_daily_records WHERE machine_id = ?");
    $stmt->execute([$id]);
    $lastUsed = $stmt->fetch()['last_used'];
    
    echo json_encode([
        'has_usage' => ($standardCount > 0 || $recordCount > 0),
        'standard_count' => $standardCount,
        'record_count' => $recordCount,
        'last_used' => $lastUsed,
        'last_used_thai' => $lastUsed ? date('d/m/Y', strtotime($lastUsed)) : null
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'has_usage' => false,
        'error' => $e->getMessage()
    ]);
}
?>