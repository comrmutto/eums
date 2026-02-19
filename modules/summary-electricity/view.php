<?php
/**
 * Summary Electricity Module - View Record Details
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Summary Electricity - ดูรายละเอียด';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Summary Electricity', 'link' => 'index.php'],
    ['title' => 'ดูรายละเอียด', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Load required files
require_once __DIR__ . '/../../includes/functions.php';

// Get database connection
$db = getDB();

// Get record ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    $_SESSION['error'] = 'ไม่พบข้อมูลที่ต้องการดู';
    header('Location: index.php');
    exit();
}

// Get record details
$stmt = $db->prepare("
    SELECT s.*, d.doc_no, d.rev_no, u.fullname as recorded_by_name
    FROM electricity_summary s
    LEFT JOIN documents d ON s.doc_id = d.id
    LEFT JOIN users u ON s.recorded_by = u.username
    WHERE s.id = ?
");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    $_SESSION['error'] = 'ไม่พบข้อมูลที่ต้องการดู';
    header('Location: index.php');
    exit();
}

$displayDate = date('d/m/Y', strtotime($record['record_date']));
$month = date('m', strtotime($record['record_date']));
$year = date('Y', strtotime($record['record_date']));

// Get month summary
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as days_count,
        SUM(ee_unit) as month_ee,
        SUM(total_cost) as month_cost,
        AVG(cost_per_unit) as avg_cost_per_unit,
        MAX(ee_unit) as max_daily_ee,
        MIN(ee_unit) as min_daily_ee
    FROM electricity_summary
    WHERE MONTH(record_date) = ? AND YEAR(record_date) = ?
");
$stmt->execute([$month, $year]);
$monthSummary = $stmt->fetch();

// Get previous day record
$stmt = $db->prepare("
    SELECT ee_unit, total_cost, record_date
    FROM electricity_summary
    WHERE record_date < ? AND MONTH(record_date) = ? AND YEAR(record_date) = ?
    ORDER BY record_date DESC
    LIMIT 1
");
$stmt->execute([$record['record_date'], $month, $year]);
$prevDay = $stmt->fetch();

// Get next day record
$stmt = $db->prepare("
    SELECT ee_unit, total_cost, record_date
    FROM electricity_summary
    WHERE record_date > ? AND MONTH(record_date) = ? AND YEAR(record_date) = ?
    ORDER BY record_date ASC
    LIMIT 1
");
$stmt->execute([$record['record_date'], $month, $year]);
$nextDay = $stmt->fetch();

// Get same day last year
$lastYear = $year - 1;
$stmt = $db->prepare("
    SELECT ee_unit, total_cost
    FROM electricity_summary
    WHERE DAY(record_date) = ? AND MONTH(record_date) = ? AND YEAR(record_date) = ?
");
$stmt->execute([date('d', strtotime($record['record_date'])), $month, $lastYear]);
$lastYearDay = $stmt->fetch();
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <div class="row">
            <div class="col-md-6">
                <!-- Record Details Card -->
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-clipboard-list"></i>
                            ข้อมูลการบันทึก
                        </h3>
                        <div class="card-tools">
                            <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-tool">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="delete.php?id=<?php echo $id; ?>" class="btn btn-tool">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
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
                                <th>Rev.No.:</th>
                                <td><?php echo $record['rev_no'] ?: '-'; ?></td>
                            </tr>
                            <tr>
                                <th>หน่วยไฟฟ้า (EE):</th>
                                <td>
                                    <h4><?php echo number_format($record['ee_unit'], 2); ?> kWh</h4>
                                </td>
                            </tr>
                            <tr>
                                <th>ค่าไฟต่อหน่วย:</th>
                                <td>
                                    <h4><?php echo number_format($record['cost_per_unit'], 4); ?> บาท</h4>
                                </td>
                            </tr>
                            <tr>
                                <th>ค่าไฟฟ้า:</th>
                                <td>
                                    <h3 class="text-primary"><?php echo number_format($record['total_cost'], 2); ?> บาท</h3>
                                </td>
                            </tr>
                            <tr>
                                <th>PE:</th>
                                <td><?php echo $record['pe'] ? number_format($record['pe'], 4) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>หมายเหตุ:</th>
                                <td><?php echo nl2br(htmlspecialchars($record['remarks'] ?: '-')); ?></td>
                            </tr>
                            <tr>
                                <th>ผู้บันทึก:</th>
                                <td><?php echo $record['recorded_by_name'] ?: $record['recorded_by']; ?></td>
                            </tr>
                            <tr>
                                <th>วันที่สร้าง:</th>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($record['created_at'])); ?></td>
                            </tr>
                            <?php if ($record['updated_at']): ?>
                            <tr>
                                <th>ปรับปรุงล่าสุด:</th>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($record['updated_at'])); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <!-- Monthly Summary Card -->
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-pie"></i>
                            สรุปเดือน <?php echo getThaiMonth($month) . ' ' . ($year + 543); ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-box bg-info">
                                    <span class="info-box-icon">
                                        <i class="fas fa-bolt"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">หน่วยรวม</span>
                                        <span class="info-box-number"><?php echo number_format($monthSummary['month_ee'], 2); ?> kWh</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box bg-success">
                                    <span class="info-box-icon">
                                        <i class="fas fa-coins"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">ค่าไฟรวม</span>
                                        <span class="info-box-number"><?php echo number_format($monthSummary['month_cost'], 2); ?> บาท</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <table class="table table-sm">
                            <tr>
                                <th>จำนวนวันที่มีข้อมูล:</th>
                                <td class="text-right"><?php echo $monthSummary['days_count']; ?> วัน</td>
                            </tr>
                            <tr>
                                <th>ค่าไฟเฉลี่ย/หน่วย:</th>
                                <td class="text-right"><?php echo number_format($monthSummary['avg_cost_per_unit'], 4); ?> บาท</td>
                            </tr>
                            <tr>
                                <th>สูงสุดรายวัน:</th>
                                <td class="text-right"><?php echo number_format($monthSummary['max_daily_ee'], 2); ?> kWh</td>
                            </tr>
                            <tr>
                                <th>ต่ำสุดรายวัน:</th>
                                <td class="text-right"><?php echo number_format($monthSummary['min_daily_ee'], 2); ?> kWh</td>
                            </tr>
                        </table>
                        
                        <div class="progress-group mt-3">
                            <span class="progress-text">สัดส่วนการใช้วันนี้เมื่อเทียบกับเดือน</span>
                            <span class="float-right">
                                <?php 
                                $percentage = ($record['ee_unit'] / $monthSummary['month_ee']) * 100;
                                echo number_format($percentage, 1); 
                                ?>%
                            </span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-primary" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Comparison Card -->
                <div class="card card-secondary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            เปรียบเทียบ
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if ($prevDay): ?>
                            <div class="col-md-6">
                                <div class="info-box bg-warning">
                                    <span class="info-box-icon">
                                        <i class="fas fa-arrow-left"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">วันก่อนหน้า</span>
                                        <span class="info-box-number"><?php echo number_format($prevDay['ee_unit'], 2); ?> kWh</span>
                                        <?php 
                                        $change = $record['ee_unit'] - $prevDay['ee_unit'];
                                        $changePercent = $prevDay['ee_unit'] > 0 ? ($change / $prevDay['ee_unit']) * 100 : 0;
                                        ?>
                                        <span class="badge badge-<?php echo $change > 0 ? 'danger' : 'success'; ?>">
                                            <?php echo $change > 0 ? '+' : ''; ?><?php echo number_format($change, 2); ?> kWh
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($nextDay): ?>
                            <div class="col-md-6">
                                <div class="info-box bg-warning">
                                    <span class="info-box-icon">
                                        <i class="fas fa-arrow-right"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">วันถัดไป</span>
                                        <span class="info-box-number"><?php echo number_format($nextDay['ee_unit'], 2); ?> kWh</span>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($lastYearDay): ?>
                        <hr>
                        <div class="info-box bg-secondary">
                            <span class="info-box-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">วันเดียวกันปีที่แล้ว (<?php echo $lastYear + 543; ?>)</span>
                                <span class="info-box-number"><?php echo number_format($lastYearDay['ee_unit'], 2); ?> kWh</span>
                                <?php 
                                $changeYear = $record['ee_unit'] - $lastYearDay['ee_unit'];
                                $changeYearPercent = $lastYearDay['ee_unit'] > 0 ? ($changeYear / $lastYearDay['ee_unit']) * 100 : 0;
                                ?>
                                <span class="badge badge-<?php echo $changeYear > 0 ? 'danger' : 'success'; ?>">
                                    <?php echo $changeYear > 0 ? '+' : ''; ?><?php echo number_format($changeYear, 2); ?> kWh
                                    (<?php echo number_format($changeYearPercent, 1); ?>%)
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <!-- Actions -->
                <div class="card">
                    <div class="card-body">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> กลับ
                        </a>
                        <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> แก้ไข
                        </a>
                        <a href="delete.php?id=<?php echo $id; ?>" class="btn btn-danger">
                            <i class="fas fa-trash"></i> ลบ
                        </a>
                        <button onclick="window.print()" class="btn btn-info">
                            <i class="fas fa-print"></i> พิมพ์
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
@media print {
    .btn, .card-tools, .main-footer, .main-header, .main-sidebar {
        display: none !important;
    }
    .content-wrapper {
        margin-left: 0 !important;
    }
}
</style>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>