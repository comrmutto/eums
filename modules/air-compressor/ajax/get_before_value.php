<?php
/**
 * AJAX: Get before_value default (= after_value of previous record)
 * Engineering Utility Monitoring System (EUMS)
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../../config/database.php';
header('Content-Type: application/json');

$machine_id         = isset($_GET['machine_id'])         ? (int)$_GET['machine_id']         : 0;
$inspection_item_id = isset($_GET['inspection_item_id']) ? (int)$_GET['inspection_item_id'] : 0;
$record_date_raw    = isset($_GET['record_date'])        ? trim($_GET['record_date'])        : '';

if (!$machine_id || !$inspection_item_id || !$record_date_raw) {
    echo json_encode(['success' => false, 'after_value' => null]);
    exit();
}

// แปลงวันที่ dd/mm/yyyy → Y-m-d
$dt = DateTime::createFromFormat('d/m/Y', $record_date_raw);
if (!$dt) {
    echo json_encode(['success' => false, 'after_value' => null]);
    exit();
}
$record_date_db = $dt->format('Y-m-d');

try {
    $db = getDB();

    // ดึง after_value ของ record ล่าสุดก่อนวันที่เลือก (เรียงจากใหม่→เก่า)
    $stmt = $db->prepare("
        SELECT after_value
        FROM air_daily_records
        WHERE machine_id         = ?
          AND inspection_item_id = ?
          AND record_date        < ?
          AND after_value        IS NOT NULL
        ORDER BY record_date DESC
        LIMIT 1
    ");
    $stmt->execute([$machine_id, $inspection_item_id, $record_date_db]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode(['success' => true, 'after_value' => (float)$row['after_value']]);
    } else {
        // ไม่มีข้อมูลก่อนหน้า — คืน null ให้ user กรอกเอง
        echo json_encode(['success' => true, 'after_value' => null]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'after_value' => null, 'error' => $e->getMessage()]);
}
?>