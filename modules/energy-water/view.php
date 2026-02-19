<?php
/**
 * Energy & Water Module - View Reading Details
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Energy & Water - ดูรายละเอียด';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Energy & Water', 'link' => 'index.php'],
    ['title' => 'ดูรายละเอียด', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Load required files
require_once __DIR__ . '/../../includes/functions.php';

// Get database connection
$db = getDB();

// Get reading ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    $_SESSION['error'] = 'ไม่พบข้อมูลที่ต้องการดู';
    header('Location: index.php');
    exit();
}

// Get reading details
$stmt = $db->prepare("
    SELECT 
        r.*,
        m.meter_code,
        m.meter_name,
        m.meter_type,
        m.location,
        m.initial_reading,
        d.doc_no,
        d.rev_no,
        u.fullname as recorded_by_name
    FROM meter_daily_readings r
    JOIN mc_mdb_water m ON r.meter_id = m.id
    LEFT JOIN documents d ON r.doc_id = d.id
    LEFT JOIN users u ON r.recorded_by = u.username
    WHERE r.id = ?
");
$stmt->execute([$id]);
$reading = $stmt->fetch();

if (!$reading) {
    $_SESSION['error'] = 'ไม่พบข้อมูลที่ต้องการดู';
    header('Location: index.php');
    exit();
}

// Get historical data for this meter
$stmt = $db->prepare("
    SELECT 
        record_date,
        morning_reading,
        evening_reading,
        usage_amount
    FROM meter_daily_readings
    WHERE meter_id = ? AND id != ?
    ORDER BY record_date DESC
    LIMIT 10
");
$stmt->execute([$reading['meter_id'], $id]);
$history = $stmt->fetchAll();

// Calculate statistics
$avg_usage = 0;
if (!empty($history)) {
    $total = 0;
    foreach ($history as $h) {
        $total += $h['usage_amount'];
    }
    $avg_usage = $total / count($history);
}

// Calculate comparison with average
$comparison = null;
$comparisonPercent = null;
if ($avg_usage > 0) {
    $comparison = $reading['usage_amount'] - $avg_usage;
    $comparisonPercent = ($comparison / $avg_usage) * 100;
}

// Get daily trend for chart
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(record_date, '%d/%m') as day,
        usage_amount
    FROM meter_daily_readings
    WHERE meter_id = ? 
    AND MONTH(record_date) = MONTH(?) 
    AND YEAR(record_date) = YEAR(?)
    ORDER BY record_date
");
$stmt->execute([$reading['meter_id'], $reading['record_date'], $reading['record_date']]);
$trendData = $stmt->fetchAll();
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <div class="row">
            <div class="col-md-6">
                <!-- Reading Details Card -->
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
                                <th style="width: 30%;">วันที่บันทึก:</th>
                                <td><?php echo date('d/m/Y', strtotime($reading['record_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>เลขที่เอกสาร:</th>
                                <td><?php echo $reading['doc_no'] ?: '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Rev.No.:</th>
                                <td><?php echo $reading['rev_no'] ?: '-'; ?></td>
                            </tr>
                            <tr>
                                <th>ประเภทมิเตอร์:</th>
                                <td>
                                    <span class="badge badge-<?php echo $reading['meter_type'] == 'electricity' ? 'warning' : 'info'; ?> p-2">
                                        <i class="fas fa-<?php echo $reading['meter_type'] == 'electricity' ? 'bolt' : 'water'; ?>"></i>
                                        <?php echo $reading['meter_type'] == 'electricity' ? 'มิเตอร์ไฟฟ้า' : 'มิเตอร์น้ำ'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>รหัสมิเตอร์:</th>
                                <td><strong><?php echo $reading['meter_code']; ?></strong></td>
                            </tr>
                            <tr>
                                <th>ชื่อมิเตอร์:</th>
                                <td><?php echo $reading['meter_name']; ?></td>
                            </tr>
                            <tr>
                                <th>ตำแหน่งที่ติดตั้ง:</th>
                                <td><?php echo $reading['location'] ?: '-'; ?></td>
                            </tr>
                            <tr>
                                <th>ค่าเริ่มต้น:</th>
                                <td><?php echo number_format($reading['initial_reading'], 2); ?></td>
                            </tr>
                            <tr>
                                <th>ค่าเช้า:</th>
                                <td><h5><?php echo number_format($reading['morning_reading'], 2); ?></h5></td>
                            </tr>
                            <tr>
                                <th>ค่าเย็น:</th>
                                <td><h5><?php echo number_format($reading['evening_reading'], 2); ?></h5></td>
                            </tr>
                            <tr>
                                <th>ปริมาณการใช้:</th>
                                <td>
                                    <h3 class="text-primary"><?php echo number_format($reading['usage_amount'], 2); ?> 
                                        <small><?php echo $reading['meter_type'] == 'electricity' ? 'kWh' : 'm³'; ?></small>
                                    </h3>
                                </td>
                            </tr>
                            <tr>
                                <th>หมายเหตุ:</th>
                                <td><?php echo nl2br(htmlspecialchars($reading['remarks'] ?: '-')); ?></td>
                            </tr>
                            <tr>
                                <th>ผู้บันทึก:</th>
                                <td><?php echo $reading['recorded_by_name'] ?: $reading['recorded_by']; ?></td>
                            </tr>
                            <tr>
                                <th>วันที่สร้าง:</th>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($reading['created_at'])); ?></td>
                            </tr>
                            <?php if ($reading['updated_at']): ?>
                            <tr>
                                <th>ปรับปรุงล่าสุด:</th>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($reading['updated_at'])); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <!-- Statistics Card -->
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-bar"></i>
                            สถิติและการวิเคราะห์
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="info-box bg-info">
                            <span class="info-box-icon">
                                <i class="fas fa-calculator"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">ค่าเฉลี่ยการใช้งานย้อนหลัง</span>
                                <span class="info-box-number">
                                    <?php echo number_format($avg_usage, 2); ?> 
                                    <small><?php echo $reading['meter_type'] == 'electricity' ? 'kWh' : 'm³'; ?></small>
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($comparison !== null): ?>
                        <div class="info-box bg-<?php echo $comparison > 0 ? 'warning' : 'success'; ?>">
                            <span class="info-box-icon">
                                <i class="fas fa-<?php echo $comparison > 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">เปรียบเทียบกับค่าเฉลี่ย</span>
                                <span class="info-box-number">
                                    <?php echo $comparison > 0 ? '+' : ''; ?><?php echo number_format($comparison, 2); ?> 
                                    <small>(<?php echo number_format(abs($comparisonPercent), 2); ?>%)</small>
                                </span>
                                <div class="progress">
                                    <div class="progress-bar bg-<?php echo $comparison > 0 ? 'warning' : 'success'; ?>" 
                                         style="width: <?php echo min(abs($comparisonPercent), 100); ?>%"></div>
                                </div>
                                <span class="progress-description">
                                    <?php echo $comparison > 0 ? 'สูงกว่าค่าเฉลี่ย' : 'ต่ำกว่าค่าเฉลี่ย'; ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Daily Usage Chart -->
                        <div class="chart-container mt-3">
                            <canvas id="dailyChart" style="height: 200px;"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- History Card -->
                <div class="card card-secondary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i>
                            ประวัติการบันทึกย้อนหลัง (10 รายการล่าสุด)
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>วันที่</th>
                                    <th>ค่าเช้า</th>
                                    <th>ค่าเย็น</th>
                                    <th>ปริมาณการใช้</th>
                                    <th>เทียบกับวันนี้</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $item): ?>
                                <?php 
                                $diff = $reading['usage_amount'] - $item['usage_amount'];
                                $diffPercent = $item['usage_amount'] > 0 ? ($diff / $item['usage_amount']) * 100 : 0;
                                ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($item['record_date'])); ?></td>
                                    <td class="text-right"><?php echo number_format($item['morning_reading'], 2); ?></td>
                                    <td class="text-right"><?php echo number_format($item['evening_reading'], 2); ?></td>
                                    <td class="text-right">
                                        <strong><?php echo number_format($item['usage_amount'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $diff > 0 ? 'danger' : ($diff < 0 ? 'success' : 'secondary'); ?>">
                                            <?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diff, 2); ?> 
                                            (<?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diffPercent, 1); ?>%)
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($history)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">ไม่มีประวัติ</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
                        <button class="btn btn-success" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    // Daily Chart
    const ctx = document.getElementById('dailyChart').getContext('2d');
    
    const trendLabels = [<?php 
        $labels = [];
        foreach ($trendData as $item) {
            $labels[] = "'" . $item['day'] . "'";
        }
        echo implode(',', $labels);
    ?>];
    
    const trendValues = [<?php 
        $values = [];
        foreach ($trendData as $item) {
            $values[] = $item['usage_amount'];
        }
        echo implode(',', $values);
    ?>];
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'ปริมาณการใช้',
                data: trendValues,
                borderColor: '<?php echo $reading['meter_type'] == 'electricity' ? '#ffc107' : '#17a2b8'; ?>',
                backgroundColor: '<?php echo $reading['meter_type'] == 'electricity' ? 'rgba(255, 193, 7, 0.1)' : 'rgba(23, 162, 184, 0.1)'; ?>',
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '<?php echo $reading['meter_type'] == 'electricity' ? '#ffc107' : '#17a2b8'; ?>',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'ปริมาณ: ' + context.raw.toFixed(2) + 
                                   ' <?php echo $reading['meter_type'] == 'electricity' ? 'kWh' : 'm³'; ?>';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: '<?php echo $reading['meter_type'] == 'electricity' ? 'kWh' : 'm³'; ?>'
                    }
                }
            }
        }
    });
});

function exportToPDF() {
    // Implement PDF export functionality
    window.open('export_pdf.php?id=<?php echo $id; ?>', '_blank');
}
</script>

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