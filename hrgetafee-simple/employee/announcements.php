<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

require_login();
check_role(3);

$employee_id = $_SESSION['employee_id'];
$employee = get_employee_info($conn, $employee_id);

// Get sort option
$sort = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'newest';
if (!in_array($sort, ['newest', 'oldest'])) {
    $sort = 'newest';
}

// Get all announcements (prepared)
$order = ($sort === 'newest') ? 'DESC' : 'ASC';
$stmt = $conn->prepare("SELECT * FROM announcements ORDER BY created_at $order");
if (!$stmt) {
    die("Error: " . $conn->error);
}
$stmt->execute();
$result = $stmt->get_result();
$announcements = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get announcement detail if requested
$selected_announcement = null;
if (isset($_GET['id'])) {
    $announcement_id = (int)$_GET['id'];
    foreach ($announcements as $ann) {
        if ($ann['announcement_id'] == $announcement_id) {
            $selected_announcement = $ann;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Verdana, sans-serif; background: #f5f7fa; }

        .navbar { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 15px 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .navbar h2 { margin: 0; font-size: 24px; }
        .navbar-user { display: flex; gap: 15px; align-items: center; }
        .navbar-user span { font-size: 14px; }
        .navbar-user a { color: white; text-decoration: none; font-weight: 600; }

        .container { max-width: 1000px; margin: 0 auto; padding: 30px 20px; }

        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #f0f0f0;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            margin-bottom: 20px;
            transition: 0.3s;
        }
        .back-btn:hover { background: #e0e0e0; }

        .card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .card h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #1f2937;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
            font-size: 20px;
        }

        .sort-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }

        .sort-btn {
            padding: 8px 15px;
            border: 1px solid #ddd;
            background: white;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            font-size: 13px;
            transition: 0.3s;
            cursor: pointer;
        }

        .sort-btn:hover,
        .sort-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .announcement-item {
            background: #f9fafb;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: 0.3s;
        }

        .announcement-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: #667eea;
            transform: translateY(-2px);
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }

        .announcement-title {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
            flex: 1;
        }

        .announcement-date {
            font-size: 12px;
            color: #999;
            white-space: nowrap;
            margin-left: 15px;
        }

        .announcement-preview {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .read-more {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: 0.3s;
        }

        .read-more:hover {
            color: #764ba2;
        }

        /* Detail View */
        .announcement-detail {
            background: white;
            border: 2px solid #667eea;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 25px;
        }

        .detail-header {
            border-bottom: 2px solid #e1e1e1;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .detail-title {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
            margin: 0 0 10px 0;
        }

        .detail-meta {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: #666;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .detail-content {
            font-size: 15px;
            line-height: 1.8;
            color: #333;
            margin-bottom: 20px;
            word-wrap: break-word;
            white-space: pre-wrap;
        }

        .back-to-list {
            display: inline-block;
            padding: 10px 20px;
            background: #f0f0f0;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            transition: 0.3s;
        }

        .back-to-list:hover {
            background: #e0e0e0;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-state p {
            margin: 10px 0;
        }

        .count-badge {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
        }

        @media (max-width: 768px) {
            .container { padding: 20px 15px; }
            .navbar h2 { font-size: 18px; }
            .announcement-header { flex-direction: column; }
            .announcement-date { margin-left: 0; margin-top: 10px; }
            .detail-meta { flex-direction: column; gap: 10px; }
            .announcement-detail { padding: 20px; }
            .detail-title { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>📢 Announcements</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($employee['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>

        <?php if ($selected_announcement): ?>
            <!-- Detail View -->
            <div class="announcement-detail">
                <div class="detail-header">
                    <h2 class="detail-title"><?php echo htmlspecialchars($selected_announcement['title']); ?></h2>
                    <div class="detail-meta">
                        <div class="meta-item">
                            📅 <?php echo format_datetime($selected_announcement['created_at']); ?>
                        </div>
                    </div>
                </div>

                <div class="detail-content">
                    <?php echo htmlspecialchars($selected_announcement['message']); ?>
                </div>

                <a href="announcements.php" class="back-to-list">← Back to All Announcements</a>
            </div>
        <?php else: ?>
            <!-- List View -->
            <div class="card">
                <h3>
                    📢 All Announcements
                    <span class="count-badge"><?php echo count($announcements); ?></span>
                </h3>

                <!-- Sort Controls -->
                <div class="sort-controls">
                    <span style="color: #666; font-weight: 600; font-size: 13px;">Sort by:</span>
                    <a href="?sort=newest" class="sort-btn <?php echo $sort === 'newest' ? 'active' : ''; ?>">
                        📅 Newest First
                    </a>
                    <a href="?sort=oldest" class="sort-btn <?php echo $sort === 'oldest' ? 'active' : ''; ?>">
                        📅 Oldest First
                    </a>
                </div>

                <!-- Announcements List -->
                <?php if (!empty($announcements)): ?>
                    <?php foreach ($announcements as $announcement): ?>
                    <a href="?id=<?php echo $announcement['announcement_id']; ?>" style="text-decoration: none; color: inherit;">
                        <div class="announcement-item">
                            <div class="announcement-header">
                                <h4 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                <div class="announcement-date">
                                    <?php echo format_datetime($announcement['created_at']); ?>
                                </div>
                            </div>
                            <p class="announcement-preview">
                                <?php echo htmlspecialchars(substr($announcement['message'], 0, 150)); ?>
                            </p>
                            <a href="?id=<?php echo $announcement['announcement_id']; ?>" class="read-more">Read More →</a>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="empty-state">
                    <p>📭 No announcements at this time</p>
                    <p style="font-size: 13px;">Check back later for updates from the HR Department.</p>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
