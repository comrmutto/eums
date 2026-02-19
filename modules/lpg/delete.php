<?php
/**
 * LPG Module - Delete Record
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

// Get date from query string
$date = isset($_GET['date']) ? $_GET['date'] : '';
$confirm = isset($_GET['confirm']) ? (int)$_GET['confirm'] : 0;

if (empty($date)) {
    $_SESSION['error'] = 'ไม่พบข้อมูลที่ต้องการลบ';
    header('Location: index.php');
    exit();
}

// Get records for this date
$stmt = $db->prepare("
    SELECT 
        r.*,
        i.item_name,
        i.item_type,
        i.standard_value,
        i.unit
    FROM lpg_daily_records r
    JOIN lpg_inspection_items i ON r.item_id = i.id
    WHERE r.record_date = ?
    ORDER BY i.item_no
");
$stmt->execute([$date]);
$records = $stmt->fetchAll();

if (empty($records)) {
    $_SESSION['error'] = 'ไม่พบข้อมูลที่ต้องการลบ';
    header('Location: index.php');
    exit();
}

// If confirmed, delete the records
if ($confirm === 1) {
    try {
        $db->beginTransaction();
        
        // Log activity
        $recordCount = count($records);
        logActivity($_SESSION['user_id'], 'delete_lpg_records', 
                   "Deleted $recordCount LPG records for date: $date");
        
        // Delete records
        $stmt = $db->prepare("DELETE FROM lpg_daily_records WHERE record_date = ?");
        $stmt->execute([$date]);
        
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
$pageTitle = 'LPG - ลบข้อมูล';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'LPG', 'link' => 'index.php'],
    ['title' => 'ลบข้อมูล', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';

$displayDate = date('d/m/Y', strtotime($date));

// Calculate statistics
$ngCount = 0;
$totalUsage = 0;
foreach ($records as $record) {
    if ($record['item_type'] == 'number') {
        $totalUsage += floatval($record['number_value']);
    } elseif ($record['enum_value'] == 'NG') {
        $ngCount++;
    }
}
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <div class="row">
            <div class="col-md-8 offset-md-2">
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
                            คุณกำลังจะลบข้อมูลของวันที่ <strong><?php echo $displayDate; ?></strong>
                            การดำเนินการนี้ไม่สามารถกู้คืนได้
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-box bg-info">
                                    <span class="info-box-icon">
                                        <i class="fas fa-calendar"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">วันที่</span>
                                        <span class="info-box-number"><?php echo $displayDate; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box bg-warning">
                                    <span class="info-box-icon">
                                        <i class="fas fa-list"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">จำนวนรายการ</span>
                                        <span class="info-box-number"><?php echo count($records); ?> รายการ</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-box bg-success">
                                    <span class="info-box-icon">
                                        <i class="fas fa-calculator"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">ปริมาณการใช้</span>
                                        <span class="info-box-number"><?php echo number_format($totalUsage, 2); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box bg-danger">
                                    <span class="info-box-icon">
                                        <i class="fas fa-times-circle"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">จำนวน NG</span>
                                        <span class="info-box-number"><?php echo $ngCount; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h5>รายการที่จะถูกลบ</h5>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>ลำดับ</th>
                                        <th>รายการ</th>
                                        <th>ประเภท</th>
                                        <th>ค่าที่บันทึก</th>
                                        <th>ค่ามาตรฐาน</th>
                                        <th>สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($records as $record): ?>
                                    <tr>
                                        <td><?php echo $record['item_no']; ?></td>
                                        <td><?php echo htmlspecialchars($record['item_name']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $record['item_type'] == 'number' ? 'success' : 'warning'; ?>">
                                                <?php echo $record['item_type'] == 'number' ? 'ตัวเลข' : 'OK/NG'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($record['item_type'] == 'number') {
                                                echo number_format($record['number_value'], 2) . ' ' . $record['unit'];
                                            } else {
                                                echo $record['enum_value'];
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo $record['standard_value'] . ($record['unit'] ? ' ' . $record['unit'] : ''); ?></td>
                                        <td>
                                            <?php 
                                            if ($record['item_type'] == 'number') {
                                                $standard = floatval($record['standard_value']);
                                                $actual = floatval($record['number_value']);
                                                $deviation = abs(($actual - $standard) / $standard * 100);
                                                $status = $deviation <= 10 ? 'OK' : 'NG';
                                                echo '<span class="badge badge-' . ($status == 'OK' ? 'success' : 'danger') . '">' . $status . '</span>';
                                            } else {
                                                $status = $record['enum_value'];
                                                echo '<span class="badge badge-' . ($status == 'OK' ? 'success' : 'danger') . '">' . $status . '</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($ngCount > 0): ?>
                        <div class="alert alert-danger mt-3">
                            <i class="fas fa-exclamation-circle"></i>
                            พบรายการ NG จำนวน <?php echo $ngCount; ?> รายการ
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="?date=<?php echo $date; ?>&confirm=1" class="btn btn-danger">
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