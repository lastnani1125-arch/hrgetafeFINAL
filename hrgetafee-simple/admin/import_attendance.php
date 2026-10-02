<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

require_login();
check_role(1);

$admin = get_employee_info($conn, $_SESSION['employee_id']);
$message = '';
$error = '';
$preview_data = [];
$file_uploaded = false;

// Create import_logs table if it doesn't exist
$check_table = $conn->query("SHOW TABLES LIKE 'biometric_import_logs'");
if ($check_table->num_rows === 0) {
    $conn->query("CREATE TABLE biometric_import_logs (
        import_id INT PRIMARY KEY AUTO_INCREMENT,
        imported_by INT NOT NULL,
        import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        filename VARCHAR(255),
        total_records INT,
        successful_records INT,
        failed_records INT,
        notes TEXT,
        FOREIGN KEY (imported_by) REFERENCES users(user_id)
    )");
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['attendance_file'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    
    if (empty($csrf) || !verifyCSRFToken($csrf)) {
        $error = 'Invalid form token. Please reload the page.';
    } else {
        $file = $_FILES['attendance_file'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'File upload error: ' . $file['error'];
        } elseif ($file['size'] > 5000000) { // 5MB limit
            $error = 'File size too large (max 5MB).';
        } elseif (!in_array(pathinfo($file['name'], PATHINFO_EXTENSION), ['csv', 'xlsx', 'xls'])) {
            $error = 'Only CSV and Excel files are allowed.';
        } else {
            // Read file
            $handle = fopen($file['tmp_name'], 'r');
            $headers = fgetcsv($handle);
            
            if (!$headers) {
                $error = 'Empty file or cannot read CSV.';
            } else {
                // Get column mappings from form
                $emp_col = sanitizeInput($_POST['emp_col'] ?? '');
                $date_col = sanitizeInput($_POST['date_col'] ?? '');
                $time_in_col = sanitizeInput($_POST['time_in_col'] ?? '');
                $time_out_col = sanitizeInput($_POST['time_out_col'] ?? '');
                $status_col = sanitizeInput($_POST['status_col'] ?? '');
                
                if (!$emp_col || !$date_col || !$time_in_col) {
                    $error = 'Please map all required columns (Employee Code, Date, Time In).';
                } else {
                    // Find column indices
                    $emp_idx = array_search($emp_col, $headers, true);
                    $date_idx = array_search($date_col, $headers, true);
                    $time_in_idx = array_search($time_in_col, $headers, true);
                    $time_out_idx = array_search($time_out_col, $headers, true);
                    $status_idx = array_search($status_col, $headers, true);
                    
                    if ($emp_idx === false || $date_idx === false || $time_in_idx === false) {
                        $error = 'Selected columns not found in file.';
                    } else {
                        // Preview mode or import mode
                        $import_mode = sanitizeInput($_POST['mode'] ?? 'preview');
                        
                        if ($import_mode === 'preview') {
                            // Show preview
                            $preview_data = [];
                            $row_num = 0;
                            while (($row = fgetcsv($handle)) && $row_num < 10) {
                                if (empty($row[0])) continue; // Skip empty rows
                                
                                $emp_code = trim($row[$emp_idx] ?? '');
                                $att_date = trim($row[$date_idx] ?? '');
                                $clock_in = trim($row[$time_in_idx] ?? '');
                                $clock_out = isset($time_out_idx) && $time_out_idx !== false ? trim($row[$time_out_idx] ?? '') : '';
                                $status = isset($status_idx) && $status_idx !== false ? trim($row[$status_idx] ?? 'present') : 'present';
                                
                                // Validate employee exists
                                $emp_stmt = $conn->prepare("SELECT employee_id FROM employees WHERE employee_code = ? LIMIT 1");
                                $emp_stmt->bind_param('s', $emp_code);
                                $emp_stmt->execute();
                                $emp_result = $emp_stmt->get_result();
                                $emp_row = $emp_result->fetch_assoc();
                                $emp_stmt->close();
                                
                                $preview_data[] = [
                                    'emp_code' => $emp_code,
                                    'emp_exists' => $emp_row ? '✓' : '✗',
                                    'date' => $att_date,
                                    'clock_in' => $clock_in,
                                    'clock_out' => $clock_out,
                                    'status' => $status
                                ];
                                $row_num++;
                            }
                            $file_uploaded = true;
                            $message = 'Preview loaded. Check the data below and click "Confirm Import" to proceed.';
                        } elseif ($import_mode === 'import') {
                            // Actually import
                            rewind($handle);
                            fgetcsv($handle); // Skip headers
                            
                            $successful = 0;
                            $failed = 0;
                            $failed_rows = [];
                            
                            while (($row = fgetcsv($handle))) {
                                if (empty($row[0])) continue;
                                
                                $emp_code = trim($row[$emp_idx] ?? '');
                                $att_date = trim($row[$date_idx] ?? '');
                                $clock_in = trim($row[$time_in_idx] ?? '');
                                $clock_out = isset($time_out_idx) && $time_out_idx !== false ? trim($row[$time_out_idx] ?? '') : null;
                                $status = isset($status_idx) && $status_idx !== false ? trim($row[$status_idx] ?? 'present') : 'present';
                                
                                // Get employee ID
                                $emp_stmt = $conn->prepare("SELECT employee_id FROM employees WHERE employee_code = ? LIMIT 1");
                                $emp_stmt->bind_param('s', $emp_code);
                                $emp_stmt->execute();
                                $emp_result = $emp_stmt->get_result();
                                $emp_row = $emp_result->fetch_assoc();
                                $emp_stmt->close();
                                
                                if (!$emp_row) {
                                    $failed++;
                                    $failed_rows[] = "Employee code '$emp_code' not found";
                                    continue;
                                }
                                
                                $employee_id = $emp_row['employee_id'];
                                
                                // Validate date
                                if (!strtotime($att_date)) {
                                    $failed++;
                                    $failed_rows[] = "Invalid date format: $att_date";
                                    continue;
                                }
                                
                                // Format times
                                $clock_in_datetime = null;
                                $clock_out_datetime = null;
                                
                                if ($clock_in) {
                                    $clock_in_datetime = date('Y-m-d H:i:s', strtotime("$att_date $clock_in"));
                                }
                                
                                if ($clock_out) {
                                    $clock_out_datetime = date('Y-m-d H:i:s', strtotime("$att_date $clock_out"));
                                }
                                
                                // Check if record already exists
                                $check_stmt = $conn->prepare("SELECT attendance_id FROM attendance WHERE employee_id = ? AND attendance_date = ? LIMIT 1");
                                $check_stmt->bind_param('is', $employee_id, $att_date);
                                $check_stmt->execute();
                                $check_result = $check_stmt->get_result();
                                $check_stmt->close();
                                
                                if ($check_result->num_rows > 0) {
                                    // Update existing record
                                    $update_stmt = $conn->prepare("UPDATE attendance SET clock_in = ?, clock_out = ?, status = ?, updated_at = NOW() WHERE employee_id = ? AND attendance_date = ?");
                                    $update_stmt->bind_param('ssssis', $clock_in_datetime, $clock_out_datetime, $status, $employee_id, $att_date);
                                    
                                    if ($update_stmt->execute()) {
                                        $successful++;
                                    } else {
                                        $failed++;
                                        $failed_rows[] = "Update failed for $emp_code on $att_date";
                                    }
                                    $update_stmt->close();
                                } else {
                                    // Insert new record
                                    $insert_stmt = $conn->prepare("INSERT INTO attendance (employee_id, clock_in, clock_out, attendance_date, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                                    $insert_stmt->bind_param('issss', $employee_id, $clock_in_datetime, $clock_out_datetime, $att_date, $status);
                                    
                                    if ($insert_stmt->execute()) {
                                        $successful++;
                                    } else {
                                        $failed++;
                                        $failed_rows[] = "Insert failed for $emp_code on $att_date";
                                    }
                                    $insert_stmt->close();
                                }
                            }
                            
                            fclose($handle);
                            
                            // Log the import
                            $log_stmt = $conn->prepare("INSERT INTO biometric_import_logs (imported_by, filename, total_records, successful_records, failed_records, notes) VALUES (?, ?, ?, ?, ?, ?)");
                            $filename = $file['name'];
                            $total = $successful + $failed;
                            $notes = implode('; ', array_slice($failed_rows, 0, 5));
                            $log_stmt->bind_param('ississ', $_SESSION['user_id'], $filename, $total, $successful, $failed, $notes);
                            $log_stmt->execute();
                            $log_stmt->close();
                            
                            $message = "✓ Import completed! Successful: $successful, Failed: $failed";
                            logSecurityEvent($conn, $_SESSION['user_id'], 'BIOMETRIC_IMPORT', "Imported $successful attendance records from biometric system");
                        }
                    }
                }
            }
            
            fclose($handle);
        }
    }
}

// Get import history
$import_history = [];
$hist_stmt = $conn->prepare("SELECT import_id, imported_by, import_date, filename, total_records, successful_records, failed_records FROM biometric_import_logs ORDER BY import_date DESC LIMIT 20");
if ($hist_stmt) {
    $hist_stmt->execute();
    $hist_result = $hist_stmt->get_result();
    $import_history = $hist_result->fetch_all(MYSQLI_ASSOC);
    $hist_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biometric Attendance Import - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .import-container { max-width: 1000px; }
        .upload-section { background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 2px dashed #667eea; }
        .file-input-wrapper { margin-bottom: 15px; }
        .file-input-wrapper input[type="file"] { display: block; padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%; }
        .column-mapping { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0; }
        .mapping-group { }
        .mapping-group label { display: block; margin-bottom: 6px; font-weight: 600; }
        .mapping-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .preview-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; }
        .preview-table th, .preview-table td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        .preview-table th { background: #f7f7fb; font-weight: 600; }
        .preview-table tr:hover { background: #f9f9f9; }
        .valid { color: #10b981; font-weight: bold; }
        .invalid { color: #ef4444; font-weight: bold; }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 16px; }
        .alert-success { background: #ecfdf5; border: 1px solid #d1fae5; color: #065f46; }
        .alert-danger { background: #fff1f2; border: 1px solid #fecaca; color: #7f1d1d; }
        .alert-info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
        .action-buttons { display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap; }
        .btn-upload { background: #3b82f6; color: white; padding: 10px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; }
        .btn-upload:hover { background: #2563eb; }
        .btn-import { background: #10b981; color: white; padding: 10px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; }
        .btn-import:hover { background: #059669; }
        .history-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; }
        .history-table th, .history-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .history-table th { background: #f7f7fb; font-weight: 600; }
        .history-table tr:hover { background: #f9f9f9; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #7f1d1d; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>📤 Biometric Attendance Import</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($admin['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container import-container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Dashboard</a>

        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="alert alert-info">
            <strong>ℹ️ Instructions:</strong> Upload a CSV or Excel file with your biometric attendance data. Map the columns to our system fields, preview the data, and confirm the import.
        </div>

        <!-- Upload Section -->
        <div class="upload-section">
            <h3>📁 Step 1: Upload File</h3>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="file-input-wrapper">
                    <label for="attendance_file">Select CSV/Excel File *</label>
                    <input type="file" name="attendance_file" id="attendance_file" accept=".csv,.xlsx,.xls" required>
                    <small style="color: #666; display: block; margin-top: 4px;">
                        Supported formats: CSV, Excel (.xlsx, .xls) | Max size: 5MB
                    </small>
                </div>

                <?php if (!$file_uploaded): ?>
                    <h3 style="margin-top: 20px;">📋 Step 2: Map Columns</h3>
                    <p style="color: #666;">Select which columns in your file correspond to these fields:</p>
                    
                    <div class="column-mapping">
                        <div class="mapping-group">
                            <label for="emp_col">Employee Code Column *</label>
                            <select name="emp_col" id="emp_col" required>
                                <option value="">-- Select Column --</option>
                                <option value="employee_code">employee_code</option>
                                <option value="emp_code">emp_code</option>
                                <option value="id">id</option>
                                <option value="emp_id">emp_id</option>
                                <option value="code">code</option>
                            </select>
                        </div>
                        <div class="mapping-group">
                            <label for="date_col">Date Column *</label>
                            <select name="date_col" id="date_col" required>
                                <option value="">-- Select Column --</option>
                                <option value="date">date</option>
                                <option value="attendance_date">attendance_date</option>
                                <option value="att_date">att_date</option>
                            </select>
                        </div>
                        <div class="mapping-group">
                            <label for="time_in_col">Time In Column *</label>
                            <select name="time_in_col" id="time_in_col" required>
                                <option value="">-- Select Column --</option>
                                <option value="time_in">time_in</option>
                                <option value="clock_in">clock_in</option>
                                <option value="in_time">in_time</option>
                                <option value="check_in">check_in</option>
                            </select>
                        </div>
                        <div class="mapping-group">
                            <label for="time_out_col">Time Out Column (Optional)</label>
                            <select name="time_out_col" id="time_out_col">
                                <option value="">-- Select Column --</option>
                                <option value="time_out">time_out</option>
                                <option value="clock_out">clock_out</option>
                                <option value="out_time">out_time</option>
                                <option value="check_out">check_out</option>
                            </select>
                        </div>
                        <div class="mapping-group">
                            <label for="status_col">Status Column (Optional)</label>
                            <select name="status_col" id="status_col">
                                <option value="">-- Select Column --</option>
                                <option value="status">status</option>
                                <option value="present">present</option>
                                <option value="att_status">att_status</option>
                            </select>
                        </div>
                    </div>

                    <input type="hidden" name="mode" value="preview">
                    <div class="action-buttons">
                        <button type="submit" class="btn-upload">📤 Upload & Preview</button>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Preview Section -->
        <?php if ($file_uploaded && !empty($preview_data)): ?>
            <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3>👀 Step 3: Preview Data</h3>
                <p style="color: #666;">Check if the data looks correct. ✓ means employee exists in system.</p>
                
                <div style="overflow-x: auto;">
                    <table class="preview-table">
                        <thead>
                            <tr>
                                <th>Employee Code</th>
                                <th>Exists</th>
                                <th>Date</th>
                                <th>Clock In</th>
                                <th>Clock Out</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview_data as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['emp_code']); ?></td>
                                    <td>
                                        <span class="<?php echo $row['emp_exists'] === '✓' ? 'valid' : 'invalid'; ?>">
                                            <?php echo $row['emp_exists']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['clock_in']); ?></td>
                                    <td><?php echo htmlspecialchars($row['clock_out'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <form method="POST" enctype="multipart/form-data" style="margin-top: 20px;">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="attendance_file" value="">
                    <input type="hidden" name="emp_col" value="<?php echo htmlspecialchars($_POST['emp_col'] ?? ''); ?>">
                    <input type="hidden" name="date_col" value="<?php echo htmlspecialchars($_POST['date_col'] ?? ''); ?>">
                    <input type="hidden" name="time_in_col" value="<?php echo htmlspecialchars($_POST['time_in_col'] ?? ''); ?>">
                    <input type="hidden" name="time_out_col" value="<?php echo htmlspecialchars($_POST['time_out_col'] ?? ''); ?>">
                    <input type="hidden" name="status_col" value="<?php echo htmlspecialchars($_POST['status_col'] ?? ''); ?>">
                    <input type="hidden" name="mode" value="import">
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn-import" onclick="return confirm('This will import all valid records. Continue?');">
                            ✓ Confirm & Import
                        </button>
                        <a href="import_attendance.php" class="btn btn-secondary">← Start Over</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Import History -->
        <div style="background: white; padding: 20px; border-radius: 8px;">
            <h3>📊 Import History</h3>
            <?php if (!empty($import_history)): ?>
                <div style="overflow-x: auto;">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Filename</th>
                                <th>Imported By</th>
                                <th>Total</th>
                                <th>Success</th>
                                <th>Failed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($import_history as $log): ?>
                                <tr>
                                    <td><?php echo format_datetime($log['import_date']); ?></td>
                                    <td><?php echo htmlspecialchars($log['filename']); ?></td>
                                    <td><?php 
                                        $user_stmt = $conn->prepare("SELECT CONCAT(e.first_name, ' ', e.last_name) as name FROM users u JOIN employees e ON u.employee_id = e.employee_id WHERE u.user_id = ?");
                                        $user_stmt->bind_param('i', $log['imported_by']);
                                        $user_stmt->execute();
                                        $user_result = $user_stmt->get_result();
                                        $user_row = $user_result->fetch_assoc();
                                        $user_stmt->close();
                                        echo htmlspecialchars($user_row['name'] ?? 'Unknown');
                                    ?></td>
                                    <td><?php echo (int)$log['total_records']; ?></td>
                                    <td><span class="badge badge-success"><?php echo (int)$log['successful_records']; ?></span></td>
                                    <td><span class="badge badge-danger"><?php echo (int)$log['failed_records']; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: #666; text-align: center; padding: 20px;">No imports yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
