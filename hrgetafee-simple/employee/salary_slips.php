<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

require_login();
check_role(3);

$employee_id = $_SESSION['employee_id'];
$employee = get_employee_info($conn, $employee_id);
$message = '';

// Get all payroll records for this employee (PREPARED - Fixed SQL injection)
$stmt = $conn->prepare(
    "SELECT p.*, pd.detail_type, pd.detail_name, pd.amount
     FROM payroll p 
     LEFT JOIN payroll_details pd ON p.payroll_id = pd.payroll_id
     WHERE p.employee_id = ? 
     ORDER BY p.payroll_year DESC, p.payroll_month DESC"
);
if (!$stmt) {
    die("Error: " . $conn->error);
}
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$payroll_records = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group by payroll_id
$payroll_by_id = [];
foreach ($payroll_records as $record) {
    $pid = $record['payroll_id'];
    if (!isset($payroll_by_id[$pid])) {
        $payroll_by_id[$pid] = [
            'payroll_id' => $record['payroll_id'],
            'payroll_month' => $record['payroll_month'],
            'payroll_year' => $record['payroll_year'],
            'gross_salary' => $record['gross_salary'],
            'deductions' => $record['deductions'],
            'net_salary' => $record['net_salary'],
            'status' => $record['status'],
            'created_at' => $record['created_at'],
            'details' => []
        ];
    }
    
    if ($record['detail_type']) {
        $payroll_by_id[$pid]['details'][] = [
            'type' => $record['detail_type'],
            'name' => $record['detail_name'],
            'amount' => $record['amount']
        ];
    }
}

$payroll_records = array_values($payroll_by_id);

// Handle view slip
$selected_payroll = null;
if (isset($_GET['payroll_id'])) {
    $payroll_id = (int)$_GET['payroll_id'];
    foreach ($payroll_records as $record) {
        if ($record['payroll_id'] == $payroll_id) {
            $selected_payroll = $record;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - HRGetafe</title>
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
        .navbar-user a { color: white; text-decoration: none; font-weight: 600; }

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

        .slips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .slip-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            text-decoration: none;
        }

        .slip-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .slip-card h4 {
            margin: 0 0 10px 0;
            font-size: 18px;
            font-weight: 600;
        }

        .slip-card p {
            margin: 8px 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .slip-card .amount {
            font-size: 24px;
            font-weight: bold;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.3);
        }

        .slip-card .status {
            display: inline-block;
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            font-size: 12px;
            margin-top: 10px;
            font-weight: 600;
        }

        /* Detail View */
        .salary-slip {
            background: white;
            padding: 40px;
            border: 2px solid #667eea;
            border-radius: 8px;
            max-width: 900px;
            margin: 30px auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .slip-header {
            text-align: center;
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .slip-header h2 {
            margin: 0;
            color: #667eea;
            font-size: 22px;
            font-weight: 700;
        }

        .slip-header p {
            margin: 5px 0;
            color: #666;
            font-size: 12px;
        }

        .slip-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .slip-row.total {
            border-bottom: 2px solid #667eea;
            font-weight: bold;
            padding: 15px 0;
            margin: 10px 0;
        }

        .slip-section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }

        .slip-section h4 {
            margin: 0 0 10px 0;
            color: #667eea;
            font-weight: bold;
            font-size: 13px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .no-records {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .info-box p {
            margin: 0;
            color: #1976d2;
        }

        @media print {
            body > *:not(.salary-slip) {
                display: none;
            }
            .salary-slip {
                display: block !important;
                max-width: 100%;
                box-shadow: none;
                border: none;
                padding: 20px;
                margin: 0;
            }
            .action-buttons {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .container { padding: 20px 15px; }
            .navbar h2 { font-size: 18px; }
            .slips-grid { grid-template-columns: 1fr; }
            .salary-slip { padding: 20px; }
            .action-buttons { flex-direction: column; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>💰 Payslip</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($employee['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>

        <?php if ($selected_payroll): ?>
            <!-- Detail View -->
            <div class="salary-slip">
                <div class="slip-header">
                    <h2>SALARY SLIP</h2>
                    <p>Municipality of Getafe - Local Government Unit</p>
                    <p>HRGetafe Payroll System</p>
                </div>

                <!-- Employee Information -->
                <div class="slip-row">
                    <strong>Employee Name:</strong>
                    <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong>
                </div>
                <div class="slip-row">
                    <strong>Employee Code:</strong>
                    <strong><?php echo htmlspecialchars($employee['employee_code']); ?></strong>
                </div>
                <div class="slip-row">
                    <strong>Position:</strong>
                    <strong><?php echo htmlspecialchars($employee['position']); ?></strong>
                </div>
                <div class="slip-row">
                    <strong>Pay Period:</strong>
                    <strong><?php echo date('F', mktime(0, 0, 0, $selected_payroll['payroll_month'], 1)) . ' ' . $selected_payroll['payroll_year']; ?></strong>
                </div>
                <div class="slip-row">
                    <strong>Slip Generated:</strong>
                    <strong><?php echo date('F d, Y h:i A', strtotime($selected_payroll['created_at'])); ?></strong>
                </div>

                <!-- Earnings -->
                <div class="slip-section">
                    <h4>EARNINGS</h4>
                    <div class="slip-row">
                        <span>Gross Salary:</span>
                        <span style="color: #2e7d32; font-weight: bold;">₱<?php echo number_format($selected_payroll['gross_salary'], 2); ?></span>
                    </div>
                </div>

                <!-- Deductions Detail -->
                <div class="slip-section">
                    <h4>DEDUCTIONS</h4>
                    <?php
                        $deductions_total = 0;
                        foreach ($selected_payroll['details'] as $detail) {
                            if ($detail['type'] === 'deduction') {
                                $deductions_total += $detail['amount'];
                                echo '<div class="slip-row" style="color: #c62828;">';
                                echo '<span>' . htmlspecialchars($detail['name']) . '</span>';
                                echo '<span>-₱' . number_format($detail['amount'], 2) . '</span>';
                                echo '</div>';
                            }
                        }
                    ?>
                </div>

                <!-- Summary -->
                <div class="slip-section">
                    <div class="slip-row total">
                        <span>TOTAL GROSS EARNINGS:</span>
                        <span style="color: #2e7d32;">₱<?php echo number_format($selected_payroll['gross_salary'], 2); ?></span>
                    </div>
                    <div class="slip-row total">
                        <span>TOTAL DEDUCTIONS:</span>
                        <span style="color: #c62828;">₱<?php echo number_format($selected_payroll['deductions'], 2); ?></span>
                    </div>
                    <div class="slip-row total" style="font-size: 14px; background: #f0f0f0; padding: 15px; margin: 15px 0; border-radius: 4px;">
                        <span>NET TAKE HOME PAY:</span>
                        <span style="color: #2e7d32; font-size: 16px;">₱<?php echo number_format($selected_payroll['net_salary'], 2); ?></span>
                    </div>
                </div>

                <!-- Payment Status -->
                <div class="slip-section">
                    <div class="slip-row">
                        <strong>Payment Status:</strong>
                        <strong style="color: <?php echo ($selected_payroll['status'] === 'paid' ? '#2e7d32' : '#ff9800'); ?>;">
                            <?php echo ucfirst($selected_payroll['status']); ?>
                        </strong>
                    </div>
                </div>

                <!-- Footer -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #667eea; text-align: center; color: #999; font-size: 11px;">
                    <p>This is an automated salary slip. For questions regarding your compensation, please contact the HR Department.</p>
                    <p>Generated by HRGetafe Payroll System</p>
                </div>
            </div>

            <div class="action-buttons">
                <button onclick="window.print()" class="btn btn-primary">🖨️ Print Slip</button>
                <a href="salary_slips.php" class="btn btn-secondary">← Back to Slips</a>
            </div>
        <?php else: ?>
            <!-- List View -->
            <div class="card">
                <h3>💰 My Payslips</h3>

                <div class="info-box">
                    <p>ℹ️ Click on any salary slip to view details and print. Your slips are automatically generated when payroll is processed.</p>
                </div>

                <?php if (!empty($payroll_records)): ?>
                    <div class="slips-grid">
                        <?php foreach ($payroll_records as $record): ?>
                            <a href="?payroll_id=<?php echo $record['payroll_id']; ?>" class="slip-card">
                                <h4>📄 <?php echo date('F Y', mktime(0, 0, 0, $record['payroll_month'], 1, $record['payroll_year'])); ?></h4>
                                <p>Gross: ₱<?php echo number_format($record['gross_salary'], 2); ?></p>
                                <p>Deductions: ₱<?php echo number_format($record['deductions'], 2); ?></p>
                                <div class="amount">₱<?php echo number_format($record['net_salary'], 2); ?></div>
                                <div class="status">
                                    <?php if ($record['status'] === 'paid'): ?>
                                        ✓ Paid
                                    <?php elseif ($record['status'] === 'processed'): ?>
                                        ⏳ Processed
                                    <?php else: ?>
                                        ⧖ Pending
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-records">
                        <p>📭 No salary slips available yet.</p>
                        <p style="font-size: 13px; margin-top: 10px;">Salary slips will appear here once payroll is processed.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
