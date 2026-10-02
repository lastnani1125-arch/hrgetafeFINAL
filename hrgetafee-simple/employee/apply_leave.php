<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

require_login();
check_role(3);

$employee = get_employee_info($conn, $_SESSION['employee_id']);
$employee_id = $_SESSION['employee_id'];

$error_message = '';
$success_message = '';

// Check if employee is regular
if (($employee['employment_type'] ?? 'Regular') !== 'Regular') {
    $error_message = 'Leave requests are available only for Regular employees.';
}

// Get leave types
$stmt = $conn->prepare("SELECT * FROM leave_types WHERE is_deleted = FALSE ORDER BY leave_type_name ASC");
if (!$stmt) {
    die("Error: " . $conn->error);
}
$stmt->execute();
$result = $stmt->get_result();
$leave_types = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get holidays
$start_date = date('Y-m-01');
$end_date = date('Y-m-t', strtotime('+1 month'));
$stmt = $conn->prepare("SELECT * FROM holidays WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date ASC");
if (!$stmt) {
    die("Error: " . $conn->error);
}
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$holidays = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = '❌ Invalid form token. Please try again.';
    } else {
        if (($employee['employment_type'] ?? 'Regular') !== 'Regular') {
            $error_message = '❌ Leave requests are available only for Regular employees.';
        } else {
            $leave_type_id = (int)($_POST['leave_type_id'] ?? 0);
            $start_date_input = sanitizeInput($_POST['start_date'] ?? '');
            $end_date_input = sanitizeInput($_POST['end_date'] ?? '');
            $reason = sanitizeInput($_POST['reason'] ?? '');

            $signature_mode = $_POST['signature_mode'] ?? 'canvas';
            $signature_path = null;

            if ($leave_type_id <= 0) {
                $error_message = '❌ Please select a leave type.';
            } elseif (empty($start_date_input)) {
                $error_message = '❌ Please select a start date.';
            } elseif (empty($end_date_input)) {
                $error_message = '❌ Please select an end date.';
            } elseif (empty($reason)) {
                $error_message = '❌ Please provide a reason for your leave.';
            } elseif (strtotime($start_date_input) > strtotime($end_date_input)) {
                $error_message = '❌ End date must be after start date.';
            } elseif (strtotime($start_date_input) < strtotime(date('Y-m-d'))) {
                $error_message = '❌ Cannot apply for leave in the past.';
            } else {
                // Save signature
                if ($signature_mode === 'canvas') {
                    $signature_data = $_POST['signature_data'] ?? '';
                    if (empty($signature_data)) {
                        $error_message = '❌ Please draw your signature before submitting.';
                    } else {
                        $signature_data = str_replace('data:image/png;base64,', '', $signature_data);
                        $signature_data = str_replace(' ', '+', $signature_data);

                        $upload_dir = __DIR__ . '/../uploads/leave_signatures/' . $employee_id;
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }

                        $filename = 'signature_' . $employee_id . '_' . time() . '.png';
                        $signature_path = $upload_dir . '/' . $filename;

                        $decoded = base64_decode($signature_data, true);
                        if ($decoded === false) {
                            $error_message = '❌ Signature could not be processed. Please try again.';
                        } else {
                            file_put_contents($signature_path, $decoded);
                        }
                    }
                } else {
                    $signature_file = $_FILES['signature_file'] ?? null;
                    if (!$signature_file || $signature_file['error'] !== UPLOAD_ERR_OK) {
                        $error_message = '❌ Please upload your signature file or draw a signature.';
                    } else {
                        $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
                        $ext = strtolower(pathinfo($signature_file['name'], PATHINFO_EXTENSION));
                        if (!in_array($ext, $allowed_ext, true)) {
                            $error_message = '❌ Signature must be JPG, PNG, or PDF.';
                        } else {
                            $upload_dir = __DIR__ . '/../uploads/leave_signatures/' . $employee_id;
                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0777, true);
                            }

                            $safe_name = preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($signature_file['name']));
                            $new_name = time() . '_' . $safe_name;
                            $signature_path = $upload_dir . '/' . $new_name;

                            if (!move_uploaded_file($signature_file['tmp_name'], $signature_path)) {
                                $error_message = '❌ Signature upload failed. Please try again.';
                            }
                        }
                    }
                }

                if (empty($error_message)) {
                    $stmt = $conn->prepare(
                        "SELECT COUNT(*) as count FROM leave_requests 
                         WHERE employee_id = ? AND status IN ('pending', 'approved')
                         AND ((start_date <= ? AND end_date >= ?) OR (start_date <= ? AND end_date >= ?))"
                    );
                    if ($stmt) {
                        $stmt->bind_param("issss", $employee_id, $end_date_input, $start_date_input, $end_date_input, $start_date_input);
                        $stmt->execute();
                        $overlap_result = $stmt->get_result();
                        $overlap_count = $overlap_result->fetch_assoc()['count'];
                        $stmt->close();

                        if ($overlap_count > 0) {
                            $error_message = '❌ You already have an overlapping leave request during this period.';
                        } else {
                            $start = new DateTime($start_date_input);
                            $end = new DateTime($end_date_input);
                            $interval = $start->diff($end);
                            $number_of_days = $interval->days + 1;

                            $balance = calculate_leave_balance($conn, $employee_id, $leave_type_id);

                            if ($balance < $number_of_days) {
                                $error_message = '❌ Insufficient leave balance. You have ' . $balance . ' days available.';
                            } else {
                                $stmt = $conn->prepare(
                                    "INSERT INTO leave_requests 
                                    (employee_id, leave_type_id, start_date, end_date, number_of_days, reason, status, signature_path, created_at) 
                                    VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())"
                                );
                                if ($stmt) {
                                    $stmt->bind_param("iississ", $employee_id, $leave_type_id, $start_date_input, $end_date_input, $number_of_days, $reason, $signature_path);
                                    if ($stmt->execute()) {
                                        logSecurityEvent($conn, $_SESSION['user_id'], 'APPLY_LEAVE', "Applied for leave from $start_date_input to $end_date_input");
                                        $success_message = '✅ Leave request submitted successfully! HR will review your request.';
                                        $_POST = [];
                                    } else {
                                        $error_message = '❌ Error submitting leave request. Please try again.';
                                    }
                                    $stmt->close();
                                } else {
                                    $error_message = '❌ Server error. Please try again later.';
                                }
                            }
                        }
                    } else {
                        $error_message = '❌ Error checking overlapping leaves. Please try again.';
                    }
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
    <title>Apply for Leave - HRGetafe</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fb; margin: 0; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 18px 25px; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1000px; margin: 0 auto; padding: 30px 20px; }
        .back-btn { display: inline-block; background: #e5e7eb; color: #1f2937; padding: 10px 16px; text-decoration: none; border-radius: 8px; font-weight: 600; margin-bottom: 20px; }
        .card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .alert { padding: 14px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-error { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
        .alert-success { background: #dcfce7; border: 1px solid #86efac; color: #166534; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 700; color: #374151; }
        input, select, textarea { width: 100%; padding: 11px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; }
        textarea { min-height: 120px; resize: vertical; }
        .info-box { background: #eff6ff; border-left: 4px solid #3b82f6; padding: 14px 16px; border-radius: 8px; color: #1e3a8a; margin-bottom: 20px; }
        .balance-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin: 20px 0; }
        .balance-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; }
        .balance-label { font-size: 12px; color: #64748b; }
        .balance-number { font-size: 28px; font-weight: 700; color: #667eea; margin-top: 8px; }
        .holiday-box { background: #fff7ed; border-left: 4px solid #f59e0b; padding: 14px; border-radius: 8px; margin-bottom: 20px; }
        .button-group { display: flex; gap: 10px; margin-top: 20px; }
        .btn-submit { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 20px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; }
        .btn-reset { background: #e5e7eb; color: #1f2937; padding: 12px 20px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; }
        .note-block { background: #fefce8; border-left: 4px solid #facc15; color: #713f12; padding: 14px; border-radius: 8px; margin-bottom: 18px; }
        .signature-box { border: 2px solid #d1d5db; border-radius: 12px; padding: 12px; background: #f9fafb; margin-top: 20px; }
        canvas { width: 100%; height: 220px; border: 2px solid #cbd5e1; border-radius: 8px; background: white; cursor: crosshair; }
        .signature-options { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .signature-options input[type="radio"] { width: auto; }
        .upload-box { margin-top: 12px; display: none; }
        .show-upload { display: block; }
        .signature-tip { color: #64748b; font-size: 12px; margin-top: 8px; }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .button-group { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Apply for Leave</h2>
        <div>Welcome, <strong><?php echo htmlspecialchars($employee['first_name']); ?></strong></div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (($employee['employment_type'] ?? 'Regular') !== 'Regular'): ?>
            <div class="note-block">
                Leave requests are only available for <strong>Regular employees</strong>.
                Your employment type is: <strong><?php echo htmlspecialchars($employee['employment_type'] ?? 'Regular'); ?></strong>.
            </div>
        <?php else: ?>
            <div class="card">
                <div class="info-box">
                    <strong>Important:</strong> Leave requests are only for Regular employees. Please review your leave balance before submitting.
                </div>

                <h3>Available Leave Balance</h3>
                <div class="balance-grid">
                    <?php foreach ($leave_types as $leave): ?>
                        <?php $balance = calculate_leave_balance($conn, $employee_id, $leave['leave_type_id']); ?>
                        <div class="balance-card">
                            <div class="balance-label"><?php echo htmlspecialchars($leave['leave_type_name']); ?></div>
                            <div class="balance-number"><?php echo $balance; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($holidays)): ?>
                    <div class="holiday-box">
                        <strong>Upcoming Holidays:</strong><br>
                        <?php foreach ($holidays as $holiday): ?>
                            <?php echo htmlspecialchars($holiday['holiday_name']); ?> - <?php echo format_date($holiday['holiday_date']); ?><br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="leaveForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="signature_mode" id="signature_mode" value="canvas">
                    <input type="hidden" name="signature_data" id="signature_data">

                    <div class="form-row">
                        <div>
                            <label for="leave_type_id">Leave Type *</label>
                            <select id="leave_type_id" name="leave_type_id" required>
                                <option value="">-- Select leave type --</option>
                                <?php foreach ($leave_types as $leave): ?>
                                    <?php $balance = calculate_leave_balance($conn, $employee_id, $leave['leave_type_id']); ?>
                                    <option value="<?php echo (int)$leave['leave_type_id']; ?>">
                                        <?php echo htmlspecialchars($leave['leave_type_name']); ?> (<?php echo $balance; ?> available)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div></div>
                    </div>

                    <div class="signature-box">
                        <div class="signature-options">
                            <label><input type="radio" name="signature_choice" value="canvas" checked> Draw Signature</label>
                            <label><input type="radio" name="signature_choice" value="upload"> Upload Signature</label>
                        </div>

                        <div id="canvasSignatureWrap">
                            <canvas id="signatureCanvas" width="700" height="220"></canvas>
                            <div class="signature-tip">Draw your signature using mouse or touch.</div>
                            <div class="button-group" style="margin-top: 12px;">
                                <button type="button" class="btn-reset" id="clearSignatureBtn">Clear</button>
                            </div>
                        </div>

                        <div id="uploadSignatureWrap" class="upload-box">
                            <label for="signature_file">Signature File (Optional if you drew a signature)</label>
                            <input type="file" id="signature_file" name="signature_file" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                    </div>

                    <div class="form-row" style="margin-top: 20px;">
                        <div>
                            <label for="start_date">Start Date *</label>
                            <input type="date" id="start_date" name="start_date" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : ''; ?>">
                        </div>

                        <div>
                            <label for="end_date">End Date *</label>
                            <input type="date" id="end_date" name="end_date" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : ''; ?>">
                        </div>
                    </div>

                    <div style="margin-top: 20px;">
                        <label for="reason">Reason for Leave *</label>
                        <textarea id="reason" name="reason" placeholder="State your reason..." required><?php echo isset($_POST['reason']) ? htmlspecialchars($_POST['reason']) : ''; ?></textarea>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn-submit" id="submitBtn">Submit Leave Request</button>
                        <button type="reset" class="btn-reset">Clear</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const canvas = document.getElementById('signatureCanvas');
        const ctx = canvas.getContext('2d');
        let drawing = false;

        function resizeCanvas() {
            const ratio = window.devicePixelRatio || 1;
            const rect = canvas.getBoundingClientRect();
            canvas.width = rect.width * ratio;
            canvas.height = 220 * ratio;
            ctx.scale(ratio, ratio);
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#111827';
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        }

        resizeCanvas();

        canvas.addEventListener('pointerdown', function(e) {
            drawing = true;
            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            ctx.beginPath();
            ctx.moveTo(x, y);
        });

        canvas.addEventListener('pointermove', function(e) {
            if (!drawing) return;
            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            ctx.lineTo(x, y);
            ctx.stroke();
        });

        canvas.addEventListener('pointerup', function() {
            drawing = false;
        });

        canvas.addEventListener('pointerleave', function() {
            drawing = false;
        });

        document.getElementById('clearSignatureBtn').addEventListener('click', function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        });

        document.querySelectorAll('input[name="signature_choice"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                const mode = this.value;
                document.getElementById('signature_mode').value = mode;

                if (mode === 'canvas') {
                    document.getElementById('canvasSignatureWrap').style.display = 'block';
                    document.getElementById('uploadSignatureWrap').classList.remove('show-upload');
                    document.getElementById('uploadSignatureWrap').style.display = 'none';
                } else {
                    document.getElementById('canvasSignatureWrap').style.display = 'none';
                    document.getElementById('uploadSignatureWrap').style.display = 'block';
                    document.getElementById('uploadSignatureWrap').classList.add('show-upload');
                }
            });
        });

               document.getElementById('leaveForm').addEventListener('submit', function(e) {
            const mode = document.getElementById('signature_mode').value;

            if (mode === 'canvas') {
                const dataUrl = canvas.toDataURL('image/png');
                document.getElementById('signature_data').value = dataUrl;
            }
        });
    </script>
</body>
</html>