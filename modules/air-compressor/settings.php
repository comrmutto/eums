<?php
/**
 * Air Compressor Module - Inspection Standards Settings
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'Air Compressor - ตั้งค่าการตรวจสอบ';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'Air Compressor', 'link' => 'index.php'],
    ['title' => 'ตั้งค่าการตรวจสอบ', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Load required files
require_once __DIR__ . '/../../includes/functions.php';

// Get database connection
$db = getDB();

// Get all machines for dropdown
$stmt = $db->query("SELECT * FROM mc_air WHERE status = 1 ORDER BY machine_code");
$machines = $stmt->fetchAll();

// Get all inspection standards
$stmt = $db->query("
    SELECT s.*, m.machine_name, m.machine_code
    FROM air_inspection_standards s
    JOIN mc_air m ON s.machine_id = m.id
    ORDER BY m.machine_code, s.sort_order
");
$standards = $stmt->fetchAll();
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Standards List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clipboard-check"></i>
                    มาตรฐานการตรวจสอบ
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" onclick="showStandardModal()">
                        <i class="fas fa-plus"></i> เพิ่มหัวข้อตรวจสอบ
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped dataTable">
                        <thead>
                            <tr>
                                <th>เครื่องจักร</th>
                                <th>ลำดับ</th>
                                <th>หัวข้อตรวจสอบ</th>
                                <th>ค่ามาตรฐาน</th>
                                <th>หน่วย</th>
                                <th>ค่าต่ำสุด</th>
                                <th>ค่าสูงสุด</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($standards as $standard): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($standard['machine_code'] . ' - ' . $standard['machine_name']); ?></td>
                                <td><?php echo $standard['sort_order']; ?></td>
                                <td><?php echo htmlspecialchars($standard['inspection_item']); ?></td>
                                <td class="text-right"><?php echo number_format($standard['standard_value'], 2); ?></td>
                                <td><?php echo htmlspecialchars($standard['unit']); ?></td>
                                <td class="text-right"><?php echo $standard['min_value'] ? number_format($standard['min_value'], 2) : '-'; ?></td>
                                <td class="text-right"><?php echo $standard['max_value'] ? number_format($standard['max_value'], 2) : '-'; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editStandard(<?php echo $standard['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteStandard(<?php echo $standard['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Standard Modal -->
<div class="modal fade" id="standardModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="standardModalTitle">เพิ่มหัวข้อตรวจสอบ</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="standardForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="standardId">
                    
                    <div class="form-group">
                        <label>เครื่องจักร <span class="text-danger">*</span></label>
                        <select class="form-control select2" name="machine_id" id="machineId" required>
                            <option value="">เลือกเครื่องจักร</option>
                            <?php foreach ($machines as $machine): ?>
                                <option value="<?php echo $machine['id']; ?>">
                                    <?php echo $machine['machine_code'] . ' - ' . $machine['machine_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>ลำดับ</label>
                        <input type="number" class="form-control" name="sort_order" id="sortOrder" 
                               min="1" value="1">
                    </div>
                    
                    <div class="form-group">
                        <label>หัวข้อตรวจสอบ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="inspection_item" id="inspectionItem" 
                               required maxlength="255" placeholder="เช่น แรงดันลม">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ค่ามาตรฐาน</label>
                                <input type="number" step="0.01" class="form-control" name="standard_value" id="standardValue">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>หน่วย</label>
                                <input type="text" class="form-control" name="unit" id="unit" 
                                       maxlength="20" placeholder="เช่น bar, psi">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ค่าต่ำสุดที่ยอมรับ</label>
                                <input type="number" step="0.01" class="form-control" name="min_value" id="minValue">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ค่าสูงสุดที่ยอมรับ</label>
                                <input type="number" step="0.01" class="form-control" name="max_value" id="maxValue">
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        หากระบุค่าต่ำสุดและสูงสุด ระบบจะใช้ช่วงนี้ในการตรวจสอบ 
                        หากไม่ระบุจะใช้ค่ามาตรฐาน ±10% ในการตรวจสอบ
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

<script>
$(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $('#standardModal')
    });
    
    $('#standardForm').on('submit', function(e) {
        e.preventDefault();
        saveStandard();
    });
});

function showStandardModal(id = null) {
    if (id) {
        editStandard(id);
    } else {
        $('#standardModalTitle').text('เพิ่มหัวข้อตรวจสอบ');
        $('#standardForm')[0].reset();
        $('#standardId').val('');
        $('#standardModal').modal('show');
    }
}

function editStandard(id) {
    $.ajax({
        url: 'ajax/get_standard.php',
        method: 'GET',
        data: { id: id },
        success: function(response) {
            if (response.success) {
                $('#standardModalTitle').text('แก้ไขหัวข้อตรวจสอบ');
                $('#standardId').val(response.data.id);
                $('#machineId').val(response.data.machine_id).trigger('change');
                $('#sortOrder').val(response.data.sort_order);
                $('#inspectionItem').val(response.data.inspection_item);
                $('#standardValue').val(response.data.standard_value);
                $('#unit').val(response.data.unit);
                $('#minValue').val(response.data.min_value);
                $('#maxValue').val(response.data.max_value);
                $('#standardModal').modal('show');
            }
        }
    });
}

function saveStandard() {
    if (!$('#standardForm')[0].checkValidity()) {
        $('#standardForm')[0].reportValidity();
        return;
    }
    
    const formData = $('#standardForm').serialize();
    
    $.ajax({
        url: 'ajax/save_standard.php',
        method: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                $('#standardModal').modal('hide');
                showNotification('บันทึกข้อมูลเรียบร้อย', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showNotification(response.message, 'danger');
            }
        }
    });
}

function deleteStandard(id) {
    if (confirm('คุณต้องการลบหัวข้อตรวจสอบนี้ใช่หรือไม่?')) {
        $.ajax({
            url: 'ajax/delete_standard.php',
            method: 'POST',
            data: { id: id },
            success: function(response) {
                if (response.success) {
                    showNotification('ลบข้อมูลเรียบร้อย', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification(response.message, 'danger');
                }
            }
        });
    }
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>