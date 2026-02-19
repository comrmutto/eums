<?php
/**
 * Authentication Handler
 * Engineering Utility Monitoring System (EUMS)
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Load required files - ลำดับการโหลดสำคัญ!
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_functions.php';

// Set header for JSON response if AJAX request
$isAjax = isset($_POST['ajax']) && $_POST['ajax'] == 1;

if ($isAjax) {
    header('Content-Type: application/json');
}

try {
    // Get POST data
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $remember = isset($_POST['remember']) ? (int)$_POST['remember'] : 0;
    
    // Validate input
    if (empty($username) || empty($password)) {
        throw new Exception('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน');
    }
    
    // Attempt login - เรียกใช้ฟังก์ชันจาก auth_functions.php
    $result = authenticateUser($username, $password, $remember);
    
    if ($result['success']) {
        if ($isAjax) {
            echo json_encode([
                'success' => true,
                'message' => 'เข้าสู่ระบบสำเร็จ กำลังนำท่านไปยังหน้าหลัก...',
                'redirect' => 'index.php',
                'user' => [
                    'name' => $_SESSION['fullname'],
                    'role' => $_SESSION['user_role']
                ]
            ]);
        } else {
            $_SESSION['success'] = 'เข้าสู่ระบบสำเร็จ';
            header('Location: index.php');
        }
        exit();
    } else {
        throw new Exception($result['message']);
    }
    
} catch (Exception $e) {
    // Log failed attempt
    if (isset($username)) {
        @logFailedAttempt($username, $_SERVER['REMOTE_ADDR'] ?? '');
    }
    
    if ($isAjax) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    } else {
        $_SESSION['error'] = $e->getMessage();
        header('Location: login.php');
    }
    exit();
}
?>