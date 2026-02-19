<?php
/**
 * Energy & Water Module - Edit Reading
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Energy & Water - แก้ไขบันทึกค่ามิเตอร์';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Energy & Water', 'link' => 'index.php'],
    ['title' => 'แก้ไขบันทึกค่ามิเตอร์', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Load required files
require_once __DIR__ . '/../../includes/functions.php';

// Get database connection
$db = getDB();

// Get reading ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    $_SESSION['error'] = 'ไม่พบข้อมูลที่ต้องการแก้ไข';
    header('Location: index.php');
    exit();
}

// Get reading data
$stmt = $db->prepare("
    SELECT r.*, m.meter_name, m.meter_code, m.meter_type, m.location
    FROM meter_daily_readings r
    JOIN mc_mdb_water m ON r.meter_id = m.id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$reading = $stmt->fetch();

if (!$reading) {
    $_SESSION['error'] = 'ไม่พบข้อมูลที่ต้องการแก้ไข';
    header('Location: index.php');
    exit();
}

// Get document info
$stmt = $db->prepare("
    SELECT * FROM documents 
    WHERE id = ?
");
$stmt->execute([$reading['doc_id']]);
$document = $stmt->fetch();

// Get meters for dropdown
$stmt = $db->query("
    SELECT * FROM mc_mdb_water 
    WHERE status = 1 
    ORDER BY meter_type, meter_code
");
$meters = $stmt->fetchAll();

$displayDate = date('d/m/Y', strtotime($reading['record_date']));
$unit = $reading['meter_type'] == 'electricity' ? 'kWh' : 'm³';
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
                            แก้ไขบันทึกค่ามิเตอร์
                        </h3>
                    </div>
                    <form id="editRecordForm" method="POST" action="process_edit.php">
                        <div class="card-body">
                            <input type="hidden" name="id" value="<?php echo $reading['id']; ?>">
                            <input type="hidden" name="doc_id" value="<?php echo $reading['doc_id']; ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>วันที่บันทึก <span class="text-danger">*</span></label>
                                        <div class="input-group date" id="recordDatePicker" data-target-input="nearest">
                                            <input type="text" class="form-control datetimepicker-input" 
                                                   name="record_date" id="recordDate" 
                                                   value="<?php echo $displayDate; ?>" 
                                                   data-target="#recordDatePicker" required>
                                            <div class="input-group-append" data-target="#recordDatePicker" data-toggle="datetimepicker">
                                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>มิเตอร์ <span class="text-danger">*</span></label>
                                        <select class="form-control select2" name="meter_id" id="meterId" required>
                                            <option value="">เลือกมิเตอร์</option>
                                            <optgroup label="มิเตอร์ไฟฟ้า">
                                                <?php foreach ($meters as $meter): ?>
                                                    <?php if ($meter['meter_type'] == 'electricity'): ?>
                                                    <option value="<?php echo $meter['id']; ?>" 
                                                            data-type="electricity"
                                                            <?php echo $meter['id'] == $reading['meter_id'] ? 'selected' : ''; ?>>
                                                        <?php echo $meter['meter_code'] . ' - ' . $meter['meter_name']; ?>
                                                    </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </optgroup>
                                            <optgroup label="มิเตอร์น้ำ">
                                                <?php foreach ($meters as $meter): ?>
                                                    <?php if ($meter['meter_type'] == 'water'): ?>
                                                    <option value="<?php echo $meter['id']; ?>" 
                                                            data-type="water"
                                                            <?php echo $meter['id'] == $reading['meter_id'] ? 'selected' : ''; ?>>
                                                        <?php echo $meter['meter_code'] . ' - ' . $meter['meter_name']; ?>
                                                    </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>ค่าเช้า (<?php echo $unit; ?>) <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" class="form-control" 
                                               name="morning_reading" id="morningReading" 
                                               value="<?php echo $reading['morning_reading']; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>ค่าเย็น (<?php echo $unit; ?>) <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" class="form-control" 
                                               name="evening_reading" id="eveningReading" 
                                               value="<?php echo $reading['evening_reading']; ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>ปริมาณการใช้</label>
                                        <input type="text" class="form-control" id="usageAmount" 
                                               value="<?php echo number_format($reading['usage_amount'], 2); ?> <?php echo $unit; ?>" 
                                               readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>หน่วย</label>
                                        <input type="text" class="form-control" id="unit" value="<?php echo $unit; ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>หมายเหตุ</label>
                                <textarea class="form-control" name="remarks" rows="3"><?php echo htmlspecialchars($reading['remarks']); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-box bg-info">
                                        <span class="info-box-icon">
                                            <i class="fas fa-info-circle"></i>
                                        </span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">ข้อมูลเดิม</span>
                                            <span class="info-box-number">
                                                ค่าเช้า: <?php echo $reading['morning_reading']; ?><br>
                                                ค่าเย็น: <?php echo $reading['evening_reading']; ?><br>
                                                               ปริมาณ: <?php echo number_format($reading['usage_amount'], 2); ?> <?php echo $unit; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box bg-secondary">
                                        <span class="info-box-icon">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">ผู้บันทึก</span>
                                            <span class="info-box-number">
                                                <?php echo htmlspecialchars($reading['recorded_by']); ?><br>
                                                <small><?php echo date('d/m/Y H:i', strtotime($reading['created_at'])); ?></small>
                                            </span>
                                        </div>
                                    </div>
                                </div>
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
                            <a href="view.php?id=<?php echo $id; ?>" class="btn btn-info">
                                <i class="fas fa-eye"></i> ดูรายละเอียด
                            </a>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> ยกเลิก
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Meter Info Card -->
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-info-circle"></i>
                            ข้อมูลมิเตอร์
                        </h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th>รหัสมิเตอร์:</th>
                                <td><?php echo $reading['meter_code']; ?></td>
                            </tr>
                            <tr>
                                <th>ชื่อมิเตอร์:</th>
                                <td><?php echo htmlspecialchars($reading['meter_name']); ?></td>
                            </tr>
                            <tr>
                                <th>ประเภท:</th>
                                <td>
                                    <span class="badge badge-<?php echo $reading['meter_type'] == 'electricity' ? 'warning' : 'info'; ?>">
                                        <?php echo $reading['meter_type'] == 'electricity' ? 'ไฟฟ้า' : 'น้ำ'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>ตำแหน่ง:</th>
                                <td><?php echo htmlspecialchars($reading['location'] ?: '-'); ?></td>
                            </tr>
                        </table>
                        
                        <hr>
                        
                        <h5>สถิติของมิเตอร์นี้</h5>
                        <?php
                        // Get statistics for this meter
                        $stmt = $db->prepare("
                            SELECT 
                                COUNT(*) as total_readings,
                                AVG(usage_amount) as avg_usage,
                                MAX(usage_amount) as max_usage,
                                MIN(usage_amount) as min_usage,
                                MAX(record_date) as last_date
                            FROM meter_daily_readings 
                            WHERE meter_id = ? AND id != ?
                        ");
                        $stmt->execute([$reading['meter_id'], $id]);
                        $stats = $stmt->fetch();
                        ?>
                        <table class="table table-sm">
                            <tr>
                                <th>จำนวนครั้ง:</th>
                                <td><?php echo $stats['total_readings']; ?> ครั้ง</td>
                            </tr>
                            <tr>
                                <th>ค่าเฉลี่ย:</th>
                                <td><?php echo number_format($stats['avg_usage'], 2); ?> <?php echo $unit; ?></td>
                            </tr>
                            <tr>
                                <th>สูงสุด:</th>
                                <td><?php echo number_format($stats['max_usage'], 2); ?> <?php echo $unit; ?></td>
                            </tr>
                            <tr>
                                <th>ต่ำสุด:</th>
                                <td><?php echo number_format($stats['min_usage'], 2); ?> <?php echo $unit; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Recent Readings Card -->
                <div class="card card-secondary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i>
                            บันทึกล่าสุดของมิเตอร์นี้
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        <?php
                        $stmt = $db->prepare("
                            SELECT 
                                record_date,
                                morning_reading,
                                evening_reading,
                                usage_amount
                            FROM meter_daily_readings 
                            WHERE meter_id = ? AND id != ?
                            ORDER BY record_date DESC
                            LIMIT 5
                        ");
                        $stmt->execute([$reading['meter_id'], $id]);
                        $recent = $stmt->fetchAll();
                        ?>
                        
                        <?php if (empty($recent)): ?>
                            <p class="text-center text-muted p-3">ไม่มีประวัติ</p>
                        <?php else: ?>
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>วันที่</th>
                                        <th>เช้า</th>
                                        <th>เย็น</th>
                                        <th>ปริมาณ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent as $item): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($item['record_date'])); ?></td>
                                        <td class="text-right"><?php echo number_format($item['morning_reading'], 2); ?></td>
                                        <td class="text-right"><?php echo number_format($item['evening_reading'], 2); ?></td>
                                        <td class="text-right"><?php echo number_format($item['usage_amount'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    // Initialize datepicker
    $('#recordDatePicker').datetimepicker({
        format: 'DD/MM/YYYY',
        locale: 'th',
        useCurrent: false
    });
    
    // Initialize select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });
    
    // Calculate usage on input
    $('#morningReading, #eveningReading').on('input', function() {
        calculateUsage();
    });
    
    // Check for duplicate on date/meter change
    $('#recordDate, #meterId').on('change', function() {
        checkDuplicate();
    });
    
    // Form submit
    $('#editRecordForm').on('submit', function(e) {
        e.preventDefault();
        submitForm();
    });
});

function calculateUsage() {
    const morning = parseFloat($('#morningReading').val()) || 0;
    const evening = parseFloat($('#eveningReading').val()) || 0;
    const meterType = $('#meterId option:selected').data('type');
    const unit = meterType === 'electricity' ? 'kWh' : 'm³';
    
    if (morning > 0 && evening > 0) {
        if (evening > morning) {
            const usage = (evening - morning).toFixed(2);
            $('#usageAmount').val(usage + ' ' + unit);
            $('#usageAmount').removeClass('is-invalid');
            $('#warningAlert').hide();
            
            // Check for abnormal usage
            if (usage > 1000) {
                showWarning('ปริมาณการใช้สูงผิดปกติ (' + usage + ' ' + unit + ')');
            }
        } else {
            $('#usageAmount').val('ค่าเย็นต้องมากกว่าค่าเช้า');
            $('#usageAmount').addClass('is-invalid');
            showWarning('ค่าเย็นต้องมากกว่าค่าเช้า');
        }
    } else {
        $('#usageAmount').val('');
    }
}

function showWarning(message) {
    $('#warningMessage').html(message);
    $('#warningAlert').show();
}

function checkDuplicate() {
    const meterId = $('#meterId').val();
    const recordDate = $('#recordDate').val();
    const currentId = <?php echo $id; ?>;
    
    if (meterId && recordDate) {
        $.ajax({
            url: 'ajax/check_reading.php',
            method: 'POST',
            data: {
                meter_id: meterId,
                record_date: recordDate,
                exclude_id: currentId
            },
            success: function(response) {
                if (response.exists) {
                    showWarning('มีบันทึกข้อมูลสำหรับมิเตอร์และวันนี้อยู่แล้ว');
                    $('#meterId').addClass('is-invalid');
                    $('#recordDate').addClass('is-invalid');
                } else {
                    $('#meterId').removeClass('is-invalid');
                    $('#recordDate').removeClass('is-invalid');
                    $('#warningAlert').hide();
                }
            }
        });
    }
}

function submitForm() {
    // Validate form
    if (!$('#editRecordForm')[0].checkValidity()) {
        $('#editRecordForm')[0].reportValidity();
        return;
    }
    
    // Validate readings
    const morning = parseFloat($('#morningReading').val()) || 0;
    const evening = parseFloat($('#eveningReading').val()) || 0;
    
    if (evening <= morning) {
        showNotification('ค่าเย็นต้องมากกว่าค่าเช้า', 'danger');
        return;
    }
    
    // Check for duplicate
    if ($('#meterId').hasClass('is-invalid') || $('#recordDate').hasClass('is-invalid')) {
        showNotification('กรุณาตรวจสอบข้อมูลซ้ำซ้อน', 'warning');
        return;
    }
    
    const formData = $('#editRecordForm').serialize();
    
    $.ajax({
        url: 'process_edit.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        beforeSend: function() {
            showNotification('กำลังบันทึกข้อมูล...', 'info');
        },
        success: function(response) {
            if (response.success) {
                showNotification('บันทึกข้อมูลเรียบร้อย', 'success');
                setTimeout(function() {
                    window.location.href = 'view.php?id=<?php echo $id; ?>';
                }, 1500);
            } else {
                showNotification(response.message, 'danger');
            }
        },
        error: function(xhr) {
            let message = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            showNotification(message, 'danger');
        }
    });
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>