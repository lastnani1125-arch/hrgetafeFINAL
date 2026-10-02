<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

require_login();
check_role(1); // Admin only

$admin = get_employee_info($conn, $_SESSION['employee_id']);
$message = '';
$error = '';
$selected_employee_id = intval($_GET['employee_id'] ?? 0);
$selected_date = sanitizeInput($_GET['date'] ?? '');

// Handle attendance update/add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($csrf) || !verifyCSRFToken($csrf)) {
        $error = 'Invalid form token.';
    } else {
        $action = sanitizeInput($_POST['action']);
        
        if ($action === 'save_attendance') {
            $employee_id = intval($_POST['employee_id'] ?? 0);
            $attendance_date = sanitizeInput($_POST['attendance_date'] ?? '');
            $clock_in = sanitizeInput($_POST['clock_in'] ?? '');
            $clock_out = sanitizeInput($_POST['clock_out'] ?? '');
            $status = sanitizeInput($_POST['status'] ?? 'present');
            $remarks = sanitizeInput($_POST['remarks'] ?? '');
            
            if ($employee_id <= 0 || $attendance_date === '') {
                $error = 'Employee and date are required.';
            } else {
                // Verify employee exists
                $emp_check = $conn->prepare("SELECT employee_id FROM employees WHERE employee_id = ? LIMIT 1");
                $emp_check->bind_param('i', $employee_id);
                $emp_check->execute();
                if ($emp_check->get_result()->num_rows === 0) {
                    $error = 'Employee not found.';
                    $emp_check->close();
                } else {
                    $emp_check->close();
                    
                    // Format times
                    $clock_in_datetime = null;
                    $clock_out_datetime = null;
                    
                    if (!empty($clock_in)) {
                        $clock_in_datetime = date('Y-m-d H:i:s', strtotime("$attendance_date $clock_in"));
                    }
                    if (!empty($clock_out)) {
                        $clock_out_datetime = date('Y-m-d H:i:s', strtotime("$attendance_date $clock_out"));
                    }
                    
                    // Check if record exists
                    $check = $conn->prepare("SELECT attendance_id FROM attendance WHERE employee_id = ? AND attendance_date = ? LIMIT 1");
                    $check->bind_param('is', $employee_id, $attendance_date);
                    $check->execute();
                    $result = $check->get_result();
                    $exists = $result->num_rows > 0;
                    $check->close();
                    
                    if ($exists) {
                        // Update
                        $stmt = $conn->prepare("UPDATE attendance SET clock_in = ?, clock_out = ?, status = ?, remarks = ?, updated_at = NOW() WHERE employee_id = ? AND attendance_date = ?");
                        $stmt->bind_param('ssssii', $clock_in_datetime, $clock_out_datetime, $status, $remarks, $employee_id, $attendance_date);
                        $operation = 'UPDATED';
                    } else {
                        // Insert
                        $stmt = $conn->prepare("INSERT INTO attendance (employee_id, clock_in, clock_out, attendance_date, status, remarks, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                        $stmt->bind_param('issss', $employee_id, $clock_in_datetime, $clock_out_datetime, $attendance_date, $status, $remarks);
                        $operation = 'ADDED';
                    }
                    
                    if ($stmt->execute()) {
                        $message = "✓ Attendance record $operation successfully.";
                        logSecurityEvent($conn, $_SESSION['user_id'], 'EDIT_ATTENDANCE', "Admin manually $operation attendance for employee_id: $employee_id on $attendance_date");
                        header('Location: edit_attendance.php?employee_id=' . $employee_id . '&date=' . urlencode($attendance_date));
                        exit;
                    } else {
                        $error = 'Error saving attendance: ' . htmlspecialchars($stmt->error);
                    }
                    $stmt->close();
                }
            }
        } elseif ($action === 'delete_attendance') {
            $attendance_id = intval($_POST['attendance_id'] ?? 0);
            
            if ($attendance_id <= 0) {
                $error = 'Invalid attendance record.';
            } else {
                $stmt = $conn->prepare("DELETE FROM attendance WHERE attendance_id = ?");
                $stmt->bind_param('i', $attendance_id);
                if ($stmt->execute()) {
                    $message = '✓ Attendance record deleted successfully.';
                    logSecurityEvent($conn, $_SESSION['user_id'], 'DELETE_ATTENDANCE', "Deleted attendance_id: $attendance_id");
                    header('Location: edit_attendance.php?employee_id=' . $selected_employee_id);
                    exit;
                } else {
                    $error = 'Error deleting attendance.';
                }
                $stmt->close();
            }
        }
    }
}

// Get all employees for dropdown
$employees_result = $conn->query("SELECT employee_id, employee_code, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name");
$employees = $employees_result ? $employees_result->fetch_all(MYSQLI_ASSOC) : [];

// Get selected employee info
$selected_employee = null;
if ($selected_employee_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ? LIMIT 1");
    $stmt->bind_param('i', $selected_employee_id);
    $stmt->execute();
    $selected_employee = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Get current attendance record if date selected
$current_attendance = null;
if ($selected_employee_id > 0 && $selected_date !== '') {
    $stmt = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ? LIMIT 1");
    $stmt->bind_param('is', $selected_employee_id, $selected_date);
    $stmt->execute();
    $current_attendance = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Get attendance history for selected employee (last 30 days)
$attendance_history = [];
if ($selected_employee_id > 0) {
    $stmt = $conn->prepare("
        SELECT * FROM attendance 
        WHERE employee_id = ? 
        AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY attendance_date DESC
    ");
    $stmt->bind_param('i', $selected_employee_id);
    $stmt->execute();
    $attendance_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Attendance - HRGetafe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Verdana, sans-serif;
            background: #f5f7fb;
            color: #2c3e50;
        }

        .navbar {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .navbar h2 { font-size: 24px; margin: 0; }
        .navbar-user { display: flex; gap: 15px; align-items: center; }
        .navbar-user a {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            transition: 0.3s;
        }
        .navbar-user a:hover { background: rgba(255, 255, 255, 0.3); }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        .alert-success { background: #dcfce7; color: #166534; border-left-color: #10b981; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left-color: #ef4444; }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 6px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s ease;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; transform: translateY(-2px); }
        .btn-secondary { background: #e5e7eb; color: #1f2937; }
        .btn-secondary:hover { background: #d1d5db; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            border-top: 4px solid #667eea;
        }

        .card h3 {
            color: #1f2937;
            margin-bottom: 20px;
            font-size: 18px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 15px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 13px;
        }

        input, select, textarea {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }

        tr:hover { background: #f9fafb; }

        .tag {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .tag-present { background: #dcfce7; color: #166534; }
        .tag-absent { background: #fee2e2; color: #991b1b; }
        .tag-late { background: #fef3c7; color: #92400e; }

        .employee-info {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }

        .employee-info strong { color: #667eea; }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        @media (max-width: 768px) {
            .two-column { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .navbar { flex-direction: column; gap: 15px; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2><i class="fas fa-clock"></i> Edit Attendance Records</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($admin['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px;"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

        <!-- Employee Selection -->
        <div class="card">
            <h3><i class="fas fa-search"></i> Step 1: Select Employee</h3>
            
            <form method="GET">
                <div class="form-row">
                    <div class="form-group">
                        <label for="employee_id">Select Employee *</label>
                        <select name="employee_id" id="employee_id" required onchange="this.form.submit();">
                            <option value="">-- Choose Employee --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['employee_id']; ?>" <?php echo ($selected_employee_id === $emp['employee_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['employee_code'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <!-- Date & Time Entry Form -->
        <?php if ($selected_employee): ?>
        <div class="card">
            <h3><i class="fas fa-edit"></i> Step 2: Edit/Add Attendance Record</h3>

            <div class="employee-info">
                <strong><i class="fas fa-user"></i> Employee:</strong> <?php echo htmlspecialchars($selected_employee['first_name'] . ' ' . $selected_employee['last_name']); ?><br>
                <strong><i class="fas fa-id-badge"></i> Code:</strong> <?php echo htmlspecialchars($selected_employee['employee_code']); ?><br>
                <strong><i class="fas fa-briefcase"></i> Position:</strong> <?php echo htmlspecialchars($selected_employee['position'] ?? 'N/A'); ?>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="save_attendance">
                <input type="hidden" name="employee_id" value="<?php echo $selected_employee_id; ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="attendance_date">Attendance Date *</label>
                        <input type="date" name="attendance_date" id="attendance_date" required 
                               value="<?php echo htmlspecialchars($selected_date ?: date('Y-m-d')); ?>"
                               onchange="document.location.href='?employee_id=<?php echo $selected_employee_id; ?>&date=' + this.value;">
                    </div>

                    <div class="form-group">
                        <label for="clock_in">Clock In Time (Optional)</label>
                        <input type="time" name="clock_in" id="clock_in" 
                               value="<?php echo $current_attendance && $current_attendance['clock_in'] ? date('H:i', strtotime($current_attendance['clock_in'])) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="clock_out">Clock Out Time (Optional)</label>
                        <input type="time" name="clock_out" id="clock_out"
                               value="<?php echo $current_attendance && $current_attendance['clock_out'] ? date('H:i', strtotime($current_attendance['clock_out'])) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="status">Attendance Status *</label>
                        <select name="status" id="status" required>
                            <option value="present" <?php echo (($current_attendance['status'] ?? '') === 'present') ? 'selected' : ''; ?>>Present</option>
                            <option value="absent" <?php echo (($current_attendance['status'] ?? '') === 'absent') ? 'selected' : ''; ?>>Absent</option>
                            <option value="late" <?php echo (($current_attendance['status'] ?? '') === 'late') ? 'selected' : ''; ?>>Late</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="remarks">Remarks (Optional)</label>
                        <textarea name="remarks" id="remarks" rows="3" placeholder="Add any notes about this attendance record"><?php echo htmlspecialchars($current_attendance['remarks'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Attendance Record</button>
                    <?php if ($current_attendance): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="delete_attendance">
                            <input type="hidden" name="attendance_id" value="<?php echo $current_attendance['attendance_id']; ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this record?');"><i class="fas fa-trash"></i> Delete Record</button>
                        </form>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Attendance History -->
        <div class="card">
            <h3><i class="fas fa-history"></i> Step 3: Attendance History (Last 30 Days)</h3>

            <?php if (empty($attendance_history)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox" style="font-size: 48px; color: #ccc;"></i>
                    <p style="margin-top: 15px;">No attendance records found for this employee.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Clock In</th>
                                <th>Clock Out</th>
                                <th>Status</th>
                                <th>Remarks</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_history as $record): ?>
                                <tr>
                                    <td><strong><?php echo date('M d, Y', strtotime($record['attendance_date'])); ?></strong></td>
                                    <td><?php echo $record['clock_in'] ? date('H:i', strtotime($record['clock_in'])) : '-'; ?></td>
                                    <td><?php echo $record['clock_out'] ? date('H:i', strtotime($record['clock_out'])) : '-'; ?></td>
                                    <td>
                                        <span class="tag tag-<?php echo $record['status']; ?>">
                                            <?php echo ucfirst($record['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($record['remarks'] ?? '', 0, 50)); ?></td>
                                    <td>
                                        <a href="?employee_id=<?php echo $selected_employee_id; ?>&date=<?php echo urlencode($record['attendance_date']); ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
