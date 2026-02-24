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

// Helper function to parse date
function parseDateParam($raw, $fallback) {
    $raw = trim($raw);
    // Check format DD/MM/YYYY
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $raw)) {
        $dt = DateTime::createFromFormat('d/m/Y', $raw);
        return $dt ? $dt->format('Y-m-d') : $fallback;
    }
    // Check format Y-m-d
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return $raw;
    }
    return $fallback;
}

// Get parameters
$period1Start = parseDateParam($_GET['period1_start'] ?? '', date('Y-m-01'));
$period1End   = parseDateParam($_GET['period1_end']   ?? '', date('Y-m-d'));
$period2Start = parseDateParam($_GET['period2_start'] ?? '', date('Y-m-d', strtotime('-1 month')));
$period2End   = parseDateParam($_GET['period2_end']   ?? '', date('Y-m-d', strtotime('-1 day')));

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
$air1 = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->execute([$period2Start, $period2End]);
$air2 = $stmt->fetch(PDO::FETCH_ASSOC);

$comparisonData['air'] = [
    'name' => 'Air Compressor',
    'period1' => [
        'total' => $air1['total'] ?? 0,
        'records' => $air1['records'] ?? 0,
        'average' => $air1['average'] ?? 0
    ],
    'period2' => [
        'total' => $air2['total'] ?? 0,
        'records' => $air2['records'] ?? 0,
        'average' => $air2['average'] ?? 0
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
$energy1 = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->execute([$period2Start, $period2End]);
$energy2 = $stmt->fetch(PDO::FETCH_ASSOC);

$comparisonData['energy'] = [
    'name' => 'Energy & Water',
    'period1' => [
        'total' => $energy1['total'] ?? 0,
        'records' => $energy1['records'] ?? 0,
        'average' => $energy1['average'] ?? 0,
        'electricity' => $energy1['electricity'] ?? 0,
        'water' => $energy1['water'] ?? 0
    ],
    'period2' => [
        'total' => $energy2['total'] ?? 0,
        'records' => $energy2['records'] ?? 0,
        'average' => $energy2['average'] ?? 0,
        'electricity' => $energy2['electricity'] ?? 0,
        'water' => $energy2['water'] ?? 0
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
$lpg1 = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->execute([$period2Start, $period2End]);
$lpg2 = $stmt->fetch(PDO::FETCH_ASSOC);

$comparisonData['lpg'] = [
    'name' => 'LPG',
    'period1' => [
        'total' => $lpg1['total'] ?? 0,
        'records' => $lpg1['records'] ?? 0,
        'ok_count' => $lpg1['ok_count'] ?? 0,
        'ng_count' => $lpg1['ng_count'] ?? 0
    ],
    'period2' => [
        'total' => $lpg2['total'] ?? 0,
        'records' => $lpg2['records'] ?? 0,
        'ok_count' => $lpg2['ok_count'] ?? 0,
        'ng_count' => $lpg2['ng_count'] ?? 0
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
$boiler1 = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->execute([$period2Start, $period2End]);
$boiler2 = $stmt->fetch(PDO::FETCH_ASSOC);

$comparisonData['boiler'] = [
    'name' => 'Boiler',
    'period1' => [
        'fuel' => $boiler1['fuel'] ?? 0,
        'hours' => $boiler1['hours'] ?? 0,
        'avg_pressure' => $boiler1['avg_pressure'] ?? 0,
        'avg_temp' => $boiler1['avg_temp'] ?? 0,
        'records' => $boiler1['records'] ?? 0
    ],
    'period2' => [
        'fuel' => $boiler2['fuel'] ?? 0,
        'hours' => $boiler2['hours'] ?? 0,
        'avg_pressure' => $boiler2['avg_pressure'] ?? 0,
        'avg_temp' => $boiler2['avg_temp'] ?? 0,
        'records' => $boiler2['records'] ?? 0
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
$summary1 = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->execute([$period2Start, $period2End]);
$summary2 = $stmt->fetch(PDO::FETCH_ASSOC);

$comparisonData['summary'] = [
    'name' => 'Summary Electricity',
    'period1' => [
        'total_ee' => $summary1['total_ee'] ?? 0,
        'total_cost' => $summary1['total_cost'] ?? 0,
        'avg_cost' => $summary1['avg_cost'] ?? 0,
        'records' => $summary1['records'] ?? 0
    ],
    'period2' => [
        'total_ee' => $summary2['total_ee'] ?? 0,
        'total_cost' => $summary2['total_cost'] ?? 0,
        'avg_cost' => $summary2['avg_cost'] ?? 0,
        'records' => $summary2['records'] ?? 0
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
                <form method="GET" class="form-horizontal" id="comparisonForm">
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
                                                   id="period1StartDisplay" 
                                                   value="<?php echo date('d/m/Y', strtotime($period1Start)); ?>" 
                                                   data-target="#period1StartPicker"
                                                   autocomplete="off">
                                            <div class="input-group-append" data-target="#period1StartPicker" data-toggle="datetimepicker">
                                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                            </div>
                                        </div>
                                        <input type="hidden" name="period1_start" id="period1StartHidden" value="<?php echo $period1Start; ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>วันที่สิ้นสุด</label>
                                        <div class="input-group date" id="period1EndPicker" data-target-input="nearest">
                                            <input type="text" class="form-control datetimepicker-input" 
                                                   id="period1EndDisplay" 
                                                   value="<?php echo date('d/m/Y', strtotime($period1End)); ?>" 
                                                   data-target="#period1EndPicker"
                                                   autocomplete="off">
                                            <div class="input-group-append" data-target="#period1EndPicker" data-toggle="datetimepicker">
                                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                            </div>
                                        </div>
                                        <input type="hidden" name="period1_end" id="period1EndHidden" value="<?php echo $period1End; ?>">
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
                                                   id="period2StartDisplay" 
                                                   value="<?php echo date('d/m/Y', strtotime($period2Start)); ?>" 
                                                   data-target="#period2StartPicker"
                                                   autocomplete="off">
                                            <div class="input-group-append" data-target="#period2StartPicker" data-toggle="datetimepicker">
                                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                            </div>
                                        </div>
                                        <input type="hidden" name="period2_start" id="period2StartHidden" value="<?php echo $period2Start; ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>วันที่สิ้นสุด</label>
                                        <div class="input-group date" id="period2EndPicker" data-target-input="nearest">
                                            <input type="text" class="form-control datetimepicker-input" 
                                                   id="period2EndDisplay" 
                                                   value="<?php echo date('d/m/Y', strtotime($period2End)); ?>" 
                                                   data-target="#period2EndPicker"
                                                   autocomplete="off">
                                            <div class="input-group-append" data-target="#period2EndPicker" data-toggle="datetimepicker">
                                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                            </div>
                                        </div>
                                        <input type="hidden" name="period2_end" id="period2EndHidden" value="<?php echo $period2End; ?>">
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
                                <div class="info-box bg-warning">
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
                                    $diffClass = $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary');
                                    $diffSign = $diff > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $diffClass; ?>">
                                        <?php echo $diffSign . number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['total'] > 0 ? ($diff / $data['period2']['total']) * 100 : 0;
                                    $percentClass = $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary');
                                    $percentSign = $percent > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $percentClass; ?>">
                                        <?php echo $percentSign . number_format($percent, 1); ?>%
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
                                    $diffClass = $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary');
                                    $diffSign = $diff > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $diffClass; ?>">
                                        <?php echo $diffSign . number_format($diff); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['records'] > 0 ? ($diff / $data['period2']['records']) * 100 : 0;
                                    $percentClass = $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary');
                                    $percentSign = $percent > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $percentClass; ?>">
                                        <?php echo $percentSign . number_format($percent, 1); ?>%
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
                                    $diffClass = $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary');
                                    $diffSign = $diff > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $diffClass; ?>">
                                        <?php echo $diffSign . number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['average'] > 0 ? ($diff / $data['period2']['average']) * 100 : 0;
                                    $percentClass = $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary');
                                    $percentSign = $percent > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $percentClass; ?>">
                                        <?php echo $percentSign . number_format($percent, 1); ?>%
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
                                    $diffClass = $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary');
                                    $diffSign = $diff > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $diffClass; ?>">
                                        <?php echo $diffSign . number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['total'] > 0 ? ($diff / $data['period2']['total']) * 100 : 0;
                                    $percentClass = $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary');
                                    $percentSign = $percent > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $percentClass; ?>">
                                        <?php echo $percentSign . number_format($percent, 1); ?>%
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
                                    $diffClass = $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary');
                                    $diffSign = $diff > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $diffClass; ?>">
                                        <?php echo $diffSign . number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['electricity'] > 0 ? ($diff / $data['period2']['electricity']) * 100 : 0;
                                    $percentClass = $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary');
                                    $percentSign = $percent > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $percentClass; ?>">
                                        <?php echo $percentSign . number_format($percent, 1); ?>%
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
                                    $diffClass = $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary');
                                    $diffSign = $diff > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $diffClass; ?>">
                                        <?php echo $diffSign . number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['water'] > 0 ? ($diff / $data['period2']['water']) * 100 : 0;
                                    $percentClass = $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary');
                                    $percentSign = $percent > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $percentClass; ?>">
                                        <?php echo $percentSign . number_format($percent, 1); ?>%
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
                                    $diffClass = $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary');
                                    $diffSign = $diff > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $diffClass; ?>">
                                        <?php echo $diffSign . number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['total'] > 0 ? ($diff / $data['period2']['total']) * 100 : 0;
                                    $percentClass = $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary');
                                    $percentSign = $percent > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $percentClass; ?>">
                                        <?php echo $percentSign . number_format($percent, 1); ?>%
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
                                    $diffClass = $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary');
                                    $diffSign = $diff > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $diffClass; ?>">
                                        <?php echo $diffSign . number_format($diff); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['ok_count'] > 0 ? ($diff / $data['period2']['ok_count']) * 100 : 0;
                                    $percentClass = $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary');
                                    $percentSign = $percent > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $percentClass; ?>">
                                        <?php echo $percentSign . number_format($percent, 1); ?>%
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
                                    // NG ที่ลดลง是好 (success), ที่เพิ่มขึ้น是坏 (danger)
                                    $diffClass = $diff < 0 ? 'success' : ($diff > 0 ? 'danger' : 'secondary');
                                    $diffSign = $diff > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $diffClass; ?>">
                                        <?php echo $diffSign . number_format($diff); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['ng_count'] > 0 ? ($diff / $data['period2']['ng_count']) * 100 : 0;
                                    $percentClass = $percent < 0 ? 'success' : ($percent > 0 ? 'danger' : 'secondary');
                                    $percentSign = $percent > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $percentClass; ?>">
                                        <?php echo $percentSign . number_format($percent, 1); ?>%
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
                                    $diffClass = $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary');
                                    $diffSign = $diff > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $diffClass; ?>">
                                        <?php echo $diffSign . number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['fuel'] > 0 ? ($diff / $data['period2']['fuel']) * 100 : 0;
                                    $percentClass = $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary');
                                    $percentSign = $percent > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $percentClass; ?>">
                                        <?php echo $percentSign . number_format($percent, 1); ?>%
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
                                    $diffClass = $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary');
                                    $diffSign = $diff > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $diffClass; ?>">
                                        <?php echo $diffSign . number_format($diff, 1); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['hours'] > 0 ? ($diff / $data['period2']['hours']) * 100 : 0;
                                    $percentClass = $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary');
                                    $percentSign = $percent > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $percentClass; ?>">
                                        <?php echo $percentSign . number_format($percent, 1); ?>%
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
                                    $diffClass = $diff > 0 ? 'success' : ($diff < 0 ? 'danger' : 'secondary');
                                    $diffSign = $diff > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $diffClass; ?>">
                                        <?php echo $diffSign . number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['total_ee'] > 0 ? ($diff / $data['period2']['total_ee']) * 100 : 0;
                                    $percentClass = $percent > 0 ? 'success' : ($percent < 0 ? 'danger' : 'secondary');
                                    $percentSign = $percent > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $percentClass; ?>">
                                        <?php echo $percentSign . number_format($percent, 1); ?>%
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
                                    // ค่าไฟที่เพิ่มขึ้น是好? จริงๆควรเป็น danger
                                    $diffClass = $diff > 0 ? 'danger' : ($diff < 0 ? 'success' : 'secondary');
                                    $diffSign = $diff > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $diffClass; ?>">
                                        <?php echo $diffSign . number_format($diff, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php 
                                    $percent = $data['period2']['total_cost'] > 0 ? ($diff / $data['period2']['total_cost']) * 100 : 0;
                                    $percentClass = $percent > 0 ? 'danger' : ($percent < 0 ? 'success' : 'secondary');
                                    $percentSign = $percent > 0 ? '+' : '';
                                    ?>
                                    <span class="badge badge-<?php echo $percentClass; ?>">
                                        <?php echo $percentSign . number_format($percent, 1); ?>%
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

<?php
// Include footer
require_once __DIR__ . '/../includes/footer.php';
?>

<script>
let comparisonChart = null;

$(document).ready(function() {
    // Helper function to convert Date to YYYY-MM-DD
    function toYMD(d) {
        return d.getFullYear() + '-' +
               String(d.getMonth() + 1).padStart(2, '0') + '-' +
               String(d.getDate()).padStart(2, '0');
    }

    // Initialize all 4 date pickers
    const pickerConfig = [
        { picker: '#period1StartPicker', hidden: '#period1StartHidden', display: '#period1StartDisplay' },
        { picker: '#period1EndPicker', hidden: '#period1EndHidden', display: '#period1EndDisplay' },
        { picker: '#period2StartPicker', hidden: '#period2StartHidden', display: '#period2StartDisplay' },
        { picker: '#period2EndPicker', hidden: '#period2EndHidden', display: '#period2EndDisplay' }
    ];
    
    pickerConfig.forEach(function(item) {
        // Get initial date from hidden input
        const hiddenValue = $(item.hidden).val();
        let initialDate = moment();
        
        if (hiddenValue && hiddenValue.match(/^\d{4}-\d{2}-\d{2}$/)) {
            initialDate = moment(hiddenValue, 'YYYY-MM-DD');
        }
        
        // Initialize datetimepicker
        $(item.picker).datetimepicker({
            format: 'DD/MM/YYYY',
            locale: 'th',
            useCurrent: false,
            defaultDate: initialDate
        });
        
        // Update hidden input when date changes
        $(item.picker).on('change.datetimepicker', function(e) {
            if (e.date) {
                const ymd = e.date.format('YYYY-MM-DD');
                $(item.hidden).val(ymd);
                
                // Also update display if needed
                if ($(item.display).length) {
                    $(item.display).val(e.date.format('DD/MM/YYYY'));
                }
            }
        });
    });

    // Prevent form submission with empty values
    $('#comparisonForm').on('submit', function(e) {
        pickerConfig.forEach(function(item) {
            const hidden = $(item.hidden);
            if (!hidden.val()) {
                const display = $(item.display).val();
                if (display && display.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
                    const parts = display.split('/');
                    hidden.val(parts[2] + '-' + parts[1] + '-' + parts[0]);
                }
            }
        });
    });

    // Render comparison chart
    renderComparisonChart();
});

// Function to set last month vs current month
function setLastMonth() {
    const today = new Date();
    
    // Period 1: Last month (full month)
    const lastMonthFirst = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    const lastMonthLast = new Date(today.getFullYear(), today.getMonth(), 0);
    
    // Period 2: Current month (up to today)
    const currentMonthFirst = new Date(today.getFullYear(), today.getMonth(), 1);
    const currentMonthLast = today;
    
    updateDates(lastMonthFirst, lastMonthLast, currentMonthFirst, currentMonthLast);
}

// Function to set last year vs current year
function setLastYear() {
    const today = new Date();
    
    // Period 1: Last year
    const lastYearFirst = new Date(today.getFullYear() - 1, 0, 1);
    const lastYearLast = new Date(today.getFullYear() - 1, 11, 31);
    
    // Period 2: Current year (up to today)
    const currentYearFirst = new Date(today.getFullYear(), 0, 1);
    const currentYearLast = today;
    
    updateDates(lastYearFirst, lastYearLast, currentYearFirst, currentYearLast);
}

// Helper function to update all date pickers
function updateDates(p1Start, p1End, p2Start, p2End) {
    function formatYMD(d) {
        return d.getFullYear() + '-' +
               String(d.getMonth() + 1).padStart(2, '0') + '-' +
               String(d.getDate()).padStart(2, '0');
    }
    
    function formatDMY(d) {
        return String(d.getDate()).padStart(2, '0') + '/' +
               String(d.getMonth() + 1).padStart(2, '0') + '/' +
               d.getFullYear();
    }
    
    // Update hidden inputs
    $('#period1StartHidden').val(formatYMD(p1Start));
    $('#period1EndHidden').val(formatYMD(p1End));
    $('#period2StartHidden').val(formatYMD(p2Start));
    $('#period2EndHidden').val(formatYMD(p2End));
    
    // Update pickers
    $('#period1StartPicker').datetimepicker('date', moment(p1Start));
    $('#period1EndPicker').datetimepicker('date', moment(p1End));
    $('#period2StartPicker').datetimepicker('date', moment(p2Start));
    $('#period2EndPicker').datetimepicker('date', moment(p2End));
    
    // Update display inputs
    $('#period1StartDisplay').val(formatDMY(p1Start));
    $('#period1EndDisplay').val(formatDMY(p1End));
    $('#period2StartDisplay').val(formatDMY(p2Start));
    $('#period2EndDisplay').val(formatDMY(p2End));
    
    // Submit form
    $('#comparisonForm').submit();
}

// Render chart function
function renderComparisonChart() {
    const ctx = document.getElementById('comparisonChart').getContext('2d');
    
    // Destroy existing chart if it exists
    if (comparisonChart) {
        comparisonChart.destroy();
    }
    
    // Prepare chart data from PHP
    const chartData = <?php
        $chartLabels = [];
        $period1Data = [];
        $period2Data = [];
        
        foreach ($comparisonData as $key => $cData) {
            $chartLabels[] = $cData['name'];
            // Get appropriate value for each module
            if ($key == 'air') {
                $period1Data[] = $cData['period1']['total'] ?? 0;
                $period2Data[] = $cData['period2']['total'] ?? 0;
            } elseif ($key == 'energy') {
                $period1Data[] = $cData['period1']['total'] ?? 0;
                $period2Data[] = $cData['period2']['total'] ?? 0;
            } elseif ($key == 'lpg') {
                $period1Data[] = $cData['period1']['total'] ?? 0;
                $period2Data[] = $cData['period2']['total'] ?? 0;
            } elseif ($key == 'boiler') {
                $period1Data[] = $cData['period1']['fuel'] ?? 0;
                $period2Data[] = $cData['period2']['fuel'] ?? 0;
            } elseif ($key == 'summary') {
                $period1Data[] = $cData['period1']['total_ee'] ?? 0;
                $period2Data[] = $cData['period2']['total_ee'] ?? 0;
            }
        }
        
        echo json_encode([
            'labels' => $chartLabels,
            'period1' => $period1Data,
            'period2' => $period2Data
        ]);
    ?>;
    
    // Create new chart
    comparisonChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [
                {
                    label: 'ช่วงเวลาที่ 1',
                    data: chartData.period1,
                    backgroundColor: 'rgba(23, 162, 184, 0.7)',
                    borderColor: '#17a2b8',
                    borderWidth: 1
                },
                {
                    label: 'ช่วงเวลาที่ 2',
                    data: chartData.period2,
                    backgroundColor: 'rgba(255, 193, 7, 0.7)',
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
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            let value = context.raw || 0;
                            return label + ': ' + value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
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
                    },
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Update EUMS state if available
    if (window.EUMS && EUMS.state) {
        if (!EUMS.state.charts) EUMS.state.charts = {};
        EUMS.state.charts.comparison = comparisonChart;
    }
}

// Export report function
function exportReport(format) {
    const formData = $('#comparisonForm').serialize();
    window.location.href = 'export_report.php?type=comparison&format=' + format + '&' + formData;
}
</script>

<style>
@media print {
    .btn, .card-tools, .main-footer, .main-header, .main-sidebar,
    .card-header .card-tools, form .btn, .export-buttons {
        display: none !important;
    }
    .content-wrapper {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    .card {
        border: 1px solid #dee2e6 !important;
        break-inside: avoid;
        page-break-inside: avoid;
    }
    .table {
        font-size: 10pt;
    }
    .badge {
        border: 1px solid #000 !important;
        color: #000 !important;
        background: transparent !important;
    }
}
</style>