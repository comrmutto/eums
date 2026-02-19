<?php
/**
 * Summary Electricity Module - Edit Record
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Summary Electricity - แก้ไขบันทึกข้อมูล';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Summary Electricity', 'link' => 'index.php'],
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
    SELECT * FROM electricity_summary WHERE id = ?
");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    $_SESSION['error'] = 'ไม่พบข้อมูลที่ต้องการแก้ไข';
    header('Location: index.php');
    exit();
}

// Get document info
$stmt = $db->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$record['doc_id']]);
$document = $stmt->fetch();

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
                            <input type="hidden" name="doc_id" value="<?php echo $record['doc_id']; ?>">
                            
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
                                        <input type="text" class="form-control" value="<?php echo $document['doc_no'] ?? '-'; ?>" readonly>
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
                                                       name="ee_unit" id="eeUnit" 
                                                       value="<?php echo $record['ee_unit']; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>ค่าไฟต่อหน่วย (บาท) <span class="text-danger">*</span></label>
                                                <input type="number" step="0.0001" class="form-control" 
                                                       name="cost_per_unit" id="costPerUnit" 
                                                       value="<?php echo $record['cost_per_unit']; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>ค่าไฟฟ้า (บาท)</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" id="totalCost" 
                                                           value="<?php echo number_format($record['total_cost'], 2); ?>" readonly>
                                                    <div class="input-group-append">
                                                        <span class="input-group-text">บาท</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>PE</label>
                                                <input type="number" step="0.0001" class="form-control" 
                                                       name="pe" id="pe" value="<?php echo $record['pe']; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>หมายเหตุ</label>
                                <textarea class="form-control" name="remarks" rows="3"><?php echo htmlspecialchars($record['remarks']); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-box bg-info">
                                        <span class="info-box-icon">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">ผู้บันทึก</span>
                                            <span class="info-box-number">
                                                <?php echo htmlspecialchars($record['recorded_by']); ?>
                                            </span>
                                            <span class="progress-description">
                                                <?php echo date('d/m/Y H:i', strtotime($record['created_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($record['updated_at']): ?>
                                <div class="col-md-6">
                                    <div class="info-box bg-secondary">
                                        <span class="info-box-icon">
                                            <i class="fas fa-edit"></i>
                                        </span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">แก้ไขล่าสุด</span>
                                            <span class="info-box-number">
                                                <?php echo date('d/m/Y H:i', strtotime($record['updated_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
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
                <!-- Monthly Comparison Card -->
                <?php
                // Get same month last year data for comparison
                $lastYear = date('Y', strtotime($record['record_date'])) - 1;
                $sameMonth = date('m', strtotime($record['record_date']));
                
                $stmt = $db->prepare("
                    SELECT 
                        SUM(ee_unit) as total_ee,
                        AVG(cost_per_unit) as avg_cost
                    FROM electricity_summary
                    WHERE MONTH(record_date) = ? AND YEAR(record_date) = ?
                ");
                $stmt->execute([$sameMonth, $lastYear]);
                $lastYearData = $stmt->fetch();
                ?>
                
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            เปรียบเทียบกับปีที่แล้ว
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if ($lastYearData && $lastYearData['total_ee'] > 0): ?>
                            <?php 
                            $change = $record['ee_unit'] - $lastYearData['total_ee'];
                            $changePercent = ($change / $lastYearData['total_ee']) * 100;
                            ?>
                            <table class="table table-sm">
                                <tr>
                                    <th>ปีนี้:</th>
                                    <td class="text-right"><?php echo number_format($record['ee_unit'], 2); ?> kWh</td>
                                </tr>
                                <tr>
                                    <th>ปีที่แล้ว:</th>
                                    <td class="text-right"><?php echo number_format($lastYearData['total_ee'], 2); ?> kWh</td>
                                </tr>
                                <tr>
                                    <th>เปลี่ยนแปลง:</th>
                                    <td class="text-right">
                                        <span class="badge badge-<?php echo $change > 0 ? 'danger' : 'success'; ?>">
                                            <?php echo $change > 0 ? '+' : ''; ?><?php echo number_format($change, 2); ?> kWh
                                            (<?php echo number_format($changePercent, 1); ?>%)
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        <?php else: ?>
                            <p class="text-muted text-center">ไม่มีข้อมูลปีที่แล้ว</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Stats Card -->
                <div class="card card-secondary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-calculator"></i>
                            สถิติอย่างรวดเร็ว
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php
                        // Calculate average cost per day this month
                        $stmt = $db->prepare("
                            SELECT 
                                AVG(ee_unit) as avg_daily_ee,
                                MAX(ee_unit) as max_daily_ee,
                                MIN(ee_unit) as min_daily_ee
                            FROM electricity_summary
                            WHERE MONTH(record_date) = ? AND YEAR(record_date) = ?
                        ");
                        $stmt->execute([$sameMonth, date('Y', strtotime($record['record_date']))]);
                        $dailyStats = $stmt->fetch();
                        ?>
                        
                        <table class="table table-sm">
                            <tr>
                                <th>ค่าเฉลี่ยรายวัน:</th>
                                <td class="text-right"><?php echo number_format($dailyStats['avg_daily_ee'], 2); ?> kWh</td>
                            </tr>
                            <tr>
                                <th>สูงสุดรายวัน:</th>
                                <td class="text-right"><?php echo number_format($dailyStats['max_daily_ee'], 2); ?> kWh</td>
                            </tr>
                            <tr>
                                <th>ต่ำสุดรายวัน:</th>
                                <td class="text-right"><?php echo number_format($dailyStats['min_daily_ee'], 2); ?> kWh</td>
                            </tr>
                            <tr>
                                <th>วันนี้เทียบกับเฉลี่ย:</th>
                                <td class="text-right">
                                    <?php 
                                    $vsAvg = $record['ee_unit'] - $dailyStats['avg_daily_ee'];
                                    $vsAvgPercent = $dailyStats['avg_daily_ee'] > 0 ? ($vsAvg / $dailyStats['avg_daily_ee']) * 100 : 0;
                                    ?>
                                    <span class="badge badge-<?php echo $vsAvg > 0 ? 'warning' : 'success'; ?>">
                                        <?php echo $vsAvg > 0 ? '+' : ''; ?><?php echo number_format($vsAvg, 2); ?> kWh
                                        (<?php echo number_format($vsAvgPercent, 1); ?>%)
                                    </span>
                                </td>
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
    // Initialize datepicker
    $('#recordDatePicker').datetimepicker({
        format: 'DD/MM/YYYY',
        locale: 'th',
        useCurrent: false
    });
    
    // Calculate total cost
    $('#eeUnit, #costPerUnit').on('input', function() {
        calculateTotalCost();
    });
    
    // Form submit
    $('#editRecordForm').on('submit', function(e) {
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
    if (!$('#editRecordForm')[0].checkValidity()) {
        $('#editRecordForm')[0].reportValidity();
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