<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

require_login();
check_role(3);

$employee = get_employee_info($conn, $_SESSION['employee_id']);
$employee_id = $_SESSION['employee_id'];

// Get statistics for dashboard
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM attendance WHERE employee_id = ? AND MONTH(attendance_date) = MONTH(NOW()) AND YEAR(attendance_date) = YEAR(NOW())");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$this_month_attendance = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Check if there's any attendance data at all
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM attendance LIMIT 1");
$stmt->execute();
$total_attendance_records = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get pending leaves
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE employee_id = ? AND status = 'pending'");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$pending_leaves_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get approved leaves
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE employee_id = ? AND status = 'approved'");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$approved_leaves_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get latest announcements
$stmt = $conn->prepare("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 3");
$stmt->execute();
$latest_announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get announcements count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM announcements");
$stmt->execute();
$announcements_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// ===== NEW: GET EMPLOYEE DOCUMENTS =====
$employee_documents = [];
$stmt = $conn->prepare("SELECT * FROM employee_documents WHERE employee_id = ? AND is_active = 1 ORDER BY uploaded_at DESC");
if ($stmt) {
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $employee_documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Handle document download
if (isset($_GET['download_document'])) {
    $document_id = intval($_GET['download_document']);
    $stmt = $conn->prepare("SELECT * FROM employee_documents WHERE document_id = ? AND employee_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('ii', $document_id, $employee_id);
        $stmt->execute();
        $doc = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($doc && file_exists($doc['file_path'])) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($doc['document_name']) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($doc['file_path']));
            readfile($doc['file_path']);
            exit;
        }
    }
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// ===== CHART DATA QUERIES =====
// Get attendance by month for employee (last 6 months)
$attendance_trend = $conn->query("
    SELECT DATE_FORMAT(attendance_date, '%b') AS month_label,
           COUNT(*) AS total
    FROM attendance
    WHERE employee_id = $employee_id
      AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(attendance_date), MONTH(attendance_date)
    ORDER BY attendance_date ASC
");
$attendance_labels = [];
$attendance_values = [];
while ($row = $attendance_trend->fetch_assoc()) {
    $attendance_labels[] = $row['month_label'];
    $attendance_values[] = (int)$row['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - HRGetafe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #667eea;
            --primary-dark: #5568d3;
            --secondary-color: #764ba2;
            --danger-color: #ef4444;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --sidebar-width: 270px;
            --sidebar-width-collapsed: 80px;
            --transition-speed: 0.3s;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Verdana, sans-serif;
            background: #f8f9fa;
            color: #2c3e50;
        }

        .employee-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .employee-sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: #ecf0f1;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: width var(--transition-speed) ease;
            box-shadow: 3px 0 15px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
        }

        .employee-sidebar.collapsed {
            width: var(--sidebar-width-collapsed);
        }

        .sidebar-header {
            padding: 25px 15px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            position: relative;
            flex-shrink: 0;
        }

        .sidebar-logo {
            font-size: 28px;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
            transition: opacity var(--transition-speed) ease;
        }

        .sidebar-logo-text {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.8);
            opacity: 1;
            transition: opacity var(--transition-speed) ease;
            display: block;
            letter-spacing: 0.5px;
        }

        .employee-sidebar.collapsed .sidebar-logo-text {
            opacity: 0;
            display: none;
        }

        .sidebar-toggle {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: all var(--transition-speed) ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-toggle:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
            margin: 0;
            flex: 1;
            overflow-y: auto;
        }

        .sidebar-menu-section {
            margin-bottom: 5px;
        }

        .sidebar-section-title {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.45);
            padding: 15px 15px 8px 15px;
            letter-spacing: 1px;
            opacity: 1;
            transition: opacity var(--transition-speed) ease;
        }

        .employee-sidebar.collapsed .sidebar-section-title {
            opacity: 0;
            padding: 10px 5px;
            font-size: 7px;
            height: 0;
            overflow: hidden;
        }

        .sidebar-menu-item {
            position: relative;
        }

        .sidebar-menu-link {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.75);
            text-decoration: none;
            transition: all var(--transition-speed) ease;
            border-left: 3px solid transparent;
            font-size: 14px;
            cursor: pointer;
            font-weight: 500;
        }

        .sidebar-menu-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: var(--primary-color);
            padding-left: 20px;
        }

        .sidebar-menu-link.active {
            background: rgba(102, 126, 234, 0.25);
            color: white;
            border-left-color: var(--primary-color);
            font-weight: 600;
            box-shadow: inset 0 0 10px rgba(102, 126, 234, 0.1);
        }

        .sidebar-menu-link i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        .sidebar-menu-text {
            flex: 1;
            opacity: 1;
            transition: opacity var(--transition-speed) ease;
            white-space: nowrap;
            overflow: hidden;
        }

        .employee-sidebar.collapsed .sidebar-menu-text {
            opacity: 0;
            width: 0;
        }

        .sidebar-footer {
            padding: 15px;
            border-top: 2px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.2);
            flex-shrink: 0;
            margin-top: auto;
        }

        .sidebar-user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            margin-bottom: 10px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 6px;
            opacity: 1;
            transition: opacity var(--transition-speed) ease;
        }

        .employee-sidebar.collapsed .sidebar-user-info {
            opacity: 0;
        }

        .sidebar-user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .sidebar-user-details {
            flex: 1;
            overflow: hidden;
        }

        .sidebar-user-name {
            font-size: 12px;
            font-weight: 600;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-user-role {
            font-size: 10px;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 2px;
        }

        .sidebar-logout-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all var(--transition-speed) ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .sidebar-logout-btn:hover {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .employee-sidebar.collapsed .sidebar-logout-btn {
            padding: 12px 8px;
            font-size: 0;
        }

        .employee-sidebar.collapsed .sidebar-logout-btn i {
            font-size: 16px;
            margin: 0;
        }

        .employee-main {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-speed) ease;
            display: flex;
            flex-direction: column;
        }

        .employee-sidebar.collapsed ~ .employee-main {
            margin-left: var(--sidebar-width-collapsed);
        }

        .employee-navbar {
            background: white;
            padding: 18px 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e0e7ff;
        }

        .navbar-left h2 {
            color: #2c3e50;
            font-size: 26px;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
        }

        .navbar-left i {
            color: var(--primary-color);
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 14px;
            color: #666;
        }

        .navbar-date {
            color: #999;
            font-size: 13px;
        }

        .employee-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            background: #f8f9fa;
        }

        .welcome-header {
            margin-bottom: 30px;
        }

        .welcome-header h1 {
            font-size: 32px;
            color: #1f2937;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .welcome-header p {
            color: #666;
            font-size: 14px;
        }

        /* ===== QUICK ACCESS SECTION ===== */
        .quick-access-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-top: 4px solid var(--primary-color);
        }

        .quick-access-section h3 {
            margin: 0 0 20px 0;
            color: #1f2937;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .quick-access-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
        }

        .quick-access-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 600;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .quick-access-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .quick-access-btn i {
            font-size: 24px;
        }

        .quick-access-btn span {
            font-size: 13px;
            text-align: center;
            line-height: 1.3;
        }

        .quick-access-btn.info-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .quick-access-btn.success-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .quick-access-btn.warning-btn {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .quick-access-btn.danger-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .quick-access-btn.disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .quick-access-btn.disabled:hover {
            transform: none;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        /* ===== END QUICK ACCESS ===== */

        .dashboard-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 18px; 
            margin-bottom: 35px; 
        }

        .stat-box { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 24px; 
            border-radius: 10px; 
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2); 
            transition: all 0.3s ease; 
            position: relative; 
            overflow: hidden; 
        }

        .stat-box::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .stat-box:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .stat-box h4 {
            margin: 0;
            font-size: 13px;
            opacity: 0.95;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            z-index: 1;
        }

        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin: 12px 0 0 0;
            position: relative;
            z-index: 1;
        }

        .stat-box.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .stat-box.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-box.info {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            border-top: 4px solid var(--primary-color);
        }

        .card h3 {
            margin-top: 0;
            color: #1f2937;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 15px;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .card p {
            color: #666;
            margin: 15px 0;
            font-size: 14px;
        }

        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-top: 4px solid var(--primary-color);
            margin-bottom: 25px;
        }

        .chart-card h3 {
            margin-top: 0;
            color: #1f2937;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 15px;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
        }

        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            margin-top: 15px;
        }

        .document-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary-color);
        }

        .document-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }

        .document-type {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .document-name {
            font-weight: 700;
            color: #1f2937;
            font-size: 14px;
            margin-bottom: 8px;
            word-break: break-word;
        }

        .document-meta {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .document-meta strong {
            color: #374151;
        }

        .document-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .btn-download {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            flex: 1;
            text-align: center;
            border: none;
            cursor: pointer;
        }

        .btn-download:hover {
            background: #059669;
        }

        .empty-documents {
            text-align: center;
            padding: 20px;
            color: #999;
            font-size: 14px;
        }

        .announcement-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .announcement-card {
            background: #f9fafb;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid var(--primary-color);
            transition: 0.3s;
        }

        .announcement-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .announcement-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .announcement-preview {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .announcement-date {
            font-size: 11px;
            color: #999;
        }

        .view-all-link {
            display: inline-block;
            margin-top: 15px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: 0.3s;
        }

        .view-all-link:hover {
            color: var(--secondary-color);
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #999;
        }

        .setup-notice {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 25px;
            color: #664d03;
        }

        .setup-notice strong {
            color: #664d03;
            font-weight: 700;
        }

        .badge-small {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 10px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }

        .quick-summary-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .quick-summary-list li {
            padding: 8px 0;
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        .employee-sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .employee-sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        .employee-sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }

        .employee-sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .employee-content::-webkit-scrollbar {
            width: 8px;
        }

        .employee-content::-webkit-scrollbar-track {
            background: #f0f0f0;
        }

        .employee-content::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 4px;
        }

        .employee-content::-webkit-scrollbar-thumb:hover {
            background: #999;
        }

        @media (max-width: 1024px) {
            :root {
                --sidebar-width: 250px;
            }

            .employee-content {
                padding: 20px;
            }

            .documents-grid {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            }

            .quick-access-grid {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            }
        }

        @media (max-width: 768px) {
            :root {
                --sidebar-width: 240px;
            }

            .employee-sidebar {
                transform: translateX(-100%);
                transition: transform var(--transition-speed) ease;
            }

            .employee-sidebar.mobile-open {
                transform: translateX(0);
            }

            .employee-main {
                margin-left: 0;
            }

            .employee-navbar {
                padding: 12px 18px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .navbar-left h2 {
                font-size: 20px;
            }

            .employee-content {
                padding: 15px;
            }

            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .grid-2 {
                grid-template-columns: 1fr;
            }

            .documents-grid {
                grid-template-columns: 1fr;
            }

            .quick-access-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 480px) {
            .employee-content {
                padding: 10px;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 15px;
            }

            .chart-card {
                padding: 15px;
            }

            .sidebar-logo {
                font-size: 24px;
            }

            .documents-grid {
                grid-template-columns: 1fr;
            }

            .quick-access-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .quick-access-btn {
                padding: 16px 12px;
            }

            .quick-access-btn i {
                font-size: 20px;
            }

            .quick-access-btn span {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="employee-wrapper">
        <aside class="employee-sidebar" id="employeeSidebar">
            <button class="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>

            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-briefcase"></i>
                </div>
                <span class="sidebar-logo-text">Employee Portal</span>
            </div>

            <ul class="sidebar-menu">
                <li class="sidebar-menu-section">
                    <a href="dashboard.php" class="sidebar-menu-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span class="sidebar-menu-text">Dashboard</span>
                    </a>
                </li>

                <li class="sidebar-menu-section">
                    <div class="sidebar-section-title">Personal</div>
                    <ul class="sidebar-menu">
                        <li class="sidebar-menu-item">
                            <a href="view_profile.php" class="sidebar-menu-link <?php echo $current_page === 'view_profile.php' ? 'active' : ''; ?>">
                                <i class="fas fa-user-circle"></i>
                                <span class="sidebar-menu-text">My Profile</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="my_documents.php" class="sidebar-menu-link <?php echo $current_page === 'my_documents.php' ? 'active' : ''; ?>">
                                <i class="fas fa-file-alt"></i>
                                <span class="sidebar-menu-text">My Documents</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="sidebar-menu-section">
                    <div class="sidebar-section-title">Attendance</div>
                    <ul class="sidebar-menu">
                        <li class="sidebar-menu-item">
                            <a href="attendance.php" class="sidebar-menu-link <?php echo $current_page === 'attendance.php' ? 'active' : ''; ?>">
                                <i class="fas fa-calendar-check"></i>
                                <span class="sidebar-menu-text">Records</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <?php if ($employee['employment_type'] === 'Regular'): ?>
                <li class="sidebar-menu-section">
                    <div class="sidebar-section-title">Leave Management</div>
                    <ul class="sidebar-menu">
                        <li class="sidebar-menu-item">
                            <a href="apply_leave.php" class="sidebar-menu-link <?php echo $current_page === 'apply_leave.php' ? 'active' : ''; ?>">
                                <i class="fas fa-file-alt"></i>
                                <span class="sidebar-menu-text">Apply for Leave</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="leave_status.php" class="sidebar-menu-link <?php echo $current_page === 'leave_status.php' ? 'active' : ''; ?>">
                                <i class="fas fa-clipboard-list"></i>
                                <span class="sidebar-menu-text">Leave Status</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <li class="sidebar-menu-section">
                    <div class="sidebar-section-title">Payroll</div>
                    <ul class="sidebar-menu">
                        <li class="sidebar-menu-item">
                            <a href="salary_slips.php" class="sidebar-menu-link <?php echo $current_page === 'salary_slips.php' ? 'active' : ''; ?>">
                                <i class="fas fa-file-invoice-dollar"></i>
                                <span class="sidebar-menu-text">My Payslips</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="sidebar-menu-section">
                    <div class="sidebar-section-title">Communications</div>
                    <ul class="sidebar-menu">
                        <li class="sidebar-menu-item">
                            <a href="announcements.php" class="sidebar-menu-link <?php echo $current_page === 'announcements.php' ? 'active' : ''; ?>">
                                <i class="fas fa-bullhorn"></i>
                                <span class="sidebar-menu-text">Announcements</span>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>

            <div class="sidebar-footer">
                <div class="sidebar-user-info">
                    <div class="sidebar-user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="sidebar-user-details">
                        <div class="sidebar-user-name"><?php echo htmlspecialchars($employee['first_name'] ?? 'Employee'); ?></div>
                        <div class="sidebar-user-role"><?php echo htmlspecialchars($employee['employment_type'] ?? 'Regular'); ?></div>
                    </div>
                </div>
                <a href="../logout.php" class="sidebar-logout-btn" onclick="return confirm('Are you sure you want to logout?');">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <div class="employee-main">
            <nav class="employee-navbar">
                <div class="navbar-left">
                    <h2>
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard</span>
                    </h2>
                </div>
                <div class="navbar-right">
                    <span class="navbar-date"><?php echo date('l, F j, Y'); ?></span>
                </div>
            </nav>

            <div class="employee-content">
                <div class="welcome-header">
                    <h1>Welcome back, <?php echo htmlspecialchars($employee['first_name']); ?> 👋</h1>
                    <p><?php echo htmlspecialchars($employee['position']); ?> • <?php echo htmlspecialchars($employee['employment_type'] ?? 'Regular'); ?> • Employee ID: <?php echo htmlspecialchars($employee['employee_code']); ?></p>
                </div>

                <!-- ===== QUICK ACCESS SECTION ===== -->
                <div class="quick-access-section">
                    <h3><i class="fas fa-bolt"></i> Quick Access</h3>
                    <div class="quick-access-grid">
                        <!-- My Profile -->
                        <a href="view_profile.php" class="quick-access-btn info-btn" title="View and edit your profile">
                            <i class="fas fa-user-circle"></i>
                            <span>My Profile</span>
                        </a>

                        <!-- My Attendance -->
                        <a href="attendance.php" class="quick-access-btn success-btn" title="View your attendance records">
                            <i class="fas fa-calendar-check"></i>
                            <span>My Attendance</span>
                        </a>

                        <!-- Apply Leave (only for Regular employees) -->
                        <?php if ($employee['employment_type'] === 'Regular'): ?>
                            <a href="apply_leave.php" class="quick-access-btn warning-btn" title="Apply for leave">
                                <i class="fas fa-file-alt"></i>
                                <span>Apply Leave</span>
                            </a>
                        <?php else: ?>
                            <button class="quick-access-btn disabled" disabled title="Not available for <?php echo $employee['employment_type']; ?> employees">
                                <i class="fas fa-file-alt"></i>
                                <span>Apply Leave</span>
                            </button>
                        <?php endif; ?>

                        <!-- Leave Status (only for Regular employees) -->
                        <?php if ($employee['employment_type'] === 'Regular'): ?>
                            <a href="leave_status.php" class="quick-access-btn info-btn" title="Check your leave status">
                                <i class="fas fa-clipboard-list"></i>
                                <span>Leave Status</span>
                            </a>
                        <?php endif; ?>

                        <!-- My Documents -->
                        <a href="my_documents.php" class="quick-access-btn success-btn" title="Download your documents">
                            <i class="fas fa-folder-open"></i>
                            <span>My Documents</span>
                        </a>

                        <!-- My Payslips -->
                        <a href="salary_slips.php" class="quick-access-btn info-btn" title="View and download your payslips">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <span>My Payslips</span>
                        </a>

                        <!-- Announcements -->
                        <a href="announcements.php" class="quick-access-btn warning-btn" title="View company announcements">
                            <i class="fas fa-bullhorn"></i>
                            <span>Announcements</span>
                        </a>
                    </div>
                </div>
                <!-- ===== END QUICK ACCESS ===== -->

                <?php if ($total_attendance_records == 0): ?>
                <div class="setup-notice">
                    <strong>⏳ System Setup in Progress</strong><br>
                    <span style="font-size: 14px;">
                        The attendance system is being set up. HR Admin will import biometric data soon. Once imported, your attendance records will appear on the Attendance page.
                    </span>
                </div>
                <?php endif; ?>

                <div class="dashboard-grid">
                    <div class="stat-box info">
                        <h4>📅 This Month Attendance</h4>
                        <div class="stat-number"><?php echo $this_month_attendance; ?></div>
                        <?php if ($total_attendance_records == 0): ?>
                            <div class="badge-small">Pending Import</div>
                        <?php endif; ?>
                    </div>

                    <?php if ($employee['employment_type'] === 'Regular'): ?>
                        <div class="stat-box warning">
                            <h4>⏳ Pending Leaves</h4>
                            <div class="stat-number"><?php echo $pending_leaves_count; ?></div>
                        </div>

                        <div class="stat-box success">
                            <h4>✅ Approved Leaves</h4>
                            <div class="stat-number"><?php echo $approved_leaves_count; ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="stat-box info">
                        <h4>📢 Announcements</h4>
                        <div class="stat-number"><?php echo $announcements_count; ?></div>
                    </div>
                </div>

                <div class="card">
                    <h3><i class="fas fa-folder-open"></i> My Documents & Files</h3>
                    
                    <?php if (!empty($employee_documents)): ?>
                        <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                            All your official documents are securely stored here. You can download them anytime you need.
                        </p>
                        <div class="documents-grid">
                            <?php foreach ($employee_documents as $doc): ?>
                                <div class="document-card">
                                    <div class="document-type">
                                        <?php 
                                            $type_labels = [
                                                'credentials' => 'Credentials',
                                                '201_file' => '201 File',
                                                'contract' => 'Contract',
                                                'cos' => 'COS',
                                                'diploma' => 'Diploma/TOR',
                                                'other' => 'Other'
                                            ];
                                            echo $type_labels[$doc['document_type']] ?? ucfirst(str_replace('_', ' ', $doc['document_type']));
                                        ?>
                                    </div>
                                    <div class="document-name">
                                        <i class="fas fa-file"></i> <?php echo htmlspecialchars($doc['document_name']); ?>
                                    </div>
                                    <div class="document-meta">
                                        <strong>Size:</strong> <?php echo number_format((float)($doc['file_size'] / 1024), 2); ?> KB<br>
                                        <strong>Uploaded:</strong> <?php echo htmlspecialchars(date('M d, Y', strtotime($doc['uploaded_at']))); ?>
                                        <?php if (!empty($doc['notes'])): ?>
                                            <br><strong>Notes:</strong> <?php echo htmlspecialchars($doc['notes']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="document-actions">
                                        <a href="dashboard.php?download_document=<?php echo (int)$doc['document_id']; ?>" class="btn-download">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-documents">
                            <p>📁 No documents uploaded yet.</p>
                            <p style="font-size: 12px; color: #999; margin-top: 8px;">
                                HR will upload your official documents (credentials, 201 file, contracts, etc.) here. You'll be able to download them anytime.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="chart-card">
                    <h3><i class="fas fa-line-chart"></i> Your Attendance Progress (Last 6 Months)</h3>
                    <div class="chart-wrapper">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="card">
                        <h3><i class="fas fa-bell"></i> Recent Announcements</h3>
                        
                        <?php if (!empty($latest_announcements)): ?>
                            <div class="announcement-list">
                                <?php foreach ($latest_announcements as $ann): ?>
                                    <div class="announcement-card">
                                        <div class="announcement-title"><?php echo htmlspecialchars($ann['title']); ?></div>
                                        <div class="announcement-preview">
                                            <?php echo htmlspecialchars(substr($ann['content'] ?? $ann['message'] ?? '', 0, 100)); ?>
                                            <?php if (strlen($ann['content'] ?? $ann['message'] ?? '') > 100) echo '...'; ?>
                                        </div>
                                        <div class="announcement-date">
                                            📅 <?php echo format_datetime($ann['created_at']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <a href="announcements.php" class="view-all-link">View all announcements →</a>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>📭 No announcements at this time</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <h3><i class="fas fa-info-circle"></i> Quick Guide</h3>
                        <p style="color: #666; line-height: 1.8; font-size: 14px; margin: 0 0 15px 0;">
                            👋 Welcome to your employee dashboard! Manage your HR-related tasks and access important information.
                        </p>
                        <ul class="quick-summary-list">
                            <li>📊 View your attendance records and progress</li>
                            <?php if ($employee['employment_type'] === 'Regular'): ?>
                                <li>📝 Apply for leave and track request status</li>
                            <?php else: ?>
                                <li>📝 As a <?php echo htmlspecialchars($employee['employment_type']); ?> employee, leave applications are not available</li>
                            <?php endif; ?>
                            <li>💰 Access and download your payslips anytime</li>
                            <li>📁 Download all your official documents securely</li>
                            <li>👤 View and update your profile information</li>
                            <li>📢 Stay updated with company announcements</li>
                        </ul>
                        <p style="font-size: 12px; color: #999; margin-top: 15px; margin-bottom: 0;">
                            For HR-related questions, please contact the HR Department.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const sidebarToggle = document.getElementById('sidebarToggle');
        const employeeSidebar = document.getElementById('employeeSidebar');

        sidebarToggle.addEventListener('click', function() {
            employeeSidebar.classList.toggle('collapsed');
        });

        document.querySelectorAll('.sidebar-menu-link').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    employeeSidebar.classList.remove('mobile-open');
                }
            });
        });

        const currentPage = window.location.pathname.split('/').pop();
        document.querySelectorAll('.sidebar-menu-link').forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPage || (currentPage === '' && href === 'dashboard.php')) {
                link.classList.add('active');
            }
        });

        const attendanceChart = new Chart(document.getElementById('attendanceChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($attendance_labels); ?>,
                datasets: [{
                    label: 'Attendance Records',
                    data: <?php echo json_encode($attendance_values); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.15)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 5,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 13
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: 'rgba(200, 200, 200, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
