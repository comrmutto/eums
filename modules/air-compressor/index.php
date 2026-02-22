<?php
/**
 * Air Compressor Module - Main Index Page
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Air Compressor - บันทึกข้อมูล';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Air Compressor', 'link' => null],
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
    WHERE module_type = 'air' 
    AND MONTH(start_date) = ? 
    AND YEAR(start_date) = ?
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([$currentMonth, $currentYear]);
$document = $stmt->fetch();

// Get machines
$stmt = $db->query("SELECT * FROM mc_air WHERE status = 1 ORDER BY machine_code");
$machines = $stmt->fetchAll();

// Get inspection standards
$stmt = $db->query("
    SELECT s.*, m.machine_name 
    FROM air_inspection_standards s
    JOIN mc_air m ON s.machine_id = m.id
    WHERE m.status = 1
    ORDER BY m.machine_code, s.sort_order
");
$standards = $stmt->fetchAll();

// Get daily records for current month
$stmt = $db->prepare("
    SELECT r.*, s.inspection_item, m.machine_name 
    FROM air_daily_records r
    JOIN air_inspection_standards s ON r.inspection_item_id = s.id
    JOIN mc_air m ON r.machine_id = m.id
    WHERE MONTH(r.record_date) = ? AND YEAR(r.record_date) = ?
    ORDER BY r.record_date DESC
");
$stmt->execute([$currentMonth, $currentYear]);
$records = $stmt->fetchAll();

// Group records by date
$recordsByDate = [];
foreach ($records as $record) {
    $recordsByDate[$record['record_date']][] = $record;
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
                                       value="<?php echo $document['doc_no'] ?? 'AC-' . ($currentYear + 543) . '-0001'; ?>">
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
                        <h3><?php echo count($machines); ?></h3>
                        <p>เครื่องจักรทั้งหมด</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-compress"></i>
                    </div>
                    <a href="machines.php" class="small-box-footer">
                        ดูรายละเอียด <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo count($standards); ?></h3>
                        <p>หัวข้อตรวจสอบ</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <a href="settings.php" class="small-box-footer">
                        ดูรายละเอียด <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo count($records); ?></h3>
                        <p>รายการบันทึก</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <a href="#" class="small-box-footer">
                        ดูรายละเอียด <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php 
                            $totalUsage = 0;
                            foreach ($records as $r) {
                                $totalUsage += floatval($r['actual_value']);
                            }
                            echo number_format($totalUsage, 2);
                        ?></h3>
                        <p>ปริมาณการใช้รวม</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <a href="#" class="small-box-footer">
                        ดูรายละเอียด <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-line"></i>
                    กราฟสรุปปริมาณการใช้งาน
                </h3>
                <div class="card-tools">
                    <select id="chartMachine" class="form-control form-control-sm" style="width: 200px; height: 40px;">
                        <option value="all">ทุกเครื่องจักร</option>
                        <?php foreach ($machines as $machine): ?>
                            <option value="<?php echo $machine['id']; ?>">
                                <?php echo $machine['machine_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="usageChart" style="min-height: 300px; height: 300px; max-height: 300px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Daily Records Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table"></i>
                    บันทึกข้อมูลประจำวัน
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
                                <th>เครื่องจักร</th>
                                <th>หัวข้อตรวจสอบ</th>
                                <th>ค่าที่วัดได้</th>
                                <th>หน่วย</th>
                                <th>ค่ามาตรฐาน</th>
                                <th>สถานะ</th>
                                <th>ผู้บันทึก</th>
                                <th>หมายเหตุ</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($record['record_date'])); ?></td>
                                <td><?php echo htmlspecialchars($record['machine_name']); ?></td>
                                <td><?php echo htmlspecialchars($record['inspection_item']); ?></td>
                                <td class="text-right"><?php echo number_format($record['actual_value'], 2); ?></td>
                                <td><?php 
                                    // Find unit from standards
                                    foreach ($standards as $s) {
                                        if ($s['id'] == $record['inspection_item_id']) {
                                            echo $s['unit'];
                                            break;
                                        }
                                    }
                                ?></td>
                                <td class="text-right">
                                    <?php 
                                    foreach ($standards as $s) {
                                        if ($s['id'] == $record['inspection_item_id']) {
                                            if ($s['min_value'] && $s['max_value']) {
                                                echo number_format($s['min_value'], 2) . ' - ' . number_format($s['max_value'], 2);
                                            } else {
                                                echo number_format($s['standard_value'], 2);
                                            }
                                            break;
                                        }
                                    }
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    $status = 'ok';
                                    foreach ($standards as $s) {
                                        if ($s['id'] == $record['inspection_item_id']) {
                                            if ($s['min_value'] && $s['max_value']) {
                                                if ($record['actual_value'] < $s['min_value'] || $record['actual_value'] > $s['max_value']) {
                                                    $status = 'ng';
                                                }
                                            } else {
                                                if (abs($record['actual_value'] - $s['standard_value']) > $s['standard_value'] * 0.1) {
                                                    $status = 'ng';
                                                }
                                            }
                                            break;
                                        }
                                    }
                                    ?>
                                    <span class="badge badge-<?php echo $status == 'ok' ? 'success' : 'danger'; ?>">
                                        <?php echo $status == 'ok' ? 'ผ่าน' : 'ไม่ผ่าน'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($record['recorded_by']); ?></td>
                                <td><?php echo htmlspecialchars($record['remarks']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editRecord(<?php echo $record['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteRecord(<?php echo $record['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
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
                <h5 class="modal-title" id="modalTitle">เพิ่มบันทึกข้อมูล</h5>
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
                                <label>วันที่ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control datepicker" name="record_date" id="recordDate" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>เครื่องจักร <span class="text-danger">*</span></label>
                                <select class="form-control select2" name="machine_id" id="machineId" required>
                                    <option value="">เลือกเครื่องจักร</option>
                                    <?php foreach ($machines as $machine): ?>
                                        <option value="<?php echo $machine['id']; ?>">
                                            <?php echo $machine['machine_code'] . ' - ' . $machine['machine_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>หัวข้อตรวจสอบ <span class="text-danger">*</span></label>
                                <select class="form-control select2" name="inspection_item_id" id="inspectionItemId" required>
                                    <option value="">เลือกหัวข้อตรวจสอบ</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>ค่ามาตรฐาน</label>
                                <input type="text" class="form-control" id="standardValue" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ค่าที่วัดได้ <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" name="actual_value" id="actualValue" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>หน่วย</label>
                                <input type="text" class="form-control" id="unit" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>หมายเหตุ</label>
                        <textarea class="form-control" name="remarks" id="remarks" rows="2"></textarea>
                    </div>
                    
                    <div class="alert alert-info" id="statusAlert" style="display: none;">
                        <i class="fas fa-info-circle"></i> 
                        <span id="statusMessage"></span>
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

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>

<!-- Include module specific JS -->
<script>
let usageChart = null;

$(document).ready(function() {
    // Initialize datepicker
    $('.datepicker').datepicker({
        format: 'dd/mm/yyyy',
        autoclose: true,
        language: 'th'
    });
    
    // Initialize select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });
    
    // Load chart data
    loadChartData();
    
    // Machine change event
    $('#machineId').on('change', function() {
        loadInspectionItems($(this).val());
    });
    
    // Inspection item change event
    $('#inspectionItemId').on('change', function() {
        loadStandardValue($(this).val());
    });
    
    // Actual value change event
    $('#actualValue').on('input', function() {
        validateValue();
    });
    
    // Update document
    $('#updateDocument').on('click', function() {
        updateDocument();
    });
    
    // Chart machine filter
    $('#chartMachine').on('change', function() {
        loadChartData();
    });
    
    // Form submit
    $('#recordForm').on('submit', function(e) {
        e.preventDefault();
        saveRecord();
    });
});

function loadChartData() {
    const machineId = $('#chartMachine').val();
    const month = <?php echo $currentMonth; ?>;
    const year = <?php echo $currentYear; ?>;
    
    $.ajax({
        url: 'ajax/get_chart_data.php',
        method: 'POST',
        data: {
            machine_id: machineId,
            month: month,
            year: year
        },
        success: function(response) {
            if (response.success) {
                renderChart(response.data);
            }
        }
    });
}

function renderChart(data) {
    const ctx = document.getElementById('usageChart').getContext('2d');
    
    if (usageChart) {
        usageChart.destroy();
    }
    
    usageChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'ปริมาณการใช้งาน',
                data: data.values,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#007bff',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4
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
                            return 'ค่า: ' + context.raw.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    },
                    ticks: {
                        callback: function(value) {
                            return value.toFixed(2);
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

function loadInspectionItems(machineId) {
    if (!machineId) {
        $('#inspectionItemId').html('<option value="">เลือกหัวข้อตรวจสอบ</option>');
        return;
    }
    
    $.ajax({
        // 1. แก้ไข URL ให้ชี้มาที่ไฟล์ที่ถูกต้อง
        url: 'ajax/get_inspection_items.php', 
        method: 'GET',
        // 2. ส่งไปแค่ machine_id ตามที่ PHP ต้องการ
        data: {
            machine_id: machineId
        },
        success: function(response) {
            // 3. แก้ไขการวนลูปจาก response.data.standards เป็น response.data เฉยๆ
            if (response.success && response.data) {
                let options = '<option value="">เลือกหัวข้อตรวจสอบ</option>';
                response.data.forEach(function(item) {
                    options += `<option value="${item.id}" data-standard="${item.standard_value}" data-min="${item.min_value}" data-max="${item.max_value}" data-unit="${item.unit}">${item.inspection_item}</option>`;
                });
                $('#inspectionItemId').html(options);
            }
        },
        error: function() {
            EUMS.showNotification('เกิดข้อผิดพลาดในการดึงข้อมูลหัวข้อตรวจสอบ', 'error');
        }
    });
}

function loadStandardValue(itemId) {
    const option = $('#inspectionItemId option:selected');
    const standard = option.data('standard');
    const min = option.data('min');
    const max = option.data('max');
    const unit = option.data('unit');
    
    if (min && max) {
        $('#standardValue').val(min + ' - ' + max);
    } else {
        $('#standardValue').val(standard);
    }
    $('#unit').val(unit);
}

function validateValue() {
    const value = parseFloat($('#actualValue').val());
    const option = $('#inspectionItemId option:selected');
    const min = parseFloat(option.data('min'));
    const max = parseFloat(option.data('max'));
    const standard = parseFloat(option.data('standard'));
    
    if (isNaN(value)) {
        $('#statusAlert').hide();
        return;
    }
    
    let isValid = true;
    let message = '';
    
    if (min && max) {
        if (value < min || value > max) {
            isValid = false;
            message = `ค่าอยู่นอกช่วงมาตรฐาน (ต้องอยู่ระหว่าง ${min} - ${max})`;
        } else {
            message = `ค่าอยู่ในช่วงมาตรฐาน`;
        }
    } else {
        const tolerance = standard * 0.1;
        if (Math.abs(value - standard) > tolerance) {
            isValid = false;
            message = `ค่าเบี่ยงเบนจากมาตรฐานเกิน 10% (มาตรฐาน: ${standard})`;
        } else {
            message = `ค่าอยู่ในเกณฑ์มาตรฐาน (ค่าเบี่ยงเบน: ${Math.abs(((value - standard) / standard * 100)).toFixed(2)}%)`;
        }
    }
    
    $('#statusMessage').text(message);
    $('#statusAlert').removeClass('alert-success alert-danger')
        .addClass(isValid ? 'alert-success' : 'alert-danger')
        .show();
}

function showAddModal() {
    $('#modalTitle').text('เพิ่มบันทึกข้อมูล');
    $('#recordForm')[0].reset();
    $('#recordId').val('');
    $('#statusAlert').hide();
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
                $('#machineId').val(response.data.machine_id).trigger('change');
                
                setTimeout(function() {
                    $('#inspectionItemId').val(response.data.inspection_item_id).trigger('change');
                    $('#actualValue').val(response.data.actual_value);
                    $('#remarks').val(response.data.remarks);
                    validateValue();
                }, 500);
                
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
    
    const formData = $('#recordForm').serialize();
    
    $.ajax({
        url: 'ajax/save_record.php',
        method: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                $('#recordModal').modal('hide');
                EUMS.showNotification('บันทึกข้อมูลเรียบร้อย', 'success'); // เติม EUMS.
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                EUMS.showNotification(response.message, 'error'); // เติม EUMS. และเปลี่ยน 'error'
            }
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
                EUMS.showNotification('ลบข้อมูลเรียบร้อย', 'success'); // เติม EUMS.
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                EUMS.showNotification(response.message, 'error'); // เติม EUMS. และเปลี่ยน 'error'
            }
        }
    });
}
}

function updateDocument() {
    const formData = $('#documentForm').serialize();
    formData += '&module=air&month=<?php echo $currentMonth; ?>&year=<?php echo $currentYear; ?>';
    
    $.ajax({
        url: 'ajax/update_document.php',
        method: 'POST',
        data: formData,
    success: function(response) {
            if (response.success) {
                EUMS.showNotification('อัปเดตเอกสารเรียบร้อย', 'success'); // เติม EUMS.
            } else {
                EUMS.showNotification(response.message, 'error'); // เติม EUMS. และเปลี่ยน 'error'
            }
        }
    });
}

function exportData() {
    window.location.href = 'export.php?month=<?php echo $currentMonth; ?>&year=<?php echo $currentYear; ?>';
}
</script>
