<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

require_login();
check_role(1);

$admin = get_employee_info($conn, $_SESSION['employee_id']);

$success_message = '';
$error_message = '';
$action = $_GET['action'] ?? 'list';
$announcement_id = $_GET['id'] ?? null;

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM announcements WHERE announcement_id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success_message = "Announcement deleted successfully.";
            logSecurityEvent($conn, $_SESSION['user_id'], 'DELETE_ANNOUNCEMENT', "Deleted announcement ID: $id");
        } else {
            $error_message = "Failed to delete announcement.";
        }
        $stmt->close();
        $action = 'list';
    }
    
    // Handle add/edit
    elseif ($_POST['action'] === 'save') {
        $title = sanitizeInput($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $visible_to_roles = $_POST['visible_to_roles'] ?? '';
        $announcement_id = isset($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : null;
        
        // Validate
        if (empty($title) || empty($content)) {
            $error_message = "Title and content are required.";
        } else {
            if ($announcement_id) {
                // UPDATE
                $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, visible_to_roles = ?, updated_at = NOW() WHERE announcement_id = ?");
                $stmt->bind_param("sssi", $title, $content, $visible_to_roles, $announcement_id);
                if ($stmt->execute()) {
                    $success_message = "Announcement updated successfully.";
                    logSecurityEvent($conn, $_SESSION['user_id'], 'UPDATE_ANNOUNCEMENT', "Updated announcement ID: $announcement_id");
                } else {
                    $error_message = "Failed to update announcement.";
                }
                $stmt->close();
            } else {
                // INSERT
                $created_by = $_SESSION['user_id'];
                $stmt = $conn->prepare("INSERT INTO announcements (title, content, created_by, visible_to_roles, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->bind_param("ssss", $title, $content, $created_by, $visible_to_roles);
                if ($stmt->execute()) {
                    $success_message = "Announcement created successfully.";
                    logSecurityEvent($conn, $_SESSION['user_id'], 'CREATE_ANNOUNCEMENT', "Created new announcement: $title");
                } else {
                    $error_message = "Failed to create announcement.";
                }
                $stmt->close();
            }
            $action = 'list';
        }
    }
}

// Get announcement for edit
$announcement = null;
if ($action === 'edit' && $announcement_id) {
    $stmt = $conn->prepare("SELECT * FROM announcements WHERE announcement_id = ?");
    $stmt->bind_param("i", $announcement_id);
    $stmt->execute();
    $announcement = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$announcement) {
        $error_message = "Announcement not found.";
        $action = 'list';
    }
}

// Get all announcements
$announcements = [];
if ($action === 'list') {
    $result = $conn->query("SELECT a.*, CONCAT(e.first_name, ' ', e.last_name) as created_by_name FROM announcements a LEFT JOIN employees e ON a.created_by = (SELECT employee_id FROM users WHERE user_id = a.created_by LIMIT 1) ORDER BY a.created_at DESC");
    $announcements = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Announcements - HRGetafe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ============ ROOT STYLES ============ */
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

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar (reuse admin styles) */
        .admin-sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: #ecf0f1;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 3px 0 15px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 25px 15px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            flex-shrink: 0;
        }

        .sidebar-logo {
            font-size: 28px;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
        }

        .sidebar-logo-text {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.8);
            letter-spacing: 0.5px;
        }

        /* Main Content */
        .admin-main {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
        }

        .admin-navbar {
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

        .admin-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            background: #f8f9fa;
        }

        /* ============ MESSAGES ============ */
        .alert-box {
            padding: 16px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #d1fae5;
            border-left-color: #10b981;
            color: #065f46;
        }

        .alert-danger {
            background: #fee2e2;
            border-left-color: #ef4444;
            color: #7f1d1d;
        }

        /* ============ BUTTONS ============ */
        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        /* ============ TABLE ============ */
        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-top: 4px solid var(--primary-color);
        }

        .card h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #1f2937;
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: space-between;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f3f4f6;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            font-size: 13px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 13px;
        }

        tr:hover {
            background: #f9fafb;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 11px;
        }

        /* ============ FORM ============ */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 13px;
            transition: border-color 0.3s;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 200px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        /* ============ RESPONSIVE ============ */
        @media (max-width: 768px) {
            .admin-content {
                padding: 15px;
            }

            .admin-navbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .card-header {
                flex-direction: column;
                gap: 15px;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 8px;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Minimal Sidebar for navigation -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-briefcase"></i>
                </div>
                <span class="sidebar-logo-text">Admin Panel</span>
            </div>

            <div style="padding: 20px; flex: 1; overflow-y: auto;">
                <ul style="list-style: none; margin: 0; padding: 0;">
                    <li style="margin-bottom: 10px;">
                        <a href="dashboard.php" style="display: flex; align-items: center; gap: 12px; padding: 10px 15px; color: rgba(255, 255, 255, 0.75); text-decoration: none; border-radius: 6px; transition: 0.3s;">
                            <i class="fas fa-home" style="width: 20px;"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="manage_announcements.php" style="display: flex; align-items: center; gap: 12px; padding: 10px 15px; color: white; text-decoration: none; border-radius: 6px; background: rgba(255, 255, 255, 0.1);">
                            <i class="fas fa-bullhorn" style="width: 20px;"></i>
                            <span>Announcements</span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="admin-main">
            <nav class="admin-navbar">
                <div class="navbar-left">
                    <h2>
                        <i class="fas fa-bullhorn"></i>
                        <span><?php echo $action === 'list' ? 'Announcements' : ($action === 'add' ? 'Create Announcement' : 'Edit Announcement'); ?></span>
                    </h2>
                </div>
            </nav>

            <div class="admin-content">
                <!-- Success/Error Messages -->
                <?php if ($success_message): ?>
                    <div class="alert-box alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo $success_message; ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert-box alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $error_message; ?></span>
                    </div>
                <?php endif; ?>

                <!-- LIST VIEW -->
                <?php if ($action === 'list'): ?>
                <div class="card">
                    <h3>
                        <span>Announcements Management</span>
                        <a href="?action=add" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Announcement
                        </a>
                    </h3>

                    <?php if (empty($announcements)): ?>
                        <div class="no-data">
                            <p>📭 No announcements yet. <a href="?action=add" style="color: var(--primary-color); font-weight: 600;">Create the first one</a></p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 5%;">ID</th>
                                    <th style="width: 30%;">Title</th>
                                    <th style="width: 30%;">Preview</th>
                                    <th style="width: 15%;">Created By</th>
                                    <th style="width: 10%;">Date</th>
                                    <th style="width: 10%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($announcements as $ann): ?>
                                <tr>
                                    <td>#<?php echo $ann['announcement_id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($ann['title']); ?></strong>
                                    </td>
                                    <td>
                                        <?php 
                                            $preview = htmlspecialchars(substr($ann['content'] ?? '', 0, 50));
                                            echo $preview . (strlen($ann['content'] ?? '') > 50 ? '...' : '');
                                        ?>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($ann['created_by_name'] ?? 'System'); ?></small>
                                    </td>
                                    <td>
                                        <small style="color: #999;">📅 <?php echo date('M d, Y', strtotime($ann['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?action=edit&id=<?php echo $ann['announcement_id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this announcement? This cannot be undone.');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $ann['announcement_id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- ADD/EDIT VIEW -->
                <?php elseif ($action === 'add' || $action === 'edit'): ?>
                <div class="card">
                    <h3><?php echo $action === 'add' ? 'Create New Announcement' : 'Edit Announcement'; ?></h3>

                    <form method="POST" action="">
                        <input type="hidden" name="action" value="save">
                        <?php if ($announcement): ?>
                            <input type="hidden" name="announcement_id" value="<?php echo $announcement['announcement_id']; ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="title">
                                <i class="fas fa-heading"></i> Announcement Title
                            </label>
                            <input type="text" id="title" name="title" placeholder="Enter announcement title" required 
                                   value="<?php echo $announcement ? htmlspecialchars($announcement['title']) : ''; ?>"
                                   maxlength="255">
                        </div>

                        <div class="form-group">
                            <label for="content">
                                <i class="fas fa-file-alt"></i> Content
                            </label>
                            <textarea id="content" name="content" placeholder="Enter announcement content..." required><?php echo $announcement ? htmlspecialchars($announcement['content']) : ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="visible_to_roles">
                                <i class="fas fa-eye"></i> Visible To Roles (comma-separated: 1=Admin, 2=HR Staff, 3=Employee)
                            </label>
                            <input type="text" id="visible_to_roles" name="visible_to_roles" placeholder="e.g., 1,2,3" 
                                   value="<?php echo $announcement ? htmlspecialchars($announcement['visible_to_roles']) : '1,2,3'; ?>">
                            <small style="color: #666; display: block; margin-top: 5px;">Leave empty to show to all roles</small>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create Announcement' : 'Update Announcement'; ?>
                            </button>
                            <a href="manage_announcements.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
