<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../includes/soft_delete_helper.php';

require_login();
check_role(1);

$admin = get_employee_info($conn, $_SESSION['employee_id']);
$message = '';
$error = '';

// Add holiday
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_holiday'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    
    if (empty($csrf) || !verifyCSRFToken($csrf)) {
        $error = 'Invalid form token. Please reload the page.';
    } else {
        $holiday_name = sanitizeInput($_POST['holiday_name'] ?? '');
        $holiday_date = $_POST['holiday_date'] ?? '';
        
        if (empty($holiday_name) || empty($holiday_date)) {
            $error = 'All fields are required';
        } else {
            $stmt = $conn->prepare("INSERT INTO holidays (holiday_name, holiday_date) VALUES (?, ?)");
            $stmt->bind_param("ss", $holiday_name, $holiday_date);
            
            if ($stmt->execute()) {
                $message = '✓ Holiday added successfully';
                logSecurityEvent($conn, $_SESSION['user_id'], 'ADD_HOLIDAY', "Added holiday: $holiday_name on $holiday_date");
            } else {
                $error = 'Error adding holiday';
            }
            $stmt->close();
        }
    }
}

// Delete holiday (soft delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_holiday'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    
    if (empty($csrf) || !verifyCSRFToken($csrf)) {
        $error = 'Invalid form token. Please reload the page.';
    } else {
        $delete_id = intval($_POST['holiday_id'] ?? 0);
        
        if ($delete_id <= 0) {
            $error = 'Invalid holiday.';
        } else {
            // Get holiday data before soft deleting
            $verify_stmt = $conn->prepare("SELECT holiday_id, holiday_name, holiday_date FROM holidays WHERE holiday_id = ? AND is_deleted = FALSE LIMIT 1");
            if ($verify_stmt) {
                $verify_stmt->bind_param('i', $delete_id);
                $verify_stmt->execute();
                $verify_res = $verify_stmt->get_result();
                $holiday_data = $verify_res->fetch_assoc();
                $verify_stmt->close();
                
                if ($holiday_data) {
                    // Soft delete using helper function
                    $result = softDeleteItem($conn, 'holiday', $delete_id, $holiday_data['holiday_name'], $holiday_data, $_SESSION['user_id']);
                    
                    if ($result['success']) {
                        $message = '✓ Holiday moved to recycle bin.';
                        logSecurityEvent($conn, $_SESSION['user_id'], 'DELETE_HOLIDAY', "Moved to bin: {$holiday_data['holiday_name']}");
                    } else {
                        $error = $result['error'];
                    }
                } else {
                    $error = 'Holiday not found.';
                }
            }
        }
    }
}

// Get active holidays only
$holidays = [];
$stmt = $conn->prepare("SELECT holiday_id, holiday_name, holiday_date FROM holidays WHERE is_deleted = FALSE ORDER BY holiday_date DESC");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    $holidays = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Holidays - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .recycle-link { display: inline-block; margin-left: 10px; padding: 8px 12px; background: #f59e0b; color: white; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 12px; }
        .recycle-link:hover { background: #d97706; }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 16px; }
        .alert-success { background: #ecfdf5; border: 1px solid #d1fae5; color: #065f46; }
        .alert-danger { background: #fff1f2; border: 1px solid #fecaca; color: #7f1d1d; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f7f7fb; font-weight: 600; }
        tr:hover { background: #f9f9f9; }
        .btn-delete { background: #ef4444; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .btn-delete:hover { background: #dc2626; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Holiday Management</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($admin['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 12px;">← Back to Dashboard</a>
        <a href="recycle_bin.php" class="recycle-link">🗑️ Recycle Bin</a>

        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <!-- Add Holiday Form -->
        <div class="card" style="max-width: 400px; margin-bottom: 30px;">
            <h3>➕ Add Holiday</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="holiday_name">Holiday Name</label>
                    <input type="text" name="holiday_name" id="holiday_name" placeholder="e.g., Christmas Day" required>
                </div>

                <div class="form-group">
                    <label for="holiday_date">Date</label>
                    <input type="date" name="holiday_date" id="holiday_date" required>
                </div>

                <button type="submit" name="add_holiday" class="btn btn-primary" style="width: 100%;">Add Holiday</button>
            </form>
        </div>

        <!-- Holidays List -->
        <div class="table-container">
            <h3>📅 All Holidays (<?php echo count($holidays); ?>)</h3>
            <?php if (empty($holidays)): ?>
                <div style="padding: 20px; text-align: center; color: #666;">No holidays found. Create one above!</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Holiday Name</th>
                            <th>Date</th>
                            <th>Days Until</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($holidays as $holiday): ?>
                            <?php 
                                $holiday_time = strtotime($holiday['holiday_date']);
                                $today_time = strtotime(date('Y-m-d'));
                                $days_until = floor(($holiday_time - $today_time) / 86400);
                            ?>
                        <tr>
                            <td><?php echo htmlspecialchars($holiday['holiday_name']); ?></td>
                            <td><?php echo format_date($holiday['holiday_date']); ?></td>
                            <td>
                                <?php if ($days_until < 0): ?>
                                    <span class="badge badge-danger">Passed</span>
                                <?php elseif ($days_until == 0): ?>
                                    <span class="badge badge-warning">Today!</span>
                                <?php else: ?>
                                    <span class="badge badge-success"><?php echo $days_until; ?> days</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Move this holiday to recycle bin?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="delete_holiday" value="1">
                                    <input type="hidden" name="holiday_id" value="<?php echo (int)$holiday['holiday_id']; ?>">
                                    <button class="btn-delete" type="submit">🗑️ Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
