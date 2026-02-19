<?php
/**
 * AJAX: Calculate Electricity Cost
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
    // Get parameters
    $ee_unit = isset($_POST['ee_unit']) ? (float)$_POST['ee_unit'] : 0;
    $cost_per_unit = isset($_POST['cost_per_unit']) ? (float)$_POST['cost_per_unit'] : 0;
    $pe = isset($_POST['pe']) ? (float)$_POST['pe'] : null;
    $date = isset($_POST['date']) ? $_POST['date'] : null;
    $include_comparison = isset($_POST['include_comparison']) ? (bool)$_POST['include_comparison'] : false;
    
    if ($ee_unit <= 0 || $cost_per_unit <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'กรุณากรอกหน่วยไฟฟ้าและค่าไฟต่อหน่วยให้ถูกต้อง'
        ]);
        exit();
    }
    
    // Calculate total cost
    $total_cost = $ee_unit * $cost_per_unit;
    
    $response = [
        'success' => true,
        'total_cost' => round($total_cost, 2),
        'ee_unit' => round($ee_unit, 2),
        'cost_per_unit' => round($cost_per_unit, 4),
        'formatted' => [
            'total_cost' => number_format($total_cost, 2),
            'ee_unit' => number_format($ee_unit, 2),
            'cost_per_unit' => number_format($cost_per_unit, 4)
        ]
    ];
    
    // Add PE adjustment if provided
    if ($pe !== null && $pe > 0 && $pe <= 1) {
        $adjusted_cost = $total_cost * $pe;
        $response['pe'] = round($pe, 4);
        $response['adjusted_cost'] = round($adjusted_cost, 2);
        $response['formatted']['adjusted_cost'] = number_format($adjusted_cost, 2);
        $response['formatted']['pe'] = number_format($pe, 4);
    }
    
    // Include comparison if requested and date provided
    if ($include_comparison && $date) {
        require_once __DIR__ . '/../../../config/database.php';
        $db = getDB();
        
        // Convert date from Thai format if needed
        $dateObj = DateTime::createFromFormat('d/m/Y', $date);
        if (!$dateObj) {
            $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        }
        
        if ($dateObj) {
            $date_db = $dateObj->format('Y-m-d');
            $month = $dateObj->format('m');
            $year = $dateObj->format('Y');
            
            // Get average for this month
            $stmt = $db->prepare("
                SELECT 
                    AVG(ee_unit) as avg_daily,
                    AVG(cost_per_unit) as avg_cost,
                    COUNT(*) as days_count
                FROM electricity_summary
                WHERE MONTH(record_date) = ? AND YEAR(record_date) = ?
            ");
            $stmt->execute([$month, $year]);
            $monthlyAvg = $stmt->fetch();
            
            if ($monthlyAvg && $monthlyAvg['days_count'] > 0) {
                $response['comparison'] = [
                    'monthly_avg_ee' => round($monthlyAvg['avg_daily'], 2),
                    'monthly_avg_cost' => round($monthlyAvg['avg_cost'], 4),
                    'days_in_month' => $monthlyAvg['days_count'],
                    'vs_avg_ee' => round($ee_unit - $monthlyAvg['avg_daily'], 2),
                    'vs_avg_ee_percent' => $monthlyAvg['avg_daily'] > 0 ? 
                        round((($ee_unit - $monthlyAvg['avg_daily']) / $monthlyAvg['avg_daily']) * 100, 1) : 0
                ];
            }
            
            // Get same day last year
            $lastYear = $year - 1;
            $stmt = $db->prepare("
                SELECT ee_unit, total_cost
                FROM electricity_summary
                WHERE DAY(record_date) = ? AND MONTH(record_date) = ? AND YEAR(record_date) = ?
            ");
            $stmt->execute([$dateObj->format('d'), $month, $lastYear]);
            $lastYearData = $stmt->fetch();
            
            if ($lastYearData) {
                $response['comparison']['last_year_ee'] = round($lastYearData['ee_unit'], 2);
                $response['comparison']['last_year_cost'] = round($lastYearData['total_cost'], 2);
                $response['comparison']['vs_last_year'] = round($ee_unit - $lastYearData['ee_unit'], 2);
                $response['comparison']['vs_last_year_percent'] = $lastYearData['ee_unit'] > 0 ? 
                    round((($ee_unit - $lastYearData['ee_unit']) / $lastYearData['ee_unit']) * 100, 1) : 0;
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