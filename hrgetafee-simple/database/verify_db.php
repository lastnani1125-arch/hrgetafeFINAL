<?php
/**
 * Database Setup Helper - Safe Version
 * Only creates missing tables/columns, doesn't modify existing ones
 */

require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/error_handler.php';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Database Setup - HRGetafe</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdance, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 10px; }
        .success { color: #155724; padding: 15px; background: #d4edda; margin: 10px 0; border-radius: 5px; border-left: 4px solid #28a745; }
        .info { color: #0c5460; padding: 15px; background: #d1ecf1; margin: 10px 0; border-radius: 5px; border-left: 4px solid #17a2b8; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; font-weight: 600; }
        .status-ok { color: #28a745; font-weight: bold; }
        .status-missing { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
<div class='container'>
    <h1>✅ Database Status Check - HRGetafe</h1>
<?php

$tables = [
    'employees' => 'Employee records',
    'users' => 'Login accounts',
    'attendance' => 'Clock in/out records',
    'payroll' => 'Salary and payments',
    'leave_requests' => 'Leave applications',
    'leave_types' => 'Types of leave',
    'holidays' => 'Company holidays',
    'security_logs' => 'System activity logs'
];

$all_ok = true;

echo "<table>";
echo "<thead><tr><th>Table</th><th>Status</th><th>Description</th></tr></thead>";
echo "<tbody>";

foreach ($tables as $table => $description) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    $exists = $result && $result->num_rows > 0;
    $all_ok = $all_ok && $exists;
    
    $status = $exists ? '<span class="status-ok">✓ EXISTS</span>' : '<span class="status-missing">✗ MISSING</span>';
    echo "<tr><td><code>$table</code></td><td>$status</td><td>$description</td></tr>";
}

echo "</tbody></table>";

if ($all_ok) {
    echo "<div class='success'><h2>✓ All tables exist! System is ready to use.</h2></div>";
} else {
    echo "<div class='info'><h2>⚠ Some tables are missing. Run the SQL setup first.</h2></div>";
}

?>
</div>
</body>
</html>