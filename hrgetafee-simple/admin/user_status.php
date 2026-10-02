<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
check_role(1);

$user_id = $_GET['user_id'] ?? '';
$status = $_GET['status'] ?? '';

if ($user_id && in_array($status, ['active', 'inactive'])) {
    $conn->query("UPDATE users SET status = '$status' WHERE user_id = $user_id");
}

header("Location: dashboard.php");
exit;
?>