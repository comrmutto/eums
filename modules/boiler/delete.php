<?php
/**
 * Boiler Module - Delete Record
 * Engineering Utility Monitoring System (EUMS)
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /eums/login.php');
    exit();
}

// Load required files
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Get database connection
$db = getDB();

// Get record ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$confirm = isset($_GET['confirm']) ? (int)$_GET['confirm'] : 0;

if (!$id) {
    $_SESSION['error'] = 'ไม่พบข้อมูลที่ต้องการลบ';
    header('Location: index.php');
    exit();
}

// Get record details
$stmt = $db->prepare("
    SELECT r.*, m.machine_name, m.machine_code 
    FROM boiler_daily_records r
    JOIN mc_boiler m ON r.machine_id = m.id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    $_SESSION['error'] = 'ไม่พบข้อมูลที่ต้องการลบ';
    header('Location: index.php');
    exit();
}

// If confirmed, delete the record
if ($confirm === 1) {
    try {
        $db->beginTransaction();
        
        // Log activity
        logActivity($_SESSION['user_id'], 'delete_boiler_record', 
                   "Deleted boiler record ID: $id, Machine: {$record['machine_code']}, Date: {$record['record_date']}");
        
        // Delete record
        $stmt = $db->prepare("DELETE FROM boiler_daily_records WHERE id = ?");
        $stmt->execute([$id]);
        
        $db->commit();
        
        $_SESSION['success'] = 'ลบข้อมูลเรียบร้อย';
        header('Location: index.php');
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $e->getMessage();
        header('Location: index.php');
        exit();
    }
}

// Set page title
$pageTitle = 'Boiler - ลบข้อมูล';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Boiler', 'link' => 'index.php'],
    ['title' => 'ลบข้อมูล', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';

$displayDate = date('d/m/Y', strtotime($record['record_date']));
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <div class="row">
            <div class="col-md-6 offset-md-3">
                <!-- Delete Confirmation Card -->
                <div class="card card-danger">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            ยืนยันการลบข้อมูล
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <h5><i class="icon fas fa-ban"></i> คำเตือน!</h5>
                            คุณกำลังจะลบข้อมูลต่อไปนี้ การดำเนินการนี้ไม่สามารถกู้คืนได้
                        </div>
                        
                        <table class="table table-bordered">
                            <tr>
                                <th style="width: 30%;">วันที่บันทึก:</th>
                                <td><?php echo $displayDate; ?></td>
                            </tr>
                            <tr>
                                <th>เครื่อง Boiler:</th>
                                <td><?php echo $record['machine_code'] . ' - ' . $record['machine_name']; ?></td>
                            </tr>
                            <tr>
                                <th>แรงดันไอน้ำ:</th>
                                <td><?php echo number_format($record['steam_pressure'], 2); ?> bar</td>
                            </tr>
                            <tr>
                                <th>อุณหภูมิไอน้ำ:</th>
                                <td><?php echo number_format($record['steam_temperature'], 1); ?> °C</td>
                            </tr>
                            <tr>
                                <th>ระดับน้ำ:</th>
                                <td><?php echo number_format($record['feed_water_level'], 2); ?> m</td>
                            </tr>
                            <tr>
                                <th>ปริมาณเชื้อเพลิง:</th>
                                <td><?php echo number_format($record['fuel_consumption'], 2); ?> L</td>
                            </tr>
                            <tr>
                                <th>ชั่วโมงทำงาน:</th>
                                <td><?php echo number_format($record['operating_hours'], 1); ?> hr</td>
                            </tr>
                            <tr>
                                <th>ผู้บันทึก:</th>
                                <td><?php echo htmlspecialchars($record['recorded_by']); ?></td>
                            </tr>
                            <?php if ($record['remarks']): ?>
                            <tr>
                                <th>หมายเหตุ:</th>
                                <td><?php echo nl2br(htmlspecialchars($record['remarks'])); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <div class="card-footer">
                        <a href="?id=<?php echo $id; ?>&confirm=1" class="btn btn-danger">
                            <i class="fas fa-trash"></i> ยืนยันการลบ
                        </a>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> ยกเลิก
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>