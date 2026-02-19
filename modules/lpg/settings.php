<?php
/**
 * LPG Module - Settings (Inspection Items Management)
 * Engineering Utility Monitoring System (EUMS)
 */

// Set page title
$pageTitle = 'LPG - ตั้งค่าหัวข้อตรวจสอบ';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'LPG', 'link' => 'index.php'],
    ['title' => 'ตั้งค่าหัวข้อตรวจสอบ', 'link' => null]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';

// Load required files
require_once __DIR__ . '/../../includes/functions.php';

// Get database connection
$db = getDB();

// Get all inspection items
$stmt = $db->query("
    SELECT * FROM lpg_inspection_items 
    ORDER BY item_no
");
$items = $stmt->fetchAll();
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Items List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list"></i>
                    หัวข้อตรวจสอบ
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" onclick="showItemModal()">
                        <i class="fas fa-plus"></i> เพิ่มหัวข้อ
                    </button>
                </div>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs" id="itemTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="all-tab" data-toggle="tab" href="#all" role="tab">
                            ทั้งหมด
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="numbers-tab" data-toggle="tab" href="#numbers" role="tab">
                            แบบตัวเลข
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="enums-tab" data-toggle="tab" href="#enums" role="tab">
                            แบบ OK/NG
                        </a>
                    </li>
                </ul>
                
                <div class="tab-content mt-3" id="itemTabsContent">
                    <!-- All Items -->
                    <div class="tab-pane fade show active" id="all" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped dataTable">
                                <thead>
                                    <tr>
                                        <th>ลำดับ</th>
                                        <th>ประเภท</th>
                                        <th>หัวข้อตรวจสอบ</th>
                                        <th>ค่ามาตรฐาน</th>
                                        <th>หน่วย</th>
                                        <th>ตัวเลือก (Enum)</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?php echo $item['item_no']; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $item['item_type'] == 'number' ? 'success' : 'warning'; ?>">
                                                <?php echo $item['item_type'] == 'number' ? 'ตัวเลข' : 'OK/NG'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['standard_value']); ?></td>
                                        <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                        <td>
                                            <?php 
                                            if ($item['item_type'] == 'enum' && $item['enum_options']) {
                                                $options = json_decode($item['enum_options'], true);
                                                echo implode(', ', $options);
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick="editItem(<?php echo $item['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Number Items -->
                    <div class="tab-pane fade" id="numbers" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ลำดับ</th>
                                        <th>หัวข้อตรวจสอบ</th>
                                        <th>ค่ามาตรฐาน</th>
                                        <th>หน่วย</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_filter($items, function($i) { return $i['item_type'] == 'number'; }) as $item): ?>
                                    <tr>
                                        <td><?php echo $item['item_no']; ?></td>
                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['standard_value']); ?></td>
                                        <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick="editItem(<?php echo $item['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Enum Items -->
                    <div class="tab-pane fade" id="enums" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ลำดับ</th>
                                        <th>หัวข้อตรวจสอบ</th>
                                        <th>ค่ามาตรฐาน</th>
                                        <th>ตัวเลือก</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_filter($items, function($i) { return $i['item_type'] == 'enum'; }) as $item): ?>
                                    <tr>
                                        <td><?php echo $item['item_no']; ?></td>
                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['standard_value']); ?></td>
                                        <td>
                                            <?php 
                                            $options = json_decode($item['enum_options'], true);
                                            foreach ($options as $opt) {
                                                echo '<span class="badge badge-' . ($opt == 'OK' ? 'success' : 'danger') . ' mr-1">' . $opt . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick="editItem(<?php echo $item['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>)">
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
        </div>
    </div>
</section>

<!-- Item Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="itemModalTitle">เพิ่มหัวข้อตรวจสอบ</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="itemForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="itemId">
                    
                    <div class="form-group">
                        <label>ลำดับที่ <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="item_no" id="itemNo" required min="1">
                    </div>
                    
                    <div class="form-group">
                        <label>ประเภท <span class="text-danger">*</span></label>
                        <select class="form-control" name="item_type" id="itemType" required onchange="toggleItemType()">
                            <option value="">เลือกประเภท</option>
                            <option value="number">แบบตัวเลข</option>
                            <option value="enum">แบบ OK/NG</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>หัวข้อตรวจสอบ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="item_name" id="itemName" required maxlength="255">
                    </div>
                    
                    <div class="form-group" id="standardField">
                        <label>ค่ามาตรฐาน</label>
                        <input type="text" class="form-control" name="standard_value" id="standardValue">
                        <small class="text-muted">สำหรับแบบตัวเลข กรอกตัวเลข, แบบ OK/NG กรอกค่าที่ต้องการให้เป็นมาตรฐาน</small>
                    </div>
                    
                    <div class="form-group" id="unitField">
                        <label>หน่วย</label>
                        <input type="text" class="form-control" name="unit" id="unit" placeholder="เช่น kg, L">
                    </div>
                    
                    <div class="form-group" id="enumField" style="display: none;">
                        <label>ตัวเลือก (คั่นด้วยเครื่องหมาย ,) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="enum_options" id="enumOptions" value="OK,NG">
                        <small class="text-muted">ตัวอย่าง: OK,NG หรือ Pass,Fail</small>
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
    $('#itemForm').on('submit', function(e) {
        e.preventDefault();
        saveItem();
    });
});

function toggleItemType() {
    const type = $('#itemType').val();
    
    if (type === 'number') {
        $('#unitField').show();
        $('#enumField').hide();
        $('#standardValue').attr('placeholder', 'เช่น 100');
    } else if (type === 'enum') {
        $('#unitField').hide();
        $('#enumField').show();
        $('#standardValue').attr('placeholder', 'เช่น OK');
    }
}

function showItemModal(id = null) {
    if (id) {
        editItem(id);
    } else {
        $('#itemModalTitle').text('เพิ่มหัวข้อตรวจสอบ');
        $('#itemForm')[0].reset();
        $('#itemId').val('');
        $('#unitField').show();
        $('#enumField').hide();
        $('#itemModal').modal('show');
    }
}

function editItem(id) {
    $.ajax({
        url: 'ajax/get_item.php',
        method: 'GET',
        data: { id: id },
        success: function(response) {
            if (response.success) {
                $('#itemModalTitle').text('แก้ไขหัวข้อตรวจสอบ');
                $('#itemId').val(response.data.id);
                $('#itemNo').val(response.data.item_no);
                $('#itemType').val(response.data.item_type).trigger('change');
                $('#itemName').val(response.data.item_name);
                $('#standardValue').val(response.data.standard_value);
                $('#unit').val(response.data.unit);
                
                if (response.data.enum_options) {
                    const options = JSON.parse(response.data.enum_options);
                    $('#enumOptions').val(options.join(','));
                }
                
                toggleItemType();
                $('#itemModal').modal('show');
            }
        }
    });
}

function saveItem() {
    if (!$('#itemForm')[0].checkValidity()) {
        $('#itemForm')[0].reportValidity();
        return;
    }
    
    const formData = $('#itemForm').serialize();
    
    $.ajax({
        url: 'ajax/save_item.php',
        method: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                $('#itemModal').modal('hide');
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

function deleteItem(id) {
    if (confirm('คุณต้องการลบหัวข้อตรวจสอบนี้ใช่หรือไม่?')) {
        $.ajax({
            url: 'ajax/delete_item.php',
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