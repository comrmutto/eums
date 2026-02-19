<?php
/**
 * AJAX: Get Backup Schedule Settings
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
require_once __DIR__ . '/../../includes/functions.php';

// Set header
header('Content-Type: application/json');

try {
    // In a real application, these would be stored in database
    // For now, we'll use session or config file
    $scheduleFile = __DIR__ . '/../../config/backup_schedule.json';
    
    if (file_exists($scheduleFile)) {
        $schedule = json_decode(file_get_contents($scheduleFile), true);
    } else {
        // Default schedule
        $schedule = [
            'frequency' => 'daily',
            'time' => '02:00',
            'keep_days' => 30,
            'last_backup' => null,
            'next_backup' => null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $schedule
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>