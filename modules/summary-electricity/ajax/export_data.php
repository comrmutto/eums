<?php
/**
 * AJAX: Export Summary Electricity Data
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
    
    $format = isset($_GET['format']) ? $_GET['format'] : 'json';
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    $month = isset($_GET['month']) ? (int)$_GET['month'] : null;
    
    // Build query
    $sql = "
        SELECT 
            DATE_FORMAT(record_date, '%d/%m/%Y') as date,
            ee_unit,
            cost_per_unit,
            total_cost,
            pe,
            remarks,
            recorded_by,
            DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as created
        FROM electricity_summary
        WHERE 1=1
    ";
    $params = [];
    
    if ($year) {
        $sql .= " AND YEAR(record_date) = ?";
        $params[] = $year;
    }
    
    if ($month) {
        $sql .= " AND MONTH(record_date) = ?";
        $params[] = $month;
    }
    
    $sql .= " ORDER BY record_date";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    // Add summary
    $totalEE = 0;
    $totalCost = 0;
    foreach ($data as $row) {
        $totalEE += $row['ee_unit'];
        $totalCost += $row['total_cost'];
    }
    
    $summary = [
        'total_records' => count($data),
        'total_ee' => round($totalEE, 2),
        'total_cost' => round($totalCost, 2),
        'avg_cost_per_unit' => count($data) > 0 ? round($totalCost / $totalEE, 4) : 0,
        'export_date' => date('d/m/Y H:i:s'),
        'export_by' => $_SESSION['username']
    ];
    
    $result = [
        'success' => true,
        'data' => $data,
        'summary' => $summary,
        'period' => $month ? getThaiMonth($month) . ' ' . ($year + 543) : 'ปี ' . ($year + 543)
    ];
    
    if ($format === 'csv') {
        // Generate CSV
        $output = fopen('php://temp', 'w');
        
        // Headers
        fputcsv($output, ['วันที่', 'หน่วยไฟฟ้า (kWh)', 'ค่าไฟต่อหน่วย (บาท)', 'ค่าไฟฟ้า (บาท)', 'PE', 'หมายเหตุ', 'ผู้บันทึก', 'วันที่สร้าง']);
        
        // Data
        foreach ($data as $row) {
            fputcsv($output, [
                $row['date'],
                $row['ee_unit'],
                $row['cost_per_unit'],
                $row['total_cost'],
                $row['pe'] ?: '',
                $row['remarks'],
                $row['recorded_by'],
                $row['created']
            ]);
        }
        
        // Summary
        fputcsv($output, []);
        fputcsv($output, ['สรุป', '', '', '', '', '', '', '']);
        fputcsv($output, ['จำนวนรายการ', $summary['total_records'], '', '', '', '', '', '']);
        fputcsv($output, ['หน่วยไฟฟ้ารวม', $summary['total_ee'], 'kWh', '', '', '', '', '']);
        fputcsv($output, ['ค่าไฟฟ้ารวม', $summary['total_cost'], 'บาท', '', '', '', '', '']);
        fputcsv($output, ['ค่าไฟเฉลี่ย/หน่วย', $summary['avg_cost_per_unit'], 'บาท', '', '', '', '', '']);
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        $result['csv'] = $csv;
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Export error: ' . $e->getMessage()
    ]);
}
?>