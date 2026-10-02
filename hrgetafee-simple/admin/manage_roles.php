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

// Get all roles (only active ones)
$roles_result = $conn->query("SELECT * FROM roles WHERE is_deleted = FALSE ORDER BY role_id");
$roles = $roles_result ? $roles_result->fetch_all(MYSQLI_ASSOC) : [];

// Get permissions grouped by role (only active ones)
$permissions_by_role = [];
foreach ($roles as $role) {
    $stmt = $conn->prepare("SELECT permission_id, role_id, permission_name, description FROM role_permissions WHERE role_id = ? AND is_deleted = FALSE ORDER BY permission_name");
    if ($stmt) {
        $stmt->bind_param('i', $role['role_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        $permissions_by_role[$role['role_id']] = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
}

// Handle permission updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CSRF check - REQUIRED for all POST requests
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($csrf) || !verifyCSRFToken($csrf)) {
        $error = 'Invalid or expired form token. Please reload the page and try again.';
    } elseif ($action === 'add_permission') {
        $role_id = intval($_POST['role_id'] ?? 0);
        $permission_name = sanitizeInput($_POST['permission_name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        
        if ($role_id <= 0) {
            $error = 'Invalid role selected.';
        } elseif (empty($permission_name)) {
            $error = 'Permission name is required.';
        } elseif (strlen($permission_name) < 3) {
            $error = 'Permission name must be at least 3 characters.';
        } elseif (!preg_match('/^[a-z0-9_]+$/', str_replace(' ', '', $permission_name))) {
            $error = 'Permission name can only contain letters, numbers, spaces, and underscores.';
        } else {
            // Check if permission already exists for this role (only active ones)
            $check_stmt = $conn->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ? AND LOWER(permission_name) = LOWER(?) AND is_deleted = FALSE LIMIT 1");
            if ($check_stmt) {
                $lower_perm = strtolower($permission_name);
                $check_stmt->bind_param('is', $role_id, $lower_perm);
                $check_stmt->execute();
                $check_stmt->store_result();
                
                if ($check_stmt->num_rows > 0) {
                    $error = 'This permission already exists for this role.';
                    $check_stmt->close();
                } else {
                    $check_stmt->close();
                    
                    // Insert new permission using prepared statement
                    $insert_stmt = $conn->prepare("INSERT INTO role_permissions (role_id, permission_name, description, created_at) VALUES (?, ?, ?, NOW())");
                    if ($insert_stmt) {
                        $insert_stmt->bind_param('iss', $role_id, $permission_name, $description);
                        
                        if ($insert_stmt->execute()) {
                            $message = 'Permission added successfully.';
                            logSecurityEvent($conn, $_SESSION['user_id'], 'ADD_PERMISSION', "Added permission '$permission_name' to role_id $role_id");
                            header('Location: manage_roles.php');
                            exit;
                        } else {
                            $error = 'Error adding permission: ' . htmlspecialchars($insert_stmt->error);
                        }
                        $insert_stmt->close();
                    } else {
                        $error = 'Database error: ' . htmlspecialchars($conn->error);
                    }
                }
            } else {
                $error = 'Database error: ' . htmlspecialchars($conn->error);
            }
        }
    } elseif ($action === 'delete_permission') {
        $permission_id = intval($_POST['permission_id'] ?? 0);
        
        if ($permission_id <= 0) {
            $error = 'Invalid permission ID.';
        } else {
            // Verify permission exists before soft deleting
            $verify_stmt = $conn->prepare("SELECT permission_id, permission_name, role_id FROM role_permissions WHERE permission_id = ? AND is_deleted = FALSE LIMIT 1");
            if ($verify_stmt) {
                $verify_stmt->bind_param('i', $permission_id);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                
                if ($verify_result && $verify_result->num_rows > 0) {
                    $perm_data = $verify_result->fetch_assoc();
                    $verify_stmt->close();
                    
                    // Soft delete the permission using helper function
                    $deleted_data = $perm_data;
                    $result = softDeleteItem($conn, 'permission', $permission_id, $perm_data['permission_name'], $deleted_data, $_SESSION['user_id']);
                    
                    if ($result['success']) {
                        $message = 'Permission moved to recycle bin.';
                        logSecurityEvent($conn, $_SESSION['user_id'], 'DELETE_PERMISSION', "Moved to bin: '{$perm_data['permission_name']}' from role_id {$perm_data['role_id']}");
                        header('Location: manage_roles.php');
                        exit;
                    } else {
                        $error = $result['error'];
                    }
                } else {
                    $verify_stmt->close();
                    $error = 'Permission not found.';
                }
            } else {
                $error = 'Database error: ' . htmlspecialchars($conn->error);
            }
        }
    } else {
        $error = 'Invalid action.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Roles & Permissions - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .permission-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-top: 20px; }
        .permission-card { border: 1px solid #ddd; padding: 20px; border-radius: 8px; background: #f9f9f9; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .permission-card h3 { margin-top: 0; color: #1f2937; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
        .permission-card p { margin: 5px 0; color: #666; font-size: 14px; }
        .permission-list { list-style: none; padding: 0; margin: 15px 0; max-height: 400px; overflow-y: auto; }
        .permission-list li { padding: 10px; background: white; margin: 8px 0; border-left: 4px solid #10b981; display: flex; justify-content: space-between; align-items: center; border-radius: 4px; }
        .permission-item-text { flex: 1; }
        .permission-item-text strong { display: block; color: #1f2937; }
        .permission-item-text small { color: #666; }
        .btn-delete { background: #ef4444; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; transition: background 0.2s; }
        .btn-delete:hover { background: #dc2626; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); }
        .modal.show { display: block; }
        .modal-content { background-color: #fff; margin: 5% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 500px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .close { color: #999; float: right; font-size: 28px; font-weight: bold; cursor: pointer; transition: color 0.2s; }
        .close:hover { color: #000; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #1f2937; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; font-family: inherit; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-actions { display: flex; gap: 10px; margin-top: 20px; }
        .form-actions button { flex: 1; padding: 10px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-secondary { background: #64748b; color: white; }
        .btn-secondary:hover { background: #475569; }
        .no-permissions { color: #999; font-style: italic; padding: 10px; text-align: center; background: #f3f4f6; border-radius: 4px; }
        .recycle-link { display: inline-block; margin-top: 20px; padding: 10px 16px; background: #f59e0b; color: white; text-decoration: none; border-radius: 4px; font-weight: 600; }
        .recycle-link:hover { background: #d97706; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Manage Roles & Permissions</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($admin['first_name'] ?? 'Admin'); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Dashboard</a>
        <a href="recycle_bin.php" class="recycle-link">🗑️ Recycle Bin</a>

        <div class="permission-grid">
            <?php foreach ($roles as $role): ?>
                <div class="permission-card">
                    <h3><?php echo htmlspecialchars($role['role_name']); ?></h3>
                    <p><small><?php echo htmlspecialchars($role['description'] ?? 'No description'); ?></small></p>
                    
                    <h4 style="margin-top: 15px; margin-bottom: 10px;">Permissions (<?php echo count($permissions_by_role[$role['role_id']]); ?>):</h4>
                    <?php if (!empty($permissions_by_role[$role['role_id']])): ?>
                        <ul class="permission-list">
                            <?php foreach ($permissions_by_role[$role['role_id']] as $perm): ?>
                                <li>
                                    <div class="permission-item-text">
                                        <strong><?php echo htmlspecialchars($perm['permission_name']); ?></strong>
                                        <?php if (!empty($perm['description'])): ?>
                                            <small><?php echo htmlspecialchars($perm['description']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Move this permission to recycle bin?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="delete_permission">
                                        <input type="hidden" name="permission_id" value="<?php echo (int)$perm['permission_id']; ?>">
                                        <button type="submit" class="btn-delete">🗑️ Delete</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="no-permissions">No permissions assigned</div>
                    <?php endif; ?>
                    
                    <button class="btn btn-primary" style="width: 100%; margin-top: 10px;" onclick="openModal('modal-<?php echo (int)$role['role_id']; ?>')">+ Add Permission</button>
                </div>

                <!-- Modal for adding permission -->
                <div id="modal-<?php echo (int)$role['role_id']; ?>" class="modal">
                    <div class="modal-content">
                        <span class="close" onclick="closeModal('modal-<?php echo (int)$role['role_id']; ?>')">&times;</span>
                        <h3>Add Permission to <?php echo htmlspecialchars($role['role_name']); ?></h3>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="add_permission">
                            <input type="hidden" name="role_id" value="<?php echo (int)$role['role_id']; ?>">
                            
                            <div class="form-group">
                                <label for="perm_name_<?php echo (int)$role['role_id']; ?>">Permission Name *</label>
                                <input type="text" id="perm_name_<?php echo (int)$role['role_id']; ?>" name="permission_name" required placeholder="e.g., view_reports" minlength="3">
                                <small style="color: #666; display: block; margin-top: 4px;">Use lowercase letters, numbers, and underscores only</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="perm_desc_<?php echo (int)$role['role_id']; ?>">Description</label>
                                <textarea id="perm_desc_<?php echo (int)$role['role_id']; ?>" name="description" placeholder="What does this permission allow?"></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn-success">✓ Add Permission</button>
                                <button type="button" class="btn-secondary" onclick="closeModal('modal-<?php echo (int)$role['role_id']; ?>')">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>
