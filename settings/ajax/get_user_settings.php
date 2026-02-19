<?php
/**
 * AJAX: Get User Settings
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
    
    // Check if user settings table exists
    $stmt = $db->query("SHOW TABLES LIKE 'user_settings'");
    if ($stmt->rowCount() == 0) {
        // Return default settings
        echo json_encode([
            'success' => true,
            'data' => [
                'notification' => true,
                'language' => 'th',
                'timezone' => 'Asia/Bangkok',
                'items_per_page' => 25,
                'theme' => 'light'
            ]
        ]);
        exit();
    }
    
    // Get user settings
    $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $settings = $stmt->fetch();
    
    if (!$settings) {
        // Return default settings
        $settings = [
            'notification' => true,
            'language' => 'th',
            'timezone' => 'Asia/Bangkok',
            'items_per_page' => 25,
            'theme' => 'light'
        ];
    } else {
        // Convert to proper types
        $settings['notification'] = (bool)$settings['notification'];
        $settings['items_per_page'] = (int)$settings['items_per_page'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $settings
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>