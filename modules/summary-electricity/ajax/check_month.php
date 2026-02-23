<?php
/**
 * AJAX: Check if month record exists
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

header('Content-Type: application/json');

try {
    $db = getDB();
    
    $month = isset($_POST['month']) ? (int)$_POST['month'] : 0;
    $year = isset($_POST['year']) ? (int)$_POST['year'] : 0;
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if (!$month || !$year) {
        echo json_encode(['exists' => false]);
        exit();
    }
    
    $record_date = sprintf("%04d-%02d-01", $year, $month);
    
    $sql = "SELECT id FROM electricity_summary WHERE record_date = ?";
    $params = [$record_date];
    
    if ($id > 0) {
        $sql .= " AND id != ?";
        $params[] = $id;
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