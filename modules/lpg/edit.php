<?php
/**
 * LPG Module - Edit Record
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'LPG - แก้ไขบันทึกข้อมูล';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'LPG', 'link' => 'index.php'],
    ['title' => 'แก้ไขบันทึกข้อมูล', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Load required files
require_once __DIR__ . '/../../includes/functions.php';

// Get database connection
$db = getDB();

// Get date from query string
$editDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Check if records exist for this date
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lpg_daily_records WHERE record_date = ?
");
$stmt->execute([$editDate]);
$recordCount = $stmt->fetch()['count'];

if ($recordCount == 0) {
    $_SESSION['error'] = 'ไม่พบข้อมูลสำหรับวันที่นี้';
    header('Location: index.php');
    exit();
}

// Get document info
$stmt = $db->prepare("
    SELECT * FROM documents 
    WHERE module_type = 'lpg' 
    AND start_date <= ? 
    ORDER BY start_date DESC LIMIT 1
");
$stmt->execute([$editDate]);
$document = $stmt->fetch();

// Get inspection items
$stmt = $db->query("
    SELECT * FROM lpg_inspection_items 
    ORDER BY item_no
");
$inspectionItems = $stmt->fetchAll();

// Get existing records for this date
$stmt = $db->prepare("
    SELECT * FROM lpg_daily_records WHERE record_date = ?
");
$stmt->execute([$editDate]);
$existingRecords = $stmt->fetchAll();

// Create lookup array
$recordsLookup = [];
foreach ($existingRecords as $record) {
    $recordsLookup[$record['item_id']] = $record;
}

// Separate items by type
$numberItems = array_filter($inspectionItems, function($item) {
    return $item['item_type'] == 'number';
});

$enumItems = array_filter($inspectionItems, function($item) {
    return $item['item_type'] == 'enum';
});

$displayDate = date('d/m/Y', strtotime($editDate));
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
                            <input type="hidden" name="record_date" value="<?php echo $editDate; ?>">
                            <input type="hidden" name="doc_id" value="<?php echo $document['id'] ?? 0; ?>">
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                กำลังแก้ไขข้อมูลวันที่: <strong><?php echo $displayDate; ?></strong>
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
                                        <p class="text-muted text-center">ไม่มีหัวข้อแบบตัวเลข</p>
                                    <?php else: ?>
                                        <?php foreach ($numberItems as $item): ?>
                                        <?php 
                                        $record = $recordsLookup[$item['id']] ?? null;
                                        $value = $record ? $record['number_value'] : '';
                                        ?>
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
                                                       value="<?php echo $value; ?>"
                                                       data-standard="<?php echo $item['standard_value']; ?>"
                                                       data-item="<?php echo htmlspecialchars($item['item_name']); ?>">
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
                                        <p class="text-muted text-center">ไม่มีหัวข้อแบบ OK/NG</p>
                                    <?php else: ?>
                                        <?php foreach ($enumItems as $item): ?>
                                        <?php 
                                        $record = $recordsLookup[$item['id']] ?? null;
                                        $value = $record ? $record['enum_value'] : '';
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
                                                    <label class="btn btn-outline-<?php echo $option == 'OK' ? 'success' : 'danger'; ?> <?php echo $value == $option ? 'active' : ''; ?>">
                                                        <input type="radio" name="enums[<?php echo $item['id']; ?>]" 
                                                               value="<?php echo $option; ?>" 
                                                               <?php echo $value == $option ? 'checked' : ''; ?>
                                                               autocomplete="off"> 
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
                                <textarea class="form-control" name="remarks" rows="3"><?php echo $existingRecords[0]['remarks'] ?? ''; ?></textarea>
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
                <!-- Info Card -->
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-info-circle"></i>
                            ข้อมูลการแก้ไข
                        </h3>
                    </div>
                    <div class="card-body">
                        <p><strong>วันที่:</strong> <?php echo $displayDate; ?></p>
                        <p><strong>เอกสาร:</strong> <?php echo $document['doc_no'] ?? '-'; ?></p>
                        <p><strong>จำนวนรายการ:</strong> <?php echo $recordCount; ?> รายการ</p>
                        
                        <hr>
                        
                        <h5>สถิติ</h5>
                        <?php 
                        $ngCount = 0;
                        foreach ($existingRecords as $record) {
                            if ($record['enum_value'] == 'NG') {
                                $ngCount++;
                            }
                        }
                        ?>
                        <p>จำนวน NG: <span class="badge badge-danger"><?php echo $ngCount; ?></span></p>
                        
                        <?php if ($ngCount > 0): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            พบรายการ NG จำนวน <?php echo $ngCount; ?> รายการ
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
    // Number input validation
    $('.number-input').on('input', function() {
        validateNumber($(this));
    });
    
    // Trigger validation on load
    $('.number-input').each(function() {
        if ($(this).val()) {
            validateNumber($(this));
        }
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
$('#editRecordForm').on('submit', function(e) {
    let hasError = false;
    
    $('.number-input').each(function() {
        if ($(this).hasClass('is-invalid')) {
            hasError = true;
        }
    });
    
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