<?php
// admin/create_user.php - create a user for an existing employee (CSRF, prepared statements, password_hash)
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
check_role(1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$csrf = $_POST['csrf_token'] ?? '';
if (empty($csrf) || !verifyCSRFToken($csrf)) {
    $_SESSION['flash_error'] = 'Invalid form token. Please reload and try again.';
    header('Location: manage_users.php');
    exit;
}

$employee_id = intval($_POST['employee_id'] ?? 0);
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role_id = intval($_POST['role_id'] ?? 0);

if ($employee_id <= 0 || $username === '' || $password === '' || $role_id <= 0) {
    $_SESSION['flash_error'] = 'Missing required fields.';
    header('Location: manage_users.php');
    exit;
}

// Validate username format
if (!preg_match('/^[a-zA-Z0-9_]{4,}$/', $username)) {
    $_SESSION['flash_error'] = 'Username must be at least 4 chars and contain only letters/numbers/underscores.';
    header('Location: manage_users.php');
    exit;
}

// Password strength
$pwd_errors = [];
if (strlen($password) < 8) $pwd_errors[] = 'Password must be at least 8 characters.';
if (!preg_match('/[A-Z]/', $password)) $pwd_errors[] = 'Include at least one uppercase letter.';
if (!preg_match('/[a-z]/', $password)) $pwd_errors[] = 'Include at least one lowercase letter.';
if (!preg_match('/[0-9]/', $password)) $pwd_errors[] = 'Include at least one number.';
if (!empty($pwd_errors)) {
    $_SESSION['flash_error'] = implode(' ', $pwd_errors);
    header('Location: manage_users.php');
    exit;
}

// Check if username exists
$stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    $_SESSION['flash_error'] = 'Username already exists.';
    header('Location: manage_users.php');
    exit;
}
$stmt->close();

// Check employee exists and doesn't already have an account
$stmt = $conn->prepare("SELECT employee_id FROM employees WHERE employee_id = ? LIMIT 1");
$stmt->bind_param('i', $employee_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    $stmt->close();
    $_SESSION['flash_error'] = 'Employee not found.';
    header('Location: manage_users.php');
    exit;
}
$stmt->close();

$stmt = $conn->prepare("SELECT user_id FROM users WHERE employee_id = ? LIMIT 1");
$stmt->bind_param('i', $employee_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    $_SESSION['flash_error'] = 'This employee already has a user account.';
    header('Location: manage_users.php');
    exit;
}
$stmt->close();

// Hash password and insert
$password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
if ($password_hash === false) {
    $_SESSION['flash_error'] = 'Failed to hash password.';
    header('Location: manage_users.php');
    exit;
}

$stmt = $conn->prepare("INSERT INTO users (username, password, role_id, employee_id, status) VALUES (?, ?, ?, ?, 'active')");
if (!$stmt) {
    $_SESSION['flash_error'] = 'DB error: '.$conn->error;
    header('Location: manage_users.php');
    exit;
}
$stmt->bind_param('ssii', $username, $password_hash, $role_id, $employee_id);
if ($stmt->execute()) {
    $stmt->close();
    $_SESSION['flash_success'] = 'User account created successfully.';
    header('Location: manage_users.php');
    exit;
} else {
    $err = $stmt->error;
    $stmt->close();
    $_SESSION['flash_error'] = 'Failed to create user account: '.$err;
    header('Location: manage_users.php');
    exit;
}