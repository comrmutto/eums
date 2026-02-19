<?php
/**
 * Energy & Water Module - Main Index Page
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Energy & Water - บันทึกข้อมูล';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Energy & Water', 'link' => null],
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
    WHERE module_type = 'energy_water' 
    AND MONTH(start_date) = ? 
    AND YEAR(start_date) = ?
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([$currentMonth, $currentYear]);
$document = $stmt->fetch();

// Get meters
$stmt = $db->query("
    SELECT * FROM mc_mdb_water 
    WHERE status = 1 
    ORDER BY meter_type, meter_code
");
$meters = $stmt->fetchAll();

// Group meters by type
$electricityMeters = array_filter($meters, function($m) { return $m['meter_type'] == 'electricity'; });
$waterMeters = array_filter($meters, function($m) { return $m['meter_type'] == 'water'; });

// Get daily readings for current month
$stmt = $db->prepare("
    SELECT r.*, m.meter_name, m.meter_code, m.meter_type, m.location
    FROM meter_daily_readings r
    JOIN mc_mdb_water m ON r.meter_id = m.id
    WHERE MONTH(r.record_date) = ? AND YEAR(r.record_date) = ?
    ORDER BY r.record_date DESC, m.meter_type, m.meter_code
");
$stmt->execute([$currentMonth, $currentYear]);
$readings = $stmt->fetchAll();

// Group readings by date
$readingsByDate = [];
foreach ($readings as $reading) {
    $readingsByDate[$reading['record_date']][] = $reading;
}

// Calculate summary statistics
$totalElectricity = 0;
$totalWater = 0;
$totalUsage = 0;
$readingsCount = count($readings);

foreach ($readings as $reading) {
    if ($reading['meter_type'] == 'electricity') {
        $totalElectricity += floatval($reading['usage_amount']);
    } else {
        $totalWater += floatval($reading['usage_amount']);
    }
    $totalUsage += floatval($reading['usage_amount']);
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
                                       value="<?php echo $document['doc_no'] ?? 'EW-' . ($currentYear + 543) . '-' . str_pad($currentMonth, 2, '0', STR_PAD_LEFT); ?>">
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
                        <h3><?php echo count($meters); ?></h3>
                        <p>มิเตอร์ทั้งหมด</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-gauge-high"></i>
                    </div>
                    <a href="machines.php" class="small-box-footer">
                        ดูรายละเอียด <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo count($electricityMeters); ?></h3>
                        <p>มิเตอร์ไฟฟ้า</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <a href="machines.php?type=electricity" class="small-box-footer">
                        ดูรายละเอียด <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo count($waterMeters); ?></h3>
                        <p>มิเตอร์น้ำ</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-water"></i>
                    </div>
                    <a href="machines.php?type=water" class="small-box-footer">
                        ดูรายละเอียด <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo number_format($totalUsage, 2); ?></h3>
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

        <!-- Charts Section -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            กราฟการใช้ไฟฟ้ารายวัน
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="electricityChart" style="min-height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            กราฟการใช้น้ำรายวัน
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="waterChart" style="min-height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Readings Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table"></i>
                    บันทึกค่ามิเตอร์ประจำวัน
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
                                <th>ประเภท</th>
                                <th>รหัสมิเตอร์</th>
                                <th>ชื่อมิเตอร์</th>
                                <th>ตำแหน่ง</th>
                                <th>ค่าเช้า</th>
                                <th>ค่าเย็น</th>
                                <th>ปริมาณการใช้</th>
                                <th>หน่วย</th>
                                <th>ผู้บันทึก</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($readings as $reading): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($reading['record_date'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $reading['meter_type'] == 'electricity' ? 'warning' : 'info'; ?>">
                                        <?php echo $reading['meter_type'] == 'electricity' ? 'ไฟฟ้า' : 'น้ำ'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($reading['meter_code']); ?></td>
                                <td><?php echo htmlspecialchars($reading['meter_name']); ?></td>
                                <td><?php echo htmlspecialchars($reading['location']); ?></td>
                                <td class="text-right"><?php echo number_format($reading['morning_reading'], 2); ?></td>
                                <td class="text-right"><?php echo number_format($reading['evening_reading'], 2); ?></td>
                                <td class="text-right">
                                    <strong><?php echo number_format($reading['usage_amount'], 2); ?></strong>
                                </td>
                                <td><?php echo $reading['meter_type'] == 'electricity' ? 'kWh' : 'm³'; ?></td>
                                <td><?php echo htmlspecialchars($reading['recorded_by']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editReading(<?php echo $reading['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteReading(<?php echo $reading['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($readings)): ?>
                            <tr>
                                <td colspan="11" class="text-center">ไม่พบข้อมูล</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Add/Edit Modal -->
<div class="modal fade" id="readingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">เพิ่มบันทึกค่ามิเตอร์</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="readingForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="readingId">
                    <input type="hidden" name="doc_id" value="<?php echo $document['id'] ?? 0; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>วันที่บันทึก <span class="text-danger">*</span></label>
                                <input type="text" class="form-control datepicker" name="record_date" id="recordDate" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>มิเตอร์ <span class="text-danger">*</span></label>
                                <select class="form-control select2" name="meter_id" id="meterId" required>
                                    <option value="">เลือกมิเตอร์</option>
                                    <optgroup label="มิเตอร์ไฟฟ้า">
                                        <?php foreach ($electricityMeters as $meter): ?>
                                            <option value="<?php echo $meter['id']; ?>" data-type="electricity">
                                                <?php echo $meter['meter_code'] . ' - ' . $meter['meter_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <optgroup label="มิเตอร์น้ำ">
                                        <?php foreach ($waterMeters as $meter): ?>
                                            <option value="<?php echo $meter['id']; ?>" data-type="water">
                                                <?php echo $meter['meter_code'] . ' - ' . $meter['meter_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ค่าเช้า <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" name="morning_reading" id="morningReading" required>
                                <small class="text-muted">ค่ามิเตอร์ตอนเช้า</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ค่าเย็น <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" name="evening_reading" id="eveningReading" required>
                                <small class="text-muted">ค่ามิเตอร์ตอนเย็น</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ปริมาณการใช้</label>
                                <input type="text" class="form-control" id="usageAmount" readonly>
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
                    
                    <div class="alert alert-warning" id="validationAlert" style="display: none;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span id="validationMessage"></span>
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
let electricityChart = null;
let waterChart = null;

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
    
    // Reading input events
    $('#morningReading, #eveningReading').on('input', function() {
        calculateUsage();
    });
    
    // Meter change event
    $('#meterId').on('change', function() {
        updateUnit();
        checkExistingReading();
    });
    
    // Update document
    $('#updateDocument').on('click', function() {
        updateDocument();
    });
    
    // Form submit
    $('#readingForm').on('submit', function(e) {
        e.preventDefault();
        saveReading();
    });
});

function loadChartData() {
    const month = <?php echo $currentMonth; ?>;
    const year = <?php echo $currentYear; ?>;
    
    $.ajax({
        url: 'ajax/get_chart_data.php',
        method: 'POST',
        data: {
            month: month,
            year: year
        },
        success: function(response) {
            if (response.success) {
                renderElectricityChart(response.electricity);
                renderWaterChart(response.water);
            }
        }
    });
}

function renderElectricityChart(data) {
    const ctx = document.getElementById('electricityChart').getContext('2d');
    
    if (electricityChart) {
        electricityChart.destroy();
    }
    
    electricityChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'การใช้ไฟฟ้า (kWh)',
                data: data.values,
                borderColor: '#ffc107',
                backgroundColor: 'rgba(255, 193, 7, 0.1)',
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
}

function renderWaterChart(data) {
    const ctx = document.getElementById('waterChart').getContext('2d');
    
    if (waterChart) {
        waterChart.destroy();
    }
    
    waterChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'การใช้น้ำ (m³)',
                data: data.values,
                borderColor: '#17a2b8',
                backgroundColor: 'rgba(23, 162, 184, 0.1)',
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
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'm³'
                    }
                }
            }
        }
    });
}

function calculateUsage() {
    const morning = parseFloat($('#morningReading').val()) || 0;
    const evening = parseFloat($('#eveningReading').val()) || 0;
    
    if (evening >= morning) {
        const usage = evening - morning;
        $('#usageAmount').val(usage.toFixed(2));
        
        // Check for abnormal usage
        if (usage > 1000) {
            $('#validationMessage').text('ปริมาณการใช้สูงผิดปกติ กรุณาตรวจสอบ');
            $('#validationAlert').removeClass('alert-warning').addClass('alert-danger').show();
        } else if (usage === 0) {
            $('#validationMessage').text('ปริมาณการใช้เป็น 0 กรุณาตรวจสอบ');
            $('#validationAlert').removeClass('alert-danger').addClass('alert-warning').show();
        } else {
            $('#validationAlert').hide();
        }
    } else {
        $('#usageAmount').val('ค่าเย็นต้องมากกว่าค่าเช้า');
        $('#validationMessage').text('ค่าเย็นต้องมากกว่าค่าเช้า');
        $('#validationAlert').removeClass('alert-danger').addClass('alert-warning').show();
    }
}

function updateUnit() {
    const option = $('#meterId option:selected');
    const type = option.data('type');
    
    if (type === 'electricity') {
        $('#unit').val('kWh');
    } else if (type === 'water') {
        $('#unit').val('m³');
    }
}

function checkExistingReading() {
    const meterId = $('#meterId').val();
    const recordDate = $('#recordDate').val();
    
    if (meterId && recordDate) {
        $.ajax({
            url: 'ajax/check_reading.php',
            method: 'POST',
            data: {
                meter_id: meterId,
                record_date: recordDate
            },
            success: function(response) {
                if (response.exists) {
                    $('#validationMessage').text('มีบันทึกข้อมูลสำหรับมิเตอร์และวันนี้แล้ว');
                    $('#validationAlert').removeClass('alert-danger').addClass('alert-warning').show();
                }
            }
        });
    }
}

function showAddModal() {
    $('#modalTitle').text('เพิ่มบันทึกค่ามิเตอร์');
    $('#readingForm')[0].reset();
    $('#readingId').val('');
    $('#usageAmount').val('');
    $('#validationAlert').hide();
    $('#readingModal').modal('show');
}

function editReading(id) {
    $.ajax({
        url: 'ajax/get_reading.php',
        method: 'GET',
        data: { id: id },
        success: function(response) {
            if (response.success) {
                $('#modalTitle').text('แก้ไขบันทึกค่ามิเตอร์');
                $('#readingId').val(response.data.id);
                $('#recordDate').val(response.data.record_date_thai);
                $('#meterId').val(response.data.meter_id).trigger('change');
                $('#morningReading').val(response.data.morning_reading);
                $('#eveningReading').val(response.data.evening_reading);
                $('#remarks').val(response.data.remarks);
                calculateUsage();
                $('#readingModal').modal('show');
            }
        }
    });
}

function saveReading() {
    if (!$('#readingForm')[0].checkValidity()) {
        $('#readingForm')[0].reportValidity();
        return;
    }
    
    const formData = $('#readingForm').serialize();
    
    $.ajax({
        url: 'process_add.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#readingModal').modal('hide');
                showNotification('บันทึกข้อมูลเรียบร้อย', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showNotification(response.message, 'danger');
            }
        }
    });
}

function deleteReading(id) {
    if (confirm('คุณต้องการลบข้อมูลนี้ใช่หรือไม่?')) {
        $.ajax({
            url: 'ajax/delete_reading.php',
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
    formData += '&module=energy_water&month=<?php echo $currentMonth; ?>&year=<?php echo $currentYear; ?>';
    
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