<?php
/**
 * Energy & Water Module - Meters Management
 * Engineering Utility Monitoring System (EUMS)
 */

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

// Get filter
$filterType = isset($_GET['type']) ? $_GET['type'] : '';

// Build query
$sql = "SELECT * FROM mc_mdb_water";
$params = [];

if ($filterType && in_array($filterType, ['electricity', 'water'])) {
    $sql .= " WHERE meter_type = ?";
    $params[] = $filterType;
}

$sql .= " ORDER BY meter_type, meter_code";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$meters = $stmt->fetchAll();
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Filter Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-filter"></i>
                    กรองข้อมูล
                </h3>
            </div>
            <div class="card-body">
                <div class="btn-group">
                    <a href="machines.php" class="btn btn-<?php echo !$filterType ? 'primary' : 'default'; ?>">
                        ทั้งหมด
                    </a>
                    <a href="machines.php?type=electricity" class="btn btn-<?php echo $filterType == 'electricity' ? 'warning' : 'default'; ?>">
                        <i class="fas fa-bolt"></i> มิเตอร์ไฟฟ้า
                    </a>
                    <a href="machines.php?type=water" class="btn btn-<?php echo $filterType == 'water' ? 'info' : 'default'; ?>">
                        <i class="fas fa-water"></i> มิเตอร์น้ำ
                    </a>
                </div>
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
                    <button type="button" class="btn btn-primary btn-sm" onclick="showMeterModal()">
                        <i class="fas fa-plus"></i> เพิ่มมิเตอร์
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped dataTable">
                        <thead>
                            <tr>
                                <th>ประเภท</th>
                                <th>รหัสมิเตอร์</th>
                                <th>ชื่อมิเตอร์</th>
                                <th>ตำแหน่งที่ติดตั้ง</th>
                                <th>ค่าเริ่มต้น</th>
                                <th>สถานะ</th>
                                <th>วันที่เพิ่ม</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($meters as $meter): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-<?php echo $meter['meter_type'] == 'electricity' ? 'warning' : 'info'; ?>">
                                        <i class="fas fa-<?php echo $meter['meter_type'] == 'electricity' ? 'bolt' : 'water'; ?>"></i>
                                        <?php echo $meter['meter_type'] == 'electricity' ? 'ไฟฟ้า' : 'น้ำ'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($meter['meter_code']); ?></td>
                                <td><?php echo htmlspecialchars($meter['meter_name']); ?></td>
                                <td><?php echo htmlspecialchars($meter['location']); ?></td>
                                <td class="text-right"><?php echo number_format($meter['initial_reading'], 2); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $meter['status'] ? 'success' : 'danger'; ?>">
                                        <?php echo $meter['status'] ? 'ใช้งาน' : 'ไม่ใช้งาน'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($meter['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editMeter(<?php echo $meter['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteMeter(<?php echo $meter['id']; ?>)">
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

<!-- Meter Modal -->
<div class="modal fade" id="meterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="meterModalTitle">เพิ่มมิเตอร์</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="meterForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="meterId">
                    
                    <div class="form-group">
                        <label>ประเภทมิเตอร์ <span class="text-danger">*</span></label>
                        <select class="form-control" name="meter_type" id="meterType" required>
                            <option value="">เลือกประเภท</option>
                            <option value="electricity">มิเตอร์ไฟฟ้า</option>
                            <option value="water">มิเตอร์น้ำ</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>รหัสมิเตอร์ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="meter_code" id="meterCode" 
                               required maxlength="50" placeholder="เช่น E-001, W-001">
                        <small class="text-muted">รหัสมิเตอร์ต้องไม่ซ้ำกัน</small>
                    </div>
                    
                    <div class="form-group">
                        <label>ชื่อมิเตอร์ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="meter_name" id="meterName" 
                               required maxlength="100" placeholder="เช่น MDB 1">
                    </div>
                    
                    <div class="form-group">
                        <label>ตำแหน่งที่ติดตั้ง</label>
                        <input type="text" class="form-control" name="location" id="location" 
                               maxlength="255" placeholder="เช่น อาคาร 1 ชั้น 1">
                    </div>
                    
                    <div class="form-group">
                        <label>ค่าเริ่มต้น</label>
                        <input type="number" step="0.01" class="form-control" name="initial_reading" id="initialReading" value="0">
                        <small class="text-muted">ค่ามิเตอร์เริ่มต้นก่อนเริ่มบันทึก</small>
                    </div>
                    
                    <div class="form-group">
                        <label>สถานะ</label>
                        <select class="form-control" name="status" id="status">
                            <option value="1">ใช้งาน</option>
                            <option value="0">ไม่ใช้งาน</option>
                        </select>
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
$(document).ready(function() {
    $('#meterForm').on('submit', function(e) {
        e.preventDefault();
        saveMeter();
    });
    
    $('#meterCode').on('blur', function() {
        validateMeterCode();
    });
});

function showMeterModal(id = null) {
    if (id) {
        editMeter(id);
    } else {
        $('#meterModalTitle').text('เพิ่มมิเตอร์');
        $('#meterForm')[0].reset();
        $('#meterId').val('');
        $('#meterModal').modal('show');
    }
}

function editMeter(id) {
    $.ajax({
        url: 'ajax/get_meter.php',
        method: 'GET',
        data: { id: id },
        success: function(response) {
            if (response.success) {
                $('#meterModalTitle').text('แก้ไขมิเตอร์');
                $('#meterId').val(response.data.id);
                $('#meterType').val(response.data.meter_type);
                $('#meterCode').val(response.data.meter_code);
                $('#meterName').val(response.data.meter_name);
                $('#location').val(response.data.location);
                $('#initialReading').val(response.data.initial_reading);
                $('#status').val(response.data.status);
                $('#meterModal').modal('show');
            }
        }
    });
}

function saveMeter() {
    if (!$('#meterForm')[0].checkValidity()) {
        $('#meterForm')[0].reportValidity();
        return;
    }
    
    const formData = $('#meterForm').serialize();
    
    $.ajax({
        url: 'ajax/save_meter.php',
        method: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                $('#meterModal').modal('hide');
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

function deleteMeter(id) {
    if (confirm('คุณต้องการลบมิเตอร์นี้ใช่หรือไม่? การลบจะส่งผลต่อข้อมูลที่เกี่ยวข้อง')) {
        $.ajax({
            url: 'ajax/delete_meter.php',
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

function validateMeterCode() {
    const code = $('#meterCode').val();
    const id = $('#meterId').val();
    
    if (code) {
        $.ajax({
            url: '../../api/validate_data.php',
            method: 'POST',
            data: {
                validation_type: 'meter_code',
                code: code,
                id: id
            },
            success: function(response) {
                if (!response.valid) {
                    $('#meterCode').addClass('is-invalid');
                    if (!$('#meterCode').next('.invalid-feedback').length) {
                        $('#meterCode').after('<div class="invalid-feedback">' + response.errors[0] + '</div>');
                    }
                } else {
                    $('#meterCode').removeClass('is-invalid');
                    $('.invalid-feedback').remove();
                }
            }
        });
    }
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>