<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
check_role(3);

$employee_id = $_SESSION['employee_id'];
$employee = get_employee_info($conn, $employee_id);

// Get all attendance records
$attendance = $conn->query("SELECT * FROM attendance WHERE employee_id = $employee_id ORDER BY attendance_date DESC")->fetch_all(MYSQLI_ASSOC);

// Get all leave requests
$leaves = $conn->query("SELECT lr.*, lt.leave_type_name FROM leave_requests lr JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id WHERE lr.employee_id = $employee_id ORDER BY lr.created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Records - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <h2>My Records</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($employee['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Dashboard</a>

        <!-- Attendance Records -->
        <div class="table-container">
            <h3>📅 Attendance Records</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($attendance)): ?>
                        <?php foreach ($attendance as $record): ?>
                        <tr>
                            <td><?php echo format_date($record['attendance_date']); ?></td>
                            <td><?php echo $record['clock_in'] ? format_datetime($record['clock_in']) : '-'; ?></td>
                            <td><?php echo $record['clock_out'] ? format_datetime($record['clock_out']) : '-'; ?></td>
                            <td>
                                <?php if ($record['status'] === 'present'): ?>
                                    <span class="badge badge-success">Present</span>
                                <?php elseif ($record['status'] === 'late'): ?>
                                    <span class="badge badge-warning">Late</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Absent</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center">No records found</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Leave Requests -->
        <div class="table-container">
            <h3>📋 Leave Requests History</h3>
            <table>
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($leaves)): ?>
                        <?php foreach ($leaves as $leave): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                            <td><?php echo format_date($leave['start_date']); ?></td>
                            <td><?php echo format_date($leave['end_date']); ?></td>
                            <td><?php echo $leave['number_of_days']; ?></td>
                            <td><?php echo htmlspecialchars(substr($leave['reason'], 0, 30)); ?></td>
                            <td>
                                <?php if ($leave['status'] === 'approved'): ?>
                                    <span class="badge badge-success">Approved</span>
                                <?php elseif ($leave['status'] === 'denied'): ?>
                                    <span class="badge badge-danger">Denied</span>
                                <?php else: ?>
                                    <span class="badge badge-pending">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">No leave requests</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>