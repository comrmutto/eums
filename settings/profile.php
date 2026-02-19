<?php
/**
 * User Profile
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
$pageTitle = 'โปรไฟล์ของฉัน';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'ตั้งค่า', 'link' => '#'],
    ['title' => 'โปรไฟล์', 'link' => null]
];

// Include header
require_once __DIR__ . '/../includes/header.php';

// Load required files
require_once __DIR__ . '/../includes/functions.php';

// Get database connection
$db = getDB();

// Get user data
$stmt = $db->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM activity_logs WHERE user_id = u.id) as login_count,
           (SELECT MAX(created_at) FROM activity_logs WHERE user_id = u.id) as last_activity
    FROM users u
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get recent activities
$stmt = $db->prepare("
    SELECT * FROM activity_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 20
");
$stmt->execute([$_SESSION['user_id']]);
$activities = $stmt->fetchAll();

// Get login history
$stmt = $db->prepare("
    SELECT * FROM login_attempts 
    WHERE username = ? 
    ORDER BY attempt_time DESC 
    LIMIT 10
");
$stmt->execute([$user['username']]);
$loginAttempts = $stmt->fetchAll();
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <div class="row">
            <div class="col-md-3">
                <!-- Profile Image -->
                <div class="card card-primary card-outline">
                    <div class="card-body box-profile">
                        <div class="text-center">
                            <i class="fas fa-user-circle fa-5x text-primary"></i>
                        </div>
                        
                        <h3 class="profile-username text-center"><?php echo htmlspecialchars($user['fullname']); ?></h3>
                        
                        <p class="text-muted text-center">
                            <span class="badge badge-<?php 
                                echo $user['role'] == 'admin' ? 'danger' : 
                                    ($user['role'] == 'operator' ? 'warning' : 'secondary'); 
                            ?>">
                                <?php 
                                echo $user['role'] == 'admin' ? 'ผู้ดูแลระบบ' : 
                                    ($user['role'] == 'operator' ? 'ผู้ปฏิบัติงาน' : 'ผู้ดู'); 
                                ?>
                            </span>
                        </p>
                        
                        <ul class="list-group list-group-unbordered mb-3">
                            <li class="list-group-item">
                                <b>ชื่อผู้ใช้</b> <a class="float-right"><?php echo htmlspecialchars($user['username']); ?></a>
                            </li>
                            <li class="list-group-item">
                                <b>อีเมล</b> <a class="float-right"><?php echo htmlspecialchars($user['email']); ?></a>
                            </li>
                            <li class="list-group-item">
                                <b>สมาชิกตั้งแต่</b> <a class="float-right"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></a>
                            </li>
                            <li class="list-group-item">
                                <b>เข้าสู่ระบบล่าสุด</b> 
                                <a class="float-right">
                                    <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '-'; ?>
                                </a>
                            </li>
                        </ul>
                        
                        <button type="button" class="btn btn-primary btn-block" onclick="showEditProfileModal()">
                            <i class="fas fa-edit"></i> แก้ไขโปรไฟล์
                        </button>
                        <button type="button" class="btn btn-warning btn-block" onclick="showChangePasswordModal()">
                            <i class="fas fa-key"></i> เปลี่ยนรหัสผ่าน
                        </button>
                    </div>
                </div>
                
                <!-- Account Status -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-shield-alt"></i>
                            สถานะบัญชี
                        </h3>
                    </div>
                    <div class="card-body">
                        <p>
                            <strong>สถานะ:</strong> 
                            <span class="badge badge-<?php echo $user['status'] ? 'success' : 'danger'; ?>">
                                <?php echo $user['status'] ? 'ปกติ' : 'ถูกระงับ'; ?>
                            </span>
                        </p>
                        <p>
                            <strong>จำนวนครั้งที่เข้าใช้:</strong> 
                            <span class="badge badge-info"><?php echo $user['login_count']; ?> ครั้ง</span>
                        </p>
                        <p>
                            <strong>ความแรงของรหัสผ่าน:</strong><br>
                            <?php 
                            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
                            $stmt->execute([$_SESSION['user_id']]);
                            $hash = $stmt->fetchColumn();
                            
                            // Simple password strength indicator
                            $strength = 'ปานกลาง';
                            $color = 'warning';
                            if (strlen($hash) > 60) {
                                $strength = 'แข็งแรง';
                                $color = 'success';
                            }
                            ?>
                            <span class="badge badge-<?php echo $color; ?>"><?php echo $strength; ?></span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header p-2">
                        <ul class="nav nav-pills">
                            <li class="nav-item"><a class="nav-link active" href="#activity" data-toggle="tab">กิจกรรมล่าสุด</a></li>
                            <li class="nav-item"><a class="nav-link" href="#login_history" data-toggle="tab">ประวัติการเข้าใช้</a></li>
                            <li class="nav-item"><a class="nav-link" href="#settings" data-toggle="tab">ตั้งค่าเพิ่มเติม</a></li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Activity Tab -->
                            <div class="tab-pane active" id="activity">
                                <div class="timeline">
                                    <?php 
                                    $lastDate = '';
                                    foreach ($activities as $activity): 
                                        $date = date('Y-m-d', strtotime($activity['created_at']));
                                        if ($date != $lastDate):
                                            $lastDate = $date;
                                    ?>
                                    <div class="time-label">
                                        <span class="bg-primary"><?php echo date('d/m/Y', strtotime($activity['created_at'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <i class="fas fa-<?php 
                                            echo strpos($activity['action'], 'add') !== false ? 'plus-circle bg-success' : 
                                                (strpos($activity['action'], 'edit') !== false ? 'edit bg-warning' : 
                                                (strpos($activity['action'], 'delete') !== false ? 'trash bg-danger' : 
                                                (strpos($activity['action'], 'login') !== false ? 'sign-in-alt bg-info' : 'circle bg-gray'))); 
                                        ?>"></i>
                                        <div class="timeline-item">
                                            <span class="time"><i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($activity['created_at'])); ?></span>
                                            <h3 class="timeline-header">
                                                <?php 
                                                echo ucfirst(str_replace('_', ' ', $activity['action']));
                                                if ($activity['details']) {
                                                    echo '<small> - ' . htmlspecialchars($activity['details']) . '</small>';
                                                }
                                                ?>
                                            </h3>
                                            <div class="timeline-body">
                                                <small class="text-muted">IP: <?php echo $activity['ip_address'] ?: 'ไม่ทราบ'; ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($activities)): ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-history fa-2x"></i>
                                        <p>ไม่มีกิจกรรม</p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <i class="fas fa-clock bg-gray"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Login History Tab -->
                            <div class="tab-pane" id="login_history">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>เวลา</th>
                                                <th>IP Address</th>
                                                <th>สถานะ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($loginAttempts as $attempt): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y H:i:s', strtotime($attempt['attempt_time'])); ?></td>
                                                <td><?php echo $attempt['ip_address'] ?: '-'; ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $attempt['username'] == $user['username'] ? 'success' : 'danger'; ?>">
                                                        <?php echo $attempt['username'] == $user['username'] ? 'สำเร็จ' : 'ล้มเหลว'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            
                                            <?php if (empty($loginAttempts)): ?>
                                            <tr>
                                                <td colspan="3" class="text-center">ไม่มีประวัติการเข้าใช้</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Settings Tab -->
                            <div class="tab-pane" id="settings">
                                <form class="form-horizontal" id="settingsForm">
                                    <div class="form-group row">
                                        <label for="notificationEmail" class="col-sm-3 col-form-label">รับการแจ้งเตือนทางอีเมล</label>
                                        <div class="col-sm-9">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="notificationEmail" checked>
                                                <label class="custom-control-label" for="notificationEmail">เปิดใช้งาน</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group row">
                                        <label for="language" class="col-sm-3 col-form-label">ภาษา</label>
                                        <div class="col-sm-9">
                                            <select class="form-control" id="language">
                                                <option value="th" selected>ไทย</option>
                                                <option value="en">English</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group row">
                                        <label for="timezone" class="col-sm-3 col-form-label">เขตเวลา</label>
                                        <div class="col-sm-9">
                                            <select class="form-control" id="timezone">
                                                <option value="Asia/Bangkok" selected>Asia/Bangkok (GMT+7)</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group row">
                                        <label for="itemsPerPage" class="col-sm-3 col-form-label">รายการต่อหน้า</label>
                                        <div class="col-sm-9">
                                            <select class="form-control" id="itemsPerPage">
                                                <option value="10">10</option>
                                                <option value="25" selected>25</option>
                                                <option value="50">50</option>
                                                <option value="100">100</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group row">
                                        <label for="theme" class="col-sm-3 col-form-label">ธีม</label>
                                        <div class="col-sm-9">
                                            <select class="form-control" id="theme">
                                                <option value="light" selected>สว่าง</option>
                                                <option value="dark">มืด</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group row">
                                        <div class="offset-sm-3 col-sm-9">
                                            <button type="button" class="btn btn-primary" onclick="saveSettings()">
                                                <i class="fas fa-save"></i> บันทึกการตั้งค่า
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">แก้ไขโปรไฟล์</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="editProfileForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label>ชื่อ-นามสกุล</label>
                        <input type="text" class="form-control" name="fullname" 
                               value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>อีเมล</label>
                        <input type="email" class="form-control" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
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

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">เปลี่ยนรหัสผ่าน</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="changePasswordForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label>รหัสผ่านเดิม</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>รหัสผ่านใหม่</label>
                        <input type="password" class="form-control" name="new_password" id="newPassword" 
                               minlength="<?php echo config('security.password_min_length', 8); ?>" required>
                        <small class="text-muted">อย่างน้อย <?php echo config('security.password_min_length', 8); ?> ตัวอักษร</small>
                    </div>
                    <div class="form-group">
                        <label>ยืนยันรหัสผ่านใหม่</label>
                        <input type="password" class="form-control" name="confirm_password" id="confirmNewPassword" required>
                    </div>
                    <div id="passwordStrength" class="mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning">เปลี่ยนรหัสผ่าน</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#editProfileForm').on('submit', function(e) {
        e.preventDefault();
        saveProfile();
    });
    
    $('#changePasswordForm').on('submit', function(e) {
        e.preventDefault();
        changePassword();
    });
    
    $('#newPassword, #confirmNewPassword').on('input', function() {
        validatePassword();
        checkPasswordStrength();
    });
});

function showEditProfileModal() {
    $('#editProfileModal').modal('show');
}

function showChangePasswordModal() {
    $('#changePasswordModal').modal('show');
}

function saveProfile() {
    const formData = $('#editProfileForm').serialize();
    
    $.ajax({
        url: 'ajax/update_profile.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        beforeSend: function() {
            $('#editProfileModal .btn-primary').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...');
        },
        success: function(response) {
            if (response.success) {
                $('#editProfileModal').modal('hide');
                showNotification('อัปเดตโปรไฟล์เรียบร้อย', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                $('#editProfileModal .btn-primary').prop('disabled', false).html('บันทึก');
                showNotification(response.message, 'danger');
            }
        },
        error: function() {
            $('#editProfileModal .btn-primary').prop('disabled', false).html('บันทึก');
            showNotification('เกิดข้อผิดพลาด', 'danger');
        }
    });
}

function changePassword() {
    const current = $('input[name=current_password]').val();
    const newPass = $('#newPassword').val();
    const confirm = $('#confirmNewPassword').val();
    
    if (!current || !newPass || !confirm) {
        showNotification('กรุณากรอกข้อมูลให้ครบถ้วน', 'warning');
        return;
    }
    
    if (newPass !== confirm) {
        showNotification('รหัสผ่านใหม่ไม่ตรงกัน', 'warning');
        return;
    }
    
    if (newPass.length < <?php echo config('security.password_min_length', 8); ?>) {
        showNotification('รหัสผ่านต้องมีอย่างน้อย <?php echo config('security.password_min_length', 8); ?> ตัวอักษร', 'warning');
        return;
    }
    
    const formData = $('#changePasswordForm').serialize();
    
    $.ajax({
        url: 'ajax/change_password.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        beforeSend: function() {
            $('#changePasswordModal .btn-warning').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> กำลังดำเนินการ...');
        },
        success: function(response) {
            if (response.success) {
                $('#changePasswordModal').modal('hide');
                showNotification('เปลี่ยนรหัสผ่านเรียบร้อย', 'success');
                $('#changePasswordForm')[0].reset();
            } else {
                $('#changePasswordModal .btn-warning').prop('disabled', false).html('เปลี่ยนรหัสผ่าน');
                showNotification(response.message, 'danger');
            }
        },
        error: function() {
            $('#changePasswordModal .btn-warning').prop('disabled', false).html('เปลี่ยนรหัสผ่าน');
            showNotification('เกิดข้อผิดพลาด', 'danger');
        }
    });
}

function validatePassword() {
    const newPass = $('#newPassword').val();
    const confirm = $('#confirmNewPassword').val();
    
    if (confirm) {
        if (newPass !== confirm) {
            $('#confirmNewPassword').addClass('is-invalid');
            if (!$('#confirmNewPassword').next('.invalid-feedback').length) {
                $('#confirmNewPassword').after('<div class="invalid-feedback">รหัสผ่านไม่ตรงกัน</div>');
            }
        } else {
            $('#confirmNewPassword').removeClass('is-invalid');
            $('.invalid-feedback').remove();
        }
    }
}

function checkPasswordStrength() {
    const password = $('#newPassword').val();
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]+/)) strength++;
    if (password.match(/[A-Z]+/)) strength++;
    if (password.match(/[0-9]+/)) strength++;
    if (password.match(/[$@#&!]+/)) strength++;
    
    let strengthText = '';
    let strengthClass = '';
    
    switch(strength) {
        case 0:
        case 1:
            strengthText = 'อ่อน';
            strengthClass = 'danger';
            break;
        case 2:
        case 3:
            strengthText = 'ปานกลาง';
            strengthClass = 'warning';
            break;
        case 4:
        case 5:
            strengthText = 'แข็งแรง';
            strengthClass = 'success';
            break;
    }
    
    $('#passwordStrength').html(
        '<div class="progress">' +
        '<div class="progress-bar bg-' + strengthClass + '" style="width: ' + (strength * 20) + '%"></div>' +
        '</div>' +
        '<small class="text-' + strengthClass + '">ความแข็งแรง: ' + strengthText + '</small>'
    );
}

function saveSettings() {
    const settings = {
        notification: $('#notificationEmail').is(':checked'),
        language: $('#language').val(),
        timezone: $('#timezone').val(),
        itemsPerPage: $('#itemsPerPage').val(),
        theme: $('#theme').val()
    };
    
    $.ajax({
        url: 'ajax/save_user_settings.php',
        method: 'POST',
        data: settings,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('บันทึกการตั้งค่าเรียบร้อย', 'success');
            } else {
                showNotification(response.message, 'danger');
            }
        },
        error: function() {
            showNotification('เกิดข้อผิดพลาด', 'danger');
        }
    });
}
</script>

<?php
// Include footer
require_once __DIR__ . '/../includes/footer.php';
?>