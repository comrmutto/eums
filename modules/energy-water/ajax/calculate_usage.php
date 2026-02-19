<?php
/**
 * AJAX: Calculate Usage from Readings
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

// Set header
header('Content-Type: application/json');

try {
    $morning = isset($_POST['morning']) ? (float)$_POST['morning'] : 0;
    $evening = isset($_POST['evening']) ? (float)$_POST['evening'] : 0;
    $meter_id = isset($_POST['meter_id']) ? (int)$_POST['meter_id'] : 0;
    $date = isset($_POST['date']) ? $_POST['date'] : '';
    
    if ($evening <= $morning) {
        echo json_encode([
            'success' => false,
            'message' => 'ค่าเย็นต้องมากกว่าค่าเช้า'
        ]);
        exit();
    }
    
    $usage = $evening - $morning;
    $response = [
        'success' => true,
        'usage' => round($usage, 2),
        'warning' => null
    ];
    
    // Check for abnormal usage
    if ($usage > 1000) {
        $response['warning'] = 'ปริมาณการใช้สูงผิดปกติ';
    } elseif ($usage == 0) {
        $response['warning'] = 'ปริมาณการใช้เป็น 0';
    }
    
    // If meter_id and date provided, check against average
    if ($meter_id && $date) {
        require_once __DIR__ . '/../../../config/database.php';
        $db = getDB();
        
        // Convert date from Thai format if needed
        if (strpos($date, '/') !== false) {
            $dateObj = DateTime::createFromFormat('d/m/Y', $date);
            if ($dateObj) {
                $date = $dateObj->format('Y-m-d');
            }
        }
        
        // Get average usage for this meter
        $stmt = $db->prepare("
            SELECT AVG(usage_amount) as avg_usage 
            FROM meter_daily_readings 
            WHERE meter_id = ? AND record_date < ? AND usage_amount > 0
        ");
        $stmt->execute([$meter_id, $date]);
        $avg = $stmt->fetch();
        
        if ($avg && $avg['avg_usage'] > 0) {
            $ratio = $usage / $avg['avg_usage'];
            if ($ratio > 3) {
                $response['warning'] = "ปริมาณการใช้สูงกว่าค่าเฉลี่ย " . round($ratio, 1) . " เท่า";
            } elseif ($ratio < 0.1 && $usage > 0) {
                $response['warning'] = "ปริมาณการใช้ต่ำกว่าค่าเฉลี่ยมาก";
            }
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>