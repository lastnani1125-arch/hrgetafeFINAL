<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

require_login();
check_role(3);

$employee_id = $_SESSION['employee_id'];
$employee = get_employee_info($conn, $employee_id);

// Get filters
$filter_month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$filter_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

$filter_month = max(1, min(12, $filter_month));
$filter_year = max(2020, min(date('Y'), $filter_year));

// Get date range for the month (prepared)
$month_start = date('Y-m-d', mktime(0, 0, 0, $filter_month, 1, $filter_year));
$month_end = date('Y-m-d', mktime(0, 0, 0, $filter_month + 1, 0, $filter_year));

// Get attendance summary (prepared)
$stmt = $conn->prepare(
    "SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days
     FROM attendance
     WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?"
);
$summary = [];
if ($stmt) {
    $stmt->bind_param("iss", $employee_id, $month_start, $month_end);
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Get detailed attendance records (prepared)
$stmt = $conn->prepare(
    "SELECT * FROM attendance
     WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?
     ORDER BY attendance_date DESC"
);
$attendance_records = [];
if ($stmt) {
    $stmt->bind_param("iss", $employee_id, $month_start, $month_end);
    $stmt->execute();
    $attendance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance Summary - HRGetafe</title>
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
        .navbar-user { display: flex; gap: 15px; align-items: center; }
        .navbar-user span { font-size: 14px; }
        .navbar-user a { color: white; text-decoration: none; font-weight: 600; transition: 0.3s; }
        .navbar-user a:hover { opacity: 0.8; }
        
        .container { max-width: 1000px; margin: 0 auto; padding: 30px 20px; }
        
        .card { 
            background: white; 
            padding: 25px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
            margin-bottom: 25px; 
        }
        .card h3 { 
            margin-top: 0; 
            color: #1f2937; 
            border-bottom: 2px solid #667eea; 
            padding-bottom: 10px; 
            font-size: 18px;
        }
        
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 15px; 
            margin-bottom: 25px; 
        }
        .stat-box { 
            background: white;
            padding: 20px; 
            border-radius: 8px; 
            text-align: center;
            border-top: 4px solid #667eea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .stat-box h4 { margin: 0 0 10px 0; font-size: 13px; color: #666; font-weight: 600; }
        .stat-number { font-size: 32px; font-weight: bold; color: #667eea; }
        .stat-box.success { border-top-color: #10b981; }
        .stat-box.success .stat-number { color: #10b981; }
        .stat-box.warning { border-top-color: #f59e0b; }
        .stat-box.warning .stat-number { color: #f59e0b; }
        .stat-box.danger { border-top-color: #ef4444; }
        .stat-box.danger .stat-number { color: #ef4444; }
        
        .filter-section { 
            background: #f9f9f9; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 25px; 
        }
        .filter-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 15px; 
            margin-bottom: 15px; 
        }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-weight: 600; margin-bottom: 8px; font-size: 14px; }
        .filter-group input, .filter-group select { 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn { 
            display: inline-block; 
            padding: 10px 20px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-weight: 600; 
            text-decoration: none;
            transition: 0.3s;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #f0f0f0; color: #333; }
        .btn-primary:hover { background: #764ba2; }
        .btn-secondary:hover { background: #e0e0e0; }
        
        .table-container { 
            background: white; 
            border-radius: 8px; 
            overflow-x: auto; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
        }
        .table-container table { width: 100%; border-collapse: collapse; }
        .table-container th { 
            background: #f8f9fa; 
            padding: 12px; 
            text-align: left; 
            font-weight: 600; 
            border-bottom: 2px solid #e1e1e1; 
            font-size: 13px; 
            color: #1f2937;
        }
        .table-container td { 
            padding: 12px; 
            border-bottom: 1px solid #e1e1e1; 
            font-size: 13px;
            color: #333;
        }
        .table-container tr:hover { background: #f8f9fa; }
        
        .badge { 
            display: inline-block; 
            padding: 5px 10px; 
            border-radius: 4px; 
            font-size: 11px; 
            font-weight: 600; 
        }
        .badge-present { background: #d1fae5; color: #065f46; }
        .badge-late { background: #fef3c7; color: #92400e; }
        .badge-absent { background: #fee2e2; color: #991b1b; }
        
        .summary-box { 
            padding: 15px; 
            background: #f9fafb; 
            border-radius: 6px; 
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }
        .summary-box p { margin: 8px 0; color: #666; font-size: 14px; }

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

        .empty-state { 
            text-align: center; 
            color: #999; 
            padding: 40px 20px; 
        }

        @media (max-width: 768px) {
            .container { padding: 20px 15px; }
            .navbar h2 { font-size: 18px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>📊 My Attendance Summary</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($employee['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-box success">
                <h4>✅ Present</h4>
                <div class="stat-number"><?php echo (int)($summary['present_days'] ?? 0); ?></div>
            </div>
            <div class="stat-box warning">
                <h4>⏰ Late</h4>
                <div class="stat-number"><?php echo (int)($summary['late_days'] ?? 0); ?></div>
            </div>
            <div class="stat-box danger">
                <h4>❌ Absent</h4>
                <div class="stat-number"><?php echo (int)($summary['absent_days'] ?? 0); ?></div>
            </div>
            <div class="stat-box">
                <h4>📊 Total Days</h4>
                <div class="stat-number"><?php echo (int)($summary['total_records'] ?? 0); ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <h4 style="margin-top: 0;">📅 Select Month & Year</h4>
            <form method="GET" style="display: flex; flex-direction: column; gap: 15px;">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>Month</label>
                        <select name="month">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $filter_month == $m ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Year</label>
                        <select name="year">
                            <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $filter_year == $y ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">🔍 Generate Summary</button>
                    <a href="attendance_summary.php" class="btn btn-secondary">↻ Reset</a>
                </div>
            </form>
        </div>

        <!-- Summary Box -->
        <div class="summary-box">
            <h4 style="margin-top: 0; color: #1f2937;">📈 Attendance Summary for <?php echo date('F Y', mktime(0, 0, 0, $filter_month, 1, $filter_year)); ?></h4>
            <p>
                <strong>Total Working Days:</strong> <?php echo (int)($summary['total_records'] ?? 0); ?> |
                <strong>Present:</strong> <?php echo (int)($summary['present_days'] ?? 0); ?> |
                <strong>Late:</strong> <?php echo (int)($summary['late_days'] ?? 0); ?> |
                <strong>Absent:</strong> <?php echo (int)($summary['absent_days'] ?? 0); ?>
            </p>
            <p>
                <strong>Attendance Rate:</strong> 
                <?php 
                    $attendance_rate = ($summary['total_records'] > 0) ? (($summary['present_days'] + $summary['late_days']) / $summary['total_records'] * 100) : 0;
                    echo round($attendance_rate, 1) . '%';
                ?>
            </p>
        </div>

        <!-- Attendance Details Table -->
        <div class="card">
            <h3>📋 Detailed Attendance Records</h3>
            
            <?php if (!empty($attendance_records)): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Duration</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $record): ?>
                        <tr>
                            <td><strong><?php echo format_date($record['attendance_date']); ?></strong></td>
                            <td><?php echo $record['clock_in'] ? date('H:i', strtotime($record['clock_in'])) : '-'; ?></td>
                            <td><?php echo $record['clock_out'] ? date('H:i', strtotime($record['clock_out'])) : '-'; ?></td>
                            <td>
                                <?php 
                                    if ($record['clock_in'] && $record['clock_out']) {
                                        $start = new DateTime($record['clock_in']);
                                        $end = new DateTime($record['clock_out']);
                                        $diff = $start->diff($end);
                                        echo $diff->h . 'h ' . $diff->i . 'm';
                                    } else {
                                        echo '-';
                                    }
                                ?>
                            </td>
                            <td>
                                <?php 
                                    $badge_class = 'badge-present';
                                    $badge_text = '✓ Present';
                                    
                                    if ($record['status'] === 'late') {
                                        $badge_class = 'badge-late';
                                        $badge_text = '⏰ Late';
                                    } elseif ($record['status'] === 'absent') {
                                        $badge_class = 'badge-absent';
                                        $badge_text = '✗ Absent';
                                    }
                                ?>
                                <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p>📭 No attendance records for the selected period</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Information Card -->
        <div class="card" style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);">
            <h3 style="border-bottom-color: #667eea;">ℹ️ Attendance Information</h3>
            <ul style="color: #666; font-size: 13px; line-height: 1.8; padding-left: 20px;">
                <li><strong>Clock In/Out Times:</strong> Your actual clock-in and clock-out times as recorded in the system</li>
                <li><strong>Status:</strong> Present (on time), Late (after scheduled time), or Absent (no record)</li>
                <li><strong>Duration:</strong> Total hours worked (Clock Out time - Clock In time)</li>
                <li><strong>Attendance Rate:</strong> Calculated as (Present + Late) / Total Days × 100%</li>
                <li>For any discrepancies, please contact the HR Department</li>
            </ul>
        </div>
    </div>
</body>
</html>
