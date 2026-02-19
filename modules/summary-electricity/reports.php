<?php
/**
 * Summary Electricity Module - Reports Page
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
$pageTitle = 'Summary Electricity - รายงาน';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Summary Electricity', 'link' => 'index.php'],
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
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$compareWith = isset($_GET['compare_with']) ? $_GET['compare_with'] : 'previous';
$chartType = isset($_GET['chart_type']) ? $_GET['chart_type'] : 'line';

// Format dates for display
$displayStartDate = date('d/m/Y', strtotime($startDate));
$displayEndDate = date('d/m/Y', strtotime($endDate));
$thaiYear = $year + 543;

// Get report data based on type
$reportData = [];
$summary = [];
$chartData = [];
$statistics = [];
$analysis = [];

switch ($reportType) {
    case 'daily':
        $reportData = getDailyReport($db, $startDate, $endDate);
        $summary = calculateDailySummary($reportData);
        $chartData = prepareDailyChartData($reportData);
        $statistics = calculateDailyStatistics($reportData);
        $analysis = analyzeDailyData($reportData);
        break;
        
    case 'monthly':
        $reportData = getMonthlyReport($db, $year);
        $summary = calculateMonthlySummary($reportData);
        $chartData = prepareMonthlyChartData($reportData);
        $statistics = calculateMonthlyStatistics($reportData);
        $analysis = analyzeMonthlyData($reportData, $year);
        break;
        
    case 'yearly':
        $reportData = getYearlyReport($db);
        $summary = calculateYearlySummary($reportData);
        $chartData = prepareYearlyChartData($reportData);
        $statistics = calculateYearlyStatistics($reportData);
        $analysis = analyzeYearlyData($reportData);
        break;
        
    case 'comparison':
        $compareYear = isset($_GET['compare_year']) ? (int)$_GET['compare_year'] : ($year - 1);
        $reportData = getComparisonReport($db, $year, $compareYear);
        $summary = calculateComparisonSummary($reportData);
        $chartData = prepareComparisonChartData($reportData, $year, $compareYear);
        $statistics = calculateComparisonStatistics($reportData);
        $analysis = analyzeComparisonData($reportData, $year, $compareYear);
        break;
        
    case 'cost_analysis':
        $reportData = getCostAnalysisReport($db, $startDate, $endDate);
        $summary = calculateCostAnalysisSummary($reportData);
        $chartData = prepareCostAnalysisChartData($reportData);
        $statistics = calculateCostAnalysisStatistics($reportData);
        $analysis = analyzeCostData($reportData);
        break;
        
    case 'forecast':
        $reportData = getForecastReport($db, $year);
        $summary = calculateForecastSummary($reportData);
        $chartData = prepareForecastChartData($reportData);
        $statistics = calculateForecastStatistics($reportData);
        $analysis = analyzeForecastData($reportData);
        break;
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
                                    <option value="yearly" <?php echo $reportType == 'yearly' ? 'selected' : ''; ?>>รายงานรายปี</option>
                                    <option value="comparison" <?php echo $reportType == 'comparison' ? 'selected' : ''; ?>>รายงานเปรียบเทียบ</option>
                                    <option value="cost_analysis" <?php echo $reportType == 'cost_analysis' ? 'selected' : ''; ?>>วิเคราะห์ค่าไฟฟ้า</option>
                                    <option value="forecast" <?php echo $reportType == 'forecast' ? 'selected' : ''; ?>>พยากรณ์แนวโน้ม</option>
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
                        
                        <div class="col-md-2" id="yearDiv">
                            <div class="form-group">
                                <label>ปี</label>
                                <select name="year" class="form-control">
                                    <?php for ($y = date('Y') - 3; $y <= date('Y') + 1; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                        <?php echo $y + 543; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-2" id="compareYearDiv" style="display: none;">
                            <div class="form-group">
                                <label>ปีเปรียบเทียบ</label>
                                <select name="compare_year" class="form-control">
                                    <?php for ($y = date('Y') - 5; $y <= date('Y'); $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($y == $year - 1) ? 'selected' : ''; ?>>
                                        <?php echo $y + 543; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-2" id="compareDiv" style="display: none;">
                            <div class="form-group">
                                <label>เปรียบเทียบกับ</label>
                                <select name="compare_with" class="form-control">
                                    <option value="previous" <?php echo $compareWith == 'previous' ? 'selected' : ''; ?>>ปีก่อนหน้า</option>
                                    <option value="average" <?php echo $compareWith == 'average' ? 'selected' : ''; ?>>ค่าเฉลี่ย</option>
                                    <option value="target" <?php echo $compareWith == 'target' ? 'selected' : ''; ?>>เป้าหมาย</option>
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
                        <h3><?php echo number_format($statistics['total_ee'] ?? 0, 2); ?></h3>
                        <p>หน่วยไฟฟ้ารวม (kWh)</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo number_format($statistics['total_cost'] ?? 0, 2); ?></h3>
                        <p>ค่าไฟฟ้ารวม (บาท)</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo number_format($statistics['avg_cost_per_unit'] ?? 0, 4); ?></h3>
                        <p>ค่าไฟเฉลี่ย/หน่วย</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo number_format($statistics['avg_daily_ee'] ?? 0, 2); ?></h3>
                        <p>ค่าเฉลี่ยรายวัน</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Analysis Cards -->
        <?php if (!empty($analysis)): ?>
        <div class="row">
            <div class="col-lg-4 col-6">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3><?php echo number_format($analysis['growth_rate'] ?? 0, 1); ?>%</h3>
                        <p>อัตราการเติบโต</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-6">
                <div class="small-box bg-secondary">
                    <div class="inner">
                        <h3><?php echo $analysis['peak_month'] ?? '-'; ?></h3>
                        <p>เดือนที่ใช้ไฟฟ้าสูงสุด</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo number_format($analysis['peak_value'] ?? 0, 2); ?></h3>
                        <p>ค่าสูงสุด (kWh)</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-arrow-up"></i>
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
                        <option value="line" <?php echo $chartType == 'line' ? 'selected' : ''; ?>>กราฟเส้น</option>
                        <option value="bar" <?php echo $chartType == 'bar' ? 'selected' : ''; ?>>กราฟแท่ง</option>
                        <option value="area" <?php echo $chartType == 'area' ? 'selected' : ''; ?>>กราฟพื้นที่</option>
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
        
        <!-- Data Table -->
        <?php if (!empty($reportData)): ?>
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
                                <th>หน่วยไฟฟ้า (kWh)</th>
                                <th>ค่าไฟต่อหน่วย</th>
                                <th>ค่าไฟฟ้า (บาท)</th>
                                <th>PE</th>
                                <th>เทียบกับวันก่อน</th>
                                <th>เทียบกับค่าเฉลี่ย</th>
                                <th>ผู้บันทึก</th>
                                
                                <?php elseif ($reportType == 'monthly'): ?>
                                <th>เดือน</th>
                                <th>หน่วยไฟฟ้ารวม</th>
                                <th>ค่าไฟฟ้ารวม</th>
                                <th>ค่าไฟเฉลี่ย/หน่วย</th>
                                <th>จำนวนวัน</th>
                                <th>เฉลี่ยรายวัน</th>
                                <th>สูงสุดรายวัน</th>
                                <th>ต่ำสุดรายวัน</th>
                                
                                <?php elseif ($reportType == 'yearly'): ?>
                                <th>ปี</th>
                                <th>หน่วยไฟฟ้ารวม</th>
                                <th>ค่าไฟฟ้ารวม</th>
                                <th>ค่าไฟเฉลี่ย/หน่วย</th>
                                <th>จำนวนวัน</th>
                                <th>เฉลี่ยรายวัน</th>
                                <th>เปลี่ยนแปลงจากปีก่อน</th>
                                
                                <?php elseif ($reportType == 'comparison'): ?>
                                <th>เดือน</th>
                                <th>ปี <?php echo $year + 543; ?> (kWh)</th>
                                <th>ปี <?php echo ($year - 1) + 543; ?> (kWh)</th>
                                <th>ความต่าง</th>
                                <th>% เปลี่ยนแปลง</th>
                                <th>ปี <?php echo $year + 543; ?> (บาท)</th>
                                <th>ปี <?php echo ($year - 1) + 543; ?> (บาท)</th>
                                
                                <?php elseif ($reportType == 'cost_analysis'): ?>
                                <th>วันที่</th>
                                <th>หน่วยไฟฟ้า</th>
                                <th>ค่าไฟ/หน่วย</th>
                                <th>ค่าไฟรวม</th>
                                <th>สัดส่วนค่าไฟ</th>
                                <th>ประสิทธิภาพ</th>
                                
                                <?php elseif ($reportType == 'forecast'): ?>
                                <th>เดือน</th>
                                <th>ค่าพยากรณ์</th>
                                <th>ช่วงล่าง</th>
                                <th>ช่วงบน</th>
                                <th>ความเชื่อมั่น</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                            <tr>
                                <?php if ($reportType == 'daily'): ?>
                                <td><?php echo date('d/m/Y', strtotime($row['record_date'])); ?></td>
                                <td class="text-right"><?php echo number_format($row['ee_unit'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['cost_per_unit'], 4); ?></td>
                                <td class="text-right"><?php echo number_format($row['total_cost'], 2); ?></td>
                                <td class="text-right"><?php echo $row['pe'] ? number_format($row['pe'], 4) : '-'; ?></td>
                                <td class="text-center">
                                    <?php if (isset($row['prev_ee'])): ?>
                                        <?php 
                                        $change = $row['ee_unit'] - $row['prev_ee'];
                                        $changePercent = $row['prev_ee'] > 0 ? ($change / $row['prev_ee']) * 100 : 0;
                                        ?>
                                        <span class="badge badge-<?php echo $change > 0 ? 'danger' : ($change < 0 ? 'success' : 'secondary'); ?>">
                                            <?php echo $change > 0 ? '+' : ''; ?><?php echo number_format($change, 2); ?> (<?php echo number_format($changePercent, 1); ?>%)
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (isset($row['avg_ee'])): ?>
                                        <?php 
                                        $vsAvg = $row['ee_unit'] - $row['avg_ee'];
                                        $vsAvgPercent = ($vsAvg / $row['avg_ee']) * 100;
                                        ?>
                                        <span class="badge badge-<?php echo $vsAvg > 0 ? 'danger' : ($vsAvg < 0 ? 'success' : 'secondary'); ?>">
                                            <?php echo $vsAvg > 0 ? '+' : ''; ?><?php echo number_format($vsAvg, 2); ?> (<?php echo number_format($vsAvgPercent, 1); ?>%)
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['recorded_by']); ?></td>
                                
                                <?php elseif ($reportType == 'monthly'): ?>
                                <td><?php echo getThaiMonth($row['month']) . ' ' . ($row['year'] + 543); ?></td>
                                <td class="text-right"><?php echo number_format($row['total_ee'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['total_cost'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['avg_cost'], 4); ?></td>
                                <td class="text-right"><?php echo $row['days_count']; ?></td>
                                <td class="text-right"><?php echo number_format($row['avg_daily'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['max_daily'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['min_daily'], 2); ?></td>
                                
                                <?php elseif ($reportType == 'yearly'): ?>
                                <td><?php echo $row['year'] + 543; ?></td>
                                <td class="text-right"><?php echo number_format($row['total_ee'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['total_cost'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['avg_cost'], 4); ?></td>
                                <td class="text-right"><?php echo $row['days_count']; ?></td>
                                <td class="text-right"><?php echo number_format($row['avg_daily'], 2); ?></td>
                                <td class="text-center">
                                    <?php if (isset($row['growth'])): ?>
                                    <span class="badge badge-<?php echo $row['growth'] > 0 ? 'danger' : ($row['growth'] < 0 ? 'success' : 'secondary'); ?>">
                                        <?php echo $row['growth'] > 0 ? '+' : ''; ?><?php echo number_format($row['growth'], 1); ?>%
                                    </span>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                
                                <?php elseif ($reportType == 'comparison'): ?>
                                <td><?php echo getThaiMonth($row['month']); ?></td>
                                <td class="text-right"><?php echo number_format($row['ee_current'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['ee_previous'], 2); ?></td>
                                <td class="text-right">
                                    <?php 
                                    $diff = $row['ee_current'] - $row['ee_previous'];
                                    ?>
                                    <span class="badge badge-<?php echo $diff > 0 ? 'danger' : ($diff < 0 ? 'success' : 'secondary'); ?>">
                                        <?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $row['ee_previous'] > 0 ? ($diff / $row['ee_previous']) * 100 : 0;
                                    ?>
                                    <span class="badge badge-<?php echo $percent > 0 ? 'danger' : ($percent < 0 ? 'success' : 'secondary'); ?>">
                                        <?php echo $percent > 0 ? '+' : ''; ?><?php echo number_format($percent, 1); ?>%
                                    </span>
                                </td>
                                <td class="text-right"><?php echo number_format($row['cost_current'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['cost_previous'], 2); ?></td>
                                
                                <?php elseif ($reportType == 'cost_analysis'): ?>
                                <td><?php echo date('d/m/Y', strtotime($row['record_date'])); ?></td>
                                <td class="text-right"><?php echo number_format($row['ee_unit'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['cost_per_unit'], 4); ?></td>
                                <td class="text-right"><?php echo number_format($row['total_cost'], 2); ?></td>
                                <td class="text-right">
                                    <?php 
                                    $totalCost = array_sum(array_column($reportData, 'total_cost'));
                                    $share = $totalCost > 0 ? ($row['total_cost'] / $totalCost) * 100 : 0;
                                    ?>
                                    <div class="progress progress-xs">
                                        <div class="progress-bar bg-primary" style="width: <?php echo $share; ?>%"></div>
                                    </div>
                                    <?php echo number_format($share, 1); ?>%
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $efficiency = $row['total_cost'] > 0 ? $row['ee_unit'] / $row['total_cost'] : 0;
                                    echo number_format($efficiency, 4);
                                    ?> kWh/บาท
                                </td>
                                
                                <?php elseif ($reportType == 'forecast'): ?>
                                <td><?php echo getThaiMonth($row['month']); ?></td>
                                <td class="text-right"><?php echo number_format($row['forecast'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['lower_bound'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['upper_bound'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['confidence'], 1); ?>%</td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if (!empty($summary) && $reportType == 'daily'): ?>
                        <tfoot>
                            <tr class="bg-gray">
                                <th colspan="1" class="text-right">รวม</th>
                                <th class="text-right"><?php echo number_format($summary['total_ee'], 2); ?></th>
                                <th class="text-right">-</th>
                                <th class="text-right"><?php echo number_format($summary['total_cost'], 2); ?></th>
                                <th colspan="4"></th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
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
    renderChart(<?php echo json_encode($chartData); ?>, '<?php echo $chartType; ?>');
    <?php endif; ?>
});

function toggleReportFields() {
    const type = $('#reportType').val();
    
    // Hide all optional fields first
    $('#dateRangeDiv').hide();
    $('#endDateDiv').hide();
    $('#yearDiv').hide();
    $('#compareYearDiv').hide();
    $('#compareDiv').hide();
    
    if (type === 'daily' || type === 'cost_analysis') {
        $('#dateRangeDiv').show();
        $('#endDateDiv').show();
    } else if (type === 'monthly') {
        $('#yearDiv').show();
    } else if (type === 'yearly') {
        // No additional fields needed
    } else if (type === 'comparison') {
        $('#yearDiv').show();
        $('#compareYearDiv').show();
    } else if (type === 'forecast') {
        $('#yearDiv').show();
        $('#compareDiv').show();
    }
}

function renderChart(data, type) {
    const ctx = document.getElementById('reportChart').getContext('2d');
    
    if (reportChart) {
        reportChart.destroy();
    }
    
    let chartType = type;
    if (type === 'area') {
        chartType = 'line';
    }
    
    let chartConfig = {
        type: chartType,
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
    
    if (type === 'area') {
        chartConfig.options.elements = {
            line: {
                tension: 0.4,
                borderWidth: 2,
                fill: 'origin'
            }
        };
    }
    
    // Add datasets based on data structure
    if (data.datasets) {
        chartConfig.data.datasets = data.datasets;
    } else if (data.ee && data.cost) {
        chartConfig.data = {
            labels: data.labels,
            datasets: [
                {
                    label: 'หน่วยไฟฟ้า (kWh)',
                    data: data.ee,
                    borderColor: '#ffc107',
                    backgroundColor: type === 'area' ? 'rgba(255, 193, 7, 0.2)' : 'rgba(255, 193, 7, 0.1)',
                    yAxisID: 'y',
                    unit: ' kWh',
                    fill: type === 'area'
                },
                {
                    label: 'ค่าไฟฟ้า (บาท)',
                    data: data.cost,
                    borderColor: '#28a745',
                    backgroundColor: type === 'area' ? 'rgba(40, 167, 69, 0.2)' : 'rgba(40, 167, 69, 0.1)',
                    yAxisID: 'y1',
                    unit: ' บาท',
                    fill: type === 'area'
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
                    text: 'หน่วยไฟฟ้า (kWh)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'ค่าไฟฟ้า (บาท)'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        };
    } else if (data.current && data.previous) {
        chartConfig.data = {
            labels: data.labels,
            datasets: [
                {
                    label: 'ปีปัจจุบัน',
                    data: data.current,
                    borderColor: '#007bff',
                    backgroundColor: type === 'area' ? 'rgba(0, 123, 255, 0.2)' : 'rgba(0, 123, 255, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: type === 'area'
                },
                {
                    label: 'ปีเปรียบเทียบ',
                    data: data.previous,
                    borderColor: '#6c757d',
                    backgroundColor: type === 'area' ? 'rgba(108, 117, 125, 0.2)' : 'rgba(108, 117, 125, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: type === 'area'
                }
            ]
        };
    } else {
        chartConfig.data.datasets = [{
            label: data.label || 'ปริมาณ',
            data: data.values || [],
            borderColor: '#007bff',
            backgroundColor: type === 'area' ? 'rgba(0, 123, 255, 0.2)' : 'rgba(0, 123, 255, 0.1)',
            borderWidth: 2,
            tension: 0.4,
            fill: type === 'area'
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

<?php
// Report data fetching functions
function getDailyReport($db, $startDate, $endDate) {
    $sql = "
        SELECT 
            *,
            (SELECT AVG(ee_unit) FROM electricity_summary WHERE record_date BETWEEN ? AND ?) as avg_ee
        FROM electricity_summary
        WHERE record_date BETWEEN ? AND ?
        ORDER BY record_date
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$startDate, $endDate, $startDate, $endDate]);
    $data = $stmt->fetchAll();
    
    // Add previous day comparison
    for ($i = 0; $i < count($data); $i++) {
        if ($i > 0) {
            $data[$i]['prev_ee'] = $data[$i-1]['ee_unit'];
        }
    }
    
    return $data;
}

function calculateDailySummary($data) {
    $totalEE = 0;
    $totalCost = 0;
    $days = count($data);
    
    foreach ($data as $row) {
        $totalEE += $row['ee_unit'];
        $totalCost += $row['total_cost'];
    }
    
    return [
        'total_ee' => $totalEE,
        'total_cost' => $totalCost,
        'avg_daily' => $days > 0 ? $totalEE / $days : 0
    ];
}

function calculateDailyStatistics($data) {
    $totalEE = 0;
    $totalCost = 0;
    $days = count($data);
    $eeValues = array_column($data, 'ee_unit');
    $costValues = array_column($data, 'total_cost');
    
    foreach ($data as $row) {
        $totalEE += $row['ee_unit'];
        $totalCost += $row['total_cost'];
    }
    
    return [
        'total_ee' => $totalEE,
        'total_cost' => $totalCost,
        'avg_cost_per_unit' => $totalEE > 0 ? $totalCost / $totalEE : 0,
        'avg_daily_ee' => $days > 0 ? $totalEE / $days : 0,
        'max_ee' => !empty($eeValues) ? max($eeValues) : 0,
        'min_ee' => !empty($eeValues) ? min($eeValues) : 0
    ];
}

function analyzeDailyData($data) {
    $eeValues = array_column($data, 'ee_unit');
    $dates = array_column($data, 'record_date');
    
    // Find peak day
    $maxEE = !empty($eeValues) ? max($eeValues) : 0;
    $maxIndex = array_search($maxEE, $eeValues);
    $peakDate = $maxIndex !== false ? $dates[$maxIndex] : null;
    
    // Calculate growth rate
    $firstEE = !empty($eeValues) ? $eeValues[0] : 0;
    $lastEE = !empty($eeValues) ? $eeValues[count($eeValues)-1] : 0;
    $growthRate = $firstEE > 0 ? (($lastEE - $firstEE) / $firstEE) * 100 : 0;
    
    return [
        'peak_value' => $maxEE,
        'peak_date' => $peakDate ? date('d/m/Y', strtotime($peakDate)) : '-',
        'growth_rate' => $growthRate,
        'data_points' => count($data)
    ];
}

function prepareDailyChartData($data) {
    $labels = [];
    $ee = [];
    $cost = [];
    
    foreach ($data as $row) {
        $labels[] = date('d/m', strtotime($row['record_date']));
        $ee[] = $row['ee_unit'];
        $cost[] = $row['total_cost'];
    }
    
    return [
        'labels' => $labels,
        'ee' => $ee,
        'cost' => $cost
    ];
}

function getMonthlyReport($db, $year) {
    $sql = "
        SELECT 
            MONTH(record_date) as month,
            YEAR(record_date) as year,
            COUNT(*) as days_count,
            SUM(ee_unit) as total_ee,
            SUM(total_cost) as total_cost,
            AVG(cost_per_unit) as avg_cost,
            AVG(ee_unit) as avg_daily,
            MAX(ee_unit) as max_daily,
            MIN(ee_unit) as min_daily
        FROM electricity_summary
        WHERE YEAR(record_date) = ?
        GROUP BY YEAR(record_date), MONTH(record_date)
        ORDER BY month
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$year]);
    return $stmt->fetchAll();
}

function calculateMonthlySummary($data) {
    $totalEE = 0;
    $totalCost = 0;
    $months = count($data);
    
    foreach ($data as $row) {
        $totalEE += $row['total_ee'];
        $totalCost += $row['total_cost'];
    }
    
    return [
        'total_ee' => $totalEE,
        'total_cost' => $totalCost,
        'avg_daily' => $months > 0 ? $totalEE / $months : 0
    ];
}

function calculateMonthlyStatistics($data) {
    $totalEE = 0;
    $totalCost = 0;
    $months = count($data);
    $monthlyEE = array_column($data, 'total_ee');
    
    foreach ($data as $row) {
        $totalEE += $row['total_ee'];
        $totalCost += $row['total_cost'];
    }
    
    return [
        'total_ee' => $totalEE,
        'total_cost' => $totalCost,
        'avg_cost_per_unit' => $totalEE > 0 ? $totalCost / $totalEE : 0,
        'avg_daily_ee' => $months > 0 ? $totalEE / $months : 0,
        'max_monthly' => !empty($monthlyEE) ? max($monthlyEE) : 0,
        'min_monthly' => !empty($monthlyEE) ? min($monthlyEE) : 0
    ];
}

function analyzeMonthlyData($data, $year) {
    $monthlyEE = array_column($data, 'total_ee');
    $months = array_column($data, 'month');
    
    // Find peak month
    $maxEE = !empty($monthlyEE) ? max($monthlyEE) : 0;
    $maxIndex = array_search($maxEE, $monthlyEE);
    $peakMonth = $maxIndex !== false ? $months[$maxIndex] : null;
    
    // Calculate average
    $avgEE = !empty($monthlyEE) ? array_sum($monthlyEE) / count($monthlyEE) : 0;
    
    // Get previous year data for comparison
    $db = getDB();
    $stmt = $db->prepare("
        SELECT SUM(ee_unit) as total_ee
        FROM electricity_summary
        WHERE YEAR(record_date) = ?
    ");
    $stmt->execute([$year - 1]);
    $prevYear = $stmt->fetch();
    
    $growthRate = $prevYear && $prevYear['total_ee'] > 0 ? 
                  ((array_sum($monthlyEE) - $prevYear['total_ee']) / $prevYear['total_ee']) * 100 : 0;
    
    return [
        'peak_month' => $peakMonth ? getThaiMonth($peakMonth) : '-',
        'peak_value' => $maxEE,
        'avg_monthly' => $avgEE,
        'growth_rate' => $growthRate,
        'total_months' => count($data)
    ];
}

function prepareMonthlyChartData($data) {
    $labels = [];
    $ee = [];
    $cost = [];
    
    foreach ($data as $row) {
        $labels[] = getThaiShortMonth($row['month']);
        $ee[] = $row['total_ee'];
        $cost[] = $row['total_cost'];
    }
    
    return [
        'labels' => $labels,
        'ee' => $ee,
        'cost' => $cost
    ];
}

function getYearlyReport($db) {
    $sql = "
        SELECT 
            YEAR(record_date) as year,
            COUNT(*) as days_count,
            SUM(ee_unit) as total_ee,
            SUM(total_cost) as total_cost,
            AVG(cost_per_unit) as avg_cost,
            AVG(ee_unit) as avg_daily
        FROM electricity_summary
        GROUP BY YEAR(record_date)
        ORDER BY year DESC
    ";
    $stmt = $db->query($sql);
    $data = $stmt->fetchAll();
    
    // Calculate growth for each year
    for ($i = 0; $i < count($data); $i++) {
        if ($i < count($data) - 1) {
            $current = $data[$i]['total_ee'];
            $previous = $data[$i+1]['total_ee'];
            $data[$i]['growth'] = $previous > 0 ? (($current - $previous) / $previous) * 100 : 0;
        }
    }
    
    return $data;
}

function calculateYearlySummary($data) {
    $totalEE = 0;
    $totalCost = 0;
    $years = count($data);
    
    foreach ($data as $row) {
        $totalEE += $row['total_ee'];
        $totalCost += $row['total_cost'];
    }
    
    return [
        'total_ee' => $totalEE,
        'total_cost' => $totalCost,
        'avg_yearly' => $years > 0 ? $totalEE / $years : 0
    ];
}

function calculateYearlyStatistics($data) {
    $totalEE = 0;
    $totalCost = 0;
    $years = count($data);
    
    foreach ($data as $row) {
        $totalEE += $row['total_ee'];
        $totalCost += $row['total_cost'];
    }
    
    return [
        'total_ee' => $totalEE,
        'total_cost' => $totalCost,
        'avg_cost_per_unit' => $totalEE > 0 ? $totalCost / $totalEE : 0,
        'avg_yearly_ee' => $years > 0 ? $totalEE / $years : 0,
        'num_years' => $years
    ];
}

function analyzeYearlyData($data) {
    $yearlyEE = array_column($data, 'total_ee');
    $years = array_column($data, 'year');
    
    // Find peak year
    $maxEE = !empty($yearlyEE) ? max($yearlyEE) : 0;
    $maxIndex = array_search($maxEE, $yearlyEE);
    $peakYear = $maxIndex !== false ? $years[$maxIndex] : null;
    
    // Calculate average growth
    $growthRates = [];
    for ($i = 0; $i < count($data) - 1; $i++) {
        if ($data[$i+1]['total_ee'] > 0) {
            $growth = (($data[$i]['total_ee'] - $data[$i+1]['total_ee']) / $data[$i+1]['total_ee']) * 100;
            $growthRates[] = $growth;
        }
    }
    $avgGrowth = !empty($growthRates) ? array_sum($growthRates) / count($growthRates) : 0;
    
    return [
        'peak_year' => $peakYear ? ($peakYear + 543) : '-',
        'peak_value' => $maxEE,
        'avg_growth' => $avgGrowth,
        'num_years' => count($data),
        'trend' => $avgGrowth > 0 ? 'เพิ่มขึ้น' : ($avgGrowth < 0 ? 'ลดลง' : 'คงที่')
    ];
}

function prepareYearlyChartData($data) {
    $labels = [];
    $values = [];
    
    foreach ($data as $row) {
        $labels[] = $row['year'] + 543;
        $values[] = $row['total_ee'];
    }
    
    return [
        'labels' => $labels,
        'values' => $values,
        'label' => 'หน่วยไฟฟ้ารายปี (kWh)'
    ];
}

function getComparisonReport($db, $year1, $year2) {
    $sql = "
        SELECT 
            COALESCE(m1.month, m2.month) as month,
            COALESCE(m1.total_ee, 0) as ee_current,
            COALESCE(m2.total_ee, 0) as ee_previous,
            COALESCE(m1.total_cost, 0) as cost_current,
            COALESCE(m2.total_cost, 0) as cost_previous
        FROM (
            SELECT MONTH(record_date) as month, SUM(ee_unit) as total_ee, SUM(total_cost) as total_cost
            FROM electricity_summary
            WHERE YEAR(record_date) = ?
            GROUP BY MONTH(record_date)
        ) m1
        LEFT JOIN (
            SELECT MONTH(record_date) as month, SUM(ee_unit) as total_ee, SUM(total_cost) as total_cost
            FROM electricity_summary
            WHERE YEAR(record_date) = ?
            GROUP BY MONTH(record_date)
        ) m2 ON m1.month = m2.month
        
        UNION
        
        SELECT 
            COALESCE(m2.month, m1.month) as month,
            COALESCE(m1.total_ee, 0) as ee_current,
            COALESCE(m2.total_ee, 0) as ee_previous,
            COALESCE(m1.total_cost, 0) as cost_current,
            COALESCE(m2.total_cost, 0) as cost_previous
        FROM (
            SELECT MONTH(record_date) as month, SUM(ee_unit) as total_ee, SUM(total_cost) as total_cost
            FROM electricity_summary
            WHERE YEAR(record_date) = ?
            GROUP BY MONTH(record_date)
        ) m2
        LEFT JOIN (
            SELECT MONTH(record_date) as month, SUM(ee_unit) as total_ee, SUM(total_cost) as total_cost
            FROM electricity_summary
            WHERE YEAR(record_date) = ?
            GROUP BY MONTH(record_date)
        ) m1 ON m2.month = m1.month
        WHERE m1.month IS NULL
        ORDER BY month
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$year1, $year2, $year2, $year1]);
    return $stmt->fetchAll();
}

function calculateComparisonSummary($data) {
    $totalCurrent = 0;
    $totalPrevious = 0;
    
    foreach ($data as $row) {
        $totalCurrent += $row['ee_current'];
        $totalPrevious += $row['ee_previous'];
    }
    
    return [
        'total_ee' => $totalCurrent,
        'total_cost' => 0,
        'avg_daily' => 0
    ];
}

function calculateComparisonStatistics($data) {
    $totalCurrent = 0;
    $totalPrevious = 0;
    $totalCostCurrent = 0;
    $totalCostPrevious = 0;
    
    foreach ($data as $row) {
        $totalCurrent += $row['ee_current'];
        $totalPrevious += $row['ee_previous'];
        $totalCostCurrent += $row['cost_current'];
        $totalCostPrevious += $row['cost_previous'];
    }
    
    $change = $totalCurrent - $totalPrevious;
    $changePercent = $totalPrevious > 0 ? ($change / $totalPrevious) * 100 : 0;
    
    return [
        'total_ee' => $totalCurrent,
        'total_cost' => $totalCostCurrent,
        'avg_cost_per_unit' => $totalCurrent > 0 ? $totalCostCurrent / $totalCurrent : 0,
        'avg_daily_ee' => 0,
        'change' => $change,
        'change_percent' => $changePercent
    ];
}

function analyzeComparisonData($data, $year1, $year2) {
    $monthsWithIncrease = 0;
    $totalIncrease = 0;
    $totalDecrease = 0;
    
    foreach ($data as $row) {
        if ($row['ee_current'] > $row['ee_previous']) {
            $monthsWithIncrease++;
            $totalIncrease += $row['ee_current'] - $row['ee_previous'];
        } else {
            $totalDecrease += $row['ee_previous'] - $row['ee_current'];
        }
    }
    
    return [
        'months_increase' => $monthsWithIncrease,
        'months_decrease' => 12 - $monthsWithIncrease,
        'total_increase' => $totalIncrease,
        'total_decrease' => $totalDecrease,
        'net_change' => $totalIncrease - $totalDecrease,
        'year1' => $year1 + 543,
        'year2' => $year2 + 543
    ];
}

function prepareComparisonChartData($data, $year1, $year2) {
    $labels = [];
    $current = [];
    $previous = [];
    
    foreach ($data as $row) {
        $labels[] = getThaiShortMonth($row['month']);
        $current[] = $row['ee_current'];
        $previous[] = $row['ee_previous'];
    }
    
    return [
        'labels' => $labels,
        'current' => $current,
        'previous' => $previous
    ];
}

function getCostAnalysisReport($db, $startDate, $endDate) {
    $sql = "
        SELECT *
        FROM electricity_summary
        WHERE record_date BETWEEN ? AND ?
        ORDER BY record_date
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

function calculateCostAnalysisSummary($data) {
    $totalEE = 0;
    $totalCost = 0;
    
    foreach ($data as $row) {
        $totalEE += $row['ee_unit'];
        $totalCost += $row['total_cost'];
    }
    
    return [
        'total_ee' => $totalEE,
        'total_cost' => $totalCost,
        'avg_daily' => count($data) > 0 ? $totalEE / count($data) : 0
    ];
}

function calculateCostAnalysisStatistics($data) {
    $totalEE = 0;
    $totalCost = 0;
    
    foreach ($data as $row) {
        $totalEE += $row['ee_unit'];
        $totalCost += $row['total_cost'];
    }
    
    return [
        'total_ee' => $totalEE,
        'total_cost' => $totalCost,
        'avg_cost_per_unit' => $totalEE > 0 ? $totalCost / $totalEE : 0,
        'avg_daily_ee' => count($data) > 0 ? $totalEE / count($data) : 0
    ];
}

function analyzeCostData($data) {
    $costPerUnit = [];
    foreach ($data as $row) {
        $costPerUnit[] = $row['cost_per_unit'];
    }
    
    $avgCost = !empty($costPerUnit) ? array_sum($costPerUnit) / count($costPerUnit) : 0;
    $minCost = !empty($costPerUnit) ? min($costPerUnit) : 0;
    $maxCost = !empty($costPerUnit) ? max($costPerUnit) : 0;
    
    return [
        'avg_cost_per_unit' => $avgCost,
        'min_cost_per_unit' => $minCost,
        'max_cost_per_unit' => $maxCost,
        'cost_variance' => $maxCost - $minCost,
        'cost_efficiency' => $avgCost > 0 ? (array_sum(array_column($data, 'ee_unit')) / array_sum(array_column($data, 'total_cost'))) : 0
    ];
}

function prepareCostAnalysisChartData($data) {
    $labels = [];
    $costPerUnit = [];
    
    foreach ($data as $row) {
        $labels[] = date('d/m', strtotime($row['record_date']));
        $costPerUnit[] = $row['cost_per_unit'];
    }
    
    return [
        'labels' => $labels,
        'values' => $costPerUnit,
        'label' => 'ค่าไฟต่อหน่วย (บาท)'
    ];
}

function getForecastReport($db, $year) {
    // Simple linear regression forecast
    // Get last 3 years data
    $sql = "
        SELECT 
            MONTH(record_date) as month,
            YEAR(record_date) as year,
            AVG(ee_unit) as avg_ee
        FROM electricity_summary
        WHERE YEAR(record_date) BETWEEN ? AND ?
        GROUP BY YEAR(record_date), MONTH(record_date)
        ORDER BY year, month
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$year - 2, $year - 1]);
    $historical = $stmt->fetchAll();
    
    // Group by month
    $monthlyData = [];
    foreach ($historical as $row) {
        if (!isset($monthlyData[$row['month']])) {
            $monthlyData[$row['month']] = [];
        }
        $monthlyData[$row['month']][] = $row['avg_ee'];
    }
    
    // Calculate forecast for each month
    $forecast = [];
    for ($m = 1; $m <= 12; $m++) {
        if (isset($monthlyData[$m]) && count($monthlyData[$m]) > 0) {
            $values = $monthlyData[$m];
            $avg = array_sum($values) / count($values);
            $std = count($values) > 1 ? stats_standard_deviation($values) : $avg * 0.1;
            
            $forecast[] = [
                'month' => $m,
                'forecast' => $avg,
                'lower_bound' => $avg - (1.96 * $std),
                'upper_bound' => $avg + (1.96 * $std),
                'confidence' => 95
            ];
        } else {
            $forecast[] = [
                'month' => $m,
                'forecast' => 0,
                'lower_bound' => 0,
                'upper_bound' => 0,
                'confidence' => 0
            ];
        }
    }
    
    return $forecast;
}

function stats_standard_deviation($array) {
    $n = count($array);
    if ($n === 0) return 0;
    $mean = array_sum($array) / $n;
    $carry = 0.0;
    foreach ($array as $val) {
        $carry += pow($val - $mean, 2);
    }
    return sqrt($carry / ($n - 1));
}

function calculateForecastSummary($data) {
    $totalForecast = 0;
    foreach ($data as $row) {
        $totalForecast += $row['forecast'];
    }
    
    return [
        'total_ee' => $totalForecast,
        'total_cost' => 0,
        'avg_daily' => $totalForecast / 365
    ];
}

function calculateForecastStatistics($data) {
    $forecastValues = array_column($data, 'forecast');
    
    return [
        'total_ee' => array_sum($forecastValues),
        'total_cost' => 0,
        'avg_cost_per_unit' => 0,
        'avg_daily_ee' => array_sum($forecastValues) / 365,
        'max_monthly' => !empty($forecastValues) ? max($forecastValues) : 0,
        'min_monthly' => !empty($forecastValues) ? min($forecastValues) : 0
    ];
}

function analyzeForecastData($data) {
    $forecastValues = array_column($data, 'forecast');
    $lowerBounds = array_column($data, 'lower_bound');
    $upperBounds = array_column($data, 'upper_bound');
    
    $totalRange = 0;
    for ($i = 0; $i < count($data); $i++) {
        $totalRange += $upperBounds[$i] - $lowerBounds[$i];
    }
    
    return [
        'total_forecast' => array_sum($forecastValues),
        'avg_range' => count($data) > 0 ? $totalRange / count($data) : 0,
        'peak_month' => array_search(max($forecastValues), $forecastValues) + 1,
        'low_month' => array_search(min($forecastValues), $forecastValues) + 1,
        'confidence_months' => count(array_filter(array_column($data, 'confidence'), function($c) { return $c > 0; }))
    ];
}

function prepareForecastChartData($data) {
    $labels = [];
    $forecast = [];
    $lower = [];
    $upper = [];
    
    foreach ($data as $row) {
        $labels[] = getThaiShortMonth($row['month']);
        $forecast[] = $row['forecast'];
        $lower[] = $row['lower_bound'];
        $upper[] = $row['upper_bound'];
    }
    
    return [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'ค่าพยากรณ์',
                'data' => $forecast,
                'borderColor' => '#007bff',
                'backgroundColor' => 'rgba(0, 123, 255, 0.1)',
                'borderWidth' => 2,
                'tension' => 0.4,
                'fill' => false
            ],
            [
                'label' => 'ช่วงความเชื่อมั่นบน',
                'data' => $upper,
                'borderColor' => 'rgba(40, 167, 69, 0.3)',
                'borderWidth' => 1,
                'borderDash' => [5, 5],
                'fill' => '+1',
                'backgroundColor' => 'rgba(40, 167, 69, 0.1)',
                'pointRadius' => 0
            ],
            [
                'label' => 'ช่วงความเชื่อมั่นล่าง',
                'data' => $lower,
                'borderColor' => 'rgba(40, 167, 69, 0.3)',
                'borderWidth' => 1,
                'borderDash' => [5, 5],
                'fill' => false,
                'pointRadius' => 0
            ]
        ]
    ];
}
?>
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
    }
}
</style>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>