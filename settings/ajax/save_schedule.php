<?php
/**
 * AJAX: Save Backup Schedule Settings
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
require_once __DIR__ . '/../../includes/functions.php';

// Set header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get parameters
    $frequency = isset($_POST['frequency']) ? $_POST['frequency'] : 'daily';
    $time = isset($_POST['time']) ? $_POST['time'] : '02:00';
    $keepDays = isset($_POST['keep_days']) ? (int)$_POST['keep_days'] : 30;
    
    // Validate
    if (!in_array($frequency, ['daily', 'weekly', 'monthly', 'never'])) {
        throw new Exception('ความถี่ไม่ถูกต้อง');
    }
    
    if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
        throw new Exception('รูปแบบเวลาไม่ถูกต้อง');
    }
    
    if ($keepDays < 0) {
        throw new Exception('จำนวนวันต้องมากกว่าหรือเท่ากับ 0');
    }
    
    // Calculate next backup time
    $now = new DateTime();
    $nextBackup = null;
    
    if ($frequency !== 'never') {
        list($hour, $minute) = explode(':', $time);
        $nextBackup = clone $now;
        $nextBackup->setTime($hour, $minute, 0);
        
        if ($nextBackup <= $now) {
            if ($frequency === 'daily') {
                $nextBackup->modify('+1 day');
            } elseif ($frequency === 'weekly') {
                $nextBackup->modify('+1 week');
            } elseif ($frequency === 'monthly') {
                $nextBackup->modify('+1 month');
            }
        }
    }
    
    // Save schedule
    $schedule = [
        'frequency' => $frequency,
        'time' => $time,
        'keep_days' => $keepDays,
        'last_backup' => null,
        'next_backup' => $nextBackup ? $nextBackup->format('Y-m-d H:i:s') : null,
        'updated_at' => $now->format('Y-m-d H:i:s')
    ];
    
    $scheduleFile = __DIR__ . '/../../config/backup_schedule.json';
    file_put_contents($scheduleFile, json_encode($schedule, JSON_PRETTY_PRINT));
    
    // Log activity
    logActivity($_SESSION['user_id'], 'save_backup_schedule', 
               "บันทึกการตั้งค่าสำรองข้อมูล: $frequency ที่ $time เก็บ $keepDays วัน");
    
    echo json_encode([
        'success' => true,
        'message' => 'บันทึกการตั้งค่าเรียบร้อย',
        'data' => $schedule
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>