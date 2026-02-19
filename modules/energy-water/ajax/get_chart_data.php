<?php
/**
 * AJAX: Get Chart Data for Energy & Water
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
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Set header
header('Content-Type: application/json');

try {
    $db = getDB();
    
    $month = isset($_POST['month']) ? (int)$_POST['month'] : (int)date('m');
    $year = isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y');
    
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $labels = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $labels[] = $d;
    }
    
    // Get electricity usage data
    $stmt = $db->prepare("
        SELECT 
            DAY(record_date) as day,
            SUM(usage_amount) as total_usage
        FROM meter_daily_readings r
        JOIN mc_mdb_water m ON r.meter_id = m.id
        WHERE m.meter_type = 'electricity'
        AND MONTH(r.record_date) = ? 
        AND YEAR(r.record_date) = ?
        GROUP BY DAY(r.record_date)
        ORDER BY day
    ");
    $stmt->execute([$month, $year]);
    $electricityData = $stmt->fetchAll();
    
    // Get water usage data
    $stmt = $db->prepare("
        SELECT 
            DAY(record_date) as day,
            SUM(usage_amount) as total_usage
        FROM meter_daily_readings r
        JOIN mc_mdb_water m ON r.meter_id = m.id
        WHERE m.meter_type = 'water'
        AND MONTH(r.record_date) = ? 
        AND YEAR(r.record_date) = ?
        GROUP BY DAY(r.record_date)
        ORDER BY day
    ");
    $stmt->execute([$month, $year]);
    $waterData = $stmt->fetchAll();
    
    // Prepare chart data
    $electricityValues = array_fill(0, $daysInMonth, 0);
    foreach ($electricityData as $row) {
        $electricityValues[$row['day'] - 1] = round($row['total_usage'], 2);
    }
    
    $waterValues = array_fill(0, $daysInMonth, 0);
    foreach ($waterData as $row) {
        $waterValues[$row['day'] - 1] = round($row['total_usage'], 2);
    }
    
    echo json_encode([
        'success' => true,
        'electricity' => [
            'labels' => $labels,
            'values' => $electricityValues
        ],
        'water' => [
            'labels' => $labels,
            'values' => $waterValues
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>