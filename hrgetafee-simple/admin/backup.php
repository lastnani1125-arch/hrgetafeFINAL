<?php
// hrgetafee-simple/admin/backup.php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

require_login();
check_role(1);

$message = ''; $error = '';

$backupDir = __DIR__ . '/../scripts/backups';
if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    if (empty($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid form token.';
    } else {
        $timestamp = date('Ymd_His');
        $file = $backupDir . "/hrgetafee_backup_{$timestamp}.sql";

        $dbHost = DB_HOST; 
        $dbUser = DB_USER; 
        $dbPass = DB_PASS; 
        $dbName = DB_NAME;

        try {
            $output = '';
            
            // Get all tables
            $tables = [];
            $result = $conn->query("SHOW TABLES");
            while ($row = $result->fetch_row()) {
                $tables[] = $row[0];
            }
            
            // Build SQL dump
            $output .= "-- HRGetafe Database Backup\n";
            $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $output .= "-- Database: " . $dbName . "\n\n";
            $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            
            foreach ($tables as $table) {
                // Drop table if exists
                $output .= "DROP TABLE IF EXISTS `$table`;\n";
                
                // Create table
                $createTableResult = $conn->query("SHOW CREATE TABLE `$table`");
                if ($createTableResult) {
                    $createTableRow = $createTableResult->fetch_row();
                    $output .= $createTableRow[1] . ";\n\n";
                    
                    // Insert data
                    $dataResult = $conn->query("SELECT * FROM `$table`");
                    if ($dataResult && $dataResult->num_rows > 0) {
                        while ($row = $dataResult->fetch_assoc()) {
                            $cols = implode('`, `', array_keys($row));
                            $vals = array_map(function($v) use ($conn) {
                                // Handle NULL values properly
                                if ($v === null) {
                                    return 'NULL';
                                }
                                return "'" . $conn->real_escape_string((string)$v) . "'";
                            }, array_values($row));
                            $output .= "INSERT INTO `$table` (`$cols`) VALUES (" . implode(", ", $vals) . ");\n";
                        }
                        $output .= "\n";
                    }
                }
            }
            
            $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            // Write to file
            $bytesWritten = file_put_contents($file, $output);
            
            if ($bytesWritten !== false && $bytesWritten > 0) {
                $message = "✓ Backup created successfully: " . basename($file) . " (" . round($bytesWritten / 1024, 2) . " KB)";
            } else {
                $error = "Failed to write backup file. Check directory permissions.";
                if (file_exists($file)) @unlink($file); // Clean up incomplete file
            }
        } catch (Exception $e) {
            $error = "Backup error: " . $e->getMessage();
        }
    }
}

// list backups
$files = [];
foreach (glob($backupDir . '/hrgetafee_backup_*.sql') as $f) {
    $files[] = [
        'name' => basename($f),
        'size' => filesize($f),
        'time' => filemtime($f)
    ];
}
usort($files, function($a, $b) { return $b['time'] <=> $a['time']; });
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin Backup</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .backup-list { margin-top: 20px; }
        .backup-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border: 1px solid #ddd; margin-bottom: 8px; border-radius: 5px; background: #f9f9f9; }
        .backup-info { flex: 1; }
        .backup-size { color: #666; font-size: 12px; }
        .backup-actions { display: flex; gap: 10px; }
        .alert { padding: 12px; border-radius: 5px; margin-bottom: 15px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
<div class="navbar">
    <h2>Backup & Restore</h2>
    <div class="navbar-user">
        <a href="../logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <h3>Create Backup</h3>
    <p style="color: #666; font-size: 13px; margin-bottom: 10px;">Click the button below to create a complete database backup. The process will complete before showing the result.</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <button type="submit" name="create_backup" class="btn btn-primary">📥 Create Backup Now</button>
    </form>

    <h3 style="margin-top: 30px;">Available Backups</h3>
    <?php if (!empty($files)): ?>
        <div class="backup-list">
            <?php foreach ($files as $backup): ?>
                <div class="backup-item">
                    <div class="backup-info">
                        <strong><?php echo htmlspecialchars($backup['name']); ?></strong>
                        <br>
                        <span class="backup-size">
                            Size: <?php echo round($backup['size'] / 1024, 2); ?> KB | 
                            Created: <?php echo date('M d, Y H:i:s', $backup['time']); ?>
                        </span>
                    </div>
                    <div class="backup-actions">
                        <a href="<?php echo '../scripts/backups/' . rawurlencode($backup['name']); ?>" download class="btn btn-primary" style="font-size: 12px;">Download</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="color: #666;">No backups created yet. Click "Create Backup Now" to create one.</p>
    <?php endif; ?>
    
    <p style="margin-top: 20px; color: #666; font-size: 13px; border: 1px solid #ccc; padding: 10px; background: #f0f0f0; border-radius: 5px;">
        <strong>⚠️ Note:</strong> Restoring a backup requires database admin access. Contact your system administrator for restore operations. Do not restore backups yourself without proper backup of current data.
    </p>
</div>
</body>
</html>
