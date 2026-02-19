<?php
/**
 * AJAX: Restore Database from Backup
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
    $db = getDB();
    
    // Check if it's file upload or existing file
    if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        // Handle file upload
        $uploadedFile = $_FILES['backup_file'];
        $tmpPath = $uploadedFile['tmp_name'];
        $originalName = $uploadedFile['name'];
        
        // Validate file type
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['sql', 'gz', 'zip'])) {
            throw new Exception('ไฟล์ต้องเป็น .sql, .gz หรือ .zip เท่านั้น');
        }
        
        // Validate file size (max 100MB)
        if ($uploadedFile['size'] > 100 * 1024 * 1024) {
            throw new Exception('ไฟล์มีขนาดใหญ่เกินไป (ไม่เกิน 100MB)');
        }
        
        $sqlContent = file_get_contents($tmpPath);
        
        // If compressed, decompress
        if ($extension === 'gz') {
            $sqlContent = gzdecode($sqlContent);
            if ($sqlContent === false) {
                throw new Exception('ไม่สามารถแตกไฟล์ .gz ได้');
            }
        } elseif ($extension === 'zip') {
            $zip = new ZipArchive();
            if ($zip->open($tmpPath) === true) {
                // Assume first file is the SQL
                $sqlContent = $zip->getFromIndex(0);
                $zip->close();
                if ($sqlContent === false) {
                    throw new Exception('ไม่สามารถแตกไฟล์ .zip ได้');
                }
            } else {
                throw new Exception('ไม่สามารถเปิดไฟล์ .zip ได้');
            }
        }
        
    } elseif (isset($_POST['filename'])) {
        // Use existing backup file
        $filename = basename($_POST['filename']);
        $backupDir = __DIR__ . '/../../backups/';
        $filePath = $backupDir . $filename;
        
        // Security check
        $realPath = realpath($filePath);
        $realBackupDir = realpath($backupDir);
        
        if ($realPath === false || strpos($realPath, $realBackupDir) !== 0) {
            throw new Exception('ไฟล์ไม่อยู่ในโฟลเดอร์ที่กำหนด');
        }
        
        if (!file_exists($filePath)) {
            throw new Exception('ไม่พบไฟล์สำรอง');
        }
        
        // Read file content
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $sqlContent = file_get_contents($filePath);
        
        if ($extension === 'gz') {
            $sqlContent = gzdecode($sqlContent);
            if ($sqlContent === false) {
                throw new Exception('ไม่สามารถแตกไฟล์ .gz ได้');
            }
        } elseif ($extension === 'zip') {
            $zip = new ZipArchive();
            if ($zip->open($filePath) === true) {
                $sqlContent = $zip->getFromIndex(0);
                $zip->close();
                if ($sqlContent === false) {
                    throw new Exception('ไม่สามารถแตกไฟล์ .zip ได้');
                }
            } else {
                throw new Exception('ไม่สามารถเปิดไฟล์ .zip ได้');
            }
        }
        
    } else {
        throw new Exception('กรุณาเลือกไฟล์สำรอง');
    }
    
    // Check if overwrite existing data
    $overwrite = isset($_POST['overwrite']) ? (bool)$_POST['overwrite'] : false;
    
    if (!$overwrite) {
        // Check if database has data
        $stmt = $db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($tables)) {
            echo json_encode([
                'success' => false,
                'has_data' => true,
                'message' => 'ฐานข้อมูลมีข้อมูลอยู่แล้ว กรุณาเลือกเขียนทับหรือสำรองข้อมูลก่อน'
            ]);
            exit();
        }
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Split SQL into individual queries
    $queries = explode(';', $sqlContent);
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) continue;
        
        try {
            $db->exec($query);
            $successCount++;
        } catch (PDOException $e) {
            $errorCount++;
            $errors[] = "Query failed: " . substr($query, 0, 100) . "... - " . $e->getMessage();
            
            // Rollback if not overwrite mode
            if (!$overwrite) {
                throw new Exception('เกิดข้อผิดพลาดในการกู้คืนข้อมูล: ' . $e->getMessage());
            }
        }
    }
    
    // Commit transaction if no errors or in overwrite mode
    if ($overwrite || $errorCount === 0) {
        $db->commit();
    } else {
        $db->rollBack();
        throw new Exception('เกิดข้อผิดพลาดในการกู้คืนข้อมูล');
    }
    
    // Log activity
    logActivity($_SESSION['user_id'], 'restore_backup', 
               "กู้คืนข้อมูลจากไฟล์สำรอง (สำเร็จ: $successCount, ล้มเหลว: $errorCount)");
    
    echo json_encode([
        'success' => true,
        'message' => 'กู้คืนข้อมูลเรียบร้อย',
        'queries_success' => $successCount,
        'queries_error' => $errorCount,
        'errors' => $errors
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