<?php
/**
 * Summary Electricity Module - Add New Record
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Summary Electricity - เพิ่มบันทึกข้อมูล';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Summary Electricity', 'link' => 'index.php'],
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

// Get document info for current month
$stmt = $db->prepare("
    SELECT * FROM documents 
    WHERE module_type = 'summary' 
    AND MONTH(start_date) = ? 
    AND YEAR(start_date) = ?
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([date('m'), date('Y')]);
$document = $stmt->fetch();

// Check if today already has record
$stmt = $db->prepare("
    SELECT id FROM electricity_summary 
    WHERE record_date = ?
");
$stmt->execute([$today]);
$existingRecord = $stmt->fetch();

if ($existingRecord) {
    $_SESSION['warning'] = 'วันนี้มีบันทึกข้อมูลแล้ว กรุณาใช้หน้าแก้ไขข้อมูล';
    header('Location: index.php');
    exit();
}
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
                                        <label>เลขที่เอกสาร</label>
                                        <input type="text" class="form-control" value="<?php echo $document['doc_no'] ?? 'SUM-' . date('Ymd'); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card card-secondary">
                                <div class="card-header">
                                    <h5 class="card-title">ข้อมูลไฟฟ้า</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>หน่วยไฟฟ้า (EE) - kWh <span class="text-danger">*</span></label>
                                                <input type="number" step="0.01" class="form-control" 
                                                       name="ee_unit" id="eeUnit" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>ค่าไฟต่อหน่วย (บาท) <span class="text-danger">*</span></label>
                                                <input type="number" step="0.0001" class="form-control" 
                                                       name="cost_per_unit" id="costPerUnit" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>ค่าไฟฟ้า (บาท)</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" id="totalCost" readonly>
                                                    <div class="input-group-append">
                                                        <span class="input-group-text">บาท</span>
                                                    </div>
                                                </div>
                                                <small class="text-muted">คำนวณอัตโนมัติ</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>PE</label>
                                                <input type="number" step="0.0001" class="form-control" 
                                                       name="pe" id="pe" placeholder="0.0000">
                                                <small class="text-muted">Power Factor (ถ้ามี)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>หมายเหตุ</label>
                                <textarea class="form-control" name="remarks" rows="3" placeholder="หมายเหตุเพิ่มเติม (ถ้ามี)"></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>หมายเหตุ:</strong> ค่าไฟฟ้าจะคำนวณอัตโนมัติจาก หน่วยไฟฟ้า × ค่าไฟต่อหน่วย
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
                        <p><strong>เดือน:</strong> <?php echo getThaiMonth(date('m')) . ' ' . (date('Y') + 543); ?></p>
                        <p><strong>เอกสาร:</strong> <?php echo $document['doc_no'] ?? 'SUM-' . date('Ymd'); ?></p>
                        
                        <hr>
                        
                        <h5>คำแนะนำ</h5>
                        <ul class="text-muted">
                            <li>กรอกหน่วยไฟฟ้าที่ใช้ในแต่ละวัน</li>
                            <li>กรอกค่าไฟต่อหน่วย (จากบิลค่าไฟ)</li>
                            <li>ระบบคำนวณค่าไฟฟ้าอัตโนมัติ</li>
                            <li>PE (Power Factor) เป็นค่าตัวเลือก</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Monthly Summary Card -->
                <?php
                // Get current month summary
                $stmt = $db->prepare("
                    SELECT 
                        SUM(ee_unit) as month_total,
                        AVG(cost_per_unit) as avg_cost,
                        COUNT(*) as days_count
                    FROM electricity_summary
                    WHERE MONTH(record_date) = ? AND YEAR(record_date) = ?
                ");
                $stmt->execute([date('m'), date('Y')]);
                $monthSummary = $stmt->fetch();
                ?>
                
                <?php if ($monthSummary && $monthSummary['days_count'] > 0): ?>
                <div class="card card-secondary mt-3">
                    <div class="card-header">
                        <h5 class="card-title">สรุปเดือนนี้</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th>หน่วยไฟฟ้ารวม:</th>
                                <td class="text-right"><?php echo number_format($monthSummary['month_total'], 2); ?> kWh</td>
                            </tr>
                            <tr>
                                <th>ค่าไฟเฉลี่ย/หน่วย:</th>
                                <td class="text-right"><?php echo number_format($monthSummary['avg_cost'], 4); ?> บาท</td>
                            </tr>
                            <tr>
                                <th>จำนวนวัน:</th>
                                <td class="text-right"><?php echo $monthSummary['days_count']; ?> วัน</td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
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
        useCurrent: true
    });
    
    // Calculate total cost
    $('#eeUnit, #costPerUnit').on('input', function() {
        calculateTotalCost();
    });
    
    // Form submit
    $('#addRecordForm').on('submit', function(e) {
        e.preventDefault();
        submitForm();
    });
});

function calculateTotalCost() {
    const ee = parseFloat($('#eeUnit').val()) || 0;
    const costPerUnit = parseFloat($('#costPerUnit').val()) || 0;
    const total = (ee * costPerUnit).toFixed(2);
    
    $('#totalCost').val(total);
}

function submitForm() {
    // Validate form
    if (!$('#addRecordForm')[0].checkValidity()) {
        $('#addRecordForm')[0].reportValidity();
        return;
    }
    
    const formData = $('#addRecordForm').serialize();
    
    $.ajax({
        url: 'process_add.php',
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
                    window.location.href = 'index.php';
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