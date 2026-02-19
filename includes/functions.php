<?php
/**
 * Global Functions
 * Engineering Utility Monitoring System (EUMS)
 */

// Load configuration
$GLOBALS['app_config'] = require __DIR__ . '/../config/app.php';

/**
 * Get configuration value using dot notation
 */
function config($key, $default = null) {
    $keys = explode('.', $key);
    $value = $GLOBALS['app_config'];
    
    foreach ($keys as $segment) {
        if (is_array($value) && isset($value[$segment])) {
            $value = $value[$segment];
        } else {
            return $default;
        }
    }
    
    return $value;
}

/**
 * Get database connection - FIXED VERSION
 */
function getDB() {
    static $db = null;
    
    if ($db === null) {
        try {
            // โหลดไฟล์ database.php และรับ instance จาก Database class
            require_once __DIR__ . '/../config/database.php';
            $database = Database::getInstance();
            $db = $database->getConnection();
            
            // ทดสอบการเชื่อมต่อ
            $db->query("SELECT 1");
            
        } catch (Exception $e) {
            error_log("getDB Error: " . $e->getMessage());
            die("Database connection error: " . $e->getMessage());
        }
    }
    
    return $db;
}

/**
 * Sanitize input data
 * 
 * @param mixed $input Input data
 * @return mixed Sanitized data
 */
if (!function_exists('sanitize')) {
    function sanitize($input) {
        if (is_array($input)) {
            return array_map('sanitize', $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Validate date format
 * 
 * @param string $date Date string
 * @param string $format Expected format
 * @return bool True if valid
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Format date for display
 * 
 * @param string $date Date string
 * @param string $format Output format
 * @return string Formatted date
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Get Thai month name
 * 
 * @param int $month Month number (1-12)
 * @return string Thai month name
 */
function getThaiMonth($month) {
    $months = [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม'
    ];
    return $months[$month] ?? '';
}

/**
 * Get Thai short month name
 * 
 * @param int $month Month number (1-12)
 * @return string Thai short month name
 */
function getThaiShortMonth($month) {
    $months = [
        1 => 'ม.ค.',
        2 => 'ก.พ.',
        3 => 'มี.ค.',
        4 => 'เม.ย.',
        5 => 'พ.ค.',
        6 => 'มิ.ย.',
        7 => 'ก.ค.',
        8 => 'ส.ค.',
        9 => 'ก.ย.',
        10 => 'ต.ค.',
        11 => 'พ.ย.',
        12 => 'ธ.ค.'
    ];
    return $months[$month] ?? '';
}

/**
 * Get Thai day name
 * 
 * @param string $day English day name
 * @return string Thai day name
 */
function getThaiDay($day) {
    $days = [
        'Sunday' => 'อาทิตย์',
        'Monday' => 'จันทร์',
        'Tuesday' => 'อังคาร',
        'Wednesday' => 'พุธ',
        'Thursday' => 'พฤหัสบดี',
        'Friday' => 'ศุกร์',
        'Saturday' => 'เสาร์'
    ];
    return $days[$day] ?? $day;
}

/**
 * Generate CSRF token
 * 
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * 
 * @param string $token Token to verify
 * @return bool True if valid
 */
function verifyCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log user activity
 * 
 * @param int $user_id User ID
 * @param string $action Action performed
 * @param string $details Additional details
 */
function logActivity($user_id, $action, $details = null) {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $action, $details, $ip, $user_agent]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Format number with thousands separator
 * 
 * @param float $number Number to format
 * @param int $decimals Number of decimal places
 * @return string Formatted number
 */
function formatNumber($number, $decimals = 2) {
    return number_format($number, $decimals);
}

/**
 * Format currency
 * 
 * @param float $amount Amount to format
 * @param string $currency Currency symbol
 * @return string Formatted currency
 */
function formatCurrency($amount, $currency = '฿') {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Generate random string
 * 
 * @param int $length Length of string
 * @return string Random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Check if user has permission
 * 
 * @param string $permission Permission key
 * @return bool True if has permission
 */
function hasPermission($permission) {
    if (!isset($_SESSION['user_permissions'])) {
        return false;
    }
    
    if (in_array('*', $_SESSION['user_permissions'])) {
        return true;
    }
    
    return in_array($permission, $_SESSION['user_permissions']);
}

/**
 * Get current user info
 * 
 * @return array|null User info or null if not logged in
 */
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'fullname' => $_SESSION['fullname'],
        'email' => $_SESSION['user_email'] ?? null,
        'role' => $_SESSION['user_role']
    ];
}

/**
 * Redirect to URL with message
 * 
 * @param string $url URL to redirect
 * @param string $message Message
 * @param string $type Message type (success, error, warning, info)
 */
function redirectWithMessage($url, $message, $type = 'info') {
    $_SESSION['flash_' . $type] = $message;
    header('Location: ' . $url);
    exit();
}

/**
 * Get flash message
 * 
 * @param string $type Message type
 * @return string|null Flash message or null
 */
function getFlashMessage($type = 'info') {
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $message = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $message;
    }
    return null;
}

/**
 * Get user IP address
 * 
 * @return string User IP address
 */
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

/**
 * Upload file
 * 
 * @param array $file File data from $_FILES
 * @param string $targetDir Target directory
 * @param array|null $allowedTypes Allowed MIME types
 * @return array Upload result
 */
function uploadFile($file, $targetDir, $allowedTypes = null) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file parameters'];
    }
    
    // Check file error
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'message' => 'No file uploaded'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'message' => 'File size exceeds limit'];
        default:
            return ['success' => false, 'message' => 'Unknown error'];
    }
    
    // Check file size (10MB max)
    if ($file['size'] > 10485760) {
        return ['success' => false, 'message' => 'File size exceeds 10MB'];
    }
    
    // Check file type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    
    if ($allowedTypes && !in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    // Create target directory if not exists
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $targetFile = $targetDir . '/' . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return ['success' => true, 'filename' => $filename, 'path' => $targetFile];
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
}

/**
 * Export to Excel
 * 
 * @param array $data Data to export
 * @param string $filename Filename
 * @return bool Success status
 */
function exportToExcel($data, $filename = 'export') {
    // Implementation depends on PHPExcel or similar library
    // This is a placeholder
    return true;
}

/**
 * Generate PDF
 * 
 * @param string $html HTML content
 * @param string $filename Filename
 * @return bool Success status
 */
function generatePDF($html, $filename = 'document') {
    // Implementation depends on DOMPDF or similar library
    // This is a placeholder
    return true;
}

/**
 * Get module list
 * 
 * @return array Available modules
 */
function getModules() {
    return [
        'air' => 'Air Compressor',
        'energy' => 'Energy & Water',
        'lpg' => 'LPG',
        'boiler' => 'Boiler',
        'summary' => 'Summary Electricity'
    ];
}

/**
 * Calculate percentage
 * 
 * @param float $value Value
 * @param float $total Total
 * @param int $decimals Decimal places
 * @return float Percentage
 */
function calculatePercentage($value, $total, $decimals = 2) {
    if ($total == 0) return 0;
    return round(($value / $total) * 100, $decimals);
}

/**
 * Get document number
 * 
 * @param string $prefix Document prefix
 * @param int|null $year Year
 * @return string Document number
 */
function generateDocNo($prefix, $year = null) {
    if (!$year) {
        $year = date('Y') + 543; // Thai year
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM documents 
            WHERE doc_no LIKE ? 
            AND YEAR(created_at) = ?
        ");
        $stmt->execute([$prefix . '-%', date('Y')]);
        $result = $stmt->fetch();
        
        $nextNumber = str_pad($result['count'] + 1, 4, '0', STR_PAD_LEFT);
        return $prefix . '-' . $year . '-' . $nextNumber;
    } catch (Exception $e) {
        error_log("Failed to generate document number: " . $e->getMessage());
        return $prefix . '-' . $year . '-0001';
    }
}

/**
 * Get date range for report
 * 
 * @param string $period Period type
 * @param string|null $custom_start Custom start date
 * @param string|null $custom_end Custom end date
 * @return array Date range
 */
function getDateRange($period, $custom_start = null, $custom_end = null) {
    $today = date('Y-m-d');
    
    switch ($period) {
        case 'today':
            return [
                'start' => $today,
                'end' => $today
            ];
        
        case 'yesterday':
            return [
                'start' => date('Y-m-d', strtotime('-1 day')),
                'end' => date('Y-m-d', strtotime('-1 day'))
            ];
        
        case 'this_week':
            return [
                'start' => date('Y-m-d', strtotime('monday this week')),
                'end' => date('Y-m-d', strtotime('sunday this week'))
            ];
        
        case 'last_week':
            return [
                'start' => date('Y-m-d', strtotime('monday last week')),
                'end' => date('Y-m-d', strtotime('sunday last week'))
            ];
        
        case 'this_month':
            return [
                'start' => date('Y-m-01'),
                'end' => date('Y-m-t')
            ];
        
        case 'last_month':
            return [
                'start' => date('Y-m-01', strtotime('first day of last month')),
                'end' => date('Y-m-t', strtotime('last day of last month'))
            ];
        
        case 'this_year':
            return [
                'start' => date('Y-01-01'),
                'end' => date('Y-12-31')
            ];
        
        case 'last_year':
            return [
                'start' => date('Y-01-01', strtotime('-1 year')),
                'end' => date('Y-12-31', strtotime('-1 year'))
            ];
        
        case 'custom':
            return [
                'start' => $custom_start,
                'end' => $custom_end
            ];
        
        default:
            return [
                'start' => date('Y-m-01'),
                'end' => date('Y-m-t')
            ];
    }
}

/**
 * Calculate summary statistics
 * 
 * @param array $data Data array
 * @param string $type Field name for calculation
 * @return array Summary statistics
 */
function calculateSummary($data, $type) {
    if (empty($data)) {
        return [
            'total' => 0,
            'average' => 0,
            'max' => 0,
            'min' => 0,
            'count' => 0
        ];
    }
    
    $values = array_column($data, $type);
    $count = count($values);
    $total = array_sum($values);
    $average = $total / $count;
    $max = max($values);
    $min = min($values);
    
    return [
        'total' => $total,
        'average' => $average,
        'max' => $max,
        'min' => $min,
        'count' => $count
    ];
}

/**
 * Create dropdown options
 * 
 * @param array $data Data array
 * @param string $valueField Value field name
 * @param string $textField Text field name
 * @param mixed $selected Selected value
 * @return string HTML options
 */
function createDropdownOptions($data, $valueField, $textField, $selected = null) {
    $html = '';
    foreach ($data as $item) {
        $value = $item[$valueField];
        $text = $item[$textField];
        $selectedAttr = ($selected == $value) ? 'selected' : '';
        $html .= "<option value=\"$value\" $selectedAttr>$text</option>";
    }
    return $html;
}

/**
 * Generate pagination
 * 
 * @param int $currentPage Current page
 * @param int $totalPages Total pages
 * @param string $urlPattern URL pattern with {page} placeholder
 * @return string Pagination HTML
 */
function generatePagination($currentPage, $totalPages, $urlPattern) {
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<nav><ul class="pagination">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . str_replace('{page}', $currentPage - 1, $urlPattern) . '">«</a></li>';
    }
    
    // Page numbers
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . str_replace('{page}', $i, $urlPattern) . '">' . $i . '</a></li>';
        }
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . str_replace('{page}', $currentPage + 1, $urlPattern) . '">»</a></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}
?>