<?php
/**
 * AJAX: Check if Reading Exists
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
    
    $meter_id = isset($_POST['meter_id']) ? (int)$_POST['meter_id'] : 0;
    $record_date = isset($_POST['record_date']) ? $_POST['record_date'] : '';
    $exclude_id = isset($_POST['exclude_id']) ? (int)$_POST['exclude_id'] : 0;
    
    if (!$meter_id || !$record_date) {
        echo json_encode(['exists' => false]);
        exit();
    }
    
    // Convert date from Thai format
    $dateObj = DateTime::createFromFormat('d/m/Y', $record_date);
    if (!$dateObj) {
        echo json_encode(['exists' => false]);
        exit();
    }
    $record_date_db = $dateObj->format('Y-m-d');
    
    $sql = "SELECT id FROM meter_daily_readings WHERE meter_id = ? AND record_date = ?";
    $params = [$meter_id, $record_date_db];
    
    if ($exclude_id) {
        $sql .= " AND id != ?";
        $params[] = $exclude_id;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode([
        'exists' => $stmt->fetch() ? true : false
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'exists' => false,
        'error' => $e->getMessage()
    ]);
}
?>