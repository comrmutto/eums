<?php
/**
 * Documents Management
 * Engineering Utility Monitoring System (EUMS)
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /eums/login.php');
    exit();
}

// Set page title
$pageTitle = 'จัดการเอกสาร';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'ตั้งค่า', 'link' => '#'],
    ['title' => 'จัดการเอกสาร', 'link' => null]
];

// Include header
require_once __DIR__ . '/../includes/header.php';

// Load required files
require_once __DIR__ . '/../includes/functions.php';

// Get database connection
$db = getDB();

// Get parameters
$module = isset($_GET['module']) ? $_GET['module'] : '';
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Build query
$sql = "SELECT d.*, 
               (SELECT COUNT(*) FROM air_daily_records WHERE doc_id = d.id) as air_count,
               (SELECT COUNT(*) FROM meter_daily_readings WHERE doc_id = d.id) as energy_count,
               (SELECT COUNT(*) FROM lpg_daily_records WHERE doc_id = d.id) as lpg_count,
               (SELECT COUNT(*) FROM boiler_daily_records WHERE doc_id = d.id) as boiler_count,
               (SELECT COUNT(*) FROM electricity_summary WHERE doc_id = d.id) as summary_count
        FROM documents d
        WHERE 1=1";
$params = [];

if ($module) {
    $sql .= " AND d.module_type = ?";
    $params[] = $module;
}

if ($year) {
    $sql .= " AND YEAR(d.start_date) = ?";
    $params[] = $year;
}

$sql .= " ORDER BY d.start_date DESC, d.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll();

// Get modules list
$modules = [
    'air' => 'Air Compressor',
    'energy_water' => 'Energy & Water',
    'lpg' => 'LPG',
    'boiler' => 'Boiler',
    'summary' => 'Summary Electricity'
];

// Get statistics
$stmt = $db->query("
    SELECT 
        module_type,
        COUNT(*) as total,
        MAX(start_date) as latest
    FROM documents
    GROUP BY module_type
");
$moduleStats = $stmt->fetchAll();
$statsByModule = [];
foreach ($moduleStats as $stat) {
    $statsByModule[$stat['module_type']] = $stat;
}
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Statistics Cards -->
        <div class="row">
            <?php foreach ($modules as $key => $name): ?>
            <div class="col-lg-2 col-4">
                <div class="small-box bg-<?php 
                    echo $key == 'air' ? 'info' : 
                        ($key == 'energy_water' ? 'warning' : 
                        ($key == 'lpg' ? 'danger' : 
                        ($key == 'boiler' ? 'secondary' : 'success'))); 
                ?>">
                    <div class="inner">
                        <h3><?php echo $statsByModule[$key]['total'] ?? 0; ?></h3>
                        <p><?php echo $name; ?></p>
                        <small>ล่าสุด: <?php echo isset($statsByModule[$key]['latest']) ? date('d/m/Y', strtotime($statsByModule[$key]['latest'])) : '-'; ?></small>
                    </div>
                    <div class="icon">
                        <i class="fas fa-file"></i>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Filter Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-filter"></i>
                    ค้นหาเอกสาร
                </h3>
            </div>
            <div class="card-body">
                <form method="GET" class="form-inline">
                    <div class="form-group mr-2">
                        <label class="mr-2">โมดูล:</label>
                        <select name="module" class="form-control">
                            <option value="">ทั้งหมด</option>
                            <?php foreach ($modules as $key => $name): ?>
                            <option value="<?php echo $key; ?>" <?php echo $key == $module ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mr-2">
                        <label class="mr-2">ปี:</label>
                        <select name="year" class="form-control">
                            <option value="">ทั้งหมด</option>
                            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                <?php echo $y + 543; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> ค้นหา
                    </button>
                    <a href="documents.php" class="btn btn-default ml-2">
                        <i class="fas fa-redo"></i> รีเซ็ต
                    </a>
                </form>
            </div>
        </div>

        <!-- Documents List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list"></i>
                    รายการเอกสาร
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" onclick="showDocumentModal()">
                        <i class="fas fa-plus"></i> เพิ่มเอกสาร
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped dataTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>เลขที่เอกสาร</th>
                                <th>โมดูล</th>
                                <th>วันที่เริ่มใช้</th>
                                <th>Rev.No.</th>
                                <th>รายละเอียด</th>
                                <th>จำนวนบันทึก</th>
                                <th>วันที่สร้าง</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $index => $doc): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($doc['doc_no']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $doc['module_type'] == 'air' ? 'info' : 
                                            ($doc['module_type'] == 'energy_water' ? 'warning' : 
                                            ($doc['module_type'] == 'lpg' ? 'danger' : 
                                            ($doc['module_type'] == 'boiler' ? 'secondary' : 'success'))); 
                                    ?>">
                                        <?php echo $modules[$doc['module_type']] ?? $doc['module_type']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($doc['start_date'])); ?></td>
                                <td><?php echo $doc['rev_no'] ?: '-'; ?></td>
                                <td><?php echo htmlspecialchars($doc['details'] ?: '-'); ?></td>
                                <td>
                                    <?php 
                                    $total = $doc['air_count'] + $doc['energy_count'] + $doc['lpg_count'] + 
                                             $doc['boiler_count'] + $doc['summary_count'];
                                    echo $total;
                                    ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($doc['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-info" onclick="viewDocument(<?php echo $doc['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning" onclick="editDocument(<?php echo $doc['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteDocument(<?php echo $doc['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($documents)): ?>
                            <tr>
                                <td colspan="9" class="text-center">ไม่พบเอกสาร</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Document Modal -->
<div class="modal fade" id="documentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="documentModalTitle">เพิ่มเอกสาร</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="documentForm" method="POST" action="ajax/save_document.php">
                <div class="modal-body">
                    <input type="hidden" name="id" id="documentId">
                    
                    <div class="form-group">
                        <label>โมดูล <span class="text-danger">*</span></label>
                        <select class="form-control" name="module_type" id="moduleType" required>
                            <option value="">เลือกโมดูล</option>
                            <?php foreach ($modules as $key => $name): ?>
                            <option value="<?php echo $key; ?>"><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>เลขที่เอกสาร <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="doc_no" id="docNo" 
                               required maxlength="50">
                        <small class="text-muted">เช่น AC-2567-0001</small>
                    </div>
                    
                    <div class="form-group">
                        <label>วันที่เริ่มใช้ <span class="text-danger">*</span></label>
                        <div class="input-group date" id="startDatePicker" data-target-input="nearest">
                            <input type="text" class="form-control datetimepicker-input" 
                                   name="start_date" id="startDate" 
                                   data-target="#startDatePicker" required>
                            <div class="input-group-append" data-target="#startDatePicker" data-toggle="datetimepicker">
                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Rev.No.</label>
                        <input type="text" class="form-control" name="rev_no" id="revNo" maxlength="20">
                    </div>
                    
                    <div class="form-group">
                        <label>รายละเอียด</label>
                        <textarea class="form-control" name="details" id="details" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Document Modal -->
<div class="modal fade" id="viewDocumentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">รายละเอียดเอกสาร</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="documentDetails">
                <!-- Loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#startDatePicker').datetimepicker({
        format: 'DD/MM/YYYY',
        locale: 'th',
        useCurrent: true
    });
    
    $('#documentForm').on('submit', function(e) {
        e.preventDefault();
        saveDocument();
    });
    
    $('#docNo').on('blur', function() {
        checkDocNo();
    });
});

function showDocumentModal(id = null) {
    if (id) {
        editDocument(id);
    } else {
        $('#documentModalTitle').text('เพิ่มเอกสาร');
        $('#documentForm')[0].reset();
        $('#documentId').val('');
        $('#startDate').val('');
        $('#documentModal').modal('show');
    }
}

function editDocument(id) {
    $.ajax({
        url: 'ajax/get_document.php',
        method: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#documentModalTitle').text('แก้ไขเอกสาร');
                $('#documentId').val(response.data.id);
                $('#moduleType').val(response.data.module_type);
                $('#docNo').val(response.data.doc_no);
                $('#startDate').val(response.data.start_date_thai);
                $('#revNo').val(response.data.rev_no);
                $('#details').val(response.data.details);
                $('#documentModal').modal('show');
            } else {
                showNotification(response.message, 'danger');
            }
        },
        error: function() {
            showNotification('เกิดข้อผิดพลาดในการโหลดข้อมูล', 'danger');
        }
    });
}

function viewDocument(id) {
    $.ajax({
        url: 'ajax/get_document.php',
        method: 'GET',
        data: { id: id },
        dataType: 'json',
        beforeSend: function() {
            $('#viewDocumentModal .modal-body').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-3x"></i><p>กำลังโหลด...</p></div>');
            $('#viewDocumentModal').modal('show');
        },
        success: function(response) {
            if (response.success) {
                let html = generateDocumentDetails(response.data);
                $('#documentDetails').html(html);
            } else {
                $('#documentDetails').html('<div class="alert alert-danger">' + response.message + '</div>');
            }
        },
        error: function() {
            $('#documentDetails').html('<div class="alert alert-danger">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>');
        }
    });
}

function generateDocumentDetails(doc) {
    const moduleNames = {
        'air': 'Air Compressor',
        'energy_water': 'Energy & Water',
        'lpg': 'LPG',
        'boiler': 'Boiler',
        'summary': 'Summary Electricity'
    };
    
    const moduleClass = {
        'air': 'info',
        'energy_water': 'warning',
        'lpg': 'danger',
        'boiler': 'secondary',
        'summary': 'success'
    };
    
    let usageHtml = '';
    if (doc.usage_stats) {
        usageHtml = '<h6 class="mt-3">สถิติการใช้งาน</h6><ul class="list-group">';
        if (doc.usage_stats.air_count > 0) usageHtml += '<li class="list-group-item">Air Compressor: ' + doc.usage_stats.air_count + ' รายการ</li>';
        if (doc.usage_stats.energy_count > 0) usageHtml += '<li class="list-group-item">Energy & Water: ' + doc.usage_stats.energy_count + ' รายการ</li>';
        if (doc.usage_stats.lpg_count > 0) usageHtml += '<li class="list-group-item">LPG: ' + doc.usage_stats.lpg_count + ' รายการ</li>';
        if (doc.usage_stats.boiler_count > 0) usageHtml += '<li class="list-group-item">Boiler: ' + doc.usage_stats.boiler_count + ' รายการ</li>';
        if (doc.usage_stats.summary_count > 0) usageHtml += '<li class="list-group-item">Summary Electricity: ' + doc.usage_stats.summary_count + ' รายการ</li>';
        usageHtml += '</ul>';
    }
    
    return `
        <div class="row">
            <div class="col-md-12">
                <table class="table table-sm">
                    <tr>
                        <th style="width: 30%">เลขที่เอกสาร:</th>
                        <td><strong>${doc.doc_no}</strong></td>
                    </tr>
                    <tr>
                        <th>โมดูล:</th>
                        <td><span class="badge badge-${moduleClass[doc.module_type]}">${moduleNames[doc.module_type]}</span></td>
                    </tr>
                    <tr>
                        <th>วันที่เริ่มใช้:</th>
                        <td>${doc.start_date_thai}</td>
                    </tr>
                    <tr>
                        <th>Rev.No.:</th>
                        <td>${doc.rev_no || '-'}</td>
                    </tr>
                    <tr>
                        <th>รายละเอียด:</th>
                        <td>${doc.details || '-'}</td>
                    </tr>
                    <tr>
                        <th>วันที่สร้าง:</th>
                        <td>${doc.created_at}</td>
                    </tr>
                    <tr>
                        <th>ปรับปรุงล่าสุด:</th>
                        <td>${doc.updated_at || '-'}</td>
                    </tr>
                </table>
                ${usageHtml}
            </div>
        </div>
    `;
}

function saveDocument() {
    if (!$('#documentForm')[0].checkValidity()) {
        $('#documentForm')[0].reportValidity();
        return;
    }
    
    const formData = $('#documentForm').serialize();
    
    $.ajax({
        url: 'ajax/save_document.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        beforeSend: function() {
            $('#documentModal .btn-primary').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...');
        },
        success: function(response) {
            if (response.success) {
                $('#documentModal').modal('hide');
                showNotification('บันทึกข้อมูลเรียบร้อย', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                $('#documentModal .btn-primary').prop('disabled', false).html('บันทึก');
                showNotification(response.message, 'danger');
            }
        },
        error: function() {
            $('#documentModal .btn-primary').prop('disabled', false).html('บันทึก');
            showNotification('เกิดข้อผิดพลาดในการบันทึกข้อมูล', 'danger');
        }
    });
}

function deleteDocument(id) {
    if (confirm('คุณต้องการลบเอกสารนี้ใช่หรือไม่?')) {
        $.ajax({
            url: 'ajax/delete_document.php',
            method: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showNotification('ลบข้อมูลเรียบร้อย', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification(response.message, 'danger');
                }
            },
            error: function() {
                showNotification('เกิดข้อผิดพลาดในการลบข้อมูล', 'danger');
            }
        });
    }
}

function checkDocNo() {
    const docNo = $('#docNo').val();
    const id = $('#documentId').val();
    
    if (docNo) {
        $.ajax({
            url: 'ajax/check_docno.php',
            method: 'POST',
            data: { doc_no: docNo, id: id },
            dataType: 'json',
            success: function(response) {
                if (response.exists) {
                    $('#docNo').addClass('is-invalid');
                    if (!$('#docNo').next('.invalid-feedback').length) {
                        $('#docNo').after('<div class="invalid-feedback">เลขที่เอกสารนี้มีอยู่ในระบบแล้ว</div>');
                    }
                } else {
                    $('#docNo').removeClass('is-invalid');
                    $('.invalid-feedback').remove();
                }
            }
        });
    }
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../includes/footer.php';
?>