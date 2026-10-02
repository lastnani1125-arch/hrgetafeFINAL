<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

require_login();
check_role(3);

$employee_id = $_SESSION['employee_id'];
$employee = get_employee_info($conn, $employee_id);

// Get month and year from query or use current
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Validate month and year
if ($month < 1 || $month > 12) $month = (int)date('m');
if ($year < 2020 || $year > 2100) $year = (int)date('Y');

$start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
$end_date = date('Y-m-t', strtotime($start_date));

// Get attendance records for the month (prepared)
$stmt = $conn->prepare(
    "SELECT * FROM attendance 
     WHERE employee_id = ? AND attendance_date BETWEEN ? AND ? 
     ORDER BY attendance_date ASC"
);
if (!$stmt) {
    die("Error: " . $conn->error);
}
$stmt->bind_param("iss", $employee_id, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$attendance_records = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate statistics
$total_days = 0;
$present_days = 0;
$absent_days = 0;
$late_days = 0;

foreach ($attendance_records as $record) {
    $total_days++;
    if ($record['status'] === 'present') {
        $present_days++;
    } elseif ($record['status'] === 'absent') {
        $absent_days++;
    } elseif ($record['status'] === 'late') {
        $late_days++;
    }
}

$attendance_percentage = $total_days > 0 ? round(($present_days / $total_days) * 100, 2) : 0;

// Previous and next month links
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

$month_name = date('F', mktime(0, 0, 0, $month, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Verdana, sans-serif; background: #f5f7fa; }

        .navbar { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 15px 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .navbar h2 { margin: 0; font-size: 24px; }

        .container { max-width: 1200px; margin: 0 auto; padding: 30px 20px; }

        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #f0f0f0;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            margin-bottom: 20px;
            transition: 0.3s;
        }
        .back-btn:hover { background: #e0e0e0; }

        .card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        .card h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #1f2937;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
            font-size: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            text-align: center;
        }
        .stat-box.present { border-left-color: #10b981; }
        .stat-box.absent { border-left-color: #ef4444; }
        .stat-box.late { border-left-color: #f59e0b; }
        .stat-box.percentage { border-left-color: #3b82f6; }

        .stat-label { 
            font-size: 13px; 
            color: #666; 
            font-weight: 600; 
            margin-bottom: 10px;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #1f2937;
        }
        .stat-box.present .stat-number { color: #10b981; }
        .stat-box.absent .stat-number { color: #ef4444; }
        .stat-box.late .stat-number { color: #f59e0b; }
        .stat-box.percentage .stat-number { color: #3b82f6; }

        .month-selector {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
        }

        .month-nav {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .month-nav a {
            padding: 8px 15px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            transition: 0.3s;
        }
        .month-nav a:hover { background: #764ba2; }

        .month-display {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e1e1e1;
            color: #1f2937;
            font-size: 13px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e1e1e1;
            font-size: 14px;
            color: #333;
        }

        tr:hover { background: #f9fafb; }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-present {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-absent {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-late {
            background: #fef3c7;
            color: #92400e;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h4 {
            color: #1f2937;
            font-size: 20px;
            margin-bottom: 10px;
        }

        .empty-state p {
            margin: 10px 0;
            font-size: 14px;
            color: #666;
            line-height: 1.6;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 20px;
            border-radius: 6px;
            color: #1565c0;
            font-size: 14px;
            line-height: 1.6;
            margin-top: 20px;
        }

        .info-box strong {
            color: #0d47a1;
        }

        .checklist {
            margin-top: 15px;
            text-align: left;
            display: inline-block;
        }

        .checklist-item {
            margin: 8px 0;
            padding-left: 25px;
            position: relative;
        }

        .checklist-item:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #2196f3;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .container { padding: 20px 15px; }
            .navbar h2 { font-size: 18px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .month-selector { flex-direction: column; gap: 15px; }
            table { font-size: 12px; }
            th, td { padding: 10px; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>📊 Attendance</h2>
    </div>

    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>

        <div class="card">
            <h3>📅 Attendance Records</h3>

            <?php if (empty($attendance_records)): ?>
                <!-- No Data State -->
                <div class="empty-state">
                    <div class="empty-state-icon">⏳</div>
                    <h4>No Attendance Data Available Yet</h4>
                    <p>Your attendance records will appear here once the HR Department</p>
                    <p>imports data from the biometric system.</p>
                    
                    <div class="info-box" style="margin-top: 30px;">
                        <strong>📌 What Happens Next?</strong>
                        <div class="checklist">
                            <div class="checklist-item">HR Staff imports biometric attendance data</div>
                            <div class="checklist-item">Your records will automatically appear here</div>
                            <div class="checklist-item">You can then view your attendance summary</div>
                            <div class="checklist-item">Track your monthly attendance statistics</div>
                        </div>
                    </div>

                    <div class="info-box" style="margin-top: 15px; background: #f0f4ff; border-color: #667eea; color: #1e40af;">
                        <strong>💡 Note:</strong> Attendance data is imported periodically from the LGU's biometric system. 
                        Please check back soon or contact the HR Department for more information.
                    </div>
                </div>
            <?php else: ?>
                <!-- Month Selector -->
                <div class="month-selector">
                    <div class="month-nav">
                        <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>">← Previous</a>
                        <div class="month-display"><?php echo $month_name . ' ' . $year; ?></div>
                        <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>">Next →</a>
                    </div>
                    <div style="font-size: 13px; color: #666;">
                        Attendance records imported from biometric system
                    </div>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-box present">
                        <div class="stat-label">Present Days</div>
                        <div class="stat-number"><?php echo $present_days; ?></div>
                    </div>
                    <div class="stat-box absent">
                        <div class="stat-label">Absent Days</div>
                        <div class="stat-number"><?php echo $absent_days; ?></div>
                    </div>
                    <div class="stat-box late">
                        <div class="stat-label">Late Days</div>
                        <div class="stat-number"><?php echo $late_days; ?></div>
                    </div>
                    <div class="stat-box percentage">
                        <div class="stat-label">Attendance Rate</div>
                        <div class="stat-number"><?php echo $attendance_percentage; ?>%</div>
                    </div>
                </div>

                <!-- Attendance Table -->
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>📅 Date</th>
                                <th>⏰ Time In</th>
                                <th>⏰ Time Out</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $record): ?>
                            <tr>
                                <td><?php echo format_date($record['attendance_date']); ?></td>
                                <td>
                                    <?php 
                                    if ($record['clock_in']) {
                                        echo date('h:i A', strtotime($record['clock_in']));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($record['clock_out']) {
                                        echo date('h:i A', strtotime($record['clock_out']));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if ($record['status'] === 'present') {
                                        echo '<span class="badge badge-present">✅ Present</span>';
                                    } elseif ($record['status'] === 'absent') {
                                        echo '<span class="badge badge-absent">❌ Absent</span>';
                                    } elseif ($record['status'] === 'late') {
                                        echo '<span class="badge badge-late">⏳ Late</span>';
                                    } else {
                                        echo '<span class="badge">' . ucfirst($record['status']) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($record['remarks']) {
                                        echo htmlspecialchars($record['remarks']);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Information Box -->
        <div class="card" style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); border-left: 4px solid #667eea;">
            <h3 style="border: none; padding: 0; margin: 0 0 10px 0;">ℹ️ Attendance Information</h3>
            <p style="color: #666; margin: 0; font-size: 14px; line-height: 1.6;">
                This page shows your attendance records that are imported from the LGU's biometric system. 
                Records include clock-in and clock-out times, as well as your attendance status (Present, Late, or Absent).
                For discrepancies or concerns about your attendance, please contact the HR Department.
            </p>
        </div>
    </div>
</body>
</html>
ss