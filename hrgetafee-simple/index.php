<?php
session_start();

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    $role_id = $_SESSION['role_id'];
    $redirect_map = [
        1 => 'admin/dashboard.php',
        3 => 'employee/dashboard.php'
    ];
    header("Location: " . ($redirect_map[$role_id] ?? 'login.php'));
    exit;
}

// Not logged in, redirect to login
header("Location: login.php");
exit;
?>