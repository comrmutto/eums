<?php
/**
 * AJAX: Check if Email Exists
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
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if (empty($email)) {
        echo json_encode(['exists' => false]);
        exit();
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'exists' => false,
            'valid' => false,
            'message' => 'รูปแบบอีเมลไม่ถูกต้อง'
        ]);
        exit();
    }
    
    // Check if email exists
    $sql = "SELECT id FROM users WHERE email = ?";
    $params = [$email];
    
    if ($id > 0) {
        $sql .= " AND id != ?";
        $params[] = $id;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $exists = $stmt->fetch() ? true : false;
    
    // Get email domain for additional info
    $domain = substr(strrchr($email, "@"), 1);
    
    echo json_encode([
        'exists' => $exists,
        'valid' => true,
        'email' => $email,
        'domain' => $domain,
        'message' => $exists ? 'อีเมลนี้มีผู้ใช้งานแล้ว' : 'สามารถใช้อีเมลนี้ได้'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'exists' => false,
        'error' => $e->getMessage()
    ]);
}
?>