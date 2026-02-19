<?php
/**
 * AJAX: Cleanup Old Backup Files
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
    $days = isset($_POST['days']) ? (int)$_POST['days'] : 30;
    
    if ($days < 1) {
        throw new Exception('จำนวนวันต้องมากกว่า 0');
    }
    
    // Backup directory
    $backupDir = __DIR__ . '/../../backups/';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    // Calculate cutoff time
    $cutoff = time() - ($days * 24 * 60 * 60);
    
    // Get all backup files
    $files = glob($backupDir . '*.{sql,sql.gz,zip}', GLOB_BRACE);
    $deleted = 0;
    $deletedFiles = [];
    $errors = [];
    
    foreach ($files as $file) {
        $fileTime = filemtime($file);
        
        if ($fileTime < $cutoff) {
            // Check if file is in use
            if (is_resource($file) || !is_writable($file)) {
                $errors[] = "ไม่สามารถลบไฟล์ " . basename($file) . " (ไฟล์กำลังถูกใช้งาน)";
                continue;
            }
            
            // Try to delete
            if (unlink($file)) {
                $deleted++;
                $deletedFiles[] = basename($file);
                
                // Log deletion
                error_log("Backup cleanup: Deleted " . basename($file));
            } else {
                $errors[] = "ไม่สามารถลบไฟล์ " . basename($file);
            }
        }
    }
    
    // Calculate freed space
    $freedSpace = 0;
    foreach ($deletedFiles as $file) {
        $freedSpace += filesize($backupDir . $file);
    }
    
    // Log activity
    logActivity($_SESSION['user_id'], 'cleanup_backups', 
               "ลบไฟล์สำรองที่เก่ากว่า $days วัน จำนวน $deleted ไฟล์ (" . formatBytes($freedSpace) . ")");
    
    echo json_encode([
        'success' => true,
        'message' => "ลบไฟล์สำรองที่เก่ากว่า $days วัน เรียบร้อย $deleted ไฟล์",
        'deleted' => $deleted,
        'deleted_files' => $deletedFiles,
        'freed_space' => $freedSpace,
        'freed_space_formatted' => formatBytes($freedSpace),
        'errors' => $errors,
        'cutoff_date' => date('Y-m-d H:i:s', $cutoff)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>