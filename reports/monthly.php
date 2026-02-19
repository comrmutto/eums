<?php
/**
 * Monthly Report - All Modules
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
$pageTitle = 'รายงานประจำเดือน';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'รายงาน', 'link' => '#'],
    ['title' => 'ประจำเดือน', 'link' => null]
];

// Include header
require_once __DIR__ . '/../includes/header.php';

// Load required files
require_once __DIR__ . '/../includes/functions.php';

// Get database connection
$db = getDB();

// Get parameters
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$thaiYear = $year + 543;

// Get data from all modules for the month
$startDate = "$year-$month-01";
$endDate = date('Y-m-t', strtotime($startDate));

// Air Compressor monthly summary
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT r.record_date) as days_with_data,
        COUNT(r.id) as total_records,
        COUNT(DISTINCT r.machine_id) as active_machines,
        SUM(r.actual_value) as total_usage,
        AVG(r.actual_value) as avg_daily_usage,
        MAX(r.actual_value) as max_daily_usage,
        MIN(r.actual_value) as min_daily_usage,
        SUM(CASE 
            WHEN (s.min_value IS NOT NULL AND (r.actual_value < s.min_value OR r.actual_value > s.max_value))
                 OR (s.min_value IS NULL AND ABS(r.actual_value - s.standard_value) > s.standard_value * 0.1)
            THEN 1 ELSE 0 END) as ng_count,
        COUNT(r.id) - SUM(CASE 
            WHEN (s.min_value IS NOT NULL AND (r.actual_value < s.min_value OR r.actual_value > s.max_value))
                 OR (s.min_value IS NULL AND ABS(r.actual_value - s.standard_value) > s.standard_value * 0.1)
            THEN 1 ELSE 0 END) as ok_count
    FROM air_daily_records r
    JOIN air_inspection_standards s ON r.inspection_item_id = s.id
    WHERE r.record_date BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$airData = $stmt->fetch();

// Energy & Water monthly summary
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT r.record_date) as days_with_data,
        COUNT(r.id) as total_records,
        COUNT(DISTINCT r.meter_id) as active_meters,
        SUM(r.usage_amount) as total_usage,
        AVG(r.usage_amount) as avg_daily_usage,
        MAX(r.usage_amount) as max_daily_usage,
        SUM(CASE WHEN m.meter_type = 'electricity' THEN r.usage_amount ELSE 0 END) as electricity_usage,
        SUM(CASE WHEN m.meter_type = 'water' THEN r.usage_amount ELSE 0 END) as water_usage,
        AVG(CASE WHEN m.meter_type = 'electricity' THEN r.usage_amount ELSE NULL END) as avg_electricity,
        AVG(CASE WHEN m.meter_type = 'water' THEN r.usage_amount ELSE NULL END) as avg_water
    FROM meter_daily_readings r
    JOIN mc_mdb_water m ON r.meter_id = m.id
    WHERE r.record_date BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$energyData = $stmt->fetch();

// LPG monthly summary
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT r.record_date) as days_with_data,
        COUNT(r.id) as total_records,
        COUNT(DISTINCT r.item_id) as active_items,
        SUM(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE 0 END) as total_usage,
        AVG(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE NULL END) as avg_daily_usage,
        SUM(CASE WHEN i.item_type = 'enum' AND r.enum_value = 'OK' THEN 1 ELSE 0 END) as ok_count,
        SUM(CASE WHEN i.item_type = 'enum' AND r.enum_value = 'NG' THEN 1 ELSE 0 END) as ng_count
    FROM lpg_daily_records r
    JOIN lpg_inspection_items i ON r.item_id = i.id
    WHERE r.record_date BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$lpgData = $stmt->fetch();

// Boiler monthly summary
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT record_date) as days_with_data,
        COUNT(id) as total_records,
        COUNT(DISTINCT machine_id) as active_machines,
        SUM(fuel_consumption) as total_fuel,
        SUM(operating_hours) as total_hours,
        AVG(steam_pressure) as avg_pressure,
        AVG(steam_temperature) as avg_temperature,
        MAX(steam_pressure) as max_pressure,
        MAX(steam_temperature) as max_temperature
    FROM boiler_daily_records
    WHERE record_date BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$boilerData = $stmt->fetch();

// Summary Electricity monthly summary
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as days_with_data,
        SUM(ee_unit) as total_ee,
        SUM(total_cost) as total_cost,
        AVG(cost_per_unit) as avg_cost_per_unit,
        MAX(ee_unit) as max_daily_ee,
        MIN(ee_unit) as min_daily_ee,
        AVG(ee_unit) as avg_daily_ee
    FROM electricity_summary
    WHERE record_date BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$summaryData = $stmt->fetch();

// Get daily breakdown for charts
$dailyData = [];

// Air daily
$stmt = $db->prepare("
    SELECT 
        DAY(record_date) as day,
        SUM(actual_value) as total
    FROM air_daily_records
    WHERE record_date BETWEEN ? AND ?
    GROUP BY DAY(record_date)
    ORDER BY day
");
$stmt->execute([$startDate, $endDate]);
$airDaily = $stmt->fetchAll();

// Energy daily
$stmt = $db->prepare("
    SELECT 
        DAY(record_date) as day,
        SUM(usage_amount) as total
    FROM meter_daily_readings
    WHERE record_date BETWEEN ? AND ?
    GROUP BY DAY(record_date)
    ORDER BY day
");
$stmt->execute([$startDate, $endDate]);
$energyDaily = $stmt->fetchAll();

// LPG daily
$stmt = $db->prepare("
    SELECT 
        DAY(record_date) as day,
        SUM(number_value) as total
    FROM lpg_daily_records
    WHERE record_date BETWEEN ? AND ? AND number_value IS NOT NULL
    GROUP BY DAY(record_date)
    ORDER BY day
");
$stmt->execute([$startDate, $endDate]);
$lpgDaily = $stmt->fetchAll();

// Boiler daily fuel
$stmt = $db->prepare("
    SELECT 
        DAY(record_date) as day,
        SUM(fuel_consumption) as total
    FROM boiler_daily_records
    WHERE record_date BETWEEN ? AND ?
    GROUP BY DAY(record_date)
    ORDER BY day
");
$stmt->execute([$startDate, $endDate]);
$boilerDaily = $stmt->fetchAll();

// Summary daily
$stmt = $db->prepare("
    SELECT 
        DAY(record_date) as day,
        ee_unit as total
    FROM electricity_summary
    WHERE record_date BETWEEN ? AND ?
    ORDER BY day
");
$stmt->execute([$startDate, $endDate]);
$summaryDaily = $stmt->fetchAll();

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$dailyLabels = range(1, $daysInMonth);

// Prepare chart data
$airValues = array_fill(1, $daysInMonth, 0);
foreach ($airDaily as $d) {
    $airValues[$d['day']] = $d['total'];
}

$energyValues = array_fill(1, $daysInMonth, 0);
foreach ($energyDaily as $d) {
    $energyValues[$d['day']] = $d['total'];
}

$lpgValues = array_fill(1, $daysInMonth, 0);
foreach ($lpgDaily as $d) {
    $lpgValues[$d['day']] = $d['total'];
}

$boilerValues = array_fill(1, $daysInMonth, 0);
foreach ($boilerDaily as $d) {
    $boilerValues[$d['day']] = $d['total'];
}

$summaryValues = array_fill(1, $daysInMonth, 0);
foreach ($summaryDaily as $d) {
    $summaryValues[$d['day']] = $d['total'];
}
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Month Selector Card -->
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-alt"></i>
                    เลือกเดือน
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
                        <label class="mr-2">เดือน:</label>
                        <select name="month" class="form-control">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                                <?php echo getThaiMonth($m); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group mr-2">
                        <label class="mr-2">ปี:</label>
                        <select name="year" class="form-control">
                            <?php for ($y = date('Y') - 3; $y <= date('Y') + 1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                <?php echo $y + 543; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> แสดงรายงาน
                    </button>
                    <button type="button" class="btn btn-success ml-2" onclick="window.location.href='?month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>'">
                        <i class="fas fa-calendar-check"></i> เดือนนี้
                    </button>
                </form>
            </div>
        </div>

        <!-- Month Summary -->
        <div class="row">
            <div class="col-12">
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-pie"></i>
                            สรุปภาพรวมเดือน <?php echo getThaiMonth($month) . ' ' . $thaiYear; ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-6">
                                <div class="info-box bg-info">
                                    <span class="info-box-icon"><i class="fas fa-calendar"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">จำนวนวัน</span>
                                        <span class="info-box-number"><?php echo $daysInMonth; ?> วัน</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="info-box bg-success">
                                    <span class="info-box-icon"><i class="fas fa-check-circle"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">โมดูลที่มีข้อมูล</span>
                                        <span class="info-box-number">
                                            <?php 
                                            $activeModules = 0;
                                            if ($airData['total_records'] > 0) $activeModules++;
                                            if ($energyData['total_records'] > 0) $activeModules++;
                                            if ($lpgData['total_records'] > 0) $activeModules++;
                                            if ($boilerData['total_records'] > 0) $activeModules++;
                                            if ($summaryData['days_with_data'] > 0) $activeModules++;
                                            echo $activeModules; ?>/5
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="info-box bg-warning">
                                    <span class="info-box-icon"><i class="fas fa-clipboard-list"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">บันทึกทั้งหมด</span>
                                        <span class="info-box-number">
                                            <?php 
                                            $totalRecords = ($airData['total_records'] ?? 0) + 
                                                            ($energyData['total_records'] ?? 0) + 
                                                            ($lpgData['total_records'] ?? 0) + 
                                                            ($boilerData['total_records'] ?? 0) + 
                                                            ($summaryData['days_with_data'] ?? 0);
                                            echo number_format($totalRecords);
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="info-box bg-danger">
                                    <span class="info-box-icon"><i class="fas fa-exclamation-triangle"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">รายการ NG</span>
                                        <span class="info-box-number">
                                            <?php 
                                            $totalNg = ($airData['ng_count'] ?? 0) + ($lpgData['ng_count'] ?? 0);
                                            echo $totalNg;
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Chart -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-line"></i>
                    ปริมาณการใช้งานรายวัน
                </h3>
                <div class="card-tools">
                    <select id="chartDataset" class="form-control form-control-sm" style="width: 200px;">
                        <option value="all">ทั้งหมด</option>
                        <option value="air">Air Compressor</option>
                        <option value="energy">Energy & Water</option>
                        <option value="lpg">LPG</option>
                        <option value="boiler">Boiler</option>
                        <option value="summary">Summary Electricity</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="dailyChart" style="min-height: 400px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Module Summaries -->
        <div class="row">
            <!-- Air Compressor Summary -->
            <div class="col-md-6">
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-compress"></i>
                            Air Compressor
                        </h3>
                        <div class="card-tools">
                            <a href="/eums/modules/air-compressor/reports.php?report_type=monthly&month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn btn-tool">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($airData['total_records'] > 0): ?>
                        <div class="row">
                            <div class="col-sm-6">
                                <table class="table table-sm">
                                    <tr>
                                        <th>วันที่บันทึก:</th>
                                        <td class="text-right"><?php echo $airData['days_with_data']; ?> วัน</td>
                                    </tr>
                                    <tr>
                                        <th>จำนวนบันทึก:</th>
                                        <td class="text-right"><?php echo number_format($airData['total_records']); ?> รายการ</td>
                                    </tr>
                                    <tr>
                                        <th>เครื่องที่ใช้งาน:</th>
                                        <td class="text-right"><?php echo $airData['active_machines']; ?> เครื่อง</td>
                                    </tr>
                                    <tr>
                                        <th>ปริมาณรวม:</th>
                                        <td class="text-right"><?php echo number_format($airData['total_usage'], 2); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-sm-6">
                                <table class="table table-sm">
                                    <tr>
                                        <th>ค่าเฉลี่ย/วัน:</th>
                                        <td class="text-right"><?php echo number_format($airData['avg_daily_usage'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <th>สูงสุด:</th>
                                        <td class="text-right"><?php echo number_format($airData['max_daily_usage'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <th>ต่ำสุด:</th>
                                        <td class="text-right"><?php echo number_format($airData['min_daily_usage'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <th>OK/NG:</th>
                                        <td class="text-right">
                                            <span class="badge badge-success"><?php echo $airData['ok_count']; ?></span>
                                            <span class="badge badge-danger"><?php echo $airData['ng_count']; ?></span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <?php else: ?>
                        <p class="text-center text-muted">ไม่มีข้อมูล</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Energy & Water Summary -->
            <div class="col-md-6">
                <div class="card card-warning">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bolt"></i>
                            Energy & Water
                        </h3>
                        <div class="card-tools">
                            <a href="/eums/modules/energy-water/reports.php?report_type=monthly&month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn btn-tool">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($energyData['total_records'] > 0): ?>
                        <div class="row">
                            <div class="col-sm-6">
                                <table class="table table-sm">
                                    <tr>
                                        <th>วันที่บันทึก:</th>
                                        <td class="text-right"><?php echo $energyData['days_with_data']; ?> วัน</td>
                                    </tr>
                                    <tr>
                                        <th>จำนวนบันทึก:</th>
                                        <td class="text-right"><?php echo number_format($energyData['total_records']); ?> รายการ</td>
                                    </tr>
                                    <tr>
                                        <th>มิเตอร์ที่ใช้งาน:</th>
                                        <td class="text-right"><?php echo $energyData['active_meters']; ?> มิเตอร์</td>
                                    </tr>
                                    <tr>
                                        <th>ไฟฟ้ารวม:</th>
                                        <td class="text-right"><?php echo number_format($energyData['electricity_usage'], 2); ?> kWh</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-sm-6">
                                <table class="table table-sm">
                                    <tr>
                                        <th>น้ำรวม:</th>
                                        <td class="text-right"><?php echo number_format($energyData['water_usage'], 2); ?> m³</td>
                                    </tr>
                                    <tr>
                                        <th>ไฟฟ้าเฉลี่ย/วัน:</th>
                                        <td class="text-right"><?php echo number_format($energyData['avg_electricity'], 2); ?> kWh</td>
                                    </tr>
                                    <tr>
                                        <th>น้ำเฉลี่ย/วัน:</th>
                                        <td class="text-right"><?php echo number_format($energyData['avg_water'], 2); ?> m³</td>
                                    </tr>
                                    <tr>
                                        <th>สูงสุดรายวัน:</th>
                                        <td class="text-right"><?php echo number_format($energyData['max_daily_usage'], 2); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <?php else: ?>
                        <p class="text-center text-muted">ไม่มีข้อมูล</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- LPG Summary -->
            <div class="col-md-6">
                <div class="card card-danger">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-fire"></i>
                            LPG
                        </h3>
                        <div class="card-tools">
                            <a href="/eums/modules/lpg/reports.php?report_type=monthly&month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn btn-tool">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($lpgData['total_records'] > 0): ?>
                        <div class="row">
                            <div class="col-sm-6">
                                <table class="table table-sm">
                                    <tr>
                                        <th>วันที่บันทึก:</th>
                                        <td class="text-right"><?php echo $lpgData['days_with_data']; ?> วัน</td>
                                    </tr>
                                    <tr>
                                        <th>จำนวนบันทึก:</th>
                                        <td class="text-right"><?php echo number_format($lpgData['total_records']); ?> รายการ</td>
                                    </tr>
                                    <tr>
                                        <th>รายการที่ใช้งาน:</th>
                                        <td class="text-right"><?php echo $lpgData['active_items']; ?> รายการ</td>
                                    </tr>
                                    <tr>
                                        <th>ปริมาณรวม:</th>
                                        <td class="text-right"><?php echo number_format($lpgData['total_usage'], 2); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-sm-6">
                                <table class="table table-sm">
                                    <tr>
                                        <th>ค่าเฉลี่ย/วัน:</th>
                                        <td class="text-right"><?php echo number_format($lpgData['avg_daily_usage'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <th>OK:</th>
                                        <td class="text-right"><span class="badge badge-success"><?php echo $lpgData['ok_count']; ?></span></td>
                                    </tr>
                                    <tr>
                                        <th>NG:</th>
                                        <td class="text-right"><span class="badge badge-danger"><?php echo $lpgData['ng_count']; ?></span></td>
                                    </tr>
                                    <tr>
                                        <th>อัตราผ่าน:</th>
                                        <td class="text-right">
                                            <?php 
                                            $total = $lpgData['ok_count'] + $lpgData['ng_count'];
                                            $rate = $total > 0 ? ($lpgData['ok_count'] / $total) * 100 : 0;
                                            echo number_format($rate, 1); ?>%
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <?php else: ?>
                        <p class="text-center text-muted">ไม่มีข้อมูล</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Boiler Summary -->
            <div class="col-md-6">
                <div class="card card-secondary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-industry"></i>
                            Boiler
                        </h3>
                        <div class="card-tools">
                            <a href="/eums/modules/boiler/reports.php?report_type=monthly&month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn btn-tool">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($boilerData['total_records'] > 0): ?>
                        <div class="row">
                            <div class="col-sm-6">
                                <table class="table table-sm">
                                    <tr>
                                        <th>วันที่บันทึก:</th>
                                        <td class="text-right"><?php echo $boilerData['days_with_data']; ?> วัน</td>
                                    </tr>
                                    <tr>
                                        <th>จำนวนบันทึก:</th>
                                        <td class="text-right"><?php echo number_format($boilerData['total_records']); ?> รายการ</td>
                                    </tr>
                                    <tr>
                                        <th>เครื่องที่ใช้งาน:</th>
                                        <td class="text-right"><?php echo $boilerData['active_machines']; ?> เครื่อง</td>
                                    </tr>
                                    <tr>
                                        <th>เชื้อเพลิงรวม:</th>
                                        <td class="text-right"><?php echo number_format($boilerData['total_fuel'], 2); ?> L</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-sm-6">
                                <table class="table table-sm">
                                    <tr>
                                        <th>ชั่วโมงรวม:</th>
                                        <td class="text-right"><?php echo number_format($boilerData['total_hours'], 1); ?> ชม.</td>
                                    </tr>
                                    <tr>
                                        <th>แรงดันเฉลี่ย:</th>
                                        <td class="text-right"><?php echo number_format($boilerData['avg_pressure'], 2); ?> bar</td>
                                    </tr>
                                    <tr>
                                        <th>อุณหภูมิเฉลี่ย:</th>
                                        <td class="text-right"><?php echo number_format($boilerData['avg_temperature'], 1); ?> °C</td>
                                    </tr>
                                    <tr>
                                        <th>แรงดันสูงสุด:</th>
                                        <td class="text-right"><?php echo number_format($boilerData['max_pressure'], 2); ?> bar</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <?php else: ?>
                        <p class="text-center text-muted">ไม่มีข้อมูล</p>
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
                            <a href="/eums/modules/summary-electricity/reports.php?report_type=monthly&month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn btn-tool">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($summaryData['days_with_data'] > 0): ?>
                        <div class="row">
                            <div class="col-md-3 col-6">
                                <div class="info-box bg-success">
                                    <span class="info-box-icon"><i class="fas fa-bolt"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">หน่วยไฟฟ้ารวม</span>
                                        <span class="info-box-number"><?php echo number_format($summaryData['total_ee'], 2); ?> kWh</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="info-box bg-warning">
                                    <span class="info-box-icon"><i class="fas fa-coins"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">ค่าไฟฟ้ารวม</span>
                                        <span class="info-box-number"><?php echo number_format($summaryData['total_cost'], 2); ?> บาท</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="info-box bg-info">
                                    <span class="info-box-icon"><i class="fas fa-calculator"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">ค่าไฟเฉลี่ย/หน่วย</span>
                                        <span class="info-box-number"><?php echo number_format($summaryData['avg_cost_per_unit'], 4); ?> บาท</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="info-box bg-danger">
                                    <span class="info-box-icon"><i class="fas fa-chart-line"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">เฉลี่ยรายวัน</span>
                                        <span class="info-box-number"><?php echo number_format($summaryData['avg_daily_ee'], 2); ?> kWh</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <small>จำนวนวันที่มีข้อมูล: <?php echo $summaryData['days_with_data']; ?> วัน</small>
                            </div>
                            <div class="col-md-4">
                                <small>สูงสุดรายวัน: <?php echo number_format($summaryData['max_daily_ee'], 2); ?> kWh</small>
                            </div>
                            <div class="col-md-4">
                                <small>ต่ำสุดรายวัน: <?php echo number_format($summaryData['min_daily_ee'], 2); ?> kWh</small>
                            </div>
                        </div>
                        <?php else: ?>
                        <p class="text-center text-muted">ไม่มีข้อมูล</p>
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
let dailyChart = null;

$(document).ready(function() {
    renderDailyChart('all');
    
    $('#chartDataset').on('change', function() {
        renderDailyChart($(this).val());
    });
});

function renderDailyChart(dataset) {
    const ctx = document.getElementById('dailyChart').getContext('2d');
    
    if (dailyChart) {
        dailyChart.destroy();
    }
    
    const labels = <?php echo json_encode($dailyLabels); ?>;
    
    let datasets = [];
    
    if (dataset === 'all' || dataset === 'air') {
        datasets.push({
            label: 'Air Compressor',
            data: <?php echo json_encode(array_values($airValues)); ?>,
            borderColor: '#17a2b8',
            backgroundColor: 'rgba(23, 162, 184, 0.1)',
            borderWidth: 2,
            tension: 0.4,
            fill: false
        });
    }
    
    if (dataset === 'all' || dataset === 'energy') {
        datasets.push({
            label: 'Energy & Water',
            data: <?php echo json_encode(array_values($energyValues)); ?>,
            borderColor: '#ffc107',
            backgroundColor: 'rgba(255, 193, 7, 0.1)',
            borderWidth: 2,
            tension: 0.4,
            fill: false
        });
    }
    
    if (dataset === 'all' || dataset === 'lpg') {
        datasets.push({
            label: 'LPG',
            data: <?php echo json_encode(array_values($lpgValues)); ?>,
            borderColor: '#dc3545',
            backgroundColor: 'rgba(220, 53, 69, 0.1)',
            borderWidth: 2,
            tension: 0.4,
            fill: false
        });
    }
    
    if (dataset === 'all' || dataset === 'boiler') {
        datasets.push({
            label: 'Boiler',
            data: <?php echo json_encode(array_values($boilerValues)); ?>,
            borderColor: '#6c757d',
            backgroundColor: 'rgba(108, 117, 125, 0.1)',
            borderWidth: 2,
            tension: 0.4,
            fill: false
        });
    }
    
    if (dataset === 'all' || dataset === 'summary') {
        datasets.push({
            label: 'Summary Electricity',
            data: <?php echo json_encode(array_values($summaryValues)); ?>,
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            borderWidth: 2,
            tension: 0.4,
            fill: false
        });
    }
    
    dailyChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'ปริมาณ'
                    }
                }
            }
        }
    });
}

function exportReport(format) {
    const month = $('select[name=month]').val();
    const year = $('select[name=year]').val();
    window.location.href = 'export_report.php?type=monthly&format=' + format + '&month=' + month + '&year=' + year;
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