<?php
/**
 * AJAX: Check if Document Number Exists
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
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Set header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $db = getDB();
    
    // Get POST data
    $doc_no = isset($_POST['doc_no']) ? trim($_POST['doc_no']) : '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if (empty($doc_no)) {
        echo json_encode(['exists' => false]);
        exit();
    }
    
    // Check if document number exists
    $sql = "SELECT id FROM documents WHERE doc_no = ?";
    $params = [$doc_no];
    
    if ($id > 0) {
        $sql .= " AND id != ?";
        $params[] = $id;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $exists = $stmt->fetch() ? true : false;
    
    echo json_encode([
        'exists' => $exists,
        'doc_no' => $doc_no
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'exists' => false,
        'error' => $e->getMessage()
    ]);
}
?>