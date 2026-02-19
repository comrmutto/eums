<?php
/**
 * Air Compressor Module - Add New Record
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Air Compressor - เพิ่มบันทึกข้อมูล';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Air Compressor', 'link' => 'index.php'],
    ['title' => 'เพิ่มบันทึกข้อมูล', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Load required files
require_once __DIR__ . '/../../includes/functions.php';

// Get database connection
$db = getDB();

// Get machines
$stmt = $db->query("SELECT * FROM mc_air WHERE status = 1 ORDER BY machine_code");
$machines = $stmt->fetchAll();

// Get document for current month
$currentMonth = date('m');
$currentYear = date('Y');

$stmt = $db->prepare("
    SELECT * FROM documents 
    WHERE module_type = 'air' 
    AND MONTH(start_date) = ? 
    AND YEAR(start_date) = ?
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([$currentMonth, $currentYear]);
$document = $stmt->fetch();

// Get today's date in Thai format
$todayThai = date('d/m/Y');
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <div class="row">
            <div class="col-md-8">
                <!-- Add Record Form -->
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-plus-circle"></i>
                            ฟอร์มบันทึกข้อมูล
                        </h3>
                    </div>
                    <form id="addRecordForm" method="POST" action="process_add.php">
                        <div class="card-body">
                            <input type="hidden" name="doc_id" value="<?php echo $document['id'] ?? 0; ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>วันที่บันทึก <span class="text-danger">*</span></label>
                                        <div class="input-group date" id="recordDatePicker" data-target-input="nearest">
                                            <input type="text" class="form-control datetimepicker-input" 
                                                   name="record_date" id="recordDate" 
                                                   value="<?php echo $todayThai; ?>" 
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
                                        <select class="form-control select2" name="machine_id" id="machineId" 
                                                style="width: 100%;" required>
                                            <option value="">เลือกเครื่องจักร</option>
                                            <?php foreach ($machines as $machine): ?>
                                                <option value="<?php echo $machine['id']; ?>" 
                                                        data-code="<?php echo $machine['machine_code']; ?>">
                                                    <?php echo $machine['machine_code'] . ' - ' . $machine['machine_name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Inspection Items Container -->
                            <div id="inspectionItemsContainer">
                                <p class="text-muted text-center">กรุณาเลือกเครื่องจักรเพื่อแสดงหัวข้อตรวจสอบ</p>
                            </div>
                            
                            <div class="form-group">
                                <label>หมายเหตุ</label>
                                <textarea class="form-control" name="remarks" id="remarks" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> บันทึกข้อมูล
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> ยกเลิก
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Info Card -->
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-info-circle"></i>
                            ข้อมูลการบันทึก
                        </h3>
                    </div>
                    <div class="card-body">
                        <p><strong>เอกสาร:</strong> <?php echo $document['doc_no'] ?? 'AC-' . ($currentYear + 543) . '-0001'; ?></p>
                        <p><strong>เดือน:</strong> <?php echo getThaiMonth($currentMonth) . ' ' . ($currentYear + 543); ?></p>
                        <p><strong>จำนวนเครื่องจักร:</strong> <?php echo count($machines); ?> เครื่อง</p>
                        
                        <hr>
                        
                        <h5>คำแนะนำ</h5>
                        <ul class="text-muted">
                            <li>กรุณาเลือกเครื่องจักรก่อน</li>
                            <li>ระบบจะแสดงหัวข้อตรวจสอบอัตโนมัติ</li>
                            <li>กรอกค่าที่วัดได้ในแต่ละหัวข้อ</li>
                            <li>ระบบจะตรวจสอบค่ามาตรฐานให้อัตโนมัติ</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Recent Records Card -->
                <div class="card card-secondary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i>
                            บันทึกล่าสุด
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush" id="recentRecords">
                            <!-- Loaded via AJAX -->
                            <li class="list-group-item text-center text-muted">กำลังโหลด...</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Include module specific JS -->
<script>
$(document).ready(function() {
    // Initialize date picker
    $('#recordDatePicker').datetimepicker({
        format: 'DD/MM/YYYY',
        locale: 'th',
        useCurrent: true
    });
    
    // Initialize select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });
    
    // Machine change event
    $('#machineId').on('change', function() {
        loadInspectionItems($(this).val());
    });
    
    // Load recent records
    loadRecentRecords();
    
    // Form submit
    $('#addRecordForm').on('submit', function(e) {
        e.preventDefault();
        submitForm();
    });
});

function loadInspectionItems(machineId) {
    if (!machineId) {
        $('#inspectionItemsContainer').html('<p class="text-muted text-center">กรุณาเลือกเครื่องจักรเพื่อแสดงหัวข้อตรวจสอบ</p>');
        return;
    }
    
    $.ajax({
        url: 'ajax/get_inspection_items.php',
        method: 'GET',
        data: { machine_id: machineId },
        beforeSend: function() {
            $('#inspectionItemsContainer').html('<p class="text-center"><i class="fas fa-spinner fa-spin"></i> กำลังโหลด...</p>');
        },
        success: function(response) {
            if (response.success && response.data.length > 0) {
                let html = '<div class="card bg-light">';
                html += '<div class="card-header bg-info"><h5>หัวข้อตรวจสอบ</h5></div>';
                html += '<div class="card-body">';
                
                response.data.forEach(function(item, index) {
                    html += `<div class="form-group row">`;
                    html += `<label class="col-sm-4 col-form-label">${item.inspection_item}</label>`;
                    html += `<div class="col-sm-4">`;
                    html += `<input type="number" step="0.01" class="form-control actual-value" `;
                    html += `name="values[${item.id}]" id="value_${item.id}" `;
                    html += `data-id="${item.id}" data-standard="${item.standard_value}" `;
                    html += `data-min="${item.min_value || ''}" data-max="${item.max_value || ''}" `;
                    html += `data-unit="${item.unit || ''}" required>`;
                    html += `</div>`;
                    html += `<div class="col-sm-4">`;
                    html += `<small class="form-text text-muted">`;
                    if (item.min_value && item.max_value) {
                        html += `มาตรฐาน: ${item.min_value} - ${item.max_value} ${item.unit}`;
                    } else {
                        html += `มาตรฐาน: ${item.standard_value} ${item.unit} ±10%`;
                    }
                    html += `</small>`;
                    html += `<div class="invalid-feedback" id="feedback_${item.id}"></div>`;
                    html += `</div>`;
                    html += `</div>`;
                });
                
                html += '</div></div>';
                $('#inspectionItemsContainer').html(html);
                
                // Add validation events
                $('.actual-value').on('input', function() {
                    validateValue($(this));
                });
                
            } else {
                $('#inspectionItemsContainer').html('<p class="text-warning text-center">ไม่พบหัวข้อตรวจสอบสำหรับเครื่องนี้</p>');
            }
        },
        error: function() {
            $('#inspectionItemsContainer').html('<p class="text-danger text-center">เกิดข้อผิดพลาดในการโหลดข้อมูล</p>');
        }
    });
}

function validateValue(input) {
    const value = parseFloat(input.val());
    const id = input.data('id');
    const standard = parseFloat(input.data('standard'));
    const min = input.data('min') ? parseFloat(input.data('min')) : null;
    const max = input.data('max') ? parseFloat(input.data('max')) : null;
    const unit = input.data('unit');
    
    const feedback = $(`#feedback_${id}`);
    
    if (isNaN(value)) {
        input.removeClass('is-valid is-invalid');
        feedback.hide();
        return;
    }
    
    let isValid = true;
    let message = '';
    
    if (min && max) {
        if (value < min || value > max) {
            isValid = false;
            message = `ค่าอยู่นอกช่วงมาตรฐาน (${min} - ${max} ${unit})`;
        }
    } else {
        const tolerance = standard * 0.1;
        if (Math.abs(value - standard) > tolerance) {
            isValid = false;
            message = `ค่าเบี่ยงเบนเกิน 10% (มาตรฐาน: ${standard} ${unit})`;
        }
    }
    
    if (isValid) {
        input.removeClass('is-invalid').addClass('is-valid');
        feedback.hide();
    } else {
        input.removeClass('is-valid').addClass('is-invalid');
        feedback.text(message).show();
    }
}

function loadRecentRecords() {
    $.ajax({
        url: 'ajax/get_recent_records.php',
        method: 'GET',
        data: { limit: 5 },
        success: function(response) {
            if (response.success && response.data.length > 0) {
                let html = '';
                response.data.forEach(function(record) {
                    html += `<li class="list-group-item">`;
                    html += `<small>${record.record_date}</small><br>`;
                    html += `<strong>${record.machine_name}</strong> - ${record.inspection_item}<br>`;
                    html += `<span class="text-muted">ค่า: ${record.actual_value}</span> `;
                    html += `<span class="badge badge-${record.status} float-right">${record.status_text}</span>`;
                    html += `</li>`;
                });
                $('#recentRecords').html(html);
            } else {
                $('#recentRecords').html('<li class="list-group-item text-center text-muted">ไม่มีข้อมูลล่าสุด</li>');
            }
        },
        error: function() {
            $('#recentRecords').html('<li class="list-group-item text-center text-danger">ไม่สามารถโหลดข้อมูลได้</li>');
        }
    });
}

function submitForm() {
    // Validate all fields
    let isValid = true;
    $('.actual-value').each(function() {
        if (!$(this).val()) {
            isValid = false;
            $(this).addClass('is-invalid');
        }
    });
    
    if (!$('#machineId').val()) {
        isValid = false;
        $('#machineId').addClass('is-invalid');
    }
    
    if (!isValid) {
        showNotification('กรุณากรอกข้อมูลให้ครบถ้วน', 'warning');
        return;
    }
    
    // Check if any value is invalid
    if ($('.is-invalid').length > 0) {
        showNotification('กรุณาตรวจสอบค่าที่กรอกอีกครั้ง', 'warning');
        return;
    }
    
    const formData = $('#addRecordForm').serialize();
    
    $.ajax({
        url: 'process_add.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('บันทึกข้อมูลเรียบร้อย', 'success');
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 1500);
            } else {
                showNotification(response.message, 'danger');
            }
        },
        error: function(xhr) {
            showNotification('เกิดข้อผิดพลาดในการบันทึกข้อมูล', 'danger');
            console.error(xhr.responseText);
        }
    });
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>