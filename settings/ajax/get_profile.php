<?php
/**
 * AJAX: Get User Profile
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

try {
    $db = getDB();
    
    // Get user profile
    $stmt = $db->prepare("
        SELECT id, username, fullname, email, role, status, 
               created_at, last_login,
               (SELECT COUNT(*) FROM activity_logs WHERE user_id = users.id) as activity_count
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('ไม่พบข้อมูลผู้ใช้');
    }
    
    // Get recent activity
    $stmt = $db->prepare("
        SELECT action, details, ip_address, created_at
        FROM activity_logs
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_activity = $stmt->fetchAll();
    
    // Format dates
    $user['created_at_formatted'] = date('d/m/Y H:i', strtotime($user['created_at']));
    $user['last_login_formatted'] = $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '-';
    
    foreach ($recent_activity as &$activity) {
        $activity['created_at_formatted'] = date('d/m/Y H:i', strtotime($activity['created_at']));
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'profile' => $user,
            'recent_activity' => $recent_activity
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>