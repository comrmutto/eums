<?php
/**
 * LPG Module - Reports Page
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
$pageTitle = 'LPG - รายงาน';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'LPG', 'link' => 'index.php'],
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
$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$itemType = isset($_GET['item_type']) ? $_GET['item_type'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$compareWith = isset($_GET['compare_with']) ? $_GET['compare_with'] : 'previous';

// Format dates for display
$displayStartDate = date('d/m/Y', strtotime($startDate));
$displayEndDate = date('d/m/Y', strtotime($endDate));

// Get all inspection items for dropdown
$stmt = $db->query("
    SELECT * FROM lpg_inspection_items 
    ORDER BY item_no
");
$inspectionItems = $stmt->fetchAll();

// Get report data based on type
$reportData = [];
$summary = [];
$chartData = [];
$statistics = [];

switch ($reportType) {
    case 'daily':
        $reportData = getDailyReport($db, $startDate, $endDate, $itemId, $itemType);
        $summary = calculateDailySummary($reportData);
        $chartData = prepareDailyChartData($reportData);
        $statistics = calculateDailyStatistics($reportData);
        break;
        
    case 'monthly':
        $reportData = getMonthlyReport($db, $startDate, $endDate, $itemId, $itemType);
        $summary = calculateMonthlySummary($reportData);
        $chartData = prepareMonthlyChartData($reportData);
        $statistics = calculateMonthlyStatistics($reportData);
        break;
        
    case 'quality':
        $reportData = getQualityReport($db, $startDate, $endDate, $itemId);
        $summary = calculateQualitySummary($reportData);
        $chartData = prepareQualityChartData($reportData);
        $statistics = calculateQualityStatistics($reportData);
        break;
        
    case 'ng_analysis':
        $reportData = getNGAnalysisReport($db, $startDate, $endDate);
        $summary = calculateNGAnalysisSummary($reportData);
        $chartData = prepareNGAnalysisChartData($reportData);
        $statistics = calculateNGAnalysisStatistics($reportData);
        break;
        
    case 'item_detail':
        $reportData = getItemDetailReport($db, $startDate, $endDate, $itemId);
        $summary = calculateItemDetailSummary($reportData);
        $chartData = prepareItemDetailChartData($reportData);
        $statistics = calculateItemDetailStatistics($reportData);
        break;
        
    case 'comparison':
        $reportData = getComparisonReport($db, $startDate, $endDate, $itemId, $itemType, $compareWith);
        $summary = calculateComparisonSummary($reportData);
        $chartData = prepareComparisonChartData($reportData);
        $statistics = calculateComparisonStatistics($reportData);
        break;
}

// Get item name if selected
$itemName = '';
if ($itemId > 0) {
    foreach ($inspectionItems as $item) {
        if ($item['id'] == $itemId) {
            $itemName = $item['item_name'];
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
                                    <option value="quality" <?php echo $reportType == 'quality' ? 'selected' : ''; ?>>รายงานคุณภาพ (OK/NG)</option>
                                    <option value="ng_analysis" <?php echo $reportType == 'ng_analysis' ? 'selected' : ''; ?>>วิเคราะห์ NG</option>
                                    <option value="item_detail" <?php echo $reportType == 'item_detail' ? 'selected' : ''; ?>>รายงานแยกรายการ</option>
                                    <option value="comparison" <?php echo $reportType == 'comparison' ? 'selected' : ''; ?>>รายงานเปรียบเทียบ</option>
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
                        
                        <div class="col-md-2" id="itemDiv">
                            <div class="form-group">
                                <label>หัวข้อตรวจสอบ</label>
                                <select name="item_id" class="form-control select2">
                                    <option value="0">ทั้งหมด</option>
                                    <?php foreach ($inspectionItems as $item): ?>
                                        <option value="<?php echo $item['id']; ?>" 
                                                data-type="<?php echo $item['item_type']; ?>"
                                                <?php echo $itemId == $item['id'] ? 'selected' : ''; ?>>
                                            <?php echo $item['item_no'] . '. ' . $item['item_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-2" id="itemTypeDiv">
                            <div class="form-group">
                                <label>ประเภท</label>
                                <select name="item_type" class="form-control">
                                    <option value="">ทั้งหมด</option>
                                    <option value="number" <?php echo $itemType == 'number' ? 'selected' : ''; ?>>ตัวเลข</option>
                                    <option value="enum" <?php echo $itemType == 'enum' ? 'selected' : ''; ?>>OK/NG</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-2" id="statusDiv" style="display: none;">
                            <div class="form-group">
                                <label>สถานะ</label>
                                <select name="status" class="form-control">
                                    <option value="">ทั้งหมด</option>
                                    <option value="OK" <?php echo $status == 'OK' ? 'selected' : ''; ?>>OK</option>
                                    <option value="NG" <?php echo $status == 'NG' ? 'selected' : ''; ?>>NG</option>
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
                        <h3><?php echo number_format($statistics['total_usage'] ?? 0, 2); ?></h3>
                        <p>ปริมาณการใช้รวม</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-fire"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo $statistics['total_ok'] ?? 0; ?></h3>
                        <p>จำนวน OK</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo $statistics['total_ng'] ?? 0; ?></h3>
                        <p>จำนวน NG</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo number_format($statistics['ok_rate'] ?? 0, 1); ?>%</h3>
                        <p>อัตราผ่าน (OK Rate)</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-pie"></i>
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
                        <h3><?php echo number_format($summary['avg_daily'] ?? 0, 2); ?></h3>
                        <p>ค่าเฉลี่ยต่อวัน</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo number_format($summary['max_value'] ?? 0, 2); ?></h3>
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
                        <option value="pie">กราฟวงกลม</option>
                        <option value="doughnut">กราฟโดนัท</option>
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
        
        <!-- Quality Summary Cards (for quality report) -->
        <?php if ($reportType == 'quality' && !empty($reportData)): ?>
        <div class="row">
            <div class="col-md-6">
                <div class="card card-success">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-check-circle"></i>
                            รายการ OK
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>รายการ</th>
                                        <th class="text-right">จำนวน</th>
                                        <th class="text-right">สัดส่วน</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $totalOk = 0;
                                    foreach ($reportData as $item) {
                                        if (isset($item['ok_count'])) {
                                            $totalOk += $item['ok_count'];
                                        }
                                    }
                                    foreach ($reportData as $item): 
                                    if ($item['ok_count'] > 0):
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td class="text-right"><?php echo $item['ok_count']; ?></td>
                                        <td class="text-right">
                                            <?php echo number_format(($item['ok_count'] / $totalOk) * 100, 1); ?>%
                                        </td>
                                    </tr>
                                    <?php endif; endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray">
                                        <th>รวม</th>
                                        <th class="text-right"><?php echo $totalOk; ?></th>
                                        <th class="text-right">100%</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card card-danger">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-times-circle"></i>
                            รายการ NG
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>รายการ</th>
                                        <th class="text-right">จำนวน</th>
                                        <th class="text-right">สัดส่วน</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $totalNg = 0;
                                    foreach ($reportData as $item) {
                                        if (isset($item['ng_count'])) {
                                            $totalNg += $item['ng_count'];
                                        }
                                    }
                                    foreach ($reportData as $item): 
                                    if ($item['ng_count'] > 0):
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td class="text-right"><?php echo $item['ng_count']; ?></td>
                                        <td class="text-right">
                                            <?php echo number_format(($item['ng_count'] / $totalNg) * 100, 1); ?>%
                                        </td>
                                    </tr>
                                    <?php endif; endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray">
                                        <th>รวม</th>
                                        <th class="text-right"><?php echo $totalNg; ?></th>
                                        <th class="text-right">100%</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
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
                                <th>ลำดับ</th>
                                <th>รายการ</th>
                                <th>ประเภท</th>
                                <th>ค่าที่บันทึก</th>
                                <th>ค่ามาตรฐาน</th>
                                <th>หน่วย</th>
                                <th>สถานะ</th>
                                <th>ผู้บันทึก</th>
                                
                                <?php elseif ($reportType == 'monthly'): ?>
                                <th>เดือน</th>
                                <th>ปริมาณรวม</th>
                                <th>จำนวนบันทึก</th>
                                <th>ค่าเฉลี่ย</th>
                                <th>สูงสุด</th>
                                <th>ต่ำสุด</th>
                                <th>OK</th>
                                <th>NG</th>
                                <th>อัตราผ่าน</th>
                                
                                <?php elseif ($reportType == 'quality'): ?>
                                <th>รายการ</th>
                                <th>ประเภท</th>
                                <th>จำนวน OK</th>
                                <th>จำนวน NG</th>
                                <th>รวม</th>
                                <th>อัตราผ่าน</th>
                                
                                <?php elseif ($reportType == 'ng_analysis'): ?>
                                <th>วันที่</th>
                                <th>รายการ NG</th>
                                <th>จำนวน NG</th>
                                <th>สาเหตุ/หมายเหตุ</th>
                                
                                <?php elseif ($reportType == 'item_detail'): ?>
                                <th>วันที่</th>
                                <th>ค่าที่บันทึก</th>
                                <th>ค่ามาตรฐาน</th>
                                <th>ผลต่าง</th>
                                <th>ส่วนเบี่ยงเบน</th>
                                <th>สถานะ</th>
                                
                                <?php elseif ($reportType == 'comparison'): ?>
                                <th>ช่วงเวลา</th>
                                <th>ปริมาณ</th>
                                <th>จำนวนบันทึก</th>
                                <th>OK</th>
                                <th>NG</th>
                                <th>อัตราผ่าน</th>
                                <th>เปลี่ยนแปลง</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                            <tr>
                                <?php if ($reportType == 'daily'): ?>
                                <td><?php echo date('d/m/Y', strtotime($row['record_date'])); ?></td>
                                <td><?php echo $row['item_no']; ?></td>
                                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $row['item_type'] == 'number' ? 'success' : 'warning'; ?>">
                                        <?php echo $row['item_type'] == 'number' ? 'ตัวเลข' : 'OK/NG'; ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    if ($row['item_type'] == 'number') {
                                        echo number_format($row['number_value'], 2);
                                    } else {
                                        echo $row['enum_value'];
                                    }
                                    ?>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    echo $row['standard_value'];
                                    if ($row['item_type'] == 'number') {
                                        echo ' ' . $row['unit'];
                                    }
                                    ?>
                                </td>
                                <td><?php echo $row['item_type'] == 'number' ? $row['unit'] : '-'; ?></td>
                                <td class="text-center">
                                    <?php 
                                    if ($row['item_type'] == 'number') {
                                        $deviation = abs(($row['number_value'] - $row['standard_value']) / $row['standard_value'] * 100);
                                        $status = $deviation <= 10 ? 'OK' : 'NG';
                                    } else {
                                        $status = $row['enum_value'];
                                    }
                                    ?>
                                    <span class="badge badge-<?php echo $status == 'OK' ? 'success' : 'danger'; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['recorded_by']); ?></td>
                                
                                <?php elseif ($reportType == 'monthly'): ?>
                                <td><?php echo getThaiMonth($row['month']) . ' ' . ($row['year'] + 543); ?></td>
                                <td class="text-right"><?php echo number_format($row['total_usage'], 2); ?></td>
                                <td class="text-right"><?php echo $row['total_records']; ?></td>
                                <td class="text-right"><?php echo number_format($row['avg_usage'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['max_usage'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['min_usage'], 2); ?></td>
                                <td class="text-right"><?php echo $row['ok_count']; ?></td>
                                <td class="text-right"><?php echo $row['ng_count']; ?></td>
                                <td class="text-right"><?php echo number_format($row['pass_rate'], 1); ?>%</td>
                                
                                <?php elseif ($reportType == 'quality'): ?>
                                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $row['item_type'] == 'number' ? 'success' : 'warning'; ?>">
                                        <?php echo $row['item_type'] == 'number' ? 'ตัวเลข' : 'OK/NG'; ?>
                                    </span>
                                </td>
                                <td class="text-right"><?php echo $row['ok_count']; ?></td>
                                <td class="text-right"><?php echo $row['ng_count']; ?></td>
                                <td class="text-right"><?php echo $row['total']; ?></td>
                                <td class="text-right">
                                    <?php 
                                    $rate = $row['total'] > 0 ? ($row['ok_count'] / $row['total']) * 100 : 0;
                                    ?>
                                    <div class="progress progress-xs">
                                        <div class="progress-bar bg-success" style="width: <?php echo $rate; ?>%"></div>
                                    </div>
                                    <?php echo number_format($rate, 1); ?>%
                                </td>
                                
                                <?php elseif ($reportType == 'ng_analysis'): ?>
                                <td><?php echo date('d/m/Y', strtotime($row['record_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td class="text-right">1</td>
                                <td><?php echo htmlspecialchars($row['remarks'] ?: '-'); ?></td>
                                
                                <?php elseif ($reportType == 'item_detail'): ?>
                                <td><?php echo date('d/m/Y', strtotime($row['record_date'])); ?></td>
                                <td class="text-right"><?php echo number_format($row['number_value'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($row['standard_value'], 2); ?></td>
                                <td class="text-right">
                                    <?php 
                                    $diff = $row['number_value'] - $row['standard_value'];
                                    ?>
                                    <span class="badge badge-<?php echo $diff > 0 ? 'danger' : ($diff < 0 ? 'success' : 'secondary'); ?>">
                                        <?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $deviation = ($diff / $row['standard_value']) * 100;
                                    ?>
                                    <span class="badge badge-<?php echo abs($deviation) > 10 ? 'danger' : 'success'; ?>">
                                        <?php echo number_format($deviation, 1); ?>%
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    $status = abs($deviation) <= 10 ? 'OK' : 'NG';
                                    ?>
                                    <span class="badge badge-<?php echo $status == 'OK' ? 'success' : 'danger'; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                                
                                <?php elseif ($reportType == 'comparison'): ?>
                                <td><?php echo $row['period']; ?></td>
                                <td class="text-right"><?php echo number_format($row['usage'], 2); ?></td>
                                <td class="text-right"><?php echo $row['records']; ?></td>
                                <td class="text-right"><?php echo $row['ok_count']; ?></td>
                                <td class="text-right"><?php echo $row['ng_count']; ?></td>
                                <td class="text-right"><?php echo number_format($row['pass_rate'], 1); ?>%</td>
                                <td class="text-right">
                                    <?php if (isset($row['change_percent'])): ?>
                                    <span class="badge badge-<?php echo $row['change_percent'] > 0 ? 'danger' : ($row['change_percent'] < 0 ? 'success' : 'secondary'); ?>">
                                        <?php echo $row['change_percent'] > 0 ? '+' : ''; ?><?php echo number_format($row['change_percent'], 1); ?>%
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if (!empty($summary) && $reportType == 'daily'): ?>
                        <tfoot>
                            <tr class="bg-gray">
                                <th colspan="4" class="text-right">รวม</th>
                                <th class="text-right"><?php echo number_format($summary['total_usage'], 2); ?></th>
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
    $('#statusDiv').hide();
    $('#compareDiv').hide();
    $('#itemDiv').show();
    $('#itemTypeDiv').show();
    
    if (type === 'quality') {
        $('#statusDiv').hide();
        $('#compareDiv').hide();
    } else if (type === 'ng_analysis') {
        $('#statusDiv').show();
        $('#compareDiv').hide();
        $('#itemDiv').hide();
        $('#itemTypeDiv').hide();
    } else if (type === 'comparison') {
        $('#statusDiv').hide();
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
    
    // Configure based on chart type
    if (type === 'pie' || type === 'doughnut') {
        if (data.pieData) {
            chartConfig.data = {
                labels: data.pieData.labels,
                datasets: [{
                    data: data.pieData.values,
                    backgroundColor: ['#28a745', '#dc3545', '#ffc107', '#17a2b8', '#007bff'],
                    borderWidth: 0
                }]
            };
        }
    } else {
        // Line or bar chart
        if (data.datasets) {
            chartConfig.data.datasets = data.datasets;
        } else if (data.usage && data.ok_rate) {
            chartConfig.data = {
                labels: data.labels,
                datasets: [
                    {
                        label: 'ปริมาณการใช้',
                        data: data.usage,
                        borderColor: '#17a2b8',
                        backgroundColor: type === 'line' ? 'rgba(23, 162, 184, 0.1)' : 'rgba(23, 162, 184, 0.5)',
                        yAxisID: 'y',
                        unit: ' หน่วย'
                    },
                    {
                        label: 'อัตราผ่าน (%)',
                        data: data.ok_rate,
                        borderColor: '#28a745',
                        backgroundColor: type === 'line' ? 'rgba(40, 167, 69, 0.1)' : 'rgba(40, 167, 69, 0.5)',
                        yAxisID: 'y1',
                        unit: '%'
                    }
                ]
            };
            
            if (type !== 'pie' && type !== 'doughnut') {
                chartConfig.options.scales = {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'ปริมาณ'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'อัตราผ่าน (%)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                };
            }
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
function getDailyReport($db, $startDate, $endDate, $itemId, $itemType) {
    $sql = "
        SELECT 
            r.*,
            i.item_no,
            i.item_name,
            i.item_type,
            i.standard_value,
            i.unit
        FROM lpg_daily_records r
        JOIN lpg_inspection_items i ON r.item_id = i.id
        WHERE r.record_date BETWEEN ? AND ?
    ";
    $params = [$startDate, $endDate];
    
    if ($itemId > 0) {
        $sql .= " AND r.item_id = ?";
        $params[] = $itemId;
    }
    
    if (!empty($itemType)) {
        $sql .= " AND i.item_type = ?";
        $params[] = $itemType;
    }
    
    $sql .= " ORDER BY r.record_date DESC, i.item_no";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function calculateDailySummary($data) {
    $total = 0;
    $days = [];
    $maxValue = 0;
    
    foreach ($data as $row) {
        if ($row['item_type'] == 'number') {
            $total += $row['number_value'];
            $maxValue = max($maxValue, $row['number_value']);
        }
        $days[$row['record_date']] = true;
    }
    
    return [
        'total_days' => count($days),
        'total_records' => count($data),
        'total_usage' => $total,
        'avg_daily' => count($days) > 0 ? $total / count($days) : 0,
        'max_value' => $maxValue
    ];
}

function calculateDailyStatistics($data) {
    $totalOk = 0;
    $totalNg = 0;
    $totalUsage = 0;
    
    foreach ($data as $row) {
        if ($row['item_type'] == 'number') {
            $totalUsage += $row['number_value'];
            $deviation = abs(($row['number_value'] - $row['standard_value']) / $row['standard_value'] * 100);
            if ($deviation <= 10) {
                $totalOk++;
            } else {
                $totalNg++;
            }
        } else {
            if ($row['enum_value'] == 'OK') {
                $totalOk++;
            } else {
                $totalNg++;
            }
        }
    }
    
    $total = $totalOk + $totalNg;
    
    return [
        'total_usage' => $totalUsage,
        'total_ok' => $totalOk,
        'total_ng' => $totalNg,
        'ok_rate' => $total > 0 ? ($totalOk / $total) * 100 : 0
    ];
}

function prepareDailyChartData($data) {
    $grouped = [];
    
    foreach ($data as $row) {
        if ($row['item_type'] == 'number') {
            $date = $row['record_date'];
            if (!isset($grouped[$date])) {
                $grouped[$date] = 0;
            }
            $grouped[$date] += $row['number_value'];
        }
    }
    
    ksort($grouped);
    
    return [
        'labels' => array_map(function($d) { return date('d/m', strtotime($d)); }, array_keys($grouped)),
        'values' => array_values($grouped),
        'label' => 'ปริมาณการใช้ LPG'
    ];
}

function getMonthlyReport($db, $startDate, $endDate, $itemId, $itemType) {
    $sql = "
        SELECT 
            YEAR(r.record_date) as year,
            MONTH(r.record_date) as month,
            COUNT(DISTINCT r.record_date) as days_count,
            COUNT(r.id) as total_records,
            SUM(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE 0 END) as total_usage,
            AVG(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE NULL END) as avg_usage,
            MAX(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE 0 END) as max_usage,
            MIN(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE 0 END) as min_usage,
            SUM(CASE WHEN i.item_type = 'enum' AND r.enum_value = 'OK' THEN 1 ELSE 0 END) as ok_count,
            SUM(CASE WHEN i.item_type = 'enum' AND r.enum_value = 'NG' THEN 1 ELSE 0 END) as ng_count
        FROM lpg_daily_records r
        JOIN lpg_inspection_items i ON r.item_id = i.id
        WHERE r.record_date BETWEEN ? AND ?
    ";
    $params = [$startDate, $endDate];
    
    if ($itemId > 0) {
        $sql .= " AND r.item_id = ?";
        $params[] = $itemId;
    }
    
    if (!empty($itemType)) {
        $sql .= " AND i.item_type = ?";
        $params[] = $itemType;
    }
    
    $sql .= " GROUP BY YEAR(r.record_date), MONTH(r.record_date) ORDER BY year, month";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    foreach ($data as &$row) {
        $totalEnum = $row['ok_count'] + $row['ng_count'];
        $row['pass_rate'] = $totalEnum > 0 ? ($row['ok_count'] / $totalEnum) * 100 : 0;
    }
    
    return $data;
}

function calculateMonthlySummary($data) {
    $totalUsage = 0;
    $totalOk = 0;
    $totalNg = 0;
    $months = count($data);
    
    foreach ($data as $row) {
        $totalUsage += $row['total_usage'];
        $totalOk += $row['ok_count'];
        $totalNg += $row['ng_count'];
    }
    
    return [
        'total_days' => $months,
        'total_records' => $totalOk + $totalNg,
        'total_usage' => $totalUsage,
        'avg_daily' => $months > 0 ? $totalUsage / $months : 0,
        'max_value' => !empty($data) ? max(array_column($data, 'max_usage')) : 0
    ];
}

function calculateMonthlyStatistics($data) {
    $totalUsage = 0;
    $totalOk = 0;
    $totalNg = 0;
    
    foreach ($data as $row) {
        $totalUsage += $row['total_usage'];
        $totalOk += $row['ok_count'];
        $totalNg += $row['ng_count'];
    }
    
    $total = $totalOk + $totalNg;
    
    return [
        'total_usage' => $totalUsage,
        'total_ok' => $totalOk,
        'total_ng' => $totalNg,
        'ok_rate' => $total > 0 ? ($totalOk / $total) * 100 : 0
    ];
}

function prepareMonthlyChartData($data) {
    $labels = [];
    $usage = [];
    $okRate = [];
    
    foreach ($data as $row) {
        $labels[] = getThaiShortMonth($row['month']) . ' ' . ($row['year'] + 543);
        $usage[] = $row['total_usage'];
        $totalEnum = $row['ok_count'] + $row['ng_count'];
        $okRate[] = $totalEnum > 0 ? ($row['ok_count'] / $totalEnum) * 100 : 0;
    }
    
    return [
        'labels' => $labels,
        'usage' => $usage,
        'ok_rate' => $okRate
    ];
}

function getQualityReport($db, $startDate, $endDate, $itemId) {
    $sql = "
        SELECT 
            i.id,
            i.item_no,
            i.item_name,
            i.item_type,
            i.standard_value,
            COUNT(CASE WHEN r.enum_value = 'OK' THEN 1 END) as ok_count,
            COUNT(CASE WHEN r.enum_value = 'NG' THEN 1 END) as ng_count,
            COUNT(r.id) as total
        FROM lpg_inspection_items i
        LEFT JOIN lpg_daily_records r ON i.id = r.item_id 
            AND r.record_date BETWEEN ? AND ?
            AND i.item_type = 'enum'
    ";
    $params = [$startDate, $endDate];
    
    if ($itemId > 0) {
        $sql .= " AND i.id = ?";
        $params[] = $itemId;
    }
    
    $sql .= " GROUP BY i.id, i.item_no, i.item_name, i.item_type, i.standard_value 
              ORDER BY i.item_no";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function calculateQualitySummary($data) {
    $totalOk = 0;
    $totalNg = 0;
    
    foreach ($data as $row) {
        $totalOk += $row['ok_count'];
        $totalNg += $row['ng_count'];
    }
    
    return [
        'total_records' => $totalOk + $totalNg,
        'total_usage' => 0,
        'avg_daily' => 0,
        'max_value' => 0
    ];
}

function calculateQualityStatistics($data) {
    $totalOk = 0;
    $totalNg = 0;
    
    foreach ($data as $row) {
        $totalOk += $row['ok_count'];
        $totalNg += $row['ng_count'];
    }
    
    $total = $totalOk + $totalNg;
    
    return [
        'total_usage' => 0,
        'total_ok' => $totalOk,
        'total_ng' => $totalNg,
        'ok_rate' => $total > 0 ? ($totalOk / $total) * 100 : 0
    ];
}

function prepareQualityChartData($data) {
    $labels = [];
    $okData = [];
    $ngData = [];
    $pieLabels = [];
    $pieValues = [];
    $totalOk = 0;
    $totalNg = 0;
    
    foreach ($data as $row) {
        if ($row['ok_count'] > 0 || $row['ng_count'] > 0) {
            $labels[] = $row['item_name'];
            $okData[] = $row['ok_count'];
            $ngData[] = $row['ng_count'];
            $totalOk += $row['ok_count'];
            $totalNg += $row['ng_count'];
        }
    }
    
    if ($totalOk > 0 || $totalNg > 0) {
        $pieLabels = ['OK', 'NG'];
        $pieValues = [$totalOk, $totalNg];
    }
    
    return [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'OK',
                'data' => $okData,
                'backgroundColor' => '#28a745'
            ],
            [
                'label' => 'NG',
                'data' => $ngData,
                'backgroundColor' => '#dc3545'
            ]
        ],
        'pieData' => [
            'labels' => $pieLabels,
            'values' => $pieValues
        ]
    ];
}

function getNGAnalysisReport($db, $startDate, $endDate) {
    $sql = "
        SELECT 
            r.record_date,
            i.item_name,
            r.remarks
        FROM lpg_daily_records r
        JOIN lpg_inspection_items i ON r.item_id = i.id
        WHERE r.record_date BETWEEN ? AND ?
        AND r.enum_value = 'NG'
        ORDER BY r.record_date DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll();
}

function calculateNGAnalysisSummary($data) {
    return [
        'total_records' => count($data),
        'total_usage' => 0,
        'avg_daily' => 0,
        'max_value' => 0
    ];
}

function calculateNGAnalysisStatistics($data) {
    return [
        'total_usage' => 0,
        'total_ok' => 0,
        'total_ng' => count($data),
        'ok_rate' => 0
    ];
}

function prepareNGAnalysisChartData($data) {
    $ngByDate = [];
    
    foreach ($data as $row) {
        $date = $row['record_date'];
        if (!isset($ngByDate[$date])) {
            $ngByDate[$date] = 0;
        }
        $ngByDate[$date]++;
    }
    
    ksort($ngByDate);
    
    return [
        'labels' => array_map(function($d) { return date('d/m', strtotime($d)); }, array_keys($ngByDate)),
        'values' => array_values($ngByDate),
        'label' => 'จำนวน NG'
    ];
}

function getItemDetailReport($db, $startDate, $endDate, $itemId) {
    if ($itemId <= 0) return [];
    
    $sql = "
        SELECT 
            r.record_date,
            r.number_value,
            i.standard_value
        FROM lpg_daily_records r
        JOIN lpg_inspection_items i ON r.item_id = i.id
        WHERE r.item_id = ? AND r.record_date BETWEEN ? AND ?
        AND i.item_type = 'number'
        ORDER BY r.record_date
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$itemId, $startDate, $endDate]);
    return $stmt->fetchAll();
}

function calculateItemDetailSummary($data) {
    $total = 0;
    $days = count($data);
    $max = 0;
    
    foreach ($data as $row) {
        $total += $row['number_value'];
        $max = max($max, $row['number_value']);
    }
    
    return [
        'total_days' => $days,
        'total_records' => $days,
        'total_usage' => $total,
        'avg_daily' => $days > 0 ? $total / $days : 0,
        'max_value' => $max
    ];
}

function calculateItemDetailStatistics($data) {
    $total = 0;
    $ok = 0;
    $ng = 0;
    
    foreach ($data as $row) {
        $total += $row['number_value'];
        $deviation = abs(($row['number_value'] - $row['standard_value']) / $row['standard_value'] * 100);
        if ($deviation <= 10) {
            $ok++;
        } else {
            $ng++;
        }
    }
    
    $totalCount = $ok + $ng;
    
    return [
        'total_usage' => $total,
        'total_ok' => $ok,
        'total_ng' => $ng,
        'ok_rate' => $totalCount > 0 ? ($ok / $totalCount) * 100 : 0
    ];
}

function prepareItemDetailChartData($data) {
    $labels = [];
    $values = [];
    $standardLine = [];
    
    foreach ($data as $row) {
        $labels[] = date('d/m', strtotime($row['record_date']));
        $values[] = $row['number_value'];
        $standardLine[] = $row['standard_value'];
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
                'fill' => false
            ],
            [
                'label' => 'ค่ามาตรฐาน',
                'data' => $standardLine,
                'borderColor' => '#dc3545',
                'borderWidth' => 2,
                'borderDash' => [5, 5],
                'fill' => false,
                'pointRadius' => 0
            ]
        ]
    ];
}

function getComparisonReport($db, $startDate, $endDate, $itemId, $itemType, $compareWith) {
    $dateDiff = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24);
    
    if ($compareWith == 'previous') {
        $prevStart = date('Y-m-d', strtotime("-$dateDiff days", strtotime($startDate)));
        $prevEnd = date('Y-m-d', strtotime("-1 day", strtotime($startDate)));
    } elseif ($compareWith == 'last_year') {
        $prevStart = date('Y-m-d', strtotime("-1 year", strtotime($startDate)));
        $prevEnd = date('Y-m-d', strtotime("-1 year", strtotime($endDate)));
    } else {
        return getAverageComparison($db, $startDate, $endDate, $itemId, $itemType);
    }
    
    $current = getPeriodSummary($db, $startDate, $endDate, $itemId, $itemType);
    $previous = getPeriodSummary($db, $prevStart, $prevEnd, $itemId, $itemType);
    
    $usageChange = $current['usage'] - $previous['usage'];
    $usageChangePercent = $previous['usage'] > 0 ? ($usageChange / $previous['usage']) * 100 : 0;
    $passRateChange = $current['pass_rate'] - $previous['pass_rate'];
    
    return [
        [
            'period' => 'ช่วงเวลาปัจจุบัน',
            'usage' => $current['usage'],
            'records' => $current['records'],
            'ok_count' => $current['ok_count'],
            'ng_count' => $current['ng_count'],
            'pass_rate' => $current['pass_rate'],
            'change' => null,
            'change_percent' => null
        ],
        [
            'period' => 'ช่วงเวลาเปรียบเทียบ',
            'usage' => $previous['usage'],
            'records' => $previous['records'],
            'ok_count' => $previous['ok_count'],
            'ng_count' => $previous['ng_count'],
            'pass_rate' => $previous['pass_rate'],
            'change' => -$usageChange,
            'change_percent' => -$usageChangePercent
        ]
    ];
}

function getPeriodSummary($db, $startDate, $endDate, $itemId, $itemType) {
    $sql = "
        SELECT 
            COUNT(DISTINCT r.record_date) as days,
            COUNT(r.id) as records,
            SUM(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE 0 END) as usage,
            SUM(CASE WHEN i.item_type = 'enum' AND r.enum_value = 'OK' THEN 1 ELSE 0 END) as ok_count,
            COUNT(CASE WHEN i.item_type = 'enum' THEN 1 END) as enum_count
        FROM lpg_daily_records r
        JOIN lpg_inspection_items i ON r.item_id = i.id
        WHERE r.record_date BETWEEN ? AND ?
    ";
    $params = [$startDate, $endDate];
    
    if ($itemId > 0) {
        $sql .= " AND r.item_id = ?";
        $params[] = $itemId;
    }
    
    if (!empty($itemType)) {
        $sql .= " AND i.item_type = ?";
        $params[] = $itemType;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    $result['pass_rate'] = $result['enum_count'] > 0 ? ($result['ok_count'] / $result['enum_count']) * 100 : 0;
    
    return $result;
}

function getAverageComparison($db, $startDate, $endDate, $itemId, $itemType) {
    // Implementation for average comparison
    return [];
}

function calculateComparisonSummary($data) {
    return [
        'total_days' => 2,
        'total_records' => ($data[0]['records'] ?? 0) + ($data[1]['records'] ?? 0),
        'total_usage' => ($data[0]['usage'] ?? 0) + ($data[1]['usage'] ?? 0),
        'avg_daily' => (($data[0]['usage'] ?? 0) + ($data[1]['usage'] ?? 0)) / 2,
        'max_value' => max($data[0]['usage'] ?? 0, $data[1]['usage'] ?? 0)
    ];
}

function calculateComparisonStatistics($data) {
    $totalOk = ($data[0]['ok_count'] ?? 0) + ($data[1]['ok_count'] ?? 0);
    $totalNg = ($data[0]['ng_count'] ?? 0) + ($data[1]['ng_count'] ?? 0);
    $total = $totalOk + $totalNg;
    
    return [
        'total_usage' => ($data[0]['usage'] ?? 0) + ($data[1]['usage'] ?? 0),
        'total_ok' => $totalOk,
        'total_ng' => $totalNg,
        'ok_rate' => $total > 0 ? ($totalOk / $total) * 100 : 0
    ];
}

function prepareComparisonChartData($data) {
    return [
        'labels' => [$data[0]['period'], $data[1]['period']],
        'datasets' => [
            [
                'label' => 'ปริมาณการใช้',
                'data' => [$data[0]['usage'], $data[1]['usage']],
                'backgroundColor' => ['#17a2b8', '#17a2b8']
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
    .zero-value {
        display: table-row !important;
    }
}
</style>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>