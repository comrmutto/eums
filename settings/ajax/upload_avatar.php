<?php
/**
 * AJAX: Upload User Avatar
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
    // Check if file was uploaded
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'ไฟล์มีขนาดใหญ่เกินไป',
            UPLOAD_ERR_FORM_SIZE => 'ไฟล์มีขนาดใหญ่เกินไป',
            UPLOAD_ERR_PARTIAL => 'ไฟล์ถูกอัปโหลดมาเพียงบางส่วน',
            UPLOAD_ERR_NO_FILE => 'กรุณาเลือกไฟล์',
            UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบโฟลเดอร์ชั่วคราว',
            UPLOAD_ERR_CANT_WRITE => 'ไม่สามารถเขียนไฟล์ได้',
            UPLOAD_ERR_EXTENSION => 'ส่วนขยายไม่อนุญาตให้อัปโหลด'
        ];
        
        $error_code = $_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE;
        $error_message = $upload_errors[$error_code] ?? 'ไม่ทราบข้อผิดพลาด';
        
        throw new Exception($error_message);
    }
    
    $file = $_FILES['avatar'];
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('ไฟล์ต้องเป็นรูปภาพ (JPEG, PNG, GIF) เท่านั้น');
    }
    
    // Validate file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception('ไฟล์ต้องมีขนาดไม่เกิน 2MB');
    }
    
    // Create avatars directory if not exists
    $avatar_dir = __DIR__ . '/../../uploads/avatars/';
    if (!file_exists($avatar_dir)) {
        mkdir($avatar_dir, 0755, true);
    }
    
    // Generate filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
    $filepath = $avatar_dir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('ไม่สามารถบันทึกไฟล์ได้');
    }
    
    // Create thumbnail
    list($width, $height) = getimagesize($filepath);
    $thumb_size = 150;
    $thumb_path = $avatar_dir . 'thumb_' . $filename;
    
    switch ($mime_type) {
        case 'image/jpeg':
            $src = imagecreatefromjpeg($filepath);
            break;
        case 'image/png':
            $src = imagecreatefrompng($filepath);
            break;
        case 'image/gif':
            $src = imagecreatefromgif($filepath);
            break;
        default:
            throw new Exception('ไม่รองรับรูปแบบไฟล์นี้');
    }
    
    $thumb = imagecreatetruecolor($thumb_size, $thumb_size);
    
    // Preserve transparency for PNG
    if ($mime_type == 'image/png') {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
        imagefilledrectangle($thumb, 0, 0, $thumb_size, $thumb_size, $transparent);
    }
    
    imagecopyresampled($thumb, $src, 0, 0, 0, 0, $thumb_size, $thumb_size, $width, $height);
    
    // Save thumbnail
    switch ($mime_type) {
        case 'image/jpeg':
            imagejpeg($thumb, $thumb_path, 90);
            break;
        case 'image/png':
            imagepng($thumb, $thumb_path, 9);
            break;
        case 'image/gif':
            imagegif($thumb, $thumb_path);
            break;
    }
    
    imagedestroy($src);
    imagedestroy($thumb);
    
    // Delete old avatars
    $old_files = glob($avatar_dir . 'avatar_' . $_SESSION['user_id'] . '_*');
    foreach ($old_files as $old_file) {
        if ($old_file != $filepath && $old_file != $thumb_path) {
            @unlink($old_file);
        }
    }
    
    // Update user profile with avatar path
    $db = getDB();
    $avatar_url = '/uploads/avatars/' . $filename;
    $thumb_url = '/uploads/avatars/thumb_' . $filename;
    
    // You might want to store avatar path in database
    // $stmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    // $stmt->execute([$avatar_url, $_SESSION['user_id']]);
    
    // Log activity
    logActivity($_SESSION['user_id'], 'upload_avatar', 'อัปโหลดรูปโปรไฟล์');
    
    echo json_encode([
        'success' => true,
        'message' => 'อัปโหลดรูปโปรไฟล์เรียบร้อย',
        'data' => [
            'avatar' => $avatar_url,
            'thumbnail' => $thumb_url,
            'filename' => $filename,
            'size' => $file['size']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>