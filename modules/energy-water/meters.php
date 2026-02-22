<?php
/**
 * Energy & Water Module - Meters Management
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
$pageTitle = 'Energy & Water - จัดการมิเตอร์';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Energy & Water', 'link' => 'index.php'],
    ['title' => 'จัดการมิเตอร์', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Load required files
require_once __DIR__ . '/../../includes/functions.php';

// Get database connection
$db = getDB();

// Get filter from query string
$filterType = isset($_GET['type']) ? $_GET['type'] : '';
$filterStatus = isset($_GET['status']) ? (int)$_GET['status'] : -1;

// Build query conditions
$conditions = [];
$params = [];

if ($filterType && in_array($filterType, ['electricity', 'water'])) {
    $conditions[] = "meter_type = ?";
    $params[] = $filterType;
}

if ($filterStatus !== -1) {
    $conditions[] = "status = ?";
    $params[] = $filterStatus;
}

// Build SQL query
$sql = "SELECT * FROM mc_mdb_water";
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY meter_type, meter_code";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$meters = $stmt->fetchAll();

// Get summary statistics
$stats = [
    'total' => count($meters),
    'electricity' => 0,
    'water' => 0,
    'active' => 0,
    'inactive' => 0
];

foreach ($meters as $meter) {
    if ($meter['meter_type'] == 'electricity') $stats['electricity']++;
    else $stats['water']++;
    
    if ($meter['status'] == 1) $stats['active']++;
    else $stats['inactive']++;
}
?>
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Summary Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>มิเตอร์ทั้งหมด</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-gauge-high"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo $stats['electricity']; ?></h3>
                        <p>มิเตอร์ไฟฟ้า</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo $stats['water']; ?></h3>
                        <p>มิเตอร์น้ำ</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-water"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo $stats['active']; ?></h3>
                        <p>กำลังใช้งาน</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-filter"></i>
                    กรองข้อมูล
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" onclick="showMeterModal()">
                        <i class="fas fa-plus"></i> เพิ่มมิเตอร์
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" class="form-inline">
                    <div class="form-group mr-2" style="width: 200px; height: 40px;">
                        <label class="mr-2">ประเภท:</label>
                        <select name="type" class="form-control form-control-sm" style="height: 40px">
                            <option value="">ทั้งหมด</option>
                            <option value="electricity" <?php echo $filterType == 'electricity' ? 'selected' : ''; ?>>มิเตอร์ไฟฟ้า</option>
                            <option value="water" <?php echo $filterType == 'water' ? 'selected' : ''; ?>>มิเตอร์น้ำ</option>
                        </select>
                    </div>
                    <div class="form-group mr-2" style="width: 200px; height: 40px;">
                        <label class="mr-2">สถานะ:</label>
                        <select name="status" class="form-control form-control-sm" style="height: 40px">
                            <option value="-1">ทั้งหมด</option>
                            <option value="1" <?php echo $filterStatus == 1 ? 'selected' : ''; ?>>ใช้งาน</option>
                            <option value="0" <?php echo $filterStatus == 0 ? 'selected' : ''; ?>>ไม่ใช้งาน</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> ค้นหา
                    </button>
                    <a href="meters.php" class="btn btn-default btn-sm ml-2">
                        <i class="fas fa-redo"></i> รีเซ็ต
                    </a>
                </form>
            </div>
        </div>

        <!-- Meters List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list"></i>
                    รายการมิเตอร์
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-success btn-sm" onclick="exportData()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="metersTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ประเภท</th>
                                <th>รหัสมิเตอร์</th>
                                <th>ชื่อมิเตอร์</th>
                                <th>ตำแหน่งที่ติดตั้ง</th>
                                <th>ค่าเริ่มต้น</th>
                                <th>สถานะ</th>
                                <th>วันที่เพิ่ม</th>
                                <th>สถิติการใช้งาน</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($meters)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">
                                    <i class="fas fa-info-circle"></i>
                                    ไม่พบข้อมูลมิเตอร์
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($meters as $index => $meter): ?>
                                <?php
                                // Get usage statistics for this meter
                                $stmt = $db->prepare("
                                    SELECT 
                                        COUNT(*) as total_readings,
                                        MAX(record_date) as last_reading,
                                        AVG(usage_amount) as avg_usage,
                                        SUM(usage_amount) as total_usage
                                    FROM meter_daily_readings 
                                    WHERE meter_id = ?
                                ");
                                $stmt->execute([$meter['id']]);
                                $stats = $stmt->fetch();
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $meter['meter_type'] == 'electricity' ? 'warning' : 'info'; ?> p-2">
                                            <i class="fas fa-<?php echo $meter['meter_type'] == 'electricity' ? 'bolt' : 'water'; ?>"></i>
                                            <?php echo $meter['meter_type'] == 'electricity' ? 'ไฟฟ้า' : 'น้ำ'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($meter['meter_code']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($meter['meter_name']); ?></td>
                                    <td><?php echo htmlspecialchars($meter['location'] ?: '-'); ?></td>
                                    <td class="text-right"><?php echo number_format($meter['initial_reading'], 2); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $meter['status'] ? 'success' : 'danger'; ?>">
                                            <i class="fas fa-<?php echo $meter['status'] ? 'check-circle' : 'times-circle'; ?>"></i>
                                            <?php echo $meter['status'] ? 'ใช้งาน' : 'ไม่ใช้งาน'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($meter['created_at'])); ?></td>
                                    <td>
                                        <?php if ($stats['total_readings'] > 0): ?>
                                        <small>
                                            <i class="fas fa-chart-line"></i> <?php echo $stats['total_readings']; ?> ครั้ง<br>
                                            <i class="fas fa-calendar"></i> ล่าสุด: <?php echo date('d/m/Y', strtotime($stats['last_reading'])); ?><br>
                                            <i class="fas fa-calculator"></i> รวม: <?php echo number_format($stats['total_usage'], 2); ?>
                                        </small>
                                        <?php else: ?>
                                        <span class="text-muted">ไม่มีข้อมูล</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-info" onclick="viewMeter(<?php echo $meter['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="editMeter(<?php echo $meter['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteMeter(<?php echo $meter['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Add/Edit Meter Modal -->
<div class="modal fade" id="meterModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="meterModalTitle">เพิ่มมิเตอร์</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="meterForm" method="POST" action="ajax/save_meter.php">
                <div class="modal-body">
                    <input type="hidden" name="id" id="meterId">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ประเภทมิเตอร์ <span class="text-danger">*</span></label>
                                <select class="form-control" name="meter_type" id="meterType" required>
                                    <option value="">เลือกประเภท</option>
                                    <option value="electricity">มิเตอร์ไฟฟ้า</option>
                                    <option value="water">มิเตอร์น้ำ</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>รหัสมิเตอร์ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="meter_code" id="meterCode" 
                                       required maxlength="50" placeholder="เช่น E-001, W-001">
                                <small class="text-muted">รหัสมิเตอร์ต้องไม่ซ้ำกัน</small>
                                <div class="invalid-feedback" id="codeFeedback"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ชื่อมิเตอร์ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="meter_name" id="meterName" 
                                       required maxlength="100" placeholder="เช่น MDB 1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ตำแหน่งที่ติดตั้ง</label>
                                <input type="text" class="form-control" name="location" id="location" 
                                       maxlength="255" placeholder="เช่น อาคาร 1 ชั้น 1">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ค่าเริ่มต้น</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-control" name="initial_reading" id="initialReading" value="0">
                                    <div class="input-group-append">
                                        <span class="input-group-text" id="unitDisplay">หน่วย</span>
                                    </div>
                                </div>
                                <small class="text-muted">ค่ามิเตอร์เริ่มต้นก่อนเริ่มบันทึก</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>สถานะ</label>
                                <select class="form-control" name="status" id="status">
                                    <option value="1">ใช้งาน</option>
                                    <option value="0">ไม่ใช้งาน</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="alert alert-info" id="meterInfo" style="display: none;">
                                <i class="fas fa-info-circle"></i>
                                <span id="meterInfoText"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="saveMeterBtn">
                        <i class="fas fa-save"></i> บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Meter Modal -->
<div class="modal fade" id="viewMeterModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">รายละเอียดมิเตอร์</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="meterDetails"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#metersTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.10.21/i18n/Thai.json'
        },
        pageLength: 25,
        order: [[2, 'asc']],
        columnDefs: [
            { orderable: false, targets: [9] }
        ]
    });
    
    // Update unit display when meter type changes
    $('#meterType').on('change', function() {
        const type = $(this).val();
        if (type === 'electricity') {
            $('#unitDisplay').text('kWh');
        } else if (type === 'water') {
            $('#unitDisplay').text('m³');
        } else {
            $('#unitDisplay').text('หน่วย');
        }
    });
    
    // Validate meter code on input
    $('#meterCode').on('blur', function() {
        validateMeterCode();
    });
    
    // Form submit
    $('#meterForm').on('submit', function(e) {
        e.preventDefault();
        saveMeter();
    });
});

function showMeterModal(id = null) {
    if (id) {
        editMeter(id);
    } else {
        $('#meterModalTitle').text('เพิ่มมิเตอร์');
        $('#meterForm')[0].reset();
        $('#meterId').val('');
        $('#meterCode').removeClass('is-invalid is-valid');
        $('#codeFeedback').hide();
        $('#meterInfo').hide();
        $('#meterModal').modal('show');
    }
}

function editMeter(id) {
    $.ajax({
        url: 'ajax/get_meter.php',
        method: 'GET',
        data: { id: id },
        dataType: 'json',
        beforeSend: function() {
            showNotification('กำลังโหลดข้อมูล...', 'info');
        },
        success: function(response) {
            if (response.success) {
                $('#meterModalTitle').text('แก้ไขมิเตอร์');
                $('#meterId').val(response.data.id);
                $('#meterType').val(response.data.meter_type).trigger('change');
                $('#meterCode').val(response.data.meter_code);
                $('#meterName').val(response.data.meter_name);
                $('#location').val(response.data.location);
                $('#initialReading').val(response.data.initial_reading);
                $('#status').val(response.data.status);
                
                $('#meterCode').removeClass('is-invalid is-valid');
                $('#codeFeedback').hide();
                $('#meterInfo').hide();
                
                $('#meterModal').modal('show');
            } else {
                showNotification(response.message, 'error');
            }
        },
        error: function() {
            showNotification('เกิดข้อผิดพลาดในการโหลดข้อมูล', 'error');
        }
    });
}

function viewMeter(id) {
    $.ajax({
        url: 'ajax/get_meter.php',
        method: 'GET',
        data: { id: id },
        dataType: 'json',
        beforeSend: function() {
            $('#viewMeterModal .modal-body').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-3x"></i><p>กำลังโหลด...</p></div>');
            $('#viewMeterModal').modal('show');
        },
        success: function(response) {
            if (response.success) {
                let html = generateMeterDetails(response.data);
                $('#meterDetails').html(html);
            } else {
                $('#meterDetails').html('<div class="alert alert-danger">' + response.message + '</div>');
            }
        },
        error: function() {
            $('#meterDetails').html('<div class="alert alert-danger">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>');
        }
    });
}

function generateMeterDetails(meter) {
    const type = meter.meter_type === 'electricity' ? 'ไฟฟ้า' : 'น้ำ';
    const unit = meter.meter_type === 'electricity' ? 'kWh' : 'm³';
    const status = meter.status ? 'ใช้งาน' : 'ไม่ใช้งาน';
    const statusClass = meter.status ? 'success' : 'error';
    
    let stats = '';
    if (meter.statistics) {
        stats = `
            <div class="row mt-3">
                <div class="col-md-12">
                    <h5>สถิติการใช้งาน</h5>
                    <table class="table table-sm table-bordered">
                        <tr>
                            <th>จำนวนครั้งที่บันทึก:</th>
                            <td class="text-right">${meter.statistics.total_readings || 0} ครั้ง</td>
                        </tr>
                        <tr>
                            <th>ค่าเฉลี่ยการใช้งาน:</th>
                            <td class="text-right">${(meter.statistics.avg_usage || 0).toFixed(2)} ${unit}</td>
                        </tr>
                        <tr>
                            <th>ใช้งานล่าสุด:</th>
                            <td class="text-right">${meter.statistics.last_reading_date ? new Date(meter.statistics.last_reading_date).toLocaleDateString('th-TH') : '-'}</td>
                        </tr>
                    </table>
                </div>
            </div>
        `;
    }
    
    return `
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr>
                        <th style="width: 40%">รหัสมิเตอร์:</th>
                        <td><strong>${meter.meter_code}</strong></td>
                    </tr>
                    <tr>
                        <th>ชื่อมิเตอร์:</th>
                        <td>${meter.meter_name}</td>
                    </tr>
                    <tr>
                        <th>ประเภท:</th>
                        <td><span class="badge badge-${meter.meter_type === 'electricity' ? 'warning' : 'info'}">${type}</span></td>
                    </tr>
                    <tr>
                        <th>ตำแหน่ง:</th>
                        <td>${meter.location || '-'}</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr>
                        <th style="width: 40%">ค่าเริ่มต้น:</th>
                        <td>${meter.initial_reading.toFixed(2)} ${unit}</td>
                    </tr>
                    <tr>
                        <th>สถานะ:</th>
                        <td><span class="badge badge-${statusClass}">${status}</span></td>
                    </tr>
                    <tr>
                        <th>วันที่เพิ่ม:</th>
                        <td>${new Date(meter.created_at).toLocaleDateString('th-TH')}</td>
                    </tr>
                </table>
            </div>
        </div>
        ${stats}
    `;
}

function saveMeter() {
    // Validate form
    if (!$('#meterForm')[0].checkValidity()) {
        $('#meterForm')[0].reportValidity();
        return;
    }
    
    // Validate meter code
    if ($('#meterCode').hasClass('is-invalid')) {
        showNotification('กรุณาตรวจสอบรหัสมิเตอร์', 'warning');
        return;
    }
    
    const formData = $('#meterForm').serialize();
    
    $.ajax({
        url: 'ajax/save_meter.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        beforeSend: function() {
            $('#saveMeterBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...');
        },
        success: function(response) {
            if (response.success) {
                $('#meterModal').modal('hide');
                showNotification('บันทึกข้อมูลเรียบร้อย', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                $('#saveMeterBtn').prop('disabled', false).html('<i class="fas fa-save"></i> บันทึก');
                showNotification(response.message, 'error');
            }
        },
        error: function(xhr) {
            $('#saveMeterBtn').prop('disabled', false).html('<i class="fas fa-save"></i> บันทึก');
            let message = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            showNotification(message, 'error');
        }
    });
}

function deleteMeter(id) {
    if (confirm('คุณต้องการลบมิเตอร์นี้ใช่หรือไม่?')) {
        // Check if meter has records
        $.ajax({
            url: 'ajax/check_meter_usage.php',
            method: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(usage) {
                let message = 'คุณต้องการลบมิเตอร์นี้ใช่หรือไม่?';
                if (usage.has_records) {
                    message = `มิเตอร์นี้มีข้อมูลการบันทึกแล้ว ${usage.record_count} รายการ\n`;
                    message += `ตั้งแต่วันที่ ${new Date(usage.first_date).toLocaleDateString('th-TH')} ถึง ${new Date(usage.last_date).toLocaleDateString('th-TH')}\n`;
                    message += 'การลบจะส่งผลต่อข้อมูลเหล่านี้ คุณต้องการดำเนินการต่อหรือไม่?';
                }
                
                if (confirm(message)) {
                    $.ajax({
                        url: 'ajax/delete_meter.php',
                        method: 'POST',
                        data: { id: id, force: usage.has_records },
                        dataType: 'json',
                        beforeSend: function() {
                            showNotification('กำลังลบข้อมูล...', 'info');
                        },
                        success: function(response) {
                            if (response.success) {
                                showNotification('ลบข้อมูลเรียบร้อย', 'success');
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            } else {
                                showNotification(response.message, 'error');
                            }
                        },
                        error: function() {
                            showNotification('เกิดข้อผิดพลาดในการลบข้อมูล', 'error');
                        }
                    });
                }
            },
            error: function() {
                showNotification('ไม่สามารถตรวจสอบการใช้งานมิเตอร์ได้', 'warning');
            }
        });
    }
}

function validateMeterCode() {
    const code = $('#meterCode').val();
    const id = $('#meterId').val();
    
    if (!code) {
        $('#meterCode').removeClass('is-invalid is-valid');
        $('#codeFeedback').hide();
        return;
    }
    
    $.ajax({
        url: '../../api/validate_data.php',
        method: 'POST',
        data: {
            validation_type: 'meter_code',
            code: code,
            id: id
        },
        dataType: 'json',
        success: function(response) {
            if (!response.valid) {
                $('#meterCode').addClass('is-invalid').removeClass('is-valid');
                $('#codeFeedback').text(response.errors[0]).show();
            } else {
                $('#meterCode').removeClass('is-invalid').addClass('is-valid');
                $('#codeFeedback').hide();
            }
        },
        error: function() {
            $('#meterCode').removeClass('is-invalid is-valid');
            $('#codeFeedback').hide();
        }
    });
}

function exportData() {
    const type = $('#filterType').val() || '';
    const status = $('#filterStatus').val() || '';
    
    window.location.href = 'export_meters.php?type=' + type + '&status=' + status;
}

function showNotification(message, type) {
    // แปลง type ให้ตรงกับ toastr
    const toastrType = type === 'error' ? 'error'
                     : type === 'warning' ? 'warning'
                     : type === 'info' ? 'info'
                     : 'success';

    if (typeof toastr !== 'undefined') {
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 4000
        };
        toastr[toastrType](message);
    } else {
        // Fallback: สร้าง popup แบบ fixed ถ้าไม่มี toastr
        const icons = { success: 'check-circle', error: 'times-circle', warning: 'exclamation-triangle', info: 'info-circle' };
        const colors = { success: '#28a745', error: '#dc3545', warning: '#ffc107', info: '#17a2b8' };
        const id = 'popup_' + Date.now();
        const el = $(`
            <div id="${id}" style="
                position: fixed; top: 20px; right: 20px; z-index: 99999;
                background: #fff; border-left: 5px solid ${colors[type] || colors.info};
                border-radius: 4px; padding: 14px 20px; min-width: 280px; max-width: 380px;
                box-shadow: 0 4px 16px rgba(0,0,0,0.18); display: flex; align-items: center; gap: 10px;
            ">
                <i class="fas fa-${icons[type] || icons.info}" style="color:${colors[type] || colors.info}; font-size:1.3em;"></i>
                <span style="flex:1; font-size:.95em;">${message}</span>
                <span style="cursor:pointer; font-size:1.1em; color:#888;" onclick="$('#${id}').fadeOut(300, function(){$(this).remove()})">&times;</span>
            </div>
        `);
        $('body').append(el);
        setTimeout(() => el.fadeOut(400, function(){ $(this).remove(); }), 4000);
    }
}
</script>

<style>
.table td {
    vertical-align: middle;
}
.btn-group .btn {
    margin-right: 2px;
}
.progress-group {
    margin-bottom: 10px;
}
</style>