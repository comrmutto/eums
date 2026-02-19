<?php
/**
 * AJAX: Create Database Backup
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
    // Get parameters
    $filename = isset($_POST['filename']) ? trim($_POST['filename']) : '';
    $includeData = isset($_POST['include_data']) ? (bool)$_POST['include_data'] : true;
    $compress = isset($_POST['compress']) ? (bool)$_POST['compress'] : false;
    
    // Validate filename
    if (empty($filename)) {
        $filename = 'backup_' . date('Ymd_His');
    }
    
    // Sanitize filename
    $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '', $filename);
    if (empty($filename)) {
        $filename = 'backup_' . date('Ymd_His');
    }
    
    // Get database configuration
    $config = require __DIR__ . '/../../config/database.php';
    $dbConfig = $config['connection'];
    
    // Backup directory
    $backupDir = __DIR__ . '/../../backups/';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    // Set file paths
    $sqlFile = $backupDir . $filename . '.sql';
    $outputFile = $sqlFile;
    
    if ($compress) {
        $outputFile = $backupDir . $filename . '.sql.gz';
    }
    
    // Check if file already exists
    if (file_exists($outputFile)) {
        throw new Exception("ไฟล์ $filename มีอยู่แล้ว กรุณาใช้ชื่ออื่น");
    }
    
    // Build mysqldump command
    $command = sprintf(
        'mysqldump --host=%s --port=%s --user=%s --password=%s %s %s %s 2>&1',
        escapeshellarg($dbConfig['host']),
        escapeshellarg($dbConfig['port']),
        escapeshellarg($dbConfig['username']),
        escapeshellarg($dbConfig['password']),
        $includeData ? '' : '--no-data',
        escapeshellarg($dbConfig['database']),
        $compress ? '| gzip' : ''
    );
    
    // For Windows, use different approach
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $command = sprintf(
            '"C:\\xampp\\mysql\\bin\\mysqldump" --host=%s --port=%s --user=%s --password=%s %s %s',
            $dbConfig['host'],
            $dbConfig['port'],
            $dbConfig['username'],
            $dbConfig['password'],
            $includeData ? '' : '--no-data',
            $dbConfig['database']
        );
        
        if ($compress) {
            // For Windows, we'll create SQL first then compress
            $tempFile = $backupDir . $filename . '_temp.sql';
            $command .= ' > ' . escapeshellarg($tempFile);
            
            // Execute mysqldump
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception("เกิดข้อผิดพลาดในการสำรองข้อมูล");
            }
            
            // Compress the file
            $gz = gzopen($outputFile, 'w9');
            gzwrite($gz, file_get_contents($tempFile));
            gzclose($gz);
            
            // Delete temp file
            unlink($tempFile);
            
            $fileSize = filesize($outputFile);
        } else {
            $command .= ' > ' . escapeshellarg($sqlFile);
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception("เกิดข้อผิดพลาดในการสำรองข้อมูล");
            }
            
            $fileSize = filesize($sqlFile);
        }
    } else {
        // Linux/Unix
        if ($compress) {
            $command = sprintf(
                'mysqldump --host=%s --port=%s --user=%s --password=%s %s %s | gzip > %s',
                escapeshellarg($dbConfig['host']),
                escapeshellarg($dbConfig['port']),
                escapeshellarg($dbConfig['username']),
                escapeshellarg($dbConfig['password']),
                $includeData ? '' : '--no-data',
                escapeshellarg($dbConfig['database']),
                escapeshellarg($outputFile)
            );
        } else {
            $command = sprintf(
                'mysqldump --host=%s --port=%s --user=%s --password=%s %s %s > %s',
                escapeshellarg($dbConfig['host']),
                escapeshellarg($dbConfig['port']),
                escapeshellarg($dbConfig['username']),
                escapeshellarg($dbConfig['password']),
                $includeData ? '' : '--no-data',
                escapeshellarg($dbConfig['database']),
                escapeshellarg($sqlFile)
            );
        }
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("เกิดข้อผิดพลาดในการสำรองข้อมูล");
        }
        
        $fileSize = filesize($outputFile);
    }
    
    // Get list of tables
    $db = getDB();
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Log activity
    logActivity($_SESSION['user_id'], 'create_backup', 
               "สร้างไฟล์สำรอง: $filename (" . formatBytes($fileSize) . ")");
    
    echo json_encode([
        'success' => true,
        'message' => 'สำรองข้อมูลเรียบร้อย',
        'filename' => basename($outputFile),
        'size' => $fileSize,
        'size_formatted' => formatBytes($fileSize),
        'path' => $outputFile,
        'tables' => count($tables),
        'include_data' => $includeData,
        'compressed' => $compress,
        'created_at' => date('Y-m-d H:i:s')
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