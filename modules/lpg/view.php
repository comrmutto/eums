<?php
/**
 * LPG Module - View Records
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'LPG - ดูบันทึกข้อมูล';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'LPG', 'link' => 'index.php'],
    ['title' => 'ดูบันทึกข้อมูล', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Load required files
require_once __DIR__ . '/../../includes/functions.php';

// Get database connection
$db = getDB();

// Get date from query string
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get records for this date
$stmt = $db->prepare("
    SELECT 
        r.*,
        i.item_name,
        i.item_type,
        i.standard_value,
        i.unit,
        i.enum_options,
        d.doc_no,
        u.fullname as recorded_by_name
    FROM lpg_daily_records r
    JOIN lpg_inspection_items i ON r.item_id = i.id
    LEFT JOIN documents d ON r.doc_id = d.id
    LEFT JOIN users u ON r.recorded_by = u.username
    WHERE r.record_date = ?
    ORDER BY i.item_no
");
$stmt->execute([$date]);
$records = $stmt->fetchAll();

if (empty($records)) {
    $_SESSION['error'] = 'ไม่พบข้อมูลสำหรับวันที่นี้';
    header('Location: index.php');
    exit();
}

// Get summary for the month
$month = date('m', strtotime($date));
$year = date('Y', strtotime($date));

$stmt = $db->prepare("
    SELECT 
        SUM(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE 0 END) as month_total,
        AVG(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE NULL END) as month_avg,
        COUNT(CASE WHEN i.item_type = 'enum' AND r.enum_value = 'NG' THEN 1 END) as month_ng,
        COUNT(CASE WHEN i.item_type = 'enum' AND r.enum_value = 'OK' THEN 1 END) as month_ok
    FROM lpg_daily_records r
    JOIN lpg_inspection_items i ON r.item_id = i.id
    WHERE MONTH(r.record_date) = ? AND YEAR(r.record_date) = ?
");
$stmt->execute([$month, $year]);
$monthSummary = $stmt->fetch();

// Get recent records for comparison
$stmt = $db->prepare("
    SELECT 
        record_date,
        SUM(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE 0 END) as daily_total,
        COUNT(CASE WHEN i.item_type = 'enum' AND r.enum_value = 'NG' THEN 1 END) as daily_ng
    FROM lpg_daily_records r
    JOIN lpg_inspection_items i ON r.item_id = i.id
    WHERE r.record_date < ?
    GROUP BY r.record_date
    ORDER BY r.record_date DESC
    LIMIT 7
");
$stmt->execute([$date]);
$recentRecords = $stmt->fetchAll();

$displayDate = date('d/m/Y', strtotime($date));
$thaiMonth = getThaiMonth((int)date('m', strtotime($date)));
$thaiYear = date('Y', strtotime($date)) + 543;

// Calculate statistics for this day
$totalUsage = 0;
$ngCount = 0;
$okCount = 0;

foreach ($records as $record) {
    if ($record['item_type'] == 'number') {
        $totalUsage += floatval($record['number_value']);
    } else {
        if ($record['enum_value'] == 'NG') {
            $ngCount++;
        } else {
            $okCount++;
        }
    }
}
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Summary Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo count($records); ?></h3>
                        <p>รายการทั้งหมด</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-list"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo number_format($totalUsage, 2); ?></h3>
                        <p>ปริมาณการใช้รวม</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo $okCount; ?></h3>
                        <p>OK</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo $ngCount; ?></h3>
                        <p>NG</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <!-- Records Table -->
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-clipboard-list"></i>
                            รายละเอียดการบันทึก
                        </h3>
                        <div class="card-tools">
                            <a href="edit.php?date=<?php echo $date; ?>" class="btn btn-tool">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="delete.php?date=<?php echo $date; ?>" class="btn btn-tool">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th style="width: 15%">เลขที่เอกสาร:</th>
                                <td><?php echo $records[0]['doc_no'] ?? '-'; ?></td>
                                <th style="width: 15%">ผู้บันทึก:</th>
                                <td><?php echo $records[0]['recorded_by_name'] ?? $records[0]['recorded_by']; ?></td>
                            </tr>
                            <tr>
                                <th>วันที่บันทึก:</th>
                                <td><?php echo $displayDate; ?></td>
                                <th>เวลาที่บันทึก:</th>
                                <td><?php echo date('H:i:s', strtotime($records[0]['created_at'])); ?></td>
                            </tr>
                            <?php if ($records[0]['updated_at']): ?>
                            <tr>
                                <th>ปรับปรุงล่าสุด:</th>
                                <td colspan="3"><?php echo date('d/m/Y H:i:s', strtotime($records[0]['updated_at'])); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                        
                        <hr>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ลำดับ</th>
                                        <th>รายการ</th>
                                        <th>ประเภท</th>
                                        <th>ค่าที่บันทึก</th>
                                        <th>ค่ามาตรฐาน</th>
                                        <th>ผลการประเมิน</th>
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
                                                echo '<strong>' . number_format($record['number_value'], 2) . '</strong> ' . $record['unit'];
                                            } else {
                                                echo '<strong>' . $record['enum_value'] . '</strong>';
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
                                                ?>
                                                <span class="badge badge-<?php echo $status == 'OK' ? 'success' : 'danger'; ?> p-2">
                                                    <?php echo $status; ?>
                                                </span>
                                                <small class="text-muted d-block">
                                                    เบี่ยงเบน <?php echo number_format($deviation, 2); ?>%
                                                </small>
                                                <?php
                                            } else {
                                                $status = $record['enum_value'];
                                                ?>
                                                <span class="badge badge-<?php echo $status == 'OK' ? 'success' : 'danger'; ?> p-2">
                                                    <?php echo $status; ?>
                                                </span>
                                                <?php
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($records[0]['remarks']): ?>
                        <div class="callout callout-info mt-3">
                            <h5>หมายเหตุ</h5>
                            <p><?php echo nl2br(htmlspecialchars($records[0]['remarks'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Monthly Summary Card -->
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-pie"></i>
                            สรุปประจำเดือน <?php echo $thaiMonth . ' ' . $thaiYear; ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="info-box bg-info">
                            <span class="info-box-icon">
                                <i class="fas fa-chart-line"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">ปริมาณการใช้รวม</span>
                                <span class="info-box-number"><?php echo number_format($monthSummary['month_total'] ?? 0, 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="info-box bg-success">
                            <span class="info-box-icon">
                                <i class="fas fa-check-circle"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">OK ทั้งหมด</span>
                                <span class="info-box-number"><?php echo number_format($monthSummary['month_ok'] ?? 0); ?></span>
                            </div>
                        </div>
                        
                        <div class="info-box bg-danger">
                            <span class="info-box-icon">
                                <i class="fas fa-times-circle"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">NG ทั้งหมด</span>
                                <span class="info-box-number"><?php echo number_format($monthSummary['month_ng'] ?? 0); ?></span>
                            </div>
                        </div>
                        
                        <?php if (($monthSummary['month_ok'] ?? 0) + ($monthSummary['month_ng'] ?? 0) > 0): ?>
                        <?php 
                        $totalChecks = ($monthSummary['month_ok'] ?? 0) + ($monthSummary['month_ng'] ?? 0);
                        $okPercent = ($monthSummary['month_ok'] ?? 0) / $totalChecks * 100;
                        ?>
                        <div class="progress-group">
                            <span class="progress-text">อัตราการผ่าน (OK)</span>
                            <span class="float-right"><b><?php echo number_format($okPercent, 1); ?>%</b></span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-success" style="width: <?php echo $okPercent; ?>%"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Comparison Card -->
                <div class="card card-secondary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i>
                            เปรียบเทียบ 7 วันล่าสุด
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>วันที่</th>
                                    <th class="text-right">ปริมาณ</th>
                                    <th class="text-center">NG</th>
                                    <th>เทียบกับวันนี้</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentRecords as $recent): ?>
                                <?php 
                                $diff = $totalUsage - $recent['daily_total'];
                                $diffPercent = $recent['daily_total'] > 0 ? ($diff / $recent['daily_total']) * 100 : 0;
                                ?>
                                <tr>
                                    <td><?php echo date('d/m', strtotime($recent['record_date'])); ?></td>
                                    <td class="text-right"><?php echo number_format($recent['daily_total'], 1); ?></td>
                                    <td class="text-center">
                                        <span class="badge badge-<?php echo $recent['daily_ng'] > 0 ? 'danger' : 'success'; ?>">
                                            <?php echo $recent['daily_ng']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $diff > 0 ? 'danger' : ($diff < 0 ? 'success' : 'secondary'); ?>">
                                            <?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diff, 1); ?> 
                                            (<?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diffPercent, 1); ?>%)
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Actions Card -->
                <div class="card">
                    <div class="card-body">
                        <a href="index.php" class="btn btn-secondary btn-block">
                            <i class="fas fa-arrow-left"></i> กลับ
                        </a>
                        <a href="edit.php?date=<?php echo $date; ?>" class="btn btn-warning btn-block">
                            <i class="fas fa-edit"></i> แก้ไข
                        </a>
                        <a href="delete.php?date=<?php echo $date; ?>" class="btn btn-danger btn-block">
                            <i class="fas fa-trash"></i> ลบ
                        </a>
                        <button onclick="window.print()" class="btn btn-info btn-block">
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
    .btn, .card-tools, .main-footer, .main-header, .main-sidebar, .small-box {
        display: none !important;
    }
    .content-wrapper {
        margin-left: 0 !important;
    }
    .card {
        border: 1px solid #dee2e6 !important;
    }
}
</style>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>