<?php
/**
 * Comparison Report - All Modules
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
$pageTitle = 'รายงานเปรียบเทียบ';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'รายงาน', 'link' => '#'],
    ['title' => 'เปรียบเทียบ', 'link' => null]
];

// Include header
require_once __DIR__ . '/../includes/header.php';

// Load required files
require_once __DIR__ . '/../includes/functions.php';

// Get database connection
$db = getDB();

// Get parameters
$period1Start = isset($_GET['period1_start']) ? $_GET['period1_start'] : date('Y-m-01');
$period1End = isset($_GET['period1_end']) ? $_GET['period1_end'] : date('Y-m-d');
$period2Start = isset($_GET['period2_start']) ? $_GET['period2_start'] : date('Y-m-d', strtotime('-1 month'));
$period2End = isset($_GET['period2_end']) ? $_GET['period2_end'] : date('Y-m-d', strtotime('-1 day'));
$compareType = isset($_GET['compare_type']) ? $_GET['compare_type'] : 'modules';

$period1Display = date('d/m/Y', strtotime($period1Start)) . ' - ' . date('d/m/Y', strtotime($period1End));
$period2Display = date('d/m/Y', strtotime($period2Start)) . ' - ' . date('d/m/Y', strtotime($period2End));

// Get comparison data
$comparisonData = [];

// Air Compressor
$stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(actual_value), 0) as total,
        COALESCE(COUNT(*), 0) as records,
        COALESCE(AVG(actual_value), 0) as average
    FROM air_daily_records
    WHERE record_date BETWEEN ? AND ?
");
$stmt->execute([$period1Start, $period1End]);
$air1 = $stmt->fetch();
$stmt->execute([$period2Start, $period2End]);
$air2 = $stmt->fetch();

$comparisonData['air'] = [
    'name' => 'Air Compressor',
    'period1' => [
        'total' => $air1['total'],
        'records' => $air1['records'],
        'average' => $air1['average']
    ],
    'period2' => [
        'total' => $air2['total'],
        'records' => $air2['records'],
        'average' => $air2['average']
    ]
];

// Energy & Water
$stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(usage_amount), 0) as total,
        COALESCE(COUNT(*), 0) as records,
        COALESCE(AVG(usage_amount), 0) as average,
        COALESCE(SUM(CASE WHEN m.meter_type = 'electricity' THEN usage_amount ELSE 0 END), 0) as electricity,
        COALESCE(SUM(CASE WHEN m.meter_type = 'water' THEN usage_amount ELSE 0 END), 0) as water
    FROM meter_daily_readings r
    JOIN mc_mdb_water m ON r.meter_id = m.id
    WHERE r.record_date BETWEEN ? AND ?
");
$stmt->execute([$period1Start, $period1End]);
$energy1 = $stmt->fetch();
$stmt->execute([$period2Start, $period2End]);
$energy2 = $stmt->fetch();

$comparisonData['energy'] = [
    'name' => 'Energy & Water',
    'period1' => [
        'total' => $energy1['total'],
        'records' => $energy1['records'],
        'average' => $energy1['average'],
        'electricity' => $energy1['electricity'],
        'water' => $energy1['water']
    ],
    'period2' => [
        'total' => $energy2['total'],
        'records' => $energy2['records'],
        'average' => $energy2['average'],
        'electricity' => $energy2['electricity'],
        'water' => $energy2['water']
    ]
];

// LPG
$stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN i.item_type = 'number' THEN r.number_value ELSE 0 END), 0) as total,
        COALESCE(COUNT(*), 0) as records,
        COALESCE(SUM(CASE WHEN i.item_type = 'enum' AND r.enum_value = 'OK' THEN 1 ELSE 0 END), 0) as ok_count,
        COALESCE(SUM(CASE WHEN i.item_type = 'enum' AND r.enum_value = 'NG' THEN 1 ELSE 0 END), 0) as ng_count
    FROM lpg_daily_records r
    JOIN lpg_inspection_items i ON r.item_id = i.id
    WHERE r.record_date BETWEEN ? AND ?
");
$stmt->execute([$period1Start, $period1End]);
$lpg1 = $stmt->fetch();
$stmt->execute([$period2Start, $period2End]);
$lpg2 = $stmt->fetch();

$comparisonData['lpg'] = [
    'name' => 'LPG',
    'period1' => [
        'total' => $lpg1['total'],
        'records' => $lpg1['records'],
        'ok_count' => $lpg1['ok_count'],
        'ng_count' => $lpg1['ng_count']
    ],
    'period2' => [
        'total' => $lpg2['total'],
        'records' => $lpg2['records'],
        'ok_count' => $lpg2['ok_count'],
        'ng_count' => $lpg2['ng_count']
    ]
];

// Boiler
$stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(fuel_consumption), 0) as fuel,
        COALESCE(SUM(operating_hours), 0) as hours,
        COALESCE(AVG(steam_pressure), 0) as avg_pressure,
        COALESCE(AVG(steam_temperature), 0) as avg_temp,
        COALESCE(COUNT(*), 0) as records
    FROM boiler_daily_records
    WHERE record_date BETWEEN ? AND ?
");
$stmt->execute([$period1Start, $period1End]);
$boiler1 = $stmt->fetch();
$stmt->execute([$period2Start, $period2End]);
$boiler2 = $stmt->fetch();

$comparisonData['boiler'] = [
    'name' => 'Boiler',
    'period1' => [
        'fuel' => $boiler1['fuel'],
        'hours' => $boiler1['hours'],
        'avg_pressure' => $boiler1['avg_pressure'],
        'avg_temp' => $boiler1['avg_temp'],
        'records' => $boiler1['records']
    ],
    'period2' => [
        'fuel' => $boiler2['fuel'],
        'hours' => $boiler2['hours'],
        'avg_pressure' => $boiler2['avg_pressure'],
        'avg_temp' => $boiler2['avg_temp'],
        'records' => $boiler2['records']
    ]
];

// Summary Electricity
$stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(ee_unit), 0) as total_ee,
        COALESCE(SUM(total_cost), 0) as total_cost,
        COALESCE(AVG(cost_per_unit), 0) as avg_cost,
        COALESCE(COUNT(*), 0) as records
    FROM electricity_summary
    WHERE record_date BETWEEN ? AND ?
");
$stmt->execute([$period1Start, $period1End]);
$summary1 = $stmt->fetch();
$stmt->execute([$period2Start, $period2End]);
$summary2 = $stmt->fetch();

$comparisonData['summary'] = [
    'name' => 'Summary Electricity',
    'period1' => [
        'total_ee' => $summary1['total_ee'],
        'total_cost' => $summary1['total_cost'],
        'avg_cost' => $summary1['avg_cost'],
        'records' => $summary1['records']
    ],
    'period2' => [
        'total_ee' => $summary2['total_ee'],
        'total_cost' => $summary2['total_cost'],
        'avg_cost' => $summary2['avg_cost'],
        'records' => $summary2['records']
    ]
];
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Period Selector Card -->
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-alt"></i>
                    เลือกช่วงเวลาเปรียบเทียบ
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" class="form-horizontal">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="card card-info">
                                <div class="card-header">
                                    <h5 class="card-title">ช่วงเวลาที่ 1</h5>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>วันที่เริ่มต้น</label>
                                        <div class="input-group date" id="period1StartPicker" data-target-input="nearest">
                                            <input type="text" class="form-control datetimepicker-input" 
                                                   name="period1_start" id="period1Start" 
                                                   value="<?php echo date('d/m/Y', strtotime($period1Start)); ?>" 
                                                   data-target="#period1StartPicker">
                                            <div class="input-group-append" data-target="#period1StartPicker" data-toggle="datetimepicker">
                                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>วันที่สิ้นสุด</label>
                                        <div class="input-group date" id="period1EndPicker" data-target-input="nearest">
                                            <input type="text" class="form-control datetimepicker-input" 
                                                   name="period1_end" id="period1End" 
                                                   value="<?php echo date('d/m/Y', strtotime($period1End)); ?>" 
                                                   data-target="#period1EndPicker">
                                            <div class="input-group-append" data-target="#period1EndPicker" data-toggle="datetimepicker">
                                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-center justify-content-center">
                            <h2><i class="fas fa-exchange-alt"></i></h2>
                        </div>
                        
                        <div class="col-md-5">
                            <div class="card card-warning">
                                <div class="card-header">
                                    <h5 class="card-title">ช่วงเวลาที่ 2</h5>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>วันที่เริ่มต้น</label>
                                        <div class="input-group date" id="period2StartPicker" data-target-input="nearest">
                                            <input type="text" class="form-control datetimepicker-input" 
                                                   name="period2_start" id="period2Start" 
                                                   value="<?php echo date('d/m/Y', strtotime($period2Start)); ?>" 
                                                   data-target="#period2StartPicker">
                                            <div class="input-group-append" data-target="#period2StartPicker" data-toggle="datetimepicker">
                                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>วันที่สิ้นสุด</label>
                                        <div class="input-group date" id="period2EndPicker" data-target-input="nearest">
                                            <input type="text" class="form-control datetimepicker-input" 
                                                   name="period2_end" id="period2End" 
                                                   value="<?php echo date('d/m/Y', strtotime($period2End)); ?>" 
                                                   data-target="#period2EndPicker">
                                            <div class="input-group-append" data-target="#period2EndPicker" data-toggle="datetimepicker">
                                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 text-center">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-chart-bar"></i> เปรียบเทียบ
                            </button>
                            <button type="button" class="btn btn-success" onclick="setLastMonth()">
                                เดือนที่แล้ว vs เดือนนี้
                            </button>
                            <button type="button" class="btn btn-info" onclick="setLastYear()">
                                ปีที่แล้ว vs ปีนี้
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Comparison Summary -->
        <div class="row">
            <div class="col-12">
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            ผลการเปรียบเทียบ
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-box bg-info">
                                    <span class="info-box-icon"><i class="fas fa-calendar"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">ช่วงเวลาที่ 1</span>
                                        <span class="info-box-number"><?php echo $period1Display; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box bg-warning">
                                    <span class="info-box-icon"><i class="fas fa-calendar"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">ช่วงเวลาที่ 2</span>
                                        <span class="info-box-number"><?php echo $period2Display; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comparison Tables -->
        <?php foreach ($comparisonData as $key => $data): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-<?php 
                        echo $key == 'air' ? 'compress' : 
                            ($key == 'energy' ? 'bolt' : 
                            ($key == 'lpg' ? 'fire' : 
                            ($key == 'boiler' ? 'industry' : 'chart-line'))); 
                    ?>"></i>
                    <?php echo $data['name']; ?>
                </h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>รายการ</th>
                                <th class="text-center bg-info">ช่วงเวลาที่ 1</th>
                                <th class="text-center bg-warning">ช่วงเวลาที่ 2</th>
                                <th class="text-center">ความแตกต่าง</th>
                                <th class="text-center">% เปลี่ยนแปลง</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($key == 'air'): ?>
                            <tr>
                                <td>ปริมาณรวม</td>
                                <td class="text-right"><?php echo number_format($data['period1']['total'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($data['period2']['total'], 2); ?></td>
                                <td class="text-right">
                                    <?php 
                                    $diff = $data['period1']['total'] - $data['period2']['total'];
                                    ?>
                                    <span class="badge badge-<?php echo $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['total'] > 0 ? ($diff / $data['period2']['total']) * 100 : 0;
                                    ?>
                                    <span class="badge badge-<?php echo $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $percent > 0 ? '+' : ''; ?><?php echo number_format($percent, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>จำนวนบันทึก</td>
                                <td class="text-right"><?php echo number_format($data['period1']['records']); ?></td>
                                <td class="text-right"><?php echo number_format($data['period2']['records']); ?></td>
                                <td class="text-right">
                                    <?php 
                                    $diff = $data['period1']['records'] - $data['period2']['records'];
                                    ?>
                                    <span class="badge badge-<?php echo $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diff); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['records'] > 0 ? ($diff / $data['period2']['records']) * 100 : 0;
                                    ?>
                                    <span class="badge badge-<?php echo $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $percent > 0 ? '+' : ''; ?><?php echo number_format($percent, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>ค่าเฉลี่ย/วัน</td>
                                <td class="text-right"><?php echo number_format($data['period1']['average'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($data['period2']['average'], 2); ?></td>
                                <td class="text-right">
                                    <?php 
                                    $diff = $data['period1']['average'] - $data['period2']['average'];
                                    ?>
                                    <span class="badge badge-<?php echo $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['average'] > 0 ? ($diff / $data['period2']['average']) * 100 : 0;
                                    ?>
                                    <span class="badge badge-<?php echo $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $percent > 0 ? '+' : ''; ?><?php echo number_format($percent, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            
                            <?php elseif ($key == 'energy'): ?>
                            <tr>
                                <td>ปริมาณรวม</td>
                                <td class="text-right"><?php echo number_format($data['period1']['total'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($data['period2']['total'], 2); ?></td>
                                <td class="text-right">
                                    <?php 
                                    $diff = $data['period1']['total'] - $data['period2']['total'];
                                    ?>
                                    <span class="badge badge-<?php echo $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['total'] > 0 ? ($diff / $data['period2']['total']) * 100 : 0;
                                    ?>
                                    <span class="badge badge-<?php echo $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $percent > 0 ? '+' : ''; ?><?php echo number_format($percent, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>ไฟฟ้า</td>
                                <td class="text-right"><?php echo number_format($data['period1']['electricity'], 2); ?> kWh</td>
                                <td class="text-right"><?php echo number_format($data['period2']['electricity'], 2); ?> kWh</td>
                                <td class="text-right">
                                    <?php 
                                    $diff = $data['period1']['electricity'] - $data['period2']['electricity'];
                                    ?>
                                    <span class="badge badge-<?php echo $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['electricity'] > 0 ? ($diff / $data['period2']['electricity']) * 100 : 0;
                                    ?>
                                    <span class="badge badge-<?php echo $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $percent > 0 ? '+' : ''; ?><?php echo number_format($percent, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>น้ำ</td>
                                <td class="text-right"><?php echo number_format($data['period1']['water'], 2); ?> m³</td>
                                <td class="text-right"><?php echo number_format($data['period2']['water'], 2); ?> m³</td>
                                <td class="text-right">
                                    <?php 
                                    $diff = $data['period1']['water'] - $data['period2']['water'];
                                    ?>
                                    <span class="badge badge-<?php echo $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['water'] > 0 ? ($diff / $data['period2']['water']) * 100 : 0;
                                    ?>
                                    <span class="badge badge-<?php echo $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $percent > 0 ? '+' : ''; ?><?php echo number_format($percent, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            
                            <?php elseif ($key == 'lpg'): ?>
                            <tr>
                                <td>ปริมาณรวม</td>
                                <td class="text-right"><?php echo number_format($data['period1']['total'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($data['period2']['total'], 2); ?></td>
                                <td class="text-right">
                                    <?php 
                                    $diff = $data['period1']['total'] - $data['period2']['total'];
                                    ?>
                                    <span class="badge badge-<?php echo $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['total'] > 0 ? ($diff / $data['period2']['total']) * 100 : 0;
                                    ?>
                                    <span class="badge badge-<?php echo $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $percent > 0 ? '+' : ''; ?><?php echo number_format($percent, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>OK</td>
                                <td class="text-right"><?php echo number_format($data['period1']['ok_count']); ?></td>
                                <td class="text-right"><?php echo number_format($data['period2']['ok_count']); ?></td>
                                <td class="text-right">
                                    <?php 
                                    $diff = $data['period1']['ok_count'] - $data['period2']['ok_count'];
                                    ?>
                                    <span class="badge badge-<?php echo $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diff); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['ok_count'] > 0 ? ($diff / $data['period2']['ok_count']) * 100 : 0;
                                    ?>
                                    <span class="badge badge-<?php echo $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $percent > 0 ? '+' : ''; ?><?php echo number_format($percent, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>NG</td>
                                <td class="text-right"><?php echo number_format($data['period1']['ng_count']); ?></td>
                                <td class="text-right"><?php echo number_format($data['period2']['ng_count']); ?></td>
                                <td class="text-right">
                                    <?php 
                                    $diff = $data['period1']['ng_count'] - $data['period2']['ng_count'];
                                    ?>
                                    <span class="badge badge-<?php echo $diff < 0 ? 'success' : ($diff > 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diff); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['ng_count'] > 0 ? ($diff / $data['period2']['ng_count']) * 100 : 0;
                                    ?>
                                    <span class="badge badge-<?php echo $percent < 0 ? 'success' : ($percent > 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $percent > 0 ? '+' : ''; ?><?php echo number_format($percent, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            
                            <?php elseif ($key == 'boiler'): ?>
                            <tr>
                                <td>เชื้อเพลิง</td>
                                <td class="text-right"><?php echo number_format($data['period1']['fuel'], 2); ?> L</td>
                                <td class="text-right"><?php echo number_format($data['period2']['fuel'], 2); ?> L</td>
                                <td class="text-right">
                                    <?php 
                                    $diff = $data['period1']['fuel'] - $data['period2']['fuel'];
                                    ?>
                                    <span class="badge badge-<?php echo $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['fuel'] > 0 ? ($diff / $data['period2']['fuel']) * 100 : 0;
                                    ?>
                                    <span class="badge badge-<?php echo $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $percent > 0 ? '+' : ''; ?><?php echo number_format($percent, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>ชั่วโมงทำงาน</td>
                                <td class="text-right"><?php echo number_format($data['period1']['hours'], 1); ?> ชม.</td>
                                <td class="text-right"><?php echo number_format($data['period2']['hours'], 1); ?> ชม.</td>
                                <td class="text-right">
                                    <?php 
                                    $diff = $data['period1']['hours'] - $data['period2']['hours'];
                                    ?>
                                    <span class="badge badge-<?php echo $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diff, 1); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['hours'] > 0 ? ($diff / $data['period2']['hours']) * 100 : 0;
                                    ?>
                                    <span class="badge badge-<?php echo $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $percent > 0 ? '+' : ''; ?><?php echo number_format($percent, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            
                            <?php elseif ($key == 'summary'): ?>
                            <tr>
                                <td>หน่วยไฟฟ้า</td>
                                <td class="text-right"><?php echo number_format($data['period1']['total_ee'], 2); ?> kWh</td>
                                <td class="text-right"><?php echo number_format($data['period2']['total_ee'], 2); ?> kWh</td>
                                <td class="text-right">
                                    <?php 
                                    $diff = $data['period1']['total_ee'] - $data['period2']['total_ee'];
                                    ?>
                                    <span class="badge badge-<?php echo $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['total_ee'] > 0 ? ($diff / $data['period2']['total_ee']) * 100 : 0;
                                    ?>
                                    <span class="badge badge-<?php echo $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary'); ?>">
                                        <?php echo $percent > 0 ? '+' : ''; ?><?php echo number_format($percent, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>ค่าไฟฟ้า</td>
                                <td class="text-right"><?php echo number_format($data['period1']['total_cost'], 2); ?> บาท</td>
                                <td class="text-right"><?php echo number_format($data['period2']['total_cost'], 2); ?> บาท</td>
                                <td class="text-right">
                                    <?php 
                                    $diff = $data['period1']['total_cost'] - $data['period2']['total_cost'];
                                    ?>
                                    <span class="badge badge-<?php echo $diff > 0 ? 'danger' : ($diff < 0 ? 'success' : 'secondary'); ?>">
                                        <?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['total_cost'] > 0 ? ($diff / $data['period2']['total_cost']) * 100 : 0;
                                    ?>
                                    <span class="badge badge-<?php echo $percent > 0 ? 'danger' : ($percent < 0 ? 'success' : 'secondary'); ?>">
                                        <?php echo $percent > 0 ? '+' : ''; ?><?php echo number_format($percent, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Summary Chart -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-bar"></i>
                    กราฟเปรียบเทียบ
                </h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="comparisonChart" style="min-height: 400px;"></canvas>
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

<!-- PHP chart data output here so it's available as a JS global before/after jQuery loads -->
<script>
var comparisonChartData = null; // will be set after footer loads jQuery
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<!-- jQuery is now loaded - safe to use $() -->
<script>
let comparisonChart = null;

$(document).ready(function() {
    // Initialize date pickers
    $('#period1StartPicker, #period1EndPicker, #period2StartPicker, #period2EndPicker').datetimepicker({
        format: 'DD/MM/YYYY',
        locale: 'th',
        useCurrent: false
    });
    
    renderComparisonChart();
});

function setLastMonth() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    const lastMonthFirst = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    const lastMonthLast = new Date(today.getFullYear(), today.getMonth(), 0);
    
    $('#period1Start').val(formatDate(lastMonthFirst));
    $('#period1End').val(formatDate(lastMonthLast));
    $('#period2Start').val(formatDate(firstDay));
    $('#period2End').val(formatDate(lastDay));
    
    $('form').submit();
}

function setLastYear() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), 0, 1);
    const lastDay = new Date(today.getFullYear(), 11, 31);
    const lastYearFirst = new Date(today.getFullYear() - 1, 0, 1);
    const lastYearLast = new Date(today.getFullYear() - 1, 11, 31);
    
    $('#period1Start').val(formatDate(lastYearFirst));
    $('#period1End').val(formatDate(lastYearLast));
    $('#period2Start').val(formatDate(firstDay));
    $('#period2End').val(formatDate(lastDay));
    
    $('form').submit();
}

function formatDate(date) {
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return day + '/' + month + '/' + year;
}

function renderComparisonChart() {
    const ctx = document.getElementById('comparisonChart').getContext('2d');
    
    if (comparisonChart) {
        comparisonChart.destroy();
    }
    
    const data = <?php
        $chartData = [];
        foreach ($comparisonData as $key => $cData) {
            $chartData['labels'][] = $cData['name'];
            $chartData['period1'][] = $cData['period1']['total'] ?? $cData['period1']['total_ee'] ?? $cData['period1']['fuel'] ?? 0;
            $chartData['period2'][] = $cData['period2']['total'] ?? $cData['period2']['total_ee'] ?? $cData['period2']['fuel'] ?? 0;
        }
        echo json_encode($chartData);
    ?>;
    
    comparisonChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'ช่วงเวลาที่ 1',
                    data: data.period1,
                    backgroundColor: '#17a2b8',
                    borderColor: '#17a2b8',
                    borderWidth: 1
                },
                {
                    label: 'ช่วงเวลาที่ 2',
                    data: data.period2,
                    backgroundColor: '#ffc107',
                    borderColor: '#ffc107',
                    borderWidth: 1
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
                        text: 'ปริมาณ'
                    }
                }
            }
        }
    });
}

function exportReport(format) {
    const formData = $('form').serialize();
    window.location.href = 'export_report.php?type=comparison&format=' + format + '&' + formData;
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
// Footer already included above (before the jQuery-dependent script block)
?>