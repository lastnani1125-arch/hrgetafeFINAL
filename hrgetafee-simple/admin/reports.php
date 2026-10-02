<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

require_login();
check_role(1);

$admin = get_employee_info($conn, $_SESSION['employee_id']);
$report_type = $_GET['type'] ?? '';
$action = $_GET['action'] ?? ''; // 'view' or 'download' or 'print'
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

// Handle report generation
$report_data = [];
$report_title = '';
$headers = [];

if ($report_type) {
    // Employee Report
    if ($report_type === 'employee') {
        $report_title = 'Employee Report';
        $headers = ['Employee Code', 'Full Name', 'Position', 'Email', 'Phone', 'Salary', 'Date Hired', 'Status', 'Date Generated'];
        
        $stmt = $conn->prepare("SELECT employee_id, employee_code, first_name, last_name, position, email, phone, salary, date_hired, status FROM employees WHERE status = 'active' ORDER BY employee_code");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $report_data[] = [
                    $row['employee_code'],
                    $row['first_name'] . ' ' . $row['last_name'],
                    $row['position'],
                    $row['email'],
                    $row['phone'],
                    number_format($row['salary'], 2),
                    format_date($row['date_hired']),
                    ucfirst($row['status']),
                    date('M d, Y h:i A')
                ];
            }
            $stmt->close();
        }
    }
    // Attendance Report
    elseif ($report_type === 'attendance') {
        $report_title = 'Attendance Report';
        $headers = ['Employee Code', 'Name', 'Date', 'Clock In', 'Clock Out', 'Status', 'Remarks'];
        
        $sql = "SELECT a.attendance_id, a.attendance_date, a.clock_in, a.clock_out, a.status, a.remarks, e.employee_code, CONCAT(e.first_name, ' ', e.last_name) as name FROM attendance a JOIN employees e ON a.employee_id = e.employee_id WHERE 1=1";
        $params = [];
        $types = '';
        
        if ($from) {
            $sql .= " AND a.attendance_date >= ?";
            $params[] = $from;
            $types .= 's';
        }
        if ($to) {
            $sql .= " AND a.attendance_date <= ?";
            $params[] = $to;
            $types .= 's';
        }
        $sql .= " ORDER BY a.attendance_date DESC, e.employee_code ASC";
        
        $stmt = $conn->prepare($sql);
        if ($stmt && !empty($types)) {
            $refs = [&$types];
            foreach ($params as &$param) {
                $refs[] = &$param;
            }
            call_user_func_array([$stmt, 'bind_param'], $refs);
        }
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $report_data[] = [
                    $row['employee_code'],
                    $row['name'],
                    format_date($row['attendance_date']),
                    $row['clock_in'] ? date('h:i A', strtotime($row['clock_in'])) : 'N/A',
                    $row['clock_out'] ? date('h:i A', strtotime($row['clock_out'])) : 'N/A',
                    ucfirst($row['status']),
                    $row['remarks'] ?? '-'
                ];
            }
            $stmt->close();
        }
    }
    // Leave Report
    elseif ($report_type === 'leave') {
        $report_title = 'Leave Report';
        $headers = ['Employee Code', 'Name', 'Leave Type', 'Start Date', 'End Date', 'Days', 'Status', 'Reason'];
        
        $sql = "SELECT lr.leave_id, lr.employee_id, lr.start_date, lr.end_date, lr.number_of_days, lr.status, lr.reason, e.employee_code, CONCAT(e.first_name, ' ', e.last_name) as name, lt.leave_type_name FROM leave_requests lr JOIN employees e ON lr.employee_id = e.employee_id JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id WHERE 1=1";
        $params = [];
        $types = '';
        
        if ($from) {
            $sql .= " AND lr.start_date >= ?";
            $params[] = $from;
            $types .= 's';
        }
        if ($to) {
            $sql .= " AND lr.end_date <= ?";
            $params[] = $to;
            $types .= 's';
        }
        $sql .= " ORDER BY lr.start_date DESC";
        
        $stmt = $conn->prepare($sql);
        if ($stmt && !empty($types)) {
            $refs = [&$types];
            foreach ($params as &$param) {
                $refs[] = &$param;
            }
            call_user_func_array([$stmt, 'bind_param'], $refs);
        }
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $report_data[] = [
                    $row['employee_code'],
                    $row['name'],
                    $row['leave_type_name'],
                    format_date($row['start_date']),
                    format_date($row['end_date']),
                    $row['number_of_days'],
                    ucfirst($row['status']),
                    $row['reason'] ?? '-'
                ];
            }
            $stmt->close();
        }
    }
    // Payroll Report
    elseif ($report_type === 'payroll') {
        $report_title = 'Payroll Report';
        $headers = ['Employee Code', 'Name', 'Month', 'Gross Salary', 'Deductions', 'Net Salary', 'Status'];
        
        $sql = "SELECT p.payroll_id, p.payroll_month, p.payroll_year, p.gross_salary, p.deductions, p.net_salary, p.status, e.employee_code, CONCAT(e.first_name, ' ', e.last_name) as name FROM payroll p JOIN employees e ON p.employee_id = e.employee_id WHERE 1=1";
        $params = [];
        $types = '';
        
        if ($from) {
            $year = (int)substr($from, 0, 4);
            $month = (int)substr($from, 5, 2);
            $sql .= " AND (p.payroll_year > ? OR (p.payroll_year = ? AND p.payroll_month >= ?))";
            $params[] = $year;
            $params[] = $year;
            $params[] = $month;
            $types .= 'iii';
        }
        if ($to) {
            $year = (int)substr($to, 0, 4);
            $month = (int)substr($to, 5, 2);
            $sql .= " AND (p.payroll_year < ? OR (p.payroll_year = ? AND p.payroll_month <= ?))";
            $params[] = $year;
            $params[] = $year;
            $params[] = $month;
            $types .= 'iii';
        }
        $sql .= " ORDER BY p.payroll_year DESC, p.payroll_month DESC";
        
        $stmt = $conn->prepare($sql);
        if ($stmt && !empty($types)) {
            $refs = [&$types];
            foreach ($params as &$param) {
                $refs[] = &$param;
            }
            call_user_func_array([$stmt, 'bind_param'], $refs);
        }
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $month_name = date('F', mktime(0, 0, 0, $row['payroll_month'], 1));
                $report_data[] = [
                    $row['employee_code'],
                    $row['name'],
                    $month_name . ' ' . $row['payroll_year'],
                    number_format($row['gross_salary'], 2),
                    number_format($row['deductions'], 2),
                    number_format($row['net_salary'], 2),
                    ucfirst($row['status'])
                ];
            }
            $stmt->close();
        }
    }
}

// Handle CSV Download
if ($action === 'download' && $report_type && !empty($report_data)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=hrgetafee_' . $report_type . '_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($report_data as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

// Handle Print
if ($action === 'print' && $report_type && !empty($report_data)) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title><?php echo htmlspecialchars($report_title); ?> - HRGetafe</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { text-align: center; color: #333; }
            .meta { text-align: center; color: #666; font-size: 12px; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background: #667eea; color: white; font-weight: bold; }
            tr:nth-child(even) { background: #f9f9f9; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <h1><?php echo htmlspecialchars($report_title); ?></h1>
        <div class="meta">
            Generated: <?php echo date('F d, Y h:i A'); ?>
            <?php if ($from || $to): ?>
                | Period: <?php echo $from ? format_date($from) : 'N/A'; ?> to <?php echo $to ? format_date($to) : 'N/A'; ?>
            <?php endif; ?>
        </div>
        <table>
            <thead>
                <tr>
                    <?php foreach ($headers as $header): ?>
                        <th><?php echo htmlspecialchars($header); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <td><?php echo htmlspecialchars($cell); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="no-print" style="margin-top: 30px; text-align: center;">
            <button onclick="window.print()">🖨️ Print</button>
            <button onclick="window.history.back()">← Back</button>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .report-container { max-width: 1200px; }
        .report-filters { background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .filter-group label { display: block; margin-bottom: 6px; font-weight: 600; }
        .filter-group input, .filter-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .action-buttons { display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap; }
        .btn-view { background: #3b82f6; color: white; padding: 10px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; }
        .btn-view:hover { background: #2563eb; }
        .btn-download { background: #10b981; color: white; padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .btn-download:hover { background: #059669; }
        .btn-print { background: #8b5cf6; color: white; padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .btn-print:hover { background: #7c3aed; }
        .report-display { background: white; border: 1px solid #ddd; border-radius: 8px; overflow: auto; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f7f7fb; font-weight: 600; color: #333; }
        tr:hover { background: #f9f9f9; }
        .no-data { padding: 40px; text-align: center; color: #666; }
        .report-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 20px; }
        .stat { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; border-radius: 8px; text-align: center; }
        .stat-number { font-size: 24px; font-weight: bold; }
        .stat-label { font-size: 12px; margin-top: 5px; opacity: 0.9; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>📊 Reports</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($admin['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container report-container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Dashboard</a>

        <!-- Report Filters -->
        <div class="report-filters">
            <h3>📋 Generate Report</h3>
            <form method="GET">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="type">Report Type *</label>
                        <select name="type" id="type" required onchange="document.getElementById('from').disabled = this.value === 'employee';">
                            <option value="">-- Select Report Type --</option>
                            <option value="employee" <?php echo $report_type === 'employee' ? 'selected' : ''; ?>>👥 Employee Report</option>
                            <option value="attendance" <?php echo $report_type === 'attendance' ? 'selected' : ''; ?>>📅 Attendance Report</option>
                            <option value="leave" <?php echo $report_type === 'leave' ? 'selected' : ''; ?>>📋 Leave Report</option>
                            <option value="payroll" <?php echo $report_type === 'payroll' ? 'selected' : ''; ?>>💰 Payroll Report</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="from">From Date (Optional)</label>
                        <input type="date" name="from" id="from" value="<?php echo htmlspecialchars($from); ?>" <?php echo $report_type === 'employee' ? 'disabled' : ''; ?>>
                    </div>
                    <div class="filter-group">
                        <label for="to">To Date (Optional)</label>
                        <input type="date" name="to" id="to" value="<?php echo htmlspecialchars($to); ?>" <?php echo $report_type === 'employee' ? 'disabled' : ''; ?>>
                    </div>
                </div>
                <div class="action-buttons">
                    <button type="submit" class="btn-view">🔍 View Report</button>
                </div>
            </form>
        </div>

        <!-- Report Display -->
        <?php if ($report_type && !empty($report_data)): ?>
            <div>
                <h3><?php echo htmlspecialchars($report_title); ?></h3>
                <p style="color: #666; font-size: 14px;">Total Records: <strong><?php echo count($report_data); ?></strong></p>
                
                <div class="action-buttons" style="margin-bottom: 20px;">
                    <form method="GET" style="display:inline;">
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($report_type); ?>">
                        <input type="hidden" name="from" value="<?php echo htmlspecialchars($from); ?>">
                        <input type="hidden" name="to" value="<?php echo htmlspecialchars($to); ?>">
                        <input type="hidden" name="action" value="download">
                        <button type="submit" class="btn-download">⬇️ Download CSV</button>
                    </form>
                    <form method="GET" style="display:inline;">
                        <input type="hidden" name="type" value="<?php echo htmlspecialchars($report_type); ?>">
                        <input type="hidden" name="from" value="<?php echo htmlspecialchars($from); ?>">
                        <input type="hidden" name="to" value="<?php echo htmlspecialchars($to); ?>">
                        <input type="hidden" name="action" value="print">
                        <button type="submit" class="btn-print">🖨️ Print</button>
                    </form>
                </div>

                <div class="report-display">
                    <table>
                        <thead>
                            <tr>
                                <?php foreach ($headers as $header): ?>
                                    <th><?php echo htmlspecialchars($header); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <?php foreach ($row as $cell): ?>
                                        <td><?php echo htmlspecialchars($cell); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($report_type): ?>
            <div class="no-data">
                <h3>No Data Found</h3>
                <p>No records match your criteria. Try adjusting the date range or report type.</p>
            </div>
        <?php else: ?>
            <div class="report-stats">
                <div class="stat">
                    <div class="stat-number"><?php echo $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'")->fetch_assoc()['count']; ?></div>
                    <div class="stat-label">Active Employees</div>
                </div>
                <div class="stat">
                    <div class="stat-number"><?php echo $conn->query("SELECT COUNT(*) as count FROM attendance WHERE attendance_date = CURDATE()")->fetch_assoc()['count']; ?></div>
                    <div class="stat-label">Today's Attendance</div>
                </div>
                <div class="stat">
                    <div class="stat-number"><?php echo $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'")->fetch_assoc()['count']; ?></div>
                    <div class="stat-label">Pending Leave</div>
                </div>
                <div class="stat">
                    <div class="stat-number"><?php 
                        $result = $conn->query("SELECT COUNT(*) as count FROM payroll WHERE status = 'pending' AND payroll_month = MONTH(NOW()) AND payroll_year = YEAR(NOW())");
                        echo $result->fetch_assoc()['count']; 
                    ?></div>
                    <div class="stat-label">Pending Payroll</div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
