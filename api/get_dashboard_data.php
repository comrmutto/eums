<?php
/**
 * API: Get Dashboard Data
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
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Set header
header('Content-Type: application/json');

try {
    $db = getDB();
    
    $type = isset($_GET['type']) ? $_GET['type'] : 'monthly';
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    
    $response = ['success' => true];
    
    switch ($type) {
        case 'monthly':
            $response['data'] = getMonthlyData($db, $year);
            break;
            
        case 'pie':
            $response['data'] = getPieData($db);
            break;
            
        default:
            $response['data'] = [];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

function getMonthlyData($db, $year) {
    $monthNames = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 
                   'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    
    // Air Compressor
    $airData = array_fill(0, 12, 0);
    $stmt = $db->prepare("
        SELECT MONTH(record_date) as month, SUM(actual_value) as total
        FROM air_daily_records
        WHERE YEAR(record_date) = ?
        GROUP BY MONTH(record_date)
    ");
    $stmt->execute([$year]);
    foreach ($stmt->fetchAll() as $row) {
        $airData[$row['month'] - 1] = round($row['total'], 2);
    }
    
    // Energy & Water
    $energyData = array_fill(0, 12, 0);
    $stmt = $db->prepare("
        SELECT MONTH(record_date) as month, SUM(usage_amount) as total
        FROM meter_daily_readings
        WHERE YEAR(record_date) = ?
        GROUP BY MONTH(record_date)
    ");
    $stmt->execute([$year]);
    foreach ($stmt->fetchAll() as $row) {
        $energyData[$row['month'] - 1] = round($row['total'], 2);
    }
    
    // LPG
    $lpgData = array_fill(0, 12, 0);
    $stmt = $db->prepare("
        SELECT MONTH(record_date) as month, SUM(number_value) as total
        FROM lpg_daily_records
        WHERE YEAR(record_date) = ? AND number_value IS NOT NULL
        GROUP BY MONTH(record_date)
    ");
    $stmt->execute([$year]);
    foreach ($stmt->fetchAll() as $row) {
        $lpgData[$row['month'] - 1] = round($row['total'], 2);
    }
    
    // Boiler
    $boilerData = array_fill(0, 12, 0);
    $stmt = $db->prepare("
        SELECT MONTH(record_date) as month, SUM(fuel_consumption) as total
        FROM boiler_daily_records
        WHERE YEAR(record_date) = ?
        GROUP BY MONTH(record_date)
    ");
    $stmt->execute([$year]);
    foreach ($stmt->fetchAll() as $row) {
        $boilerData[$row['month'] - 1] = round($row['total'], 2);
    }
    
    // Summary Electricity
    $summaryData = array_fill(0, 12, 0);
    $stmt = $db->prepare("
        SELECT MONTH(record_date) as month, SUM(ee_unit) as total
        FROM electricity_summary
        WHERE YEAR(record_date) = ?
        GROUP BY MONTH(record_date)
    ");
    $stmt->execute([$year]);
    foreach ($stmt->fetchAll() as $row) {
        $summaryData[$row['month'] - 1] = round($row['total'], 2);
    }
    
    return [
        'labels' => $monthNames,
        'air' => $airData,
        'energy' => $energyData,
        'lpg' => $lpgData,
        'boiler' => $boilerData,
        'summary' => $summaryData
    ];
}

function getPieData($db) {
    // Get current month totals
    $month = date('m');
    $year = date('Y');
    
    // Air Compressor
    $stmt = $db->prepare("SELECT SUM(actual_value) as total FROM air_daily_records WHERE MONTH(record_date) = ? AND YEAR(record_date) = ?");
    $stmt->execute([$month, $year]);
    $air = $stmt->fetch()['total'] ?? 0;
    
    // Energy & Water
    $stmt = $db->prepare("SELECT SUM(usage_amount) as total FROM meter_daily_readings WHERE MONTH(record_date) = ? AND YEAR(record_date) = ?");
    $stmt->execute([$month, $year]);
    $energy = $stmt->fetch()['total'] ?? 0;
    
    // LPG
    $stmt = $db->prepare("SELECT SUM(number_value) as total FROM lpg_daily_records WHERE MONTH(record_date) = ? AND YEAR(record_date) = ? AND number_value IS NOT NULL");
    $stmt->execute([$month, $year]);
    $lpg = $stmt->fetch()['total'] ?? 0;
    
    // Boiler
    $stmt = $db->prepare("SELECT SUM(fuel_consumption) as total FROM boiler_daily_records WHERE MONTH(record_date) = ? AND YEAR(record_date) = ?");
    $stmt->execute([$month, $year]);
    $boiler = $stmt->fetch()['total'] ?? 0;
    
    // Summary Electricity
    $stmt = $db->prepare("SELECT SUM(ee_unit) as total FROM electricity_summary WHERE MONTH(record_date) = ? AND YEAR(record_date) = ?");
    $stmt->execute([$month, $year]);
    $summary = $stmt->fetch()['total'] ?? 0;
    
    return [
        'air' => round($air, 2),
        'energy' => round($energy, 2),
        'lpg' => round($lpg, 2),
        'boiler' => round($boiler, 2),
        'summary' => round($summary, 2)
    ];
}
?>