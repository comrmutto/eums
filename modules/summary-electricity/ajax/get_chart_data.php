<?php
/**
 * AJAX: Get Chart Data for Summary Electricity
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
    
    // Get parameters
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    $month = isset($_GET['month']) ? (int)$_GET['month'] : null;
    $compare = isset($_GET['compare']) ? (bool)$_GET['compare'] : false;
    $chart_type = isset($_GET['chart_type']) ? $_GET['chart_type'] : 'monthly'; // monthly, daily, comparison
    
    $response = ['success' => true];
    
    switch ($chart_type) {
        case 'monthly':
            $response['data'] = getMonthlyData($db, $year);
            break;
            
        case 'daily':
            if (!$month) {
                $month = (int)date('m');
            }
            $response['data'] = getDailyData($db, $year, $month);
            break;
            
        case 'comparison':
            $compareYear = isset($_GET['compare_year']) ? (int)$_GET['compare_year'] : ($year - 1);
            $response['data'] = getComparisonData($db, $year, $compareYear);
            break;
            
        case 'trend':
            $response['data'] = getTrendData($db, $year);
            break;
            
        default:
            $response['data'] = getMonthlyData($db, $year);
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

/**
 * Get monthly summary data for the year
 */
function getMonthlyData($db, $year) {
    $monthNames = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 
                   'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    
    $stmt = $db->prepare("
        SELECT 
            MONTH(record_date) as month,
            SUM(ee_unit) as total_ee,
            SUM(total_cost) as total_cost,
            AVG(cost_per_unit) as avg_cost_per_unit,
            COUNT(*) as days_count,
            MAX(ee_unit) as max_daily,
            MIN(ee_unit) as min_daily
        FROM electricity_summary
        WHERE YEAR(record_date) = ?
        GROUP BY MONTH(record_date)
        ORDER BY month
    ");
    $stmt->execute([$year]);
    $results = $stmt->fetchAll();
    
    // Initialize arrays for all months
    $eeData = array_fill(0, 12, 0);
    $costData = array_fill(0, 12, 0);
    $avgCostData = array_fill(0, 12, 0);
    $daysData = array_fill(0, 12, 0);
    $maxData = array_fill(0, 12, 0);
    $minData = array_fill(0, 12, 0);
    
    foreach ($results as $row) {
        $idx = $row['month'] - 1;
        $eeData[$idx] = round($row['total_ee'], 2);
        $costData[$idx] = round($row['total_cost'], 2);
        $avgCostData[$idx] = round($row['avg_cost_per_unit'], 4);
        $daysData[$idx] = $row['days_count'];
        $maxData[$idx] = round($row['max_daily'], 2);
        $minData[$idx] = round($row['min_daily'], 2);
    }
    
    // Calculate cumulative data
    $cumulativeEE = [];
    $cumulativeCost = [];
    $runningEE = 0;
    $runningCost = 0;
    
    for ($i = 0; $i < 12; $i++) {
        $runningEE += $eeData[$i];
        $runningCost += $costData[$i];
        $cumulativeEE[] = $runningEE;
        $cumulativeCost[] = $runningCost;
    }
    
    return [
        'labels' => $monthNames,
        'ee' => $eeData,
        'cost' => $costData,
        'avg_cost' => $avgCostData,
        'days' => $daysData,
        'max' => $maxData,
        'min' => $minData,
        'cumulative_ee' => $cumulativeEE,
        'cumulative_cost' => $cumulativeCost,
        'year' => $year + 543 // Thai year
    ];
}

/**
 * Get daily data for specific month
 */
function getDailyData($db, $year, $month) {
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    
    $stmt = $db->prepare("
        SELECT 
            DAY(record_date) as day,
            ee_unit,
            total_cost,
            cost_per_unit,
            pe
        FROM electricity_summary
        WHERE YEAR(record_date) = ? AND MONTH(record_date) = ?
        ORDER BY day
    ");
    $stmt->execute([$year, $month]);
    $results = $stmt->fetchAll();
    
    // Initialize arrays for all days
    $eeData = array_fill(0, $daysInMonth, 0);
    $costData = array_fill(0, $daysInMonth, 0);
    $costPerUnitData = array_fill(0, $daysInMonth, 0);
    $peData = array_fill(0, $daysInMonth, null);
    $hasData = array_fill(0, $daysInMonth, false);
    
    foreach ($results as $row) {
        $idx = $row['day'] - 1;
        $eeData[$idx] = round($row['ee_unit'], 2);
        $costData[$idx] = round($row['total_cost'], 2);
        $costPerUnitData[$idx] = round($row['cost_per_unit'], 4);
        $peData[$idx] = $row['pe'] ? round($row['pe'], 4) : null;
        $hasData[$idx] = true;
    }
    
    // Calculate moving average (7-day)
    $movingAvg = [];
    for ($i = 0; $i < $daysInMonth; $i++) {
        if ($i < 3) {
            $movingAvg[] = null;
            continue;
        }
        
        $sum = 0;
        $count = 0;
        for ($j = max(0, $i - 6); $j <= $i; $j++) {
            if ($hasData[$j]) {
                $sum += $eeData[$j];
                $count++;
            }
        }
        $movingAvg[] = $count > 0 ? round($sum / $count, 2) : null;
    }
    
    return [
        'labels' => range(1, $daysInMonth),
        'ee' => $eeData,
        'cost' => $costData,
        'cost_per_unit' => $costPerUnitData,
        'pe' => $peData,
        'has_data' => $hasData,
        'moving_avg' => $movingAvg,
        'month' => $month,
        'year' => $year + 543,
        'month_name' => getThaiMonth($month)
    ];
}

/**
 * Get year comparison data
 */
function getComparisonData($db, $year1, $year2) {
    $monthNames = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 
                   'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    
    // Get data for first year
    $stmt = $db->prepare("
        SELECT 
            MONTH(record_date) as month,
            SUM(ee_unit) as total_ee,
            SUM(total_cost) as total_cost
        FROM electricity_summary
        WHERE YEAR(record_date) = ?
        GROUP BY MONTH(record_date)
    ");
    $stmt->execute([$year1]);
    $data1 = $stmt->fetchAll();
    
    // Get data for second year
    $stmt->execute([$year2]);
    $data2 = $stmt->fetchAll();
    
    // Initialize arrays
    $ee1 = array_fill(0, 12, 0);
    $cost1 = array_fill(0, 12, 0);
    $ee2 = array_fill(0, 12, 0);
    $cost2 = array_fill(0, 12, 0);
    
    foreach ($data1 as $row) {
        $idx = $row['month'] - 1;
        $ee1[$idx] = round($row['total_ee'], 2);
        $cost1[$idx] = round($row['total_cost'], 2);
    }
    
    foreach ($data2 as $row) {
        $idx = $row['month'] - 1;
        $ee2[$idx] = round($row['total_ee'], 2);
        $cost2[$idx] = round($row['total_cost'], 2);
    }
    
    // Calculate differences and percentages
    $eeDiff = [];
    $eePercent = [];
    $costDiff = [];
    $costPercent = [];
    
    for ($i = 0; $i < 12; $i++) {
        $eeDiff[$i] = round($ee1[$i] - $ee2[$i], 2);
        $eePercent[$i] = $ee2[$i] > 0 ? round(($eeDiff[$i] / $ee2[$i]) * 100, 1) : 0;
        $costDiff[$i] = round($cost1[$i] - $cost2[$i], 2);
        $costPercent[$i] = $cost2[$i] > 0 ? round(($costDiff[$i] / $cost2[$i]) * 100, 1) : 0;
    }
    
    return [
        'labels' => $monthNames,
        'year1' => [
            'ee' => $ee1,
            'cost' => $cost1,
            'year' => $year1 + 543
        ],
        'year2' => [
            'ee' => $ee2,
            'cost' => $cost2,
            'year' => $year2 + 543
        ],
        'comparison' => [
            'ee_diff' => $eeDiff,
            'ee_percent' => $eePercent,
            'cost_diff' => $costDiff,
            'cost_percent' => $costPercent
        ]
    ];
}

/**
 * Get trend analysis data
 */
function getTrendData($db, $year) {
    // Get last 3 years data including current year
    $years = [$year - 2, $year - 1, $year];
    $result = [];
    
    foreach ($years as $y) {
        $stmt = $db->prepare("
            SELECT 
                SUM(ee_unit) as total_ee,
                SUM(total_cost) as total_cost,
                AVG(cost_per_unit) as avg_cost,
                COUNT(*) as total_days
            FROM electricity_summary
            WHERE YEAR(record_date) = ?
        ");
        $stmt->execute([$y]);
        $data = $stmt->fetch();
        
        $result[] = [
            'year' => $y + 543,
            'total_ee' => round($data['total_ee'] ?? 0, 2),
            'total_cost' => round($data['total_cost'] ?? 0, 2),
            'avg_cost' => round($data['avg_cost'] ?? 0, 4),
            'total_days' => $data['total_days'] ?? 0
        ];
    }
    
    // Calculate growth rates
    $growth = [];
    for ($i = 1; $i < count($result); $i++) {
        $prevEE = $result[$i-1]['total_ee'];
        $currentEE = $result[$i]['total_ee'];
        
        if ($prevEE > 0) {
            $growth[] = [
                'period' => $result[$i-1]['year'] . ' → ' . $result[$i]['year'],
                'ee_growth' => round((($currentEE - $prevEE) / $prevEE) * 100, 1),
                'cost_growth' => round((($result[$i]['total_cost'] - $result[$i-1]['total_cost']) / $result[$i-1]['total_cost']) * 100, 1)
            ];
        }
    }
    
    return [
        'yearly' => $result,
        'growth' => $growth
    ];
}
?>