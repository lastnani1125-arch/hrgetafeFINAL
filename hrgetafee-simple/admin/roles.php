<?php
// hrgetafee-simple/admin/roles.php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

require_login();
check_role(1);

$message = '';
$error = '';

// Add or Edit role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_role']))) {
    if (empty($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid form token.';
    } else {
        $role_name = trim($_POST['role_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $role_id = isset($_POST['role_id']) ? intval($_POST['role_id']) : 0;

        if ($role_name === '') {
            $error = 'Role name is required.';
        } else {
            if ($role_id > 0) {
                $stmt = $conn->prepare("UPDATE roles SET role_name = ?, description = ? WHERE role_id = ?");
                $stmt->bind_param("ssi", $role_name, $description, $role_id);
                $ok = $stmt->execute();
                $stmt->close();
                $message = $ok ? 'Role updated.' : 'Failed to update role.';
            } else {
                $stmt = $conn->prepare("INSERT INTO roles (role_name, description) VALUES (?, ?)");
                $stmt->bind_param("ss", $role_name, $description);
                $ok = $stmt->execute();
                $stmt->close();
                $message = $ok ? 'Role created.' : 'Failed to create role.';
            }
        }
    }
}

// Delete role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_role'])) {
    if (empty($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid form token.';
    } else {
        $rid = intval($_POST['role_id']);
        $stmt = $conn->prepare("DELETE FROM roles WHERE role_id = ?");
        $stmt->bind_param("i", $rid);
        if ($stmt->execute()) {
            $message = 'Role deleted.';
        } else {
            $error = 'Failed to delete role.';
        }
        $stmt->close();
    }
}

// Fetch roles
$roles = [];
$res = $conn->query("SELECT role_id, role_name, description, created_at FROM roles ORDER BY role_id ASC");
if ($res) $roles = $res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Manage Roles - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>.form-inline{display:flex;gap:8px;align-items:center}</style>
</head>
<body>
<div class="navbar"><h2>Manage Roles</h2><div class="navbar-user"> <a href="../logout.php">Logout</a></div></div>
<div class="container">
    <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>

    <?php if ($message): ?><div class="alert alert-success" style="margin-top:10px;"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger" style="margin-top:10px;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <h3 style="margin-top:20px;">Create / Edit Role</h3>
    <form method="post" style="max-width:600px;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="role_id" id="role_id" value="">
        <div class="form-group">
            <label>Role Name</label>
            <input name="role_name" id="role_name" required>
        </div>
        <div class="form-group">
            <label>Description</label>
            <input name="description" id="description">
        </div>
        <div class="button-group">
            <button type="submit" name="save_role" class="btn btn-primary">Save Role</button>
            <button type="button" id="role_clear" class="btn btn-secondary">Clear</button>
        </div>
    </form>

    <h3 style="margin-top:30px;">Existing Roles</h3>
    <table>
        <thead><tr><th>ID</th><th>Name</th><th>Description</th><th>Created</th><th>Action</th></tr></thead>
        <tbody>
            <?php foreach ($roles as $r): ?>
            <tr>
                <td><?php echo (int)$r['role_id']; ?></td>
                <td><?php echo htmlspecialchars($r['role_name']); ?></td>
                <td><?php echo htmlspecialchars($r['description']); ?></td>
                <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                <td>
                    <button class="btn" onclick="editRole(<?php echo (int)$r['role_id']; ?>, '<?php echo htmlspecialchars(addslashes($r['role_name'])); ?>', '<?php echo htmlspecialchars(addslashes($r['description'])); ?>')">Edit</button>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete role?');">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="role_id" value="<?php echo (int)$r['role_id']; ?>">
                        <button type="submit" name="delete_role" class="btn btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function editRole(id, name, desc){
    document.getElementById('role_id').value = id;
    document.getElementById('role_name').value = name;
    document.getElementById('description').value = desc;
}
document.getElementById('role_clear').addEventListener('click', function(){
    document.getElementById('role_id').value=''; document.getElementById('role_name').value=''; document.getElementById('description').value='';
});
</script>
</body>
</html>