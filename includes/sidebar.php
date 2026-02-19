<?php
/**
 * Sidebar Template
 * Engineering Utility Monitoring System (EUMS)
 */

// Get current module from URL
$current_page = basename($_SERVER['PHP_SELF']);
$current_module = isset($_GET['module']) ? $_GET['module'] : '';
?>

<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="/eums/index.php" class="brand-link">
        <img src="/eums/assets/images/logo.png" alt="EUMS Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
        <span class="brand-text font-weight-light"><?php echo config('app.short_name'); ?></span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel (optional) -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <i class="fas fa-user-circle fa-2x img-circle elevation-2"></i>
            </div>
            <div class="info">
                <a href="#" class="d-block"><?php echo $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'ผู้ใช้งาน'; ?></a>
                <small class="text-white">
                    <i class="fas fa-circle text-success"></i> Online
                </small>
            </div>
        </div>

        <!-- SidebarSearch -->
        <div class="form-inline">
            <div class="input-group" data-widget="sidebar-search">
                <input class="form-control form-control-sidebar" type="search" placeholder="ค้นหา..." aria-label="Search">
                <div class="input-group-append">
                    <button class="btn btn-sidebar">
                        <i class="fas fa-search fa-fw"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="/eums/index.php" class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>แดชบอร์ด</p>
                    </a>
                </li>
                
                <!-- Air Compressor Module -->
                <li class="nav-item has-treeview <?php echo ($current_module == 'air') ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-compress"></i>
                        <p>
                            Air Compressor
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="/eums/modules/air-compressor/index.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>บันทึกข้อมูล</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/eums/modules/air-compressor/machines.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>จัดการเครื่องจักร</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/eums/modules/air-compressor/settings.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>ตั้งค่าการตรวจสอบ</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/eums/modules/air-compressor/reports.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>รายงาน</p>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Energy & Water Module -->
                <li class="nav-item has-treeview <?php echo ($current_module == 'energy') ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-tint"></i>
                        <p>
                            Energy & Water
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="/eums/modules/energy-water/index.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>บันทึกข้อมูล</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/eums/modules/energy-water/meters.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>จัดการมิเตอร์</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/eums/modules/energy-water/reports.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>รายงาน</p>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- LPG Module -->
                <li class="nav-item has-treeview <?php echo ($current_module == 'lpg') ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-fire"></i>
                        <p>
                            LPG
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="/eums/modules/lpg/index.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>บันทึกข้อมูล</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/eums/modules/lpg/settings.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>ตั้งค่าหัวข้อตรวจสอบ</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/eums/modules/lpg/reports.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>รายงาน</p>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Boiler Module -->
                <li class="nav-item has-treeview <?php echo ($current_module == 'boiler') ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-industry"></i>
                        <p>
                            Boiler
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="/eums/modules/boiler/index.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>บันทึกข้อมูล</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/eums/modules/boiler/machines.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>จัดการเครื่องจักร</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/eums/modules/boiler/reports.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>รายงาน</p>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Summary Electricity Module -->
                <li class="nav-item has-treeview <?php echo ($current_module == 'summary') ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-chart-line"></i>
                        <p>
                            Summary Electricity
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="/eums/modules/summary-electricity/index.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>บันทึกข้อมูล</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/eums/modules/summary-electricity/reports.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>รายงานสรุป</p>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Reports Section -->
                <li class="nav-header">รายงาน</li>
                
                <li class="nav-item">
                    <a href="/eums/reports/daily.php" class="nav-link">
                        <i class="nav-icon fas fa-calendar-day"></i>
                        <p>รายงานประจำวัน</p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="/eums/reports/monthly.php" class="nav-link">
                        <i class="nav-icon fas fa-calendar-alt"></i>
                        <p>รายงานประจำเดือน</p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="/eums/reports/yearly.php" class="nav-link">
                        <i class="nav-icon fas fa-calendar"></i>
                        <p>รายงานประจำปี</p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="/eums/reports/comparison.php" class="nav-link">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <p>รายงานเปรียบเทียบ</p>
                    </a>
                </li>
                
                <!-- Settings -->
                <li class="nav-header">ตั้งค่า</li>
                
                <li class="nav-item">
                    <a href="/eums/settings/users.php" class="nav-link">
                        <i class="nav-icon fas fa-users"></i>
                        <p>จัดการผู้ใช้งาน</p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="/eums/settings/documents.php" class="nav-link">
                        <i class="nav-icon fas fa-file-alt"></i>
                        <p>จัดการเอกสาร</p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="/eums/settings/backup.php" class="nav-link">
                        <i class="nav-icon fas fa-database"></i>
                        <p>สำรองข้อมูล</p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="/eums/settings/profile.php" class="nav-link">
                        <i class="nav-icon fas fa-user-cog"></i>
                        <p>ตั้งค่าโปรไฟล์</p>
                    </a>
                </li>
                
                <!-- Help -->
                <li class="nav-header">ช่วยเหลือ</li>
                
                <li class="nav-item">
                    <a href="/eums/help/manual.php" class="nav-link">
                        <i class="nav-icon fas fa-book"></i>
                        <p>คู่มือการใช้งาน</p>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="/eums/help/about.php" class="nav-link">
                        <i class="nav-icon fas fa-info-circle"></i>
                        <p>เกี่ยวกับระบบ</p>
                    </a>
                </li>
                
                <!-- Logout -->
                <li class="nav-item">
                    <a href="/eums/logout.php" class="nav-link text-danger">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p>ออกจากระบบ</p>
                    </a>
                </li>
                
            </ul>
        </nav>
    </div>
</aside>