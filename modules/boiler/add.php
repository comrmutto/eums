<?php
/**
 * Boiler Module - Add New Record
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Boiler - เพิ่มบันทึกข้อมูล';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Boiler', 'link' => 'index.php'],
    ['title' => 'เพิ่มบันทึกข้อมูล', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Load required files
require_once __DIR__ . '/../../includes/functions.php';

// Get database connection
$db = getDB();

// Get today's date
$today = date('Y-m-d');
$displayDate = date('d/m/Y');

// Get document info
$stmt = $db->prepare("
    SELECT * FROM documents 
    WHERE module_type = 'boiler' 
    AND start_date <= ? 
    ORDER BY start_date DESC LIMIT 1
");
$stmt->execute([$today]);
$document = $stmt->fetch();

// Get boiler machines
$stmt = $db->query("
    SELECT * FROM mc_boiler 
    WHERE status = 1 
    ORDER BY machine_code
");
$machines = $stmt->fetchAll();

// Get machine ID from query if specified
$selectedMachine = isset($_GET['machine_id']) ? (int)$_GET['machine_id'] : 0;
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
                            <input type="hidden" name="record_date" value="<?php echo $today; ?>">
                            
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
                                                <option value="<?php echo $machine['id']; ?>" 
                                                        <?php echo $machine['id'] == $selectedMachine ? 'selected' : ''; ?>>
                                                    <?php echo $machine['machine_code'] . ' - ' . $machine['machine_name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card card-secondary">
                                <div class="card-header">
                                    <h5 class="card-title">ข้อมูลการทำงาน</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>แรงดันไอน้ำ (bar) <span class="text-danger">*</span></label>
                                                <input type="number" step="0.01" class="form-control" 
                                                       name="steam_pressure" id="steamPressure" required>
                                                <small class="text-muted">ค่ามาตรฐาน: 8-12 bar</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>อุณหภูมิไอน้ำ (°C) <span class="text-danger">*</span></label>
                                                <input type="number" step="0.1" class="form-control" 
                                                       name="steam_temperature" id="steamTemperature" required>
                                                <small class="text-muted">ค่ามาตรฐาน: 170-190 °C</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>ระดับน้ำในหม้อ (m) <span class="text-danger">*</span></label>
                                                <input type="number" step="0.01" class="form-control" 
                                                       name="feed_water_level" id="feedWaterLevel" required>
                                                <small class="text-muted">ค่ามาตรฐาน: 0.5-1.5 m</small>
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
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>หมายเหตุ</label>
                                <textarea class="form-control" name="remarks" rows="3" placeholder="หมายเหตุเพิ่มเติม (ถ้ามี)"></textarea>
                            </div>
                            
                            <div id="warningAlert" class="alert alert-warning" style="display: none;">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span id="warningMessage"></span>
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
                        <p><strong>วันที่:</strong> <?php echo $displayDate; ?></p>
                        <p><strong>เอกสาร:</strong> <?php echo $document['doc_no'] ?? 'BLR-' . date('Ymd'); ?></p>
                        <p><strong>เครื่องที่พร้อมใช้งาน:</strong> <?php echo count($machines); ?> เครื่อง</p>
                        
                        <hr>
                        
                        <h5>ค่ามาตรฐาน</h5>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check-circle text-success"></i> แรงดัน: 8-12 bar</li>
                            <li><i class="fas fa-check-circle text-success"></i> อุณหภูมิ: 170-190 °C</li>
                            <li><i class="fas fa-check-circle text-success"></i> ระดับน้ำ: 0.5-1.5 m</li>
                        </ul>
                        
                        <hr>
                        
                        <h5>คำแนะนำ</h5>
                        <ul class="text-muted">
                            <li>กรอกข้อมูลตัวเลขทั้งหมด</li>
                            <li>ตรวจสอบค่าที่กรอกให้อยู่ในเกณฑ์</li>
                            <li>ระบบจะแจ้งเตือนเมื่อค่านอกเกณฑ์</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    // Initialize select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });
    
    // Input validation
    $('#steamPressure, #steamTemperature, #feedWaterLevel, #fuelConsumption, #operatingHours').on('input', function() {
        validateInputs();
    });
    
    // Form submit
    $('#addRecordForm').on('submit', function(e) {
        e.preventDefault();
        submitForm();
    });
});

function validateInputs() {
    const pressure = parseFloat($('#steamPressure').val()) || 0;
    const temp = parseFloat($('#steamTemperature').val()) || 0;
    const water = parseFloat($('#feedWaterLevel').val()) || 0;
    const hours = parseFloat($('#operatingHours').val()) || 0;
    
    let warnings = [];
    let hasError = false;
    
    // Pressure check
    if (pressure < 8 || pressure > 12) {
        warnings.push('แรงดันไอน้ำอยู่นอกเกณฑ์มาตรฐาน (8-12 bar)');
        $('#steamPressure').addClass('is-invalid');
        hasError = true;
    } else {
        $('#steamPressure').removeClass('is-invalid').addClass('is-valid');
    }
    
    // Temperature check
    if (temp < 170 || temp > 190) {
        warnings.push('อุณหภูมิไอน้ำอยู่นอกเกณฑ์มาตรฐาน (170-190 °C)');
        $('#steamTemperature').addClass('is-invalid');
        hasError = true;
    } else {
        $('#steamTemperature').removeClass('is-invalid').addClass('is-valid');
    }
    
    // Water level check
    if (water < 0.5 || water > 1.5) {
        warnings.push('ระดับน้ำในหม้ออยู่นอกเกณฑ์มาตรฐาน (0.5-1.5 m)');
        $('#feedWaterLevel').addClass('is-invalid');
        hasError = true;
    } else {
        $('#feedWaterLevel').removeClass('is-invalid').addClass('is-valid');
    }
    
    // Hours check
    if (hours > 24) {
        warnings.push('ชั่วโมงการทำงานเกิน 24 ชั่วโมง');
        $('#operatingHours').addClass('is-invalid');
        hasError = true;
    } else {
        $('#operatingHours').removeClass('is-invalid').addClass('is-valid');
    }
    
    if (warnings.length > 0) {
        $('#warningMessage').html(warnings.join('<br>'));
        $('#warningAlert').show();
    } else {
        $('#warningAlert').hide();
    }
    
    return !hasError;
}

function submitForm() {
    // Validate form
    if (!$('#addRecordForm')[0].checkValidity()) {
        $('#addRecordForm')[0].reportValidity();
        return;
    }
    
    // Check machine selected
    if (!$('#machineId').val()) {
        showNotification('กรุณาเลือกเครื่อง Boiler', 'warning');
        return;
    }
    
    // Validate inputs
    const isValid = validateInputs();
    
    if (!isValid) {
        if (!confirm('มีบางค่าอยู่นอกเกณฑ์มาตรฐาน คุณต้องการบันทึกต่อหรือไม่?')) {
            return;
        }
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
        error: function() {
            showNotification('เกิดข้อผิดพลาดในการบันทึกข้อมูล', 'danger');
        }
    });
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>