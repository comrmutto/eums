<?php
/**
 * Energy & Water Module - Reports Page
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
$pageTitle = 'Energy & Water - รายงาน';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Energy & Water', 'link' => 'index.php'],
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
$meterId = isset($_GET['meter_id']) ? (int)$_GET['meter_id'] : 0;
$meterType = isset($_GET['meter_type']) ? $_GET['meter_type'] : '';
$compareWith = isset($_GET['compare_with']) ? $_GET['compare_with'] : 'previous';
$groupBy = isset($_GET['group_by']) ? $_GET['group_by'] : 'day';

// Format dates for display
$displayStartDate = date('d/m/Y', strtotime($startDate));
$displayEndDate = date('d/m/Y', strtotime($endDate));

// Get all meters for dropdown
$stmt = $db->query("
    SELECT * FROM mc_mdb_water 
    WHERE status = 1 
    ORDER BY meter_type, meter_code
");
$meters = $stmt->fetchAll();

// Get report data based on type
$reportData = [];
$summary = [];
$chartData = [];
$statistics = [];

switch ($reportType) {
    case 'daily':
        $reportData = getDailyReport($db, $startDate, $endDate, $meterId, $meterType);
        $summary = calculateDailySummary($reportData);
        $chartData = prepareDailyChartData($reportData);
        $statistics = calculateDailyStatistics($reportData);
        break;
        
    case 'monthly':
        $reportData = getMonthlyReport($db, $startDate, $endDate, $meterId, $meterType);
        $summary = calculateMonthlySummary($reportData);
        $chartData = prepareMonthlyChartData($reportData);
        $statistics = calculateMonthlyStatistics($reportData);
        break;
        
    case 'comparison':
        $reportData = getComparisonReport($db, $startDate, $endDate, $meterId, $meterType, $compareWith);
        $summary = calculateComparisonSummary($reportData);
        $chartData = prepareComparisonChartData($reportData);
        $statistics = calculateComparisonStatistics($reportData);
        break;
        
    case 'meter_detail':
        $reportData = getMeterDetailReport($db, $startDate, $endDate, $meterId);
        $summary = calculateMeterDetailSummary($reportData);
        $chartData = prepareMeterDetailChartData($reportData);
        $statistics = calculateMeterDetailStatistics($reportData);
        break;
        
    case 'summary':
        $reportData = getSummaryReport($db, $startDate, $endDate);
        $summary = calculateSummaryReportStats($reportData);
        $chartData = prepareSummaryChartData($reportData);
        $statistics = calculateSummaryStatistics($reportData);
        break;
}

// Get meter name if selected
$meterName = '';
if ($meterId > 0) {
    foreach ($meters as $m) {
        if ($m['id'] == $meterId) {
            $meterName = $m['meter_name'];
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
                                    <option value="comparison" <?php echo $reportType == 'comparison' ? 'selected' : ''; ?>>รายงานเปรียบเทียบ</option>
                                    <option value="meter_detail" <?php echo $reportType == 'meter_detail' ? 'selected' : ''; ?>>รายงานแยกรายมิเตอร์</option>
                                    <option value="summary" <?php echo $reportType == 'summary' ? 'selected' : ''; ?>>รายงานสรุป</option>
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
                        
                        <div class="col-md-2" id="meterDiv">
                            <div class="form-group">
                                <label>มิเตอร์</label>
                                <select name="meter_id" class="form-control select2">
                                    <option value="0">ทั้งหมด</option>
                                    <?php foreach ($meters as $meter): ?>
                                        <option value="<?php echo $meter['id']; ?>" 
                                                data-type="<?php echo $meter['meter_type']; ?>"
                                                <?php echo $meterId == $meter['id'] ? 'selected' : ''; ?>>
                                            <?php echo $meter['meter_code'] . ' - ' . $meter['meter_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-2" id="meterTypeDiv">
                            <div class="form-group">
                                <label>ประเภทมิเตอร์</label>
                                <select name="meter_type" class="form-control">
                                    <option value="">ทั้งหมด</option>
                                    <option value="electricity" <?php echo $meterType == 'electricity' ? 'selected' : ''; ?>>มิเตอร์ไฟฟ้า</option>
                                    <option value="water" <?php echo $meterType == 'water' ? 'selected' : ''; ?>>มิเตอร์น้ำ</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-2" id="compareDiv" style="display: none;">
                            <div class="form-group">
                                <label>เปรียบเทียบกับ</label>
                                <select name="compare_with" class="form-control">
                                    <option value="previous" <?php echo $compareWith == 'previous' ? 'selected' : ''; ?>>ช่วงก่อนหน้า</option>
                                    <option value="last_year" <?php echo $compareWith == 'last_year' ? 'selected' : ''; ?>>ปีที่แล้ว</option>
                                    <option value="average" <?php echo $compareWith == 'average' ? 'selected' : ''; ?>>ค่าเฉลี่ย</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-2" id="groupByDiv">
                            <div class="form-group">
                                <label>จัดกลุ่มตาม</label>
                                <select name="group_by" class="form-control">
                                    <option value="day" <?php echo $groupBy == 'day' ? 'selected' : ''; ?>>รายวัน</option>
                                    <option value="week" <?php echo $groupBy == 'week' ? 'selected' : ''; ?>>รายสัปดาห์</option>
                                    <option value="month" <?php echo $groupBy == 'month' ? 'selected' : ''; ?>>รายเดือน</option>
                                    <option value="meter" <?php echo $groupBy == 'meter' ? 'selected' : ''; ?>>แยกตามมิเตอร์</option>
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
                        <h3><?php echo number_format($statistics['total_electricity'] ?? 0, 2); ?></h3>
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
                        <h3><?php echo number_format($statistics['total_water'] ?? 0, 2); ?></h3>
                        <p>ปริมาณน้ำรวม (m³)</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-water"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo number_format($statistics['avg_daily_electricity'] ?? 0, 2); ?></h3>
                        <p>ค่าเฉลี่ยไฟฟ้าต่อวัน</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo number_format($statistics['avg_daily_water'] ?? 0, 2); ?></h3>
                        <p>ค่าเฉลี่ยน้ำต่อวัน</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-bar"></i>
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
                        <h3><?php echo number_format($summary['total_usage'] ?? 0, 2); ?></h3>
                        <p>ปริมาณการใช้รวม</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo number_format($summary['avg_daily'] ?? 0, 2); ?></h3>
                        <p>ค่าเฉลี่ยต่อวัน</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-calculator"></i>
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
                        <option value="stacked">กราฟแท่งซ้อน</option>
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
                <div class="card-tools">
                    <button type="button" class="btn btn-default btn-sm" onclick="toggleZeroValues()">
                        <i class="fas fa-eye-slash"></i> ซ่อนค่า 0
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped dataTable" id="reportTable">
                        <thead>
                            <tr>
                                <?php if ($reportType == 'daily'): ?>
                                <th>วันที่</th>
                                <th>มิเตอร์</th>
                                <th>ประเภท</th>
                                <th>ค่าเช้า</th>
                                <th>ค่าเย็น</th>
                                <th>ปริมาณการใช้</th>
                                <th>หน่วย</th>
                                <th>ผู้บันทึก</th>
                                
                                <?php elseif ($reportType == 'monthly'): ?>
                                <th>เดือน</th>
                                <th>มิเตอร์ไฟฟ้า (kWh)</th>
                                <th>มิเตอร์น้ำ (m³)</th>
                                <th>จำนวนวัน</th>
                                <th>ค่าเฉลี่ยไฟฟ้า</th>
                                <th>ค่าเฉลี่ยน้ำ</th>
                                <th>สูงสุดไฟฟ้า</th>
                                <th>สูงสุดน้ำ</th>
                                
                                <?php elseif ($reportType == 'comparison'): ?>
                                <th>ช่วงเวลา</th>
                                <th>ไฟฟ้า (kWh)</th>
                                <th>น้ำ (m³)</th>
                                <th>จำนวนบันทึก</th>
                                <th>เปลี่ยนแปลงไฟฟ้า</th>
                                <th>เปลี่ยนแปลงน้ำ</th>
                                
                                <?php elseif ($reportType == 'meter_detail'): ?>
                                <th>วันที่</th>
                                <th>ค่าเช้า</th>
                                <th>ค่าเย็น</th>
                                <th>ปริมาณการใช้</th>
                                <th>เปรียบเทียบกับวันก่อน</th>
                                <th>เทียบกับค่าเฉลี่ย</th>
                                
                                <?php elseif ($reportType == 'summary'): ?>
                                <th>วันที่</th>
                                <th>ไฟฟ้ารวม (kWh)</th>
                                <th>น้ำรวม (m³)</th>
                                <th>จำนวนมิเตอร์ไฟฟ้า</th>
                                <th>จำนวนมิเตอร์น้ำ</th>
                                <th>สัดส่วนไฟฟ้า/น้ำ</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                            <tr class="<?php echo (isset($row['usage_amount']) && $row['usage_amount'] == 0) ? 'zero-value' : ''; ?>">
                                <?php if ($reportType == 'daily'): ?>
                                <td><?php echo date('d/m/Y', strtotime($row['record_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['meter_name']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $row['meter_type'] == 'electricity' ? 'warning' : 'info'; ?>">
                                        <?php echo $row['meter_type'] == 'electricity' ? 'ไฟฟ้า' : 'น้ำ'; ?>
                                    </span>
                                </td>
                                <td class="text-right"><?php echo number_format($row['morning_reading'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['evening_reading'], 2); ?></td>
                                <td class="text-right"><strong><?php echo number_format($row['usage_amount'], 2); ?></strong></td>
                                <td><?php echo $row['meter_type'] == 'electricity' ? 'kWh' : 'm³'; ?></td>
                                <td><?php echo htmlspecialchars($row['recorded_by']); ?></td>
                                
                                <?php elseif ($reportType == 'monthly'): ?>
                                <td><?php echo getThaiMonth($row['month']) . ' ' . ($row['year'] + 543); ?></td>
                                <td class="text-right"><?php echo number_format($row['total_electricity'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['total_water'], 2); ?></td>
                                <td class="text-right"><?php echo $row['days_count']; ?></td>
                                <td class="text-right"><?php echo number_format($row['avg_electricity'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['avg_water'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['max_electricity'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['max_water'], 2); ?></td>
                                
                                <?php elseif ($reportType == 'comparison'): ?>
                                <td><?php echo $row['period']; ?></td>
                                <td class="text-right"><?php echo number_format($row['electricity'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['water'], 2); ?></td>
                                <td class="text-right"><?php echo $row['records']; ?></td>
                                <td class="text-right">
                                    <?php if (isset($row['electricity_change'])): ?>
                                    <span class="badge badge-<?php echo $row['electricity_change'] > 0 ? 'danger' : ($row['electricity_change'] < 0 ? 'success' : 'secondary'); ?>">
                                        <?php echo $row['electricity_change'] > 0 ? '+' : ''; ?><?php echo number_format($row['electricity_change'], 2); ?> (<?php echo number_format($row['electricity_change_percent'], 1); ?>%)
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <?php if (isset($row['water_change'])): ?>
                                    <span class="badge badge-<?php echo $row['water_change'] > 0 ? 'danger' : ($row['water_change'] < 0 ? 'success' : 'secondary'); ?>">
                                        <?php echo $row['water_change'] > 0 ? '+' : ''; ?><?php echo number_format($row['water_change'], 2); ?> (<?php echo number_format($row['water_change_percent'], 1); ?>%)
                                    </span>
                                    <?php endif; ?>
                                </td>
                                
                                <?php elseif ($reportType == 'meter_detail'): ?>
                                <td><?php echo date('d/m/Y', strtotime($row['record_date'])); ?></td>
                                <td class="text-right"><?php echo number_format($row['morning_reading'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['evening_reading'], 2); ?></td>
                                <td class="text-right"><strong><?php echo number_format($row['usage_amount'], 2); ?></strong></td>
                                <td class="text-center">
                                    <?php if (isset($row['prev_usage'])): ?>
                                        <?php 
                                        $change = $row['usage_amount'] - $row['prev_usage'];
                                        $changePercent = $row['prev_usage'] > 0 ? ($change / $row['prev_usage']) * 100 : 0;
                                        ?>
                                        <span class="badge badge-<?php echo $change > 0 ? 'danger' : ($change < 0 ? 'success' : 'secondary'); ?>">
                                            <?php echo $change > 0 ? '+' : ''; ?><?php echo number_format($change, 2); ?> (<?php echo number_format($changePercent, 1); ?>%)
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (isset($row['avg_usage']) && $row['avg_usage'] > 0): ?>
                                        <?php 
                                        $vsAvg = $row['usage_amount'] - $row['avg_usage'];
                                        $vsAvgPercent = ($vsAvg / $row['avg_usage']) * 100;
                                        ?>
                                        <span class="badge badge-<?php echo $vsAvg > 0 ? 'danger' : ($vsAvg < 0 ? 'success' : 'secondary'); ?>">
                                            <?php echo $vsAvg > 0 ? '+' : ''; ?><?php echo number_format($vsAvg, 2); ?> (<?php echo number_format($vsAvgPercent, 1); ?>%)
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                
                                <?php elseif ($reportType == 'summary'): ?>
                                <td><?php echo date('d/m/Y', strtotime($row['record_date'])); ?></td>
                                <td class="text-right"><?php echo number_format($row['total_electricity'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['total_water'], 2); ?></td>
                                <td class="text-right"><?php echo $row['electricity_meters']; ?></td>
                                <td class="text-right"><?php echo $row['water_meters']; ?></td>
                                <td class="text-right">
                                    <?php if ($row['total_water'] > 0): ?>
                                        <?php echo number_format($row['total_electricity'] / $row['total_water'], 2); ?>
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
                                <th colspan="5" class="text-right">รวม</th>
                                <th class="text-right"><?php echo number_format($summary['total_usage'], 2); ?></th>
                                <th colspan="2"></th>
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
    
    // Initialize select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });
    
    // Initialize DataTable
    $('#reportTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.10.21/i18n/Thai.json'
        },
        pageLength: 25,
        order: [[0, 'desc']]
    });
    
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
    $('#compareDiv').hide();
    $('#groupByDiv').show();
    $('#meterDiv').show();
    $('#meterTypeDiv').show();
    
    if (type === 'comparison') {
        $('#compareDiv').show();
        $('#groupByDiv').hide();
    } else if (type === 'meter_detail') {
        $('#meterDiv').show();
        $('#meterTypeDiv').hide();
        $('#groupByDiv').hide();
    } else if (type === 'summary') {
        $('#meterDiv').hide();
        $('#meterTypeDiv').hide();
        $('#groupByDiv').show();
    }
}

function renderChart(data, type) {
    const ctx = document.getElementById('reportChart').getContext('2d');
    
    if (reportChart) {
        reportChart.destroy();
    }
    
    let chartConfig = {
        type: type === 'stacked' ? 'bar' : type,
        data: {
            labels: data.labels,
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
    };
    
    if (type === 'stacked') {
        chartConfig.options.scales = {
            x: {
                stacked: true
            },
            y: {
                stacked: true,
                beginAtZero: true
            }
        };
    }
    
    // Add datasets
    if (data.datasets) {
        chartConfig.data.datasets = data.datasets;
    } else if (data.electricity && data.water) {
        chartConfig.data.datasets = [
            {
                label: 'ไฟฟ้า (kWh)',
                data: data.electricity,
                borderColor: '#ffc107',
                backgroundColor: type === 'line' ? 'rgba(255, 193, 7, 0.1)' : 'rgba(255, 193, 7, 0.5)',
                borderWidth: 2,
                tension: 0.4,
                fill: type === 'line',
                unit: ' kWh'
            },
            {
                label: 'น้ำ (m³)',
                data: data.water,
                borderColor: '#17a2b8',
                backgroundColor: type === 'line' ? 'rgba(23, 162, 184, 0.1)' : 'rgba(23, 162, 184, 0.5)',
                borderWidth: 2,
                tension: 0.4,
                fill: type === 'line',
                unit: ' m³'
            }
        ];
    } else {
        chartConfig.data.datasets = [{
            label: data.label || 'ปริมาณการใช้',
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

function toggleZeroValues() {
    $('.zero-value').toggle();
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
function getDailyReport($db, $startDate, $endDate, $meterId, $meterType) {
    $sql = "
        SELECT 
            r.*,
            m.meter_name,
            m.meter_code,
            m.meter_type
        FROM meter_daily_readings r
        JOIN mc_mdb_water m ON r.meter_id = m.id
        WHERE r.record_date BETWEEN ? AND ?
    ";
    $params = [$startDate, $endDate];
    
    if ($meterId > 0) {
        $sql .= " AND r.meter_id = ?";
        $params[] = $meterId;
    }
    
    if (!empty($meterType)) {
        $sql .= " AND m.meter_type = ?";
        $params[] = $meterType;
    }
    
    $sql .= " ORDER BY r.record_date DESC, m.meter_type, m.meter_code";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function calculateDailySummary($data) {
    $total = 0;
    $days = [];
    
    foreach ($data as $row) {
        $total += $row['usage_amount'];
        $days[$row['record_date']] = true;
    }
    
    return [
        'total_days' => count($days),
        'total_records' => count($data),
        'total_usage' => $total,
        'avg_daily' => count($days) > 0 ? $total / count($days) : 0
    ];
}

function calculateDailyStatistics($data) {
    $electricity = 0;
    $water = 0;
    $elecDays = [];
    $waterDays = [];
    
    foreach ($data as $row) {
        if ($row['meter_type'] == 'electricity') {
            $electricity += $row['usage_amount'];
            $elecDays[$row['record_date']] = true;
        } else {
            $water += $row['usage_amount'];
            $waterDays[$row['record_date']] = true;
        }
    }
    
    return [
        'total_electricity' => $electricity,
        'total_water' => $water,
        'avg_daily_electricity' => count($elecDays) > 0 ? $electricity / count($elecDays) : 0,
        'avg_daily_water' => count($waterDays) > 0 ? $water / count($waterDays) : 0
    ];
}

function prepareDailyChartData($data) {
    $grouped = [];
    $electricity = [];
    $water = [];
    
    foreach ($data as $row) {
        $date = $row['record_date'];
        if (!isset($grouped[$date])) {
            $grouped[$date] = ['electricity' => 0, 'water' => 0];
        }
        if ($row['meter_type'] == 'electricity') {
            $grouped[$date]['electricity'] += $row['usage_amount'];
        } else {
            $grouped[$date]['water'] += $row['usage_amount'];
        }
    }
    
    ksort($grouped);
    $labels = array_map(function($d) { return date('d/m', strtotime($d)); }, array_keys($grouped));
    
    foreach ($grouped as $day) {
        $electricity[] = $day['electricity'];
        $water[] = $day['water'];
    }
    
    return [
        'labels' => $labels,
        'electricity' => $electricity,
        'water' => $water
    ];
}

function getMonthlyReport($db, $startDate, $endDate, $meterId, $meterType) {
    $sql = "
        SELECT 
            YEAR(r.record_date) as year,
            MONTH(r.record_date) as month,
            COUNT(DISTINCT r.record_date) as days_count,
            COUNT(r.id) as total_records,
            SUM(CASE WHEN m.meter_type = 'electricity' THEN r.usage_amount ELSE 0 END) as total_electricity,
            SUM(CASE WHEN m.meter_type = 'water' THEN r.usage_amount ELSE 0 END) as total_water,
            AVG(CASE WHEN m.meter_type = 'electricity' THEN r.usage_amount ELSE NULL END) as avg_electricity,
            AVG(CASE WHEN m.meter_type = 'water' THEN r.usage_amount ELSE NULL END) as avg_water,
            MAX(CASE WHEN m.meter_type = 'electricity' THEN r.usage_amount ELSE 0 END) as max_electricity,
            MAX(CASE WHEN m.meter_type = 'water' THEN r.usage_amount ELSE 0 END) as max_water
        FROM meter_daily_readings r
        JOIN mc_mdb_water m ON r.meter_id = m.id
        WHERE r.record_date BETWEEN ? AND ?
    ";
    $params = [$startDate, $endDate];
    
    if ($meterId > 0) {
        $sql .= " AND r.meter_id = ?";
        $params[] = $meterId;
    }
    
    if (!empty($meterType)) {
        $sql .= " AND m.meter_type = ?";
        $params[] = $meterType;
    }
    
    $sql .= " GROUP BY YEAR(r.record_date), MONTH(r.record_date) ORDER BY year, month";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function calculateMonthlySummary($data) {
    $totalElec = 0;
    $totalWater = 0;
    $months = count($data);
    
    foreach ($data as $row) {
        $totalElec += $row['total_electricity'];
        $totalWater += $row['total_water'];
    }
    
    return [
        'total_days' => $months,
        'total_records' => count($data),
        'total_usage' => $totalElec + $totalWater,
        'avg_daily' => $months > 0 ? ($totalElec + $totalWater) / $months : 0
    ];
}

function calculateMonthlyStatistics($data) {
    $totalElec = 0;
    $totalWater = 0;
    $months = count($data);
    
    foreach ($data as $row) {
        $totalElec += $row['total_electricity'];
        $totalWater += $row['total_water'];
    }
    
    return [
        'total_electricity' => $totalElec,
        'total_water' => $totalWater,
        'avg_daily_electricity' => $months > 0 ? $totalElec / $months : 0,
        'avg_daily_water' => $months > 0 ? $totalWater / $months : 0
    ];
}

function prepareMonthlyChartData($data) {
    $labels = [];
    $electricity = [];
    $water = [];
    
    foreach ($data as $row) {
        $labels[] = getThaiShortMonth($row['month']) . ' ' . ($row['year'] + 543);
        $electricity[] = $row['total_electricity'];
        $water[] = $row['total_water'];
    }
    
    return [
        'labels' => $labels,
        'electricity' => $electricity,
        'water' => $water
    ];
}

function getComparisonReport($db, $startDate, $endDate, $meterId, $meterType, $compareWith) {
    $dateDiff = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24);
    
    if ($compareWith == 'previous') {
        $prevStart = date('Y-m-d', strtotime("-$dateDiff days", strtotime($startDate)));
        $prevEnd = date('Y-m-d', strtotime("-1 day", strtotime($startDate)));
    } elseif ($compareWith == 'last_year') {
        $prevStart = date('Y-m-d', strtotime("-1 year", strtotime($startDate)));
        $prevEnd = date('Y-m-d', strtotime("-1 year", strtotime($endDate)));
    } else {
        return getAverageComparison($db, $startDate, $endDate, $meterId, $meterType);
    }
    
    $current = getPeriodSummary($db, $startDate, $endDate, $meterId, $meterType);
    $previous = getPeriodSummary($db, $prevStart, $prevEnd, $meterId, $meterType);
    
    $elecChange = $current['electricity'] - $previous['electricity'];
    $waterChange = $current['water'] - $previous['water'];
    $elecChangePercent = $previous['electricity'] > 0 ? ($elecChange / $previous['electricity']) * 100 : 0;
    $waterChangePercent = $previous['water'] > 0 ? ($waterChange / $previous['water']) * 100 : 0;
    
    return [
        [
            'period' => 'ช่วงเวลาปัจจุบัน',
            'electricity' => $current['electricity'],
            'water' => $current['water'],
            'records' => $current['records'],
            'electricity_change' => null,
            'water_change' => null,
            'electricity_change_percent' => null,
            'water_change_percent' => null
        ],
        [
            'period' => 'ช่วงเวลาเปรียบเทียบ',
            'electricity' => $previous['electricity'],
            'water' => $previous['water'],
            'records' => $previous['records'],
            'electricity_change' => -$elecChange,
            'water_change' => -$waterChange,
            'electricity_change_percent' => -$elecChangePercent,
            'water_change_percent' => -$waterChangePercent
        ]
    ];
}

function getPeriodSummary($db, $startDate, $endDate, $meterId, $meterType) {
    $sql = "
        SELECT 
            COUNT(DISTINCT r.record_date) as days,
            COUNT(r.id) as records,
            SUM(CASE WHEN m.meter_type = 'electricity' THEN r.usage_amount ELSE 0 END) as electricity,
            SUM(CASE WHEN m.meter_type = 'water' THEN r.usage_amount ELSE 0 END) as water
        FROM meter_daily_readings r
        JOIN mc_mdb_water m ON r.meter_id = m.id
        WHERE r.record_date BETWEEN ? AND ?
    ";
    $params = [$startDate, $endDate];
    
    if ($meterId > 0) {
        $sql .= " AND r.meter_id = ?";
        $params[] = $meterId;
    }
    
    if (!empty($meterType)) {
        $sql .= " AND m.meter_type = ?";
        $params[] = $meterType;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

function getAverageComparison($db, $startDate, $endDate, $meterId, $meterType) {
    // Implementation for average comparison
    return [];
}

function calculateComparisonSummary($data) {
    return [
        'total_days' => 2,
        'total_records' => ($data[0]['records'] ?? 0) + ($data[1]['records'] ?? 0),
        'total_usage' => ($data[0]['electricity'] ?? 0) + ($data[0]['water'] ?? 0) + 
                        ($data[1]['electricity'] ?? 0) + ($data[1]['water'] ?? 0),
        'avg_daily' => (($data[0]['electricity'] ?? 0) + ($data[0]['water'] ?? 0)) / 2
    ];
}

function calculateComparisonStatistics($data) {
    return [
        'total_electricity' => ($data[0]['electricity'] ?? 0) + ($data[1]['electricity'] ?? 0),
        'total_water' => ($data[0]['water'] ?? 0) + ($data[1]['water'] ?? 0),
        'avg_daily_electricity' => ($data[0]['electricity'] ?? 0) / 2,
        'avg_daily_water' => ($data[0]['water'] ?? 0) / 2
    ];
}

function prepareComparisonChartData($data) {
    return [
        'labels' => [$data[0]['period'], $data[1]['period']],
        'datasets' => [
            [
                'label' => 'ไฟฟ้า (kWh)',
                'data' => [$data[0]['electricity'], $data[1]['electricity']],
                'backgroundColor' => ['#ffc107', '#ffc107'],
                'unit' => ' kWh',
            ],
            [
                'label' => 'น้ำ (m³)',
                'data' => [$data[0]['water'], $data[1]['water']],
                'backgroundColor' => ['#17a2b8', '#17a2b8'],
                'unit' => ' m³'
            ]
        ]
    ];
}

function getMeterDetailReport($db, $startDate, $endDate, $meterId) {
    if ($meterId <= 0) return [];
    
    // Get meter type
    $stmt = $db->prepare("SELECT meter_type FROM mc_mdb_water WHERE id = ?");
    $stmt->execute([$meterId]);
    $meterType = $stmt->fetchColumn();
    
    // Get readings
    $sql = "
        SELECT 
            r.*,
            (SELECT AVG(usage_amount) FROM meter_daily_readings WHERE meter_id = ?) as avg_usage
        FROM meter_daily_readings r
        WHERE r.meter_id = ? AND r.record_date BETWEEN ? AND ?
        ORDER BY r.record_date
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$meterId, $meterId, $startDate, $endDate]);
    $readings = $stmt->fetchAll();
    
    // Add previous day comparison
    for ($i = 0; $i < count($readings); $i++) {
        if ($i > 0) {
            $readings[$i]['prev_usage'] = $readings[$i-1]['usage_amount'];
        }
    }
    
    return $readings;
}

function calculateMeterDetailSummary($data) {
    $total = 0;
    $days = count($data);
    
    foreach ($data as $row) {
        $total += $row['usage_amount'];
    }
    
    return [
        'total_days' => $days,
        'total_records' => $days,
        'total_usage' => $total,
        'avg_daily' => $days > 0 ? $total / $days : 0
    ];
}

function calculateMeterDetailStatistics($data) {
    return [
        'total_electricity' => 0,
        'total_water' => 0,
        'avg_daily_electricity' => 0,
        'avg_daily_water' => 0
    ];
}

function prepareMeterDetailChartData($data) {
    $labels = [];
    $values = [];
    
    foreach ($data as $row) {
        $labels[] = date('d/m', strtotime($row['record_date']));
        $values[] = $row['usage_amount'];
    }
    
    return [
        'labels' => $labels,
        'values' => $values,
        'label' => 'ปริมาณการใช้รายวัน'
    ];
}

function getSummaryReport($db, $startDate, $endDate) {
    $sql = "
        SELECT 
            r.record_date,
            SUM(CASE WHEN m.meter_type = 'electricity' THEN r.usage_amount ELSE 0 END) as total_electricity,
            SUM(CASE WHEN m.meter_type = 'water' THEN r.usage_amount ELSE 0 END) as total_water,
            COUNT(DISTINCT CASE WHEN m.meter_type = 'electricity' THEN r.meter_id END) as electricity_meters,
            COUNT(DISTINCT CASE WHEN m.meter_type = 'water' THEN r.meter_id END) as water_meters
        FROM meter_daily_readings r
        JOIN mc_mdb_water m ON r.meter_id = m.id
        WHERE r.record_date BETWEEN ? AND ?
        GROUP BY r.record_date
        ORDER BY r.record_date
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

function calculateSummaryReportStats($data) {
    $totalElec = 0;
    $totalWater = 0;
    $days = count($data);
    
    foreach ($data as $row) {
        $totalElec += $row['total_electricity'];
        $totalWater += $row['total_water'];
    }
    
    return [
        'total_days' => $days,
        'total_records' => $days,
        'total_usage' => $totalElec + $totalWater,
        'avg_daily' => $days > 0 ? ($totalElec + $totalWater) / $days : 0
    ];
}

function calculateSummaryStatistics($data) {
    $totalElec = 0;
    $totalWater = 0;
    $days = count($data);
    
    foreach ($data as $row) {
        $totalElec += $row['total_electricity'];
        $totalWater += $row['total_water'];
    }
    
    return [
        'total_electricity' => $totalElec,
        'total_water' => $totalWater,
        'avg_daily_electricity' => $days > 0 ? $totalElec / $days : 0,
        'avg_daily_water' => $days > 0 ? $totalWater / $days : 0
    ];
}

function prepareSummaryChartData($data) {
    $labels = [];
    $electricity = [];
    $water = [];
    
    foreach ($data as $row) {
        $labels[] = date('d/m', strtotime($row['record_date']));
        $electricity[] = $row['total_electricity'];
        $water[] = $row['total_water'];
    }
    
    return [
        'labels' => $labels,
        'electricity' => $electricity,
        'water' => $water
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
    .zero-value {
        display: table-row !important;
    }
}
</style>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>