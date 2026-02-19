<?php
/**
 * Air Compressor Module - Delete Record
 * Engineering Utility Monitoring System (EUMS)
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Load required files
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /eums/login.php');
    exit();
}

// Get record ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$confirm = isset($_GET['confirm']) ? (int)$_GET['confirm'] : 0;

if (!$id) {
    $_SESSION['error'] = 'ไม่พบข้อมูลที่ต้องการลบ';
    header('Location: index.php');
    exit();
}

// Get database connection
$db = getDB();

// Get record details for confirmation
$stmt = $db->prepare("
    SELECT r.*, m.machine_name, s.inspection_item 
    FROM air_daily_records r
    JOIN mc_air m ON r.machine_id = m.id
    JOIN air_inspection_standards s ON r.inspection_item_id = s.id
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
        // Begin transaction
        $db->beginTransaction();
        
        // Log activity before delete
        logActivity($_SESSION['user_id'], 'delete_air_record', 
                   "Deleted record ID: $id, Machine: {$record['machine_name']}, Date: {$record['record_date']}");
        
        // Delete the record
        $stmt = $db->prepare("DELETE FROM air_daily_records WHERE id = ?");
        $stmt->execute([$id]);
        
        // Commit transaction
        $db->commit();
        
        $_SESSION['success'] = 'ลบข้อมูลเรียบร้อย';
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        $_SESSION['error'] = 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $e->getMessage();
    }
    
    header('Location: index.php');
    exit();
}

// Set page title
$pageTitle = 'Air Compressor - ลบข้อมูล';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Air Compressor', 'link' => 'index.php'],
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
                                <td><?php echo date('d/m/Y', strtotime($record['record_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>เครื่องจักร:</th>
                                <td><?php echo htmlspecialchars($record['machine_name']); ?></td>
                            </tr>
                            <tr>
                                <th>หัวข้อตรวจสอบ:</th>
                                <td><?php echo htmlspecialchars($record['inspection_item']); ?></td>
                            </tr>
                            <tr>
                                <th>ค่าที่บันทึก:</th>
                                <td><?php echo $record['actual_value']; ?></td>
                            </tr>
                            <tr>
                                <th>ผู้บันทึก:</th>
                                <td><?php echo htmlspecialchars($record['recorded_by']); ?></td>
                            </tr>
                            <tr>
                                <th>หมายเหตุ:</th>
                                <td><?php echo htmlspecialchars($record['remarks'] ?: '-'); ?></td>
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