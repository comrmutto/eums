<?php
/**
 * Summary Electricity Module - Delete Record
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
    SELECT s.*, d.doc_no 
    FROM electricity_summary s
    LEFT JOIN documents d ON s.doc_id = d.id
    WHERE s.id = ?
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
        logActivity($_SESSION['user_id'], 'delete_summary_record', 
                   "Deleted summary record ID: $id, Date: {$record['record_date']}, EE: {$record['ee_unit']}");
        
        // Delete record
        $stmt = $db->prepare("DELETE FROM electricity_summary WHERE id = ?");
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
$pageTitle = 'Summary Electricity - ลบข้อมูล';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Summary Electricity', 'link' => 'index.php'],
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
                                <th style="width: 30%;">วันที่:</th>
                                <td><?php echo $displayDate; ?></td>
                            </tr>
                            <tr>
                                <th>เลขที่เอกสาร:</th>
                                <td><?php echo $record['doc_no'] ?: '-'; ?></td>
                            </tr>
                            <tr>
                                <th>หน่วยไฟฟ้า (EE):</th>
                                <td><?php echo number_format($record['ee_unit'], 2); ?> kWh</td>
                            </tr>
                            <tr>
                                <th>ค่าไฟต่อหน่วย:</th>
                                <td><?php echo number_format($record['cost_per_unit'], 4); ?> บาท</td>
                            </tr>
                            <tr>
                                <th>ค่าไฟฟ้ารวม:</th>
                                <td><?php echo number_format($record['total_cost'], 2); ?> บาท</td>
                            </tr>
                            <tr>
                                <th>PE:</th>
                                <td><?php echo $record['pe'] ? number_format($record['pe'], 4) : '-'; ?></td>
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
                        
                        <!-- Impact on monthly summary -->
                        <?php
                        // Get month summary without this record
                        $month = date('m', strtotime($record['record_date']));
                        $year = date('Y', strtotime($record['record_date']));
                        
                        $stmt = $db->prepare("
                            SELECT 
                                COUNT(*) as total_records,
                                SUM(ee_unit) as total_ee,
                                SUM(total_cost) as total_cost
                            FROM electricity_summary
                            WHERE MONTH(record_date) = ? AND YEAR(record_date) = ?
                        ");
                        $stmt->execute([$month, $year]);
                        $monthTotal = $stmt->fetch();
                        ?>
                        
                        <div class="alert alert-info mt-3">
                            <h5>ผลกระทบต่อสรุปเดือน <?php echo getThaiMonth($month) . ' ' . ($year + 543); ?></h5>
                            <table class="table table-sm">
                                <tr>
                                    <th>ปัจจุบัน:</th>
                                    <td class="text-right"><?php echo number_format($monthTotal['total_ee'], 2); ?> kWh</td>
                                    <td class="text-right"><?php echo number_format($monthTotal['total_cost'], 2); ?> บาท</td>
                                </tr>
                                <tr>
                                    <th>หลังจากลบ:</th>
                                    <td class="text-right"><?php echo number_format($monthTotal['total_ee'] - $record['ee_unit'], 2); ?> kWh</td>
                                    <td class="text-right"><?php echo number_format($monthTotal['total_cost'] - $record['total_cost'], 2); ?> บาท</td>
                                </tr>
                            </table>
                        </div>
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