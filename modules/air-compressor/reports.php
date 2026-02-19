<?php
/**
 * Air Compressor Module - Reports Page
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
$pageTitle = 'Air Compressor - รายงาน';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Air Compressor', 'link' => 'index.php'],
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
$compareWith = isset($_GET['compare_with']) ? $_GET['compare_with'] : 'previous';

// Format dates for display
$displayStartDate = date('d/m/Y', strtotime($startDate));
$displayEndDate = date('d/m/Y', strtotime($endDate));

// Get all machines for dropdown
$stmt = $db->query("SELECT * FROM mc_air WHERE status = 1 ORDER BY machine_code");
$machines = $stmt->fetchAll();

// Get report data based on type
$reportData = [];
$summary = [];
$chartData = [];

switch ($reportType) {
    case 'daily':
        $reportData = getDailyReport($db, $startDate, $endDate, $machineId);
        $summary = calculateDailySummary($reportData);
        $chartData = prepareDailyChartData($reportData);
        break;
        
    case 'monthly':
        $reportData = getMonthlyReport($db, $startDate, $endDate, $machineId);
        $summary = calculateMonthlySummary($reportData);
        $chartData = prepareMonthlyChartData($reportData);
        break;
        
    case 'comparison':
        $reportData = getComparisonReport($db, $startDate, $endDate, $machineId, $compareWith);
        $summary = calculateComparisonSummary($reportData);
        $chartData = prepareComparisonChartData($reportData);
        break;
        
    case 'statistics':
        $reportData = getStatisticsReport($db, $machineId);
        $summary = calculateStatisticsSummary($reportData);
        $chartData = prepareStatisticsChartData($reportData);
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
                                    <option value="comparison" <?php echo $reportType == 'comparison' ? 'selected' : ''; ?>>รายงานเปรียบเทียบ</option>
                                    <option value="statistics" <?php echo $reportType == 'statistics' ? 'selected' : ''; ?>>รายงานสถิติ</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-3" id="dateRangeDiv">
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
                        
                        <div class="col-md-3" id="endDateDiv">
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
                        
                        <div class="col-md-3" id="machineDiv">
                            <div class="form-group">
                                <label>เครื่องจักร</label>
                                <select name="machine_id" class="form-control select2">
                                    <option value="0">ทั้งหมด</option>
                                    <?php foreach ($machines as $machine): ?>
                                        <option value="<?php echo $machine['id']; ?>" <?php echo $machineId == $machine['id'] ? 'selected' : ''; ?>>
                                            <?php echo $machine['machine_code'] . ' - ' . $machine['machine_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-3" id="compareDiv" style="display: none;">
                            <div class="form-group">
                                <label>เปรียบเทียบกับ</label>
                                <select name="compare_with" class="form-control">
                                    <option value="previous" <?php echo $compareWith == 'previous' ? 'selected' : ''; ?>>ช่วงก่อนหน้า</option>
                                    <option value="last_year" <?php echo $compareWith == 'last_year' ? 'selected' : ''; ?>>ปีที่แล้ว</option>
                                    <option value="average" <?php echo $compareWith == 'average' ? 'selected' : ''; ?>>ค่าเฉลี่ย</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-search"></i> แสดงรายงาน
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="button" class="btn btn-success btn-block" onclick="exportReport()">
                                    <i class="fas fa-download"></i> ส่งออกรายงาน
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <?php if (!empty($summary)): ?>
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
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
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo number_format($summary['total_usage'] ?? 0, 2); ?></h3>
                        <p>ปริมาณการใช้รวม</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo number_format($summary['avg_usage'] ?? 0, 2); ?></h3>
                        <p>ค่าเฉลี่ยต่อวัน</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo number_format($summary['max_usage'] ?? 0, 2); ?></h3>
                        <p>ค่าสูงสุด</p>
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
        
        <!-- Data Table -->
        <?php if (!empty($reportData)): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table"></i>
                    ข้อมูลรายละเอียด
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-info btn-sm" onclick="printReport()">
                        <i class="fas fa-print"></i> พิมพ์
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped dataTable">
                        <thead>
                            <tr>
                                <?php if ($reportType == 'daily'): ?>
                                <th>วันที่</th>
                                <th>เครื่องจักร</th>
                                <th>หัวข้อตรวจสอบ</th>
                                <th>ค่าที่วัดได้</th>
                                <th>ค่ามาตรฐาน</th>
                                <th>หน่วย</th>
                                <th>สถานะ</th>
                                <th>ผู้บันทึก</th>
                                <?php elseif ($reportType == 'monthly'): ?>
                                <th>เดือน</th>
                                <th>จำนวนวัน</th>
                                <th>ปริมาณรวม</th>
                                <th>ค่าเฉลี่ย</th>
                                <th>สูงสุด</th>
                                <th>ต่ำสุด</th>
                                <th>ผ่านเกณฑ์</th>
                                <?php elseif ($reportType == 'comparison'): ?>
                                <th>ช่วงเวลา</th>
                                <th>ปริมาณ</th>
                                <th>จำนวนบันทึก</th>
                                <th>ค่าเฉลี่ย</th>
                                <th>เปลี่ยนแปลง</th>
                                <th>เปอร์เซ็นต์</th>
                                <?php elseif ($reportType == 'statistics'): ?>
                                <th>สถิติ</th>
                                <th>ค่า</th>
                                <th>หน่วย</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                            <tr>
                                <?php if ($reportType == 'daily'): ?>
                                <td><?php echo date('d/m/Y', strtotime($row['record_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['machine_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['inspection_item']); ?></td>
                                <td class="text-right"><?php echo number_format($row['actual_value'], 2); ?></td>
                                <td class="text-right">
                                    <?php 
                                    if ($row['min_value'] && $row['max_value']) {
                                        echo number_format($row['min_value'], 2) . ' - ' . number_format($row['max_value'], 2);
                                    } else {
                                        echo number_format($row['standard_value'], 2);
                                    }
                                    ?>
                                </td>
                                <td><?php echo $row['unit']; ?></td>
                                <td class="text-center">
                                    <?php 
                                    $status = determineStatus($row);
                                    ?>
                                    <span class="badge badge-<?php echo $status['class']; ?>">
                                        <?php echo $status['text']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['recorded_by']); ?></td>
                                
                                <?php elseif ($reportType == 'monthly'): ?>
                                <td><?php echo getThaiMonth($row['month']) . ' ' . ($row['year'] + 543); ?></td>
                                <td class="text-right"><?php echo $row['days_count']; ?></td>
                                <td class="text-right"><?php echo number_format($row['total_usage'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['avg_usage'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['max_usage'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['min_usage'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['pass_rate'], 1); ?>%</td>
                                
                                <?php elseif ($reportType == 'comparison'): ?>
                                <td><?php echo $row['period']; ?></td>
                                <td class="text-right"><?php echo number_format($row['usage'], 2); ?></td>
                                <td class="text-right"><?php echo $row['records']; ?></td>
                                <td class="text-right"><?php echo number_format($row['average'], 2); ?></td>
                                <td class="text-right">
                                    <?php if (isset($row['change'])): ?>
                                    <span class="badge badge-<?php echo $row['change'] > 0 ? 'danger' : ($row['change'] < 0 ? 'success' : 'secondary'); ?>">
                                        <?php echo $row['change'] > 0 ? '+' : ''; ?><?php echo number_format($row['change'], 2); ?>
                                    </span>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <?php if (isset($row['change_percent'])): ?>
                                    <span class="badge badge-<?php echo $row['change_percent'] > 0 ? 'danger' : ($row['change_percent'] < 0 ? 'success' : 'secondary'); ?>">
                                        <?php echo $row['change_percent'] > 0 ? '+' : ''; ?><?php echo number_format($row['change_percent'], 1); ?>%
                                    </span>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                
                                <?php elseif ($reportType == 'statistics'): ?>
                                <td><?php echo $row['stat_name']; ?></td>
                                <td class="text-right"><?php echo number_format($row['stat_value'], 2); ?></td>
                                <td><?php echo $row['unit']; ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
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
    
    if (type === 'statistics') {
        $('#dateRangeDiv').hide();
        $('#endDateDiv').hide();
        $('#compareDiv').hide();
    } else if (type === 'comparison') {
        $('#dateRangeDiv').show();
        $('#endDateDiv').show();
        $('#compareDiv').show();
    } else {
        $('#dateRangeDiv').show();
        $('#endDateDiv').show();
        $('#compareDiv').hide();
    }
}

function renderChart(data, type) {
    const ctx = document.getElementById('reportChart').getContext('2d');
    
    if (reportChart) {
        reportChart.destroy();
    }
    
    const chartConfig = {
        type: type,
        data: {
            labels: data.labels,
            datasets: [{
                label: data.label || 'ปริมาณการใช้งาน',
                data: data.values,
                borderColor: '#007bff',
                backgroundColor: type === 'line' ? 'rgba(0, 123, 255, 0.1)' : 'rgba(0, 123, 255, 0.5)',
                borderWidth: 2,
                tension: 0.4,
                fill: type === 'line'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.raw.toFixed(2) + ' หน่วย';
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
    
    // Add second dataset for comparison
    if (data.values2) {
        chartConfig.data.datasets.push({
            label: data.label2 || 'เปรียบเทียบ',
            data: data.values2,
            borderColor: '#dc3545',
            backgroundColor: type === 'line' ? 'rgba(220, 53, 69, 0.1)' : 'rgba(220, 53, 69, 0.5)',
            borderWidth: 2,
            tension: 0.4,
            fill: type === 'line'
        });
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
function determineStatus($row) {
    if ($row['min_value'] && $row['max_value']) {
        if ($row['actual_value'] < $row['min_value'] || $row['actual_value'] > $row['max_value']) {
            return ['class' => 'danger', 'text' => 'ไม่ผ่าน'];
        }
    } else {
        $deviation = abs(($row['actual_value'] - $row['standard_value']) / $row['standard_value'] * 100);
        if ($deviation > 10) {
            return ['class' => 'danger', 'text' => 'ไม่ผ่าน'];
        }
    }
    return ['class' => 'success', 'text' => 'ผ่าน'];
}

function getDailyReport($db, $startDate, $endDate, $machineId) {
    $sql = "
        SELECT 
            r.*,
            m.machine_name,
            m.machine_code,
            s.inspection_item,
            s.standard_value,
            s.min_value,
            s.max_value,
            s.unit
        FROM air_daily_records r
        JOIN mc_air m ON r.machine_id = m.id
        JOIN air_inspection_standards s ON r.inspection_item_id = s.id
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
    $total = 0;
    $count = count($data);
    
    foreach ($data as $row) {
        $total += $row['actual_value'];
    }
    
    return [
        'total_records' => $count,
        'total_usage' => $total,
        'avg_usage' => $count > 0 ? $total / $count : 0,
        'max_usage' => $count > 0 ? max(array_column($data, 'actual_value')) : 0
    ];
}

function prepareDailyChartData($data) {
    $grouped = [];
    foreach ($data as $row) {
        $date = $row['record_date'];
        if (!isset($grouped[$date])) {
            $grouped[$date] = 0;
        }
        $grouped[$date] += $row['actual_value'];
    }
    
    ksort($grouped);
    
    return [
        'labels' => array_map(function($d) { return date('d/m', strtotime($d)); }, array_keys($grouped)),
        'values' => array_values($grouped),
        'label' => 'ปริมาณการใช้รายวัน'
    ];
}

function getMonthlyReport($db, $startDate, $endDate, $machineId) {
    $sql = "
        SELECT 
            YEAR(r.record_date) as year,
            MONTH(r.record_date) as month,
            COUNT(DISTINCT r.record_date) as days_count,
            COUNT(r.id) as total_records,
            SUM(r.actual_value) as total_usage,
            AVG(r.actual_value) as avg_usage,
            MAX(r.actual_value) as max_usage,
            MIN(r.actual_value) as min_usage,
            SUM(CASE 
                WHEN (s.min_value IS NOT NULL AND r.actual_value BETWEEN s.min_value AND s.max_value) 
                     OR (s.min_value IS NULL AND ABS(r.actual_value - s.standard_value) <= s.standard_value * 0.1)
                THEN 1 ELSE 0 END) as pass_count
        FROM air_daily_records r
        JOIN air_inspection_standards s ON r.inspection_item_id = s.id
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
    $data = $stmt->fetchAll();
    
    foreach ($data as &$row) {
        $row['pass_rate'] = $row['total_records'] > 0 ? ($row['pass_count'] / $row['total_records']) * 100 : 0;
    }
    
    return $data;
}

function calculateMonthlySummary($data) {
    $total = 0;
    $records = 0;
    
    foreach ($data as $row) {
        $total += $row['total_usage'];
        $records += $row['total_records'];
    }
    
    return [
        'total_records' => $records,
        'total_usage' => $total,
        'avg_usage' => count($data) > 0 ? $total / count($data) : 0,
        'max_usage' => count($data) > 0 ? max(array_column($data, 'max_usage')) : 0
    ];
}

function prepareMonthlyChartData($data) {
    $labels = [];
    $values = [];
    
    foreach ($data as $row) {
        $labels[] = getThaiShortMonth($row['month']) . ' ' . ($row['year'] + 543);
        $values[] = $row['total_usage'];
    }
    
    return [
        'labels' => $labels,
        'values' => $values,
        'label' => 'ปริมาณการใช้รายเดือน'
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
        // Get average of all similar periods
        return getAverageComparison($db, $startDate, $endDate, $machineId);
    }
    
    // Get current period data
    $current = getPeriodData($db, $startDate, $endDate, $machineId);
    
    // Get previous period data
    $previous = getPeriodData($db, $prevStart, $prevEnd, $machineId);
    
    $change = $current['total_usage'] - $previous['total_usage'];
    $changePercent = $previous['total_usage'] > 0 ? ($change / $previous['total_usage']) * 100 : 0;
    
    return [
        [
            'period' => 'ช่วงเวลาปัจจุบัน',
            'usage' => $current['total_usage'],
            'records' => $current['records'],
            'average' => $current['avg_usage'],
            'change' => null,
            'change_percent' => null
        ],
        [
            'period' => 'ช่วงเวลาเปรียบเทียบ',
            'usage' => $previous['total_usage'],
            'records' => $previous['records'],
            'average' => $previous['avg_usage'],
            'change' => -$change,
            'change_percent' => -$changePercent
        ]
    ];
}

function getPeriodData($db, $startDate, $endDate, $machineId) {
    $sql = "
        SELECT 
            COUNT(DISTINCT r.record_date) as days,
            COUNT(r.id) as records,
            SUM(r.actual_value) as total_usage,
            AVG(r.actual_value) as avg_usage
        FROM air_daily_records r
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

function getAverageComparison($db, $startDate, $endDate, $machineId) {
    // Implementation for average comparison
    return [];
}

function calculateComparisonSummary($data) {
    return [
        'total_records' => $data[0]['records'] + ($data[1]['records'] ?? 0),
        'total_usage' => $data[0]['usage'] + ($data[1]['usage'] ?? 0),
        'avg_usage' => ($data[0]['average'] + ($data[1]['average'] ?? 0)) / 2,
        'max_usage' => max($data[0]['usage'], $data[1]['usage'] ?? 0)
    ];
}

function prepareComparisonChartData($data) {
    return [
        'labels' => [$data[0]['period'], $data[1]['period']],
        'values' => [$data[0]['usage'], $data[1]['usage']],
        'label' => 'ปริมาณการใช้งาน'
    ];
}

function getStatisticsReport($db, $machineId) {
    $sql = "
        SELECT 
            COUNT(DISTINCT r.record_date) as total_days,
            COUNT(r.id) as total_records,
            SUM(r.actual_value) as total_usage,
            AVG(r.actual_value) as overall_avg,
            MAX(r.actual_value) as overall_max,
            MIN(r.actual_value) as overall_min,
            STDDEV(r.actual_value) as std_deviation
        FROM air_daily_records r
    ";
    
    if ($machineId > 0) {
        $sql .= " WHERE r.machine_id = ?";
        $params = [$machineId];
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    } else {
        $stmt = $db->query($sql);
    }
    
    $stats = $stmt->fetch();
    
    return [
        ['stat_name' => 'จำนวนวันที่บันทึก', 'stat_value' => $stats['total_days'] ?? 0, 'unit' => 'วัน'],
        ['stat_name' => 'จำนวนบันทึกทั้งหมด', 'stat_value' => $stats['total_records'] ?? 0, 'unit' => 'รายการ'],
        ['stat_name' => 'ปริมาณการใช้รวม', 'stat_value' => $stats['total_usage'] ?? 0, 'unit' => 'หน่วย'],
        ['stat_name' => 'ค่าเฉลี่ย', 'stat_value' => $stats['overall_avg'] ?? 0, 'unit' => 'หน่วย/วัน'],
        ['stat_name' => 'ค่าสูงสุด', 'stat_value' => $stats['overall_max'] ?? 0, 'unit' => 'หน่วย'],
        ['stat_name' => 'ค่าต่ำสุด', 'stat_value' => $stats['overall_min'] ?? 0, 'unit' => 'หน่วย'],
        ['stat_name' => 'ส่วนเบี่ยงเบนมาตรฐาน', 'stat_value' => $stats['std_deviation'] ?? 0, 'unit' => 'หน่วย']
    ];
}

function calculateStatisticsSummary($data) {
    $summary = [];
    foreach ($data as $row) {
        $summary[$row['stat_name']] = $row['stat_value'];
    }
    return $summary;
}

function prepareStatisticsChartData($data) {
    return [
        'labels' => ['ค่าเฉลี่ย', 'สูงสุด', 'ต่ำสุด'],
        'values' => [
            $data[3]['stat_value'] ?? 0,
            $data[4]['stat_value'] ?? 0,
            $data[5]['stat_value'] ?? 0
        ],
        'label' => 'สถิติการใช้งาน'
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