<?php
/**
 * LPG Module - Add New Record
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'LPG - เพิ่มบันทึกข้อมูล';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'LPG', 'link' => 'index.php'],
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

// Get document info for today
$stmt = $db->prepare("
    SELECT * FROM documents 
    WHERE module_type = 'lpg' 
    AND start_date <= ? 
    ORDER BY start_date DESC LIMIT 1
");
$stmt->execute([$today]);
$document = $stmt->fetch();

// Get inspection items
$stmt = $db->query("
    SELECT * FROM lpg_inspection_items 
    ORDER BY item_no
");
$inspectionItems = $stmt->fetchAll();

// Separate items by type
$numberItems = array_filter($inspectionItems, function($item) {
    return $item['item_type'] == 'number';
});

$enumItems = array_filter($inspectionItems, function($item) {
    return $item['item_type'] == 'enum';
});

// Check if today already has records
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lpg_daily_records 
    WHERE record_date = ?
");
$stmt->execute([$today]);
$hasRecords = $stmt->fetch()['count'] > 0;

if ($hasRecords) {
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
                                        <input type="text" class="form-control" value="<?php echo $document['doc_no'] ?? 'LPG-' . date('Ymd'); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Number Items Section -->
                            <div class="card card-secondary mb-3">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        <i class="fas fa-calculator"></i>
                                        ข้อมูลตัวเลข
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($numberItems)): ?>
                                        <p class="text-muted text-center">ไม่มีหัวข้อแบบตัวเลข กรุณาเพิ่มในตั้งค่า</p>
                                    <?php else: ?>
                                        <?php foreach ($numberItems as $item): ?>
                                        <div class="form-group row align-items-center">
                                            <label class="col-sm-4 col-form-label">
                                                <?php echo $item['item_no']; ?>. <?php echo htmlspecialchars($item['item_name']); ?>
                                                <br><small class="text-muted">มาตรฐาน: <?php echo $item['standard_value']; ?> <?php echo $item['unit']; ?></small>
                                            </label>
                                            <div class="col-sm-5">
                                                <input type="number" step="0.01" 
                                                       class="form-control number-input" 
                                                       name="numbers[<?php echo $item['id']; ?>]" 
                                                       id="num_<?php echo $item['id']; ?>"
                                                       data-standard="<?php echo $item['standard_value']; ?>"
                                                       data-item="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                       placeholder="กรอกค่าที่วัดได้">
                                                <div class="invalid-feedback" id="feedback_<?php echo $item['id']; ?>"></div>
                                            </div>
                                            <div class="col-sm-3">
                                                <span class="form-text text-muted"><?php echo $item['unit']; ?></span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Enum Items Section -->
                            <div class="card card-secondary mb-3">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        <i class="fas fa-check-circle"></i>
                                        สถานะ OK/NG
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($enumItems)): ?>
                                        <p class="text-muted text-center">ไม่มีหัวข้อแบบ OK/NG กรุณาเพิ่มในตั้งค่า</p>
                                    <?php else: ?>
                                        <?php foreach ($enumItems as $item): ?>
                                        <?php 
                                        $options = json_decode($item['enum_options'], true) ?? ['OK', 'NG'];
                                        ?>
                                        <div class="form-group row">
                                            <label class="col-sm-4 col-form-label">
                                                <?php echo $item['item_no']; ?>. <?php echo htmlspecialchars($item['item_name']); ?>
                                                <br><small class="text-muted">มาตรฐาน: <?php echo $item['standard_value']; ?></small>
                                            </label>
                                            <div class="col-sm-8">
                                                <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                                    <?php foreach ($options as $option): ?>
                                                    <label class="btn btn-outline-<?php echo $option == 'OK' ? 'success' : 'danger'; ?>">
                                                        <input type="radio" name="enums[<?php echo $item['id']; ?>]" 
                                                               value="<?php echo $option; ?>" autocomplete="off"> 
                                                        <?php echo $option; ?>
                                                    </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>หมายเหตุ</label>
                                <textarea class="form-control" name="remarks" rows="3" placeholder="หมายเหตุเพิ่มเติม (ถ้ามี)"></textarea>
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
                        <p><strong>เอกสาร:</strong> <?php echo $document['doc_no'] ?? 'LPG-' . date('Ymd'); ?></p>
                        <p><strong>หัวข้อทั้งหมด:</strong> <?php echo count($inspectionItems); ?> รายการ</p>
                        <p><strong>แบบตัวเลข:</strong> <?php echo count($numberItems); ?> รายการ</p>
                        <p><strong>แบบ OK/NG:</strong> <?php echo count($enumItems); ?> รายการ</p>
                        
                        <hr>
                        
                        <h5>คำแนะนำ</h5>
                        <ul class="text-muted">
                            <li>กรอกค่าตัวเลขตามที่วัดได้</li>
                            <li>เลือกสถานะ OK/NG สำหรับหัวข้อที่กำหนด</li>
                            <li>ระบบจะตรวจสอบค่าที่กรอกกับค่ามาตรฐาน</li>
                            <li>สามารถบันทึกข้อมูลได้วันละ 1 ครั้งเท่านั้น</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    // Number input validation
    $('.number-input').on('input', function() {
        validateNumber($(this));
    });
});

function validateNumber(input) {
    const value = parseFloat(input.val());
    const standard = parseFloat(input.data('standard'));
    const itemName = input.data('item');
    const id = input.attr('id').replace('num_', '');
    
    if (isNaN(value) || value === '') {
        input.removeClass('is-valid is-invalid');
        $(`#feedback_${id}`).hide();
        return;
    }
    
    const tolerance = standard * 0.1;
    const deviation = Math.abs(value - standard);
    
    if (deviation <= tolerance) {
        input.removeClass('is-invalid').addClass('is-valid');
        $(`#feedback_${id}`).hide();
    } else {
        input.removeClass('is-valid').addClass('is-invalid');
        const percent = ((deviation / standard) * 100).toFixed(2);
        $(`#feedback_${id}`).text(`ค่าเบี่ยงเบน ${percent}% (เกิน 10%)`).show();
    }
}

// Form validation before submit
$('#addRecordForm').on('submit', function(e) {
    let hasError = false;
    let hasNumberValue = false;
    
    $('.number-input').each(function() {
        if ($(this).hasClass('is-invalid')) {
            hasError = true;
        }
        if ($(this).val()) {
            hasNumberValue = true;
        }
    });
    
    let hasEnumValue = false;
    $('input[type=radio]').each(function() {
        if ($(this).is(':checked')) {
            hasEnumValue = true;
        }
    });
    
    if (!hasNumberValue && !hasEnumValue) {
        e.preventDefault();
        showNotification('กรุณากรอกข้อมูลอย่างน้อย 1 รายการ', 'warning');
        return false;
    }
    
    if (hasError) {
        if (!confirm('มีบางรายการที่ค่าอยู่นอกเกณฑ์มาตรฐาน คุณต้องการบันทึกต่อหรือไม่?')) {
            e.preventDefault();
            return false;
        }
    }
    
    return true;
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>