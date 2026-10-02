<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hrgetafee_simple');

// Create Connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check Connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set Charset
$conn->set_charset("utf8");

// Base URL
define('BASE_URL', 'http://localhost/hrgetafee-simple/');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>