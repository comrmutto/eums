<?php
/**
 * Boiler Module - Edit Record
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Boiler - แก้ไขบันทึกข้อมูล';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Boiler', 'link' => 'index.php'],
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
    FROM boiler_daily_records r
    JOIN mc_boiler m ON r.machine_id = m.id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    $_SESSION['error'] = 'ไม่พบข้อมูลที่ต้องการแก้ไข';
    header('Location: index.php');
    exit();
}

// Get boiler machines
$stmt = $db->query("
    SELECT * FROM mc_boiler 
    WHERE status = 1 
    ORDER BY machine_code
");
$machines = $stmt->fetchAll();

// Format date for display
$displayDate = date('d/m/Y', strtotime($record['record_date']));
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
                                        <label>วันที่บันทึก</label>
                                        <input type="text" class="form-control" value="<?php echo $displayDate; ?>" readonly>
                                        <input type="hidden" name="record_date" value="<?php echo $record['record_date']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>เครื่อง Boiler <span class="text-danger">*</span></label>
                                        <select class="form-control select2" name="machine_id" id="machineId" required>
                                            <option value="">เลือกเครื่อง Boiler</option>
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
                                                       name="steam_pressure" id="steamPressure" 
                                                       value="<?php echo $record['steam_pressure']; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>อุณหภูมิไอน้ำ (°C) <span class="text-danger">*</span></label>
                                                <input type="number" step="0.1" class="form-control" 
                                                       name="steam_temperature" id="steamTemperature" 
                                                       value="<?php echo $record['steam_temperature']; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>ระดับน้ำในหม้อ (m) <span class="text-danger">*</span></label>
                                                <input type="number" step="0.01" class="form-control" 
                                                       name="feed_water_level" id="feedWaterLevel" 
                                                       value="<?php echo $record['feed_water_level']; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>ปริมาณเชื้อเพลิง (L) <span class="text-danger">*</span></label>
                                                <input type="number" step="0.01" class="form-control" 
                                                       name="fuel_consumption" id="fuelConsumption" 
                                                       value="<?php echo $record['fuel_consumption']; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>ชั่วโมงการทำงาน (hr) <span class="text-danger">*</span></label>
                                                <input type="number" step="0.5" class="form-control" 
                                                       name="operating_hours" id="operatingHours" 
                                                       value="<?php echo $record['operating_hours']; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>หมายเหตุ</label>
                                <textarea class="form-control" name="remarks" rows="3"><?php echo htmlspecialchars($record['remarks']); ?></textarea>
                            </div>
                            
                            <div id="warningAlert" class="alert alert-warning" style="display: none;">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span id="warningMessage"></span>
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
                <!-- Original Data Card -->
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i>
                            ข้อมูลเดิม
                        </h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th>เครื่อง:</th>
                                <td><?php echo $record['machine_code'] . ' - ' . $record['machine_name']; ?></td>
                            </tr>
                            <tr>
                                <th>แรงดัน:</th>
                                <td><?php echo $record['steam_pressure']; ?> bar</td>
                            </tr>
                            <tr>
                                <th>อุณหภูมิ:</th>
                                <td><?php echo $record['steam_temperature']; ?> °C</td>
                            </tr>
                            <tr>
                                <th>ระดับน้ำ:</th>
                                <td><?php echo $record['feed_water_level']; ?> m</td>
                            </tr>
                            <tr>
                                <th>เชื้อเพลิง:</th>
                                <td><?php echo $record['fuel_consumption']; ?> L</td>
                            </tr>
                            <tr>
                                <th>ชั่วโมง:</th>
                                <td><?php echo $record['operating_hours']; ?> hr</td>
                            </tr>
                            <tr>
                                <th>ผู้บันทึก:</th>
                                <td><?php echo $record['recorded_by']; ?></td>
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
    // Initialize select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });
    
    // Trigger validation on load
    validateInputs();
    
    // Input validation
    $('#steamPressure, #steamTemperature, #feedWaterLevel, #fuelConsumption, #operatingHours').on('input', function() {
        validateInputs();
    });
    
    // Form submit
    $('#editRecordForm').on('submit', function(e) {
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
    
    // Pressure check
    if (pressure < 8 || pressure > 12) {
        warnings.push('แรงดันไอน้ำอยู่นอกเกณฑ์มาตรฐาน (8-12 bar)');
        $('#steamPressure').addClass('is-invalid');
    } else {
        $('#steamPressure').removeClass('is-invalid').addClass('is-valid');
    }
    
    // Temperature check
    if (temp < 170 || temp > 190) {
        warnings.push('อุณหภูมิไอน้ำอยู่นอกเกณฑ์มาตรฐาน (170-190 °C)');
        $('#steamTemperature').addClass('is-invalid');
    } else {
        $('#steamTemperature').removeClass('is-invalid').addClass('is-valid');
    }
    
    // Water level check
    if (water < 0.5 || water > 1.5) {
        warnings.push('ระดับน้ำในหม้ออยู่นอกเกณฑ์มาตรฐาน (0.5-1.5 m)');
        $('#feedWaterLevel').addClass('is-invalid');
    } else {
        $('#feedWaterLevel').removeClass('is-invalid').addClass('is-valid');
    }
    
    // Hours check
    if (hours > 24) {
        warnings.push('ชั่วโมงการทำงานเกิน 24 ชั่วโมง');
        $('#operatingHours').addClass('is-invalid');
    } else {
        $('#operatingHours').removeClass('is-invalid').addClass('is-valid');
    }
    
    if (warnings.length > 0) {
        $('#warningMessage').html(warnings.join('<br>'));
        $('#warningAlert').show();
    } else {
        $('#warningAlert').hide();
    }
}

function submitForm() {
    // Validate form
    if (!$('#editRecordForm')[0].checkValidity()) {
        $('#editRecordForm')[0].reportValidity();
        return;
    }
    
    // Check if there are validation errors
    const hasInvalid = $('.is-invalid').length > 0;
    
    if (hasInvalid) {
        if (!confirm('มีบางค่าอยู่นอกเกณฑ์มาตรฐาน คุณต้องการบันทึกต่อหรือไม่?')) {
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
                    window.location.href = 'view.php?id=<?php echo $id; ?>';
                }, 1500);
            } else {
                showNotification(response.message, 'danger');
            }
        },
        error: function() {
            showNotification('เกิดข้อผิดพลาดในการอัปเดตข้อมูล', 'danger');
        }
    });
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>