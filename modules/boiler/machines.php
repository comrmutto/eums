<?php
/**
 * Boiler Module - Machines Management
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Boiler - จัดการเครื่องจักร';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Boiler', 'link' => 'index.php'],
    ['title' => 'จัดการเครื่องจักร', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Load required files
require_once __DIR__ . '/../../includes/functions.php';

// Get database connection
$db = getDB();

// Get all boiler machines
$stmt = $db->query("
    SELECT * FROM mc_boiler 
    ORDER BY machine_code
");
$machines = $stmt->fetchAll();
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Machines List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list"></i>
                    รายการเครื่อง Boiler
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" onclick="showMachineModal()">
                        <i class="fas fa-plus"></i> เพิ่มเครื่องจักร
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped dataTable">
                        <thead>
                            <tr>
                                <th>รหัสเครื่อง</th>
                                <th>ชื่อเครื่อง</th>
                                <th>ยี่ห้อ</th>
                                <th>รุ่น</th>
                                <th>ความจุ</th>
                                <th>แรงดันสูงสุด</th>
                                <th>สถานะ</th>
                                <th>วันที่เพิ่ม</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($machines as $machine): ?>
                            <?php
                            // Get usage statistics
                            $stmt = $db->prepare("
                                SELECT 
                                    COUNT(*) as record_count,
                                    MAX(record_date) as last_used
                                FROM boiler_daily_records 
                                WHERE machine_id = ?
                            ");
                            $stmt->execute([$machine['id']]);
                            $stats = $stmt->fetch();
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($machine['machine_code']); ?></td>
                                <td><?php echo htmlspecialchars($machine['machine_name']); ?></td>
                                <td><?php echo htmlspecialchars($machine['brand']); ?></td>
                                <td><?php echo htmlspecialchars($machine['model']); ?></td>
                                <td class="text-right"><?php echo number_format($machine['capacity'], 2); ?> T/hr</td>
                                <td class="text-right"><?php echo number_format($machine['pressure_rating'], 2); ?> bar</td>
                                <td>
                                    <span class="badge badge-<?php echo $machine['status'] ? 'success' : 'danger'; ?>">
                                        <?php echo $machine['status'] ? 'ใช้งาน' : 'ไม่ใช้งาน'; ?>
                                    </span>
                                    <?php if ($stats['record_count'] > 0): ?>
                                    <br><small class="text-muted">
                                        ใช้ล่าสุด: <?php echo date('d/m/Y', strtotime($stats['last_used'])); ?>
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($machine['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editMachine(<?php echo $machine['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteMachine(<?php echo $machine['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($machines)): ?>
                            <tr>
                                <td colspan="9" class="text-center">ไม่พบข้อมูลเครื่องจักร</td>
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
<div class="modal fade" id="machineModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="machineModalTitle">เพิ่มเครื่อง Boiler</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="machineForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="machineId">
                    
                    <div class="form-group">
                        <label>รหัสเครื่อง <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="machine_code" id="machineCode" 
                               required maxlength="50" placeholder="เช่น BLR-001">
                        <small class="text-muted">รหัสเครื่องต้องไม่ซ้ำกัน</small>
                    </div>
                    
                    <div class="form-group">
                        <label>ชื่อเครื่อง <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="machine_name" id="machineName" 
                               required maxlength="100" placeholder="เช่น Boiler 1">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ยี่ห้อ</label>
                                <input type="text" class="form-control" name="brand" id="brand" 
                                       maxlength="100" placeholder="เช่น Miura">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>รุ่น</label>
                                <input type="text" class="form-control" name="model" id="model" 
                                       maxlength="100" placeholder="เช่น EX-100">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ความจุ (T/hr)</label>
                                <input type="number" step="0.01" class="form-control" name="capacity" id="capacity">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>แรงดันสูงสุด (bar)</label>
                                <input type="number" step="0.01" class="form-control" name="pressure_rating" id="pressureRating">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>สถานะ</label>
                        <select class="form-control" name="status" id="status">
                            <option value="1">ใช้งาน</option>
                            <option value="0">ไม่ใช้งาน</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        ข้อมูลทั้งหมดจะถูกบันทึกเป็นตัวเลข
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
    $('#machineForm').on('submit', function(e) {
        e.preventDefault();
        saveMachine();
    });
    
    $('#machineCode').on('blur', function() {
        validateMachineCode();
    });
});

function showMachineModal(id = null) {
    if (id) {
        editMachine(id);
    } else {
        $('#machineModalTitle').text('เพิ่มเครื่อง Boiler');
        $('#machineForm')[0].reset();
        $('#machineId').val('');
        $('#machineModal').modal('show');
    }
}

function editMachine(id) {
    $.ajax({
        url: 'ajax/get_machine.php',
        method: 'GET',
        data: { id: id },
        success: function(response) {
            if (response.success) {
                $('#machineModalTitle').text('แก้ไขเครื่อง Boiler');
                $('#machineId').val(response.data.id);
                $('#machineCode').val(response.data.machine_code);
                $('#machineName').val(response.data.machine_name);
                $('#brand').val(response.data.brand);
                $('#model').val(response.data.model);
                $('#capacity').val(response.data.capacity);
                $('#pressureRating').val(response.data.pressure_rating);
                $('#status').val(response.data.status);
                $('#machineModal').modal('show');
            }
        }
    });
}

function saveMachine() {
    if (!$('#machineForm')[0].checkValidity()) {
        $('#machineForm')[0].reportValidity();
        return;
    }
    
    const formData = $('#machineForm').serialize();
    
    $.ajax({
        url: 'ajax/save_machine.php',
        method: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                $('#machineModal').modal('hide');
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

function deleteMachine(id) {
    // Check if machine has records
    $.ajax({
        url: 'ajax/check_machine_usage.php',
        method: 'GET',
        data: { id: id },
        success: function(usage) {
            let message = 'คุณต้องการลบเครื่อง Boiler นี้ใช่หรือไม่?';
            if (usage.has_records) {
                message = `เครื่องนี้มีข้อมูลการบันทึกแล้ว ${usage.record_count} รายการ\n`;
                message += `ตั้งแต่วันที่ ${usage.first_date} ถึง ${usage.last_date}\n`;
                message += 'การลบจะส่งผลต่อข้อมูลเหล่านี้ คุณต้องการดำเนินการต่อหรือไม่?';
            }
            
            if (confirm(message)) {
                $.ajax({
                    url: 'ajax/delete_machine.php',
                    method: 'POST',
                    data: { id: id, force: usage.has_records },
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
    });
}

function validateMachineCode() {
    const code = $('#machineCode').val();
    const id = $('#machineId').val();
    
    if (code) {
        $.ajax({
            url: '../../api/validate_data.php',
            method: 'POST',
            data: {
                validation_type: 'machine_code',
                code: code,
                id: id,
                module: 'boiler'
            },
            success: function(response) {
                if (!response.valid) {
                    $('#machineCode').addClass('is-invalid');
                    if (!$('#machineCode').next('.invalid-feedback').length) {
                        $('#machineCode').after('<div class="invalid-feedback">' + response.errors[0] + '</div>');
                    }
                } else {
                    $('#machineCode').removeClass('is-invalid');
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