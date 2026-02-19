<?php
/**
 * AJAX: Delete User
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

// Check permission (เฉพาะ admin)
if ($_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'คุณไม่มีสิทธิ์ดำเนินการนี้']);
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
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if (!$id) {
        throw new Exception('ไม่พบผู้ใช้ที่ต้องการลบ');
    }
    
    // Cannot delete yourself
    if ($id == $_SESSION['user_id']) {
        throw new Exception('ไม่สามารถลบบัญชีของตัวเองได้');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Get user details for logging
    $stmt = $db->prepare("SELECT username, fullname FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('ไม่พบผู้ใช้ในระบบ');
    }
    
    // Check if user has any activity logs
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM activity_logs WHERE user_id = ?");
    $stmt->execute([$id]);
    $logCount = $stmt->fetch()['count'];
    
    if ($logCount > 0) {
        // Option: Delete logs or just keep them
        // Here we'll keep the logs but set user_id to NULL
        $stmt = $db->prepare("UPDATE activity_logs SET user_id = NULL WHERE user_id = ?");
        $stmt->execute([$id]);
    }
    
    // Delete user tokens
    $stmt = $db->prepare("DELETE FROM user_tokens WHERE user_id = ?");
    $stmt->execute([$id]);
    
    // Delete user permissions
    $stmt = $db->prepare("DELETE FROM user_permissions WHERE user_id = ?");
    $stmt->execute([$id]);
    
    // Delete user
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log activity
    logActivity($_SESSION['user_id'], 'delete_user', 
               "ลบผู้ใช้ ID: $id, Username: {$user['username']}, Name: {$user['fullname']}");
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'ลบผู้ใช้เรียบร้อย'
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