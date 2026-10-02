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

// Handle leave approval/denial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $action_type = $_POST['action_type'] ?? '';
    
    if ($action_type === 'manage_leave_request') {
        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !verifyCSRFToken($csrf)) {
            $error = 'Invalid form token.';
        } else {
            $leave_id = intval($_POST['leave_id'] ?? 0);
            $action = sanitizeInput($_POST['action'] ?? '');
            $denial_reason = sanitizeInput($_POST['denial_reason'] ?? '');
            $staff_note = sanitizeInput($_POST['staff_note'] ?? '');

            if ($leave_id <= 0 || !in_array($action, ['approve', 'deny'])) {
                $error = 'Invalid request.';
            } else {
                $status = $action === 'approve' ? 'approved' : 'denied';
                $admin_id = $_SESSION['user_id'];

                $stmt = $conn->prepare("UPDATE leave_requests SET status = ?, staff_note = ?, denial_reason = ?, approved_by = ?, approved_date = NOW() WHERE leave_id = ?");
                if ($stmt) {
                    $stmt->bind_param("sssii", $status, $staff_note, $denial_reason, $admin_id, $leave_id);
                    if ($stmt->execute()) {
                        $message = $action === 'approve' ? '✓ Leave request approved.' : '✓ Leave request denied.';
                        logSecurityEvent($conn, $_SESSION['user_id'], $action === 'approve' ? 'APPROVE_LEAVE' : 'DENY_LEAVE', "Leave ID: $leave_id - Action: $action");
                        header('Location: manage_leaves.php');
                        exit;
                    } else {
                        $error = 'Failed to update leave request.';
                    }
                    $stmt->close();
                } else {
                    $error = 'Database error.';
                }
            }
        }
    }
    
    // Add leave type
    elseif ($action_type === 'add_leave_type') {
        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !verifyCSRFToken($csrf)) {
            $error = 'Invalid form token.';
        } else {
            $leave_type_name = sanitizeInput($_POST['leave_type_name'] ?? '');
            $max_days = intval($_POST['max_days'] ?? 0);
            $description = sanitizeInput($_POST['description'] ?? '');
            
            if (empty($leave_type_name) || $max_days <= 0) {
                $error = 'Leave type name and max days are required.';
            } else {
                $check_stmt = $conn->prepare("SELECT leave_type_id FROM leave_types WHERE LOWER(leave_type_name) = LOWER(?) AND is_deleted = FALSE LIMIT 1");
                if ($check_stmt) {
                    $check_stmt->bind_param('s', $leave_type_name);
                    $check_stmt->execute();
                    $check_stmt->store_result();
                    
                    if ($check_stmt->num_rows > 0) {
                        $error = 'This leave type already exists.';
                        $check_stmt->close();
                    } else {
                        $check_stmt->close();
                        
                        $stmt = $conn->prepare("INSERT INTO leave_types (leave_type_name, max_days_per_year, description, created_at) VALUES (?, ?, ?, NOW())");
                        if ($stmt) {
                            $stmt->bind_param("sis", $leave_type_name, $max_days, $description);
                            if ($stmt->execute()) {
                                $message = '✓ Leave type added successfully';
                                logSecurityEvent($conn, $_SESSION['user_id'], 'ADD_LEAVE_TYPE', "Added leave type: $leave_type_name");
                                header('Location: manage_leaves.php');
                                exit;
                            } else {
                                $error = 'Error adding leave type: ' . htmlspecialchars($stmt->error);
                            }
                            $stmt->close();
                        } else {
                            $error = 'Database error.';
                        }
                    }
                } else {
                    $error = 'Database error.';
                }
            }
        }
    }
    
    elseif ($action_type === 'delete_leave_type') {
        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || !verifyCSRFToken($csrf)) {
            $error = 'Invalid form token.';
        } else {
            $delete_id = intval($_POST['leave_type_id'] ?? 0);
            
            if ($delete_id <= 0) {
                $error = 'Invalid leave type.';
            } else {
                $verify_stmt = $conn->prepare("SELECT leave_type_id, leave_type_name, max_days_per_year, description FROM leave_types WHERE leave_type_id = ? AND is_deleted = FALSE LIMIT 1");
                if ($verify_stmt) {
                    $verify_stmt->bind_param('i', $delete_id);
                    $verify_stmt->execute();
                    $verify_res = $verify_stmt->get_result();
                    $leave_data = $verify_res->fetch_assoc();
                    $verify_stmt->close();
                    
                    if ($leave_data) {
                        $result = softDeleteItem($conn, 'leave_type', $delete_id, $leave_data['leave_type_name'], $leave_data, $_SESSION['user_id']);
                        
                        if ($result['success']) {
                            $message = '✓ Leave type moved to recycle bin.';
                            logSecurityEvent($conn, $_SESSION['user_id'], 'DELETE_LEAVE_TYPE', "Moved to bin: {$leave_data['leave_type_name']}");
                        } else {
                            $error = $result['error'];
                        }
                    } else {
                        $error = 'Leave type not found.';
                    }
                } else {
                    $error = 'Database error.';
                }
            }
        }
    }
}

// Get active leave types
$leave_types = [];
$stmt = $conn->prepare("SELECT leave_type_id, leave_type_name, max_days_per_year, description FROM leave_types WHERE is_deleted = FALSE ORDER BY leave_type_name");
if ($stmt) {
    $stmt->execute();
    $leave_types = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get pending leave requests
$pending_leaves = [];
$stmt = $conn->prepare(
    "SELECT lr.*, e.first_name, e.last_name, e.employee_code, e.employment_type, lt.leave_type_name
     FROM leave_requests lr
     JOIN employees e ON e.employee_id = lr.employee_id
     JOIN leave_types lt ON lt.leave_type_id = lr.leave_type_id
     WHERE lr.status = 'pending'
     ORDER BY lr.created_at DESC"
);
if ($stmt) {
    $stmt->execute();
    $pending_leaves = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get all leave requests (history)
$all_leaves = [];
$stmt = $conn->prepare(
    "SELECT lr.*, e.first_name, e.last_name, e.employee_code, lt.leave_type_name
     FROM leave_requests lr
     JOIN employees e ON e.employee_id = lr.employee_id
     JOIN leave_types lt ON lt.leave_type_id = lr.leave_type_id
     ORDER BY lr.created_at DESC
     LIMIT 50"
);
if ($stmt) {
    $stmt->execute();
    $all_leaves = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background: #f5f7fb; }
        .card { background: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .alert-danger { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f8fafc; }
        .btn { display: inline-block; padding: 8px 12px; border-radius: 6px; text-decoration: none; border: none; cursor: pointer; font-weight: 600; font-size: 13px; }
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #e5e7eb; color: #1f2937; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-denied { background: #fee2e2; color: #991b1b; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #374151; }
        input, select, textarea { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; }
        textarea { min-height: 70px; }
        .signature-link { display: inline-block; background: #dbeafe; color: #1e40af; padding: 6px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 12px; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .modal-close { float: right; font-size: 28px; font-weight: bold; cursor: pointer; color: #999; }
        .modal-close:hover { color: #333; }
        .signature-preview { max-width: 100%; border: 1px solid #ddd; border-radius: 8px; background: #fff; }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Leave Management</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($admin['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Dashboard</a>

        <div class="card">
            <h3>⏳ Pending Leave Requests (<?php echo count($pending_leaves); ?>)</h3>
            
            <?php if (empty($pending_leaves)): ?>
                <p style="color: #666;">No pending leave requests.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>Dates</th>
                            <th>Days</th>
                            <th>Reason</th>
                            <th>Signature</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_leaves as $leave): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></strong><br>
                                    <code><?php echo htmlspecialchars($leave['employee_code']); ?></code>
                                </td>
                                <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                                <td>
                                    <?php echo format_date($leave['start_date']); ?> to <?php echo format_date($leave['end_date']); ?>
                                </td>
                                <td><?php echo (int)$leave['number_of_days']; ?></td>
                                <td><?php echo htmlspecialchars(substr($leave['reason'], 0, 50)) . (strlen($leave['reason']) > 50 ? '...' : ''); ?></td>
                                <td>
                                    <?php if (!empty($leave['signature_path']) && file_exists($leave['signature_path'])): ?>
                                        <a href="download_signature.php?leave_id=<?php echo (int)$leave['leave_id']; ?>" target="_blank" class="signature-link">View Signature</a>
                                    <?php else: ?>
                                        <span style="color: #999;">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-primary" onclick="openApprovalModal(<?php echo (int)$leave['leave_id']; ?>)">Review</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>📋 Leave Request History</h3>
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th>Approved By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_leaves)): ?>
                        <tr><td colspan="5" style="padding: 16px; color: #999;">No leave requests found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($all_leaves as $leave): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                                <td><?php echo format_date($leave['start_date']); ?> to <?php echo format_date($leave['end_date']); ?></td>
                                <td>
                                    <?php
                                    if ($leave['status'] === 'pending') echo '<span class="badge badge-pending">Pending</span>';
                                    elseif ($leave['status'] === 'approved') echo '<span class="badge badge-approved">Approved</span>';
                                    elseif ($leave['status'] === 'denied') echo '<span class="badge badge-denied">Denied</span>';
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($leave['approved_date'] ? date('M d, Y', strtotime($leave['approved_date'])) : '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3>➕ Add Leave Type</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action_type" value="add_leave_type">
                
                <div class="form-row">
                    <div>
                        <label>Leave Type Name *</label>
                        <input type="text" name="leave_type_name" placeholder="e.g., Vacation Leave" required>
                    </div>
                    <div>
                        <label>Max Days Per Year *</label>
                        <input type="number" name="max_days" min="1" placeholder="e.g., 15" required>
                    </div>
                </div>

                <div style="margin-top: 16px;">
                    <label>Description</label>
                    <textarea name="description" placeholder="Describe this leave type..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top: 16px;">Add Leave Type</button>
            </form>
        </div>

        <div class="card">
            <h3>📋 Leave Types (<?php echo count($leave_types); ?>)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Max Days/Year</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leave_types)): ?>
                        <tr><td colspan="4" style="padding: 16px; color: #999;">No leave types found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($leave_types as $lt): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($lt['leave_type_name']); ?></strong></td>
                                <td><?php echo (int)$lt['max_days_per_year']; ?> days</td>
                                <td><?php echo htmlspecialchars($lt['description'] ?? '-'); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action_type" value="delete_leave_type">
                                        <input type="hidden" name="leave_type_id" value="<?php echo (int)$lt['leave_type_id']; ?>">
                                        <button class="btn btn-danger" type="submit" onclick="return confirm('Move to recycle bin?')">🗑️ Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="approvalModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeApprovalModal()">&times;</span>
            <h3>Review Leave Request</h3>
            
            <form method="POST" id="approvalForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action_type" value="manage_leave_request">
                <input type="hidden" name="leave_id" id="modalLeaveId" value="">

                <div style="margin: 20px 0;">
                    <label>HR Note (if approving)</label>
                    <textarea name="staff_note" placeholder="Optional note for approval..."></textarea>
                </div>

                <div style="margin: 20px 0;">
                    <label>Reason for Denial (if denying) *</label>
                    <textarea name="denial_reason" placeholder="State reason for denial..." required></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="action" value="approve" class="btn btn-success">✅ Approve</button>
                    <button type="submit" name="action" value="deny" class="btn btn-danger">❌ Deny</button>
                    <button type="button" class="btn btn-secondary" onclick="closeApprovalModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openApprovalModal(leaveId) {
            document.getElementById('modalLeaveId').value = leaveId;
            document.getElementById('approvalModal').classList.add('active');
        }

        function closeApprovalModal() {
            document.getElementById('approvalModal').classList.remove('active');
        }

                window.onclick = function(event) {
            const modal = document.getElementById('approvalModal');
            if (event.target === modal) {
                closeApprovalModal();
            }
        }
    </script>
</body>
</html>