<?php
/**
 * AJAX: Get Air Compressor Daily Record
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
    
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$id) {
        throw new Exception('Record ID required');
    }
    
    // Get record data with related information
    $stmt = $db->prepare("
        SELECT 
            r.*,
            m.machine_code,
            m.machine_name,
            s.inspection_item,
            s.standard_value,
            s.min_value,
            s.max_value,
            s.unit,
            u.fullname as recorded_by_name,
            d.doc_no,
            DATE_FORMAT(r.record_date, '%d/%m/%Y') as record_date_thai,
            DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as created_at_thai,
            DATE_FORMAT(r.updated_at, '%d/%m/%Y %H:%i') as updated_at_thai
        FROM air_daily_records r
        JOIN mc_air m ON r.machine_id = m.id
        JOIN air_inspection_standards s ON r.inspection_item_id = s.id
        LEFT JOIN users u ON r.recorded_by = u.username
        LEFT JOIN documents d ON r.doc_id = d.id
        WHERE r.id = ?
    ");
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    
    if (!$record) {
        throw new Exception('Record not found');
    }
    
    // Calculate status and deviation
    if ($record['min_value'] && $record['max_value']) {
        $record['status'] = ($record['actual_value'] >= $record['min_value'] && $record['actual_value'] <= $record['max_value']) ? 'OK' : 'NG';
        $record['deviation'] = null;
    } else {
        $deviation = (($record['actual_value'] - $record['standard_value']) / $record['standard_value']) * 100;
        $record['deviation'] = round($deviation, 2);
        $record['status'] = abs($deviation) <= 10 ? 'OK' : 'NG';
    }
    
    // Get previous records for comparison
    $stmt = $db->prepare("
        SELECT 
            record_date,
            actual_value,
            DATE_FORMAT(record_date, '%d/%m/%Y') as record_date_thai
        FROM air_daily_records
        WHERE machine_id = ? AND inspection_item_id = ? AND id != ?
        ORDER BY record_date DESC
        LIMIT 5
    ");
    $stmt->execute([$record['machine_id'], $record['inspection_item_id'], $id]);
    $previous_records = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'record' => $record,
            'previous_records' => $previous_records
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>