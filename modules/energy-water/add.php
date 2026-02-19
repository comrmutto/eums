<?php
/**
 * Energy & Water Module - Add New Reading
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Energy & Water - เพิ่มบันทึกค่ามิเตอร์';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Energy & Water', 'link' => 'index.php'],
    ['title' => 'เพิ่มบันทึกค่ามิเตอร์', 'link' => null]
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
    WHERE module_type = 'energy_water' 
    AND start_date <= ? 
    ORDER BY start_date DESC LIMIT 1
");
$stmt->execute([$today]);
$document = $stmt->fetch();

// Get meters
$stmt = $db->query("
    SELECT * FROM mc_mdb_water 
    WHERE status = 1 
    ORDER BY meter_type, meter_code
");
$meters = $stmt->fetchAll();

// Group meters by type
$electricityMeters = array_filter($meters, function($m) { return $m['meter_type'] == 'electricity'; });
$waterMeters = array_filter($meters, function($m) { return $m['meter_type'] == 'water'; });

// Check if today already has readings for any meter
$stmt = $db->prepare("
    SELECT meter_id FROM meter_daily_readings 
    WHERE record_date = ?
");
$stmt->execute([$today]);
$existingReadings = $stmt->fetchAll(PDO::FETCH_COLUMN);
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
                            ฟอร์มบันทึกค่ามิเตอร์
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
                                                   value="<?php echo $displayDate; ?>" 
                                                   data-target="#recordDatePicker" required readonly>
                                            <div class="input-group-append">
                                                <div class="input-group-text">
                                                    <i class="fa fa-calendar"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>เลขที่เอกสาร</label>
                                        <input type="text" class="form-control" value="<?php echo $document['doc_no'] ?? 'EW-' . date('Ymd'); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Electricity Meters Section -->
                            <div class="card card-warning mb-3">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        <i class="fas fa-bolt"></i>
                                        มิเตอร์ไฟฟ้า
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($electricityMeters)): ?>
                                        <p class="text-muted text-center">ไม่มีมิเตอร์ไฟฟ้า กรุณาเพิ่มในจัดการมิเตอร์</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>รหัสมิเตอร์</th>
                                                        <th>ชื่อมิเตอร์</th>
                                                        <th>ตำแหน่ง</th>
                                                        <th>ค่าเช้า (kWh)</th>
                                                        <th>ค่าเย็น (kWh)</th>
                                                        <th>สถานะ</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($electricityMeters as $meter): ?>
                                                    <?php 
                                                    $hasReading = in_array($meter['id'], $existingReadings);
                                                    $disabled = $hasReading ? 'disabled' : '';
                                                    $warningClass = $hasReading ? 'table-warning' : '';
                                                    ?>
                                                    <tr class="<?php echo $warningClass; ?>">
                                                        <td><?php echo htmlspecialchars($meter['meter_code']); ?></td>
                                                        <td><?php echo htmlspecialchars($meter['meter_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($meter['location']); ?></td>
                                                        <td>
                                                            <input type="number" step="0.01" 
                                                                   class="form-control form-control-sm morning-reading electricity" 
                                                                   name="morning[<?php echo $meter['id']; ?>]" 
                                                                   id="morning_<?php echo $meter['id']; ?>"
                                                                   data-meter-id="<?php echo $meter['id']; ?>"
                                                                   data-meter-type="electricity"
                                                                   data-meter-code="<?php echo $meter['meter_code']; ?>"
                                                                   <?php echo $disabled; ?>>
                                                        </td>
                                                        <td>
                                                            <input type="number" step="0.01" 
                                                                   class="form-control form-control-sm evening-reading electricity" 
                                                                   name="evening[<?php echo $meter['id']; ?>]" 
                                                                   id="evening_<?php echo $meter['id']; ?>"
                                                                   data-meter-id="<?php echo $meter['id']; ?>"
                                                                   data-meter-type="electricity"
                                                                   data-meter-code="<?php echo $meter['meter_code']; ?>"
                                                                   <?php echo $disabled; ?>>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php if ($hasReading): ?>
                                                            <span class="badge badge-success">บันทึกแล้ว</span>
                                                            <?php else: ?>
                                                            <span class="badge badge-warning">รอบันทึก</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Water Meters Section -->
                            <div class="card card-info mb-3">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        <i class="fas fa-water"></i>
                                        มิเตอร์น้ำ
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($waterMeters)): ?>
                                        <p class="text-muted text-center">ไม่มีมิเตอร์น้ำ กรุณาเพิ่มในจัดการมิเตอร์</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>รหัสมิเตอร์</th>
                                                        <th>ชื่อมิเตอร์</th>
                                                        <th>ตำแหน่ง</th>
                                                        <th>ค่าเช้า (m³)</th>
                                                        <th>ค่าเย็น (m³)</th>
                                                        <th>สถานะ</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($waterMeters as $meter): ?>
                                                    <?php 
                                                    $hasReading = in_array($meter['id'], $existingReadings);
                                                    $disabled = $hasReading ? 'disabled' : '';
                                                    $warningClass = $hasReading ? 'table-warning' : '';
                                                    ?>
                                                    <tr class="<?php echo $warningClass; ?>">
                                                        <td><?php echo htmlspecialchars($meter['meter_code']); ?></td>
                                                        <td><?php echo htmlspecialchars($meter['meter_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($meter['location']); ?></td>
                                                        <td>
                                                            <input type="number" step="0.01" 
                                                                   class="form-control form-control-sm morning-reading water" 
                                                                   name="morning[<?php echo $meter['id']; ?>]" 
                                                                   id="morning_<?php echo $meter['id']; ?>"
                                                                   data-meter-id="<?php echo $meter['id']; ?>"
                                                                   data-meter-type="water"
                                                                   data-meter-code="<?php echo $meter['meter_code']; ?>"
                                                                   <?php echo $disabled; ?>>
                                                        </td>
                                                        <td>
                                                            <input type="number" step="0.01" 
                                                                   class="form-control form-control-sm evening-reading water" 
                                                                   name="evening[<?php echo $meter['id']; ?>]" 
                                                                   id="evening_<?php echo $meter['id']; ?>"
                                                                   data-meter-id="<?php echo $meter['id']; ?>"
                                                                   data-meter-type="water"
                                                                   data-meter-code="<?php echo $meter['meter_code']; ?>"
                                                                   <?php echo $disabled; ?>>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php if ($hasReading): ?>
                                                            <span class="badge badge-success">บันทึกแล้ว</span>
                                                            <?php else: ?>
                                                            <span class="badge badge-warning">รอบันทึก</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
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
                            
                            <div id="usageTable" style="display: none;">
                                <h5>ปริมาณการใช้ที่คำนวณได้</h5>
                                <table class="table table-bordered table-sm">
                                    <thead>
                                        <tr>
                                            <th>มิเตอร์</th>
                                            <th>ประเภท</th>
                                            <th>ค่าเช้า</th>
                                            <th>ค่าเย็น</th>
                                            <th>ปริมาณการใช้</th>
                                            <th>หน่วย</th>
                                        </tr>
                                    </thead>
                                    <tbody id="usageTableBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> บันทึกข้อมูล
                            </button>
                            <button type="button" class="btn btn-info" onclick="calculateAllUsage()">
                                <i class="fas fa-calculator"></i> คำนวณปริมาณการใช้
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
                        <p><strong>เอกสาร:</strong> <?php echo $document['doc_no'] ?? 'EW-' . date('Ymd'); ?></p>
                        <p><strong>มิเตอร์ไฟฟ้า:</strong> <?php echo count($electricityMeters); ?> เครื่อง</p>
                        <p><strong>มิเตอร์น้ำ:</strong> <?php echo count($waterMeters); ?> เครื่อง</p>
                        <p><strong>บันทึกแล้ว:</strong> <?php echo count($existingReadings); ?> รายการ</p>
                        
                        <hr>
                        
                        <h5>คำแนะนำ</h5>
                        <ul class="text-muted">
                            <li>กรอกค่าเช้าและค่าเย็นเป็นตัวเลข</li>
                            <li>ค่าเย็นต้องมากกว่าค่าเช้าเสมอ</li>
                            <li>ระบบจะคำนวณปริมาณการใช้อัตโนมัติ</li>
                            <li>สามารถบันทึกได้วันละ 1 ครั้งต่อมิเตอร์</li>
                        </ul>
                        
                        <?php if (!empty($existingReadings)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle"></i>
                            มีมิเตอร์ที่บันทึกแล้ว <?php echo count($existingReadings); ?> รายการ
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    // Auto-calculate usage when both readings are entered
    $('.morning-reading, .evening-reading').on('input', function() {
        const meterId = $(this).data('meter-id');
        calculateUsage(meterId);
    });
});

function calculateUsage(meterId) {
    const morning = parseFloat($(`#morning_${meterId}`).val()) || 0;
    const evening = parseFloat($(`#evening_${meterId}`).val()) || 0;
    const meterCode = $(`#morning_${meterId}`).data('meter-code');
    const meterType = $(`#morning_${meterId}`).data('meter-type');
    
    if (morning > 0 && evening > 0) {
        if (evening > morning) {
            const usage = (evening - morning).toFixed(2);
            const unit = meterType === 'electricity' ? 'kWh' : 'm³';
            
            // Add to usage table
            updateUsageTable(meterId, meterCode, meterType, morning, evening, usage, unit);
            
            // Check for abnormal usage
            if (usage > 1000) {
                showWarning('ปริมาณการใช้ ' + meterCode + ' สูงผิดปกติ (' + usage + ' ' + unit + ')');
            }
        } else {
            $(`#morning_${meterId}`).addClass('is-invalid');
            $(`#evening_${meterId}`).addClass('is-invalid');
            showWarning('ค่าเย็นต้องมากกว่าค่าเช้าสำหรับมิเตอร์ ' + meterCode);
        }
    }
}

function updateUsageTable(meterId, meterCode, meterType, morning, evening, usage, unit) {
    let tableBody = $('#usageTableBody');
    let existingRow = $(`#usage-row-${meterId}`);
    
    let rowHtml = `<tr id="usage-row-${meterId}">
        <td>${meterCode}</td>
        <td>${meterType === 'electricity' ? 'ไฟฟ้า' : 'น้ำ'}</td>
        <td class="text-right">${morning.toFixed(2)}</td>
        <td class="text-right">${evening.toFixed(2)}</td>
        <td class="text-right"><strong>${usage}</strong></td>
        <td>${unit}</td>
    </tr>`;
    
    if (existingRow.length) {
        existingRow.replaceWith(rowHtml);
    } else {
        tableBody.append(rowHtml);
    }
    
    $('#usageTable').show();
}

function calculateAllUsage() {
    $('.morning-reading').each(function() {
        const meterId = $(this).data('meter-id');
        calculateUsage(meterId);
    });
}

function showWarning(message) {
    $('#warningMessage').html(message);
    $('#warningAlert').show();
    setTimeout(function() {
        $('#warningAlert').fadeOut();
    }, 5000);
}

// Form validation before submit
$('#addRecordForm').on('submit', function(e) {
    let hasData = false;
    let hasError = false;
    let warnings = [];
    
    $('.morning-reading').each(function() {
        const meterId = $(this).data('meter-id');
        const morning = parseFloat($(this).val()) || 0;
        const evening = parseFloat($(`#evening_${meterId}`).val()) || 0;
        const meterCode = $(this).data('meter-code');
        
        if (morning > 0 || evening > 0) {
            hasData = true;
            
            if (evening <= morning) {
                hasError = true;
                warnings.push('ค่าเย็นต้องมากกว่าค่าเช้าสำหรับมิเตอร์ ' + meterCode);
            }
            
            if (evening - morning > 1000) {
                warnings.push('ปริมาณการใช้มิเตอร์ ' + meterCode + ' สูงผิดปกติ');
            }
        }
    });
    
    if (!hasData) {
        e.preventDefault();
        showNotification('กรุณากรอกข้อมูลอย่างน้อย 1 มิเตอร์', 'warning');
        return false;
    }
    
    if (hasError) {
        e.preventDefault();
        showNotification('กรุณาตรวจสอบข้อมูลให้ถูกต้อง', 'danger');
        return false;
    }
    
    if (warnings.length > 0 && !confirm('มีคำเตือน:\n' + warnings.join('\n') + '\n\nคุณต้องการบันทึกต่อหรือไม่?')) {
        e.preventDefault();
        return false;
    }
    
    return true;
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>