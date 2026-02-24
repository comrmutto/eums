<?php
/**
 * Header Template
 * Engineering Utility Monitoring System (EUMS)
 */

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) != 'login.php') {
    header('Location: /eums/login.php');
    exit();
}

// Load configuration and functions
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';

$appName = config('app.name');
$appVersion = config('app.version');
$currentUser = $_SESSION['username'] ?? 'Guest';
$userRole = $_SESSION['user_role'] ?? 'viewer';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    
    <title><?php echo $appName; ?> - <?php echo $pageTitle ?? 'Dashboard'; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/eums/assets/images/favicon.png">
    
    <!-- Google Font: Sarabun -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Theme style (AdminLTE) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    
    <!-- DatePicker CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    
    <!-- Toastr CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/eums/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tempusdominus-bootstrap-4/5.39.0/css/tempusdominus-bootstrap-4.min.css" />
    <!-- Module specific CSS -->
    <?php if (isset($module_css)): ?>
        <?php foreach ($module_css as $css): ?>
            <link rel="stylesheet" href="/eums/assets/css/modules/<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Custom inline styles -->
    <style>
        .content-wrapper {
            background-color: #f4f6f9;
            min-height: calc(100vh - 57px);
        }
        .main-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            /* สำคัญ: ต้องไม่มี overflow:hidden เพื่อให้ dropdown แสดงได้ */
            overflow: visible !important;
        }
        .main-header .navbar {
            overflow: visible !important;
        }
        .navbar-nav .nav-link {
            color: rgba(255,255,255,0.8) !important;
        }
        .navbar-nav .nav-link:hover {
            color: white !important;
        }

        /* ===== User Dropdown Menu ===== */
        .user-dropdown-menu {
            /* ให้ dropdown ลอยออกมาถูกต้องแม้ navbar มี overflow */
            position: absolute !important;
            z-index: 9999 !important;
            right: 0 !important;
            left: auto !important;
            top: 100% !important;
            margin-top: 4px !important;
            border: none !important;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18) !important;
            border-radius: 12px !important;
            overflow: hidden !important;
            padding: 0 !important;
            background: #fff !important;
        }
        .user-dropdown-menu .dropdown-item {
            color: #333 !important;
        }
        .user-dropdown-menu .dropdown-item:hover {
            background-color: #f0f2ff !important;
            color: #667eea !important;
        }

        /* ===== Notification Dropdown ===== */
        .nav-item.dropdown .dropdown-menu {
            z-index: 9999 !important;
            background-color: #fff !important;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15) !important;
            border: none !important;
            border-radius: 12px !important;
        }
        .nav-item.dropdown .dropdown-menu .dropdown-item {
            color: #333 !important;
        }
        .nav-item.dropdown .dropdown-menu .dropdown-item:hover {
            background-color: #f8f9fa !important;
        }

        /* แก้ปัญหา Datetimepicker (BS4) แสดงผลไม่เต็มกรอบใน Bootstrap 5 */
        .bootstrap-datetimepicker-widget.dropdown-menu {
            width: auto !important;
            min-width: 330px !important;
            padding: 10px !important;
        }
        .bootstrap-datetimepicker-widget.dropdown-menu table {
            width: 100% !important;
            margin: 0 !important;
        }
        .bootstrap-datetimepicker-widget.dropdown-menu table td,
        .bootstrap-datetimepicker-widget.dropdown-menu table th {
            border-radius: 0;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">
    
    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="/eums/index.php" class="nav-link">หน้าหลัก</a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="#" class="nav-link" id="refreshData">
                    <i class="fas fa-sync-alt"></i> รีเฟรช
                </a>
            </li>
        </ul>

        <!-- Right navbar links -->
    
        <ul class="navbar-nav ml-auto">
            <!-- Notifications Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">
                    <i class="far fa-bell"></i>
                    <span class="badge badge-warning navbar-badge" id="notificationCount">0</span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <span class="dropdown-item dropdown-header">การแจ้งเตือน</span>
                    <div class="dropdown-divider"></div>
                    <div id="notificationList">
                        <a href="#" class="dropdown-item">
                            <i class="fas fa-info-circle mr-2"></i> ไม่มีการแจ้งเตือน
                        </a>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item dropdown-footer">ดูทั้งหมด</a>
                </div>
            </li>
            
            <!-- User Menu -->
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" data-bs-offset="0,4" role="button" aria-expanded="false">
                    <i class="fas fa-user-circle fa-2x"></i>
                    <span class="d-none d-md-inline"><?php echo htmlspecialchars($currentUser); ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-end user-dropdown-menu" style="min-width:260px; right:0; left:auto;">
                    <!-- User Header -->
                    <div class="user-header" style="background: linear-gradient(135deg,#667eea,#764ba2); padding:15px; text-align:center; color:#fff;">
                        <i class="fas fa-user-circle fa-4x mb-2"></i>
                        <p class="mb-0 fw-bold"><?php echo htmlspecialchars($currentUser); ?></p>
                        <small style="opacity:.8;">สมาชิกตั้งแต่: <?php echo date('d/m/Y'); ?></small>
                    </div>
                    <!-- Quick Links -->
                    <div class="d-flex border-bottom">
                        <a href="/eums/settings/profile.php" class="dropdown-item text-center border-end py-2" style="font-size:.85rem;">
                            <i class="fas fa-user d-block mb-1"></i>โปรไฟล์
                        </a>
                        <a href="/eums/settings/users.php" class="dropdown-item text-center border-end py-2" style="font-size:.85rem;">
                            <i class="fas fa-cog d-block mb-1"></i>ตั้งค่า
                        </a>
                        <a href="/eums/reports/daily.php" class="dropdown-item text-center py-2" style="font-size:.85rem;">
                            <i class="fas fa-chart-bar d-block mb-1"></i>รายงาน
                        </a>
                    </div>
                    <!-- Footer -->
                    <div class="d-flex justify-content-between align-items-center px-3 py-2" style="background:#f8f9fa;">
                        <a href="/eums/settings/profile.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-user me-1"></i>โปรไฟล์
                        </a>
                        <a href="/eums/logout.php" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-sign-out-alt me-1"></i>ออกจากระบบ
                        </a>
                    </div>
                </div>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->
    
    <!-- Main Sidebar Container -->
    <?php require_once 'sidebar.php'; ?>
    
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><?php echo $pageTitle ?? 'Dashboard'; ?></h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/eums/index.php">หน้าหลัก</a></li>
                            <?php if (isset($breadcrumb)): ?>
                                <?php foreach ($breadcrumb as $item): ?>
                                    <?php if (isset($item['link'])): ?>
                                        <li class="breadcrumb-item"><a href="<?php echo $item['link']; ?>"><?php echo $item['title']; ?></a></li>
                                    <?php else: ?>
                                        <li class="breadcrumb-item active"><?php echo $item['title']; ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <!-- Notification Container -->
                <div id="notificationContainer"></div>
                
                <!-- Loading Overlay -->
                <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); z-index: 9999;">
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                        <div class="spinner-custom"></div>
                        <p class="mt-3">กำลังโหลด...</p>
                    </div>
                </div>