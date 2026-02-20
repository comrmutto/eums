<?php
/**
 * Air Compressor Module - Machines Management
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Air Compressor - จัดการเครื่องจักร';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Air Compressor', 'link' => 'index.php'],
    ['title' => 'จัดการเครื่องจักร', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Load required files
require_once __DIR__ . '/../../includes/functions.php';

// Get database connection
$db = getDB();

// Get all machines
$stmt = $db->query("SELECT * FROM mc_air ORDER BY machine_code");
$machines = $stmt->fetchAll();
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Notification Container -->
        <div id="notificationContainer"></div>
        
        <!-- Machines List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list"></i>
                    รายการเครื่องจักร
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" onclick="showMachineModal()">
                        <i class="fas fa-plus"></i> เพิ่มเครื่องจักร
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="machinesTable">
                        <thead>
                            <tr>
                                <th>รหัสเครื่อง</th>
                                <th>ชื่อเครื่อง</th>
                                <th>ยี่ห้อ</th>
                                <th>รุ่น</th>
                                <th>ความจุ</th>
                                <th>หน่วย</th>
                                <th>สถานะ</th>
                                <th>วันที่เพิ่ม</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($machines as $machine): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($machine['machine_code']); ?></td>
                                <td><?php echo htmlspecialchars($machine['machine_name']); ?></td>
                                <td><?php echo htmlspecialchars($machine['brand']); ?></td>
                                <td><?php echo htmlspecialchars($machine['model']); ?></td>
                                <td class="text-right"><?php echo number_format($machine['capacity'], 2); ?></td>
                                <td><?php echo htmlspecialchars($machine['unit']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $machine['status'] ? 'success' : 'danger'; ?>">
                                        <?php echo $machine['status'] ? 'ใช้งาน' : 'ไม่ใช้งาน'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($machine['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-warning" onclick="editMachine(<?php echo $machine['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteMachine(<?php echo $machine['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($machines)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">
                                    <i class="fas fa-info-circle"></i> ไม่พบข้อมูลเครื่องจักร
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

<!-- Machine Modal -->
<div class="modal fade" id="machineModal" tabindex="-1" role="dialog" aria-labelledby="machineModalTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="machineModalTitle">เพิ่มเครื่องจักร</h5>
                <!-- FIX #1: เปลี่ยน data-dismiss="modal" → data-bs-dismiss="modal" (Bootstrap 5) -->
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="machineForm" name="machineForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="machineId" value="">
                    
                    <div class="form-group">
                        <label for="machineCode">รหัสเครื่อง <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="machine_code" id="machineCode" required 
                               maxlength="50" placeholder="เช่น AC-001">
                        <small class="text-muted">รหัสเครื่องต้องไม่ซ้ำกัน</small>
                        <div class="invalid-feedback" id="codeFeedback"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="machineName">ชื่อเครื่อง <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="machine_name" id="machineName" required 
                               maxlength="100" placeholder="เช่น Air Compressor 1">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="brand">ยี่ห้อ</label>
                                <input type="text" class="form-control" name="brand" id="brand" 
                                       maxlength="100" placeholder="เช่น Ingersoll Rand">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="model">รุ่น</label>
                                <input type="text" class="form-control" name="model" id="model" 
                                       maxlength="100" placeholder="เช่น SSR-50">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="capacity">ความจุ</label>
                                <input type="number" step="0.01" class="form-control" name="capacity" id="capacity" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="unit">หน่วย</label>
                                <select class="form-control" name="unit" id="unit">
                                    <option value="kW">kW</option>
                                    <option value="HP">HP</option>
                                    <option value="m³/min">m³/min</option>
                                    <option value="CFM">CFM</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">สถานะ</label>
                        <select class="form-control" name="status" id="status">
                            <option value="1">ใช้งาน</option>
                            <option value="0">ไม่ใช้งาน</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info" id="modalInfo" style="display: none;">
                        <i class="fas fa-info-circle"></i> <span id="modalInfoText"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <!-- FIX #1: เปลี่ยน data-dismiss="modal" → data-bs-dismiss="modal" (Bootstrap 5) -->
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">
                        <i class="fas fa-save"></i> บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ลบ script tags ซ้ำออก (jQuery/Bootstrap/AdminLTE โหลดใน footer.php แล้ว) -->

<script>
// ตรวจสอบว่า jQuery โหลดหรือไม่
if (typeof jQuery === 'undefined') {
    console.error('jQuery is not loaded!');
    document.write('<div class="alert alert-danger">เกิดข้อผิดพลาด: jQuery ไม่สามารถโหลดได้ กรุณารีเฟรชหน้า</div>');
} else {
    console.log('jQuery loaded successfully');
}

$(document).ready(function() {
    console.log('Document ready');
    
    // FIX #2: DataTable - destroy ก่อนถ้ามีอยู่แล้ว + เปลี่ยน URL ภาษาไทยให้ตรง version
    if ($.fn.DataTable) {
        if ($.fn.DataTable.isDataTable('#machinesTable')) {
            $('#machinesTable').DataTable().destroy();
        }
        $('#machinesTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/th.json'
            },
            pageLength: 25,
            order: [[0, 'asc']]
        });
    }
    
    $('#machineForm').on('submit', function(e) {
        e.preventDefault();
        console.log('Form submitted');
        saveMachine();
    });
    
    $('#machineCode').on('blur', function() {
        validateMachineCode();
    });
    
    $('#machineCode').on('input', function() {
        $(this).removeClass('is-invalid');
        $('#codeFeedback').hide();
    });
});

function showMachineModal(id = null) {
    console.log('showMachineModal called with id:', id);
    if (id) {
        editMachine(id);
    } else {
        $('#machineModalTitle').text('เพิ่มเครื่องจักร');
        $('#machineForm')[0].reset();
        $('#machineId').val('');
        $('#machineCode').removeClass('is-invalid');
        $('#codeFeedback').hide();
        $('#modalInfo').hide();
        // FIX #1: ใช้ Bootstrap 5 modal API
        var modal = new bootstrap.Modal(document.getElementById('machineModal'));
        modal.show();
    }
}

function editMachine(id) {
    console.log('editMachine called with id:', id);
    $.ajax({
        url: 'ajax/get_machine.php',
        method: 'GET',
        data: { id: id },
        dataType: 'json',
        beforeSend: function() {
            $('#modalInfo').show().removeClass('alert-danger').addClass('alert-info');
            $('#modalInfoText').text('กำลังโหลดข้อมูล...');
        },
        success: function(response) {
            console.log('Edit response:', response);
            if (response.success) {
                $('#machineModalTitle').text('แก้ไขเครื่องจักร');
                $('#machineId').val(response.data.machine.id);
                $('#machineCode').val(response.data.machine.machine_code);
                $('#machineName').val(response.data.machine.machine_name);
                $('#brand').val(response.data.machine.brand);
                $('#model').val(response.data.machine.model);
                $('#capacity').val(response.data.machine.capacity);
                $('#unit').val(response.data.machine.unit);
                $('#status').val(response.data.machine.status);
                
                $('#machineCode').removeClass('is-invalid');
                $('#codeFeedback').hide();
                $('#modalInfo').hide();
                
                // FIX #1: ใช้ Bootstrap 5 modal API
                var modal = new bootstrap.Modal(document.getElementById('machineModal'));
                modal.show();
            } else {
                $('#modalInfo').removeClass('alert-info').addClass('alert-danger');
                $('#modalInfoText').text(response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            console.error('Response:', xhr.responseText);
            let message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.message) {
                    message = response.message;
                }
            } catch(e) {
                console.error('Parse error:', e);
            }
            $('#modalInfo').removeClass('alert-info').addClass('alert-danger');
            $('#modalInfoText').text(message);
        }
    });
}

function saveMachine() {
    console.log('saveMachine called');
    
    // Validate form
    if (!$('#machineForm')[0].checkValidity()) {
        $('#machineForm')[0].reportValidity();
        return;
    }
    
    // Check if machine code is valid
    if ($('#machineCode').hasClass('is-invalid')) {
        showNotification('กรุณาตรวจสอบรหัสเครื่องอีกครั้ง', 'warning');
        return;
    }
    
    const formData = $('#machineForm').serialize();
    console.log('Form data:', formData);
    
    $.ajax({
        url: 'ajax/save_machine.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        beforeSend: function() {
            $('#saveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...');
        },
        success: function(response) {
            console.log('Save response:', response);
            if (response.success) {
                // FIX #1: ใช้ Bootstrap 5 modal API สำหรับปิด modal
                var modalEl = document.getElementById('machineModal');
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
                
                showNotification('บันทึกข้อมูลเรียบร้อย', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save"></i> บันทึก');
                showNotification(response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            console.error('Response:', xhr.responseText);
            
            $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save"></i> บันทึก');
            
            let message = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.message) {
                    message = response.message;
                }
            } catch(e) {
                console.error('Parse error:', e);
            }
            showNotification(message, 'error');
        }
    });
}

function deleteMachine(id) {
    console.log('deleteMachine called with id:', id);
    if (confirm('คุณต้องการลบเครื่องจักรนี้ใช่หรือไม่? การลบจะส่งผลต่อข้อมูลที่เกี่ยวข้อง')) {
        $.ajax({
            url: 'ajax/delete_machine.php',
            method: 'POST',
            data: { id: id },
            dataType: 'json',
            beforeSend: function() {
                showNotification('กำลังลบข้อมูล...', 'info');
            },
            success: function(response) {
                console.log('Delete response:', response);
                if (response.success) {
                    showNotification('ลบข้อมูลเรียบร้อย', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    if (response.has_related) {
                        if (confirm(response.message + '\n\nต้องการลบข้อมูลที่เกี่ยวข้องทั้งหมดหรือไม่?')) {
                            forceDeleteMachine(id);
                        }
                    } else {
                        showNotification(response.message, 'error');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                let message = 'เกิดข้อผิดพลาดในการลบข้อมูล';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        message = response.message;
                    }
                } catch(e) {}
                showNotification(message, 'error');
            }
        });
    }
}

function forceDeleteMachine(id) {
    console.log('forceDeleteMachine called with id:', id);
    $.ajax({
        url: 'ajax/delete_machine.php',
        method: 'POST',
        data: { id: id, force: true },
        dataType: 'json',
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
        error: function(xhr) {
            showNotification('เกิดข้อผิดพลาดในการลบข้อมูล', 'error');
        }
    });
}

function validateMachineCode() {
    const code = $('#machineCode').val();
    const id = $('#machineId').val();
    
    if (!code) {
        $('#machineCode').removeClass('is-invalid');
        $('#codeFeedback').hide();
        return;
    }
    
    $.ajax({
        url: '../../api/validate_data.php',
        method: 'POST',
        data: {
            validation_type: 'machine_code',
            code: code,
            id: id,
            module: 'air'
        },
        dataType: 'json',
        success: function(response) {
            console.log('Validate response:', response);
            if (!response.valid) {
                $('#machineCode').addClass('is-invalid');
                $('#codeFeedback').text(response.errors[0]).show();
            } else {
                $('#machineCode').removeClass('is-invalid');
                $('#codeFeedback').hide();
            }
        },
        error: function(xhr) {
            console.error('Validate error:', xhr);
            // ถ้า validate API error ให้ผ่านไปก่อน ไม่บล็อก
            $('#machineCode').removeClass('is-invalid');
            $('#codeFeedback').hide();
        }
    });
}

// FIX #3: showNotification - แปลง 'danger' → 'error' เพราะ toastr ไม่มี method 'danger'
function showNotification(message, type) {
    // แปลง type ให้ตรงกับ toastr (danger → error)
    const toastrType = type === 'danger' ? 'error' : type;
    
    if (typeof toastr !== 'undefined') {
        toastr[toastrType](message);
        return;
    }
    
    // Fallback: alert div (Bootstrap 5 ใช้ data-bs-dismiss)
    const displayType = type === 'error' ? 'danger' : type;
    const alertHtml = `
        <div class="alert alert-${displayType} alert-dismissible fade show" role="alert">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    $('#notificationContainer').html(alertHtml);
    
    setTimeout(function() {
        $('#notificationContainer .alert').fadeOut(300, function() {
            $(this).remove();
        });
    }, 5000);
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>