<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

require_login();
check_role(1);

$admin = get_employee_info($conn, $_SESSION['employee_id']);
$message = '';
$error = '';

// Handle restore
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';
    
    if (empty($csrf) || !verifyCSRFToken($csrf)) {
        $error = 'Invalid or expired form token. Please reload the page.';
    } elseif ($action === 'restore') {
        $bin_id = intval($_POST['bin_id'] ?? 0);
        
        if ($bin_id <= 0) {
            $error = 'Invalid recycle bin entry.';
        } else {
            // Get bin entry details
            $stmt = $conn->prepare("SELECT bin_id, item_type, item_id, item_name, deleted_data FROM recycle_bin WHERE bin_id = ? AND restored = FALSE LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $bin_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $result->num_rows > 0) {
                    $bin_entry = $result->fetch_assoc();
                    $stmt->close();
                    
                    $item_type = $bin_entry['item_type'];
                    $item_id = $bin_entry['item_id'];
                    $deleted_data = json_decode($bin_entry['deleted_data'], true);
                    
                    // Restore based on item type
                    $restored = false;
                    
                    if ($item_type === 'role' && isset($deleted_data['role_id'])) {
                        $sql = "UPDATE roles SET is_deleted = FALSE, deleted_at = NULL, deleted_by = NULL WHERE role_id = ?";
                        $restore_stmt = $conn->prepare($sql);
                        if ($restore_stmt) {
                            $restore_stmt->bind_param('i', $item_id);
                            $restored = $restore_stmt->execute();
                            $restore_stmt->close();
                        }
                    } elseif ($item_type === 'department' && isset($deleted_data['dept_id'])) {
                        $sql = "UPDATE departments SET is_deleted = FALSE, deleted_at = NULL, deleted_by = NULL WHERE dept_id = ?";
                        $restore_stmt = $conn->prepare($sql);
                        if ($restore_stmt) {
                            $restore_stmt->bind_param('i', $item_id);
                            $restored = $restore_stmt->execute();
                            $restore_stmt->close();
                        }
                    } elseif ($item_type === 'holiday' && isset($deleted_data['holiday_id'])) {
                        $sql = "UPDATE holidays SET is_deleted = FALSE, deleted_at = NULL, deleted_by = NULL WHERE holiday_id = ?";
                        $restore_stmt = $conn->prepare($sql);
                        if ($restore_stmt) {
                            $restore_stmt->bind_param('i', $item_id);
                            $restored = $restore_stmt->execute();
                            $restore_stmt->close();
                        }
                    } elseif ($item_type === 'leave_type' && isset($deleted_data['leave_type_id'])) {
                        $sql = "UPDATE leave_types SET is_deleted = FALSE, deleted_at = NULL, deleted_by = NULL WHERE leave_type_id = ?";
                        $restore_stmt = $conn->prepare($sql);
                        if ($restore_stmt) {
                            $restore_stmt->bind_param('i', $item_id);
                            $restored = $restore_stmt->execute();
                            $restore_stmt->close();
                        }
                    } elseif ($item_type === 'permission' && isset($deleted_data['permission_id'])) {
                        $sql = "UPDATE role_permissions SET is_deleted = FALSE, deleted_at = NULL, deleted_by = NULL WHERE permission_id = ?";
                        $restore_stmt = $conn->prepare($sql);
                        if ($restore_stmt) {
                            $restore_stmt->bind_param('i', $item_id);
                            $restored = $restore_stmt->execute();
                            $restore_stmt->close();
                        }
                    }
                    
                    if ($restored) {
                        // Mark as restored in recycle bin
                        $update_stmt = $conn->prepare("UPDATE recycle_bin SET restored = TRUE, restored_by = ?, restored_at = NOW() WHERE bin_id = ?");
                        if ($update_stmt) {
                            $user_id = $_SESSION['user_id'];
                            $update_stmt->bind_param('ii', $user_id, $bin_id);
                            $update_stmt->execute();
                            $update_stmt->close();
                        }
                        
                        $message = "✓ '{$bin_entry['item_name']}' has been restored successfully.";
                        logSecurityEvent($conn, $_SESSION['user_id'], 'RESTORE_FROM_BIN', "Restored {$item_type}: {$bin_entry['item_name']}");
                    } else {
                        $error = "Error restoring item. Please try again.";
                    }
                } else {
                    $stmt->close();
                    $error = 'Item not found in recycle bin or already restored.';
                }
            } else {
                $error = 'Database error: ' . htmlspecialchars($conn->error);
            }
        }
    } elseif ($action === 'permanent_delete') {
        $bin_id = intval($_POST['bin_id'] ?? 0);
        
        if ($bin_id <= 0) {
            $error = 'Invalid recycle bin entry.';
        } else {
            $stmt = $conn->prepare("SELECT bin_id, item_type, item_id, item_name FROM recycle_bin WHERE bin_id = ? AND restored = FALSE LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $bin_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $result->num_rows > 0) {
                    $bin_entry = $result->fetch_assoc();
                    $stmt->close();
                    
                    $item_type = $bin_entry['item_type'];
                    $item_id = $bin_entry['item_id'];
                    
                    $deleted = false;
                    
                    // Permanently delete based on item type
                    if ($item_type === 'role') {
                        $del_stmt = $conn->prepare("DELETE FROM roles WHERE role_id = ?");
                        if ($del_stmt) {
                            $del_stmt->bind_param('i', $item_id);
                            $deleted = $del_stmt->execute();
                            $del_stmt->close();
                        }
                    } elseif ($item_type === 'department') {
                        $del_stmt = $conn->prepare("DELETE FROM departments WHERE dept_id = ?");
                        if ($del_stmt) {
                            $del_stmt->bind_param('i', $item_id);
                            $deleted = $del_stmt->execute();
                            $del_stmt->close();
                        }
                    } elseif ($item_type === 'holiday') {
                        $del_stmt = $conn->prepare("DELETE FROM holidays WHERE holiday_id = ?");
                        if ($del_stmt) {
                            $del_stmt->bind_param('i', $item_id);
                            $deleted = $del_stmt->execute();
                            $del_stmt->close();
                        }
                    } elseif ($item_type === 'leave_type') {
                        $del_stmt = $conn->prepare("DELETE FROM leave_types WHERE leave_type_id = ?");
                        if ($del_stmt) {
                            $del_stmt->bind_param('i', $item_id);
                            $deleted = $del_stmt->execute();
                            $del_stmt->close();
                        }
                    } elseif ($item_type === 'permission') {
                        $del_stmt = $conn->prepare("DELETE FROM role_permissions WHERE permission_id = ?");
                        if ($del_stmt) {
                            $del_stmt->bind_param('i', $item_id);
                            $deleted = $del_stmt->execute();
                            $del_stmt->close();
                        }
                    }
                    
                    if ($deleted) {
                        // Mark as permanently deleted
                        $update_stmt = $conn->prepare("UPDATE recycle_bin SET permanent_delete = TRUE WHERE bin_id = ?");
                        if ($update_stmt) {
                            $update_stmt->bind_param('i', $bin_id);
                            $update_stmt->execute();
                            $update_stmt->close();
                        }
                        
                        $message = "✓ '{$bin_entry['item_name']}' has been permanently deleted.";
                        logSecurityEvent($conn, $_SESSION['user_id'], 'PERMANENT_DELETE', "Permanently deleted {$item_type}: {$bin_entry['item_name']}");
                    } else {
                        $error = "Error permanently deleting item.";
                    }
                } else {
                    $stmt->close();
                    $error = 'Item not found in recycle bin.';
                }
            } else {
                $error = 'Database error: ' . htmlspecialchars($conn->error);
            }
        }
    }
}

// Get deleted items from recycle bin (not restored or permanently deleted)
$bin_items = [];
$stmt = $conn->prepare("SELECT bin_id, item_type, item_id, item_name, deleted_by, deleted_at, restored FROM recycle_bin WHERE restored = FALSE AND permanent_delete = FALSE ORDER BY deleted_at DESC");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $bin_items = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

// Get deletion history
$history = [];
$stmt = $conn->prepare("SELECT bin_id, item_type, item_id, item_name, deleted_by, deleted_at, restored, restored_by, restored_at, permanent_delete FROM recycle_bin ORDER BY deleted_at DESC LIMIT 100");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $history = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

// Get user names for display
function get_username($conn, $user_id) {
    if (!$user_id) return 'N/A';
    $stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ? LIMIT 1");
    if (!$stmt) return 'Unknown';
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['username'] ?? 'Unknown';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycle Bin - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .recycle-container { max-width: 1200px; margin: 0 auto; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #eee; }
        .tab-btn { background: none; border: none; padding: 12px 20px; cursor: pointer; font-size: 14px; font-weight: 600; color: #666; border-bottom: 3px solid transparent; transition: all 0.3s; }
        .tab-btn.active { color: #667eea; border-bottom-color: #667eea; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .bin-table { width: 100%; border-collapse: collapse; background: white; }
        .bin-table thead th { background: #f7f7fb; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #eee; }
        .bin-table tbody td { padding: 12px; border-bottom: 1px solid #eee; }
        .bin-table tbody tr:hover { background: #f9f9f9; }
        .item-type-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-role { background: #dbeafe; color: #1e40af; }
        .badge-department { background: #fce7f3; color: #831843; }
        .badge-holiday { background: #dbeafe; color: #1e40af; }
        .badge-leave_type { background: #fef3c7; color: #92400e; }
        .badge-permission { background: #e0e7ff; color: #3730a3; }
        .deleted-by { font-size: 12px; color: #666; }
        .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-restore { background: #10b981; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .btn-restore:hover { background: #059669; }
        .btn-delete-permanent { background: #ef4444; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .btn-delete-permanent:hover { background: #dc2626; }
        .empty-state { text-align: center; padding: 40px 20px; color: #999; }
        .empty-state svg { width: 64px; height: 64px; margin-bottom: 20px; opacity: 0.5; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; }
        .alert-success { background: #ecfdf5; border: 1px solid #d1fae5; color: #065f46; }
        .alert-error { background: #fff1f2; border: 1px solid #fecaca; color: #7f1d1d; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .status-restored { background: #d1fae5; color: #065f46; }
        .status-permanent { background: #fee2e2; color: #7f1d1d; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>🗑️ Recycle Bin</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($admin['first_name'] ?? 'Admin'); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container recycle-container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Dashboard</a>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('deleted')">🗑️ Deleted Items (<?php echo count($bin_items); ?>)</button>
            <button class="tab-btn" onclick="switchTab('history')">📋 Deletion History</button>
        </div>

        <!-- Tab 1: Deleted Items -->
        <div id="deleted" class="tab-content active">
            <?php if (empty($bin_items)): ?>
                <div class="empty-state">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                    <p>No deleted items in recycle bin</p>
                </div>
            <?php else: ?>
                <table class="bin-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th>Deleted By</th>
                            <th>Deleted At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bin_items as $item): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                <td>
                                    <span class="item-type-badge badge-<?php echo htmlspecialchars($item['item_type']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $item['item_type'])); ?>
                                    </span>
                                </td>
                                <td><span class="deleted-by"><?php echo htmlspecialchars(get_username($conn, $item['deleted_by'])); ?></span></td>
                                <td><?php echo format_datetime($item['deleted_at']); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="bin_id" value="<?php echo (int)$item['bin_id']; ?>">
                                            <button type="submit" class="btn-restore" onclick="return confirm('Restore this item?')">✓ Restore</button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="permanent_delete">
                                            <input type="hidden" name="bin_id" value="<?php echo (int)$item['bin_id']; ?>">
                                            <button type="submit" class="btn-delete-permanent" onclick="return confirm('Permanently delete this item? This action cannot be undone.')">✕ Permanent Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Tab 2: Deletion History -->
        <div id="history" class="tab-content">
            <?php if (empty($history)): ?>
                <div class="empty-state">
                    <p>No deletion history</p>
                </div>
            <?php else: ?>
                <table class="bin-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th>Deleted By</th>
                            <th>Deleted At</th>
                            <th>Status</th>
                            <th>Restored By</th>
                            <th>Restored At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $item): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                <td>
                                    <span class="item-type-badge badge-<?php echo htmlspecialchars($item['item_type']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $item['item_type'])); ?>
                                    </span>
                                </td>
                                <td><span class="deleted-by"><?php echo htmlspecialchars(get_username($conn, $item['deleted_by'])); ?></span></td>
                                <td><?php echo format_datetime($item['deleted_at']); ?></td>
                                <td>
                                    <?php if ($item['permanent_delete']): ?>
                                        <span class="status-badge status-permanent">Permanently Deleted</span>
                                    <?php elseif ($item['restored']): ?>
                                        <span class="status-badge status-restored">Restored</span>
                                    <?php else: ?>
                                        <span class="status-badge" style="background: #fef3c7; color: #92400e;">In Bin</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars(get_username($conn, $item['restored_by'])); ?></td>
                                <td><?php echo $item['restored_at'] ? format_datetime($item['restored_at']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>