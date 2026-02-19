<?php
/**
 * Boiler Module - View Record Details
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Boiler - ดูรายละเอียด';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Boiler', 'link' => 'index.php'],
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
    SELECT 
        r.*,
        m.machine_code,
        m.machine_name,
        m.brand,
        m.model,
        m.capacity,
        m.pressure_rating,
        d.doc_no,
        d.rev_no,
        u.fullname as recorded_by_name
    FROM boiler_daily_records r
    JOIN mc_boiler m ON r.machine_id = m.id
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

// Get historical data for this machine
$stmt = $db->prepare("
    SELECT 
        record_date,
        steam_pressure,
        steam_temperature,
        feed_water_level,
        fuel_consumption,
        operating_hours
    FROM boiler_daily_records
    WHERE machine_id = ? AND id != ?
    ORDER BY record_date DESC
    LIMIT 10
");
$stmt->execute([$record['machine_id'], $id]);
$history = $stmt->fetchAll();

// Calculate statistics
$pressureStatus = ($record['steam_pressure'] >= 8 && $record['steam_pressure'] <= 12) ? 'ok' : 'ng';
$tempStatus = ($record['steam_temperature'] >= 170 && $record['steam_temperature'] <= 190) ? 'ok' : 'ng';
$waterStatus = ($record['feed_water_level'] >= 0.5 && $record['feed_water_level'] <= 1.5) ? 'ok' : 'ng';

$displayDate = date('d/m/Y', strtotime($record['record_date']));
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
                                <th>เครื่อง Boiler:</th>
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
                                <td><?php echo number_format($record['capacity'], 2); ?> T/hr</td>
                            </tr>
                            <tr>
                                <th>แรงดันสูงสุด:</th>
                                <td><?php echo number_format($record['pressure_rating'], 2); ?> bar</td>
                            </tr>
                        </table>
                        
                        <hr>
                        
                        <h5>ข้อมูลการทำงาน</h5>
                        <table class="table table-bordered">
                            <tr>
                                <th>แรงดันไอน้ำ:</th>
                                <td>
                                    <h4><?php echo number_format($record['steam_pressure'], 2); ?> bar</h4>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $pressureStatus == 'ok' ? 'success' : 'danger'; ?> p-2">
                                        <i class="fas fa-<?php echo $pressureStatus == 'ok' ? 'check' : 'times'; ?>-circle"></i>
                                        <?php echo $pressureStatus == 'ok' ? 'ปกติ' : 'ผิดปกติ'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>อุณหภูมิไอน้ำ:</th>
                                <td>
                                    <h4><?php echo number_format($record['steam_temperature'], 1); ?> °C</h4>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $tempStatus == 'ok' ? 'success' : 'danger'; ?> p-2">
                                        <i class="fas fa-<?php echo $tempStatus == 'ok' ? 'check' : 'times'; ?>-circle"></i>
                                        <?php echo $tempStatus == 'ok' ? 'ปกติ' : 'ผิดปกติ'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>ระดับน้ำในหม้อ:</th>
                                <td>
                                    <h4><?php echo number_format($record['feed_water_level'], 2); ?> m</h4>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $waterStatus == 'ok' ? 'success' : 'danger'; ?> p-2">
                                        <i class="fas fa-<?php echo $waterStatus == 'ok' ? 'check' : 'times'; ?>-circle"></i>
                                        <?php echo $waterStatus == 'ok' ? 'ปกติ' : 'ผิดปกติ'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>ปริมาณเชื้อเพลิง:</th>
                                <td colspan="2">
                                    <h4><?php echo number_format($record['fuel_consumption'], 2); ?> L</h4>
                                </td>
                            </tr>
                            <tr>
                                <th>ชั่วโมงการทำงาน:</th>
                                <td colspan="2">
                                    <h4><?php echo number_format($record['operating_hours'], 1); ?> hr</h4>
                                </td>
                            </tr>
                        </table>
                        
                        <?php if ($record['remarks']): ?>
                        <div class="callout callout-info">
                            <h5>หมายเหตุ</h5>
                            <p><?php echo nl2br(htmlspecialchars($record['remarks'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <table class="table table-sm">
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
                <!-- Status Summary Card -->
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-pie"></i>
                            สรุปสถานะ
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="info-box bg-<?php echo $pressureStatus == 'ok' ? 'success' : 'danger'; ?>">
                            <span class="info-box-icon">
                                <i class="fas fa-gauge-high"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">แรงดันไอน้ำ</span>
                                <span class="info-box-number">
                                    <?php echo number_format($record['steam_pressure'], 2); ?> bar
                                </span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?php echo ($record['steam_pressure'] / 15) * 100; ?>%"></div>
                                </div>
                                <span class="progress-description">
                                    มาตรฐาน: 8-12 bar
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-box bg-<?php echo $tempStatus == 'ok' ? 'success' : 'danger'; ?>">
                            <span class="info-box-icon">
                                <i class="fas fa-temperature-high"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">อุณหภูมิไอน้ำ</span>
                                <span class="info-box-number">
                                    <?php echo number_format($record['steam_temperature'], 1); ?> °C
                                </span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?php echo ($record['steam_temperature'] / 250) * 100; ?>%"></div>
                                </div>
                                <span class="progress-description">
                                    มาตรฐาน: 170-190 °C
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-box bg-<?php echo $waterStatus == 'ok' ? 'success' : 'danger'; ?>">
                            <span class="info-box-icon">
                                <i class="fas fa-water"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">ระดับน้ำในหม้อ</span>
                                <span class="info-box-number">
                                    <?php echo number_format($record['feed_water_level'], 2); ?> m
                                </span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?php echo ($record['feed_water_level'] / 2) * 100; ?>%"></div>
                                </div>
                                <span class="progress-description">
                                    มาตรฐาน: 0.5-1.5 m
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-box bg-warning">
                            <span class="info-box-icon">
                                <i class="fas fa-fire"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">อัตราสิ้นเปลืองเชื้อเพลิง</span>
                                <span class="info-box-number">
                                    <?php 
                                    $fuelRate = $record['operating_hours'] > 0 ? 
                                               $record['fuel_consumption'] / $record['operating_hours'] : 0;
                                    echo number_format($fuelRate, 2); 
                                    ?> L/hr
                                </span>
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
                                    <th>แรงดัน</th>
                                    <th>อุณหภูมิ</th>
                                    <th>เชื้อเพลิง</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $item): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($item['record_date'])); ?></td>
                                    <td><?php echo number_format($item['steam_pressure'], 2); ?></td>
                                    <td><?php echo number_format($item['steam_temperature'], 1); ?></td>
                                    <td><?php echo number_format($item['fuel_consumption'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($history)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">ไม่มีประวัติ</td>
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