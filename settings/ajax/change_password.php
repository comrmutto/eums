<?php
/**
 * AJAX: Change User Password
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
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Validate input
    if (empty($current_password)) {
        throw new Exception('กรุณากรอกรหัสผ่านเดิม');
    }
    
    if (empty($new_password)) {
        throw new Exception('กรุณากรอกรหัสผ่านใหม่');
    }
    
    if (empty($confirm_password)) {
        throw new Exception('กรุณายืนยันรหัสผ่านใหม่');
    }
    
    if ($new_password !== $confirm_password) {
        throw new Exception('รหัสผ่านใหม่ไม่ตรงกัน');
    }
    
    // Check password length
    $min_length = config('security.password_min_length', 8);
    if (strlen($new_password) < $min_length) {
        throw new Exception("รหัสผ่านต้องมีความยาวอย่างน้อย {$min_length} ตัวอักษร");
    }
    
    // Get current user's password
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_hash = $stmt->fetchColumn();
    
    if (!$current_hash) {
        throw new Exception('ไม่พบข้อมูลผู้ใช้');
    }
    
    // Verify current password
    if (!password_verify($current_password, $current_hash)) {
        // Log failed attempt
        logActivity($_SESSION['user_id'], 'password_change_failed', 'รหัสผ่านเดิมไม่ถูกต้อง');
        throw new Exception('รหัสผ่านเดิมไม่ถูกต้อง');
    }
    
    // Check if new password is same as old
    if (password_verify($new_password, $current_hash)) {
        throw new Exception('รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านเดิม');
    }
    
    // Check password strength
    $strength = 0;
    if (preg_match('/[a-z]/', $new_password)) $strength++;
    if (preg_match('/[A-Z]/', $new_password)) $strength++;
    if (preg_match('/[0-9]/', $new_password)) $strength++;
    if (preg_match('/[^a-zA-Z0-9]/', $new_password)) $strength++;
    
    if ($strength < 2) {
        throw new Exception('รหัสผ่านควรประกอบด้วยตัวพิมพ์ใหญ่ ตัวพิมพ์เล็ก ตัวเลข และอักขระพิเศษ');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Hash new password
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password
    $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$new_hash, $_SESSION['user_id']]);
    
    if (!$result) {
        throw new Exception('ไม่สามารถเปลี่ยนรหัสผ่านได้');
    }
    
    // Log success
    logActivity($_SESSION['user_id'], 'password_changed', 'เปลี่ยนรหัสผ่านสำเร็จ');
    
    // Commit transaction
    $db->commit();
    
    // Clear remember me tokens for security
    $stmt = $db->prepare("DELETE FROM user_tokens WHERE user_id = ? AND type = 'remember'");
    $stmt->execute([$_SESSION['user_id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'เปลี่ยนรหัสผ่านเรียบร้อย'
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