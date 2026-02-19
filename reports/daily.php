<?php
/**
 * Daily Report - All Modules
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

// Set page title
$pageTitle = 'รายงานประจำวัน';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'รายงาน', 'link' => '#'],
    ['title' => 'ประจำวัน', 'link' => null]
];

// Include header
require_once __DIR__ . '/../includes/header.php';

// Load required files
require_once __DIR__ . '/../includes/functions.php';

// Get database connection
$db = getDB();

// Get parameters
$reportDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$displayDate = date('d/m/Y', strtotime($reportDate));
$thaiDate = date('d', strtotime($reportDate)) . ' ' . 
            getThaiMonth(date('m', strtotime($reportDate))) . ' ' . 
            (date('Y', strtotime($reportDate)) + 543);

// Get data from all modules
$modules = [
    'air' => [
        'name' => 'Air Compressor',
        'icon' => 'compress',
        'color' => 'info',
        'query' => "
            SELECT 
                COUNT(*) as total_records,
                COUNT(DISTINCT r.machine_id) as machines,
                SUM(actual_value) as total_usage,
                AVG(actual_value) as avg_value,
                COUNT(CASE 
                    WHEN (s.min_value IS NOT NULL AND (r.actual_value < s.min_value OR r.actual_value > s.max_value))
                         OR (s.min_value IS NULL AND ABS(r.actual_value - s.standard_value) > s.standard_value * 0.1)
                    THEN 1 END) as ng_count
            FROM air_daily_records r
            JOIN air_inspection_standards s ON r.inspection_item_id = s.id
            WHERE r.record_date = ?
        "
    ],
    'energy' => [
        'name' => 'Energy & Water',
        'icon' => 'bolt',
        'color' => 'warning',
        'query' => "
            SELECT 
                COUNT(*) as total_records,
                COUNT(DISTINCT meter_id) as meters,
                SUM(usage_amount) as total_usage,
                AVG(usage_amount) as avg_usage,
                SUM(CASE WHEN meter_type = 'electricity' THEN usage_amount ELSE 0 END) as electricity_usage,
                SUM(CASE WHEN meter_type = 'water' THEN usage_amount ELSE 0 END) as water_usage
            FROM meter_daily_readings r
            JOIN mc_mdb_water m ON r.meter_id = m.id
            WHERE r.record_date = ?
        "
    ],
    'lpg' => [
        'name' => 'LPG',
        'icon' => 'fire',
        'color' => 'danger',
        'query' => "
            SELECT 
                COUNT(*) as total_records,
                COUNT(DISTINCT item_id) as items,
                SUM(CASE WHEN item_type = 'number' THEN number_value ELSE 0 END) as total_usage,
                SUM(CASE WHEN item_type = 'enum' AND enum_value = 'OK' THEN 1 ELSE 0 END) as ok_count,
                SUM(CASE WHEN item_type = 'enum' AND enum_value = 'NG' THEN 1 ELSE 0 END) as ng_count
            FROM lpg_daily_records r
            JOIN lpg_inspection_items i ON r.item_id = i.id
            WHERE r.record_date = ?
        "
    ],
    'boiler' => [
        'name' => 'Boiler',
        'icon' => 'industry',
        'color' => 'secondary',
        'query' => "
            SELECT 
                COUNT(*) as total_records,
                COUNT(DISTINCT machine_id) as machines,
                SUM(steam_pressure) as total_pressure,
                AVG(steam_pressure) as avg_pressure,
                SUM(steam_temperature) as total_temp,
                AVG(steam_temperature) as avg_temp,
                SUM(fuel_consumption) as total_fuel,
                SUM(operating_hours) as total_hours
            FROM boiler_daily_records
            WHERE record_date = ?
        "
    ],
    'summary' => [
        'name' => 'Summary Electricity',
        'icon' => 'chart-line',
        'color' => 'success',
        'query' => "
            SELECT 
                ee_unit,
                cost_per_unit,
                total_cost,
                pe
            FROM electricity_summary
            WHERE record_date = ?
        "
    ]
];

$moduleData = [];
$hasData = false;

foreach ($modules as $key => $module) {
    $stmt = $db->prepare($module['query']);
    $stmt->execute([$reportDate]);
    $data = $stmt->fetch();
    
    if ($data && array_filter($data)) {
        $hasData = true;
    }
    
    $moduleData[$key] = $data ?: [];
}

// Get recent activities across all modules
$recentActivities = [];

// Air Compressor details
$stmt = $db->prepare("
    SELECT 
        'air' as module,
        r.record_date,
        r.actual_value as value,
        m.machine_name,
        s.inspection_item,
        r.recorded_by
    FROM air_daily_records r
    JOIN mc_air m ON r.machine_id = m.id
    JOIN air_inspection_standards s ON r.inspection_item_id = s.id
    WHERE r.record_date = ?
    ORDER BY m.machine_code
    LIMIT 10
");
$stmt->execute([$reportDate]);
$recentActivities['air'] = $stmt->fetchAll();

// Energy & Water details
$stmt = $db->prepare("
    SELECT 
        'energy' as module,
        r.record_date,
        r.usage_amount as value,
        m.meter_name,
        m.meter_type,
        r.morning_reading,
        r.evening_reading,
        r.recorded_by
    FROM meter_daily_readings r
    JOIN mc_mdb_water m ON r.meter_id = m.id
    WHERE r.record_date = ?
    ORDER BY m.meter_type, m.meter_code
    LIMIT 10
");
$stmt->execute([$reportDate]);
$recentActivities['energy'] = $stmt->fetchAll();

// LPG details
$stmt = $db->prepare("
    SELECT 
        'lpg' as module,
        r.record_date,
        COALESCE(r.number_value, r.enum_value) as value,
        i.item_name,
        i.item_type,
        r.recorded_by
    FROM lpg_daily_records r
    JOIN lpg_inspection_items i ON r.item_id = i.id
    WHERE r.record_date = ?
    ORDER BY i.item_no
    LIMIT 10
");
$stmt->execute([$reportDate]);
$recentActivities['lpg'] = $stmt->fetchAll();

// Boiler details
$stmt = $db->prepare("
    SELECT 
        'boiler' as module,
        r.record_date,
        r.steam_pressure,
        r.steam_temperature,
        r.fuel_consumption,
        r.operating_hours,
        m.machine_name,
        r.recorded_by
    FROM boiler_daily_records r
    JOIN mc_boiler m ON r.machine_id = m.id
    WHERE r.record_date = ?
    ORDER BY m.machine_code
    LIMIT 10
");
$stmt->execute([$reportDate]);
$recentActivities['boiler'] = $stmt->fetchAll();

// Summary Electricity
$stmt = $db->prepare("
    SELECT 
        'summary' as module,
        record_date,
        ee_unit,
        cost_per_unit,
        total_cost,
        pe,
        recorded_by
    FROM electricity_summary
    WHERE record_date = ?
");
$stmt->execute([$reportDate]);
$recentActivities['summary'] = $stmt->fetchAll();
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Date Selector Card -->
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-alt"></i>
                    เลือกวันที่
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" class="form-inline justify-content-center">
                    <div class="form-group mr-2">
                        <label class="mr-2">วันที่:</label>
                        <div class="input-group date" id="datePicker" data-target-input="nearest">
                            <input type="text" class="form-control datetimepicker-input" 
                                   name="date" id="reportDate" 
                                   value="<?php echo $displayDate; ?>" 
                                   data-target="#datePicker">
                            <div class="input-group-append" data-target="#datePicker" data-toggle="datetimepicker">
                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> แสดงรายงาน
                    </button>
                    <button type="button" class="btn btn-success ml-2" onclick="window.location.href='?date=<?php echo date('Y-m-d'); ?>'">
                        <i class="fas fa-calendar-check"></i> วันนี้
                    </button>
                </form>
            </div>
        </div>

        <?php if (!$hasData): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            ไม่พบข้อมูลในวันที่ <strong><?php echo $thaiDate; ?></strong>
        </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo $moduleData['air']['total_records'] ?? 0; ?></h3>
                        <p>Air Compressor</p>
                        <small>เครื่อง: <?php echo $moduleData['air']['machines'] ?? 0; ?> | 
                               การใช้: <?php echo number_format($moduleData['air']['total_usage'] ?? 0, 2); ?></small>
                    </div>
                    <div class="icon">
                        <i class="fas fa-compress"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo $moduleData['energy']['total_records'] ?? 0; ?></h3>
                        <p>Energy & Water</p>
                        <small>ไฟฟ้า: <?php echo number_format($moduleData['energy']['electricity_usage'] ?? 0, 2); ?> kWh<br>
                               น้ำ: <?php echo number_format($moduleData['energy']['water_usage'] ?? 0, 2); ?> m³</small>
                    </div>
                    <div class="icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo $moduleData['lpg']['total_records'] ?? 0; ?></h3>
                        <p>LPG</p>
                        <small>OK: <?php echo $moduleData['lpg']['ok_count'] ?? 0; ?> | 
                               NG: <?php echo $moduleData['lpg']['ng_count'] ?? 0; ?></small>
                    </div>
                    <div class="icon">
                        <i class="fas fa-fire"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo isset($moduleData['summary']['ee_unit']) ? 1 : 0; ?></h3>
                        <p>Summary Electricity</p>
                        <small>หน่วย: <?php echo number_format($moduleData['summary']['ee_unit'] ?? 0, 2); ?> kWh<br>
                               ค่าไฟ: <?php echo number_format($moduleData['summary']['total_cost'] ?? 0, 2); ?> บาท</small>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Reports -->
        <div class="row">
            <!-- Air Compressor Details -->
            <div class="col-md-6">
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-compress"></i>
                            Air Compressor
                        </h3>
                        <div class="card-tools">
                            <a href="/eums/modules/air-compressor/index.php?date=<?php echo $reportDate; ?>" class="btn btn-tool">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentActivities['air'])): ?>
                            <p class="text-center text-muted p-3">ไม่มีข้อมูล</p>
                        <?php else: ?>
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>เครื่องจักร</th>
                                        <th>หัวข้อตรวจสอบ</th>
                                        <th class="text-right">ค่า</th>
                                        <th>ผู้บันทึก</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentActivities['air'] as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['machine_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['inspection_item']); ?></td>
                                        <td class="text-right"><?php echo number_format($item['value'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($item['recorded_by']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Energy & Water Details -->
            <div class="col-md-6">
                <div class="card card-warning">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bolt"></i>
                            Energy & Water
                        </h3>
                        <div class="card-tools">
                            <a href="/eums/modules/energy-water/index.php?date=<?php echo $reportDate; ?>" class="btn btn-tool">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentActivities['energy'])): ?>
                            <p class="text-center text-muted p-3">ไม่มีข้อมูล</p>
                        <?php else: ?>
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>มิเตอร์</th>
                                        <th>ประเภท</th>
                                        <th class="text-right">เช้า</th>
                                        <th class="text-right">เย็น</th>
                                        <th class="text-right">ใช้</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentActivities['energy'] as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['meter_name']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $item['meter_type'] == 'electricity' ? 'warning' : 'info'; ?>">
                                                <?php echo $item['meter_type'] == 'electricity' ? 'ไฟฟ้า' : 'น้ำ'; ?>
                                            </span>
                                        </td>
                                        <td class="text-right"><?php echo number_format($item['morning_reading'], 2); ?></td>
                                        <td class="text-right"><?php echo number_format($item['evening_reading'], 2); ?></td>
                                        <td class="text-right"><?php echo number_format($item['value'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- LPG Details -->
            <div class="col-md-6">
                <div class="card card-danger">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-fire"></i>
                            LPG
                        </h3>
                        <div class="card-tools">
                            <a href="/eums/modules/lpg/index.php?date=<?php echo $reportDate; ?>" class="btn btn-tool">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentActivities['lpg'])): ?>
                            <p class="text-center text-muted p-3">ไม่มีข้อมูล</p>
                        <?php else: ?>
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>รายการ</th>
                                        <th>ประเภท</th>
                                        <th class="text-right">ค่า</th>
                                        <th>สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentActivities['lpg'] as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $item['item_type'] == 'number' ? 'success' : 'warning'; ?>">
                                                <?php echo $item['item_type'] == 'number' ? 'ตัวเลข' : 'OK/NG'; ?>
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <?php 
                                            if ($item['item_type'] == 'number') {
                                                echo number_format($item['value'], 2);
                                            } else {
                                                echo $item['value'];
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($item['item_type'] == 'enum'): ?>
                                                <span class="badge badge-<?php echo $item['value'] == 'OK' ? 'success' : 'danger'; ?>">
                                                    <?php echo $item['value']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Boiler Details -->
            <div class="col-md-6">
                <div class="card card-secondary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-industry"></i>
                            Boiler
                        </h3>
                        <div class="card-tools">
                            <a href="/eums/modules/boiler/index.php?date=<?php echo $reportDate; ?>" class="btn btn-tool">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentActivities['boiler'])): ?>
                            <p class="text-center text-muted p-3">ไม่มีข้อมูล</p>
                        <?php else: ?>
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>เครื่อง</th>
                                        <th class="text-right">แรงดัน</th>
                                        <th class="text-right">อุณหภูมิ</th>
                                        <th class="text-right">เชื้อเพลิง</th>
                                        <th class="text-right">ชั่วโมง</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentActivities['boiler'] as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['machine_name']); ?></td>
                                        <td class="text-right"><?php echo number_format($item['steam_pressure'], 2); ?></td>
                                        <td class="text-right"><?php echo number_format($item['steam_temperature'], 1); ?></td>
                                        <td class="text-right"><?php echo number_format($item['fuel_consumption'], 2); ?></td>
                                        <td class="text-right"><?php echo number_format($item['operating_hours'], 1); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Summary Electricity -->
            <div class="col-md-12">
                <div class="card card-success">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            Summary Electricity
                        </h3>
                        <div class="card-tools">
                            <a href="/eums/modules/summary-electricity/index.php?date=<?php echo $reportDate; ?>" class="btn btn-tool">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentActivities['summary'])): ?>
                            <p class="text-center text-muted">ไม่มีข้อมูล</p>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-md-3 col-6">
                                    <div class="info-box bg-success">
                                        <span class="info-box-icon"><i class="fas fa-bolt"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">หน่วยไฟฟ้า</span>
                                            <span class="info-box-number"><?php echo number_format($recentActivities['summary'][0]['ee_unit'], 2); ?> kWh</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="info-box bg-warning">
                                        <span class="info-box-icon"><i class="fas fa-coins"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">ค่าไฟต่อหน่วย</span>
                                            <span class="info-box-number"><?php echo number_format($recentActivities['summary'][0]['cost_per_unit'], 4); ?> บาท</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="info-box bg-danger">
                                        <span class="info-box-icon"><i class="fas fa-money-bill"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">ค่าไฟฟ้ารวม</span>
                                            <span class="info-box-number"><?php echo number_format($recentActivities['summary'][0]['total_cost'], 2); ?> บาท</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="info-box bg-info">
                                        <span class="info-box-icon"><i class="fas fa-chart-pie"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">PE</span>
                                            <span class="info-box-number"><?php echo $recentActivities['summary'][0]['pe'] ? number_format($recentActivities['summary'][0]['pe'], 4) : '-'; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <small>ผู้บันทึก: <?php echo htmlspecialchars($recentActivities['summary'][0]['recorded_by']); ?></small>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Buttons -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <button type="button" class="btn btn-success" onclick="exportReport('excel')">
                            <i class="fas fa-file-excel"></i> ส่งออก Excel
                        </button>
                        <button type="button" class="btn btn-danger" onclick="exportReport('pdf')">
                            <i class="fas fa-file-pdf"></i> ส่งออก PDF
                        </button>
                        <button type="button" class="btn btn-info" onclick="window.print()">
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
    $('#datePicker').datetimepicker({
        format: 'DD/MM/YYYY',
        locale: 'th',
        useCurrent: true
    });
});

function exportReport(format) {
    const date = $('#reportDate').val();
    window.location.href = 'export_report.php?type=daily&format=' + format + '&date=' + encodeURIComponent(date);
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
    .card {
        border: 1px solid #dee2e6 !important;
        break-inside: avoid;
    }
}
</style>

<?php
// Include footer
require_once __DIR__ . '/../includes/footer.php';
?>