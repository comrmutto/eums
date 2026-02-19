<?php
/**
 * Download Backup File
 * Engineering Utility Monitoring System (EUMS)
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /eums/login.php');
    exit();
}

// Check permission (เฉพาะ admin)
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: /eums/index.php');
    exit();
}

// Get filename
$filename = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($filename)) {
    die('ไม่พบไฟล์ที่ต้องการ');
}

// Sanitize filename
$filename = basename($filename);
$backupDir = __DIR__ . '/../../backups/';
$filePath = $backupDir . $filename;

// Security check
$realPath = realpath($filePath);
$realBackupDir = realpath($backupDir);

if ($realPath === false || strpos($realPath, $realBackupDir) !== 0) {
    die('ไฟล์ไม่อยู่ในโฟลเดอร์ที่กำหนด');
}

if (!file_exists($filePath)) {
    die('ไม่พบไฟล์');
}

// Set headers for download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

// Clear output buffer
ob_clean();
flush();

// Read file
readfile($filePath);

// Log download
require_once __DIR__ . '/../../includes/functions.php';
logActivity($_SESSION['user_id'], 'download_backup', "ดาวน์โหลดไฟล์สำรอง: $filename");

exit();
?>