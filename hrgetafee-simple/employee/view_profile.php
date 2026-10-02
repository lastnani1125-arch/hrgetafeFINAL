<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

require_login();
check_role(3);

$employee_id = $_SESSION['employee_id'];
$employee = get_employee_info($conn, $employee_id);

if (!$employee) {
    die("Employee not found");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - HRGetafe</title>
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

        /* Profile Header */
        .profile-header {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 40px;
            align-items: center;
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            font-weight: bold;
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .profile-main {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .profile-name {
            font-size: 32px;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
        }

        .profile-subtitle {
            font-size: 16px;
            color: #667eea;
            font-weight: 600;
        }

        .profile-id {
            display: flex;
            gap: 20px;
            font-size: 14px;
            flex-wrap: wrap;
        }

        .profile-id span {
            color: #666;
        }

        .profile-id strong {
            color: #1f2937;
            font-weight: 600;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            width: fit-content;
        }

        .status-active { 
            background: #d1fae5; 
            color: #065f46; 
        }

        .status-inactive { 
            background: #fee2e2; 
            color: #991b1b; 
        }

        /* Information Sections */
        .info-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .info-row {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-label {
            font-size: 12px;
            font-weight: 700;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 16px;
            color: #1f2937;
            font-weight: 500;
        }

        .info-value.highlight {
            color: #667eea;
            font-weight: 600;
        }

        /* Benefits Section */
        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .benefit-card {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .benefit-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .benefit-value {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
        }

        .benefit-unit {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        /* Alert Box */
        .alert-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 20px;
            border-radius: 6px;
            color: #1565c0;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .alert-box strong {
            color: #0d47a1;
        }

        /* Mobile */
        @media (max-width: 768px) {
            .container { padding: 20px 15px; }
            .profile-header { 
                grid-template-columns: 1fr;
                gap: 20px;
                text-align: center;
            }
            .profile-avatar { margin: 0 auto; }
            .profile-id { justify-content: center; flex-direction: column; }
            .info-grid { grid-template-columns: 1fr; }
            .benefits-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>👤 My Profile</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($employee['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)); ?>
            </div>
            <div class="profile-main">
                <h1 class="profile-name"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h1>
                <p class="profile-subtitle"><?php echo htmlspecialchars($employee['position'] ?? 'N/A'); ?></p>
                <div class="profile-id">
                    <span><strong>ID:</strong> <?php echo htmlspecialchars($employee['employee_code']); ?></span>
                </div>
                <div>
                    <span class="status-badge <?php echo $employee['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                        <?php echo ucfirst($employee['status']); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Information Alert -->
        <div class="alert-box">
            <strong>📌 Note:</strong> Your personal information is managed by the HR Department. To update any information, please contact HR or submit a change request through the HR office.
        </div>

        <!-- Personal Information -->
        <div class="info-card">
            <div class="card-title">📋 Personal Information</div>
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">First Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($employee['first_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Last Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($employee['last_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email Address</span>
                    <span class="info-value"><?php echo htmlspecialchars($employee['email'] ?? 'Not provided'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone Number</span>
                    <span class="info-value"><?php echo htmlspecialchars($employee['phone'] ?? 'Not provided'); ?></span>
                </div>
            </div>
        </div>

        <!-- Employment Information -->
        <div class="info-card">
            <div class="card-title">💼 Employment Information</div>
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">Employee ID</span>
                    <span class="info-value highlight"><?php echo htmlspecialchars($employee['employee_code']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Position</span>
                    <span class="info-value"><?php echo htmlspecialchars($employee['position'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Employment Status</span>
                    <span class="info-value"><?php echo ucfirst($employee['status']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date Hired</span>
                    <span class="info-value"><?php echo $employee['date_hired'] ? format_date($employee['date_hired']) : 'N/A'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Years of Service</span>
                    <span class="info-value">
                        <?php 
                            if ($employee['date_hired']) {
                                $hired = new DateTime($employee['date_hired']);
                                $today = new DateTime();
                                $interval = $hired->diff($today);
                                echo $interval->y . ' year' . ($interval->y !== 1 ? 's' : '') . ' ' . $interval->m . ' month' . ($interval->m !== 1 ? 's' : '');
                            } else {
                                echo 'N/A';
                            }
                        ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Compensation Information -->
        <div class="info-card">
            <div class="card-title">💰 Compensation Information</div>
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">Monthly Salary</span>
                    <span class="info-value highlight">₱<?php echo number_format($employee['salary'] ?? 0, 2); ?></span>
                </div>
            </div>
        </div>

        <!-- Leave Benefits -->
        <div class="info-card">
            <div class="card-title">🏖️ Leave Benefits & Balances</div>
            <?php
            $stmt = $conn->prepare("SELECT * FROM leave_types WHERE is_deleted = FALSE ORDER BY leave_type_name ASC");
            $stmt->execute();
            $result = $stmt->get_result();
            $leave_types = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            ?>
            <div class="benefits-grid">
                <?php foreach ($leave_types as $leave): ?>
                    <?php $balance = calculate_leave_balance($conn, $employee_id, $leave['leave_type_id']); ?>
                    <div class="benefit-card">
                        <div class="benefit-label"><?php echo htmlspecialchars($leave['leave_type_name']); ?></div>
                        <div class="benefit-value"><?php echo $balance; ?></div>
                        <div class="benefit-unit">days available (<?php echo $leave['max_days_per_year']; ?> total per year)</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Help Section -->
        <div class="info-card" style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); border-left: 4px solid #667eea;">
            <div class="card-title" style="border-bottom-color: #667eea;">❓ Need Help?</div>
            <p style="color: #666; line-height: 1.8; margin: 0;">
                If you need to update your personal information, file a complaint, or have HR-related concerns, please visit the <strong>HR Department Office</strong> or send an email to the HR office.
                <br><br>
                <strong>HR Department Hours:</strong> Monday to Friday, 8:00 AM - 5:00 PM
                <br>
                <strong>Location:</strong> HR Office, Ground Floor
            </p>
        </div>
    </div>
</body>
</html>
