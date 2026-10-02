<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role_id'] != 1) {
    die('Access denied. Admin only.');
}

if (!isset($_GET['leave_id'])) {
    die('Invalid request.');
}

$leave_id = intval($_GET['leave_id']);

$stmt = $conn->prepare("SELECT signature_path FROM leave_requests WHERE leave_id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('i', $leave_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $leave = $result->fetch_assoc();
    $stmt->close();

    if ($leave && !empty($leave['signature_path'])) {
        $file_path = $leave['signature_path'];
        
        // Check if file exists
        if (file_exists($file_path)) {
            $mime_type = mime_content_type($file_path);
            if ($mime_type === false) {
                $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                $mime_types = [
                    'pdf' => 'application/pdf',
                    'png' => 'image/png',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg'
                ];
                $mime_type = $mime_types[$ext] ?? 'application/octet-stream';
            }
            
            header('Content-Type: ' . $mime_type);
            header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
            header('Content-Length: ' . filesize($file_path));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            
            readfile($file_path);
            exit;
        } else {
            die('Signature file not found on server: ' . htmlspecialchars($file_path));
        }
    } else {
        die('No signature found for this leave request.');
    }
} else {
    die('Database error: ' . htmlspecialchars($conn->error));
}
?>