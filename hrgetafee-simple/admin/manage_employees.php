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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (empty($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid or expired form token.';
    } else {
        // add employee WITH documents
        if ($action === 'add_employee_with_documents') {
            $first_name = sanitizeInput($_POST['first_name'] ?? '');
            $last_name = sanitizeInput($_POST['last_name'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $position = sanitizeInput($_POST['position'] ?? '');
            $employment_type = sanitizeInput($_POST['employment_type'] ?? 'Regular');
            $salary = floatval($_POST['salary'] ?? 0);
            $date_hired = sanitizeInput($_POST['date_hired'] ?? date('Y-m-d'));
            $status = sanitizeInput($_POST['status'] ?? 'active');

            if ($first_name === '' || $last_name === '' || $email === '') {
                $error = 'First name, last name, and email are required.';
            } else {
                $year = date('Y');
                $like = "GETAFE-{$year}-%";
                $stmt = $conn->prepare("SELECT employee_code FROM employees WHERE employee_code LIKE ? ORDER BY employee_code DESC LIMIT 1");
                $stmt->bind_param('s', $like);
                $stmt->execute();
                $res = $stmt->get_result();
                $last_emp = $res->fetch_assoc();
                $stmt->close();

                $next_num = 1;
                if ($last_emp && preg_match('/GETAFE-\d+-(\d+)/', $last_emp['employee_code'], $matches)) {
                    $next_num = intval($matches[1]) + 1;
                }

                $employee_code = 'GETAFE-' . $year . '-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);

                $stmt = $conn->prepare("INSERT INTO employees (employee_code, first_name, last_name, email, phone, position, employment_type, salary, date_hired, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssssdsss', $employee_code, $first_name, $last_name, $email, $phone, $position, $employment_type, $salary, $date_hired, $status);

                if ($stmt->execute()) {
                    $new_employee_id = $stmt->insert_id;
                    $stmt->close();

                    // ===== NOW HANDLE DOCUMENT UPLOADS =====
                    $documents_uploaded = 0;
                    $documents_failed = 0;

                    // Check if multiple documents are being uploaded
                    if (!empty($_FILES['document_files'])) {
                        $file_count = count($_FILES['document_files']['name']);

                        for ($i = 0; $i < $file_count; $i++) {
                            $file = [
                                'name' => $_FILES['document_files']['name'][$i],
                                'error' => $_FILES['document_files']['error'][$i],
                                'tmp_name' => $_FILES['document_files']['tmp_name'][$i],
                                'size' => $_FILES['document_files']['size'][$i]
                            ];

                            $document_type = sanitizeInput($_POST['document_type'][$i] ?? '');
                            $document_name = sanitizeInput($_POST['document_name_upload'][$i] ?? '');
                            $notes = sanitizeInput($_POST['notes_upload'][$i] ?? '');

                            // Validate
                            if (empty($document_type)) {
                                $documents_failed++;
                                continue;
                            }

                            if ($file['error'] !== UPLOAD_ERR_OK) {
                                $documents_failed++;
                                continue;
                            }

                            if (empty($document_name)) {
                                $document_name = $file['name'];
                            }

                            $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'xls', 'xlsx', 'txt'];
                            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            if (!in_array($ext, $allowed_types, true)) {
                                $documents_failed++;
                                continue;
                            }

                            $employee_dir = $upload_root . '/' . $new_employee_id;
                            if (!is_dir($employee_dir)) {
                                mkdir($employee_dir, 0777, true);
                            }

                            $safe_name = preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($file['name']));
                            $new_name = time() . '_' . $safe_name;
                            $file_path = $employee_dir . '/' . $new_name;

                            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                                $stmt_doc = $conn->prepare("INSERT INTO employee_documents (employee_id, document_type, document_name, file_path, file_size, uploaded_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                if ($stmt_doc) {
                                    $stmt_doc->bind_param('isssiis', $new_employee_id, $document_type, $document_name, $file_path, $file['size'], $_SESSION['user_id'], $notes);
                                    if ($stmt_doc->execute()) {
                                        $documents_uploaded++;
                                    } else {
                                        $documents_failed++;
                                    }
                                    $stmt_doc->close();
                                } else {
                                    $documents_failed++;
                                }
                            } else {
                                $documents_failed++;
                            }
                        }
                    }

                    // Success message
                    $message = '✅ Employee "' . htmlspecialchars($first_name . ' ' . $last_name) . '" added successfully with Employee Code: ' . htmlspecialchars($employee_code);
                    if ($documents_uploaded > 0) {
                        $message .= ' | ' . $documents_uploaded . ' document(s) uploaded.';
                    }
                    if ($documents_failed > 0) {
                        $message .= ' | ⚠️ ' . $documents_failed . ' document(s) failed to upload.';
                    }

                    logSecurityEvent($conn, $_SESSION['user_id'], 'ADD_EMPLOYEE', "Added employee: $employee_code with $documents_uploaded documents");

                    // Clear form
                    $first_name = $last_name = $email = $phone = $position = $salary = $date_hired = '';
                    $employment_type = 'Regular';
                    $status = 'active';

                } else {
                    $error = 'Error adding employee: ' . htmlspecialchars($stmt->error);
                    $stmt->close();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background: #f5f7fb; }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .card-form { background: white; padding: 22px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
        .form-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .btn { display: inline-block; padding: 9px 14px; border-radius: 8px; text-decoration: none; border: none; cursor: pointer; font-weight: 600; }
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #e5e7eb; color: #1f2937; }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #374151; }
        input, select, textarea { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; }
        textarea { min-height: 90px; resize: vertical; }
        .file-input { padding: 8px; }
        .document-upload-container { background: #f8fafc; padding: 16px; border-radius: 10px; margin-bottom: 14px; border: 2px dashed #e2e8f0; }
        .add-document-btn { background: #3b82f6; color: white; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; margin-top: 10px; }
        .add-document-btn:hover { background: #2563eb; }
        .remove-document-btn { background: #ef4444; color: white; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; border: none; }
        .navbar { background: white; padding: 15px 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: flex; justify-content: space-between; align-items: center; }
        .navbar h2 { margin: 0; font-size: 20px; color: #1f2937; }
        .navbar-user { font-size: 14px; color: #666; }
        .navbar-user a { color: #ef4444; text-decoration: none; margin-left: 15px; font-weight: 600; }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .navbar { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Add New Employee</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($admin['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 18px;">← Back to Dashboard</a>

        <!-- Add Employee WITH DOCUMENTS -->
        <div class="card-form">
            <form method="POST" enctype="multipart/form-data" id="addEmployeeForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="add_employee_with_documents">

                <!-- EMPLOYEE INFORMATION SECTION -->
                <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 2px solid #e5e7eb;">
                    <h4 style="color: #1f2937; margin-bottom: 14px;">👤 Employee Information</h4>
                    
                    <div class="form-row">
                        <div>
                            <label>First Name *</label>
                            <input type="text" name="first_name" required>
                        </div>
                        <div>
                            <label>Last Name *</label>
                            <input type="text" name="last_name" required>
                        </div>
                        <div>
                            <label>Email *</label>
                            <input type="email" name="email" required>
                        </div>
                        <div>
                            <label>Phone</label>
                            <input type="text" name="phone">
                        </div>
                        <div>
                            <label>Position</label>
                            <input type="text" name="position">
                        </div>
                        <div>
                            <label>Employment Type</label>
                            <select name="employment_type">
                                <option value="Regular">Regular</option>
                                <option value="Casual">Casual</option>
                                <option value="Job Order">Job Order</option>
                                <option value="COS">COS</option>
                            </select>
                        </div>
                        <div>
                            <label>Salary (₱)</label>
                            <input type="number" step="0.01" min="0" name="salary" value="0">
                        </div>
                        <div>
                            <label>Date Hired</label>
                            <input type="date" name="date_hired" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div>
                            <label>Status</label>
                            <select name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- DOCUMENT UPLOAD SECTION -->
                <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 2px solid #e5e7eb;">
                    <h4 style="color: #1f2937; margin-bottom: 14px;">📄 Upload Employee Documents</h4>
                    <p style="color: #666; font-size: 13px; margin-bottom: 14px;">Add documents (Diploma, Contract, 201 File, etc.) along with employee information</p>
                    
                    <div id="documentUploadContainer">
                        <!-- Documents will be added here by JavaScript -->
                    </div>

                    <button type="button" class="add-document-btn" id="addDocumentBtn">+ Add Another Document</button>
                </div>

                <!-- SUBMIT BUTTON -->
                <div style="margin-top: 16px;">
                    <button type="submit" class="btn btn-primary">Add Employee & Documents</button>
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript for dynamic document upload -->
    <script>
        let documentCount = 0;
        const maxDocuments = 10;

        function renderDocumentUpload() {
            const container = document.getElementById('documentUploadContainer');
            container.innerHTML = '';

            for (let i = 0; i < documentCount; i++) {
                const uploadDiv = document.createElement('div');
                uploadDiv.className = 'document-upload-container';
                uploadDiv.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h5 style="margin: 0; color: #1f2937;">Document ${i + 1}</h5>
                        ${i > 0 ? `<button type="button" class="remove-document-btn" onclick="removeDocument(${i})">Remove</button>` : ''}
                    </div>
                    <div class="form-row">
                        <div>
                            <label>Document Type *</label>
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
                            <label>Document Label *</label>
                            <input type="text" name="document_name_upload" placeholder="e.g., Diploma, Contract" required>
                        </div>
                    </div>
                    <div style="margin-top: 10px;">
                        <label>File *</label>
                        <input type="file" name="document_files" class="file-input" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.xls,.xlsx,.txt">
                    </div>
                    <div style="margin-top: 10px;">
                        <label>Notes</label>
                        <textarea name="notes_upload" placeholder="Optional notes or remarks"></textarea>
                    </div>
                `;
                container.appendChild(uploadDiv);
            }
        }

        document.getElementById('addDocumentBtn').addEventListener('click', function() {
            if (documentCount < maxDocuments) {
                documentCount++;
                renderDocumentUpload();
            } else {
                alert('Maximum ' + maxDocuments + ' documents allowed');
            }
        });

        function removeDocument(index) {
            documentCount--;
            renderDocumentUpload();
        }

        // Initialize with 1 document on page load
        window.addEventListener('load', function() {
            if (document.getElementById('documentUploadContainer')) {
                documentCount = 1;
                renderDocumentUpload();
            }
        });
    </script>
</body>
</html>
