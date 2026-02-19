<?php
/**
 * Yearly Report - All Modules
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
$pageTitle = 'รายงานประจำปี';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'รายงาน', 'link' => '#'],
    ['title' => 'ประจำปี', 'link' => null]
];

// Include header
require_once __DIR__ . '/../includes/header.php';

// Load required files
require_once __DIR__ . '/../includes/functions.php';

// Get database connection
$db = getDB();

// Get parameters
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$thaiYear = $year + 543;

// Get data from all modules for the year
$startDate = "$year-01-01";
$endDate = "$year-12-31";

// Air Compressor yearly summary
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT MONTH(record_date)) as months_with_data,
        COUNT(DISTINCT r.record_date) as days_with_data,
        COUNT(r.id) as total_records,
        SUM(r.actual_value) as total_usage,
        AVG(r.actual_value) as avg_daily_usage,
        MAX(r.actual_value) as max_daily_usage,
        MIN(r.actual_value) as min_daily_usage
    FROM air_daily_records r
    WHERE r.record_date BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$airData = $stmt->fetch();

// Energy & Water yearly summary
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT MONTH(r.record_date)) as months_with_data,
        COUNT(DISTINCT r.record_date) as days_with_data,
        COUNT(r.id) as total_records,
        SUM(r.usage_amount) as total_usage,
        SUM(CASE WHEN m.meter_type = 'electricity' THEN r.usage_amount ELSE 0 END) as electricity_usage,
        SUM(CASE WHEN m.meter_type = 'water' THEN r.usage_amount ELSE 0 END) as water_usage
    FROM meter_daily_readings r
    JOIN mc_mdb_water m ON r.meter_id = m.id
    WHERE r.record_date BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$energyData = $stmt->fetch();

// LPG yearly summary
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT MONTH(r.record_date)) as months_with_data,
        COUNT(DISTINCT r.record_date) as days_with_data,
        COUNT(r.id) as total_records,
        SUM(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE 0 END) as total_usage,
        SUM(CASE WHEN i.item_type = 'enum' AND r.enum_value = 'OK' THEN 1 ELSE 0 END) as ok_count,
        SUM(CASE WHEN i.item_type = 'enum' AND r.enum_value = 'NG' THEN 1 ELSE 0 END) as ng_count
    FROM lpg_daily_records r
    JOIN lpg_inspection_items i ON r.item_id = i.id
    WHERE r.record_date BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$lpgData = $stmt->fetch();

// Boiler yearly summary
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT MONTH(record_date)) as months_with_data,
        COUNT(DISTINCT record_date) as days_with_data,
        COUNT(id) as total_records,
        SUM(fuel_consumption) as total_fuel,
        SUM(operating_hours) as total_hours,
        AVG(steam_pressure) as avg_pressure,
        AVG(steam_temperature) as avg_temperature
    FROM boiler_daily_records
    WHERE record_date BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$boilerData = $stmt->fetch();

// Summary Electricity yearly summary
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT MONTH(record_date)) as months_with_data,
        COUNT(*) as days_with_data,
        SUM(ee_unit) as total_ee,
        SUM(total_cost) as total_cost,
        AVG(cost_per_unit) as avg_cost_per_unit,
        MAX(ee_unit) as max_daily_ee,
        MIN(ee_unit) as min_daily_ee
    FROM electricity_summary
    WHERE record_date BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$summaryData = $stmt->fetch();

// Get monthly breakdown for charts
$monthlyLabels = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 
                  'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

// Air monthly
$stmt = $db->prepare("
    SELECT 
        MONTH(record_date) as month,
        SUM(actual_value) as total
    FROM air_daily_records
    WHERE YEAR(record_date) = ?
    GROUP BY MONTH(record_date)
    ORDER BY month
");
$stmt->execute([$year]);
$airMonthly = $stmt->fetchAll();
$airMonthlyValues = array_fill(1, 12, 0);
foreach ($airMonthly as $m) {
    $airMonthlyValues[$m['month']] = $m['total'];
}

// Energy monthly
$stmt = $db->prepare("
    SELECT 
        MONTH(record_date) as month,
        SUM(usage_amount) as total
    FROM meter_daily_readings
    WHERE YEAR(record_date) = ?
    GROUP BY MONTH(record_date)
    ORDER BY month
");
$stmt->execute([$year]);
$energyMonthly = $stmt->fetchAll();
$energyMonthlyValues = array_fill(1, 12, 0);
foreach ($energyMonthly as $m) {
    $energyMonthlyValues[$m['month']] = $m['total'];
}

// LPG monthly
$stmt = $db->prepare("
    SELECT 
        MONTH(record_date) as month,
        SUM(number_value) as total
    FROM lpg_daily_records
    WHERE YEAR(record_date) = ? AND number_value IS NOT NULL
    GROUP BY MONTH(record_date)
    ORDER BY month
");
$stmt->execute([$year]);
$lpgMonthly = $stmt->fetchAll();
$lpgMonthlyValues = array_fill(1, 12, 0);
foreach ($lpgMonthly as $m) {
    $lpgMonthlyValues[$m['month']] = $m['total'];
}

// Boiler monthly fuel
$stmt = $db->prepare("
    SELECT 
        MONTH(record_date) as month,
        SUM(fuel_consumption) as total
    FROM boiler_daily_records
    WHERE YEAR(record_date) = ?
    GROUP BY MONTH(record_date)
    ORDER BY month
");
$stmt->execute([$year]);
$boilerMonthly = $stmt->fetchAll();
$boilerMonthlyValues = array_fill(1, 12, 0);
foreach ($boilerMonthly as $m) {
    $boilerMonthlyValues[$m['month']] = $m['total'];
}

// Summary monthly
$stmt = $db->prepare("
    SELECT 
        MONTH(record_date) as month,
        SUM(ee_unit) as total
    FROM electricity_summary
    WHERE YEAR(record_date) = ?
    GROUP BY MONTH(record_date)
    ORDER BY month
");
$stmt->execute([$year]);
$summaryMonthly = $stmt->fetchAll();
$summaryMonthlyValues = array_fill(1, 12, 0);
foreach ($summaryMonthly as $m) {
    $summaryMonthlyValues[$m['month']] = $m['total'];
}

// Get previous year data for comparison
$prevYear = $year - 1;
$prevYearStart = "$prevYear-01-01";
$prevYearEnd = "$prevYear-12-31";

// Previous year totals
$stmt = $db->prepare("SELECT SUM(actual_value) as total FROM air_daily_records WHERE record_date BETWEEN ? AND ?");
$stmt->execute([$prevYearStart, $prevYearEnd]);
$prevAir = $stmt->fetch()['total'] ?? 0;

$stmt = $db->prepare("SELECT SUM(usage_amount) as total FROM meter_daily_readings WHERE record_date BETWEEN ? AND ?");
$stmt->execute([$prevYearStart, $prevYearEnd]);
$prevEnergy = $stmt->fetch()['total'] ?? 0;

$stmt = $db->prepare("SELECT SUM(number_value) as total FROM lpg_daily_records WHERE record_date BETWEEN ? AND ? AND number_value IS NOT NULL");
$stmt->execute([$prevYearStart, $prevYearEnd]);
$prevLpg = $stmt->fetch()['total'] ?? 0;

$stmt = $db->prepare("SELECT SUM(fuel_consumption) as total FROM boiler_daily_records WHERE record_date BETWEEN ? AND ?");
$stmt->execute([$prevYearStart, $prevYearEnd]);
$prevBoiler = $stmt->fetch()['total'] ?? 0;

$stmt = $db->prepare("SELECT SUM(ee_unit) as total FROM electricity_summary WHERE record_date BETWEEN ? AND ?");
$stmt->execute([$prevYearStart, $prevYearEnd]);
$prevSummary = $stmt->fetch()['total'] ?? 0;
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Year Selector Card -->
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar"></i>
                    เลือกปี
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
                        <label class="mr-2">ปี:</label>
                        <select name="year" class="form-control">
                            <?php for ($y = date('Y') - 5; $y <= date('Y') + 1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                <?php echo $y + 543; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> แสดงรายงาน
                    </button>
                    <button type="button" class="btn btn-success ml-2" onclick="window.location.href='?year=<?php echo date('Y'); ?>'">
                        <i class="fas fa-calendar-check"></i> ปีนี้
                    </button>
                </form>
            </div>
        </div>

        <!-- Year Summary -->
        <div class="row">
            <div class="col-12">
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-pie"></i>
                            สรุปภาพรวมปี <?php echo $thaiYear; ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-6">
                                <div class="info-box bg-info">
                                    <span class="info-box-icon"><i class="fas fa-calendar"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">จำนวนวัน</span>
                                        <span class="info-box-number">365 วัน</span>
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
                                        <span class="info-box-text">อัตราการบันทึก</span>
                                        <span class="info-box-number">
                                            <?php 
                                            $totalPossible = 365 * 5; // 5 modules × 365 days
                                            $rate = $totalPossible > 0 ? ($totalRecords / $totalPossible) * 100 : 0;
                                            echo number_format($rate, 1); ?>%
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Chart -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-bar"></i>
                    ปริมาณการใช้งานรายเดือน ปี <?php echo $thaiYear; ?>
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
                    <canvas id="monthlyChart" style="min-height: 400px;"></canvas>
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
                    </div>
                    <div class="card-body">
                        <?php if ($airData['total_records'] > 0): ?>
                        <div class="row">
                            <div class="col-sm-6">
                                <table class="table table-sm">
                                    <tr>
                                        <th>เดือนที่มีข้อมูล:</th>
                                        <td class="text-right"><?php echo $airData['months_with_data']; ?> เดือน</td>
                                    </tr>
                                    <tr>
                                        <th>จำนวนวัน:</th>
                                        <td class="text-right"><?php echo number_format($airData['days_with_data']); ?> วัน</td>
                                    </tr>
                                    <tr>
                                        <th>จำนวนบันทึก:</th>
                                        <td class="text-right"><?php echo number_format($airData['total_records']); ?> รายการ</td>
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
                                        <th>สูงสุดรายวัน:</th>
                                        <td class="text-right"><?php echo number_format($airData['max_daily_usage'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <th>ต่ำสุดรายวัน:</th>
                                        <td class="text-right"><?php echo number_format($airData['min_daily_usage'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <th>เทียบกับปีก่อน:</th>
                                        <td class="text-right">
                                            <?php 
                                            $change = $airData['total_usage'] - $prevAir;
                                            $changePercent = $prevAir > 0 ? ($change / $prevAir) * 100 : 0;
                                            ?>
                                            <span class="badge badge-<?php echo $change > 0 ? 'danger' : ($change < 0 ? 'success' : 'secondary'); ?>">
                                                <?php echo $change > 0 ? '+' : ''; ?><?php echo number_format($change, 2); ?> (<?php echo number_format($changePercent, 1); ?>%)
                                            </span>
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
                    </div>
                    <div class="card-body">
                        <?php if ($energyData['total_records'] > 0): ?>
                        <div class="row">
                            <div class="col-sm-6">
                                <table class="table table-sm">
                                    <tr>
                                        <th>เดือนที่มีข้อมูล:</th>
                                        <td class="text-right"><?php echo $energyData['months_with_data']; ?> เดือน</td>
                                    </tr>
                                    <tr>
                                        <th>จำนวนวัน:</th>
                                        <td class="text-right"><?php echo number_format($energyData['days_with_data']); ?> วัน</td>
                                    </tr>
                                    <tr>
                                        <th>จำนวนบันทึก:</th>
                                        <td class="text-right"><?php echo number_format($energyData['total_records']); ?> รายการ</td>
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
                                        <th>รวมทั้งสิ้น:</th>
                                        <td class="text-right"><?php echo number_format($energyData['total_usage'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <th>เทียบกับปีก่อน:</th>
                                        <td class="text-right">
                                            <?php 
                                            $change = $energyData['total_usage'] - $prevEnergy;
                                            $changePercent = $prevEnergy > 0 ? ($change / $prevEnergy) * 100 : 0;
                                            ?>
                                            <span class="badge badge-<?php echo $change > 0 ? 'danger' : ($change < 0 ? 'success' : 'secondary'); ?>">
                                                <?php echo $change > 0 ? '+' : ''; ?><?php echo number_format($change, 2); ?> (<?php echo number_format($changePercent, 1); ?>%)
                                            </span>
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

            <!-- LPG Summary -->
            <div class="col-md-6">
                <div class="card card-danger">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-fire"></i>
                            LPG
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if ($lpgData['total_records'] > 0): ?>
                        <div class="row">
                            <div class="col-sm-6">
                                <table class="table table-sm">
                                    <tr>
                                        <th>เดือนที่มีข้อมูล:</th>
                                        <td class="text-right"><?php echo $lpgData['months_with_data']; ?> เดือน</td>
                                    </tr>
                                    <tr>
                                        <th>จำนวนวัน:</th>
                                        <td class="text-right"><?php echo number_format($lpgData['days_with_data']); ?> วัน</td>
                                    </tr>
                                    <tr>
                                        <th>จำนวนบันทึก:</th>
                                        <td class="text-right"><?php echo number_format($lpgData['total_records']); ?> รายการ</td>
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
                                        <th>OK ทั้งหมด:</th>
                                        <td class="text-right"><?php echo number_format($lpgData['ok_count']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>NG ทั้งหมด:</th>
                                        <td class="text-right"><?php echo number_format($lpgData['ng_count']); ?></td>
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
                                    <tr>
                                        <th>เทียบกับปีก่อน:</th>
                                        <td class="text-right">
                                            <?php 
                                            $change = $lpgData['total_usage'] - $prevLpg;
                                            $changePercent = $prevLpg > 0 ? ($change / $prevLpg) * 100 : 0;
                                            ?>
                                            <span class="badge badge-<?php echo $change > 0 ? 'danger' : ($change < 0 ? 'success' : 'secondary'); ?>">
                                                <?php echo $change > 0 ? '+' : ''; ?><?php echo number_format($change, 2); ?> (<?php echo number_format($changePercent, 1); ?>%)
                                            </span>
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
                    </div>
                    <div class="card-body">
                        <?php if ($boilerData['total_records'] > 0): ?>
                        <div class="row">
                            <div class="col-sm-6">
                                <table class="table table-sm">
                                    <tr>
                                        <th>เดือนที่มีข้อมูล:</th>
                                        <td class="text-right"><?php echo $boilerData['months_with_data']; ?> เดือน</td>
                                    </tr>
                                    <tr>
                                        <th>จำนวนวัน:</th>
                                        <td class="text-right"><?php echo number_format($boilerData['days_with_data']); ?> วัน</td>
                                    </tr>
                                    <tr>
                                        <th>จำนวนบันทึก:</th>
                                        <td class="text-right"><?php echo number_format($boilerData['total_records']); ?> รายการ</td>
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
                                        <th>เทียบกับปีก่อน:</th>
                                        <td class="text-right">
                                            <?php 
                                            $change = $boilerData['total_fuel'] - $prevBoiler;
                                            $changePercent = $prevBoiler > 0 ? ($change / $prevBoiler) * 100 : 0;
                                            ?>
                                            <span class="badge badge-<?php echo $change > 0 ? 'danger' : ($change < 0 ? 'success' : 'secondary'); ?>">
                                                <?php echo $change > 0 ? '+' : ''; ?><?php echo number_format($change, 2); ?> (<?php echo number_format($changePercent, 1); ?>%)
                                            </span>
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

            <!-- Summary Electricity -->
            <div class="col-md-12">
                <div class="card card-success">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            Summary Electricity
                        </h3>
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
                                        <span class="info-box-text">เทียบกับปีก่อน</span>
                                        <span class="info-box-number">
                                            <?php 
                                            $change = $summaryData['total_ee'] - $prevSummary;
                                            $changePercent = $prevSummary > 0 ? ($change / $prevSummary) * 100 : 0;
                                            ?>
                                            <span class="badge badge-<?php echo $change > 0 ? 'danger' : ($change < 0 ? 'success' : 'secondary'); ?>">
                                                <?php echo $change > 0 ? '+' : ''; ?><?php echo number_format($changePercent, 1); ?>%
                                            </span>
                                        </span>
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
let monthlyChart = null;

$(document).ready(function() {
    renderMonthlyChart('all');
    
    $('#chartDataset').on('change', function() {
        renderMonthlyChart($(this).val());
    });
});

function renderMonthlyChart(dataset) {
    const ctx = document.getElementById('monthlyChart').getContext('2d');
    
    if (monthlyChart) {
        monthlyChart.destroy();
    }
    
    const labels = <?php echo json_encode($monthlyLabels); ?>;
    
    let datasets = [];
    
    if (dataset === 'all' || dataset === 'air') {
        datasets.push({
            label: 'Air Compressor',
            data: <?php echo json_encode(array_values($airMonthlyValues)); ?>,
            backgroundColor: '#17a2b8',
            borderColor: '#17a2b8',
            borderWidth: 1
        });
    }
    
    if (dataset === 'all' || dataset === 'energy') {
        datasets.push({
            label: 'Energy & Water',
            data: <?php echo json_encode(array_values($energyMonthlyValues)); ?>,
            backgroundColor: '#ffc107',
            borderColor: '#ffc107',
            borderWidth: 1
        });
    }
    
    if (dataset === 'all' || dataset === 'lpg') {
        datasets.push({
            label: 'LPG',
            data: <?php echo json_encode(array_values($lpgMonthlyValues)); ?>,
            backgroundColor: '#dc3545',
            borderColor: '#dc3545',
            borderWidth: 1
        });
    }
    
    if (dataset === 'all' || dataset === 'boiler') {
        datasets.push({
            label: 'Boiler',
            data: <?php echo json_encode(array_values($boilerMonthlyValues)); ?>,
            backgroundColor: '#6c757d',
            borderColor: '#6c757d',
            borderWidth: 1
        });
    }
    
    if (dataset === 'all' || dataset === 'summary') {
        datasets.push({
            label: 'Summary Electricity',
            data: <?php echo json_encode(array_values($summaryMonthlyValues)); ?>,
            backgroundColor: '#28a745',
            borderColor: '#28a745',
            borderWidth: 1
        });
    }
    
    monthlyChart = new Chart(ctx, {
        type: 'bar',
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
    const year = $('select[name=year]').val();
    window.location.href = 'export_report.php?type=yearly&format=' + format + '&year=' + year;
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