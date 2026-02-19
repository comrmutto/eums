<?php
/**
 * EUMS - Main Dashboard Page
 * Engineering Utility Monitoring System
 */

// Set page title
$pageTitle = 'แดชบอร์ดหลัก';

// Include header
require_once __DIR__ . '/includes/header.php';

// Load required files
require_once __DIR__ . '/includes/functions.php';

// Get database connection
$db = getDB();

// Get current date
$today = date('Y-m-d');
$currentMonth = date('m');
$currentYear = date('Y');
$thaiYear = $currentYear + 543;

// Get summary data from all modules
$modules = [
    'air' => ['name' => 'Air Compressor', 'icon' => 'compress', 'color' => 'info', 'table' => 'air_daily_records', 'machine_table' => 'mc_air'],
    'energy' => ['name' => 'Energy & Water', 'icon' => 'bolt', 'color' => 'success', 'table' => 'meter_daily_readings', 'machine_table' => 'mc_mdb_water'],
    'lpg' => ['name' => 'LPG', 'icon' => 'fire', 'color' => 'warning', 'table' => 'lpg_daily_records', 'machine_table' => 'lpg_inspection_items'],
    'boiler' => ['name' => 'Boiler', 'icon' => 'industry', 'color' => 'danger', 'table' => 'boiler_daily_records', 'machine_table' => 'mc_boiler'],
    'summary' => ['name' => 'Summary Electricity', 'icon' => 'chart-line', 'color' => 'secondary', 'table' => 'electricity_summary', 'machine_table' => null]
];

$moduleStats = [];

foreach ($modules as $key => $module) {
    // Get machine count
if ($module['machine_table']) {
    // lpg_inspection_items has no status column
    $hasStatus = !in_array($module['machine_table'], ['lpg_inspection_items']);
    $sql = "SELECT COUNT(*) as count FROM " . $module['machine_table'];
    if ($hasStatus) $sql .= " WHERE status = 1";
    $stmt = $db->query($sql);
    $machineCount = $stmt->fetch()['count'];
    } else {
        $machineCount = 0;
    }
    
    // Get today's records count
    if ($module['table']) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM " . $module['table'] . " WHERE record_date = ?");
        $stmt->execute([$today]);
        $todayCount = $stmt->fetch()['count'];
    } else {
        $todayCount = 0;
    }
    
    // Get month records count
    if ($module['table']) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM " . $module['table'] . " WHERE MONTH(record_date) = ? AND YEAR(record_date) = ?");
        $stmt->execute([$currentMonth, $currentYear]);
        $monthCount = $stmt->fetch()['count'];
    } else {
        $monthCount = 0;
    }
    
    // Get total usage/records
    $totalUsage = 0;
    if ($module['table']) {
        switch ($key) {
            case 'air':
                $stmt = $db->query("SELECT SUM(actual_value) as total FROM air_daily_records");
                $totalUsage = $stmt->fetch()['total'] ?? 0;
                break;
            case 'energy':
                $stmt = $db->query("SELECT SUM(usage_amount) as total FROM meter_daily_readings");
                $totalUsage = $stmt->fetch()['total'] ?? 0;
                break;
            case 'lpg':
                $stmt = $db->query("SELECT SUM(number_value) as total FROM lpg_daily_records WHERE number_value IS NOT NULL");
                $totalUsage = $stmt->fetch()['total'] ?? 0;
                break;
            case 'boiler':
                $stmt = $db->query("SELECT SUM(fuel_consumption) as total FROM boiler_daily_records");
                $totalUsage = $stmt->fetch()['total'] ?? 0;
                break;
            case 'summary':
                $stmt = $db->query("SELECT SUM(ee_unit) as total FROM electricity_summary");
                $totalUsage = $stmt->fetch()['total'] ?? 0;
                break;
        }
    }
    
    $moduleStats[$key] = [
        'machine_count' => $machineCount,
        'today_count' => $todayCount,
        'month_count' => $monthCount,
        'total_usage' => round($totalUsage, 2),
        'name' => $module['name'],
        'icon' => $module['icon'],
        'color' => $module['color']
    ];
}

// Get recent activities from all modules
$recentActivities = [];

// Air Compressor recent records
$stmt = $db->query("
    SELECT 
        'air' as module,
        r.record_date,
        r.actual_value as value,
        m.machine_name as machine,
        s.inspection_item as item,
        r.recorded_by,
        r.created_at
    FROM air_daily_records r
    JOIN mc_air m ON r.machine_id = m.id
    JOIN air_inspection_standards s ON r.inspection_item_id = s.id
    ORDER BY r.created_at DESC
    LIMIT 5
");
$recentActivities = array_merge($recentActivities, $stmt->fetchAll());

// Energy & Water recent records
$stmt = $db->query("
    SELECT 
        'energy' as module,
        r.record_date,
        r.usage_amount as value,
        m.meter_name as machine,
        CONCAT('ค่าเช้า: ', r.morning_reading, ' ค่าเย็น: ', r.evening_reading) as item,
        r.recorded_by,
        r.created_at
    FROM meter_daily_readings r
    JOIN mc_mdb_water m ON r.meter_id = m.id
    ORDER BY r.created_at DESC
    LIMIT 5
");
$recentActivities = array_merge($recentActivities, $stmt->fetchAll());

// LPG recent records
$stmt = $db->query("
    SELECT 
        'lpg' as module,
        r.record_date,
        COALESCE(r.number_value, r.enum_value) as value,
        'LPG' as machine,
        i.item_name as item,
        r.recorded_by,
        r.created_at
    FROM lpg_daily_records r
    JOIN lpg_inspection_items i ON r.item_id = i.id
    ORDER BY r.created_at DESC
    LIMIT 5
");
$recentActivities = array_merge($recentActivities, $stmt->fetchAll());

// Boiler recent records
$stmt = $db->query("
    SELECT 
        'boiler' as module,
        r.record_date,
        r.fuel_consumption as value,
        m.machine_name as machine,
        CONCAT('แรงดัน: ', r.steam_pressure, ' bar') as item,
        r.recorded_by,
        r.created_at
    FROM boiler_daily_records r
    JOIN mc_boiler m ON r.machine_id = m.id
    ORDER BY r.created_at DESC
    LIMIT 5
");
$recentActivities = array_merge($recentActivities, $stmt->fetchAll());

// Summary recent records
$stmt = $db->query("
    SELECT 
        'summary' as module,
        r.record_date,
        r.ee_unit as value,
        'ไฟฟ้า' as machine,
        CONCAT('ค่าไฟ: ', r.total_cost, ' บาท') as item,
        r.recorded_by,
        r.created_at
    FROM electricity_summary r
    ORDER BY r.created_at DESC
    LIMIT 5
");
$recentActivities = array_merge($recentActivities, $stmt->fetchAll());

// Sort by created_at descending
usort($recentActivities, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Take only first 10
$recentActivities = array_slice($recentActivities, 0, 10);

// Get alerts and warnings
$alerts = [];

// Air Compressor - values outside standard
$stmt = $db->query("
    SELECT 
        'air' as module,
        r.record_date,
        m.machine_name,
        s.inspection_item,
        r.actual_value,
        s.standard_value,
        s.min_value,
        s.max_value
    FROM air_daily_records r
    JOIN mc_air m ON r.machine_id = m.id
    JOIN air_inspection_standards s ON r.inspection_item_id = s.id
    WHERE (s.min_value IS NOT NULL AND (r.actual_value < s.min_value OR r.actual_value > s.max_value))
       OR (s.min_value IS NULL AND ABS(r.actual_value - s.standard_value) > s.standard_value * 0.1)
    ORDER BY r.record_date DESC
    LIMIT 5
");
$alerts = array_merge($alerts, $stmt->fetchAll());

// LPG - NG records
$stmt = $db->query("
    SELECT 
        'lpg' as module,
        r.record_date,
        'LPG' as machine_name,
        i.item_name,
        r.enum_value as actual_value,
        i.standard_value
    FROM lpg_daily_records r
    JOIN lpg_inspection_items i ON r.item_id = i.id
    WHERE r.enum_value = 'NG'
    ORDER BY r.record_date DESC
    LIMIT 5
");
$alerts = array_merge($alerts, $stmt->fetchAll());

// Boiler - values outside standard
$stmt = $db->query("
    SELECT 
        'boiler' as module,
        r.record_date,
        m.machine_name,
        CASE 
            WHEN r.steam_pressure < 8 OR r.steam_pressure > 12 THEN 'แรงดันไอน้ำ'
            WHEN r.steam_temperature < 170 OR r.steam_temperature > 190 THEN 'อุณหภูมิไอน้ำ'
            WHEN r.feed_water_level < 0.5 OR r.feed_water_level > 1.5 THEN 'ระดับน้ำ'
        END as inspection_item,
        CASE 
            WHEN r.steam_pressure < 8 OR r.steam_pressure > 12 THEN r.steam_pressure
            WHEN r.steam_temperature < 170 OR r.steam_temperature > 190 THEN r.steam_temperature
            WHEN r.feed_water_level < 0.5 OR r.feed_water_level > 1.5 THEN r.feed_water_level
        END as actual_value,
        CASE 
            WHEN r.steam_pressure < 8 OR r.steam_pressure > 12 THEN '8-12 bar'
            WHEN r.steam_temperature < 170 OR r.steam_temperature > 190 THEN '170-190 °C'
            WHEN r.feed_water_level < 0.5 OR r.feed_water_level > 1.5 THEN '0.5-1.5 m'
        END as standard_value
    FROM boiler_daily_records r
    JOIN mc_boiler m ON r.machine_id = m.id
    WHERE (r.steam_pressure < 8 OR r.steam_pressure > 12)
       OR (r.steam_temperature < 170 OR r.steam_temperature > 190)
       OR (r.feed_water_level < 0.5 OR r.feed_water_level > 1.5)
    ORDER BY r.record_date DESC
    LIMIT 5
");
$alerts = array_merge($alerts, $stmt->fetchAll());

// Get documents
$stmt = $db->prepare("
    SELECT * FROM documents 
    WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$currentMonth, $currentYear]);
$recentDocuments = $stmt->fetchAll();
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Info Boxes -->
        <div class="row">
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-info elevation-1">
                        <i class="fas fa-calendar-day"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">วันนี้</span>
                        <span class="info-box-number">
                            <?php echo date('d/m/' . ($currentYear + 543)); ?>
                        </span>
                        <span class="info-box-text"><?php echo getThaiDay(date('l')); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-success elevation-1">
                        <i class="fas fa-database"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">บันทึกทั้งหมด</span>
                        <span class="info-box-number">
                            <?php 
                            $totalRecords = 0;
                            foreach ($moduleStats as $stat) {
                                $totalRecords += $stat['month_count'];
                            }
                            echo number_format($totalRecords);
                            ?>
                        </span>
                        <span class="info-box-text">รายการเดือนนี้</span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-warning elevation-1">
                        <i class="fas fa-exclamation-triangle"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">การแจ้งเตือน</span>
                        <span class="info-box-number"><?php echo count($alerts); ?></span>
                        <span class="info-box-text">รายการที่ต้องตรวจสอบ</span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-danger elevation-1">
                        <i class="fas fa-clock"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">เวลา</span>
                        <span class="info-box-number" id="liveClock"><?php echo date('H:i:s'); ?></span>
                        <span class="info-box-text"><?php echo date('H:i น.'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Module Cards -->
        <div class="row">
            <?php foreach ($moduleStats as $key => $stat): ?>
            <div class="col-lg-4 col-6">
                <div class="small-box bg-<?php echo $stat['color']; ?>">
                    <div class="inner">
                        <h3><?php echo $stat['today_count']; ?><sup style="font-size: 20px"> วันนี้</sup></h3>
                        <h5><?php echo $stat['name']; ?></h5>
                        <p>
                            เครื่องจักร: <?php echo $stat['machine_count']; ?> | 
                            เดือนนี้: <?php echo $stat['month_count']; ?> |
                            รวม: <?php echo $stat['total_usage']; ?>
                        </p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-<?php echo $stat['icon']; ?>"></i>
                    </div>
                    <a href="modules/<?php echo $key == 'energy' ? 'energy-water' : ($key == 'summary' ? 'summary-electricity' : $key); ?>/index.php" 
                       class="small-box-footer">
                        ดูรายละเอียด <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Charts Row -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            สรุปการใช้งานรายเดือน
                        </h3>
                        <div class="card-tools">
                            <select id="yearSelect" class="form-control form-control-sm">
                                <?php for ($y = $currentYear - 2; $y <= $currentYear; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>>
                                    ปี <?php echo $y + 543; ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="monthlyChart" style="min-height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-pie"></i>
                            สัดส่วนการใช้งาน
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="pieChart" style="min-height: 250px;"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bell"></i>
                            การแจ้งเตือนล่าสุด
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php if (empty($alerts)): ?>
                            <li class="list-group-item text-center text-muted">
                                <i class="fas fa-check-circle text-success"></i> ไม่มีการแจ้งเตือน
                            </li>
                            <?php else: ?>
                                <?php foreach (array_slice($alerts, 0, 5) as $alert): ?>
                                <li class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <span class="badge badge-<?php 
                                                echo $alert['module'] == 'air' ? 'info' : 
                                                    ($alert['module'] == 'lpg' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php 
                                                echo $alert['module'] == 'air' ? 'Air' : 
                                                    ($alert['module'] == 'lpg' ? 'LPG' : 'Boiler'); 
                                                ?>
                                            </span>
                                        </h6>
                                        <small><?php echo date('d/m', strtotime($alert['record_date'])); ?></small>
                                    </div>
                                    <p class="mb-1">
                                        <?php echo $alert['machine_name'] ?? 'LPG'; ?> - 
                                        <?php echo $alert['inspection_item'] ?? ''; ?>
                                    </p>
                                    <small class="text-danger">
                                        ค่า: <?php echo is_numeric($alert['actual_value']) ? number_format($alert['actual_value'], 2) : $alert['actual_value']; ?> 
                                        (มาตรฐาน: <?php echo $alert['standard_value']; ?>)
                                    </small>
                                </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="card-footer text-center">
                        <a href="#">ดูทั้งหมด</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activities and Documents -->
        <div class="row">
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i>
                            กิจกรรมล่าสุด
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>โมดูล</th>
                                        <th>วันที่</th>
                                        <th>รายละเอียด</th>
                                        <th>ผู้บันทึก</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentActivities as $activity): ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $activity['module'] == 'air' ? 'info' : 
                                                    ($activity['module'] == 'energy' ? 'success' : 
                                                    ($activity['module'] == 'lpg' ? 'warning' : 
                                                    ($activity['module'] == 'boiler' ? 'danger' : 'secondary'))); 
                                            ?>">
                                                <?php 
                                                echo $activity['module'] == 'air' ? 'Air' : 
                                                    ($activity['module'] == 'energy' ? 'Energy' : 
                                                    ($activity['module'] == 'lpg' ? 'LPG' : 
                                                    ($activity['module'] == 'boiler' ? 'Boiler' : 'Summary'))); 
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($activity['record_date'])); ?></td>
                                        <td>
                                            <strong><?php echo $activity['machine'] ?? ''; ?></strong><br>
                                            <small><?php echo $activity['item'] ?? ''; ?></small>
                                        </td>
                                        <td><?php echo $activity['recorded_by']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-file-alt"></i>
                            เอกสารล่าสุด
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php if (empty($recentDocuments)): ?>
                            <li class="list-group-item text-center text-muted">
                                ไม่พบเอกสาร
                            </li>
                            <?php else: ?>
                                <?php foreach ($recentDocuments as $doc): ?>
                                <li class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo $doc['doc_no']; ?></h6>
                                        <small><?php echo date('d/m/Y', strtotime($doc['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1">
                                        <?php 
                                        $moduleName = '';
                                        switch ($doc['module_type']) {
                                            case 'air': $moduleName = 'Air Compressor'; break;
                                            case 'energy_water': $moduleName = 'Energy & Water'; break;
                                            case 'lpg': $moduleName = 'LPG'; break;
                                            case 'boiler': $moduleName = 'Boiler'; break;
                                            case 'summary': $moduleName = 'Summary Electricity'; break;
                                        }
                                        echo $moduleName . ' - Rev.' . $doc['rev_no'];
                                        ?>
                                    </p>
                                    <small class="text-muted"><?php echo $doc['details'] ?: '-'; ?></small>
                                </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-calendar-check"></i>
                            ข้อมูลเดือน <?php echo getThaiMonth($currentMonth) . ' ' . $thaiYear; ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="progress-group">
                            <span class="progress-text">Air Compressor</span>
                            <span class="float-right"><b><?php echo $moduleStats['air']['month_count']; ?></b>/30</span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-info" style="width: <?php echo min(($moduleStats['air']['month_count'] / 30) * 100, 100); ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="progress-group">
                            <span class="progress-text">Energy & Water</span>
                            <span class="float-right"><b><?php echo $moduleStats['energy']['month_count']; ?></b>/60</span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-success" style="width: <?php echo min(($moduleStats['energy']['month_count'] / 60) * 100, 100); ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="progress-group">
                            <span class="progress-text">LPG</span>
                            <span class="float-right"><b><?php echo $moduleStats['lpg']['month_count']; ?></b>/30</span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-warning" style="width: <?php echo min(($moduleStats['lpg']['month_count'] / 30) * 100, 100); ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="progress-group">
                            <span class="progress-text">Boiler</span>
                            <span class="float-right"><b><?php echo $moduleStats['boiler']['month_count']; ?></b>/30</span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-danger" style="width: <?php echo min(($moduleStats['boiler']['month_count'] / 30) * 100, 100); ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="progress-group">
                            <span class="progress-text">Summary Electricity</span>
                            <span class="float-right"><b><?php echo $moduleStats['summary']['month_count']; ?></b>/30</span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-secondary" style="width: <?php echo min(($moduleStats['summary']['month_count'] / 30) * 100, 100); ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
let monthlyChart = null;
let pieChart = null;

$(document).ready(function() {
    // Live clock
    setInterval(function() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('th-TH', { hour12: false });
        $('#liveClock').text(timeString);
    }, 1000);
    
    // Load charts
    loadMonthlyChart($('#yearSelect').val());
    loadPieChart();
    
    // Year change event
    $('#yearSelect').on('change', function() {
        loadMonthlyChart($(this).val());
    });
});

function loadMonthlyChart(year) {
    $.ajax({
        url: 'api/get_dashboard_data.php',
        method: 'GET',
        data: {
            type: 'monthly',
            year: year
        },
        success: function(response) {
            if (response.success) {
                renderMonthlyChart(response.data);
            }
        }
    });
}

function loadPieChart() {
    $.ajax({
        url: 'api/get_dashboard_data.php',
        method: 'GET',
        data: {
            type: 'pie'
        },
        success: function(response) {
            if (response.success) {
                renderPieChart(response.data);
            }
        }
    });
}

function renderMonthlyChart(data) {
    const ctx = document.getElementById('monthlyChart').getContext('2d');
    
    if (monthlyChart) {
        monthlyChart.destroy();
    }
    
    monthlyChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Air Compressor',
                    data: data.air,
                    borderColor: '#17a2b8',
                    backgroundColor: 'rgba(23, 162, 184, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: false
                },
                {
                    label: 'Energy & Water',
                    data: data.energy,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: false
                },
                {
                    label: 'LPG',
                    data: data.lpg,
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: false
                },
                {
                    label: 'Boiler',
                    data: data.boiler,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: false
                },
                {
                    label: 'Electricity',
                    data: data.summary,
                    borderColor: '#6c757d',
                    backgroundColor: 'rgba(108, 117, 125, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: false
                }
            ]
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
                        text: 'ปริมาณการใช้งาน'
                    }
                }
            }
        }
    });
}

function renderPieChart(data) {
    const ctx = document.getElementById('pieChart').getContext('2d');
    
    if (pieChart) {
        pieChart.destroy();
    }
    
    pieChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Air Compressor', 'Energy & Water', 'LPG', 'Boiler', 'Electricity'],
            datasets: [{
                data: [
                    data.air,
                    data.energy,
                    data.lpg,
                    data.boiler,
                    data.summary
                ],
                backgroundColor: [
                    '#17a2b8',
                    '#28a745',
                    '#ffc107',
                    '#dc3545',
                    '#6c757d'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            },
            cutout: '60%'
        }
    });
}
</script>

<?php
// Include footer
require_once __DIR__ . '/includes/footer.php';
?>