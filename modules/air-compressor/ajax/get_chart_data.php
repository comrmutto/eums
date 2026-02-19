<?php
/**
 * AJAX: Get Chart Data for Air Compressor
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
    $machineId = isset($_POST['machine_id']) && $_POST['machine_id'] != 'all' ? (int)$_POST['machine_id'] : null;
    $month = isset($_POST['month']) ? (int)$_POST['month'] : (int)date('m');
    $year = isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y');
    
    // Prepare base query
    $sql = "
        SELECT 
            DAY(r.record_date) as day,
            AVG(r.actual_value) as avg_value,
            COUNT(r.id) as record_count
        FROM air_daily_records r
        JOIN air_inspection_standards s ON r.inspection_item_id = s.id
        WHERE MONTH(r.record_date) = ? AND YEAR(r.record_date) = ?
    ";
    
    $params = [$month, $year];
    
    if ($machineId) {
        $sql .= " AND r.machine_id = ?";
        $params[] = $machineId;
    }
    
    $sql .= " GROUP BY DAY(r.record_date) ORDER BY day";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    // Prepare chart data
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $labels = [];
    $values = [];
    
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $labels[] = $d;
        $found = false;
        
        foreach ($results as $row) {
            if ($row['day'] == $d) {
                $values[] = round($row['avg_value'], 2);
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $values[] = 0;
        }
    }
    
    // Get machine names for legend
    if (!$machineId) {
        $stmt = $db->prepare("
            SELECT m.id, m.machine_name 
            FROM mc_air m
            WHERE m.status = 1
        ");
        $stmt->execute();
        $machines = $stmt->fetchAll();
        
        // Get data for each machine
        $datasets = [];
        foreach ($machines as $machine) {
            $stmt = $db->prepare("
                SELECT 
                    DAY(r.record_date) as day,
                    AVG(r.actual_value) as avg_value
                FROM air_daily_records r
                WHERE r.machine_id = ? 
                AND MONTH(r.record_date) = ? 
                AND YEAR(r.record_date) = ?
                GROUP BY DAY(r.record_date)
            ");
            $stmt->execute([$machine['id'], $month, $year]);
            $machineData = $stmt->fetchAll();
            
            $machineValues = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $found = false;
                foreach ($machineData as $row) {
                    if ($row['day'] == $d) {
                        $machineValues[] = round($row['avg_value'], 2);
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $machineValues[] = null;
                }
            }
            
            $datasets[] = [
                'label' => $machine['machine_name'],
                'data' => $machineValues
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'labels' => $labels,
                'values' => $values,
                'datasets' => $datasets
            ]
        ]);
    } else {
        // Get machine name
        $stmt = $db->prepare("SELECT machine_name FROM mc_air WHERE id = ?");
        $stmt->execute([$machineId]);
        $machine = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'labels' => $labels,
                'values' => $values,
                'machine_name' => $machine ? $machine['machine_name'] : 'ทุกเครื่อง'
            ]
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>