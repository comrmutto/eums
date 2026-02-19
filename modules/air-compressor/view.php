<?php
/**
 * Air Compressor Module - View Record Details
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Air Compressor - ดูรายละเอียด';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Air Compressor', 'link' => 'index.php'],
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

// Get record details with related information
$stmt = $db->prepare("
    SELECT 
        r.*,
        m.machine_code,
        m.machine_name,
        m.brand,
        m.model,
        m.capacity as machine_capacity,
        m.unit as machine_unit,
        s.inspection_item,
        s.standard_value,
        s.min_value,
        s.max_value,
        s.unit as inspection_unit,
        d.doc_no,
        d.rev_no,
        u.fullname as recorded_by_name
    FROM air_daily_records r
    JOIN mc_air m ON r.machine_id = m.id
    JOIN air_inspection_standards s ON r.inspection_item_id = s.id
    LEFT JOIN documents d ON r.doc_id = d.id
    LEFT JOIN users u ON r.recorded_by = u.username
    WHERE r.id = ?
");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    $_SESSION['error'] = 'ไม่พบข้อมูลที่ต้องการดู';
    header('Location: index.php');
    exit();
}

// Calculate status
$status = 'ok';
$statusMessage = '';
$deviation = 0;

if ($record['min_value'] && $record['max_value']) {
    if ($record['actual_value'] < $record['min_value']) {
        $status = 'ng';
        $statusMessage = 'ต่ำกว่าค่ามาตรฐาน';
    } elseif ($record['actual_value'] > $record['max_value']) {
        $status = 'ng';
        $statusMessage = 'สูงกว่าค่ามาตรฐาน';
    } else {
        $statusMessage = 'อยู่ในเกณฑ์มาตรฐาน';
    }
} else {
    $deviation = (($record['actual_value'] - $record['standard_value']) / $record['standard_value']) * 100;
    if (abs($deviation) > 10) {
        $status = 'ng';
        $statusMessage = 'ค่าเบี่ยงเบนเกิน 10%';
    } else {
        $statusMessage = 'อยู่ในเกณฑ์มาตรฐาน (ค่าเบี่ยงเบน ' . number_format(abs($deviation), 2) . '%)';
    }
}

// Get historical data for this machine and inspection item
$stmt = $db->prepare("
    SELECT 
        record_date,
        actual_value,
        created_at
    FROM air_daily_records
    WHERE machine_id = ? AND inspection_item_id = ?
    ORDER BY record_date DESC
    LIMIT 10
");
$stmt->execute([$record['machine_id'], $record['inspection_item_id']]);
$history = $stmt->fetchAll();
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
                                <th style="width: 30%;">วันที่บันทึก:</th>
                                <td><?php echo date('d/m/Y', strtotime($record['record_date'])); ?></td>
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
                                <th>เครื่องจักร:</th>
                                <td>
                                    <strong><?php echo $record['machine_code']; ?></strong> - 
                                    <?php echo $record['machine_name']; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>ยี่ห้อ/รุ่น:</th>
                                <td><?php echo $record['brand'] . ' / ' . $record['model']; ?></td>
                            </tr>
                            <tr>
                                <th>ความจุ:</th>
                                <td><?php echo number_format($record['machine_capacity'], 2) . ' ' . $record['machine_unit']; ?></td>
                            </tr>
                            <tr>
                                <th>หัวข้อตรวจสอบ:</th>
                                <td><?php echo $record['inspection_item']; ?></td>
                            </tr>
                            <tr>
                                <th>ค่ามาตรฐาน:</th>
                                <td>
                                    <?php if ($record['min_value'] && $record['max_value']): ?>
                                        <?php echo number_format($record['min_value'], 2) . ' - ' . number_format($record['max_value'], 2) . ' ' . $record['inspection_unit']; ?>
                                    <?php else: ?>
                                        <?php echo number_format($record['standard_value'], 2) . ' ' . $record['inspection_unit']; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>ค่าที่วัดได้:</th>
                                <td>
                                    <h4><?php echo number_format($record['actual_value'], 2); ?> <?php echo $record['inspection_unit']; ?></h4>
                                </td>
                            </tr>
                            <tr>
                                <th>สถานะ:</th>
                                <td>
                                    <span class="badge badge-<?php echo $status == 'ok' ? 'success' : 'danger'; ?> p-2">
                                        <i class="fas fa-<?php echo $status == 'ok' ? 'check' : 'times'; ?>-circle"></i>
                                        <?php echo $status == 'ok' ? 'ผ่าน' : 'ไม่ผ่าน'; ?>
                                    </span>
                                    <small class="text-muted ml-2"><?php echo $statusMessage; ?></small>
                                </td>
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
                <!-- Status Chart Card -->
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-pie"></i>
                            การประเมินผล
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <canvas id="statusChart" style="min-height: 200px;"></canvas>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box bg-<?php echo $status == 'ok' ? 'success' : 'danger'; ?>">
                                    <span class="info-box-icon">
                                        <i class="fas fa-<?php echo $status == 'ok' ? 'check' : 'exclamation'; ?>-triangle"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">ผลการตรวจสอบ</span>
                                        <span class="info-box-number">
                                            <?php echo $status == 'ok' ? 'ผ่านเกณฑ์' : 'ไม่ผ่านเกณฑ์'; ?>
                                        </span>
                                        <div class="progress">
                                            <div class="progress-bar" style="width: <?php echo $status == 'ok' ? '100' : '0'; ?>%"></div>
                                        </div>
                                        <span class="progress-description">
                                            <?php echo $statusMessage; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if ($deviation != 0): ?>
                                <div class="info-box bg-warning">
                                    <span class="info-box-icon">
                                        <i class="fas fa-chart-line"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">ค่าเบี่ยงเบน</span>
                                        <span class="info-box-number">
                                            <?php echo number_format(abs($deviation), 2); ?>%
                                        </span>
                                        <div class="progress">
                                            <div class="progress-bar" style="width: <?php echo min(abs($deviation) * 10, 100); ?>%"></div>
                                        </div>
                                        <span class="progress-description">
                                            <?php echo $deviation > 0 ? 'สูงกว่า' : 'ต่ำกว่า'; ?> มาตรฐาน
                                        </span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
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
                                    <th>ค่า</th>
                                    <th>หน่วย</th>
                                    <th>สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $item): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($item['record_date'])); ?></td>
                                    <td><?php echo number_format($item['actual_value'], 2); ?></td>
                                    <td><?php echo $record['inspection_unit']; ?></td>
                                    <td>
                                        <?php 
                                        // Calculate status for historical item
                                        $histStatus = 'ok';
                                        if ($record['min_value'] && $record['max_value']) {
                                            if ($item['actual_value'] < $record['min_value'] || $item['actual_value'] > $record['max_value']) {
                                                $histStatus = 'ng';
                                            }
                                        } else {
                                            $histDev = (($item['actual_value'] - $record['standard_value']) / $record['standard_value']) * 100;
                                            if (abs($histDev) > 10) {
                                                $histStatus = 'ng';
                                            }
                                        }
                                        ?>
                                        <span class="badge badge-<?php echo $histStatus == 'ok' ? 'success' : 'danger'; ?>">
                                            <?php echo $histStatus == 'ok' ? 'ผ่าน' : 'ไม่ผ่าน'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    // Status Chart
    const ctx = document.getElementById('statusChart').getContext('2d');
    
    // Calculate percentage relative to standard
    const standard = <?php echo $record['standard_value']; ?>;
    const actual = <?php echo $record['actual_value']; ?>;
    const percentage = (actual / standard) * 100;
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['ค่าที่วัดได้', 'ค่ามาตรฐาน'],
            datasets: [{
                data: [actual, Math.max(standard - actual, 0)],
                backgroundColor: [
                    '<?php echo $status == 'ok' ? '#28a745' : '#dc3545'; ?>',
                    '#6c757d'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.raw.toFixed(2) + ' <?php echo $record['inspection_unit']; ?>';
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>