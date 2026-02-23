<?php
/**
 * Summary Electricity Module - Main Index Page (Monthly)
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Summary Electricity - บันทึกข้อมูลพลังงานรวมรายเดือน';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Summary Electricity', 'link' => null],
    ['title' => 'บันทึกข้อมูลรายเดือน', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Load required files
require_once __DIR__ . '/../../includes/functions.php';

// Get database connection
$db = getDB();

// Get current month and year
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selectedMonth = "$currentYear-$currentMonth-01";

// Get document info
$stmt = $db->prepare("
    SELECT * FROM documents 
    WHERE module_type = 'summary' 
    AND MONTH(start_date) = ? 
    AND YEAR(start_date) = ?
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([$currentMonth, $currentYear]);
$document = $stmt->fetch();

// Get summary record for current month
$stmt = $db->prepare("
    SELECT * FROM electricity_summary 
    WHERE MONTH(record_date) = ? AND YEAR(record_date) = ?
");
$stmt->execute([$currentMonth, $currentYear]);
$record = $stmt->fetch();

// Get chart data for the year
$stmt = $db->prepare("
    SELECT 
        MONTH(record_date) as month,
        SUM(ee_unit) as total_ee,
        SUM(water_unit) as total_water,
        SUM(total_cost) as total_cost,
        SUM(total_water_cost) as total_water_cost,
        AVG(cost_per_unit) as avg_cost_per_unit,
        AVG(water_cost_per_unit) as avg_water_cost_per_unit
    FROM electricity_summary
    WHERE YEAR(record_date) = ?
    GROUP BY MONTH(record_date)
    ORDER BY month
");
$stmt->execute([$currentYear]);
$yearlyData = $stmt->fetchAll();

// Create array for all months
$monthlyEE = array_fill(1, 12, 0);
$monthlyWater = array_fill(1, 12, 0);
$monthlyCost = array_fill(1, 12, 0);
$monthlyWaterCost = array_fill(1, 12, 0);

foreach ($yearlyData as $data) {
    $monthlyEE[$data['month']] = round($data['total_ee'], 2);
    $monthlyWater[$data['month']] = round($data['total_water'], 2);
    $monthlyCost[$data['month']] = round($data['total_cost'], 2);
    $monthlyWaterCost[$data['month']] = round($data['total_water_cost'], 2);
}

// Calculate yearly totals
$totalYearlyEE = array_sum($monthlyEE);
$totalYearlyWater = array_sum($monthlyWater);
$totalYearlyCost = array_sum($monthlyCost);
$totalYearlyWaterCost = array_sum($monthlyWaterCost);
?>
<style>
    /* ปรับสีหัวข้อให้เด่นชัดขึ้น */
.card-header h3 {
    color: #333 !important; /* เปลี่ยนเป็นสีเข้ม */
    font-weight: 600;
    margin-bottom: 0;
}

/* ปรับแต่งปุ่ม Toolbar (Export/Print) ให้มองเห็นชัดเจน */
.card-header .card-tools .btn {
    background-color: #ffffff;
    border: 1px solid #ddd;
    color: #444;
    margin-left: 5px;
    transition: all 0.3s;
}

.card-header .card-tools .btn:hover {
    background-color: #f8f9fa;
    color: #007bff;
    border-color: #007bff;
}

/* แก้ไขกรณีตัวหนังสือในตารางตัวเล็กเกินไป */
.table thead th {
    background-color: #f4f6f9;
    color: #333;
    text-align: center;
    vertical-align: middle !important;
}

/* เพิ่ม Contrast ให้กับข้อความใน Card */
.card-title {
    font-size: 1.2rem;
    color: #2c3e50;
}
/* บังคับให้ข้อความใน card-header ของ card-success เป็นสีดำ/เข้ม */
.card-success:not(.card-outline) > .card-header, 
.card-success:not(.card-outline) > .card-header a,
.card-success:not(.card-outline) > .card-header .card-title {
    color: #333 !important; /* หรือสีที่คุณต้องการ */
}

/* ปรับสีไอคอนและปุ่มใน header ให้เห็นชัดขึ้น */
.card-success:not(.card-outline) > .card-header .btn-tool {
    color: rgba(0,0,0,0.8) !important;
}
/* ปรับตัวหนังสือใน Info Box ทุกสีให้มองเห็นชัดขึ้น */
.info-box {
    color: #333 !important; /* เปลี่ยนตัวหนังสือหลักเป็นสีเข้ม */
}

/* บังคับสีข้อความใน Info Box แม้จะใช้คลาส bg- ต่างๆ */
.info-box .info-box-text, 
.info-box .info-box-number {
    color: #520404ff !important; /* ถ้าต้องการให้เป็นสีขาวที่ชัดเจน (Shadow ช่วยได้) */
    text-shadow: 1px 1px 2px rgba(0,0,0,0.2); /* เพิ่มเงาจางๆ ให้ตัวหนังสือลอยออกมา */
}

/* สำหรับสีเหลือง (bg-warning) มักจะมีปัญหาที่สุด แนะนำให้เปลี่ยนตัวหนังสือเป็นสีเข้มเฉพาะสีนี้ */
.bg-warning, .bg-warning .info-box-text, .bg-warning .info-box-number {
    color: #1f2d3d !important;
}

/* ปรับตารางด้านล่างให้เส้นขอบชัดและพื้นหลังส่วนหัวข้อ (th) ดูง่ายขึ้น */
.table-bordered th {
    background-color: #f8f9fa;
    color: #333;
}
</style>
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Document Info Card -->
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-alt"></i>
                    ข้อมูลเอกสาร
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form id="documentForm">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>เลขที่เอกสาร</label>
                                <input type="text" class="form-control" name="doc_no" 
                                       value="<?php echo $document['doc_no'] ?? 'SUM-' . ($currentYear + 543) . '-' . str_pad($currentMonth, 2, '0', STR_PAD_LEFT); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>วันที่เริ่มใช้</label>
                                <input type="text" class="form-control datepicker" name="start_date" 
                                       value="<?php echo isset($document['start_date']) ? date('d/m/Y', strtotime($document['start_date'])) : '01/' . str_pad($currentMonth, 2, '0', STR_PAD_LEFT) . '/' . ($currentYear + 543); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Rev.No.</label>
                                <input type="text" class="form-control" name="rev_no" 
                                       value="<?php echo $document['rev_no'] ?? '00'; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="button" class="btn btn-primary btn-block" id="updateDocument">
                                    <i class="fas fa-save"></i> อัปเดตเอกสาร
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>รายละเอียด</label>
                                <textarea class="form-control" name="details" rows="2"><?php echo $document['details'] ?? ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Month Selector -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-alt"></i>
                    เลือกเดือน
                </h3>
            </div>
            <div class="card-body">
                <form method="GET" class="form-inline">
                    <div class="form-group mr-2">
                        <label class="mr-2">เดือน:</label>
                        <select name="month" class="form-control">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m == $currentMonth ? 'selected' : ''; ?>>
                                    <?php echo getThaiMonth($m); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group mr-2">
                        <label class="mr-2">ปี:</label>
                        <select name="year" class="form-control">
                            <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>>
                                    <?php echo $y + 543; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> แสดงข้อมูล
                    </button>
                </form>
            </div>
        </div>

        <!-- Current Month Record Card -->
        <div class="card card-success card-outline">
    <div class="card-header">
        <h3 class="card-title" style="color: #28a745;"> <i class="fas fa-chart-bar"></i> บันทึกข้อมูลรายเดือน
        </h3>
                <div class="card-tools">
                    <?php if ($record): ?>
                        <button type="button" class="btn btn-warning btn-sm" onclick="editRecord(<?php echo $record['id']; ?>)">
                            <i class="fas fa-edit"></i> แก้ไข
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteRecord(<?php echo $record['id']; ?>)">
                            <i class="fas fa-trash"></i> ลบ
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary btn-sm" onclick="showAddModal()">
                            <i class="fas fa-plus"></i> เพิ่มบันทึก
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if ($record): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-box bg-info">
                        <span class="info-box-icon"><i class="fas fa-bolt"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">หน่วยไฟฟ้า (EE)</span>
                                <span class="info-box-number"><?php echo number_format($record['ee_unit'], 2); ?> kWh</span>
                            </div>
                        </div>
                        <div class="info-box bg-success">
                            <span class="info-box-icon"><i class="fas fa-tint"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">หน่วยน้ำ (Water)</span>
                                <span class="info-box-number"><?php echo number_format($record['water_unit'] ?? 0, 2); ?> m³</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-box bg-info">
                            <span class="info-box-icon"><i class="fas fa-coins"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">ค่าไฟต่อหน่วย</span>
                                <span class="info-box-number"><?php echo number_format($record['cost_per_unit'], 4); ?> บาท</span>
                                <span class="info-box-text">ค่าไฟรวม: <?php echo number_format($record['total_cost'], 2); ?> บาท</span>
                            </div>
                        </div>
                        <div class="info-box bg-danger">
                            <span class="info-box-icon"><i class="fas fa-water"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">ค่าน้ำต่อหน่วย</span>
                                <span class="info-box-number"><?php echo number_format($record['water_cost_per_unit'] ?? 0, 4); ?> บาท</span>
                                <span class="info-box-text">ค่าน้ำรวม: <?php echo number_format($record['total_water_cost'] ?? 0, 2); ?> บาท</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <table class="table table-bordered">
                            <tr>
                                <th>PE</th>
                                <td><?php echo $record['pe'] ? number_format($record['pe'], 4) : '-'; ?></td>
                                <th>ผู้บันทึก</th>
                                <td><?php echo htmlspecialchars($record['recorded_by']); ?></td>
                            </tr>
                            <?php if ($record['remarks']): ?>
                            <tr>
                                <th>หมายเหตุ</th>
                                <td colspan="3"><?php echo nl2br(htmlspecialchars($record['remarks'])); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    ยังไม่มีบันทึกข้อมูลสำหรับเดือนนี้ กรุณาคลิกปุ่ม "เพิ่มบันทึก" เพื่อบันทึกข้อมูล
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Yearly Summary Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo number_format($totalYearlyEE, 2); ?></h3>
                        <p>หน่วยไฟฟ้ารวมปี <?php echo $currentYear + 543; ?> (kWh)</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo number_format($totalYearlyWater, 2); ?></h3>
                        <p>หน่วยน้ำรวมปี <?php echo $currentYear + 543; ?> (m³)</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-tint"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo number_format($totalYearlyCost, 2); ?></h3>
                        <p>ค่าไฟฟ้ารวมปี (บาท)</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo number_format($totalYearlyWaterCost, 2); ?></h3>
                        <p>ค่าน้ำรวมปี (บาท)</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-water"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-bar"></i>
                            หน่วยไฟฟ้าและน้ำรายเดือน ปี <?php echo $currentYear + 543; ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="usageChart" style="min-height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            ค่าไฟฟ้าและค่าน้ำรายเดือน ปี <?php echo $currentYear + 543; ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="costChart" style="min-height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Records Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table"></i>
                    ข้อมูลสรุปรายเดือน ปี <?php echo $currentYear + 543; ?>
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-success btn-sm" onclick="exportData()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped dataTable">
                        <thead>
                            <tr>
                                <th>เดือน</th>
                                <th>หน่วยไฟฟ้า (kWh)</th>
                                <th>หน่วยน้ำ (m³)</th>
                                <th>ค่าไฟ/หน่วย</th>
                                <th>ค่าน้ำ/หน่วย</th>
                                <th>ค่าไฟฟ้า</th>
                                <th>ค่าน้ำ</th>
                                <th>รวม</th>
                                <th>PE</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 
                                      'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                            
                            $stmt = $db->prepare("
                                SELECT * FROM electricity_summary 
                                WHERE YEAR(record_date) = ?
                                ORDER BY record_date
                            ");
                            $stmt->execute([$currentYear]);
                            $monthlyRecords = $stmt->fetchAll();
                            
                            $recordByMonth = [];
                            foreach ($monthlyRecords as $rec) {
                                $recordByMonth[(int)date('m', strtotime($rec['record_date']))] = $rec;
                            }
                            
                            for ($m = 1; $m <= 12; $m++): 
                                $rec = $recordByMonth[$m] ?? null;
                            ?>
                            <tr>
                                <td><?php echo $months[$m-1]; ?></td>
                                <td class="text-right"><?php echo $rec ? number_format($rec['ee_unit'], 2) : '-'; ?></td>
                                <td class="text-right"><?php echo $rec && isset($rec['water_unit']) ? number_format($rec['water_unit'], 2) : '-'; ?></td>
                                <td class="text-right"><?php echo $rec ? number_format($rec['cost_per_unit'], 4) : '-'; ?></td>
                                <td class="text-right"><?php echo $rec && isset($rec['water_cost_per_unit']) ? number_format($rec['water_cost_per_unit'], 4) : '-'; ?></td>
                                <td class="text-right"><?php echo $rec ? number_format($rec['total_cost'], 2) : '-'; ?></td>
                                <td class="text-right"><?php echo $rec && isset($rec['total_water_cost']) ? number_format($rec['total_water_cost'], 2) : '-'; ?></td>
                                <td class="text-right">
                                    <?php 
                                    if ($rec) {
                                        $total = $rec['total_cost'] + ($rec['total_water_cost'] ?? 0);
                                        echo number_format($total, 2);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td class="text-right"><?php echo $rec && $rec['pe'] ? number_format($rec['pe'], 4) : '-'; ?></td>
                                <td>
                                    <?php if ($rec): ?>
                                    <button class="btn btn-sm btn-warning" onclick="editRecord(<?php echo $rec['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteRecord(<?php echo $rec['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-primary" onclick="showAddModal(<?php echo $m; ?>)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray">
                                <th class="text-right">รวม</th>
                                <th class="text-right"><?php echo number_format($totalYearlyEE, 2); ?></th>
                                <th class="text-right"><?php echo number_format($totalYearlyWater, 2); ?></th>
                                <th class="text-right">-</th>
                                <th class="text-right">-</th>
                                <th class="text-right"><?php echo number_format($totalYearlyCost, 2); ?></th>
                                <th class="text-right"><?php echo number_format($totalYearlyWaterCost, 2); ?></th>
                                <th class="text-right"><?php echo number_format($totalYearlyCost + $totalYearlyWaterCost, 2); ?></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Add/Edit Modal -->
<div class="modal fade" id="recordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">เพิ่มบันทึกข้อมูลรายเดือน</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="recordForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="recordId">
                    <input type="hidden" name="doc_id" value="<?php echo $document['id'] ?? 0; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>เดือน <span class="text-danger">*</span></label>
                                <select class="form-control" name="month" id="monthSelect" required>
                                    <option value="">เลือกเดือน</option>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>"><?php echo getThaiMonth($m); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ปี <span class="text-danger">*</span></label>
                                <select class="form-control" name="year" id="yearSelect" required>
                                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>>
                                        <?php echo $y + 543; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card card-info mt-3">
                        <div class="card-header">
                            <h5 class="card-title">ข้อมูลไฟฟ้า</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>หน่วยไฟฟ้า (EE) - kWh <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" class="form-control" name="ee_unit" id="eeUnit" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>ค่าไฟต่อหน่วย (บาท) <span class="text-danger">*</span></label>
                                        <input type="number" step="0.0001" class="form-control" name="cost_per_unit" id="costPerUnit" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>ค่าไฟฟ้า (บาท)</label>
                                        <input type="text" class="form-control" id="totalCost" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card card-success mt-3">
                        <div class="card-header">
                            <h5 class="card-title">ข้อมูลน้ำ</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>หน่วยน้ำ (m³)</label>
                                        <input type="number" step="0.01" class="form-control" name="water_unit" id="waterUnit" value="0">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>ค่าน้ำต่อหน่วย (บาท)</label>
                                        <input type="number" step="0.0001" class="form-control" name="water_cost_per_unit" id="waterCostPerUnit" value="0">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>ค่าน้ำ (บาท)</label>
                                        <input type="text" class="form-control" id="totalWaterCost" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>รวมค่าใช้จ่าย (บาท)</label>
                                        <input type="text" class="form-control" id="totalAllCost" readonly class="bg-light">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>PE (Power Factor)</label>
                                <input type="number" step="0.0001" min="0" max="1" class="form-control" name="pe" id="pe">
                                <small class="text-muted">ค่าระหว่าง 0-1</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>หมายเหตุ</label>
                        <textarea class="form-control" name="remarks" id="remarks" rows="2"></textarea>
                    </div>
                    
                    <div class="alert alert-warning" id="duplicateAlert" style="display: none;">
                        <i class="fas fa-exclamation-triangle"></i>
                        มีบันทึกข้อมูลสำหรับเดือนนี้แล้ว การบันทึกจะเขียนทับข้อมูลเดิม
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>

<script>
let usageChart = null;
let costChart = null;

$(document).ready(function() {
    // Initialize datepicker (for document start date only)
    $('.datepicker').datepicker({
        format: 'dd/mm/yyyy',
        autoclose: true,
        language: 'th'
    });
    
    // Load charts
    renderCharts();
    
    // Calculate costs
    $('#eeUnit, #costPerUnit, #waterUnit, #waterCostPerUnit').on('input', function() {
        calculateCosts();
    });
    
    // Check for duplicate when month/year changes
    $('#monthSelect, #yearSelect').on('change', function() {
        checkDuplicate();
    });
    
    // Form submit
    $('#recordForm').on('submit', function(e) {
        e.preventDefault();
        saveRecord();
    });
    
    // Update document
    $('#updateDocument').on('click', function() {
        updateDocument();
    });
});

function renderCharts() {
    const monthNames = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 
                        'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    
    const eeData = [<?php echo implode(',', $monthlyEE); ?>];
    const waterData = [<?php echo implode(',', $monthlyWater); ?>];
    const costData = [<?php echo implode(',', $monthlyCost); ?>];
    const waterCostData = [<?php echo implode(',', $monthlyWaterCost); ?>];
    
    // Usage Chart
    const usageCtx = document.getElementById('usageChart').getContext('2d');
    if (usageChart) usageChart.destroy();
    
    usageChart = new Chart(usageCtx, {
        type: 'bar',
        data: {
            labels: monthNames,
            datasets: [
                {
                    label: 'หน่วยไฟฟ้า (kWh)',
                    data: eeData,
                    backgroundColor: 'rgba(0, 123, 255, 0.5)',
                    borderColor: '#007bff',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'หน่วยน้ำ (m³)',
                    data: waterData,
                    backgroundColor: 'rgba(40, 167, 69, 0.5)',
                    borderColor: '#28a745',
                    borderWidth: 1,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.raw.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'kWh'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'm³'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
    
    // Cost Chart
    const costCtx = document.getElementById('costChart').getContext('2d');
    if (costChart) costChart.destroy();
    
    costChart = new Chart(costCtx, {
        type: 'line',
        data: {
            labels: monthNames,
            datasets: [
                {
                    label: 'ค่าไฟฟ้า (บาท)',
                    data: costData,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y'
                },
                {
                    label: 'ค่าน้ำ (บาท)',
                    data: waterCostData,
                    borderColor: '#17a2b8',
                    backgroundColor: 'rgba(23, 162, 184, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.raw.toFixed(2) + ' บาท';
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'ค่าไฟฟ้า (บาท)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'ค่าน้ำ (บาท)'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}

function calculateCosts() {
    const ee = parseFloat($('#eeUnit').val()) || 0;
    const costPerUnit = parseFloat($('#costPerUnit').val()) || 0;
    const water = parseFloat($('#waterUnit').val()) || 0;
    const waterCostPerUnit = parseFloat($('#waterCostPerUnit').val()) || 0;
    
    const totalCost = (ee * costPerUnit).toFixed(2);
    const totalWaterCost = (water * waterCostPerUnit).toFixed(2);
    const totalAll = (parseFloat(totalCost) + parseFloat(totalWaterCost)).toFixed(2);
    
    $('#totalCost').val(totalCost);
    $('#totalWaterCost').val(totalWaterCost);
    $('#totalAllCost').val(totalAll);
}

function checkDuplicate() {
    const month = $('#monthSelect').val();
    const year = $('#yearSelect').val();
    const id = $('#recordId').val();
    
    if (month && year) {
        $.ajax({
            url: 'ajax/check_month.php',
            method: 'POST',
            data: {
                month: month,
                year: year,
                id: id
            },
            success: function(response) {
                if (response.exists) {
                    $('#duplicateAlert').show();
                } else {
                    $('#duplicateAlert').hide();
                }
            }
        });
    }
}

function showAddModal(month = null) {
    $('#modalTitle').text('เพิ่มบันทึกข้อมูลรายเดือน');
    $('#recordForm')[0].reset();
    $('#recordId').val('');
    $('#totalCost').val('');
    $('#totalWaterCost').val('');
    $('#totalAllCost').val('');
    $('#duplicateAlert').hide();
    
    if (month) {
        $('#monthSelect').val(month);
    }
    
    $('#recordModal').modal('show');
}

function editRecord(id) {
    $.ajax({
        url: 'ajax/get_record.php',
        method: 'GET',
        data: { id: id },
        success: function(response) {
            if (response.success) {
                $('#modalTitle').text('แก้ไขบันทึกข้อมูลรายเดือน');
                $('#recordId').val(response.data.id);
                
                const date = new Date(response.data.record_date);
                $('#monthSelect').val(date.getMonth() + 1);
                $('#yearSelect').val(date.getFullYear());
                
                $('#eeUnit').val(response.data.ee_unit);
                $('#costPerUnit').val(response.data.cost_per_unit);
                
                if (response.data.water_unit) {
                    $('#waterUnit').val(response.data.water_unit);
                }
                if (response.data.water_cost_per_unit) {
                    $('#waterCostPerUnit').val(response.data.water_cost_per_unit);
                }
                
                $('#pe').val(response.data.pe);
                $('#remarks').val(response.data.remarks);
                
                calculateCosts();
                checkDuplicate();
                
                $('#recordModal').modal('show');
            }
        }
    });
}

function saveRecord() {
    if (!$('#recordForm')[0].checkValidity()) {
        $('#recordForm')[0].reportValidity();
        return;
    }
    
    // Create date from month and year (first day of month)
    const month = $('#monthSelect').val();
    const year = $('#yearSelect').val();
    const recordDate = year + '-' + month.padStart(2, '0') + '-01';
    
    const formData = $('#recordForm').serialize() + '&record_date=' + recordDate;
    
    $.ajax({
        url: 'process_add.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        beforeSend: function() {
            $('#saveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...');
        },
        success: function(response) {
            if (response.success) {
                $('#recordModal').modal('hide');
                EUMS.showNotification('บันทึกข้อมูลเรียบร้อย', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                $('#saveBtn').prop('disabled', false).html('บันทึก');
                EUMS.showNotification(response.message, 'error');
            }
        },
        error: function(xhr) {
            $('#saveBtn').prop('disabled', false).html('บันทึก');
            let message = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            EUMS.showNotification(message, 'error');
        }
    });
}

function deleteRecord(id) {
    if (confirm('คุณต้องการลบข้อมูลนี้ใช่หรือไม่?')) {
        $.ajax({
            url: 'ajax/delete_record.php',
            method: 'POST',
            data: { id: id },
            success: function(response) {
                if (response.success) {
                    EUMS.showNotification('ลบข้อมูลเรียบร้อย', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    EUMS.showNotification(response.message, 'error');
                }
            }
        });
    }
}

function updateDocument() {
    const formData = $('#documentForm').serialize();
    formData += '&module=summary&month=<?php echo $currentMonth; ?>&year=<?php echo $currentYear; ?>';
    
    $.ajax({
        url: 'ajax/update_document.php',
        method: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                EUMS.showNotification('อัปเดตเอกสารเรียบร้อย', 'success');
            } else {
                EUMS.showNotification(response.message, 'error');
            }
        }
    });
}

function exportData() {
    window.location.href = 'export.php?year=<?php echo $currentYear; ?>';
}
</script>