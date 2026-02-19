<?php
/**
 * AJAX: Delete Backup File
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
    $filename = isset($_POST['filename']) ? trim($_POST['filename']) : '';
    
    if (empty($filename)) {
        throw new Exception('กรุณาระบุชื่อไฟล์');
    }
    
    // Sanitize filename to prevent directory traversal
    $filename = basename($filename);
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        throw new Exception('ชื่อไฟล์ไม่ถูกต้อง');
    }
    
    // Backup directory
    $backupDir = __DIR__ . '/../../backups/';
    $filePath = $backupDir . $filename;
    
    // Check if file exists
    if (!file_exists($filePath)) {
        throw new Exception('ไม่พบไฟล์ที่ต้องการลบ');
    }
    
    // Check if file is within backup directory
    $realPath = realpath($filePath);
    $realBackupDir = realpath($backupDir);
    
    if ($realPath === false || strpos($realPath, $realBackupDir) !== 0) {
        throw new Exception('ไฟล์ไม่อยู่ในโฟลเดอร์ที่กำหนด');
    }
    
    // Check if file is writable
    if (!is_writable($filePath)) {
        throw new Exception('ไม่สามารถลบไฟล์ได้ (ไฟล์ถูกป้องกันการเขียน)');
    }
    
    // Check if file is in use
    if (is_resource($filePath) || !is_file($filePath)) {
        throw new Exception('ไม่สามารถลบไฟล์ได้ (ไฟล์กำลังถูกใช้งาน)');
    }
    
    // Get file size before deletion
    $fileSize = filesize($filePath);
    $fileInfo = pathinfo($filePath);
    
    // Try to delete
    if (unlink($filePath)) {
        // Log activity
        logActivity($_SESSION['user_id'], 'delete_backup', 
                   "ลบไฟล์สำรอง: $filename (" . formatBytes($fileSize) . ")");
        
        echo json_encode([
            'success' => true,
            'message' => 'ลบไฟล์เรียบร้อย',
            'filename' => $filename,
            'size' => $fileSize,
            'size_formatted' => formatBytes($fileSize),
            'extension' => $fileInfo['extension'] ?? 'unknown'
        ]);
    } else {
        throw new Exception('ไม่สามารถลบไฟล์ได้');
    }
    
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