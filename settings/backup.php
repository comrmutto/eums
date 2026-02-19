<?php
/**
 * Database Backup & Restore
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
$pageTitle = 'สำรองข้อมูล';

// Set breadcrumb
$breadcrumb = [
    ['title' => 'ตั้งค่า', 'link' => '#'],
    ['title' => 'สำรองข้อมูล', 'link' => null]
];

// Include header
require_once __DIR__ . '/../includes/header.php';

// Load required files
require_once __DIR__ . '/../includes/functions.php';

// Get database connection
$db = getDB();

// Get list of backup files
$backupDir = __DIR__ . '/../backups/';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$backupFiles = glob($backupDir . '*.sql');
$backups = [];

foreach ($backupFiles as $file) {
    $backups[] = [
        'filename' => basename($file),
        'path' => $file,
        'size' => filesize($file),
        'date' => filemtime($file)
    ];
}

// Sort by date descending
usort($backups, function($a, $b) {
    return $b['date'] - $a['date'];
});

// Get database size
$stmt = $db->query("
    SELECT 
        SUM(data_length + index_length) as total_size,
        COUNT(*) as table_count
    FROM information_schema.tables 
    WHERE table_schema = DATABASE()
");
$dbStats = $stmt->fetch();
?>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        
        <!-- Info Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo number_format($dbStats['table_count']); ?></h3>
                        <p>จำนวนตาราง</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-table"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo formatBytes($dbStats['total_size']); ?></h3>
                        <p>ขนาดฐานข้อมูล</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo count($backups); ?></h3>
                        <p>ไฟล์สำรองทั้งหมด</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-copy"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo formatBytes(array_sum(array_column($backups, 'size'))); ?></h3>
                        <p>ขนาดไฟล์สำรองรวม</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-hdd"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Backup Actions -->
        <div class="row">
            <div class="col-md-6">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-download"></i>
                            สำรองข้อมูล
                        </h3>
                    </div>
                    <div class="card-body">
                        <p>สร้างไฟล์สำรองข้อมูลฐานข้อมูลทั้งหมด เพื่อใช้ในการกู้คืนในภายหลัง</p>
                        <div class="form-group">
                            <label>ชื่อไฟล์ (ไม่ต้องระบุ .sql)</label>
                            <input type="text" class="form-control" id="backupFilename" 
                                   value="backup_<?php echo date('Ymd_His'); ?>">
                        </div>
                        <div class="form-group">
                            <label>ตัวเลือก</label>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="includeData" checked>
                                <label class="custom-control-label" for="includeData">รวมข้อมูล (ถ้าไม่เลือก จะสำรองเฉพาะโครงสร้าง)</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="compressBackup">
                                <label class="custom-control-label" for="compressBackup">บีบอัดไฟล์ (.zip)</label>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="createBackup()">
                            <i class="fas fa-download"></i> เริ่มสำรองข้อมูล
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card card-warning">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-upload"></i>
                            กู้คืนข้อมูล
                        </h3>
                    </div>
                    <div class="card-body">
                        <p>อัปโหลดไฟล์สำรองเพื่อกู้คืนข้อมูล (ไฟล์ .sql หรือ .zip)</p>
                        <form id="restoreForm" enctype="multipart/form-data">
                            <div class="form-group">
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="restoreFile" 
                                           accept=".sql,.zip" required>
                                    <label class="custom-file-label" for="restoreFile">เลือกไฟล์...</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="overwriteExisting">
                                    <label class="custom-control-label" for="overwriteExisting">เขียนทับข้อมูลที่มีอยู่</label>
                                </div>
                            </div>
                            <button type="button" class="btn btn-warning" onclick="restoreBackup()">
                                <i class="fas fa-upload"></i> กู้คืนข้อมูล
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Backup Files List -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list"></i>
                    รายการไฟล์สำรอง
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-danger btn-sm" onclick="cleanupOldBackups()">
                        <i class="fas fa-trash"></i> ลบไฟล์เก่า
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped dataTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ชื่อไฟล์</th>
                                <th>ขนาด</th>
                                <th>วันที่สร้าง</th>
                                <th>การดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $index => $backup): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <i class="fas fa-file-<?php echo pathinfo($backup['filename'], PATHINFO_EXTENSION) == 'zip' ? 'archive' : 'code'; ?>"></i>
                                    <?php echo $backup['filename']; ?>
                                </td>
                                <td><?php echo formatBytes($backup['size']); ?></td>
                                <td><?php echo date('d/m/Y H:i:s', $backup['date']); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="download_backup.php?file=<?php echo urlencode($backup['filename']); ?>" 
                                           class="btn btn-sm btn-success">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button class="btn btn-sm btn-info" onclick="restoreFromFile('<?php echo $backup['filename']; ?>')">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteBackup('<?php echo $backup['filename']; ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($backups)): ?>
                            <tr>
                                <td colspan="5" class="text-center">ไม่พบไฟล์สำรอง</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Schedule Backup -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clock"></i>
                    ตั้งค่าการสำรองข้อมูลอัตโนมัติ
                </h3>
            </div>
            <div class="card-body">
                <form id="scheduleForm">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>ความถี่</label>
                                <select class="form-control" id="backupFrequency">
                                    <option value="daily">รายวัน</option>
                                    <option value="weekly">รายสัปดาห์</option>
                                    <option value="monthly">รายเดือน</option>
                                    <option value="never">ไม่สำรองอัตโนมัติ</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>เวลาที่สำรอง</label>
                                <input type="time" class="form-control" id="backupTime" value="02:00">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>เก็บไฟล์ไว้</label>
                                <select class="form-control" id="keepDays">
                                    <option value="7">7 วัน</option>
                                    <option value="30" selected>30 วัน</option>
                                    <option value="90">90 วัน</option>
                                    <option value="365">1 ปี</option>
                                    <option value="0">ตลอดไป</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="button" class="btn btn-primary btn-block" onclick="saveSchedule()">
                                    <i class="fas fa-save"></i> บันทึกการตั้งค่า
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
$(document).ready(function() {
    // Custom file input
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass('selected').html(fileName);
    });
    
    // Load current schedule
    loadSchedule();
});

function createBackup() {
    const filename = $('#backupFilename').val();
    const includeData = $('#includeData').is(':checked');
    const compress = $('#compressBackup').is(':checked');
    
    if (!filename) {
        showNotification('กรุณาระบุชื่อไฟล์', 'warning');
        return;
    }
    
    $.ajax({
        url: 'ajax/create_backup.php',
        method: 'POST',
        data: {
            filename: filename,
            include_data: includeData ? 1 : 0,
            compress: compress ? 1 : 0
        },
        dataType: 'json',
        beforeSend: function() {
            showNotification('กำลังสำรองข้อมูล...', 'info');
        },
        success: function(response) {
            if (response.success) {
                showNotification('สำรองข้อมูลเรียบร้อย', 'success');
                setTimeout(function() {
                    location.reload();
                }, 2000);
            } else {
                showNotification(response.message, 'danger');
            }
        },
        error: function() {
            showNotification('เกิดข้อผิดพลาดในการสำรองข้อมูล', 'danger');
        }
    });
}

function restoreBackup() {
    const fileInput = $('#restoreFile')[0];
    const overwrite = $('#overwriteExisting').is(':checked');
    
    if (!fileInput.files || fileInput.files.length === 0) {
        showNotification('กรุณาเลือกไฟล์', 'warning');
        return;
    }
    
    if (!confirm('การกู้คืนข้อมูลจะเขียนทับข้อมูลปัจจุบัน คุณแน่ใจหรือไม่?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('backup_file', fileInput.files[0]);
    formData.append('overwrite', overwrite ? 1 : 0);
    
    $.ajax({
        url: 'ajax/restore_backup.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        beforeSend: function() {
            showNotification('กำลังกู้คืนข้อมูล...', 'info');
        },
        success: function(response) {
            if (response.success) {
                showNotification('กู้คืนข้อมูลเรียบร้อย', 'success');
                setTimeout(function() {
                    location.reload();
                }, 3000);
            } else {
                showNotification(response.message, 'danger');
            }
        },
        error: function() {
            showNotification('เกิดข้อผิดพลาดในการกู้คืนข้อมูล', 'danger');
        }
    });
}

function restoreFromFile(filename) {
    if (!confirm('ต้องการกู้คืนข้อมูลจากไฟล์ ' + filename + '?')) {
        return;
    }
    
    $.ajax({
        url: 'ajax/restore_backup.php',
        method: 'POST',
        data: { filename: filename },
        dataType: 'json',
        beforeSend: function() {
            showNotification('กำลังกู้คืนข้อมูล...', 'info');
        },
        success: function(response) {
            if (response.success) {
                showNotification('กู้คืนข้อมูลเรียบร้อย', 'success');
                setTimeout(function() {
                    location.reload();
                }, 3000);
            } else {
                showNotification(response.message, 'danger');
            }
        },
        error: function() {
            showNotification('เกิดข้อผิดพลาดในการกู้คืนข้อมูล', 'danger');
        }
    });
}

function deleteBackup(filename) {
    if (!confirm('ต้องการลบไฟล์ ' + filename + '?')) {
        return;
    }
    
    $.ajax({
        url: 'ajax/delete_backup.php',
        method: 'POST',
        data: { filename: filename },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('ลบไฟล์เรียบร้อย', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showNotification(response.message, 'danger');
            }
        },
        error: function() {
            showNotification('เกิดข้อผิดพลาดในการลบไฟล์', 'danger');
        }
    });
}

function cleanupOldBackups() {
    const days = prompt('ลบไฟล์ที่เก่ากว่ากี่วัน?', '30');
    if (days === null) return;
    
    $.ajax({
        url: 'ajax/cleanup_backups.php',
        method: 'POST',
        data: { days: days },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('ลบไฟล์เก่าเรียบร้อย ' + response.deleted + ' ไฟล์', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showNotification(response.message, 'danger');
            }
        },
        error: function() {
            showNotification('เกิดข้อผิดพลาด', 'danger');
        }
    });
}

function saveSchedule() {
    const frequency = $('#backupFrequency').val();
    const time = $('#backupTime').val();
    const keepDays = $('#keepDays').val();
    
    $.ajax({
        url: 'ajax/save_schedule.php',
        method: 'POST',
        data: {
            frequency: frequency,
            time: time,
            keep_days: keepDays
        },
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

function loadSchedule() {
    $.ajax({
        url: 'ajax/get_schedule.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#backupFrequency').val(response.data.frequency);
                $('#backupTime').val(response.data.time);
                $('#keepDays').val(response.data.keep_days);
            }
        }
    });
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
</script>

<?php
// Helper function
function formatBytes($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Include footer
require_once __DIR__ . '/../includes/footer.php';
?>