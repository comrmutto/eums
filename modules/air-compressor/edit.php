<?php
/**
 * Air Compressor Module - Edit Record
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Air Compressor - แก้ไขบันทึกข้อมูล';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Air Compressor', 'link' => 'index.php'],
    ['title' => 'แก้ไขบันทึกข้อมูล', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Load required files
require_once __DIR__ . '/../../includes/functions.php';

// Get database connection
$db = getDB();

// Get record ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    $_SESSION['error'] = 'ไม่พบข้อมูลที่ต้องการแก้ไข';
    header('Location: index.php');
    exit();
}

// Get record data
$stmt = $db->prepare("
    SELECT r.*, m.machine_name, m.machine_code 
    FROM air_daily_records r
    JOIN mc_air m ON r.machine_id = m.id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    $_SESSION['error'] = 'ไม่พบข้อมูลที่ต้องการแก้ไข';
    header('Location: index.php');
    exit();
}

// Get machines
$stmt = $db->query("SELECT * FROM mc_air WHERE status = 1 ORDER BY machine_code");
$machines = $stmt->fetchAll();

// Get inspection items for this machine
$stmt = $db->prepare("
    SELECT * FROM air_inspection_standards 
    WHERE machine_id = ? 
    ORDER BY sort_order
");
$stmt->execute([$record['machine_id']]);
$inspectionItems = $stmt->fetchAll();

// Format date for display
$recordDate = date('d/m/Y', strtotime($record['record_date']));
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <div class="row">
            <div class="col-md-8">
                <!-- Edit Record Form -->
                <div class="card card-warning">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-edit"></i>
                            แก้ไขบันทึกข้อมูล
                        </h3>
                    </div>
                    <form id="editRecordForm" method="POST" action="process_edit.php">
                        <div class="card-body">
                            <input type="hidden" name="id" value="<?php echo $record['id']; ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>วันที่บันทึก <span class="text-danger">*</span></label>
                                        <div class="input-group date" id="recordDatePicker" data-target-input="nearest">
                                            <input type="text" class="form-control datetimepicker-input" 
                                                   name="record_date" id="recordDate" 
                                                   value="<?php echo $recordDate; ?>" 
                                                   data-target="#recordDatePicker" required>
                                            <div class="input-group-append" data-target="#recordDatePicker" data-toggle="datetimepicker">
                                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>เครื่องจักร <span class="text-danger">*</span></label>
                                        <select class="form-control" name="machine_id" id="machineId" required>
                                            <option value="">เลือกเครื่องจักร</option>
                                            <?php foreach ($machines as $machine): ?>
                                                <option value="<?php echo $machine['id']; ?>" 
                                                        <?php echo $machine['id'] == $record['machine_id'] ? 'selected' : ''; ?>>
                                                    <?php echo $machine['machine_code'] . ' - ' . $machine['machine_name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>หัวข้อตรวจสอบ <span class="text-danger">*</span></label>
                                <select class="form-control" name="inspection_item_id" id="inspectionItemId" required>
                                    <option value="">เลือกหัวข้อตรวจสอบ</option>
                                    <?php foreach ($inspectionItems as $item): ?>
                                        <option value="<?php echo $item['id']; ?>" 
                                                data-standard="<?php echo $item['standard_value']; ?>"
                                                data-min="<?php echo $item['min_value']; ?>"
                                                data-max="<?php echo $item['max_value']; ?>"
                                                data-unit="<?php echo $item['unit']; ?>"
                                                <?php echo $item['id'] == $record['inspection_item_id'] ? 'selected' : ''; ?>>
                                            <?php echo $item['inspection_item']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>ค่าที่วัดได้ <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" class="form-control" 
                                               name="actual_value" id="actualValue" 
                                               value="<?php echo $record['actual_value']; ?>" required>
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
                                <div class="alert alert-info" id="statusAlert" style="display: none;">
                                    <i class="fas fa-info-circle"></i> 
                                    <span id="statusMessage"></span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>หมายเหตุ</label>
                                <textarea class="form-control" name="remarks" rows="2"><?php echo htmlspecialchars($record['remarks']); ?></textarea>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-save"></i> อัปเดตข้อมูล
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> ยกเลิก
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Original Record Card -->
                <div class="card card-secondary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i>
                            ข้อมูลเดิม
                        </h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th>วันที่:</th>
                                <td><?php echo date('d/m/Y', strtotime($record['record_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>เครื่องจักร:</th>
                                <td><?php echo $record['machine_code'] . ' - ' . $record['machine_name']; ?></td>
                            </tr>
                            <tr>
                                <th>ค่าที่บันทึก:</th>
                                <td><?php echo $record['actual_value']; ?></td>
                            </tr>
                            <tr>
                                <th>ผู้บันทึก:</th>
                                <td><?php echo $record['recorded_by']; ?></td>
                            </tr>
                            <tr>
                                <th>วันที่บันทึก:</th>
                                <td><?php echo date('d/m/Y H:i', strtotime($record['created_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    // Initialize date picker
    $('#recordDatePicker').datetimepicker({
        format: 'DD/MM/YYYY',
        locale: 'th',
        useCurrent: false
    });
    
    // Initialize select2
    $('#machineId, #inspectionItemId').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });
    
    // Load initial values
    loadStandardInfo();
    
    // Machine change event
    $('#machineId').on('change', function() {
        loadInspectionItems($(this).val());
    });
    
    // Inspection item change event
    $('#inspectionItemId').on('change', function() {
        loadStandardInfo();
    });
    
    // Actual value change event
    $('#actualValue').on('input', function() {
        validateValue();
    });
    
    // Form submit
    $('#editRecordForm').on('submit', function(e) {
        e.preventDefault();
        submitForm();
    });
});

function loadInspectionItems(machineId) {
    if (!machineId) {
        $('#inspectionItemId').html('<option value="">เลือกหัวข้อตรวจสอบ</option>');
        return;
    }
    
    $.ajax({
        url: 'ajax/get_inspection_items.php',
        method: 'GET',
        data: { machine_id: machineId },
        success: function(response) {
            if (response.success) {
                let options = '<option value="">เลือกหัวข้อตรวจสอบ</option>';
                response.data.forEach(function(item) {
                    options += `<option value="${item.id}" 
                        data-standard="${item.standard_value}"
                        data-min="${item.min_value || ''}"
                        data-max="${item.max_value || ''}"
                        data-unit="${item.unit || ''}">${item.inspection_item}</option>`;
                });
                $('#inspectionItemId').html(options);
            }
        }
    });
}

function loadStandardInfo() {
    const option = $('#inspectionItemId option:selected');
    const standard = option.data('standard');
    const min = option.data('min');
    const max = option.data('max');
    const unit = option.data('unit');
    
    $('#unit').val(unit || '');
    
    if (option.val()) {
        validateValue();
    }
}

function validateValue() {
    const value = parseFloat($('#actualValue').val());
    const option = $('#inspectionItemId option:selected');
    const standard = parseFloat(option.data('standard'));
    const min = option.data('min') ? parseFloat(option.data('min')) : null;
    const max = option.data('max') ? parseFloat(option.data('max')) : null;
    const unit = option.data('unit');
    
    if (isNaN(value) || !option.val()) {
        $('#statusAlert').hide();
        return;
    }
    
    let isValid = true;
    let message = '';
    
    if (min && max) {
        if (value < min || value > max) {
            isValid = false;
            message = `ค่าอยู่นอกช่วงมาตรฐาน (ต้องอยู่ระหว่าง ${min} - ${max} ${unit})`;
        } else {
            message = `ค่าอยู่ในช่วงมาตรฐาน (${min} - ${max} ${unit})`;
        }
    } else {
        const tolerance = standard * 0.1;
        if (Math.abs(value - standard) > tolerance) {
            isValid = false;
            message = `ค่าเบี่ยงเบนจากมาตรฐานเกิน 10% (มาตรฐาน: ${standard} ${unit})`;
        } else {
            const deviation = ((value - standard) / standard * 100).toFixed(2);
            message = `ค่าอยู่ในเกณฑ์มาตรฐาน (ค่าเบี่ยงเบน: ${deviation}%)`;
        }
    }
    
    $('#statusMessage').text(message);
    $('#statusAlert').removeClass('alert-success alert-danger')
        .addClass(isValid ? 'alert-success' : 'alert-danger')
        .show();
}

function submitForm() {
    // Validate form
    if (!$('#machineId').val() || !$('#inspectionItemId').val() || !$('#actualValue').val()) {
        showNotification('กรุณากรอกข้อมูลให้ครบถ้วน', 'warning');
        return;
    }
    
    // Check if value is valid
    if ($('#statusAlert').hasClass('alert-danger')) {
        if (!confirm('ค่าที่กรอกอยู่นอกเกณฑ์มาตรฐาน คุณต้องการบันทึกต่อหรือไม่?')) {
            return;
        }
    }
    
    const formData = $('#editRecordForm').serialize();
    
    $.ajax({
        url: 'process_edit.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('อัปเดตข้อมูลเรียบร้อย', 'success');
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 1500);
            } else {
                showNotification(response.message, 'danger');
            }
        },
        error: function(xhr) {
            showNotification('เกิดข้อผิดพลาดในการอัปเดตข้อมูล', 'danger');
            console.error(xhr.responseText);
        }
    });
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>