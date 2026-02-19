<?php
/**
 * Users Management
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

// Check permission (เฉพาะ admin เท่านั้น)
if ($_SESSION['user_role'] !== 'admin') {
    $_SESSION['error'] = 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้';
    header('Location: /eums/index.php');
    exit();
}

// Set page title
$pageTitle = 'จัดการผู้ใช้งาน';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'ตั้งค่า', 'link' => '#'],
    ['title' => 'จัดการผู้ใช้งาน', 'link' => null]
];

// Include header
require_once __DIR__ . '/../includes/header.php';

// Load required files
require_once __DIR__ . '/../includes/functions.php';

// Get database connection
$db = getDB();

// Get users list
$stmt = $db->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM activity_logs WHERE user_id = u.id) as login_count,
           (SELECT MAX(created_at) FROM activity_logs WHERE user_id = u.id AND action = 'login') as last_login
    FROM users u
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();

// Get roles list
$stmt = $db->query("SELECT * FROM roles ORDER BY id");
$roles = $stmt->fetchAll();

// Get permissions list
$stmt = $db->query("SELECT * FROM permissions ORDER BY module, permission_name");
$permissions = $stmt->fetchAll();
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo count($users); ?></h3>
                        <p>ผู้ใช้งานทั้งหมด</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>
                            <?php 
                            $active = array_filter($users, function($u) { return $u['status'] == 1; });
                            echo count($active);
                            ?>
                        </h3>
                        <p>กำลังใช้งาน</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>
                            <?php 
                            $admins = array_filter($users, function($u) { return $u['role'] == 'admin'; });
                            echo count($admins);
                            ?>
                        </h3>
                        <p>ผู้ดูแลระบบ</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>
                            <?php 
                            $inactive = array_filter($users, function($u) { return $u['status'] == 0; });
                            echo count($inactive);
                            ?>
                        </h3>
                        <p>ระงับการใช้งาน</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-ban"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list"></i>
                    รายชื่อผู้ใช้งาน
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" onclick="showUserModal()">
                        <i class="fas fa-plus"></i> เพิ่มผู้ใช้
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped dataTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ชื่อผู้ใช้</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>อีเมล</th>
                                <th>บทบาท</th>
                                <th>สถานะ</th>
                                <th>เข้าสู่ระบบล่าสุด</th>
                                <th>วันที่สร้าง</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $index => $user): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                        <span class="badge badge-info">คุณ</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $user['role'] == 'admin' ? 'danger' : 
                                            ($user['role'] == 'operator' ? 'warning' : 'secondary'); 
                                    ?>">
                                        <?php 
                                        echo $user['role'] == 'admin' ? 'ผู้ดูแลระบบ' : 
                                            ($user['role'] == 'operator' ? 'ผู้ปฏิบัติงาน' : 'ผู้ดู'); 
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $user['status'] ? 'success' : 'danger'; ?>">
                                        <?php echo $user['status'] ? 'ใช้งาน' : 'ระงับ'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if ($user['last_login']) {
                                        echo date('d/m/Y H:i', strtotime($user['last_login']));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-info" onclick="viewUser(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning" onclick="editUser(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Role & Permissions -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user-tag"></i>
                            บทบาท (Roles)
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>บทบาท</th>
                                        <th>คำอธิบาย</th>
                                        <th>จำนวนผู้ใช้</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roles as $role): ?>
                                    <?php 
                                    $count = array_filter($users, function($u) use ($role) { 
                                        return $u['role'] == $role['role_name']; 
                                    });
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $role['role_name'] == 'admin' ? 'danger' : 
                                                    ($role['role_name'] == 'operator' ? 'warning' : 'secondary'); 
                                            ?>">
                                                <?php 
                                                echo $role['role_name'] == 'admin' ? 'ผู้ดูแลระบบ' : 
                                                    ($role['role_name'] == 'operator' ? 'ผู้ปฏิบัติงาน' : 'ผู้ดู'); 
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($role['description']); ?></td>
                                        <td><?php echo count($count); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-key"></i>
                            สิทธิ์การเข้าถึง (Permissions)
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>โมดูล</th>
                                        <th>สิทธิ์</th>
                                        <th>คำอธิบาย</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $currentModule = '';
                                    foreach ($permissions as $perm): 
                                    ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            if ($perm['module'] != $currentModule) {
                                                $currentModule = $perm['module'];
                                                echo '<strong>' . ucfirst($currentModule) . '</strong>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($perm['permission_key']); ?></td>
                                        <td><?php echo htmlspecialchars($perm['permission_name']); ?></td>
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

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="userModalTitle">เพิ่มผู้ใช้งาน</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="userForm" method="POST" action="ajax/save_user.php">
                <div class="modal-body">
                    <input type="hidden" name="id" id="userId">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ชื่อผู้ใช้ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="username" id="username" 
                                       required maxlength="50">
                                <small class="text-muted">ใช้สำหรับเข้าสู่ระบบ</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>อีเมล <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" id="email" 
                                       required maxlength="100">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="fullname" id="fullname" 
                               required maxlength="100">
                    </div>
                    
                    <div class="row" id="passwordFields">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>รหัสผ่าน <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="password" id="password" 
                                       minlength="<?php echo config('security.password_min_length', 8); ?>">
                                <small class="text-muted">อย่างน้อย <?php echo config('security.password_min_length', 8); ?> ตัวอักษร</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>ยืนยันรหัสผ่าน <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="confirm_password" id="confirmPassword">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>บทบาท</label>
                                <select class="form-control" name="role" id="role">
                                    <option value="viewer">ผู้ดู</option>
                                    <option value="operator">ผู้ปฏิบัติงาน</option>
                                    <option value="admin">ผู้ดูแลระบบ</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>สถานะ</label>
                                <select class="form-control" name="status" id="status">
                                    <option value="1">ใช้งาน</option>
                                    <option value="0">ระงับการใช้งาน</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info" id="passwordInfo" style="display: none;">
                        <i class="fas fa-info-circle"></i>
 เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน
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

<!-- View User Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">รายละเอียดผู้ใช้งาน</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="userDetails">
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
    $('#userForm').on('submit', function(e) {
        e.preventDefault();
        saveUser();
    });
    
    $('#username').on('blur', function() {
        checkUsername();
    });
    
    $('#email').on('blur', function() {
        checkEmail();
    });
    
    $('#password, #confirmPassword').on('input', function() {
        validatePassword();
    });
});

function showUserModal(id = null) {
    if (id) {
        editUser(id);
    } else {
        $('#userModalTitle').text('เพิ่มผู้ใช้งาน');
        $('#userForm')[0].reset();
        $('#userId').val('');
        $('#passwordFields').show();
        $('#passwordInfo').hide();
        $('#password').prop('required', true);
        $('#confirmPassword').prop('required', true);
        $('#userModal').modal('show');
    }
}

function editUser(id) {
    $.ajax({
        url: 'ajax/get_user.php',
        method: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#userModalTitle').text('แก้ไขผู้ใช้งาน');
                $('#userId').val(response.data.id);
                $('#username').val(response.data.username);
                $('#fullname').val(response.data.fullname);
                $('#email').val(response.data.email);
                $('#role').val(response.data.role);
                $('#status').val(response.data.status);
                
                $('#passwordFields').hide();
                $('#passwordInfo').show();
                $('#password').prop('required', false);
                $('#confirmPassword').prop('required', false);
                
                $('#userModal').modal('show');
            } else {
                showNotification(response.message, 'danger');
            }
        },
        error: function() {
            showNotification('เกิดข้อผิดพลาดในการโหลดข้อมูล', 'danger');
        }
    });
}

function viewUser(id) {
    $.ajax({
        url: 'ajax/get_user.php',
        method: 'GET',
        data: { id: id },
        dataType: 'json',
        beforeSend: function() {
            $('#viewUserModal .modal-body').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-3x"></i><p>กำลังโหลด...</p></div>');
            $('#viewUserModal').modal('show');
        },
        success: function(response) {
            if (response.success) {
                let html = generateUserDetails(response.data);
                $('#userDetails').html(html);
            } else {
                $('#userDetails').html('<div class="alert alert-danger">' + response.message + '</div>');
            }
        },
        error: function() {
            $('#userDetails').html('<div class="alert alert-danger">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>');
        }
    });
}

function generateUserDetails(user) {
    const status = user.status ? 'ใช้งาน' : 'ระงับการใช้งาน';
    const statusClass = user.status ? 'success' : 'danger';
    const roleName = user.role == 'admin' ? 'ผู้ดูแลระบบ' : (user.role == 'operator' ? 'ผู้ปฏิบัติงาน' : 'ผู้ดู');
    const roleClass = user.role == 'admin' ? 'danger' : (user.role == 'operator' ? 'warning' : 'secondary');
    
    let loginHistory = '';
    if (user.login_history && user.login_history.length > 0) {
        loginHistory = '<h6 class="mt-3">ประวัติการเข้าใช้ล่าสุด</h6><ul class="list-group">';
        user.login_history.forEach(function(log) {
            loginHistory += '<li class="list-group-item">' + log.created_at + ' - ' + log.action + '</li>';
        });
        loginHistory += '</ul>';
    }
    
    return `
        <div class="row">
            <div class="col-md-12">
                <table class="table table-sm">
                    <tr>
                        <th style="width: 30%">ชื่อผู้ใช้:</th>
                        <td><strong>${user.username}</strong></td>
                    </tr>
                    <tr>
                        <th>ชื่อ-นามสกุล:</th>
                        <td>${user.fullname}</td>
                    </tr>
                    <tr>
                        <th>อีเมล:</th>
                        <td>${user.email}</td>
                    </tr>
                    <tr>
                        <th>บทบาท:</th>
                        <td><span class="badge badge-${roleClass}">${roleName}</span></td>
                    </tr>
                    <tr>
                        <th>สถานะ:</th>
                        <td><span class="badge badge-${statusClass}">${status}</span></td>
                    </tr>
                    <tr>
                        <th>เข้าสู่ระบบล่าสุด:</th>
                        <td>${user.last_login || '-'}</td>
                    </tr>
                    <tr>
                        <th>จำนวนครั้งที่เข้าใช้:</th>
                        <td>${user.login_count || 0} ครั้ง</td>
                    </tr>
                    <tr>
                        <th>วันที่สร้าง:</th>
                        <td>${user.created_at}</td>
                    </tr>
                </table>
                ${loginHistory}
            </div>
        </div>
    `;
}

function saveUser() {
    // Validate form
    if (!$('#userForm')[0].checkValidity()) {
        $('#userForm')[0].reportValidity();
        return;
    }
    
    // Validate password if adding new user
    if (!$('#userId').val()) {
        if (!$('#password').val()) {
            showNotification('กรุณากรอกรหัสผ่าน', 'warning');
            return;
        }
        if ($('#password').val() !== $('#confirmPassword').val()) {
            showNotification('รหัสผ่านไม่ตรงกัน', 'warning');
            return;
        }
        if ($('#password').val().length < <?php echo config('security.password_min_length', 8); ?>) {
            showNotification('รหัสผ่านต้องมีอย่างน้อย <?php echo config('security.password_min_length', 8); ?> ตัวอักษร', 'warning');
            return;
        }
    }
    
    const formData = $('#userForm').serialize();
    
    $.ajax({
        url: 'ajax/save_user.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        beforeSend: function() {
            $('#userModal .btn-primary').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...');
        },
        success: function(response) {
            if (response.success) {
                $('#userModal').modal('hide');
                showNotification('บันทึกข้อมูลเรียบร้อย', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                $('#userModal .btn-primary').prop('disabled', false).html('บันทึก');
                showNotification(response.message, 'danger');
            }
        },
        error: function() {
            $('#userModal .btn-primary').prop('disabled', false).html('บันทึก');
            showNotification('เกิดข้อผิดพลาดในการบันทึกข้อมูล', 'danger');
        }
    });
}

function deleteUser(id) {
    if (confirm('คุณต้องการลบผู้ใช้งานนี้ใช่หรือไม่? การดำเนินการนี้ไม่สามารถกู้คืนได้')) {
        $.ajax({
            url: 'ajax/delete_user.php',
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

function checkUsername() {
    const username = $('#username').val();
    const id = $('#userId').val();
    
    if (username) {
        $.ajax({
            url: 'ajax/check_username.php',
            method: 'POST',
            data: { username: username, id: id },
            dataType: 'json',
            success: function(response) {
                if (response.exists) {
                    $('#username').addClass('is-invalid');
                    if (!$('#username').next('.invalid-feedback').length) {
                        $('#username').after('<div class="invalid-feedback">ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว</div>');
                    }
                } else {
                    $('#username').removeClass('is-invalid');
                    $('.invalid-feedback').remove();
                }
            }
        });
    }
}

function checkEmail() {
    const email = $('#email').val();
    const id = $('#userId').val();
    
    if (email) {
        $.ajax({
            url: 'ajax/check_email.php',
            method: 'POST',
            data: { email: email, id: id },
            dataType: 'json',
            success: function(response) {
                if (response.exists) {
                    $('#email').addClass('is-invalid');
                    if (!$('#email').next('.invalid-feedback').length) {
                        $('#email').after('<div class="invalid-feedback">อีเมลนี้มีอยู่ในระบบแล้ว</div>');
                    }
                } else {
                    $('#email').removeClass('is-invalid');
                    $('.invalid-feedback').remove();
                }
            }
        });
    }
}

function validatePassword() {
    const password = $('#password').val();
    const confirm = $('#confirmPassword').val();
    
    if (password || confirm) {
        if (password !== confirm) {
            $('#confirmPassword').addClass('is-invalid');
            if (!$('#confirmPassword').next('.invalid-feedback').length) {
                $('#confirmPassword').after('<div class="invalid-feedback">รหัสผ่านไม่ตรงกัน</div>');
            }
        } else {
            $('#confirmPassword').removeClass('is-invalid');
            $('.invalid-feedback').remove();
        }
    }
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../includes/footer.php';
?>