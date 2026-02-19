<?php
/**
 * AJAX: Check if Username Exists
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
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if (empty($username)) {
        echo json_encode(['exists' => false]);
        exit();
    }
    
    // Validate username format
    $errors = [];
    
    if (strlen($username) < 3) {
        $errors[] = 'ชื่อผู้ใช้ต้องมีความยาวอย่างน้อย 3 ตัวอักษร';
    }
    
    if (strlen($username) > 50) {
        $errors[] = 'ชื่อผู้ใช้ต้องมีความยาวไม่เกิน 50 ตัวอักษร';
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'ชื่อผู้ใช้สามารถใช้ได้เฉพาะตัวอักษร ตัวเลข และ _ เท่านั้น';
    }
    
    if (!preg_match('/^[a-zA-Z]/', $username)) {
        $errors[] = 'ชื่อผู้ใช้ต้องขึ้นต้นด้วยตัวอักษร';
    }
    
    // Check if username exists
    $sql = "SELECT id FROM users WHERE username = ?";
    $params = [$username];
    
    if ($id > 0) {
        $sql .= " AND id != ?";
        $params[] = $id;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $exists = $stmt->fetch() ? true : false;
    
    if ($exists) {
        $errors[] = 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว';
    }
    
    echo json_encode([
        'exists' => $exists,
        'valid' => empty($errors),
        'errors' => $errors,
        'username' => $username
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'exists' => false,
        'error' => $e->getMessage()
    ]);
}
?>