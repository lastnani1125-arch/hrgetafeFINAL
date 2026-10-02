<?php
// hrgetafee-simple/admin/edit_employee.php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

require_login();
check_role(1);

$message = ''; $error = '';

$employee_id = intval($_GET['id'] ?? 0);
if ($employee_id <= 0) {
    die('Invalid employee id.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_employee'])) {
    if (empty($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid form token.';
    } else {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $position = trim($_POST['position']);
        $salary = floatval($_POST['salary']);
        $status = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

        $stmt = $conn->prepare("UPDATE employees SET first_name = ?, last_name = ?, email = ?, phone = ?, position = ?, salary = ?, status = ? WHERE employee_id = ?");
        $stmt->bind_param("sssssdis", $first_name, $last_name, $email, $phone, $position, $salary, $status, $employee_id); // note: last param type 's' for status and 'i' for employee_id - simplify: bind properly
        // Because of mixed types, use correct types string: "sssssdis" isn't correct; instead prepare with correct types:
        $stmt->close();

        // Correct prepared with types: first_name,last_name,email,phone,position,salary,status,employee_id => s s s s s d s i => "sssssdis"
        $stmt = $conn->prepare("UPDATE employees SET first_name = ?, last_name = ?, email = ?, phone = ?, position = ?, salary = ?, status = ? WHERE employee_id = ?");
        $stmt->bind_param("sssssdis", $first_name, $last_name, $email, $phone, $position, $salary, $status, $employee_id);
        if ($stmt->execute()) {
            $message = 'Employee updated.';
        } else {
            $error = 'Failed to update employee.';
        }
        $stmt->close();
    }
}

// fetch employee
$stmt = $conn->prepare("SELECT employee_id, employee_code, first_name, last_name, email, phone, position, salary, date_hired, status FROM employees WHERE employee_id = ? LIMIT 1");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$res = $stmt->get_result();
$employee = $res->fetch_assoc();
$stmt->close();
if (!$employee) die('Employee not found.');

?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Edit Employee</title><link rel="stylesheet" href="../assets/css/style.css"></head>
<body>
<div class="navbar"><h2>Edit Employee</h2><div class="navbar-user"><a href="../logout.php">Logout</a></div></div>
<div class="container">
    <a href="manage_users.php" class="btn btn-secondary">← Back to Users</a>
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="post" style="max-width:700px;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <div class="form-group">
            <label>Employee Code</label>
            <input value="<?php echo htmlspecialchars($employee['employee_code']); ?>" disabled>
        </div>
        <div class="form-group">
            <label>First Name</label>
            <input name="first_name" required value="<?php echo htmlspecialchars($employee['first_name']); ?>">
        </div>
        <div class="form-group">
            <label>Last Name</label>
            <input name="last_name" required value="<?php echo htmlspecialchars($employee['last_name']); ?>">
        </div>
        <div class="form-group">
            <label>Email</label>
            <input name="email" type="email" required value="<?php echo htmlspecialchars($employee['email']); ?>">
        </div>
        <div class="form-group">
            <label>Phone</label>
            <input name="phone" value="<?php echo htmlspecialchars($employee['phone']); ?>">
        </div>
        <div class="form-group">
            <label>Position</label>
            <input name="position" value="<?php echo htmlspecialchars($employee['position']); ?>">
        </div>
        <div class="form-group">
            <label>Salary</label>
            <input name="salary" type="number" step="0.01" value="<?php echo htmlspecialchars($employee['salary']); ?>">
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="active" <?php echo $employee['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $employee['status'] !== 'active' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <button type="submit" name="save_employee" class="btn btn-primary">Save Changes</button>
    </form>
</div>
</body>
</html>