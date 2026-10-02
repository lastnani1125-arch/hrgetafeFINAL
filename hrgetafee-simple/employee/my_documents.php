<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

require_login();
check_role(3);

$employee = get_employee_info($conn, $_SESSION['employee_id']);
$employee_id = $_SESSION['employee_id'];

// Handle download
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

// Get documents
$documents = [];
$stmt = $conn->prepare("SELECT * FROM employee_documents WHERE employee_id = ? AND is_active = 1 ORDER BY uploaded_at DESC");
if ($stmt) {
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Documents - HRGetafe</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            margin: 0;
            padding: 0;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 18px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .doc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
            margin-top: 20px;
        }
        .doc-item {
            background: #f8fafc;
            border: 1px solid #dfe7f1;
            border-radius: 10px;
            padding: 16px;
        }
        .doc-label {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            font-size: 11px;
            font-weight: 700;
            padding: 6px 8px;
            border-radius: 6px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .doc-name {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1f2937;
        }
        .doc-meta {
            font-size: 12px;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        .btn {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 9px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
        }
        .btn-secondary {
            background: #e5e7eb;
            color: #1f2937;
            margin-left: 8px;
        }
        .empty {
            color: #64748b;
            padding: 25px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>My Documents</h2>
        <div>
            Welcome, <strong><?php echo htmlspecialchars($employee['first_name']); ?></strong>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>

        <div class="card" style="margin-top: 20px;">
            <h3>Official Employee Files</h3>

            <?php if (empty($documents)): ?>
                <div class="empty">
                    No documents uploaded yet. HR will upload official files here.
                </div>
            <?php else: ?>
                <div class="doc-grid">
                    <?php foreach ($documents as $doc): ?>
                        <div class="doc-item">
                            <div class="doc-label">
                                <?php
                                $titleMap = [
                                    'credentials' => 'Credentials',
                                    '201_file' => '201 File',
                                    'contract' => 'Contract',
                                    'cos' => 'COS',
                                    'diploma' => 'Diploma / TOR',
                                    'other' => 'Other'
                                ];
                                echo $titleMap[$doc['document_type']] ?? ucfirst(str_replace('_', ' ', $doc['document_type']));
                                ?>
                            </div>
                            <div class="doc-name">
                                <?php echo htmlspecialchars($doc['document_name']); ?>
                            </div>
                            <div class="doc-meta">
                                Size: <?php echo number_format((float)($doc['file_size'] / 1024), 2); ?> KB<br>
                                Uploaded: <?php echo htmlspecialchars(date('M d, Y', strtotime($doc['uploaded_at']))); ?>
                            </div>
                            <a href="my_documents.php?download_document=<?php echo (int)$doc['document_id']; ?>" class="btn">Download</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>