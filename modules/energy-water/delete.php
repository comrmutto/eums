<?php
/**
 * Energy & Water Module - Delete Reading
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

// Get reading ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$confirm = isset($_GET['confirm']) ? (int)$_GET['confirm'] : 0;

if (!$id) {
    $_SESSION['error'] = 'ไม่พบข้อมูลที่ต้องการลบ';
    header('Location: index.php');
    exit();
}

// Get database connection
$db = getDB();

// Get reading details
$stmt = $db->prepare("
    SELECT r.*, m.meter_name, m.meter_code, m.meter_type
    FROM meter_daily_readings r
    JOIN mc_mdb_water m ON r.meter_id = m.id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$reading = $stmt->fetch();

if (!$reading) {
    $_SESSION['error'] = 'ไม่พบข้อมูลที่ต้องการลบ';
    header('Location: index.php');
    exit();
}

// If confirmed, delete the reading
if ($confirm === 1) {
    try {
        $db->beginTransaction();
        
        // Log activity
        logActivity($_SESSION['user_id'], 'delete_meter_reading', 
                   "Deleted reading ID: $id, Meter: {$reading['meter_code']}, Date: {$reading['record_date']}");
        
        // Delete the reading
        $stmt = $db->prepare("DELETE FROM meter_daily_readings WHERE id = ?");
        $stmt->execute([$id]);
        
        $db->commit();
        
        $_SESSION['success'] = 'ลบข้อมูลเรียบร้อย';
        
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $e->getMessage();
    }
    
    header('Location: index.php');
    exit();
}

// Set page title
$pageTitle = 'Energy & Water - ลบข้อมูล';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Energy & Water', 'link' => 'index.php'],
    ['title' => 'ลบข้อมูล', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6 offset-md-3">
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
                                <td><?php echo date('d/m/Y', strtotime($reading['record_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>ประเภท:</th>
                                <td>
                                    <span class="badge badge-<?php echo $reading['meter_type'] == 'electricity' ? 'warning' : 'info'; ?>">
                                        <?php echo $reading['meter_type'] == 'electricity' ? 'ไฟฟ้า' : 'น้ำ'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>มิเตอร์:</th>
                                <td><?php echo $reading['meter_code'] . ' - ' . $reading['meter_name']; ?></td>
                            </tr>
                            <tr>
                                <th>ค่าเช้า:</th>
                                <td><?php echo number_format($reading['morning_reading'], 2); ?></td>
                            </tr>
                            <tr>
                                <th>ค่าเย็น:</th>
                                <td><?php echo number_format($reading['evening_reading'], 2); ?></td>
                            </tr>
                            <tr>
                                <th>ปริมาณการใช้:</th>
                                <td><strong><?php echo number_format($reading['usage_amount'], 2); ?></strong></td>
                            </tr>
                            <tr>
                                <th>ผู้บันทึก:</th>
                                <td><?php echo htmlspecialchars($reading['recorded_by']); ?></td>
                            </tr>
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