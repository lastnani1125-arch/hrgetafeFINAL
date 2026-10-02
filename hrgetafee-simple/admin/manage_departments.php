<?php
// hrgetafee-simple/admin/manage_departments.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';
require_once '../includes/soft_delete_helper.php';

// Admin only
require_login();
check_role(1);

$admin = get_employee_info($conn, $_SESSION['employee_id']);
$message = '';
$error = '';

// Handle POST actions (add, update, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';
    
    // CSRF check
    if (empty($csrf) || !verifyCSRFToken($csrf)) {
        $error = 'Invalid form token. Please reload the page.';
    } elseif ($action === 'add') {
        $dept_name = sanitizeInput($_POST['dept_name'] ?? '');
        $dept_head = sanitizeInput($_POST['dept_head'] ?? '');
        $budget = floatval($_POST['budget'] ?? 0);

        if ($dept_name === '') {
            $error = 'Department name is required.';
        } else {
            $stmt = $conn->prepare("INSERT INTO departments (dept_name, dept_head, budget, created_date) VALUES (?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param('ssd', $dept_name, $dept_head, $budget);
                if ($stmt->execute()) {
                    $message = 'Department added successfully.';
                    logSecurityEvent($conn, $_SESSION['user_id'], 'ADD_DEPARTMENT', "Added department: $dept_name");
                    header('Location: manage_departments.php');
                    exit;
                } else {
                    $error = 'Error adding department: ' . htmlspecialchars($stmt->error);
                }
                $stmt->close();
            } else {
                $error = 'Database error: ' . htmlspecialchars($conn->error);
            }
        }
    } elseif ($action === 'update') {
        $dept_id = intval($_POST['dept_id'] ?? 0);
        $dept_name = sanitizeInput($_POST['dept_name'] ?? '');
        $dept_head = sanitizeInput($_POST['dept_head'] ?? '');
        $budget = floatval($_POST['budget'] ?? 0);

        if ($dept_id <= 0 || $dept_name === '') {
            $error = 'Invalid input.';
        } else {
            $stmt = $conn->prepare("UPDATE departments SET dept_name = ?, dept_head = ?, budget = ? WHERE dept_id = ?");
            if ($stmt) {
                $stmt->bind_param('ssdi', $dept_name, $dept_head, $budget, $dept_id);
                if ($stmt->execute()) {
                    $message = 'Department updated successfully.';
                    logSecurityEvent($conn, $_SESSION['user_id'], 'UPDATE_DEPARTMENT', "Updated department_id: $dept_id");
                    header('Location: manage_departments.php');
                    exit;
                } else {
                    $error = 'Error updating department: ' . htmlspecialchars($stmt->error);
                }
                $stmt->close();
            } else {
                $error = 'Database error: ' . htmlspecialchars($conn->error);
            }
        }
    } elseif ($action === 'delete') {
        $dept_id = intval($_POST['dept_id'] ?? 0);
        if ($dept_id <= 0) {
            $error = 'Invalid department.';
        } else {
            // Get department data before soft deleting
            $verify_stmt = $conn->prepare("SELECT dept_id, dept_name, dept_head, budget FROM departments WHERE dept_id = ? AND is_deleted = FALSE LIMIT 1");
            if ($verify_stmt) {
                $verify_stmt->bind_param('i', $dept_id);
                $verify_stmt->execute();
                $verify_res = $verify_stmt->get_result();
                $dept_data = $verify_res->fetch_assoc();
                $verify_stmt->close();

                if ($dept_data) {
                    // Soft delete using helper function
                    $result = softDeleteItem($conn, 'department', $dept_id, $dept_data['dept_name'], $dept_data, $_SESSION['user_id']);
                    
                    if ($result['success']) {
                        $message = 'Department moved to recycle bin.';
                        logSecurityEvent($conn, $_SESSION['user_id'], 'DELETE_DEPARTMENT', "Moved to bin: {$dept_data['dept_name']}");
                        header('Location: manage_departments.php');
                        exit;
                    } else {
                        $error = $result['error'];
                    }
                } else {
                    $error = 'Department not found.';
                }
            } else {
                $error = 'Database error: ' . htmlspecialchars($conn->error);
            }
        }
    } else {
        $error = 'Invalid action.';
    }
}

// Fetch active departments only
$departments = [];
$stmt = $conn->prepare("SELECT dept_id, dept_name, dept_head, budget, created_date FROM departments WHERE is_deleted = FALSE ORDER BY dept_name");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    $departments = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manage Departments - HRGetafe</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .form-row { display:flex; gap:12px; flex-wrap:wrap; }
    .form-row .col { flex:1; min-width:200px; }
    .actions-small { display:inline-flex; gap:8px; flex-wrap:wrap; }
    .notice { margin-bottom:12px; padding:10px; border-radius:6px; }
    .notice.ok { background:#ecfdf5; border:1px solid #d1fae5; color:#065f46; }
    .notice.err { background:#fff1f2; border:1px solid #fecaca; color:#7f1d1d; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:10px; text-align:left; border-bottom:1px solid #eee; }
    th { background:#f7f7fb; font-weight:600; }
    tr:hover { background:#f9f9f9; }
    .hidden { display:none; }
    .edit-form { background:#f9f9f9; padding:12px; border-radius:6px; margin-top:10px; }
    .recycle-link { display: inline-block; margin-left: 10px; padding: 8px 12px; background: #f59e0b; color: white; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 12px; }
    .recycle-link:hover { background: #d97706; }
  </style>
</head>
<body>
  <div class="navbar">
    <h2>Manage Departments</h2>
    <div class="navbar-user">
      <span>Welcome, <strong><?php echo htmlspecialchars($admin['first_name'] ?? 'Admin'); ?></strong></span>
      <a href="../logout.php">Logout</a>
    </div>
  </div>

  <div class="container">
    <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom:12px">← Back to Dashboard</a>
    <a href="recycle_bin.php" class="recycle-link">🗑️ Recycle Bin</a>

    <?php if ($message): ?><div class="notice ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <!-- Add Department -->
    <section style="background:#fff;padding:16px;border-radius:8px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
      <h3>Add New Department</h3>
      <form method="POST" action="">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <div class="form-row">
          <div class="col">
            <label>Department Name *</label>
            <input type="text" name="dept_name" required placeholder="e.g., Human Resources">
          </div>
          <div class="col">
            <label>Department Head</label>
            <input type="text" name="dept_head" placeholder="e.g., HR Manager">
          </div>
          <div class="col">
            <label>Budget (₱)</label>
            <input type="number" step="0.01" min="0" name="budget" placeholder="0.00">
          </div>
        </div>
        <div style="margin-top:10px;">
          <button class="btn btn-primary" type="submit">✓ Add Department</button>
        </div>
      </form>
    </section>

    <!-- Departments Table -->
    <section style="background:#fff;padding:16px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
      <h3>Departments (<?php echo count($departments); ?>)</h3>
      
      <?php if (empty($departments)): ?>
        <div style="padding:20px;text-align:center;color:#666">No departments found. Create one above!</div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Department Name</th>
              <th>Head</th>
              <th>Budget (₱)</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($departments as $d): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($d['dept_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($d['dept_head'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars(number_format($d['budget'], 2)); ?></td>
                <td><?php echo htmlspecialchars(format_date($d['created_date'])); ?></td>
                <td>
                  <div class="actions-small">
                    <button class="btn btn-secondary" onclick="toggleEdit(<?php echo (int)$d['dept_id']; ?>)">✎ Edit</button>
                    <form method="POST" action="" style="display:inline" onsubmit="return confirm('Move this department to recycle bin?')">
                      <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="dept_id" value="<?php echo (int)$d['dept_id']; ?>">
                      <button class="btn btn-danger" type="submit">🗑️ Delete</button>
                    </form>
                  </div>

                  <!-- Inline Edit Form -->
                  <div id="edit-<?php echo (int)$d['dept_id']; ?>" class="hidden edit-form">
                    <h4 style="margin-top:0">Edit Department</h4>
                    <form method="POST" action="">
                      <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="dept_id" value="<?php echo (int)$d['dept_id']; ?>">
                      <div class="form-row">
                        <div class="col">
                          <label>Name *</label>
                          <input type="text" name="dept_name" value="<?php echo htmlspecialchars($d['dept_name']); ?>" required>
                        </div>
                        <div class="col">
                          <label>Head</label>
                          <input type="text" name="dept_head" value="<?php echo htmlspecialchars($d['dept_head'] ?? ''); ?>">
                        </div>
                        <div class="col">
                          <label>Budget (₱)</label>
                          <input type="number" step="0.01" min="0" name="budget" value="<?php echo htmlspecialchars($d['budget']); ?>">
                        </div>
                      </div>
                      <div style="margin-top:10px;display:flex;gap:8px;">
                        <button class="btn btn-primary" type="submit">✓ Save</button>
                        <button class="btn btn-secondary" type="button" onclick="toggleEdit(<?php echo (int)$d['dept_id']; ?>)">✗ Cancel</button>
                      </div>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </div>

  <script>
    function toggleEdit(deptId) {
      const editForm = document.getElementById('edit-' + deptId);
      if (editForm) {
        editForm.classList.toggle('hidden');
      }
    }
  </script>
</body>
</html>
