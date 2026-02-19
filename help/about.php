<?php
/**
 * About Page
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
$pageTitle = 'เกี่ยวกับระบบ';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'ช่วยเหลือ', 'link' => '#'],
    ['title' => 'เกี่ยวกับระบบ', 'link' => null]
];

// Include header
require_once __DIR__ . '/../includes/header.php';

// Load required files
require_once __DIR__ . '/../includes/functions.php';

// Get system information
$db = getDB();

// Database size
$stmt = $db->query("
    SELECT 
        SUM(data_length + index_length) as total_size,
        COUNT(*) as table_count
    FROM information_schema.tables 
    WHERE table_schema = DATABASE()
");
$dbStats = $stmt->fetch();

// Record counts
$recordCounts = [
    'air' => $db->query("SELECT COUNT(*) FROM air_daily_records")->fetchColumn(),
    'energy' => $db->query("SELECT COUNT(*) FROM meter_daily_readings")->fetchColumn(),
    'lpg' => $db->query("SELECT COUNT(*) FROM lpg_daily_records")->fetchColumn(),
    'boiler' => $db->query("SELECT COUNT(*) FROM boiler_daily_records")->fetchColumn(),
    'summary' => $db->query("SELECT COUNT(*) FROM electricity_summary")->fetchColumn()
];

// User count
$userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Last update
$lastUpdate = $db->query("SELECT MAX(created_at) FROM (
    SELECT created_at FROM air_daily_records UNION
    SELECT created_at FROM meter_daily_readings UNION
    SELECT created_at FROM lpg_daily_records UNION
    SELECT created_at FROM boiler_daily_records UNION
    SELECT created_at FROM electricity_summary
) as updates")->fetchColumn();

// PHP Info
$phpVersion = phpversion();
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$dbDriver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
$dbVersion = $db->getAttribute(PDO::ATTR_SERVER_VERSION);
?>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- System Overview -->
        <div class="row">
            <div class="col-md-4">
                <div class="card card-primary card-outline">
                    <div class="card-body box-profile">
                        <div class="text-center">
                            <i class="fas fa-cogs fa-5x text-primary"></i>
                        </div>
                        
                        <h3 class="profile-username text-center">EUMS</h3>
                        
                        <p class="text-muted text-center">
                            Engineering Utility Monitoring System
                        </p>
                        
                        <ul class="list-group list-group-unbordered mb-3">
                            <li class="list-group-item">
                                <b>เวอร์ชัน</b> <a class="float-right"><?php echo config('app.version', '1.0.0'); ?></a>
                            </li>
                            <li class="list-group-item">
                                <b>ผู้พัฒนา</b> <a class="float-right">EUMS Team</a>
                            </li>
                            <li class="list-group-item">
                                <b>ลิขสิทธิ์</b> <a class="float-right">© <?php echo date('Y'); ?></a>
                            </li>
                            <li class="list-group-item">
                                <b>อัปเดตล่าสุด</b> 
                                <a class="float-right">
                                    <?php echo $lastUpdate ? date('d/m/Y H:i', strtotime($lastUpdate)) : '-'; ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-pie"></i>
                            สถิติอย่างรวดเร็ว
                        </h3>
                    </div>
                    <div class="card-body">
                        <canvas id="statsChart" style="height: 200px;"></canvas>
                        
                        <table class="table table-sm mt-3">
                            <tr>
                                <td><i class="fas fa-compress text-info"></i> Air Compressor</td>
                                <td class="text-right"><?php echo number_format($recordCounts['air']); ?></td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-bolt text-warning"></i> Energy & Water</td>
                                <td class="text-right"><?php echo number_format($recordCounts['energy']); ?></td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-fire text-danger"></i> LPG</td>
                                <td class="text-right"><?php echo number_format($recordCounts['lpg']); ?></td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-industry text-secondary"></i> Boiler</td>
                                <td class="text-right"><?php echo number_format($recordCounts['boiler']); ?></td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-chart-line text-success"></i> Summary</td>
                                <td class="text-right"><?php echo number_format($recordCounts['summary']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <!-- About System -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="card-title">
                            <i class="fas fa-info-circle"></i>
                            เกี่ยวกับระบบ
                        </h3>
                    </div>
                    <div class="card-body">
                        <p>
                            <strong>Engineering Utility Monitoring System (EUMS)</strong> เป็นระบบที่พัฒนาขึ้นเพื่อใช้ในการติดตาม 
                            และบันทึกข้อมูลสาธารณูปโภคต่างๆ ภายในโรงงาน เช่น ระบบอัดอากาศ (Air Compressor), 
                            ระบบไฟฟ้าและน้ำ (Energy & Water), ระบบ LPG, ระบบ Boiler และสรุปการใช้ไฟฟ้า 
                            (Summary Electricity)
                        </p>
                        
                        <h5 class="mt-4">วัตถุประสงค์ของระบบ</h5>
                        <ul>
                            <li>เพื่อบันทึกข้อมูลการใช้งานสาธารณูปโภคต่างๆ อย่างเป็นระบบ</li>
                            <li>เพื่อติดตามและตรวจสอบค่าต่างๆ ให้อยู่ในเกณฑ์มาตรฐาน</li>
                            <li>เพื่อวิเคราะห์แนวโน้มการใช้งานและวางแผนการจัดการ</li>
                            <li>เพื่อสร้างรายงานสรุปสำหรับผู้บริหารและผู้ปฏิบัติงาน</li>
                            <li>เพื่อเป็นฐานข้อมูลสำหรับการปรับปรุงประสิทธิภาพการใช้พลังงาน</li>
                        </ul>
                        
                        <h5 class="mt-4">คุณสมบัติหลัก</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <ul>
                                    <li><i class="fas fa-check-circle text-success"></i> บันทึกข้อมูล Air Compressor</li>
                                    <li><i class="fas fa-check-circle text-success"></i> บันทึกค่ามิเตอร์ไฟฟ้าและน้ำ</li>
                                    <li><i class="fas fa-check-circle text-success"></i> บันทึกข้อมูล LPG (ตัวเลข/OK-NG)</li>
                                    <li><i class="fas fa-check-circle text-success"></i> บันทึกข้อมูล Boiler</li>
                                    <li><i class="fas fa-check-circle text-success"></i> บันทึกสรุปการใช้ไฟฟ้า</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul>
                                    <li><i class="fas fa-check-circle text-success"></i> จัดการเครื่องจักรและมิเตอร์</li>
                                    <li><i class="fas fa-check-circle text-success"></i> ตั้งค่ามาตรฐานการตรวจสอบ</li>
                                    <li><i class="fas fa-check-circle text-success"></i> แสดงกราฟและรายงาน</li>
                                    <li><i class="fas fa-check-circle text-success"></i> ส่งออกรายงาน (Excel/PDF)</li>
                                    <li><i class="fas fa-check-circle text-success"></i> จัดการผู้ใช้งานและสิทธิ์</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- System Information -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h3 class="card-title">
                            <i class="fas fa-server"></i>
                            ข้อมูลระบบ
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th style="width: 40%">เวอร์ชัน PHP</th>
                                        <td><?php echo $phpVersion; ?></td>
                                    </tr>
                                    <tr>
                                        <th>เวอร์ชันฐานข้อมูล</th>
                                        <td><?php echo $dbDriver . ' ' . $dbVersion; ?></td>
                                    </tr>
                                    <tr>
                                        <th>ขนาดฐานข้อมูล</th>
                                        <td><?php echo formatBytes($dbStats['total_size']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>จำนวนตาราง</th>
                                        <td><?php echo number_format($dbStats['table_count']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th style="width: 40%">เซิร์ฟเวอร์</th>
                                        <td><?php echo htmlspecialchars($serverSoftware); ?></td>
                                    </tr>
                                    <tr>
                                        <th>ระบบปฏิบัติการ</th>
                                        <td><?php echo php_uname('s') . ' ' . php_uname('r'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>จำนวนผู้ใช้</th>
                                        <td><?php echo number_format($userCount); ?> คน</td>
                                    </tr>
                                    <tr>
                                        <th>หน่วยความจำสูงสุด</th>
                                        <td><?php echo ini_get('memory_limit'); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Technologies Used -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h3 class="card-title">
                            <i class="fas fa-code"></i>
                            เทคโนโลยีที่ใช้
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-6 text-center mb-3">
                                <i class="fab fa-php fa-3x text-primary"></i>
                                <p>PHP <?php echo $phpVersion; ?></p>
                            </div>
                            <div class="col-md-3 col-6 text-center mb-3">
                                <i class="fas fa-database fa-3x text-success"></i>
                                <p>MySQL <?php echo $dbVersion; ?></p>
                            </div>
                            <div class="col-md-3 col-6 text-center mb-3">
                                <i class="fab fa-html5 fa-3x text-danger"></i>
                                <p>HTML5</p>
                            </div>
                            <div class="col-md-3 col-6 text-center mb-3">
                                <i class="fab fa-css3-alt fa-3x text-info"></i>
                                <p>CSS3</p>
                            </div>
                            <div class="col-md-3 col-6 text-center mb-3">
                                <i class="fab fa-js fa-3x text-warning"></i>
                                <p>JavaScript</p>
                            </div>
                            <div class="col-md-3 col-6 text-center mb-3">
                                <i class="fab fa-bootstrap fa-3x text-primary"></i>
                                <p>Bootstrap 5</p>
                            </div>
                            <div class="col-md-3 col-6 text-center mb-3">
                                <i class="fas fa-chart-line fa-3x text-success"></i>
                                <p>Chart.js</p>
                            </div>
                            <div class="col-md-3 col-6 text-center mb-3">
                                <i class="fas fa-table fa-3x text-secondary"></i>
                                <p>DataTables</p>
                            </div>
                            <div class="col-md-3 col-6 text-center mb-3">
                                <i class="fas fa-file-excel fa-3x text-success"></i>
                                <p>Excel Export</p>
                            </div>
                            <div class="col-md-3 col-6 text-center mb-3">
                                <i class="fas fa-file-pdf fa-3x text-danger"></i>
                                <p>PDF Export</p>
                            </div>
                            <div class="col-md-3 col-6 text-center mb-3">
                                <i class="fab fa-js fa-3x text-warning"></i>
                                <p>TypeScript</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Credits -->
                <div class="card">
                    <div class="card-header bg-warning">
                        <h3 class="card-title">
                            <i class="fas fa-heart"></i>
                            ขอบคุณ
                        </h3>
                    </div>
                    <div class="card-body">
                        <p>ระบบ EUMS พัฒนาโดยทีมงาน Engineering Utility Monitoring System</p>
                        
                        <h5>ทีมพัฒนา</h5>
                        <ul>
                            <li>นักวิเคราะห์ระบบ - System Analyst</li>
                            <li>นักพัฒนาโปรแกรม - Developer</li>
                            <li>ผู้ทดสอบระบบ - Tester</li>
                            <li>ผู้ดูแลระบบ - Administrator</li>
                        </ul>
                        
                        <h5 class="mt-3">เครื่องมือและไลบรารี</h5>
                        <ul>
                            <li><a href="https://adminlte.io/" target="_blank">AdminLTE 3</a> - เทมเพลตสำหรับระบบจัดการ</li>
                            <li><a href="https://getbootstrap.com/" target="_blank">Bootstrap 5</a> - เฟรมเวิร์ก CSS</li>
                            <li><a href="https://jquery.com/" target="_blank">jQuery</a> - ไลบรารี JavaScript</li>
                            <li><a href="https://www.chartjs.org/" target="_blank">Chart.js</a> - สร้างกราฟ</li>
                            <li><a href="https://datatables.net/" target="_blank">DataTables</a> - จัดการตาราง</li>
                            <li><a href="https://fontawesome.com/" target="_blank">Font Awesome</a> - ไอคอน</li>
                        </ul>
                    </div>
                </div>
                
                <!-- License -->
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h3 class="card-title">
                            <i class="fas fa-file-contract"></i>
                            สัญญาอนุญาต
                        </h3>
                    </div>
                    <div class="card-body">
                        <p>
                            ระบบ EUMS เป็นลิขสิทธิ์ขององค์กร อนุญาตให้ใช้งานภายในองค์กรเท่านั้น 
                            ห้ามทำซ้ำ ดัดแปลง แจกจ่าย หรือนำไปใช้ในเชิงพาณิชย์โดยไม่ได้รับอนุญาต
                        </p>
                        
                        <p>
                            <strong>สงวนลิขสิทธิ์ © <?php echo date('Y'); ?> Engineering Utility Monitoring System</strong>
                        </p>
                        
                        <p class="text-muted">
                            <small>
                                ซอฟต์แวร์นี้แจกจ่ายในสภาพ "ตามที่เป็น" โดยไม่มีการรับประกันใดๆ 
                                ทั้งโดยชัดแจ้งหรือโดยนัย รวมถึงแต่ไม่จำกัดเพียงการรับประกันความสามารถในเชิงพาณิชย์ 
                                และความเหมาะสมสำหรับวัตถุประสงค์เฉพาะ ผู้ใช้ต้องรับผิดชอบต่อความเสี่ยงเอง
                            </small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    // Stats chart
    var ctx = document.getElementById('statsChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Air Compressor', 'Energy & Water', 'LPG', 'Boiler', 'Summary'],
            datasets: [{
                data: [
                    <?php echo $recordCounts['air']; ?>,
                    <?php echo $recordCounts['energy']; ?>,
                    <?php echo $recordCounts['lpg']; ?>,
                    <?php echo $recordCounts['boiler']; ?>,
                    <?php echo $recordCounts['summary']; ?>
                ],
                backgroundColor: [
                    '#17a2b8',
                    '#ffc107',
                    '#dc3545',
                    '#6c757d',
                    '#28a745'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            cutout: '70%'
        }
    });
});

function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
</script>

<style>
.profile-username {
    font-size: 24px;
    font-weight: 600;
    margin-top: 10px;
}
</style>

<?php
// Helper function
function formatBytes($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Include footer
require_once __DIR__ . '/../includes/footer.php';
?>