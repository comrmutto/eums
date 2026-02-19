<?php
/**
 * AJAX: Update User Profile
 * Engineering Utility Monitoring System (EUMS)
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
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
    $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    // Validate
    if (empty($fullname)) {
        throw new Exception('กรุณาระบุชื่อ-นามสกุล');
    }
    
    if (empty($email)) {
        throw new Exception('กรุณาระบุอีเมล');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('รูปแบบอีเมลไม่ถูกต้อง');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Check if email is already used by another user
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        throw new Exception('อีเมลนี้มีผู้ใช้งานแล้ว');
    }
    
    // Get current user data for logging
    $stmt = $db->prepare("SELECT fullname, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $oldData = $stmt->fetch();
    
    // Update profile
    $stmt = $db->prepare("UPDATE users SET fullname = ?, email = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$fullname, $email, $_SESSION['user_id']]);
    
    if (!$result) {
        throw new Exception('ไม่สามารถอัปเดตโปรไฟล์ได้');
    }
    
    // Update session
    $_SESSION['fullname'] = $fullname;
    $_SESSION['user_email'] = $email;
    
    // Log changes
    $changes = [];
    if ($oldData['fullname'] != $fullname) {
        $changes[] = "ชื่อ: {$oldData['fullname']} -> $fullname";
    }
    if ($oldData['email'] != $email) {
        $changes[] = "อีเมล: {$oldData['email']} -> $email";
    }
    
    if (!empty($changes)) {
        logActivity($_SESSION['user_id'], 'update_profile', 'อัปเดตโปรไฟล์: ' . implode(', ', $changes));
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'อัปเดตโปรไฟล์เรียบร้อย',
        'data' => [
            'fullname' => $fullname,
            'email' => $email
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>