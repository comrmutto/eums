<?php
/**
 * Summary Electricity Module - Main Index Page
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Summary Electricity - บันทึกข้อมูลพลังงานรวม';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Summary Electricity', 'link' => null],
    ['title' => 'บันทึกข้อมูล', 'link' => null]
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

// Get summary records for current month
$stmt = $db->prepare("
    SELECT * FROM electricity_summary 
    WHERE MONTH(record_date) = ? AND YEAR(record_date) = ?
    ORDER BY record_date
");
$stmt->execute([$currentMonth, $currentYear]);
$records = $stmt->fetchAll();

// Calculate monthly totals
$totalEE = 0;
$totalCost = 0;
$totalPE = 0;

foreach ($records as $record) {
    $totalEE += floatval($record['ee_unit']);
    $totalCost += floatval($record['total_cost']);
    $totalPE += floatval($record['pe'] ?? 0);
}

// Get chart data for the year
$stmt = $db->prepare("
    SELECT 
        MONTH(record_date) as month,
        SUM(ee_unit) as total_ee,
        SUM(total_cost) as total_cost,
        AVG(cost_per_unit) as avg_cost_per_unit
    FROM electricity_summary
    WHERE YEAR(record_date) = ?
    GROUP BY MONTH(record_date)
    ORDER BY month
");
$stmt->execute([$currentYear]);
$yearlyData = $stmt->fetchAll();

// Create array for all months
$monthlyEE = array_fill(1, 12, 0);
$monthlyCost = array_fill(1, 12, 0);
$monthlyAvgCost = array_fill(1, 12, 0);

foreach ($yearlyData as $data) {
    $monthlyEE[$data['month']] = round($data['total_ee'], 2);
    $monthlyCost[$data['month']] = round($data['total_cost'], 2);
    $monthlyAvgCost[$data['month']] = round($data['avg_cost_per_unit'], 4);
}
?>

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

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo number_format($totalEE, 2); ?></h3>
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
                        <h3><?php echo number_format($totalCost, 2); ?></h3>
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
                        <h3><?php echo count($records); ?></h3>
                        <p>จำนวนวันที่มีข้อมูล</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php 
                            $avgCost = $totalEE > 0 ? $totalCost / $totalEE : 0;
                            echo number_format($avgCost, 4); 
                        ?></h3>
                        <p>ค่าไฟเฉลี่ยต่อหน่วย (บาท)</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-line"></i>
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
                            หน่วยไฟฟ้ารายเดือน ปี <?php echo $currentYear + 543; ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="eeChart" style="min-height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            ค่าไฟฟ้ารายเดือน ปี <?php echo $currentYear + 543; ?>
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

        <!-- Daily Records Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table"></i>
                    บันทึกข้อมูลประจำเดือน <?php echo getThaiMonth($currentMonth) . ' ' . ($currentYear + 543); ?>
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" onclick="showAddModal()">
                        <i class="fas fa-plus"></i> เพิ่มบันทึก
                    </button>
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
                                <th>วันที่</th>
                                <th>หน่วยไฟฟ้า (kWh)</th>
                                <th>ค่าไฟต่อหน่วย (บาท)</th>
                                <th>ค่าไฟฟ้า (บาท)</th>
                                <th>PE</th>
                                <th>หมายเหตุ</th>
                                <th>ผู้บันทึก</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($record['record_date'])); ?></td>
                                <td class="text-right"><?php echo number_format($record['ee_unit'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($record['cost_per_unit'], 4); ?></td>
                                <td class="text-right"><?php echo number_format($record['total_cost'], 2); ?></td>
                                <td class="text-right"><?php echo $record['pe'] ? number_format($record['pe'], 4) : '-'; ?></td>
                                <td><?php echo htmlspecialchars($record['remarks'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($record['recorded_by']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editRecord(<?php echo $record['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteRecord(<?php echo $record['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button class="btn btn-sm btn-info" onclick="viewRecord(<?php echo $record['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="8" class="text-center">ไม่พบข้อมูล</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($records)): ?>
                        <tfoot>
                            <tr class="bg-gray">
                                <th class="text-right">รวม</th>
                                <th class="text-right"><?php echo number_format($totalEE, 2); ?></th>
                                <th class="text-right">-</th>
                                <th class="text-right"><?php echo number_format($totalCost, 2); ?></th>
                                <th class="text-right"><?php echo number_format($totalPE, 4); ?></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Add/Edit Modal -->
<div class="modal fade" id="recordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">เพิ่มบันทึกข้อมูล</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="recordForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="recordId">
                    <input type="hidden" name="doc_id" value="<?php echo $document['id'] ?? 0; ?>">
                    
                    <div class="form-group">
                        <label>วันที่ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control datepicker" name="record_date" id="recordDate" required>
                    </div>
                    
                    <div class="form-group">
                        <label>หน่วยไฟฟ้า (EE) - kWh <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="ee_unit" id="eeUnit" required>
                    </div>
                    
                    <div class="form-group">
                        <label>ค่าไฟต่อหน่วย (บาท) <span class="text-danger">*</span></label>
                        <input type="number" step="0.0001" class="form-control" name="cost_per_unit" id="costPerUnit" required>
                    </div>
                    
                    <div class="form-group">
                        <label>ค่าไฟฟ้า (บาท)</label>
                        <input type="text" class="form-control" id="totalCost" readonly>
                        <small class="text-muted">คำนวณอัตโนมัติจากหน่วยไฟฟ้า × ค่าไฟต่อหน่วย</small>
                    </div>
                    
                    <div class="form-group">
                        <label>PE</label>
                        <input type="number" step="0.0001" class="form-control" name="pe" id="pe">
                    </div>
                    
                    <div class="form-group">
                        <label>หมายเหตุ</label>
                        <textarea class="form-control" name="remarks" id="remarks" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let eeChart = null;
let costChart = null;

$(document).ready(function() {
    // Initialize datepicker
    $('.datepicker').datepicker({
        format: 'dd/mm/yyyy',
        autoclose: true,
        language: 'th'
    });
    
    // Load charts
    renderCharts();
    
    // Calculate total cost
    $('#eeUnit, #costPerUnit').on('input', function() {
        calculateTotalCost();
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
    const costData = [<?php echo implode(',', $monthlyCost); ?>];
    
    // EE Chart
    const eeCtx = document.getElementById('eeChart').getContext('2d');
    if (eeChart) eeChart.destroy();
    
    eeChart = new Chart(eeCtx, {
        type: 'bar',
        data: {
            labels: monthNames,
            datasets: [{
                label: 'หน่วยไฟฟ้า (kWh)',
                data: eeData,
                backgroundColor: 'rgba(0, 123, 255, 0.5)',
                borderColor: '#007bff',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'หน่วย: ' + context.raw.toFixed(2) + ' kWh';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'kWh'
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
            datasets: [{
                label: 'ค่าไฟฟ้า (บาท)',
                data: costData,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'ค่าไฟ: ' + context.raw.toFixed(2) + ' บาท';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'บาท'
                    }
                }
            }
        }
    });
}

function calculateTotalCost() {
    const ee = parseFloat($('#eeUnit').val()) || 0;
    const costPerUnit = parseFloat($('#costPerUnit').val()) || 0;
    const total = (ee * costPerUnit).toFixed(2);
    
    $('#totalCost').val(total);
}

function showAddModal() {
    $('#modalTitle').text('เพิ่มบันทึกข้อมูล');
    $('#recordForm')[0].reset();
    $('#recordId').val('');
    $('#totalCost').val('');
    
    // Set default date to first day of current month
    const firstDay = '01/<?php echo str_pad($currentMonth, 2, '0', STR_PAD_LEFT) . '/' . ($currentYear + 543); ?>';
    $('#recordDate').val(firstDay);
    
    $('#recordModal').modal('show');
}

function editRecord(id) {
    $.ajax({
        url: 'ajax/get_record.php',
        method: 'GET',
        data: { id: id },
        success: function(response) {
            if (response.success) {
                $('#modalTitle').text('แก้ไขบันทึกข้อมูล');
                $('#recordId').val(response.data.id);
                $('#recordDate').val(response.data.record_date_thai);
                $('#eeUnit').val(response.data.ee_unit);
                $('#costPerUnit').val(response.data.cost_per_unit);
                $('#pe').val(response.data.pe);
                $('#remarks').val(response.data.remarks);
                
                calculateTotalCost();
                $('#recordModal').modal('show');
            }
        }
    });
}

function viewRecord(id) {
    window.location.href = 'view.php?id=' + id;
}

function saveRecord() {
    if (!$('#recordForm')[0].checkValidity()) {
        $('#recordForm')[0].reportValidity();
        return;
    }
    
    const formData = $('#recordForm').serialize();
    
    $.ajax({
        url: 'process_add.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#recordModal').modal('hide');
                showNotification('บันทึกข้อมูลเรียบร้อย', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showNotification(response.message, 'danger');
            }
        },
        error: function(xhr) {
            let message = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            showNotification(message, 'danger');
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
                    showNotification('ลบข้อมูลเรียบร้อย', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification(response.message, 'danger');
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
                showNotification('อัปเดตเอกสารเรียบร้อย', 'success');
            } else {
                showNotification(response.message, 'danger');
            }
        }
    });
}

function exportData() {
    window.location.href = 'export.php?month=<?php echo $currentMonth; ?>&year=<?php echo $currentYear; ?>';
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>