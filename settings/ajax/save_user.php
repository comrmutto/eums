<?php
/**
 * AJAX: Save User (Add/Edit)
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
    
    // Get POST data
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $role = isset($_POST['role']) ? $_POST['role'] : 'viewer';
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validate required fields
    if (empty($username)) {
        throw new Exception('กรุณาระบุชื่อผู้ใช้');
    }
    
    if (empty($fullname)) {
        throw new Exception('กรุณาระบุชื่อ-นามสกุล');
    }
    
    if (empty($email)) {
        throw new Exception('กรุณาระบุอีเมล');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('รูปแบบอีเมลไม่ถูกต้อง');
    }
    
    if (!in_array($role, ['admin', 'operator', 'viewer'])) {
        throw new Exception('บทบาทไม่ถูกต้อง');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Check duplicate username
    $sql = "SELECT id FROM users WHERE username = ?";
    $params = [$username];
    
    if ($id > 0) {
        $sql .= " AND id != ?";
        $params[] = $id;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->fetch()) {
        throw new Exception('ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว');
    }
    
    // Check duplicate email
    $sql = "SELECT id FROM users WHERE email = ?";
    $params = [$email];
    
    if ($id > 0) {
        $sql .= " AND id != ?";
        $params[] = $id;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->fetch()) {
        throw new Exception('อีเมลนี้มีอยู่ในระบบแล้ว');
    }
    
    if ($id > 0) {
        // Update existing user
        $sql = "UPDATE users SET fullname = ?, email = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?";
        $params = [$fullname, $email, $role, $status, $id];
        
        // If password is provided, update it
        if (!empty($password)) {
            if (strlen($password) < config('security.password_min_length', 8)) {
                throw new Exception('รหัสผ่านต้องมีความยาวอย่างน้อย ' . config('security.password_min_length', 8) . ' ตัวอักษร');
            }
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET fullname = ?, email = ?, role = ?, status = ?, password = ?, updated_at = NOW() WHERE id = ?";
            $params = [$fullname, $email, $role, $status, $password_hash, $id];
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        logActivity($_SESSION['user_id'], 'edit_user', "แก้ไขผู้ใช้ ID: $id");
        
    } else {
        // Add new user
        if (empty($password)) {
            throw new Exception('กรุณาระบุรหัสผ่านสำหรับผู้ใช้ใหม่');
        }
        
        if (strlen($password) < config('security.password_min_length', 8)) {
            throw new Exception('รหัสผ่านต้องมีความยาวอย่างน้อย ' . config('security.password_min_length', 8) . ' ตัวอักษร');
        }
        
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("
            INSERT INTO users (username, password, fullname, email, role, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$username, $password_hash, $fullname, $email, $role, $status]);
        
        $new_id = $db->lastInsertId();
        logActivity($_SESSION['user_id'], 'add_user', "เพิ่มผู้ใช้ใหม่ ID: $new_id, Username: $username");
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $id ? 'แก้ไขผู้ใช้เรียบร้อย' : 'เพิ่มผู้ใช้เรียบร้อย'
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