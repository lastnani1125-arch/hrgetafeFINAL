<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

require_login();
check_role(3);

$employee_id = $_SESSION['employee_id'];
$employee = get_employee_info($conn, $employee_id);

// Get filter from query
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
$valid_statuses = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($status_filter, $valid_statuses)) {
    $status_filter = 'all';
}

// Build query based on filter
$query = "SELECT lr.*, lt.leave_type_name 
          FROM leave_requests lr 
          JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id 
          WHERE lr.employee_id = ?";

if ($status_filter !== 'all') {
    $query .= " AND lr.status = ?";
}

$query .= " ORDER BY lr.created_at DESC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Error: " . $conn->error);
}

if ($status_filter !== 'all') {
    $stmt->bind_param("is", $employee_id, $status_filter);
} else {
    $stmt->bind_param("i", $employee_id);
}

$stmt->execute();
$result = $stmt->get_result();
$leave_requests = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count by status (prepared)
$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM leave_requests WHERE employee_id = ? GROUP BY status");
if (!$stmt) {
    die("Error: " . $conn->error);
}
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$status_counts = [];
while ($row = $result->fetch_assoc()) {
    $status_counts[$row['status']] = $row['count'];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Status - HRGetafe</title>
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

        .container { max-width: 1100px; margin: 0 auto; padding: 30px 20px; }

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
            margin-bottom: 25px;
        }

        .stat-card {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            text-align: center;
        }

        .stat-card.pending { border-left-color: #f59e0b; }
        .stat-card.approved { border-left-color: #10b981; }
        .stat-card.rejected { border-left-color: #ef4444; }

        .stat-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #1f2937;
        }

        .stat-card.pending .stat-number { color: #f59e0b; }
        .stat-card.approved .stat-number { color: #10b981; }
        .stat-card.rejected .stat-number { color: #ef4444; }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e1e1e1;
            flex-wrap: wrap;
        }

        .tab {
            padding: 12px 20px;
            border: none;
            background: none;
            color: #666;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: 0.3s;
            font-size: 14px;
            text-decoration: none;
        }

        .tab:hover { color: #667eea; }
        .tab.active { color: #667eea; border-bottom-color: #667eea; }

        .leave-card {
            background: white;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .leave-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: #667eea;
        }

        .leave-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .leave-title {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-approved { background: #d1fae5; color: #065f46; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }

        .leave-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 15px 0;
            border-top: 1px solid #e1e1e1;
            border-bottom: 1px solid #e1e1e1;
            margin: 15px 0;
        }

        .detail-item {
            font-size: 13px;
        }

        .detail-label {
            color: #666;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .detail-value {
            color: #1f2937;
            font-weight: 600;
        }

        /* Timeline */
        .timeline {
            margin-top: 15px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 6px;
            border-left: 3px solid #667eea;
        }

        .timeline-item {
            display: flex;
            gap: 15px;
            padding: 10px 0;
            position: relative;
        }

        .timeline-item:not(:last-child) {
            padding-bottom: 15px;
            border-bottom: 1px dashed #e1e1e1;
        }

        .timeline-icon {
            width: 30px;
            height: 30px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }

        .timeline-content {
            flex: 1;
        }

        .timeline-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 3px;
        }

        .timeline-date {
            font-size: 12px;
            color: #999;
            margin-bottom: 5px;
        }

        .timeline-note {
            font-size: 13px;
            color: #666;
            background: white;
            padding: 8px 10px;
            border-radius: 4px;
            margin-top: 5px;
        }

        .leave-reason {
            margin-top: 15px;
            padding: 10px;
            background: #f0f4ff;
            border-left: 3px solid #667eea;
            border-radius: 4px;
            font-size: 13px;
            color: #1e40af;
            line-height: 1.6;
        }

        .reason-label {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        @media (max-width: 768px) {
            .container { padding: 20px 15px; }
            .leave-header { flex-direction: column; gap: 10px; }
            .leave-details { grid-template-columns: 1fr; }
            .filter-tabs { gap: 5px; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>📌 Leave Status & History</h2>
    </div>

    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>

        <div class="card">
            <h3>📋 My Leave Requests</h3>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card pending">
                    <div class="stat-label">⏳ Pending</div>
                    <div class="stat-number"><?php echo $status_counts['pending'] ?? 0; ?></div>
                </div>
                <div class="stat-card approved">
                    <div class="stat-label">✅ Approved</div>
                    <div class="stat-number"><?php echo $status_counts['approved'] ?? 0; ?></div>
                </div>
                <div class="stat-card rejected">
                    <div class="stat-label">❌ Rejected</div>
                    <div class="stat-number"><?php echo $status_counts['rejected'] ?? 0; ?></div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="?status=all" class="tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                    All (<?php echo count($leave_requests); ?>)
                </a>
                <a href="?status=pending" class="tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    Pending (<?php echo $status_counts['pending'] ?? 0; ?>)
                </a>
                <a href="?status=approved" class="tab <?php echo $status_filter === 'approved' ? 'active' : ''; ?>">
                    Approved (<?php echo $status_counts['approved'] ?? 0; ?>)
                </a>
                <a href="?status=rejected" class="tab <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">
                    Rejected (<?php echo $status_counts['rejected'] ?? 0; ?>)
                </a>
            </div>

            <!-- Leave Requests List -->
            <?php if (!empty($leave_requests)): ?>
                <?php foreach ($leave_requests as $leave): ?>
                <div class="leave-card">
                    <div class="leave-header">
                        <h4 class="leave-title">
                            📋 <?php echo htmlspecialchars($leave['leave_type_name']); ?>
                        </h4>
                        <?php
                        if ($leave['status'] === 'pending') {
                            echo '<span class="badge badge-pending">⏳ Pending</span>';
                        } elseif ($leave['status'] === 'approved') {
                            echo '<span class="badge badge-approved">✅ Approved</span>';
                        } elseif ($leave['status'] === 'rejected') {
                            echo '<span class="badge badge-rejected">❌ Rejected</span>';
                        }
                        ?>
                    </div>

                    <div class="leave-details">
                        <div class="detail-item">
                            <div class="detail-label">📅 Start Date</div>
                            <div class="detail-value"><?php echo format_date($leave['start_date']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">📅 End Date</div>
                            <div class="detail-value"><?php echo format_date($leave['end_date']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">📊 Number of Days</div>
                            <div class="detail-value"><?php echo $leave['number_of_days']; ?> day(s)</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">📤 Submitted On</div>
                            <div class="detail-value"><?php echo format_datetime($leave['created_at']); ?></div>
                        </div>
                    </div>

                    <!-- Leave Reason -->
                    <div class="leave-reason">
                        <div class="reason-label">📝 Reason for Leave:</div>
                        <?php echo htmlspecialchars($leave['reason']); ?>
                    </div>

                    <!-- Timeline -->
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-icon">📤</div>
                            <div class="timeline-content">
                                <div class="timeline-title">Request Submitted</div>
                                <div class="timeline-date"><?php echo format_datetime($leave['created_at']); ?></div>
                            </div>
                        </div>

                        <?php if ($leave['status'] === 'approved' && !empty($leave['approved_at'])): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon">✅</div>
                            <div class="timeline-content">
                                <div class="timeline-title">Request Approved</div>
                                <div class="timeline-date"><?php echo format_datetime($leave['approved_at']); ?></div>
                                <?php if (!empty($leave['approved_by'])): ?>
                                <div class="timeline-note">
                                    <strong>Approved by:</strong> <?php echo htmlspecialchars($leave['approved_by']); ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($leave['approver_comments'])): ?>
                                <div class="timeline-note">
                                    <strong>Approver's Note:</strong> <?php echo htmlspecialchars($leave['approver_comments']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php elseif ($leave['status'] === 'rejected' && !empty($leave['rejected_at'])): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon">❌</div>
                            <div class="timeline-content">
                                <div class="timeline-title">Request Rejected</div>
                                <div class="timeline-date"><?php echo format_datetime($leave['rejected_at']); ?></div>
                                <?php if (!empty($leave['rejected_by'])): ?>
                                <div class="timeline-note">
                                    <strong>Rejected by:</strong> <?php echo htmlspecialchars($leave['rejected_by']); ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($leave['rejection_reason'])): ?>
                                <div class="timeline-note">
                                    <strong>Reason for Rejection:</strong> <?php echo htmlspecialchars($leave['rejection_reason']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php elseif ($leave['status'] === 'pending'): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon">⏳</div>
                            <div class="timeline-content">
                                <div class="timeline-title">Awaiting HR Review</div>
                                <div class="timeline-date">Processing...</div>
                                <div class="timeline-note">
                                    Your leave request is being reviewed. You will be notified once it has been processed.
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div class="empty-state">
                <p>📭 No leave requests found</p>
                <?php if ($status_filter !== 'all'): ?>
                <p style="font-size: 13px; margin-top: 10px;">
                    No requests with status: <strong><?php echo ucfirst($status_filter); ?></strong>
                </p>
                <a href="?status=all" style="color: #667eea; text-decoration: none; font-weight: 600; margin-top: 10px; display: inline-block;">
                    View all requests →
                </a>
                <?php else: ?>
                <p style="font-size: 13px; margin-top: 10px;">
                    You haven't submitted any leave requests yet.
                </p>
                <a href="apply_leave.php" style="color: #667eea; text-decoration: none; font-weight: 600; margin-top: 10px; display: inline-block;">
                    Apply for leave now →
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Information Box -->
        <div class="card" style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); border-left: 4px solid #667eea;">
            <h3 style="border: none; padding: 0; margin: 0 0 10px 0;">ℹ️ Leave Request Status Guide</h3>
            <div style="color: #666; line-height: 1.8; font-size: 14px;">
                <p style="margin-bottom: 10px;">
                    <strong style="color: #f59e0b;">⏳ Pending:</strong> Your request is awaiting review by the HR Department and your supervisor. This typically takes 2-3 business days.
                </p>
                <p style="margin-bottom: 10px;">
                    <strong style="color: #10b981;">✅ Approved:</strong> Your leave request has been approved. Your leave dates are confirmed in the system. Make sure to plan your work accordingly.
                </p>
                <p>
                    <strong style="color: #ef4444;">❌ Rejected:</strong> Your leave request was not approved. Check the rejection reason provided by the HR Department. You can apply again if you'd like to resubmit your request.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
