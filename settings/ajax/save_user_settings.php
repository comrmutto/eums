<?php
/**
 * AJAX: Save User Settings
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
    $notification = isset($_POST['notification']) ? filter_var($_POST['notification'], FILTER_VALIDATE_BOOLEAN) : true;
    $language = isset($_POST['language']) ? $_POST['language'] : 'th';
    $timezone = isset($_POST['timezone']) ? $_POST['timezone'] : 'Asia/Bangkok';
    $items_per_page = isset($_POST['itemsPerPage']) ? (int)$_POST['itemsPerPage'] : 25;
    $theme = isset($_POST['theme']) ? $_POST['theme'] : 'light';
    
    // Validate
    if (!in_array($language, ['th', 'en'])) {
        throw new Exception('ภาษาไม่ถูกต้อง');
    }
    
    if (!in_array($timezone, timezone_identifiers_list())) {
        throw new Exception('เขตเวลาไม่ถูกต้อง');
    }
    
    if (!in_array($items_per_page, [10, 25, 50, 100])) {
        $items_per_page = 25;
    }
    
    if (!in_array($theme, ['light', 'dark'])) {
        throw new Exception('ธีมไม่ถูกต้อง');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Check if user settings table exists, if not create it
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_settings (
            user_id INT PRIMARY KEY,
            notification TINYINT(1) DEFAULT 1,
            language VARCHAR(5) DEFAULT 'th',
            timezone VARCHAR(50) DEFAULT 'Asia/Bangkok',
            items_per_page INT DEFAULT 25,
            theme VARCHAR(10) DEFAULT 'light',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Save settings
    $stmt = $db->prepare("
        INSERT INTO user_settings (user_id, notification, language, timezone, items_per_page, theme)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            notification = VALUES(notification),
            language = VALUES(language),
            timezone = VALUES(timezone),
            items_per_page = VALUES(items_per_page),
            theme = VALUES(theme)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $notification ? 1 : 0,
        $language,
        $timezone,
        $items_per_page,
        $theme
    ]);
    
    // Update session if needed
    $_SESSION['user_settings'] = [
        'notification' => $notification,
        'language' => $language,
        'timezone' => $timezone,
        'items_per_page' => $items_per_page,
        'theme' => $theme
    ];
    
    // Log activity
    logActivity($_SESSION['user_id'], 'update_settings', 'อัปเดตการตั้งค่าผู้ใช้');
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'บันทึกการตั้งค่าเรียบร้อย',
        'data' => [
            'notification' => $notification,
            'language' => $language,
            'timezone' => $timezone,
            'items_per_page' => $items_per_page,
            'theme' => $theme
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