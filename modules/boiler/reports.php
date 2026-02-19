<?php
/**
 * Boiler Module - Reports Page
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
$pageTitle = 'Boiler - รายงาน';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Boiler', 'link' => 'index.php'],
    ['title' => 'รายงาน', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Load required files
require_once __DIR__ . '/../../includes/functions.php';

// Get database connection
$db = getDB();

// Get parameters
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$machineId = isset($_GET['machine_id']) ? (int)$_GET['machine_id'] : 0;
$parameter = isset($_GET['parameter']) ? $_GET['parameter'] : 'all';
$compareWith = isset($_GET['compare_with']) ? $_GET['compare_with'] : 'previous';

// Format dates for display
$displayStartDate = date('d/m/Y', strtotime($startDate));
$displayEndDate = date('d/m/Y', strtotime($endDate));

// Get all machines for dropdown
$stmt = $db->query("
    SELECT * FROM mc_boiler 
    WHERE status = 1 
    ORDER BY machine_code
");
$machines = $stmt->fetchAll();

// Get report data based on type
$reportData = [];
$summary = [];
$chartData = [];
$statistics = [];
$efficiencyData = [];

switch ($reportType) {
    case 'daily':
        $reportData = getDailyReport($db, $startDate, $endDate, $machineId);
        $summary = calculateDailySummary($reportData);
        $chartData = prepareDailyChartData($reportData);
        $statistics = calculateDailyStatistics($reportData);
        break;
        
    case 'monthly':
        $reportData = getMonthlyReport($db, $startDate, $endDate, $machineId);
        $summary = calculateMonthlySummary($reportData);
        $chartData = prepareMonthlyChartData($reportData);
        $statistics = calculateMonthlyStatistics($reportData);
        break;
        
    case 'efficiency':
        $reportData = getEfficiencyReport($db, $startDate, $endDate, $machineId);
        $summary = calculateEfficiencySummary($reportData);
        $chartData = prepareEfficiencyChartData($reportData);
        $statistics = calculateEfficiencyStatistics($reportData);
        $efficiencyData = calculateEfficiencyMetrics($reportData);
        break;
        
    case 'parameter':
        $reportData = getParameterReport($db, $startDate, $endDate, $machineId, $parameter);
        $summary = calculateParameterSummary($reportData);
        $chartData = prepareParameterChartData($reportData, $parameter);
        $statistics = calculateParameterStatistics($reportData, $parameter);
        break;
        
    case 'comparison':
        $reportData = getComparisonReport($db, $startDate, $endDate, $machineId, $compareWith);
        $summary = calculateComparisonSummary($reportData);
        $chartData = prepareComparisonChartData($reportData);
        $statistics = calculateComparisonStatistics($reportData);
        break;
        
    case 'machine_detail':
        $reportData = getMachineDetailReport($db, $startDate, $endDate, $machineId);
        $summary = calculateMachineDetailSummary($reportData);
        $chartData = prepareMachineDetailChartData($reportData);
        $statistics = calculateMachineDetailStatistics($reportData);
        break;
}

// Get machine name if selected
$machineName = '';
if ($machineId > 0) {
    foreach ($machines as $m) {
        if ($m['id'] == $machineId) {
            $machineName = $m['machine_name'];
            break;
        }
    }
}
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Report Filter Card -->
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-filter"></i>
                    ตัวเลือกรายงาน
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" class="form-horizontal" id="reportForm">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>ประเภทรายงาน</label>
                                <select name="report_type" id="reportType" class="form-control">
                                    <option value="daily" <?php echo $reportType == 'daily' ? 'selected' : ''; ?>>รายงานรายวัน</option>
                                    <option value="monthly" <?php echo $reportType == 'monthly' ? 'selected' : ''; ?>>รายงานรายเดือน</option>
                                    <option value="efficiency" <?php echo $reportType == 'efficiency' ? 'selected' : ''; ?>>รายงานประสิทธิภาพ</option>
                                    <option value="parameter" <?php echo $reportType == 'parameter' ? 'selected' : ''; ?>>รายงานแยกพารามิเตอร์</option>
                                    <option value="comparison" <?php echo $reportType == 'comparison' ? 'selected' : ''; ?>>รายงานเปรียบเทียบ</option>
                                    <option value="machine_detail" <?php echo $reportType == 'machine_detail' ? 'selected' : ''; ?>>รายงานแยกเครื่อง</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-2" id="dateRangeDiv">
                            <div class="form-group">
                                <label>วันที่เริ่มต้น</label>
                                <div class="input-group date" id="startDatePicker" data-target-input="nearest">
                                    <input type="text" class="form-control datetimepicker-input" 
                                           name="start_date" id="startDate" 
                                           value="<?php echo $displayStartDate; ?>" 
                                           data-target="#startDatePicker">
                                    <div class="input-group-append" data-target="#startDatePicker" data-toggle="datetimepicker">
                                        <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-2" id="endDateDiv">
                            <div class="form-group">
                                <label>วันที่สิ้นสุด</label>
                                <div class="input-group date" id="endDatePicker" data-target-input="nearest">
                                    <input type="text" class="form-control datetimepicker-input" 
                                           name="end_date" id="endDate" 
                                           value="<?php echo $displayEndDate; ?>" 
                                           data-target="#endDatePicker">
                                    <div class="input-group-append" data-target="#endDatePicker" data-toggle="datetimepicker">
                                        <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-2" id="machineDiv">
                            <div class="form-group">
                                <label>เครื่อง Boiler</label>
                                <select name="machine_id" class="form-control select2">
                                    <option value="0">ทั้งหมด</option>
                                    <?php foreach ($machines as $machine): ?>
                                        <option value="<?php echo $machine['id']; ?>" 
                                                <?php echo $machineId == $machine['id'] ? 'selected' : ''; ?>>
                                            <?php echo $machine['machine_code'] . ' - ' . $machine['machine_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-2" id="parameterDiv" style="display: none;">
                            <div class="form-group">
                                <label>พารามิเตอร์</label>
                                <select name="parameter" class="form-control">
                                    <option value="all" <?php echo $parameter == 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                                    <option value="pressure" <?php echo $parameter == 'pressure' ? 'selected' : ''; ?>>แรงดันไอน้ำ</option>
                                    <option value="temperature" <?php echo $parameter == 'temperature' ? 'selected' : ''; ?>>อุณหภูมิไอน้ำ</option>
                                    <option value="water_level" <?php echo $parameter == 'water_level' ? 'selected' : ''; ?>>ระดับน้ำ</option>
                                    <option value="fuel" <?php echo $parameter == 'fuel' ? 'selected' : ''; ?>>ปริมาณเชื้อเพลิง</option>
                                    <option value="hours" <?php echo $parameter == 'hours' ? 'selected' : ''; ?>>ชั่วโมงทำงาน</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-2" id="compareDiv" style="display: none;">
                            <div class="form-group">
                                <label>เปรียบเทียบกับ</label>
                                <select name="compare_with" class="form-control">
                                    <option value="previous" <?php echo $compareWith == 'previous' ? 'selected' : ''; ?>>ช่วงก่อนหน้า</option>
                                    <option value="last_year" <?php echo $compareWith == 'last_year' ? 'selected' : ''; ?>>ปีที่แล้ว</option>
                                    <option value="standard" <?php echo $compareWith == 'standard' ? 'selected' : ''; ?>>ค่ามาตรฐาน</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-search"></i> แสดงรายงาน
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="button" class="btn btn-success btn-block" onclick="exportReport()">
                                    <i class="fas fa-download"></i> ส่งออกรายงาน
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="button" class="btn btn-info btn-block" onclick="printReport()">
                                    <i class="fas fa-print"></i> พิมพ์
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <?php if (!empty($statistics)): ?>
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo number_format($statistics['avg_pressure'] ?? 0, 2); ?></h3>
                        <p>แรงดันเฉลี่ย (bar)</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-gauge-high"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo number_format($statistics['avg_temperature'] ?? 0, 1); ?></h3>
                        <p>อุณหภูมิเฉลี่ย (°C)</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-temperature-high"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo number_format($statistics['total_fuel'] ?? 0, 2); ?></h3>
                        <p>เชื้อเพลิงรวม (L)</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-fire"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo number_format($statistics['total_hours'] ?? 0, 1); ?></h3>
                        <p>ชั่วโมงทำงานรวม</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Efficiency Cards (for efficiency report) -->
        <?php if ($reportType == 'efficiency' && !empty($efficiencyData)): ?>
        <div class="row">
            <div class="col-lg-4 col-6">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3><?php echo number_format($efficiencyData['fuel_efficiency'], 2); ?></h3>
                        <p>ประสิทธิภาพเชื้อเพลิง (bar/L)</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-6">
                <div class="small-box bg-secondary">
                    <div class="inner">
                        <h3><?php echo number_format($efficiencyData['thermal_efficiency'], 1); ?>%</h3>
                        <p>ประสิทธิภาพเชิงความร้อน</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-fire"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo number_format($efficiencyData['operating_efficiency'], 1); ?>%</h3>
                        <p>ประสิทธิภาพการทำงาน</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Summary Cards -->
        <?php if (!empty($summary)): ?>
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3><?php echo $summary['total_days'] ?? 0; ?></h3>
                        <p>จำนวนวัน</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-secondary">
                    <div class="inner">
                        <h3><?php echo $summary['total_records'] ?? 0; ?></h3>
                        <p>จำนวนบันทึก</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo number_format($summary['avg_pressure'] ?? 0, 2); ?></h3>
                        <p>แรงดันเฉลี่ย</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo number_format($summary['avg_temperature'] ?? 0, 1); ?></h3>
                        <p>อุณหภูมิเฉลี่ย</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-thermometer-half"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Chart Section -->
        <?php if (!empty($chartData)): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-line"></i>
                    กราฟแสดงข้อมูล
                </h3>
                <div class="card-tools">
                    <select id="chartType" class="form-control form-control-sm" style="width: 150px;">
                        <option value="line">กราฟเส้น</option>
                        <option value="bar">กราฟแท่ง</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="reportChart" style="min-height: 400px;"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Standard Values Table (for parameter report) -->
        <?php if ($reportType == 'parameter' && !empty($reportData)): ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-check-circle"></i>
                            การประเมินตามค่ามาตรฐาน
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>วันที่</th>
                                        <th>พารามิเตอร์</th>
                                        <th>ค่าที่วัดได้</th>
                                        <th>ค่ามาตรฐาน</th>
                                        <th>สถานะ</th>
                                        <th>การประเมิน</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $row): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($row['record_date'])); ?></td>
                                        <td>
                                            <?php 
                                            if ($parameter == 'pressure' || $parameter == 'all') {
                                                echo 'แรงดันไอน้ำ';
                                            } elseif ($parameter == 'temperature') {
                                                echo 'อุณหภูมิไอน้ำ';
                                            } elseif ($parameter == 'water_level') {
                                                echo 'ระดับน้ำ';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-right">
                                            <?php 
                                            if ($parameter == 'pressure' || $parameter == 'all') {
                                                echo number_format($row['steam_pressure'], 2);
                                            } elseif ($parameter == 'temperature') {
                                                echo number_format($row['steam_temperature'], 1);
                                            } elseif ($parameter == 'water_level') {
                                                echo number_format($row['feed_water_level'], 2);
                                            }
                                            ?>
                                        </td>
                                        <td class="text-right">
                                            <?php 
                                            if ($parameter == 'pressure' || $parameter == 'all') {
                                                echo '8-12 bar';
                                            } elseif ($parameter == 'temperature') {
                                                echo '170-190 °C';
                                            } elseif ($parameter == 'water_level') {
                                                echo '0.5-1.5 m';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <?php 
                                            $status = 'OK';
                                            if ($parameter == 'pressure' || $parameter == 'all') {
                                                $status = ($row['steam_pressure'] >= 8 && $row['steam_pressure'] <= 12) ? 'OK' : 'NG';
                                            } elseif ($parameter == 'temperature') {
                                                $status = ($row['steam_temperature'] >= 170 && $row['steam_temperature'] <= 190) ? 'OK' : 'NG';
                                            } elseif ($parameter == 'water_level') {
                                                $status = ($row['feed_water_level'] >= 0.5 && $row['feed_water_level'] <= 1.5) ? 'OK' : 'NG';
                                            }
                                            ?>
                                            <span class="badge badge-<?php echo $status == 'OK' ? 'success' : 'danger'; ?>">
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($status == 'OK') {
                                                echo '<span class="text-success"><i class="fas fa-check-circle"></i> ผ่านเกณฑ์</span>';
                                            } else {
                                                echo '<span class="text-danger"><i class="fas fa-times-circle"></i> ไม่ผ่านเกณฑ์</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Data Table -->
        <?php if (!empty($reportData) && $reportType != 'parameter'): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table"></i>
                    ข้อมูลรายละเอียด
                </h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped dataTable" id="reportTable">
                        <thead>
                            <tr>
                                <?php if ($reportType == 'daily'): ?>
                                <th>วันที่</th>
                                <th>เครื่องจักร</th>
                                <th>แรงดันไอน้ำ</th>
                                <th>อุณหภูมิไอน้ำ</th>
                                <th>ระดับน้ำ</th>
                                <th>เชื้อเพลิง</th>
                                <th>ชั่วโมงทำงาน</th>
                                <th>อัตราสิ้นเปลือง</th>
                                <th>ผู้บันทึก</th>
                                
                                <?php elseif ($reportType == 'monthly'): ?>
                                <th>เดือน</th>
                                <th>จำนวนวัน</th>
                                <th>แรงดันเฉลี่ย</th>
                                <th>อุณหภูมิเฉลี่ย</th>
                                <th>เชื้อเพลิงรวม</th>
                                <th>ชั่วโมงรวม</th>
                                <th>แรงดันสูงสุด</th>
                                <th>อุณหภูมิสูงสุด</th>
                                
                                <?php elseif ($reportType == 'efficiency'): ?>
                                <th>วันที่</th>
                                <th>เครื่องจักร</th>
                                <th>แรงดัน</th>
                                <th>เชื้อเพลิง</th>
                                <th>ชั่วโมง</th>
                                <th>ประสิทธิภาพเชื้อเพลิง</th>
                                <th>ประสิทธิภาพความร้อน</th>
                                
                                <?php elseif ($reportType == 'comparison'): ?>
                                <th>ช่วงเวลา</th>
                                <th>แรงดันเฉลี่ย</th>
                                <th>อุณหภูมิเฉลี่ย</th>
                                <th>เชื้อเพลิงรวม</th>
                                <th>ชั่วโมงรวม</th>
                                <th>จำนวนบันทึก</th>
                                <th>เปลี่ยนแปลง</th>
                                
                                <?php elseif ($reportType == 'machine_detail'): ?>
                                <th>วันที่</th>
                                <th>แรงดัน</th>
                                <th>อุณหภูมิ</th>
                                <th>ระดับน้ำ</th>
                                <th>เชื้อเพลิง</th>
                                <th>ชั่วโมง</th>
                                <th>เทียบกับวันก่อน</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                            <tr>
                                <?php if ($reportType == 'daily'): ?>
                                <td><?php echo date('d/m/Y', strtotime($row['record_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['machine_name']); ?></td>
                                <td class="text-right <?php echo ($row['steam_pressure'] < 8 || $row['steam_pressure'] > 12) ? 'text-danger' : ''; ?>">
                                    <?php echo number_format($row['steam_pressure'], 2); ?>
                                </td>
                                <td class="text-right <?php echo ($row['steam_temperature'] < 170 || $row['steam_temperature'] > 190) ? 'text-danger' : ''; ?>">
                                    <?php echo number_format($row['steam_temperature'], 1); ?>
                                </td>
                                <td class="text-right <?php echo ($row['feed_water_level'] < 0.5 || $row['feed_water_level'] > 1.5) ? 'text-danger' : ''; ?>">
                                    <?php echo number_format($row['feed_water_level'], 2); ?>
                                </td>
                                <td class="text-right"><?php echo number_format($row['fuel_consumption'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['operating_hours'], 1); ?></td>
                                <td class="text-right">
                                    <?php 
                                    $fuelRate = $row['operating_hours'] > 0 ? $row['fuel_consumption'] / $row['operating_hours'] : 0;
                                    echo number_format($fuelRate, 2);
                                    ?> L/hr
                                </td>
                                <td><?php echo htmlspecialchars($row['recorded_by']); ?></td>
                                
                                <?php elseif ($reportType == 'monthly'): ?>
                                <td><?php echo getThaiMonth($row['month']) . ' ' . ($row['year'] + 543); ?></td>
                                <td class="text-right"><?php echo $row['days_count']; ?></td>
                                <td class="text-right"><?php echo number_format($row['avg_pressure'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['avg_temperature'], 1); ?></td>
                                <td class="text-right"><?php echo number_format($row['total_fuel'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['total_hours'], 1); ?></td>
                                <td class="text-right"><?php echo number_format($row['max_pressure'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['max_temperature'], 1); ?></td>
                                
                                <?php elseif ($reportType == 'efficiency'): ?>
                                <td><?php echo date('d/m/Y', strtotime($row['record_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['machine_name']); ?></td>
                                <td class="text-right"><?php echo number_format($row['steam_pressure'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['fuel_consumption'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['operating_hours'], 1); ?></td>
                                <td class="text-right">
                                    <?php 
                                    $fuelEfficiency = $row['fuel_consumption'] > 0 ? $row['steam_pressure'] / $row['fuel_consumption'] : 0;
                                    echo number_format($fuelEfficiency, 3);
                                    ?>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $thermalEfficiency = ($row['steam_temperature'] * $row['steam_pressure']) / ($row['fuel_consumption'] * $row['operating_hours'] + 1);
                                    echo number_format($thermalEfficiency, 1);
                                    ?>%
                                </td>
                                
                                <?php elseif ($reportType == 'comparison'): ?>
                                <td><?php echo $row['period']; ?></td>
                                <td class="text-right"><?php echo number_format($row['avg_pressure'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['avg_temperature'], 1); ?></td>
                                <td class="text-right"><?php echo number_format($row['total_fuel'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['total_hours'], 1); ?></td>
                                <td class="text-right"><?php echo $row['records']; ?></td>
                                <td class="text-right">
                                    <?php if (isset($row['change_percent'])): ?>
                                    <span class="badge badge-<?php echo $row['change_percent'] > 0 ? 'danger' : ($row['change_percent'] < 0 ? 'success' : 'secondary'); ?>">
                                        <?php echo $row['change_percent'] > 0 ? '+' : ''; ?><?php echo number_format($row['change_percent'], 1); ?>%
                                    </span>
                                    <?php endif; ?>
                                </td>
                                
                                <?php elseif ($reportType == 'machine_detail'): ?>
                                <td><?php echo date('d/m/Y', strtotime($row['record_date'])); ?></td>
                                <td class="text-right"><?php echo number_format($row['steam_pressure'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['steam_temperature'], 1); ?></td>
                                <td class="text-right"><?php echo number_format($row['feed_water_level'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['fuel_consumption'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['operating_hours'], 1); ?></td>
                                <td class="text-center">
                                    <?php if (isset($row['prev_pressure'])): ?>
                                        <?php 
                                        $change = $row['steam_pressure'] - $row['prev_pressure'];
                                        $changePercent = $row['prev_pressure'] > 0 ? ($change / $row['prev_pressure']) * 100 : 0;
                                        ?>
                                        <span class="badge badge-<?php echo $change > 0 ? 'danger' : ($change < 0 ? 'success' : 'secondary'); ?>">
                                            <?php echo $change > 0 ? '+' : ''; ?><?php echo number_format($change, 2); ?> (<?php echo number_format($changePercent, 1); ?>%)
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if (!empty($summary) && $reportType == 'daily'): ?>
                        <tfoot>
                            <tr class="bg-gray">
                                <th colspan="2" class="text-right">รวม/เฉลี่ย</th>
                                <th class="text-right"><?php echo number_format($summary['avg_pressure'], 2); ?></th>
                                <th class="text-right"><?php echo number_format($summary['avg_temperature'], 1); ?></th>
                                <th class="text-right"><?php echo number_format($summary['avg_water_level'], 2); ?></th>
                                <th class="text-right"><?php echo number_format($summary['total_fuel'], 2); ?></th>
                                <th class="text-right"><?php echo number_format($summary['total_hours'], 1); ?></th>
                                <th class="text-right"><?php echo number_format($summary['avg_fuel_rate'], 2); ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
        <?php elseif (empty($reportData) && $reportType != 'parameter'): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            ไม่พบข้อมูลในช่วงเวลาที่เลือก
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
let reportChart = null;

$(document).ready(function() {
    // Initialize date pickers
    $('#startDatePicker').datetimepicker({
        format: 'DD/MM/YYYY',
        locale: 'th',
        useCurrent: false
    });
    
    $('#endDatePicker').datetimepicker({
        format: 'DD/MM/YYYY',
        locale: 'th',
        useCurrent: false
    });
    
    // Initialize select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });
    
    // Initialize DataTable if exists
    if ($('#reportTable').length) {
        $('#reportTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.10.21/i18n/Thai.json'
            },
            pageLength: 25,
            order: [[0, 'desc']]
        });
    }
    
    // Toggle fields based on report type
    toggleReportFields();
    
    $('#reportType').on('change', function() {
        toggleReportFields();
    });
    
    // Chart type change
    $('#chartType').on('change', function() {
        renderChart(<?php echo json_encode($chartData); ?>, $(this).val());
    });
    
    // Render initial chart
    <?php if (!empty($chartData)): ?>
    renderChart(<?php echo json_encode($chartData); ?>, 'line');
    <?php endif; ?>
});

function toggleReportFields() {
    const type = $('#reportType').val();
    
    // Hide all optional fields first
    $('#parameterDiv').hide();
    $('#compareDiv').hide();
    $('#machineDiv').show();
    
    if (type === 'parameter') {
        $('#parameterDiv').show();
        $('#compareDiv').hide();
    } else if (type === 'comparison') {
        $('#parameterDiv').hide();
        $('#compareDiv').show();
    }
}

function renderChart(data, type) {
    const ctx = document.getElementById('reportChart').getContext('2d');
    
    if (reportChart) {
        reportChart.destroy();
    }
    
    let chartConfig = {
        type: type,
        data: {
            labels: data.labels || [],
            datasets: []
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            let value = context.raw || 0;
                            return label + ': ' + value.toFixed(2) + (context.dataset.unit || '');
                        }
                    }
                }
            }
        }
    };
    
    // Add datasets
    if (data.datasets) {
        chartConfig.data.datasets = data.datasets;
    } else if (data.pressure && data.temperature) {
        chartConfig.data = {
            labels: data.labels,
            datasets: [
                {
                    label: 'แรงดันไอน้ำ (bar)',
                    data: data.pressure,
                    borderColor: '#17a2b8',
                    backgroundColor: type === 'line' ? 'rgba(23, 162, 184, 0.1)' : 'rgba(23, 162, 184, 0.5)',
                    yAxisID: 'y',
                    unit: ' bar'
                },
                {
                    label: 'อุณหภูมิไอน้ำ (°C)',
                    data: data.temperature,
                    borderColor: '#dc3545',
                    backgroundColor: type === 'line' ? 'rgba(220, 53, 69, 0.1)' : 'rgba(220, 53, 69, 0.5)',
                    yAxisID: 'y1',
                    unit: ' °C'
                }
            ]
        };
        
        chartConfig.options.scales = {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'แรงดัน (bar)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'อุณหภูมิ (°C)'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        };
    } else {
        chartConfig.data.datasets = [{
            label: data.label || 'ปริมาณ',
            data: data.values || [],
            borderColor: '#007bff',
            backgroundColor: type === 'line' ? 'rgba(0, 123, 255, 0.1)' : 'rgba(0, 123, 255, 0.5)',
            borderWidth: 2,
            tension: 0.4,
            fill: type === 'line'
        }];
    }
    
    reportChart = new Chart(ctx, chartConfig);
}

function exportReport() {
    const formData = $('#reportForm').serialize();
    window.location.href = 'export_report.php?' + formData;
}

function printReport() {
    window.print();
}
</script>  
<?php
// Report data fetching functions
function getDailyReport($db, $startDate, $endDate, $machineId) {
    $sql = "
        SELECT 
            r.*,
            m.machine_name,
            m.machine_code
        FROM boiler_daily_records r
        JOIN mc_boiler m ON r.machine_id = m.id
        WHERE r.record_date BETWEEN ? AND ?
    ";
    $params = [$startDate, $endDate];
    
    if ($machineId > 0) {
        $sql .= " AND r.machine_id = ?";
        $params[] = $machineId;
    }
    
    $sql .= " ORDER BY r.record_date DESC, m.machine_code";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function calculateDailySummary($data) {
    $totalFuel = 0;
    $totalHours = 0;
    $totalPressure = 0;
    $totalTemp = 0;
    $totalWater = 0;
    $days = [];
    $count = 0;
    
    foreach ($data as $row) {
        $totalFuel += $row['fuel_consumption'];
        $totalHours += $row['operating_hours'];
        $totalPressure += $row['steam_pressure'];
        $totalTemp += $row['steam_temperature'];
        $totalWater += $row['feed_water_level'];
        $days[$row['record_date']] = true;
        $count++;
    }
    
    $dayCount = count($days);
    
    return [
        'total_days' => $dayCount,
        'total_records' => count($data),
        'total_fuel' => $totalFuel,
        'total_hours' => $totalHours,
        'avg_pressure' => $count > 0 ? $totalPressure / $count : 0,
        'avg_temperature' => $count > 0 ? $totalTemp / $count : 0,
        'avg_water_level' => $count > 0 ? $totalWater / $count : 0,
        'avg_fuel_rate' => $totalHours > 0 ? $totalFuel / $totalHours : 0
    ];
}

function calculateDailyStatistics($data) {
    $totalFuel = 0;
    $totalHours = 0;
    $pressureReadings = [];
    $tempReadings = [];
    
    foreach ($data as $row) {
        $totalFuel += $row['fuel_consumption'];
        $totalHours += $row['operating_hours'];
        $pressureReadings[] = $row['steam_pressure'];
        $tempReadings[] = $row['steam_temperature'];
    }
    
    return [
        'avg_pressure' => !empty($pressureReadings) ? array_sum($pressureReadings) / count($pressureReadings) : 0,
        'avg_temperature' => !empty($tempReadings) ? array_sum($tempReadings) / count($tempReadings) : 0,
        'total_fuel' => $totalFuel,
        'total_hours' => $totalHours
    ];
}

function prepareDailyChartData($data) {
    $grouped = [];
    
    foreach ($data as $row) {
        $date = $row['record_date'];
        if (!isset($grouped[$date])) {
            $grouped[$date] = ['pressure' => 0, 'temperature' => 0, 'count' => 0];
        }
        $grouped[$date]['pressure'] += $row['steam_pressure'];
        $grouped[$date]['temperature'] += $row['steam_temperature'];
        $grouped[$date]['count']++;
    }
    
    ksort($grouped);
    $labels = [];
    $pressure = [];
    $temperature = [];
    
    foreach ($grouped as $date => $values) {
        $labels[] = date('d/m', strtotime($date));
        $pressure[] = $values['count'] > 0 ? $values['pressure'] / $values['count'] : 0;
        $temperature[] = $values['count'] > 0 ? $values['temperature'] / $values['count'] : 0;
    }
    
    return [
        'labels' => $labels,
        'pressure' => $pressure,
        'temperature' => $temperature
    ];
}

function getMonthlyReport($db, $startDate, $endDate, $machineId) {
    $sql = "
        SELECT 
            YEAR(r.record_date) as year,
            MONTH(r.record_date) as month,
            COUNT(DISTINCT r.record_date) as days_count,
            COUNT(r.id) as total_records,
            AVG(r.steam_pressure) as avg_pressure,
            AVG(r.steam_temperature) as avg_temperature,
            SUM(r.fuel_consumption) as total_fuel,
            SUM(r.operating_hours) as total_hours,
            MAX(r.steam_pressure) as max_pressure,
            MAX(r.steam_temperature) as max_temperature
        FROM boiler_daily_records r
        WHERE r.record_date BETWEEN ? AND ?
    ";
    $params = [$startDate, $endDate];
    
    if ($machineId > 0) {
        $sql .= " AND r.machine_id = ?";
        $params[] = $machineId;
    }
    
    $sql .= " GROUP BY YEAR(r.record_date), MONTH(r.record_date) ORDER BY year, month";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function calculateMonthlySummary($data) {
    $totalFuel = 0;
    $totalHours = 0;
    $pressureSum = 0;
    $tempSum = 0;
    $months = count($data);
    
    foreach ($data as $row) {
        $totalFuel += $row['total_fuel'];
        $totalHours += $row['total_hours'];
        $pressureSum += $row['avg_pressure'];
        $tempSum += $row['avg_temperature'];
    }
    
    return [
        'total_days' => $months,
        'total_records' => array_sum(array_column($data, 'total_records')),
        'total_fuel' => $totalFuel,
        'total_hours' => $totalHours,
        'avg_pressure' => $months > 0 ? $pressureSum / $months : 0,
        'avg_temperature' => $months > 0 ? $tempSum / $months : 0
    ];
}

function calculateMonthlyStatistics($data) {
    $totalFuel = 0;
    $totalHours = 0;
    $pressureAvg = 0;
    $tempAvg = 0;
    $months = count($data);
    
    foreach ($data as $row) {
        $totalFuel += $row['total_fuel'];
        $totalHours += $row['total_hours'];
        $pressureAvg += $row['avg_pressure'];
        $tempAvg += $row['avg_temperature'];
    }
    
    return [
        'avg_pressure' => $months > 0 ? $pressureAvg / $months : 0,
        'avg_temperature' => $months > 0 ? $tempAvg / $months : 0,
        'total_fuel' => $totalFuel,
        'total_hours' => $totalHours
    ];
}

function prepareMonthlyChartData($data) {
    $labels = [];
    $pressure = [];
    $temperature = [];
    
    foreach ($data as $row) {
        $labels[] = getThaiShortMonth($row['month']) . ' ' . ($row['year'] + 543);
        $pressure[] = $row['avg_pressure'];
        $temperature[] = $row['avg_temperature'];
    }
    
    return [
        'labels' => $labels,
        'pressure' => $pressure,
        'temperature' => $temperature
    ];
}

function getEfficiencyReport($db, $startDate, $endDate, $machineId) {
    $sql = "
        SELECT 
            r.*,
            m.machine_name
        FROM boiler_daily_records r
        JOIN mc_boiler m ON r.machine_id = m.id
        WHERE r.record_date BETWEEN ? AND ?
    ";
    $params = [$startDate, $endDate];
    
    if ($machineId > 0) {
        $sql .= " AND r.machine_id = ?";
        $params[] = $machineId;
    }
    
    $sql .= " ORDER BY r.record_date";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function calculateEfficiencySummary($data) {
    $totalFuel = 0;
    $totalHours = 0;
    $totalPressure = 0;
    $totalTemp = 0;
    $count = count($data);
    
    foreach ($data as $row) {
        $totalFuel += $row['fuel_consumption'];
        $totalHours += $row['operating_hours'];
        $totalPressure += $row['steam_pressure'];
        $totalTemp += $row['steam_temperature'];
    }
    
    return [
        'total_days' => $count,
        'total_records' => $count,
        'total_fuel' => $totalFuel,
        'total_hours' => $totalHours,
        'avg_pressure' => $count > 0 ? $totalPressure / $count : 0,
        'avg_temperature' => $count > 0 ? $totalTemp / $count : 0
    ];
}

function calculateEfficiencyStatistics($data) {
    $totalFuel = 0;
    $totalHours = 0;
    
    foreach ($data as $row) {
        $totalFuel += $row['fuel_consumption'];
        $totalHours += $row['operating_hours'];
    }
    
    return [
        'avg_pressure' => 0,
        'avg_temperature' => 0,
        'total_fuel' => $totalFuel,
        'total_hours' => $totalHours
    ];
}

function calculateEfficiencyMetrics($data) {
    $totalPressure = 0;
    $totalFuel = 0;
    $totalHours = 0;
    $totalTemp = 0;
    $count = count($data);
    
    foreach ($data as $row) {
        $totalPressure += $row['steam_pressure'];
        $totalFuel += $row['fuel_consumption'];
        $totalHours += $row['operating_hours'];
        $totalTemp += $row['steam_temperature'];
    }
    
    $avgPressure = $count > 0 ? $totalPressure / $count : 0;
    $avgFuel = $count > 0 ? $totalFuel / $count : 0;
    $avgHours = $count > 0 ? $totalHours / $count : 0;
    $avgTemp = $count > 0 ? $totalTemp / $count : 0;
    
    return [
        'fuel_efficiency' => $avgFuel > 0 ? $avgPressure / $avgFuel : 0,
        'thermal_efficiency' => ($avgTemp * $avgPressure) / ($avgFuel * $avgHours + 1),
        'operating_efficiency' => $avgHours > 0 ? ($avgHours / 24) * 100 : 0
    ];
}

function prepareEfficiencyChartData($data) {
    $labels = [];
    $efficiency = [];
    
    foreach ($data as $row) {
        $labels[] = date('d/m', strtotime($row['record_date']));
        $fuelEfficiency = $row['fuel_consumption'] > 0 ? $row['steam_pressure'] / $row['fuel_consumption'] : 0;
        $efficiency[] = $fuelEfficiency;
    }
    
    return [
        'labels' => $labels,
        'values' => $efficiency,
        'label' => 'ประสิทธิภาพเชื้อเพลิง (bar/L)'
    ];
}

function getParameterReport($db, $startDate, $endDate, $machineId, $parameter) {
    $sql = "
        SELECT 
            r.record_date,
            r.steam_pressure,
            r.steam_temperature,
            r.feed_water_level,
            m.machine_name
        FROM boiler_daily_records r
        JOIN mc_boiler m ON r.machine_id = m.id
        WHERE r.record_date BETWEEN ? AND ?
    ";
    $params = [$startDate, $endDate];
    
    if ($machineId > 0) {
        $sql .= " AND r.machine_id = ?";
        $params[] = $machineId;
    }
    
    $sql .= " ORDER BY r.record_date";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function calculateParameterSummary($data) {
    return [
        'total_days' => count($data),
        'total_records' => count($data),
        'total_fuel' => 0,
        'total_hours' => 0,
        'avg_pressure' => 0,
        'avg_temperature' => 0
    ];
}

function calculateParameterStatistics($data, $parameter) {
    $values = [];
    
    foreach ($data as $row) {
        if ($parameter == 'pressure') {
            $values[] = $row['steam_pressure'];
        } elseif ($parameter == 'temperature') {
            $values[] = $row['steam_temperature'];
        } elseif ($parameter == 'water_level') {
            $values[] = $row['feed_water_level'];
        }
    }
    
    return [
        'avg_pressure' => !empty($values) ? array_sum($values) / count($values) : 0,
        'avg_temperature' => 0,
        'total_fuel' => 0,
        'total_hours' => 0
    ];
}

function prepareParameterChartData($data, $parameter) {
    $labels = [];
    $values = [];
    $standardMin = 0;
    $standardMax = 0;
    $unit = '';
    
    foreach ($data as $row) {
        $labels[] = date('d/m', strtotime($row['record_date']));
        
        if ($parameter == 'pressure' || $parameter == 'all') {
            $values[] = $row['steam_pressure'];
            $standardMin = 8;
            $standardMax = 12;
            $unit = ' bar';
        } elseif ($parameter == 'temperature') {
            $values[] = $row['steam_temperature'];
            $standardMin = 170;
            $standardMax = 190;
            $unit = ' °C';
        } elseif ($parameter == 'water_level') {
            $values[] = $row['feed_water_level'];
            $standardMin = 0.5;
            $standardMax = 1.5;
            $unit = ' m';
        }
    }
    
    return [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'ค่าที่วัดได้',
                'data' => $values,
                'borderColor' => '#17a2b8',
                'backgroundColor' => 'rgba(23, 162, 184, 0.1)',
                'borderWidth' => 2,
                'tension' => 0.4,
                'fill' => false,
                'unit' => $unit
            ],
            [
                'label' => 'ค่ามาตรฐานต่ำสุด',
                'data' => array_fill(0, count($labels), $standardMin),
                'borderColor' => '#28a745',
                'borderWidth' => 2,
                'borderDash' => [5, 5],
                'fill' => false,
                'pointRadius' => 0
            ],
            [
                'label' => 'ค่ามาตรฐานสูงสุด',
                'data' => array_fill(0, count($labels), $standardMax),
                'borderColor' => '#dc3545',
                'borderWidth' => 2,
                'borderDash' => [5, 5],
                'fill' => false,
                'pointRadius' => 0
            ]
        ]
    ];
}

function getComparisonReport($db, $startDate, $endDate, $machineId, $compareWith) {
    $dateDiff = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24);
    
    if ($compareWith == 'previous') {
        $prevStart = date('Y-m-d', strtotime("-$dateDiff days", strtotime($startDate)));
        $prevEnd = date('Y-m-d', strtotime("-1 day", strtotime($startDate)));
    } elseif ($compareWith == 'last_year') {
        $prevStart = date('Y-m-d', strtotime("-1 year", strtotime($startDate)));
        $prevEnd = date('Y-m-d', strtotime("-1 year", strtotime($endDate)));
    } else {
        return getStandardComparison($db, $startDate, $endDate, $machineId);
    }
    
    $current = getPeriodSummary($db, $startDate, $endDate, $machineId);
    $previous = getPeriodSummary($db, $prevStart, $prevEnd, $machineId);
    
    $pressureChange = $current['avg_pressure'] - $previous['avg_pressure'];
    $pressureChangePercent = $previous['avg_pressure'] > 0 ? ($pressureChange / $previous['avg_pressure']) * 100 : 0;
    
    return [
        [
            'period' => 'ช่วงเวลาปัจจุบัน',
            'avg_pressure' => $current['avg_pressure'],
            'avg_temperature' => $current['avg_temperature'],
            'total_fuel' => $current['total_fuel'],
            'total_hours' => $current['total_hours'],
            'records' => $current['records'],
            'change' => null,
            'change_percent' => null
        ],
        [
            'period' => 'ช่วงเวลาเปรียบเทียบ',
            'avg_pressure' => $previous['avg_pressure'],
            'avg_temperature' => $previous['avg_temperature'],
            'total_fuel' => $previous['total_fuel'],
            'total_hours' => $previous['total_hours'],
            'records' => $previous['records'],
            'change' => -$pressureChange,
            'change_percent' => -$pressureChangePercent
        ]
    ];
}

function getPeriodSummary($db, $startDate, $endDate, $machineId) {
    $sql = "
        SELECT 
            COUNT(DISTINCT r.record_date) as days,
            COUNT(r.id) as records,
            AVG(r.steam_pressure) as avg_pressure,
            AVG(r.steam_temperature) as avg_temperature,
            SUM(r.fuel_consumption) as total_fuel,
            SUM(r.operating_hours) as total_hours
        FROM boiler_daily_records r
        WHERE r.record_date BETWEEN ? AND ?
    ";
    $params = [$startDate, $endDate];
    
    if ($machineId > 0) {
        $sql .= " AND r.machine_id = ?";
        $params[] = $machineId;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

function getStandardComparison($db, $startDate, $endDate, $machineId) {
    // Implementation for standard comparison
    return [];
}

function calculateComparisonSummary($data) {
    return [
        'total_days' => 2,
        'total_records' => ($data[0]['records'] ?? 0) + ($data[1]['records'] ?? 0),
        'total_fuel' => ($data[0]['total_fuel'] ?? 0) + ($data[1]['total_fuel'] ?? 0),
        'total_hours' => ($data[0]['total_hours'] ?? 0) + ($data[1]['total_hours'] ?? 0),
        'avg_pressure' => (($data[0]['avg_pressure'] ?? 0) + ($data[1]['avg_pressure'] ?? 0)) / 2,
        'avg_temperature' => (($data[0]['avg_temperature'] ?? 0) + ($data[1]['avg_temperature'] ?? 0)) / 2
    ];
}

function calculateComparisonStatistics($data) {
    return [
        'avg_pressure' => (($data[0]['avg_pressure'] ?? 0) + ($data[1]['avg_pressure'] ?? 0)) / 2,
        'avg_temperature' => (($data[0]['avg_temperature'] ?? 0) + ($data[1]['avg_temperature'] ?? 0)) / 2,
        'total_fuel' => ($data[0]['total_fuel'] ?? 0) + ($data[1]['total_fuel'] ?? 0),
        'total_hours' => ($data[0]['total_hours'] ?? 0) + ($data[1]['total_hours'] ?? 0)
    ];
}

function prepareComparisonChartData($data) {
    return [
        'labels' => [$data[0]['period'], $data[1]['period']],
        'datasets' => [
            [
                'label' => 'แรงดันเฉลี่ย (bar)',
                'data' => [$data[0]['avg_pressure'], $data[1]['avg_pressure']],
                'backgroundColor' => ['#17a2b8', '#17a2b8']
            ],
            [
                'label' => 'อุณหภูมิเฉลี่ย (°C)',
                'data' => [$data[0]['avg_temperature'], $data[1]['avg_temperature']],
                'backgroundColor' => ['#dc3545', '#dc3545']
            ]
        ]
    ];
}

function getMachineDetailReport($db, $startDate, $endDate, $machineId) {
    if ($machineId <= 0) return [];
    
    $sql = "
        SELECT 
            r.*
        FROM boiler_daily_records r
        WHERE r.machine_id = ? AND r.record_date BETWEEN ? AND ?
        ORDER BY r.record_date
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$machineId, $startDate, $endDate]);
    $data = $stmt->fetchAll();
    
    // Add previous day comparison
    for ($i = 0; $i < count($data); $i++) {
        if ($i > 0) {
            $data[$i]['prev_pressure'] = $data[$i-1]['steam_pressure'];
        }
    }
    
    return $data;
}

function calculateMachineDetailSummary($data) {
    $totalFuel = 0;
    $totalHours = 0;
    $count = count($data);
    
    foreach ($data as $row) {
        $totalFuel += $row['fuel_consumption'];
        $totalHours += $row['operating_hours'];
    }
    
    return [
        'total_days' => $count,
        'total_records' => $count,
        'total_fuel' => $totalFuel,
        'total_hours' => $totalHours,
        'avg_pressure' => 0,
        'avg_temperature' => 0
    ];
}

function calculateMachineDetailStatistics($data) {
    $totalFuel = 0;
    $totalHours = 0;
    
    foreach ($data as $row) {
        $totalFuel += $row['fuel_consumption'];
        $totalHours += $row['operating_hours'];
    }
    
    return [
        'avg_pressure' => 0,
        'avg_temperature' => 0,
        'total_fuel' => $totalFuel,
        'total_hours' => $totalHours
    ];
}

function prepareMachineDetailChartData($data) {
    $labels = [];
    $pressure = [];
    $temperature = [];
    $fuel = [];
    
    foreach ($data as $row) {
        $labels[] = date('d/m', strtotime($row['record_date']));
        $pressure[] = $row['steam_pressure'];
        $temperature[] = $row['steam_temperature'];
        $fuel[] = $row['fuel_consumption'];
    }
    
    return [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'แรงดัน (bar)',
                'data' => $pressure,
                'borderColor' => '#17a2b8',
                'backgroundColor' => 'rgba(23, 162, 184, 0.1)',
                'yAxisID' => 'y',
                'unit' => ' bar'
            ],
            [
                'label' => 'อุณหภูมิ (°C)',
                'data' => $temperature,
                'borderColor' => '#dc3545',
                'backgroundColor' => 'rgba(220, 53, 69, 0.1)',
                'yAxisID' => 'y1',
                'unit' => ' °C'
            ],
            [
                'label' => 'เชื้อเพลิง (L)',
                'data' => $fuel,
                'borderColor' => '#ffc107',
                'backgroundColor' => 'rgba(255, 193, 7, 0.1)',
                'yAxisID' => 'y2',
                'unit' => ' L'
            ]
        ]
    ];
}

// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>