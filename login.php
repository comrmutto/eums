<?php
/**
 * Login Page
 * Engineering Utility Monitoring System (EUMS)
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Load configuration and functions
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php'; // เรียกครั้งเดียวพอ

$appName = config('app.name');
$appVersion = config('app.version');

// Check for remember me cookie
if (isset($_COOKIE['remember_token']) && !isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/includes/auth_functions.php';
    $result = loginWithToken($_COOKIE['remember_token']);
    if ($result['success']) {
        header('Location: index.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $appName; ?> - เข้าสู่ระบบ</title>
    
    <!-- Google Font: Sarabun -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- AdminLTE -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-box {
            width: 400px;
            max-width: 90%;
        }
        
        .login-card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
            border: none;
        }
        
        .login-card .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 30px 20px;
            border-bottom: none;
        }
        
        .login-card .card-header h3 {
            margin: 10px 0 0;
            font-weight: 500;
        }
        
        .login-card .card-header img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            padding: 10px;
        }
        
        .login-card .card-body {
            padding: 40px;
            background: white;
        }
        
        .login-card .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .login-card .form-group i {
            position: absolute;
            left: 15px;
            top: 38px;
            color: #6c757d;
        }
        
        .login-card .form-control {
            padding-left: 45px;
            height: 50px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        
        .login-card .form-control:focus {
            border-color: #667eea;
            box-shadow: none;
        }
        
        .login-card .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            height: 50px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            width: 100%;
        }
        
        .login-card .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .login-card .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .login-card .remember {
            display: flex;
            align-items: center;
        }
        
        .login-card .remember input {
            margin-right: 5px;
        }
        
        .login-card .forgot a {
            color: #667eea;
            text-decoration: none;
        }
        
        .login-card .forgot a:hover {
            text-decoration: underline;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: white;
        }
        
        .login-footer a {
            color: white;
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="card login-card">
            <div class="card-header">
                <img src="assets/images/logo.png" alt="EUMS Logo" onerror="this.src='https://via.placeholder.com/80?text=EUMS'">
                <h3><?php echo $appName; ?></h3>
                <p>ระบบติดตามและบันทึกข้อมูลสาธารณูปโภค</p>
            </div>
            <div class="card-body">
                <!-- Alert Messages -->
                <div id="alertContainer"></div>
                
                <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <form id="loginForm" method="POST" action="authenticate.php">
                    <div class="form-group">
                        <label for="username">ชื่อผู้ใช้</label>
                        <i class="fas fa-user"></i>
                        <input type="text" class="form-control" id="username" name="username" 
                               placeholder="กรอกชื่อผู้ใช้" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">รหัสผ่าน</label>
                        <i class="fas fa-lock"></i>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="กรอกรหัสผ่าน" required>
                    </div>
                    
                    <div class="remember-forgot">
                        <div class="remember">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">จดจำฉัน</label>
                        </div>
                        <div class="forgot">
                            <a href="forgot-password.php">ลืมรหัสผ่าน?</a>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-login" id="loginBtn">
                        <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
                    </button>
                </form>
                
                <hr>
                
                <div class="text-center text-muted">
                    <small>เวอร์ชัน <?php echo $appVersion; ?></small>
                </div>
            </div>
        </div>
        
        <div class="login-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo $appName; ?>. สงวนลิขสิทธิ์</p>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        $('#loginForm').on('submit', function(e) {
            e.preventDefault();
            
            const username = $('#username').val().trim();
            const password = $('#password').val();
            
            if (!username || !password) {
                showAlert('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน', 'danger');
                return;
            }
            
            $('#loginBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> กำลังตรวจสอบ...');
            
            $.ajax({
                url: 'authenticate.php',
                method: 'POST',
                data: {
                    username: username,
                    password: password,
                    remember: $('#remember').is(':checked') ? 1 : 0,
                    ajax: 1
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert(response.message, 'success');
                        setTimeout(function() {
                            window.location.href = response.redirect || 'index.php';
                        }, 1500);
                    } else {
                        $('#loginBtn').prop('disabled', false).html('<i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ');
                        showAlert(response.message, 'danger');
                    }
                },
                error: function(xhr) {
                    $('#loginBtn').prop('disabled', false).html('<i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ');
                    
                    let message = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    showAlert(message, 'danger');
                }
            });
        });
        
        function showAlert(message, type) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $('#alertContainer').html(alertHtml);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        }
    });
    </script>
</body>
</html>