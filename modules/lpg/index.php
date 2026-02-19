<?php
/**
 * LPG Module - Main Index Page
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'LPG - บันทึกข้อมูล';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'LPG', 'link' => null],
    ['title' => 'บันทึกข้อมูล', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Load required files
require_once __DIR__ . '/../../includes/functions.php';

// Get database connection
$db = getDB();

// Get current date
$currentDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$displayDate = date('d/m/Y', strtotime($currentDate));

// Get document info
$stmt = $db->prepare("
    SELECT * FROM documents 
    WHERE module_type = 'lpg' 
    AND start_date <= ? 
    ORDER BY start_date DESC LIMIT 1
");
$stmt->execute([$currentDate]);
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

// Get today's records
$stmt = $db->prepare("
    SELECT * FROM lpg_daily_records 
    WHERE record_date = ?
");
$stmt->execute([$currentDate]);
$todayRecords = $stmt->fetchAll();

// Create lookup array for existing records
$existingRecords = [];
foreach ($todayRecords as $record) {
    $existingRecords[$record['item_id']] = $record;
}

// Get chart data for last 30 days
$stmt = $db->prepare("
    SELECT 
        record_date,
        SUM(CASE WHEN item_type = 'number' THEN number_value ELSE 0 END) as total_usage
    FROM lpg_daily_records r
    JOIN lpg_inspection_items i ON r.item_id = i.id
    WHERE r.record_date >= DATE_SUB(?, INTERVAL 30 DAY)
    AND i.item_type = 'number'
    GROUP BY r.record_date
    ORDER BY r.record_date
");
$stmt->execute([$currentDate]);
$chartData = $stmt->fetchAll();
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Document Info Card -->
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-alt"></i>
                    ข้อมูลเอกสาร
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form id="documentForm">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>เลขที่เอกสาร</label>
                                <input type="text" class="form-control" name="doc_no" 
                                       value="<?php echo $document['doc_no'] ?? 'LPG-' . date('Ymd'); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>วันที่เริ่มใช้</label>
                                <input type="text" class="form-control datepicker" name="start_date" 
                                       value="<?php echo isset($document['start_date']) ? date('d/m/Y', strtotime($document['start_date'])) : date('d/m/Y'); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Rev.No.</label>
                                <input type="text" class="form-control" name="rev_no" 
                                       value="<?php echo $document['rev_no'] ?? '00'; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="button" class="btn btn-primary btn-block" id="updateDocument">
                                    <i class="fas fa-save"></i> อัปเดตเอกสาร
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>รายละเอียด</label>
                                <textarea class="form-control" name="details" rows="2"><?php echo $document['details'] ?? ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Date Selector -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-alt"></i>
                    เลือกวันที่
                </h3>
            </div>
            <div class="card-body">
                <form method="GET" class="form-inline">
                    <div class="form-group mr-2">
                        <label class="mr-2">วันที่:</label>
                        <input type="text" class="form-control datepicker" name="date" 
                               value="<?php echo $displayDate; ?>" id="datePicker">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> แสดงข้อมูล
                    </button>
                    <button type="button" class="btn btn-success ml-2" onclick="goToToday()">
                        <i class="fas fa-calendar-check"></i> วันนี้
                    </button>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo count($inspectionItems); ?></h3>
                        <p>หัวข้อตรวจสอบทั้งหมด</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <a href="settings.php" class="small-box-footer">
                        จัดการ <i class="fas fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo count($numberItems); ?></h3>
                        <p>หัวข้อแบบตัวเลข</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo count($enumItems); ?></h3>
                        <p>หัวข้อแบบ OK/NG</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo count($todayRecords); ?></h3>
                        <p>บันทึกวันนี้</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-pen"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-line"></i>
                    กราฟสรุปปริมาณการใช้งาน LPG ย้อนหลัง 30 วัน
                </h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="usageChart" style="min-height: 300px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Daily Records Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-edit"></i>
                    บันทึกข้อมูลประจำวัน: <?php echo $displayDate; ?>
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" onclick="saveAllRecords()">
                        <i class="fas fa-save"></i> บันทึกทั้งหมด
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form id="lpgForm">
                    <input type="hidden" name="record_date" value="<?php echo $currentDate; ?>">
                    <input type="hidden" name="doc_id" value="<?php echo $document['id'] ?? 0; ?>">
                    
                    <!-- Number Items Section -->
                    <div class="card card-secondary mb-3">
                        <div class="card-header">
                            <h5 class="card-title">ข้อมูลตัวเลข</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th style="width: 5%">ลำดับ</th>
                                            <th style="width: 35%">รายการ</th>
                                            <th style="width: 20%">ค่ามาตรฐาน</th>
                                            <th style="width: 25%">ค่าที่บันทึก</th>
                                            <th style="width: 15%">หน่วย</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($numberItems as $item): ?>
                                        <?php 
                                        $record = $existingRecords[$item['id']] ?? null;
                                        $value = $record ? $record['number_value'] : '';
                                        ?>
                                        <tr>
                                            <td><?php echo $item['item_no']; ?></td>
                                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['standard_value']); ?></td>
                                            <td>
                                                <input type="number" step="0.01" 
                                                       class="form-control form-control-sm number-input" 
                                                       name="numbers[<?php echo $item['id']; ?>]" 
                                                       id="num_<?php echo $item['id']; ?>"
                                                       value="<?php echo $value; ?>"
                                                       data-standard="<?php echo $item['standard_value']; ?>"
                                                       data-item="<?php echo htmlspecialchars($item['item_name']); ?>">
                                                <div class="invalid-feedback" id="feedback_<?php echo $item['id']; ?>"></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Enum Items Section -->
                    <div class="card card-secondary">
                        <div class="card-header">
                            <h5 class="card-title">สถานะ OK/NG</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th style="width: 5%">ลำดับ</th>
                                            <th style="width: 60%">รายการ</th>
                                            <th style="width: 35%">สถานะ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($enumItems as $item): ?>
                                        <?php 
                                        $record = $existingRecords[$item['id']] ?? null;
                                        $value = $record ? $record['enum_value'] : '';
                                        $options = json_decode($item['enum_options'], true) ?? ['OK', 'NG'];
                                        ?>
                                        <tr>
                                            <td><?php echo $item['item_no']; ?></td>
                                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                                    <?php foreach ($options as $option): ?>
                                                    <label class="btn btn-outline-<?php echo $option == 'OK' ? 'success' : 'danger'; ?> btn-sm 
                                                                          <?php echo $value == $option ? 'active' : ''; ?>">
                                                        <input type="radio" name="enums[<?php echo $item['id']; ?>]" 
                                                               value="<?php echo $option; ?>"
                                                               <?php echo $value == $option ? 'checked' : ''; ?>
                                                               autocomplete="off"> <?php echo $option; ?>
                                                    </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>หมายเหตุ</label>
                        <textarea class="form-control" name="remarks" rows="2"><?php echo $todayRecords[0]['remarks'] ?? ''; ?></textarea>
                    </div>
                </form>
            </div>
            <div class="card-footer">
                <button type="button" class="btn btn-primary" onclick="saveAllRecords()">
                    <i class="fas fa-save"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-secondary" onclick="resetForm()">
                    <i class="fas fa-undo"></i> รีเซ็ต
                </button>
            </div>
        </div>
    </div>
</section>

<script>
let usageChart = null;

$(document).ready(function() {
    // Initialize datepicker
    $('.datepicker').datepicker({
        format: 'dd/mm/yyyy',
        autoclose: true,
        language: 'th'
    });
    
    // Load chart
    renderChart(<?php echo json_encode($chartData); ?>);
    
    // Number input validation
    $('.number-input').on('input', function() {
        validateNumber($(this));
    });
    
    // Update document
    $('#updateDocument').on('click', function() {
        updateDocument();
    });
});

function renderChart(data) {
    const ctx = document.getElementById('usageChart').getContext('2d');
    
    const labels = data.map(item => {
        const d = new Date(item.record_date);
        return d.getDate() + '/' + (d.getMonth() + 1);
    });
    
    const values = data.map(item => parseFloat(item.total_usage) || 0);
    
    if (usageChart) {
        usageChart.destroy();
    }
    
    usageChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'ปริมาณการใช้ LPG',
                data: values,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#dc3545',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'ปริมาณ: ' + context.raw.toFixed(2) + ' หน่วย';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'ปริมาณ'
                    }
                }
            }
        }
    });
}

function validateNumber(input) {
    const value = parseFloat(input.val());
    const standard = parseFloat(input.data('standard'));
    const itemName = input.data('item');
    const id = input.attr('id').replace('num_', '');
    
    if (isNaN(value)) {
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

function saveAllRecords() {
    // Validate all number inputs
    let hasError = false;
    $('.number-input').each(function() {
        if ($(this).hasClass('is-invalid')) {
            hasError = true;
        }
    });
    
    if (hasError) {
        if (!confirm('มีบางรายการที่ค่าอยู่นอกเกณฑ์มาตรฐาน คุณต้องการบันทึกต่อหรือไม่?')) {
            return;
        }
    }
    
    const formData = $('#lpgForm').serialize();
    
    $.ajax({
        url: 'ajax/save_record.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        beforeSend: function() {
            showNotification('กำลังบันทึกข้อมูล...', 'info');
        },
        success: function(response) {
            if (response.success) {
                showNotification('บันทึกข้อมูลเรียบร้อย', 'success');
                if (response.warnings && response.warnings.length > 0) {
                    response.warnings.forEach(function(warning) {
                        showNotification(warning, 'warning');
                    });
                }
                setTimeout(function() {
                    location.reload();
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

function resetForm() {
    if (confirm('ต้องการรีเซ็ตข้อมูลทั้งหมดหรือไม่?')) {
        $('#lpgForm')[0].reset();
        $('.number-input').removeClass('is-valid is-invalid');
        $('.invalid-feedback').hide();
    }
}

function goToToday() {
    const today = new Date();
    const dd = String(today.getDate()).padStart(2, '0');
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const yyyy = today.getFullYear();
    window.location.href = 'index.php?date=' + yyyy + '-' + mm + '-' + dd;
}

function updateDocument() {
    const formData = $('#documentForm').serialize();
    formData += '&module=lpg';
    
    $.ajax({
        url: 'ajax/update_document.php',
        method: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                showNotification('อัปเดตเอกสารเรียบร้อย', 'success');
            } else {
                showNotification(response.message, 'danger');
            }
        }
    });
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>