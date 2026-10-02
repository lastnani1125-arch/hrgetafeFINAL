<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

require_login();
check_role(1);

$admin = get_employee_info($conn, $_SESSION['employee_id']);
$message = '';
$error = '';

// upload folder
$upload_root = __DIR__ . '/../uploads/employee_documents';
if (!is_dir($upload_root)) {
    mkdir($upload_root, 0777, true);
}

// Handle document download
if (isset($_GET['download_document'])) {
    $document_id = intval($_GET['download_document']);
    $stmt = $conn->prepare("SELECT * FROM employee_documents WHERE document_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $document_id);
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
        } else {
            $error = 'Document file not found.';
        }
    }
}

// Handle document archive/activate
if (isset($_GET['toggle_document'])) {
    $document_id = intval($_GET['toggle_document']);
    $stmt = $conn->prepare("SELECT is_active FROM employee_documents WHERE document_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $document_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $doc = $res->fetch_assoc();
        $stmt->close();

        if ($doc) {
            $new_status = ($doc['is_active'] == 1) ? 0 : 1;
            $update = $conn->prepare("UPDATE employee_documents SET is_active = ? WHERE document_id = ?");
            if ($update) {
                $update->bind_param('ii', $new_status, $document_id);
                $update->execute();
                $update->close();
                $message = '✅ Document status updated successfully.';
            }
        }
    }
}

// Handle employee update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid or expired form token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_employee') {
            $employee_id = intval($_POST['employee_id'] ?? 0);
            $first_name = sanitizeInput($_POST['first_name'] ?? '');
            $last_name = sanitizeInput($_POST['last_name'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $position = sanitizeInput($_POST['position'] ?? '');
            $employment_type = sanitizeInput($_POST['employment_type'] ?? 'Regular');
            $salary = floatval($_POST['salary'] ?? 0);
            $date_hired = sanitizeInput($_POST['date_hired'] ?? date('Y-m-d'));
            $status = sanitizeInput($_POST['status'] ?? 'active');

            if ($employee_id <= 0 || $first_name === '' || $last_name === '') {
                $error = '❌ Invalid input. Please check all required fields.';
            } else {
                $stmt = $conn->prepare("UPDATE employees SET first_name=?, last_name=?, email=?, phone=?, position=?, employment_type=?, salary=?, date_hired=?, status=? WHERE employee_id=?");
                $stmt->bind_param('ssssssdssi', $first_name, $last_name, $email, $phone, $position, $employment_type, $salary, $date_hired, $status, $employee_id);
                if ($stmt->execute()) {
                    $message = '✅ Employee information updated successfully.';
                    logSecurityEvent($conn, $_SESSION['user_id'], 'EDIT_EMPLOYEE', "Updated employee_id: $employee_id");
                } else {
                    $error = '❌ Error updating employee: ' . htmlspecialchars($stmt->error);
                }
                $stmt->close();
            }
        }

        // Handle document upload
        elseif ($action === 'upload_document') {
            $employee_id = intval($_POST['employee_id'] ?? 0);
            $document_type = sanitizeInput($_POST['document_type'] ?? '');
            $document_name = sanitizeInput($_POST['document_name'] ?? '');
            $notes = sanitizeInput($_POST['notes'] ?? '');

            if ($employee_id <= 0 || empty($document_type)) {
                $error = '❌ Employee and Document Type are required.';
            } elseif (empty($_FILES['document_file']['name'])) {
                $error = '❌ Please select a file to upload.';
            } else {
                $file = $_FILES['document_file'];

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $error = '❌ Error uploading file: ' . htmlspecialchars($file['error']);
                } else {
                    // Validate file type
                    $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'xls', 'xlsx', 'txt'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    if (!in_array($ext, $allowed_types, true)) {
                        $error = '❌ File type not allowed. Allowed: PDF, DOC, DOCX, JPG, PNG, GIF, XLS, XLSX, TXT';
                    } elseif ($file['size'] > 10485760) { // 10MB limit
                        $error = '❌ File size exceeds 10MB limit.';
                    } else {
                        // Create employee directory if not exists
                        $employee_dir = $upload_root . '/' . $employee_id;
                        if (!is_dir($employee_dir)) {
                            mkdir($employee_dir, 0777, true);
                        }

                        // Generate safe filename
                        $safe_name = preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($file['name']));
                        $new_name = time() . '_' . $safe_name;
                        $file_path = $employee_dir . '/' . $new_name;

                        if (move_uploaded_file($file['tmp_name'], $file_path)) {
                            // Use document_name from form if provided, otherwise use uploaded filename
                            if (empty($document_name)) {
                                $document_name = $file['name'];
                            }

                            $stmt_doc = $conn->prepare("INSERT INTO employee_documents (employee_id, document_type, document_name, file_path, file_size, uploaded_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            if ($stmt_doc) {
                                $stmt_doc->bind_param('isssiis', $employee_id, $document_type, $document_name, $file_path, $file['size'], $_SESSION['user_id'], $notes);
                                if ($stmt_doc->execute()) {
                                    $message = '✅ Document uploaded successfully.';
                                    logSecurityEvent($conn, $_SESSION['user_id'], 'UPLOAD_DOCUMENT', "Uploaded document for employee_id: $employee_id");
                                } else {
                                    $error = '❌ Error saving document: ' . htmlspecialchars($stmt_doc->error);
                                    unlink($file_path);
                                }
                                $stmt_doc->close();
                            } else {
                                $error = '❌ Database error.';
                                unlink($file_path);
                            }
                        } else {
                            $error = '❌ Error moving uploaded file.';
                        }
                    }
                }
            }
        }
    }
}

// Get filter parameters
$status_filter = sanitizeInput($_GET['status'] ?? 'active');
$search_term = sanitizeInput($_GET['search'] ?? '');

// Get employees with filter
$query = "SELECT * FROM employees WHERE status = ?";
$params = [$status_filter];
$types = 's';

if (!empty($search_term)) {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR employee_code LIKE ?)";
    $search_param = '%' . $search_term . '%';
    $params = array_merge([$status_filter], [$search_param, $search_param, $search_param, $search_param]);
    $types = 'sssss';
}

$query .= " ORDER BY employee_code";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $employees = [];
}

// Get counts
$active_count = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'")->fetch_assoc()['count'];
$inactive_count = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'inactive'")->fetch_assoc()['count'];

// Get selected employee
$selected_employee_id = intval($_GET['employee_id'] ?? 0);
$selected_employee = null;
$employee_documents = [];

if ($selected_employee_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $selected_employee_id);
        $stmt->execute();
        $selected_employee = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if ($selected_employee) {
        $stmt = $conn->prepare("SELECT * FROM employee_documents WHERE employee_id = ? ORDER BY uploaded_at DESC");
        if ($stmt) {
            $stmt->bind_param('i', $selected_employee_id);
            $stmt->execute();
            $employee_documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Records - HRGetafe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f5f7fb; font-family: 'Segoe UI', Tahoma, Verdana, sans-serif; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .navbar { background: white; padding: 15px 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .navbar h2 { margin: 0; font-size: 20px; color: #1f2937; }
        .navbar-user { font-size: 14px; color: #666; }
        .navbar-user a { color: #ef4444; text-decoration: none; margin-left: 15px; font-weight: 600; }
        .card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); padding: 20px; margin-bottom: 20px; }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .tab-buttons { display: flex; gap: 10px; margin-bottom: 14px; }
        .tab-btn { padding: 8px 14px; border: 2px solid #e5e7eb; background: white; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.2s; }
        .tab-btn.active { background: #667eea; color: white; border-color: #667eea; }
        .tab-btn:hover { border-color: #667eea; }
        .search-box { margin-bottom: 14px; }
        .search-box input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; }
        .employee-list { max-height: 700px; overflow-y: auto; }
        .employee-item { padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 10px; cursor: pointer; transition: all 0.2s; }
        .employee-item:hover { background: #f8fafc; border-color: #667eea; }
        .employee-item.active { background: #f0f4ff; border-color: #667eea; border-left: 4px solid #667eea; }
        .employee-item strong { color: #1f2937; }
        .employee-item small { color: #6b7280; }
        .form-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; margin-bottom: 14px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 13px; }
        input, select, textarea { width: 100%; padding: 9px 11px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; }
        textarea { min-height: 80px; resize: vertical; }
        .btn { display: inline-block; padding: 8px 12px; border-radius: 6px; text-decoration: none; border: none; cursor: pointer; font-weight: 600; font-size: 12px; }
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #e5e7eb; color: #1f2937; }
        .btn-success { background: #10b981; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn:hover { opacity: 0.9; }
        .doc-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 10px; }
        .doc-card.active { border-left: 4px solid #10b981; }
        .doc-card.inactive { border-left: 4px solid #f59e0b; }
        .doc-actions { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; }
        .empty-state { color: #6b7280; text-align: center; padding: 40px 20px; }
        .upload-section { background: #f0f4ff; border: 2px dashed #667eea; border-radius: 8px; padding: 14px; margin-bottom: 14px; }
        .print-section { display: none; }
        @media print {
            .print-section { display: block; }
            body > *:not(.print-section) { display: none; }
            .navbar, .employee-list, .btn, .tab-buttons, .search-box { display: none; }
        }
        @media (max-width: 1024px) {
            .grid-2 { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Employee Records</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($admin['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div style="margin-bottom: 18px;">
            <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            <a href="manage_employees.php" class="btn btn-primary">+ Add New Employee</a>
        </div>

        <div class="grid-2">
            <!-- LEFT: EMPLOYEE LIST WITH FILTERS -->
            <div class="card">
                <h3 style="margin-bottom: 14px;">👥 Employee List</h3>

                <!-- Status Filter Tabs -->
                <div class="tab-buttons">
                    <form method="GET" style="display: flex; gap: 10px; width: 100%;">
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                        <button type="submit" name="status" value="active" class="tab-btn <?php echo ($status_filter === 'active') ? 'active' : ''; ?>">
                            ✅ Active (<?php echo $active_count; ?>)
                        </button>
                        <button type="submit" name="status" value="inactive" class="tab-btn <?php echo ($status_filter === 'inactive') ? 'active' : ''; ?>">
                            ⏸️ Inactive (<?php echo $inactive_count; ?>)
                        </button>
                    </form>
                </div>

                <!-- Search Filter -->
                <div class="search-box">
                    <form method="GET">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                        <input type="text" name="search" placeholder="🔍 Search by name, email, or code..." value="<?php echo htmlspecialchars($search_term); ?>" onchange="this.form.submit();">
                    </form>
                </div>

                <!-- Employee List -->
                <div class="employee-list">
                    <?php if (empty($employees)): ?>
                        <div class="empty-state">
                            <p>No employees found.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($employees as $emp): ?>
                            <div class="employee-item <?php echo ($selected_employee_id === (int)$emp['employee_id']) ? 'active' : ''; ?>" 
                                 onclick="window.location.href='?status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo htmlspecialchars($search_term); ?>&employee_id=<?php echo (int)$emp['employee_id']; ?>'">
                                <strong><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($emp['employee_code']); ?></small><br>
                                <small><?php echo htmlspecialchars($emp['position'] ?: 'N/A'); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT: EMPLOYEE DETAILS & DOCUMENTS -->
            <?php if ($selected_employee): ?>
                <div>
                    <!-- EMPLOYEE INFO FORM -->
                    <div class="card">
                        <h3 style="margin-bottom: 14px;">✏️ Edit Employee Information</h3>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="update_employee">
                            <input type="hidden" name="employee_id" value="<?php echo (int)$selected_employee['employee_id']; ?>">

                            <div class="form-row">
                                <div><label>First Name *</label>
                                    <input type="text" name="first_name" required value="<?php echo htmlspecialchars($selected_employee['first_name']); ?>"></div>
                                <div><label>Last Name *</label>
                                    <input type="text" name="last_name" required value="<?php echo htmlspecialchars($selected_employee['last_name']); ?>"></div>
                            </div>
                            <div class="form-row">
                                <div><label>Email</label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($selected_employee['email']); ?>"></div>
                                <div><label>Phone</label>
                                    <input type="text" name="phone" value="<?php echo htmlspecialchars($selected_employee['phone'] ?? ''); ?>"></div>
                            </div>
                            <div class="form-row">
                                <div><label>Position</label>
                                    <input type="text" name="position" value="<?php echo htmlspecialchars($selected_employee['position'] ?? ''); ?>"></div>
                                <div><label>Employment Type</label>
                                    <select name="employment_type">
                                        <option value="Regular" <?php echo (($selected_employee['employment_type'] ?? '') === 'Regular') ? 'selected' : ''; ?>>Regular</option>
                                        <option value="Casual" <?php echo (($selected_employee['employment_type'] ?? '') === 'Casual') ? 'selected' : ''; ?>>Casual</option>
                                        <option value="Job Order" <?php echo (($selected_employee['employment_type'] ?? '') === 'Job Order') ? 'selected' : ''; ?>>Job Order</option>
                                        <option value="COS" <?php echo (($selected_employee['employment_type'] ?? '') === 'COS') ? 'selected' : ''; ?>>COS</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div><label>Salary (₱)</label>
                                    <input type="number" step="0.01" min="0" name="salary" value="<?php echo htmlspecialchars($selected_employee['salary'] ?? '0'); ?>"></div>
                                <div><label>Date Hired</label>
                                    <input type="date" name="date_hired" value="<?php echo htmlspecialchars($selected_employee['date_hired']); ?>"></div>
                            </div>
                            <div class="form-row">
                                <div><label>Status</label>
                                    <select name="status">
                                        <option value="active" <?php echo (($selected_employee['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo (($selected_employee['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>

                            <div style="margin-top: 14px;">
                                <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                            </div>
                        </form>
                    </div>

                    <!-- DOCUMENTS -->
                    <div class="card">
                        <h3 style="margin-bottom: 14px;">📄 Employee Documents</h3>

                        <!-- UPLOAD NEW DOCUMENT FORM -->
                        <div class="upload-section">
                            <h4 style="margin-bottom: 12px; color: #1f2937;">📤 Upload New Document</h4>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="upload_document">
                                <input type="hidden" name="employee_id" value="<?php echo (int)$selected_employee['employee_id']; ?>">

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                                    <div>
                                        <label style="font-size: 12px;">Document Type *</label>
                                        <select name="document_type" required>
                                            <option value="">-- Select --</option>
                                            <option value="credentials">Credentials</option>
                                            <option value="201_file">201 File</option>
                                            <option value="contract">Contract</option>
                                            <option value="cos">COS</option>
                                            <option value="diploma">Diploma / TOR</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size: 12px;">Document Label</label>
                                        <input type="text" name="document_name" placeholder="e.g., Diploma 2023">
                                    </div>
                                </div>

                                <div style="margin-bottom: 10px;">
                                    <label style="font-size: 12px;">File *</label>
                                    <input type="file" name="document_file" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.xls,.xlsx,.txt">
                                </div>

                                <div style="margin-bottom: 10px;">
                                    <label style="font-size: 12px;">Notes</label>
                                    <textarea name="notes" placeholder="Optional notes or remarks" style="min-height: 60px;"></textarea>
                                </div>

                                <button type="submit" class="btn btn-success">📤 Upload Document</button>
                            </form>
                        </div>

                        <!-- EXISTING DOCUMENTS LIST -->
                        <?php if (empty($employee_documents)): ?>
                            <div class="empty-state">
                                <p>No documents uploaded yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($employee_documents as $doc): ?>
                                <div class="doc-card <?php echo ($doc['is_active'] == 1) ? 'active' : 'inactive'; ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($doc['document_name']); ?></strong><br>
                                            <small style="color: #6b7280;">
                                                Type: <?php echo htmlspecialchars($doc['document_type']); ?> | 
                                                Size: <?php echo number_format((float)($doc['file_size'] / 1024), 2); ?> KB
                                            </small><br>
                                            <small style="color: #999;">
                                                Uploaded: <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($doc['uploaded_at']))); ?>
                                            </small>
                                        </div>
                                        <span class="tag <?php echo ($doc['is_active'] == 1) ? 'tag-active' : 'tag-inactive'; ?>">
                                            <?php echo ($doc['is_active'] == 1) ? 'Active' : 'Archived'; ?>
                                        </span>
                                    </div>

                                    <?php if (!empty($doc['notes'])): ?>
                                        <div style="margin-top: 8px; padding: 8px; background: white; border-radius: 6px; font-size: 12px; color: #64748b;">
                                            <strong>Notes:</strong> <?php echo htmlspecialchars($doc['notes']); ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="doc-actions">
                                        <a href="?status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo htmlspecialchars($search_term); ?>&employee_id=<?php echo $selected_employee_id; ?>&download_document=<?php echo (int)$doc['document_id']; ?>" class="btn btn-success">⬇️ Download</a>
                                        <a href="?status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo htmlspecialchars($search_term); ?>&employee_id=<?php echo $selected_employee_id; ?>&toggle_document=<?php echo (int)$doc['document_id']; ?>" class="btn btn-warning">
                                            <?php echo ($doc['is_active'] == 1) ? '🗂️ Archive' : '📂 Activate'; ?>
                                        </a>
                                        <button type="button" class="btn btn-secondary" onclick="window.print()">🖨️ Print Document</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="empty-state">
                        <p>Select an employee to view details and documents</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PRINT VIEW FOR DOCUMENTS -->
    <?php if ($selected_employee): ?>
        <div class="print-section">
            <div style="padding: 20px; font-family: Arial, sans-serif;">
                <h1 style="text-align: center; color: #2c3e50;">Employee Document Record</h1>
                <p style="text-align: center; color: #666;">HRGetafe - Human Resources Information System</p>

                <div style="border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 8px;">
                    <table style="width: 100%;">
                        <tr>
                            <td style="width: 50%;"><strong>Employee Code:</strong> <?php echo htmlspecialchars($selected_employee['employee_code']); ?></td>
                            <td><strong>Status:</strong> <?php echo htmlspecialchars($selected_employee['status']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Name:</strong> <?php echo htmlspecialchars($selected_employee['first_name'] . ' ' . $selected_employee['last_name']); ?></td>
                            <td><strong>Email:</strong> <?php echo htmlspecialchars($selected_employee['email']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Position:</strong> <?php echo htmlspecialchars($selected_employee['position'] ?? 'N/A'); ?></td>
                            <td><strong>Employment Type:</strong> <?php echo htmlspecialchars($selected_employee['employment_type'] ?? 'Regular'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Phone:</strong> <?php echo htmlspecialchars($selected_employee['phone'] ?? 'N/A'); ?></td>
                            <td><strong>Date Hired:</strong> <?php echo htmlspecialchars(date('M d, Y', strtotime($selected_employee['date_hired']))); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Salary:</strong> ₱<?php echo number_format((float)$selected_employee['salary'], 2); ?></td>
                            <td></td>
                        </tr>
                    </table>
                </div>

                <?php if (!empty($employee_documents)): ?>
                    <h2 style="color: #2c3e50; border-bottom: 2px solid #ddd; padding-bottom: 10px;">Documents</h2>
                    <table style="width: 100%; margin-top: 10px; border: 1px solid #ddd;">
                        <thead>
                            <tr style="background: #f5f5f5;">
                                <th style="padding: 10px; border: 1px solid #ddd;">Document Name</th>
                                <th style="padding: 10px; border: 1px solid #ddd;">Type</th>
                                <th style="padding: 10px; border: 1px solid #ddd;">Uploaded</th>
                                <th style="padding: 10px; border: 1px solid #ddd;">Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employee_documents as $doc): ?>
                                <?php if ($doc['is_active'] == 1): ?>
                                    <tr>
                                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo htmlspecialchars($doc['document_name']); ?></td>
                                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo htmlspecialchars($doc['document_type']); ?></td>
                                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo htmlspecialchars(date('M d, Y', strtotime($doc['uploaded_at']))); ?></td>
                                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo number_format((float)($doc['file_size'] / 1024), 2); ?> KB</td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <p style="margin-top: 40px; text-align: center; color: #666; font-size: 12px;">
                    Printed on: <?php echo date('M d, Y h:i A'); ?>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <style>
        .tag { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .tag-active { background: #dcfce7; color: #166534; }
        .tag-inactive { background: #fef3c7; color: #92400e; }
    </style>
</body>
</html>
