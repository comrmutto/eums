<?php
/**
 * API: Mark Notification as Read
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
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Set header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $db = getDB();
    
    $notificationId = isset($_POST['id']) ? $_POST['id'] : '';
    $userId = $_SESSION['user_id'];
    
    if (empty($notificationId)) {
        throw new Exception('Notification ID required');
    }
    
    // ในระบบจริงควรมีตาราง notifications เพื่อเก็บสถานะการอ่าน
    // สำหรับตัวอย่างนี้ เราจะจำลองการ mark as read โดยเก็บใน session
    
    if (!isset($_SESSION['read_notifications'])) {
        $_SESSION['read_notifications'] = [];
    }
    
    if (!in_array($notificationId, $_SESSION['read_notifications'])) {
        $_SESSION['read_notifications'][] = $notificationId;
    }
    
    // Keep only last 100
    $_SESSION['read_notifications'] = array_slice($_SESSION['read_notifications'], -100);
    
    echo json_encode([
        'success' => true,
        'message' => 'Notification marked as read'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>