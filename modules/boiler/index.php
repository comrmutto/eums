<?php
/**
 * Boiler Module - Main Index Page
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Boiler - บันทึกข้อมูล';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Boiler', 'link' => null],
    ['title' => 'บันทึกข้อมูล', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Load required files
require_once __DIR__ . '/../../includes/functions.php';

// Get database connection
$db = getDB();

// Get current date
$currentDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$displayDate = date('d/m/Y', strtotime($currentDate));

// Get document info
$stmt = $db->prepare("
    SELECT * FROM documents 
    WHERE module_type = 'boiler' 
    AND start_date <= ? 
    ORDER BY start_date DESC LIMIT 1
");
$stmt->execute([$currentDate]);
$document = $stmt->fetch();

// Get boiler machines
$stmt = $db->query("
    SELECT * FROM mc_boiler 
    WHERE status = 1 
    ORDER BY machine_code
");
$machines = $stmt->fetchAll();

// Get today's records for each machine
$records = [];
foreach ($machines as $machine) {
    $stmt = $db->prepare("
        SELECT * FROM boiler_daily_records 
        WHERE machine_id = ? AND record_date = ?
    ");
    $stmt->execute([$machine['id'], $currentDate]);
    $record = $stmt->fetch();
    
    if ($record) {
        $records[$machine['id']] = $record;
    }
}

// Get chart data for last 30 days
$stmt = $db->prepare("
    SELECT 
        record_date,
        SUM(steam_pressure) as total_pressure,
        AVG(steam_pressure) as avg_pressure,
        SUM(steam_temperature) as total_temp,
        AVG(steam_temperature) as avg_temp,
        SUM(fuel_consumption) as total_fuel,
        AVG(fuel_consumption) as avg_fuel
    FROM boiler_daily_records
    WHERE record_date >= DATE_SUB(?, INTERVAL 30 DAY)
    GROUP BY record_date
    ORDER BY record_date
");
$stmt->execute([$currentDate]);
$chartData = $stmt->fetchAll();
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
                                       value="<?php echo $document['doc_no'] ?? 'BLR-' . date('Ymd'); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>วันที่เริ่มใช้</label>
                                <input type="text" class="form-control datepicker" name="start_date" 
                                       value="<?php echo isset($document['start_date']) ? date('d/m/Y', strtotime($document['start_date'])) : date('d/m/Y'); ?>">
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

        <!-- Date Selector -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-alt"></i>
                    เลือกวันที่
                </h3>
            </div>
            <div class="card-body">
                <form method="GET" class="form-inline">
                    <div class="form-group mr-2">
                        <label class="mr-2">วันที่:</label>
                        <input type="text" class="form-control datepicker" name="date" 
                               value="<?php echo $displayDate; ?>" id="datePicker">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> แสดงข้อมูล
                    </button>
                    <button type="button" class="btn btn-success ml-2" onclick="goToToday()">
                        <i class="fas fa-calendar-check"></i> วันนี้
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
                        <p>เครื่อง Boiler ทั้งหมด</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-industry"></i>
                    </div>
                    <a href="machines.php" class="small-box-footer">
                        จัดการเครื่องจักร <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo count($records); ?></h3>
                        <p>บันทึกวันนี้</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php 
                            $totalFuel = 0;
                            foreach ($records as $r) {
                                $totalFuel += floatval($r['fuel_consumption']);
                            }
                            echo number_format($totalFuel, 2);
                        ?></h3>
                        <p>ปริมาณเชื้อเพลิงรวม</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-fire"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php 
                            $totalHours = 0;
                            foreach ($records as $r) {
                                $totalHours += floatval($r['operating_hours']);
                            }
                            echo number_format($totalHours, 1);
                        ?></h3>
                        <p>ชั่วโมงการทำงานรวม</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clock"></i>
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
                            <i class="fas fa-chart-line"></i>
                            แรงดันไอน้ำย้อนหลัง 30 วัน
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="pressureChart" style="min-height: 250px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            ปริมาณเชื้อเพลิงย้อนหลัง 30 วัน
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="fuelChart" style="min-height: 250px;"></canvas>
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
                    บันทึกข้อมูล Boiler ประจำวันที่: <?php echo $displayDate; ?>
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" onclick="showAddModal()">
                        <i class="fas fa-plus"></i> เพิ่มบันทึก
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>รหัสเครื่อง</th>
                                <th>ชื่อเครื่อง</th>
                                <th>แรงดันไอน้ำ</th>
                                <th>อุณหภูมิไอน้ำ</th>
                                <th>ระดับน้ำ</th>
                                <th>เชื้อเพลิง</th>
                                <th>ชั่วโมงทำงาน</th>
                                <th>หน่วย</th>
                                <th>ผู้บันทึก</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($machines as $machine): ?>
                            <?php $record = $records[$machine['id']] ?? null; ?>
                            <tr class="<?php echo $record ? 'table-success' : ''; ?>">
                                <td><?php echo htmlspecialchars($machine['machine_code']); ?></td>
                                <td><?php echo htmlspecialchars($machine['machine_name']); ?></td>
                                <td class="text-right">
                                    <?php echo $record ? number_format($record['steam_pressure'], 2) : '-'; ?>
                                </td>
                                <td class="text-right">
                                    <?php echo $record ? number_format($record['steam_temperature'], 1) : '-'; ?>
                                </td>
                                <td class="text-right">
                                    <?php echo $record ? number_format($record['feed_water_level'], 2) : '-'; ?>
                                </td>
                                <td class="text-right">
                                    <?php echo $record ? number_format($record['fuel_consumption'], 2) : '-'; ?>
                                </td>
                                <td class="text-right">
                                    <?php echo $record ? number_format($record['operating_hours'], 1) : '-'; ?>
                                </td>
                                <td>
                                    <?php if ($record): ?>
                                    bar / °C / m / L / hr
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $record ? htmlspecialchars($record['recorded_by']) : '-'; ?></td>
                                <td>
                                    <?php if ($record): ?>
                                    <button class="btn btn-sm btn-warning" onclick="editRecord(<?php echo $record['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteRecord(<?php echo $record['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-success" onclick="showAddModal(<?php echo $machine['id']; ?>)">
                                        <i class="fas fa-plus"></i> บันทึก
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($machines)): ?>
                            <tr>
                                <td colspan="10" class="text-center">
                                    ไม่พบเครื่อง Boiler กรุณาเพิ่มเครื่องจักรก่อน
                                    <a href="machines.php" class="btn btn-primary btn-sm ml-2">
                                        <i class="fas fa-plus"></i> เพิ่มเครื่องจักร
                                    </a>
                                </td>
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
<div class="modal fade" id="recordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">บันทึกข้อมูล Boiler</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="recordForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="recordId">
                    <input type="hidden" name="doc_id" value="<?php echo $document['id'] ?? 0; ?>">
                    <input type="hidden" name="record_date" value="<?php echo $currentDate; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>วันที่บันทึก</label>
                                <input type="text" class="form-control" value="<?php echo $displayDate; ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>เครื่อง Boiler <span class="text-danger">*</span></label>
                                <select class="form-control select2" name="machine_id" id="machineId" required>
                                    <option value="">เลือกเครื่อง Boiler</option>
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
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>แรงดันไอน้ำ (bar) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" 
                                       name="steam_pressure" id="steamPressure" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>อุณหภูมิไอน้ำ (°C) <span class="text-danger">*</span></label>
                                <input type="number" step="0.1" class="form-control" 
                                       name="steam_temperature" id="steamTemperature" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ระดับน้ำในหม้อ (m) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" 
                                       name="feed_water_level" id="feedWaterLevel" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ปริมาณเชื้อเพลิง (L) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" 
                                       name="fuel_consumption" id="fuelConsumption" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ชั่วโมงการทำงาน (hr) <span class="text-danger">*</span></label>
                                <input type="number" step="0.5" class="form-control" 
                                       name="operating_hours" id="operatingHours" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div class="alert alert-info small">
                                    <i class="fas fa-info-circle"></i>
                                    กรอกข้อมูลตัวเลขทั้งหมด
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>หมายเหตุ</label>
                        <textarea class="form-control" name="remarks" id="remarks" rows="2"></textarea>
                    </div>
                    
                    <div id="warningAlert" class="alert alert-warning" style="display: none;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span id="warningMessage"></span>
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
let pressureChart = null;
let fuelChart = null;

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
    
    // Load charts
    renderCharts(<?php echo json_encode($chartData); ?>);
    
    // Form submit
    $('#recordForm').on('submit', function(e) {
        e.preventDefault();
        saveRecord();
    });
    
    // Update document
    $('#updateDocument').on('click', function() {
        updateDocument();
    });
    
    // Input validation
    $('#steamPressure, #steamTemperature, #feedWaterLevel, #fuelConsumption, #operatingHours').on('input', function() {
        validateInputs();
    });
});

function renderCharts(data) {
    const labels = data.map(item => {
        const d = new Date(item.record_date);
        return d.getDate() + '/' + (d.getMonth() + 1);
    });
    
    const pressureData = data.map(item => parseFloat(item.avg_pressure) || 0);
    const fuelData = data.map(item => parseFloat(item.avg_fuel) || 0);
    
    // Pressure Chart
    const pressureCtx = document.getElementById('pressureChart').getContext('2d');
    if (pressureChart) pressureChart.destroy();
    
    pressureChart = new Chart(pressureCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'แรงดันไอน้ำเฉลี่ย (bar)',
                data: pressureData,
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
            }
        }
    });
    
    // Fuel Chart
    const fuelCtx = document.getElementById('fuelChart').getContext('2d');
    if (fuelChart) fuelChart.destroy();
    
    fuelChart = new Chart(fuelCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'ปริมาณเชื้อเพลิงเฉลี่ย (L)',
                data: fuelData,
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
            }
        }
    });
}

function validateInputs() {
    const pressure = parseFloat($('#steamPressure').val()) || 0;
    const temp = parseFloat($('#steamTemperature').val()) || 0;
    const water = parseFloat($('#feedWaterLevel').val()) || 0;
    const fuel = parseFloat($('#fuelConsumption').val()) || 0;
    const hours = parseFloat($('#operatingHours').val()) || 0;
    
    let warnings = [];
    
    if (pressure > 15) {
        warnings.push('แรงดันไอน้ำสูงเกินไป (> 15 bar)');
    }
    if (temp > 250) {
        warnings.push('อุณหภูมิสูงเกินไป (> 250°C)');
    }
    if (water < 0.5) {
        warnings.push('ระดับน้ำต่ำเกินไป (< 0.5 m)');
    }
    if (hours > 24) {
        warnings.push('ชั่วโมงการทำงานเกิน 24 ชั่วโมง');
    }
    
    if (warnings.length > 0) {
        $('#warningMessage').html(warnings.join('<br>'));
        $('#warningAlert').show();
    } else {
        $('#warningAlert').hide();
    }
}

function showAddModal(machineId = null) {
    $('#modalTitle').text('เพิ่มบันทึกข้อมูล Boiler');
    $('#recordForm')[0].reset();
    $('#recordId').val('');
    $('#warningAlert').hide();
    
    if (machineId) {
        $('#machineId').val(machineId).trigger('change');
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
                $('#modalTitle').text('แก้ไขบันทึกข้อมูล Boiler');
                $('#recordId').val(response.data.id);
                $('#machineId').val(response.data.machine_id).trigger('change');
                $('#steamPressure').val(response.data.steam_pressure);
                $('#steamTemperature').val(response.data.steam_temperature);
                $('#feedWaterLevel').val(response.data.feed_water_level);
                $('#fuelConsumption').val(response.data.fuel_consumption);
                $('#operatingHours').val(response.data.operating_hours);
                $('#remarks').val(response.data.remarks);
                
                validateInputs();
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
        error: function() {
            showNotification('เกิดข้อผิดพลาดในการบันทึกข้อมูล', 'danger');
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
    formData += '&module=boiler';
    
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

function goToToday() {
    const today = new Date();
    const dd = String(today.getDate()).padStart(2, '0');
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const yyyy = today.getFullYear();
    window.location.href = 'index.php?date=' + yyyy + '-' + mm + '-' + dd;
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>