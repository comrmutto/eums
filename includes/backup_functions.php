<?php
/**
 * Backup Schedule Functions
 * Engineering Utility Monitoring System (EUMS)
 */

/**
 * Load backup schedule configuration
 */
function loadBackupSchedule() {
    $scheduleFile = __DIR__ . '/../config/backup_schedule.json';
    
    if (!file_exists($scheduleFile)) {
        return createDefaultSchedule();
    }
    
    $content = file_get_contents($scheduleFile);
    return json_decode($content, true);
}

/**
 * Create default backup schedule
 */
function createDefaultSchedule() {
    $now = new DateTime();
    $nextBackup = clone $now;
    $nextBackup->setTime(2, 0, 0);
    
    if ($nextBackup <= $now) {
        $nextBackup->modify('+1 day');
    }
    
    $schedule = [
        'frequency' => 'daily',
        'time' => '02:00',
        'keep_days' => 30,
        'last_backup' => null,
        'next_backup' => $nextBackup->format('Y-m-d H:i:s'),
        'updated_at' => $now->format('Y-m-d H:i:s'),
        'created_at' => $now->format('Y-m-d H:i:s'),
        'settings' => [
            'compress' => true,
            'include_data' => true,
            'notify_on_complete' => true,
            'notify_on_error' => true,
            'max_backups' => 30
        ],
        'excluded_tables' => [],
        'history' => []
    ];
    
    saveBackupSchedule($schedule);
    return $schedule;
}

/**
 * Save backup schedule
 */
function saveBackupSchedule($schedule) {
    $scheduleFile = __DIR__ . '/../config/backup_schedule.json';
    $schedule['updated_at'] = date('Y-m-d H:i:s');
    
    // Create backup directory if needed
    $backupDir = __DIR__ . '/../backups/';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    return file_put_contents($scheduleFile, json_encode($schedule, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Update last backup time
 */
function updateLastBackup($filename, $size, $tables, $status = 'success', $message = '') {
    $schedule = loadBackupSchedule();
    
    $schedule['last_backup'] = date('Y-m-d H:i:s');
    
    // Recalculate next backup
    $now = new DateTime();
    $nextBackup = clone $now;
    list($hour, $minute) = explode(':', $schedule['time']);
    $nextBackup->setTime($hour, $minute, 0);
    
    if ($nextBackup <= $now) {
        if ($schedule['frequency'] === 'daily') {
            $nextBackup->modify('+1 day');
        } elseif ($schedule['frequency'] === 'weekly') {
            $nextBackup->modify('+1 week');
        } elseif ($schedule['frequency'] === 'monthly') {
            $nextBackup->modify('+1 month');
        }
    }
    
    $schedule['next_backup'] = $nextBackup->format('Y-m-d H:i:s');
    
    // Add to history
    array_unshift($schedule['history'], [
        'id' => count($schedule['history']) + 1,
        'date' => $schedule['last_backup'],
        'filename' => $filename,
        'size' => $size,
        'tables' => $tables,
        'status' => $status,
        'duration' => 0,
        'message' => $message
    ]);
    
    // Keep only last 100 history entries
    $schedule['history'] = array_slice($schedule['history'], 0, 100);
    
    // Update statistics
    $totalSize = 0;
    $successCount = 0;
    foreach ($schedule['history'] as $entry) {
        $totalSize += $entry['size'];
        if ($entry['status'] === 'success') {
            $successCount++;
        }
    }
    
    $schedule['statistics'] = [
        'total_backups' => count($schedule['history']),
        'total_size' => $totalSize,
        'average_size' => count($schedule['history']) > 0 ? $totalSize / count($schedule['history']) : 0,
        'success_rate' => count($schedule['history']) > 0 ? ($successCount / count($schedule['history'])) * 100 : 0,
        'last_30_days' => count(array_filter($schedule['history'], function($entry) {
            return strtotime($entry['date']) > strtotime('-30 days');
        }))
    ];
    
    saveBackupSchedule($schedule);
    
    // Clean up old backups
    cleanupOldBackups($schedule['keep_days']);
}

/**
 * Clean up old backup files
 */
function cleanupOldBackups($keepDays) {
    $backupDir = __DIR__ . '/../backups/';
    if (!file_exists($backupDir)) {
        return;
    }
    
    $cutoff = time() - ($keepDays * 24 * 60 * 60);
    $files = glob($backupDir . '*.{sql,sql.gz,zip}', GLOB_BRACE);
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoff) {
            @unlink($file);
        }
    }
}

/**
 * Check if backup is due
 */
function isBackupDue() {
    $schedule = loadBackupSchedule();
    
    if ($schedule['frequency'] === 'never') {
        return false;
    }
    
    if (empty($schedule['next_backup'])) {
        return true;
    }
    
    $nextBackup = strtotime($schedule['next_backup']);
    return time() >= $nextBackup;
}

/**
 * Get backup statistics
 */
function getBackupStatistics() {
    $schedule = loadBackupSchedule();
    
    $backupDir = __DIR__ . '/../backups/';
    $files = glob($backupDir . '*.{sql,sql.gz,zip}', GLOB_BRACE);
    
    $totalSize = 0;
    $fileCount = count($files);
    
    foreach ($files as $file) {
        $totalSize += filesize($file);
    }
    
    return [
        'total_files' => $fileCount,
        'total_size' => $totalSize,
        'total_size_formatted' => formatBytes($totalSize),
        'last_backup' => $schedule['last_backup'],
        'next_backup' => $schedule['next_backup'],
        'frequency' => $schedule['frequency'],
        'keep_days' => $schedule['keep_days']
    ];
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>