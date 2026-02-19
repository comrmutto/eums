<?php
/**
 * AJAX: Get Statistics for Summary Electricity
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
    
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    $month = isset($_GET['month']) ? (int)$_GET['month'] : null;
    
    $response = [
        'success' => true,
        'statistics' => []
    ];
    
    // Overall statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT YEAR(record_date)) as years,
            COUNT(*) as total_records,
            SUM(ee_unit) as total_ee,
            SUM(total_cost) as total_cost,
            AVG(cost_per_unit) as overall_avg_cost,
            MAX(ee_unit) as all_time_max,
            MIN(ee_unit) as all_time_min,
            DATE_FORMAT(MAX(record_date), '%d/%m/%Y') as last_record
        FROM electricity_summary
    ");
    $stmt->execute();
    $overall = $stmt->fetch();
    
    $response['statistics']['overall'] = [
        'years' => $overall['years'],
        'total_records' => $overall['total_records'],
        'total_ee' => round($overall['total_ee'], 2),
        'total_cost' => round($overall['total_cost'], 2),
        'avg_cost' => round($overall['overall_avg_cost'], 4),
        'max_ee' => round($overall['all_time_max'], 2),
        'min_ee' => round($overall['all_time_min'], 2),
        'last_record' => $overall['last_record']
    ];
    
    // Yearly statistics
    if ($year) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as records,
                SUM(ee_unit) as total_ee,
                SUM(total_cost) as total_cost,
                AVG(cost_per_unit) as avg_cost,
                AVG(ee_unit) as avg_daily,
                MAX(ee_unit) as max_daily,
                MIN(ee_unit) as min_daily
            FROM electricity_summary
            WHERE YEAR(record_date) = ?
        ");
        $stmt->execute([$year]);
        $yearly = $stmt->fetch();
        
        $response['statistics']['yearly'] = [
            'year' => $year + 543,
            'records' => $yearly['records'],
            'total_ee' => round($yearly['total_ee'], 2),
            'total_cost' => round($yearly['total_cost'], 2),
            'avg_cost' => round($yearly['avg_cost'], 4),
            'avg_daily' => round($yearly['avg_daily'], 2),
            'max_daily' => round($yearly['max_daily'], 2),
            'min_daily' => round($yearly['min_daily'], 2)
        ];
    }
    
    // Monthly statistics
    if ($month && $year) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as records,
                SUM(ee_unit) as total_ee,
                SUM(total_cost) as total_cost,
                AVG(cost_per_unit) as avg_cost,
                AVG(ee_unit) as avg_daily,
                MAX(ee_unit) as max_daily,
                MIN(ee_unit) as min_daily
            FROM electricity_summary
            WHERE YEAR(record_date) = ? AND MONTH(record_date) = ?
        ");
        $stmt->execute([$year, $month]);
        $monthly = $stmt->fetch();
        
        $response['statistics']['monthly'] = [
            'month' => $month,
            'month_name' => getThaiMonth($month),
            'year' => $year + 543,
            'records' => $monthly['records'],
            'total_ee' => round($monthly['total_ee'], 2),
            'total_cost' => round($monthly['total_cost'], 2),
            'avg_cost' => round($monthly['avg_cost'], 4),
            'avg_daily' => round($monthly['avg_daily'], 2),
            'max_daily' => round($monthly['max_daily'], 2),
            'min_daily' => round($monthly['min_daily'], 2)
        ];
    }
    
    // Peak usage months
    $stmt = $db->prepare("
        SELECT 
            YEAR(record_date) as year,
            MONTH(record_date) as month,
            SUM(ee_unit) as total_ee
        FROM electricity_summary
        GROUP BY YEAR(record_date), MONTH(record_date)
        ORDER BY total_ee DESC
        LIMIT 5
    ");
    $stmt->execute();
    $peakMonths = $stmt->fetchAll();
    
    $peakData = [];
    foreach ($peakMonths as $peak) {
        $peakData[] = [
            'period' => getThaiMonth($peak['month']) . ' ' . ($peak['year'] + 543),
            'total_ee' => round($peak['total_ee'], 2)
        ];
    }
    $response['statistics']['peak_months'] = $peakData;
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>