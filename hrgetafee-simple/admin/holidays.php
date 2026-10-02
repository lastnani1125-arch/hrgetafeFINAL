<?php
// hrgetafee-simple/admin/holidays.php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

require_login();
check_role(1);

$message = ''; $error = '';

// Add / Edit holiday
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_holiday'])) {
    if (empty($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid form token.';
    } else {
        $holiday_id = intval($_POST['holiday_id'] ?? 0);
        $name = trim($_POST['holiday_name'] ?? '');
        $date = trim($_POST['holiday_date'] ?? '');

        if ($name === '' || $date === '') {
            $error = 'Name and date are required.';
        } else {
            if ($holiday_id > 0) {
                $stmt = $conn->prepare("UPDATE holidays SET holiday_name = ?, holiday_date = ? WHERE holiday_id = ?");
                $stmt->bind_param("ssi", $name, $date, $holiday_id);
                $ok = $stmt->execute(); $stmt->close();
                $message = $ok ? 'Holiday updated.' : 'Failed to update.';
            } else {
                $stmt = $conn->prepare("INSERT INTO holidays (holiday_name, holiday_date) VALUES (?, ?)");
                $stmt->bind_param("ss", $name, $date);
                $ok = $stmt->execute(); $stmt->close();
                $message = $ok ? 'Holiday added.' : 'Failed to add.';
            }
        }
    }
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_holiday'])) {
    if (empty($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid token.';
    } else {
        $hid = intval($_POST['holiday_id']);
        $stmt = $conn->prepare("DELETE FROM holidays WHERE holiday_id = ?");
        $stmt->bind_param("i", $hid);
        if ($stmt->execute()) $message = 'Holiday removed.'; else $error = 'Failed to remove.';
        $stmt->close();
    }
}

$holidays = [];
$res = $conn->query("SELECT holiday_id, holiday_name, holiday_date, created_at FROM holidays ORDER BY holiday_date DESC");
if ($res) $holidays = $res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Manage Holidays</title><link rel="stylesheet" href="../assets/css/style.css"></head>
<body>
<div class="navbar"><h2>Manage Holidays</h2><div class="navbar-user"><a href="../logout.php">Logout</a></div></div>
<div class="container">
    <a href="dashboard.php" class="btn btn-secondary">← Back</a>
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <h3>Add / Edit Holiday</h3>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="holiday_id" id="holiday_id" value="">
        <div class="form-group">
            <label>Name</label>
            <input name="holiday_name" id="holiday_name" required>
        </div>
        <div class="form-group">
            <label>Date</label>
            <input type="date" name="holiday_date" id="holiday_date" required>
        </div>
        <button type="submit" name="save_holiday" class="btn btn-primary">Save</button>
        <button type="button" id="clearHoliday" class="btn btn-secondary">Clear</button>
    </form>

    <h3 style="margin-top:20px">Holidays</h3>
    <table>
        <thead><tr><th>Date</th><th>Name</th><th>Action</th></tr></thead>
        <tbody>
            <?php foreach ($holidays as $h): ?>
            <tr>
                <td><?php echo htmlspecialchars($h['holiday_date']); ?></td>
                <td><?php echo htmlspecialchars($h['holiday_name']); ?></td>
                <td>
                    <button onclick="editHoliday(<?php echo (int)$h['holiday_id']; ?>, '<?php echo htmlspecialchars(addslashes($h['holiday_name'])); ?>', '<?php echo $h['holiday_date']; ?>')" class="btn">Edit</button>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete?');">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="holiday_id" value="<?php echo (int)$h['holiday_id']; ?>">
                        <button type="submit" name="delete_holiday" class="btn btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function editHoliday(id, name, date){
    document.getElementById('holiday_id').value = id;
    document.getElementById('holiday_name').value = name;
    document.getElementById('holiday_date').value = date;
}
document.getElementById('clearHoliday').addEventListener('click', function(){
    document.getElementById('holiday_id').value=''; document.getElementById('holiday_name').value=''; document.getElementById('holiday_date').value='';
});
</script>
</body>
</html>