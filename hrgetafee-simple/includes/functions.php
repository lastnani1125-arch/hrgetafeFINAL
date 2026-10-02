<?php
// Common helper functions (secure versions)

// Check if logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Redirect if not logged in
function require_login() {
    if (!is_logged_in()) {
        header("Location: " . BASE_URL . "login.php");
        exit;
    }
}

// Check user role (exact match)
function check_role($required_role) {
    if (!isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== (int)$required_role) {
        header("Location: " . BASE_URL . "login.php");
        exit;
    }
}

// Get employee info (prepared)
function get_employee_info($conn, $employee_id) {
    $stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// Format date
function format_date($date) {
    if (!$date) return '-';
    return date('M d, Y', strtotime($date));
}

// Format datetime
function format_datetime($datetime) {
    if (!$datetime) return '-';
    return date('M d, Y h:i A', strtotime($datetime));
}

// Get role name
function get_role_name($role_id) {
    $roles = [
        1 => 'HR Administrator',
        3 => 'Employee'
    ];
    return $roles[$role_id] ?? 'Unknown';
}

// Get employee name (prepared)
function get_employee_name($conn, $employee_id) {
    $stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM employees WHERE employee_id = ? LIMIT 1");
    if (!$stmt) return 'Unknown';
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row['full_name'] ?? 'Unknown';
}

// Calculate leave balance (prepared)
function calculate_leave_balance($conn, $employee_id, $leave_type_id) {
    $current_year = date('Y');

    // Get max days
    $stmt = $conn->prepare("SELECT max_days_per_year FROM leave_types WHERE leave_type_id = ? LIMIT 1");
    if (!$stmt) return 0;
    $stmt->bind_param("i", $leave_type_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    $max_days = isset($row['max_days_per_year']) ? intval($row['max_days_per_year']) : 0;

    // Count approved leaves in the same year (prepared)
    $stmt = $conn->prepare("SELECT COALESCE(SUM(number_of_days), 0) as used_days FROM leave_requests WHERE employee_id = ? AND leave_type_id = ? AND YEAR(start_date) = ?");
    if (!$stmt) return $max_days;
    $stmt->bind_param("iii", $employee_id, $leave_type_id, $current_year);
    $stmt->execute();
    $res = $stmt->get_result();
    $used_row = $res->fetch_assoc();
    $stmt->close();
    $used_days = isset($used_row['used_days']) ? intval($used_row['used_days']) : 0;

    return max(0, $max_days - $used_days);
}

// Count pending leaves (prepared)
function count_pending_leaves($conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'");
    if (!$stmt) return 0;
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return intval($row['count'] ?? 0);
}

// Get total employees (prepared)
function get_total_employees($conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
    if (!$stmt) return 0;
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return intval($row['count'] ?? 0);
}
?>