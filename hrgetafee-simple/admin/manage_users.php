<?php
// hrgetafee-simple/admin/manage_users.php
// Account manager with separate tab/view for Active (employees) and Deactivated accounts.

session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

require_login();
check_role(1); // admin only

$admin = get_employee_info($conn, $_SESSION['employee_id'] ?? 0);

// Flash helpers
function flash_success($msg) { $_SESSION['flash_success'] = $msg; }
function flash_error($msg) { $_SESSION['flash_error'] = $msg; }

// POST handlers: create_user, deactivate_user, reactivate_user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CSRF check
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($csrf) || !verifyCSRFToken($csrf)) {
        flash_error('Invalid form token. Please reload and try again.');
        header('Location: manage_users.php');
        exit;
    }

    if ($action === 'create_user') {
        $employee_id = intval($_POST['employee_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role_id = intval($_POST['role_id'] ?? 0);

        if ($employee_id <= 0 || $username === '' || $password === '' || $role_id <= 0) {
            flash_error('All fields are required to create an account.');
            header('Location: manage_users.php');
            exit;
        }

        if (!preg_match('/^[a-zA-Z0-9_]{4,}$/', $username)) {
            flash_error('Username must be at least 4 characters and contain only letters, numbers, and underscores.');
            header('Location: manage_users.php');
            exit;
        }

        // Password policy
        $pwd_err = [];
        if (strlen($password) < 8) $pwd_err[] = 'Password must be at least 8 characters.';
        if (!preg_match('/[A-Z]/', $password)) $pwd_err[] = 'Include at least one uppercase letter.';
        if (!preg_match('/[a-z]/', $password)) $pwd_err[] = 'Include at least one lowercase letter.';
        if (!preg_match('/[0-9]/', $password)) $pwd_err[] = 'Include at least one number.';
        if (!empty($pwd_err)) {
            flash_error(implode(' ', $pwd_err));
            header('Location: manage_users.php');
            exit;
        }

        // Ensure employee exists
        $stmt = $conn->prepare("SELECT employee_id, email FROM employees WHERE employee_id = ? LIMIT 1");
        $stmt->bind_param('i', $employee_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) {
            $stmt->close();
            flash_error('Selected employee not found.');
            header('Location: manage_users.php');
            exit;
        }
        $stmt->close();

        // Ensure employee does not already have a user
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE employee_id = ? LIMIT 1");
        $stmt->bind_param('i', $employee_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            flash_error('This employee already has a user account.');
            header('Location: manage_users.php');
            exit;
        }
        $stmt->close();

        // Ensure username unique
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            flash_error('Username already exists. Choose another.');
            header('Location: manage_users.php');
            exit;
        }
        $stmt->close();

        // Hash & insert
        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        if ($password_hash === false) {
            flash_error('Failed to hash password.');
            header('Location: manage_users.php');
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO users (username, password, role_id, employee_id, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
        if (!$stmt) {
            flash_error('Database error: ' . htmlspecialchars($conn->error));
            header('Location: manage_users.php');
            exit;
        }
        $stmt->bind_param('ssii', $username, $password_hash, $role_id, $employee_id);
        if ($stmt->execute()) {
            logSecurityEvent($conn, $_SESSION['user_id'] ?? null, 'CREATE_USER', "Created user '$username' for employee_id={$employee_id}");
            flash_success('User account created successfully.');
        } else {
            flash_error('Failed to create user account: ' . htmlspecialchars($stmt->error));
        }
        $stmt->close();
        header('Location: manage_users.php');
        exit;

    } elseif ($action === 'deactivate_user') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $reason = trim($_POST['deactivation_reason'] ?? '');
        if ($user_id <= 0 || $reason === '') {
            flash_error('User and reason are required to deactivate.');
            header('Location: manage_users.php');
            exit;
        }
        $stmt = $conn->prepare("UPDATE users SET status = 'inactive', deactivation_reason = ?, deactivated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param('si', $reason, $user_id);
        if ($stmt->execute()) {
            logSecurityEvent($conn, $_SESSION['user_id'] ?? null, 'DEACTIVATE_USER', "Deactivated user_id={$user_id}");
            flash_success('User deactivated.');
        } else {
            flash_error('Failed to deactivate user.');
        }
        $stmt->close();
        header('Location: manage_users.php?tab=inactive');
        exit;

    } elseif ($action === 'reactivate_user') {
        $user_id = intval($_POST['user_id'] ?? 0);
        if ($user_id <= 0) {
            flash_error('Invalid user.');
            header('Location: manage_users.php');
            exit;
        }
        $stmt = $conn->prepare("UPDATE users SET status = 'active', deactivation_reason = NULL, deactivated_at = NULL WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        if ($stmt->execute()) {
            logSecurityEvent($conn, $_SESSION['user_id'] ?? null, 'REACTIVATE_USER', "Reactivated user_id={$user_id}");
            flash_success('User reactivated.');
        } else {
            flash_error('Failed to reactivate user.');
        }
        $stmt->close();
        header('Location: manage_users.php');
        exit;
    }
}

// UI state
$tab = $_GET['tab'] ?? 'create'; // 'create', 'active', or 'inactive'
$search = trim($_GET['search'] ?? '');

// Counts for tabs
$active_user_count = $conn->query("SELECT COUNT(*) as c FROM users WHERE status = 'active'")->fetch_assoc()['c'] ?? 0;
$inactive_user_count = $conn->query("SELECT COUNT(*) as c FROM users WHERE status = 'inactive'")->fetch_assoc()['c'] ?? 0;

// Count employees without accounts
$employees_without_accounts_count = $conn->query("SELECT COUNT(*) as c FROM employees e LEFT JOIN users u ON e.employee_id = u.employee_id WHERE u.user_id IS NULL AND e.status = 'active'")->fetch_assoc()['c'] ?? 0;

// ==================== ACTIVE TAB ====================
// Only ACTIVE users (completely exclude inactive/deactivated)
$sql_active = "SELECT e.employee_id, e.employee_code, e.first_name, e.last_name, e.email, e.position, e.status AS emp_status,
                      u.user_id, u.username, u.status AS user_status, u.last_login, r.role_name
               FROM employees e
               LEFT JOIN users u ON e.employee_id = u.employee_id
               LEFT JOIN roles r ON u.role_id = r.role_id
               WHERE u.status = 'active' OR u.user_id IS NULL";

$active_params = [];
$active_types = '';

if ($search !== '') {
    $sql_active .= " AND (e.employee_code LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ?)";
    $like = "%{$search}%";
    $active_types .= 'ssss';
    $active_params[] = $like; $active_params[] = $like; $active_params[] = $like; $active_params[] = $like;
}

$sql_active .= " ORDER BY e.last_name, e.first_name";

// execute active query
$stmt_active = $conn->prepare($sql_active);
$employees_active = [];
if ($stmt_active) {
    if ($active_types !== '') {
        $refs = [];
        $refs[] = & $active_types;
        foreach ($active_params as $k => $v) $refs[] = & $active_params[$k];
        call_user_func_array([$stmt_active, 'bind_param'], $refs);
    }
    $stmt_active->execute();
    $res_active = $stmt_active->get_result();
    $employees_active = $res_active ? $res_active->fetch_all(MYSQLI_ASSOC) : [];
    $stmt_active->close();
}

// ==================== CREATE TAB ====================
// Employees without ANY account
$sql_no_account = "SELECT e.employee_id, e.employee_code, e.first_name, e.last_name, e.email, e.position, e.date_hired, e.salary
                   FROM employees e
                   LEFT JOIN users u ON e.employee_id = u.employee_id
                   WHERE u.user_id IS NULL AND e.status = 'active'";

$no_acct_params = [];
$no_acct_types = '';
if ($search !== '') {
    $sql_no_account .= " AND (e.employee_code LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ?)";
    $like = "%{$search}%";
    $no_acct_types .= 'ssss';
    $no_acct_params[] = $like; $no_acct_params[] = $like; $no_acct_params[] = $like; $no_acct_params[] = $like;
}
$sql_no_account .= " ORDER BY e.date_hired DESC, e.last_name, e.first_name";

$stmt_no_acct = $conn->prepare($sql_no_account);
$employees_no_account = [];
if ($stmt_no_acct) {
    if ($no_acct_types !== '') {
        $refs = []; $refs[] = & $no_acct_types;
        foreach ($no_acct_params as $k => $v) $refs[] = & $no_acct_params[$k];
        call_user_func_array([$stmt_no_acct, 'bind_param'], $refs);
    }
    $stmt_no_acct->execute();
    $res_no_acct = $stmt_no_acct->get_result();
    $employees_no_account = $res_no_acct ? $res_no_acct->fetch_all(MYSQLI_ASSOC) : [];
    $stmt_no_acct->close();
}

// ==================== INACTIVE TAB ====================
// Only INACTIVE users (completely separate from active)
$sql_inactive = "SELECT u.user_id, u.username, u.deactivation_reason, u.deactivated_at, u.last_login,
                        e.employee_id, e.employee_code, e.first_name, e.last_name, e.email
                 FROM users u
                 LEFT JOIN employees e ON u.employee_id = e.employee_id
                 WHERE u.status = 'inactive'";

$inactive_params = [];
$inactive_types = '';
if ($search !== '') {
    $sql_inactive .= " AND (u.username LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ?)";
    $like = "%{$search}%";
    $inactive_types .= 'ssss';
    $inactive_params[] = $like; $inactive_params[] = $like; $inactive_params[] = $like; $inactive_params[] = $like;
}
$sql_inactive .= " ORDER BY u.deactivated_at DESC";

$stmt2 = $conn->prepare($sql_inactive);
$inactive_users = [];
if ($stmt2) {
    if ($inactive_types !== '') {
        $refs = []; $refs[] = & $inactive_types;
        foreach ($inactive_params as $k => $v) $refs[] = & $inactive_params[$k];
        call_user_func_array([$stmt2, 'bind_param'], $refs);
    }
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $inactive_users = $res2 ? $res2->fetch_all(MYSQLI_ASSOC) : [];
    $stmt2->close();
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manage Users (Accounts) - HRGetafe</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .tabs { display:flex; gap:8px; margin-bottom:12px; flex-wrap:wrap; }
    .tab { padding:8px 12px; border-radius:6px; cursor:pointer; text-decoration:none; color:#fff; font-size:14px; }
    .tab.active { background:#3b82f6; }
    .tab.inactive { background:#64748b; }
    .search-bar { display:flex; gap:8px; margin-bottom:12px; align-items:center; flex-wrap:wrap; }
    table{width:100%;border-collapse:collapse}
    th,td{padding:8px 10px;border:1px solid #eaeaea;text-align:left}
    .actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .modal{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.5)}
    .modal-content{background:#fff;margin:8% auto;padding:18px;border-radius:8px;width:90%;max-width:500px}
    .section-card { background:#f9fafb; padding:14px; border-radius:8px; margin-bottom:14px; border-left:4px solid #3b82f6; }
    .section-card h4 { margin-top:0; color:#1f2937; }
    .employee-no-account-row { background:#fffbeb; }
    .badge { display:inline-block; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:600; }
    .badge-active { background:#d1fae5; color:#065f46; }
    .badge-inactive { background:#fee2e2; color:#991b1b; }
  </style>
</head>
<body>
  <div class="navbar">
    <h2>Manage Users (Accounts)</h2>
    <div class="navbar-user">
      <span>Welcome, <strong><?php echo htmlspecialchars($admin['first_name'] ?? ''); ?></strong></span>
      <a href="../logout.php">Logout</a>
    </div>
  </div>

  <div class="container">
    <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom:12px">← Back to Dashboard</a>

    <?php
      if (!empty($_SESSION['flash_error'])) { echo '<div class="alert alert-danger">'.htmlspecialchars($_SESSION['flash_error']).'</div>'; unset($_SESSION['flash_error']); }
      if (!empty($_SESSION['flash_success'])) { echo '<div class="alert alert-success">'.htmlspecialchars($_SESSION['flash_success']).'</div>'; unset($_SESSION['flash_success']); }
    ?>

    <div class="tabs" role="tablist">
      <a href="?tab=create<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="tab <?php echo $tab === 'create' ? 'active' : 'inactive'; ?>" style="background: #10b981;">
        ➕ Create Account for New Employees (<?php echo (int)$employees_without_accounts_count; ?>)
      </a>
      <a href="?tab=active<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="tab <?php echo $tab === 'active' ? 'active' : 'inactive'; ?>">
        ✅ Active Accounts (<?php echo (int)$active_user_count; ?>)
      </a>
      <a href="?tab=inactive<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="tab <?php echo $tab === 'inactive' ? 'active' : 'inactive'; ?>" style="background: #ef4444;">
        🚫 Deactivated Accounts (<?php echo (int)$inactive_user_count; ?>)
      </a>
    </div>

    <!-- Search / Filter -->
    <form method="GET" class="search-bar" role="search" style="margin-bottom:14px;">
      <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
      <input name="search" type="search" placeholder="Search by username, name, code, or email..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1;padding:8px;border-radius:6px;border:1px solid #ddd;min-width:200px;">
      <button class="btn btn-primary" type="submit">Search</button>
      <a class="btn btn-secondary" href="manage_users.php?tab=<?php echo $tab; ?>">Clear</a>
    </form>

    <?php if ($tab === 'create'): ?>
      <!-- CREATE ACCOUNT FOR EMPLOYEES WITHOUT LOGIN -->
      <div style="background:white;padding:16px;border-radius:8px">
        <h3>📝 Create Account for Employees Without Login</h3>
        <p style="color:#666;margin-top:0">These are newly hired or unregistered employees. Click "Create Account" to set up their login credentials.</p>

        <?php if (empty($employees_no_account)): ?>
          <div class="section-card" style="border-left-color:#10b981">
            <h4 style="color:#059669">✅ All Done!</h4>
            <p style="margin:0">All active employees have user accounts. Great job keeping the system up to date!</p>
          </div>
        <?php else: ?>
          <table style="margin-top:12px;">
            <thead>
              <tr style="background:#f3f4f6">
                <th>Employee Code</th>
                <th>Name</th>
                <th>Email</th>
                <th>Position</th>
                <th>Date Hired</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($employees_no_account as $emp): ?>
                <tr class="employee-no-account-row">
                  <td><strong><?php echo htmlspecialchars($emp['employee_code']); ?></strong></td>
                  <td><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></td>
                  <td><?php echo htmlspecialchars($emp['email']); ?></td>
                  <td><?php echo htmlspecialchars($emp['position']); ?></td>
                  <td><?php echo htmlspecialchars(format_date($emp['date_hired'])); ?></td>
                  <td>
                    <button class="btn btn-primary" onclick="openCreateModal(<?php echo (int)$emp['employee_id']; ?>, '<?php echo htmlspecialchars(addslashes($emp['email'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($emp['first_name'] . ' ' . $emp['last_name'])); ?>')">Create Account</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    <?php elseif ($tab === 'active'): ?>
      <!-- ACTIVE ACCOUNTS ONLY -->
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div style="font-weight:600"><span class="badge badge-active">ACTIVE</span> User Accounts (<?php echo count($employees_active); ?>)</div>
        <div><a href="manage_employees.php" class="btn btn-primary">Open Employee Records</a></div>
      </div>

      <div style="background:white;padding:16px;border-radius:8px">
        <p style="color:#666;font-size:14px">Showing only <strong>ACTIVE</strong> accounts. Deactivated accounts are displayed separately in the "Deactivated Accounts" tab.</p>
        
        <?php if (empty($employees_active)): ?>
          <div style="padding:20px;text-align:center;color:#666">No active accounts found.</div>
        <?php else: ?>
          <table style="margin-top:12px;">
            <thead>
              <tr style="background:#f3f4f6">
                <th>Employee Code</th>
                <th>Username</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($employees_active as $row): ?>
                <?php
                  $emp_id = (int)$row['employee_id'];
                  $username = $row['username'] ?? null;
                  $user_id = $row['user_id'] ?? null;
                  $user_status = $row['user_status'] ?? null;
                  $role_name = $row['role_name'] ?? '-';
                  $last_login = $row['last_login'] ?? null;
                ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($row['employee_code']); ?></strong></td>
                  <td><?php echo $username ? htmlspecialchars($username) : '<span style="color:#888">— no account —</span>'; ?></td>
                  <td><?php echo htmlspecialchars($row['first_name'].' '.$row['last_name']); ?></td>
                  <td><?php echo htmlspecialchars($row['email']); ?></td>
                  <td><?php echo $username ? htmlspecialchars($role_name) : '-'; ?></td>
                  <td><span class="badge badge-active">ACTIVE</span></td>
                  <td><?php echo $last_login ? htmlspecialchars(format_datetime($last_login)) : '-'; ?></td>
                  <td>
                    <div class="actions">
                      <?php if ($username && $user_id): ?>
                        <button class="btn btn-warning" onclick="openDeactivateModal(<?php echo (int)$user_id; ?>, '<?php echo htmlspecialchars(addslashes($username)); ?>')">Deactivate</button>
                      <?php elseif (!$username): ?>
                        <button class="btn btn-primary" onclick="openCreateModal(<?php echo $emp_id; ?>, '<?php echo htmlspecialchars(addslashes($row['email'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($row['first_name'] . ' ' . $row['last_name'])); ?>')">Create Account</button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    <?php else: ?>
      <!-- INACTIVE ACCOUNTS ONLY -->
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div style="font-weight:600"><span class="badge badge-inactive">INACTIVE</span> Deactivated Accounts (<?php echo count($inactive_users); ?>)</div>
      </div>

      <div style="background:white;padding:16px;border-radius:8px">
        <p style="color:#666;font-size:14px">Showing only <strong>DEACTIVATED</strong> accounts. Active accounts are displayed separately in the "Active Accounts" tab.</p>
        
        <?php if (empty($inactive_users)): ?>
          <div style="padding:20px;text-align:center;color:#666">No deactivated accounts found.</div>
        <?php else: ?>
          <table style="margin-top:12px;">
            <thead>
              <tr style="background:#f3f4f6">
                <th>Username</th>
                <th>Employee</th>
                <th>Email</th>
                <th>Status</th>
                <th>Deactivated At</th>
                <th>Reason</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($inactive_users as $u): ?>
                <tr style="background:#fef2f2">
                  <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                  <td><?php echo htmlspecialchars(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')); ?></td>
                  <td><?php echo htmlspecialchars($u['email'] ?? '-'); ?></td>
                  <td><span class="badge badge-inactive">INACTIVE</span></td>
                  <td><?php echo $u['deactivated_at'] ? htmlspecialchars(format_datetime($u['deactivated_at'])) : '-'; ?></td>
                  <td><?php echo htmlspecialchars($u['deactivation_reason'] ?? '-'); ?></td>
                  <td>
                    <div class="actions">
                      <form method="POST" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="reactivate_user">
                        <input type="hidden" name="user_id" value="<?php echo (int)$u['user_id']; ?>">
                        <button type="submit" class="btn btn-success">Reactivate</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Create User Modal (shared) -->
  <div id="createModal" class="modal" aria-hidden="true">
    <div class="modal-content">
      <span style="float:right;cursor:pointer;font-size:20px;" onclick="closeCreateModal()">&times;</span>
      <h3>Create User Account</h3>
      <div id="employee_info" style="background:#f3f4f6;padding:10px;border-radius:6px;margin-bottom:12px;font-size:14px;">
        <strong>Employee:</strong> <span id="info_name"></span><br>
        <strong>Email:</strong> <span id="info_email"></span>
      </div>
      <form method="POST" action="manage_users.php">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="create_user">
        <input type="hidden" id="create_employee_id" name="employee_id" value="">
        <div>
          <label>Username *</label>
          <input type="text" id="create_username" name="username" required placeholder="e.g., john.dela.cruz">
        </div>
        <div>
          <label>Password *</label>
          <input type="password" id="create_password" name="password" required placeholder="Min 8 chars, 1 uppercase, 1 lowercase, 1 number">
          <small style="color:#666">At least 8 characters with uppercase, lowercase, and number.</small>
        </div>
        <div>
          <label>Role *</label>
          <select name="role_id" required>
            <option value="">Select Role</option>
            <option value="1">HR Administrator</option>
            <option value="3" selected>Employee</option>
          </select>
        </div>
        <div style="margin-top:12px;">
          <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Account</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Deactivate Modal -->
  <div id="deactivateModal" class="modal" aria-hidden="true">
    <div class="modal-content">
      <span style="float:right;cursor:pointer;font-size:20px;" onclick="closeDeactivateModal()">&times;</span>
      <h3>Deactivate User</h3>
      <form method="POST" action="manage_users.php">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="deactivate_user">
        <input type="hidden" id="deact_user_id" name="user_id" value="">
        <div>
          <label>Username</label>
          <div id="deact_username" style="font-weight:600;margin-bottom:8px;padding:8px;background:#f3f4f6;border-radius:4px"></div>
        </div>
        <div>
          <label>Reason *</label>
          <textarea name="deactivation_reason" required rows="4" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;font-family:inherit"></textarea>
        </div>
        <div style="margin-top:12px;">
          <button type="button" class="btn btn-secondary" onclick="closeDeactivateModal()">Cancel</button>
          <button type="submit" class="btn btn-danger">Confirm Deactivate</button>
        </div>
      </form>
    </div>
  </div>

<script>
function openCreateModal(empId, email, fullName) {
  document.getElementById('create_employee_id').value = empId;
  document.getElementById('info_name').textContent = fullName;
  document.getElementById('info_email').textContent = email;
  
  var pre = '';
  if (email && email.indexOf('@') !== -1) {
    var parts = email.split('@')[0].split('.');
    pre = parts.join('_').toLowerCase();
  }
  document.getElementById('create_username').value = pre;
  document.getElementById('create_password').value = '';
  document.getElementById('createModal').style.display = 'block';
}
function closeCreateModal() { 
  document.getElementById('createModal').style.display = 'none'; 
}

function openDeactivateModal(userId, username) {
  document.getElementById('deact_user_id').value = userId;
  document.getElementById('deact_username').textContent = username;
  document.getElementById('deactivateModal').style.display = 'block';
}
function closeDeactivateModal() { 
  document.getElementById('deactivateModal').style.display = 'none'; 
}

window.onclick = function(e) {
  var cm = document.getElementById('createModal');
  var dm = document.getElementById('deactivateModal');
  if (e.target == cm) cm.style.display = 'none';
  if (e.target == dm) dm.style.display = 'none';
}
</script>
</body>
</html>
