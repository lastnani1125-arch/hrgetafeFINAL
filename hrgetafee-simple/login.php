<?php
// Professional Login Page with Footer
// Place at: hrgetafee-simple/login.php
// UPDATED: Removed staff role (role_id 2) — Now only Admin (1) and Employee (3)

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/database.php';

// Prevent caching of login page
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (isset($_SESSION['user_id'])) {
    $role_id = $_SESSION['role_id'];
    $redirect_map = [
        1 => 'admin/dashboard.php',
        3 => 'employee/dashboard.php'
    ];
    header("Location: " . ($redirect_map[$role_id] ?? 'login.php'));
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = get_client_ip();
    $rl_key = 'login:' . $ip;
    if (!checkRateLimit($rl_key, 5, 300)) {
        $error_message = 'Too many attempts. Please wait and try again later.';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $csrf = $_POST['csrf_token'] ?? '';

        if (empty($username) || empty($password)) {
            $error_message = 'Please enter username and password';
        } elseif (!verifyCSRFToken($csrf)) {
            $error_message = 'Invalid or expired form token. Please reload the page and try again.';
        } else {
            $stmt = $conn->prepare("SELECT user_id, username, password, role_id, employee_id, status FROM users WHERE username = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if ($user['status'] !== 'active') {
                        $error_message = 'Account inactive';
                    } elseif (verifyPassword($password, $user['password'])) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role_id'] = $user['role_id'];
                        $_SESSION['employee_id'] = $user['employee_id'];

                        $update = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                        if ($update) {
                            $update->bind_param("i", $user['user_id']);
                            $update->execute();
                            $update->close();
                        }

                        $redirect_map = [
                            1 => 'admin/dashboard.php',
                            3 => 'employee/dashboard.php'
                        ];
                        header("Location: " . ($redirect_map[$user['role_id']] ?? 'index.php'));
                        exit;
                    } else {
                        $error_message = 'Invalid username or password';
                    }
                } else {
                    $error_message = 'Invalid username or password';
                }
                $stmt->close();
            } else {
                $error_message = 'Server error. Please try again later.';
            }
        }
    }
    $conn->close();
}

// ===== CONFIGURABLE BACKGROUND IMAGE PATH =====
// Change this path to your desired background image
// Supported formats: jpg, png, jpeg, gif
// Example: 'assets/images/getafe-bg.jpg'
$bg_image = 'assets/images/login-bg.jpg';

// Cache-busting: Add file modification time as query parameter
// This ensures new images display immediately after replacement
$bg_url = $bg_image;
if (file_exists($bg_image)) {
    $filemtime = filemtime($bg_image);
    $bg_url = $bg_image . '?v=' . $filemtime;
} else {
    $bg_url = '';
}

// Current year for footer
$current_year = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRGetafe - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            width: 100%;
            overflow-x: hidden;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Verdana, sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        /* Background Image Container - Fills entire viewport without gaps */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('<?php echo $bg_url; ?>');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            z-index: -2;
            width: 100%;
            height: 100%;
        }

        /* Dark overlay for better form visibility */
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.35);
            z-index: -1;
        }

        /* Main Content Wrapper */
        .login-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        /* Login Container */
        .login-container {
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            padding: 40px 35px;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Header Section */
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-icon {
            font-size: 40px;
            color: #667eea;
            margin-bottom: 12px;
            display: inline-block;
        }

        .login-header h1 {
            font-size: 26px;
            color: #2c3e50;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .login-header .subtitle {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 500;
            line-height: 1.4;
            letter-spacing: 0.3px;
        }

        .divider {
            height: 2px;
            background: linear-gradient(90deg, transparent, #667eea, transparent);
            margin: 20px 0;
        }

        /* Form Section */
        .login-form {
            display: flex;
            flex-direction: column;
        }

        .form-group {
            margin-bottom: 18px;
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            color: #bdc3c7;
            font-size: 15px;
            z-index: 1;
        }

        .login-form input {
            width: 100%;
            padding: 11px 15px 11px 42px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 13px;
            transition: all 0.3s ease;
            color: #2c3e50;
            background: #f8f9fa;
        }

        .login-form input:focus {
            outline: none;
            border-color: #667eea;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .login-form input::placeholder {
            color: #bdc3c7;
        }

        /* Error Message */
        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
            border-left: 4px solid #c33;
            font-size: 12px;
            animation: slideDown 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-message i {
            flex-shrink: 0;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 11px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 8px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(102, 126, 234, 0.4);
        }

        .submit-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
        }

        /* Footer */
        footer {
            background: rgba(0, 0, 0, 0.5);
            color: #ecf0f1;
            padding: 20px 20px;
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 12px;
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 1;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .footer-text {
            font-size: 11px;
            color: #bdc3c7;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .footer-links a {
            color: #ecf0f1;
            text-decoration: none;
            transition: color 0.3s ease;
            font-size: 11px;
        }

        .footer-links a:hover {
            color: #667eea;
        }

        .footer-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 8px 0;
        }

        .footer-copyright {
            font-size: 11px;
            color: #95a5a6;
        }

        /* Responsive Design */
        @media (max-width: 600px) {
            .login-container {
                padding: 35px 22px;
                max-width: 100%;
                margin: 0 15px;
            }

            .login-header h1 {
                font-size: 22px;
            }

            .login-header .subtitle {
                font-size: 11px;
            }

            .footer-links {
                gap: 15px;
            }

            footer {
                padding: 18px 15px;
            }
        }

        /* Accessibility */
        input:focus-visible {
            outline: 2px solid #667eea;
            outline-offset: 0;
        }

        button:focus-visible {
            outline: 2px solid #667eea;
            outline-offset: 0;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <!-- Header Section -->
            <div class="login-header">
                <div class="logo-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
                <h1>HRGetafe</h1>
                <p class="subtitle">Human Resources Information System<br>for LGU Getafe</p>
            </div>

            <div class="divider"></div>

            <!-- Error Message -->
            <?php if ($error_message): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo escapeOutput($error_message); ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="" class="login-form">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i> Username
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" placeholder="Enter your username" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
        </div>
    </div>

    <!-- Footer Section -->
    <footer>
        <div class="footer-content">
            <div class="footer-links">
                <a href="#" title="Privacy Policy">Privacy Policy</a>
                <a href="#" title="Terms of Service">Terms of Service</a>
                <a href="#" title="Contact Support">Contact Support</a>
            </div>
            <div class="footer-divider"></div>
            <div class="footer-text">
                🏛️ Local Government Unit of Getafe - HR Management Portal
            </div>
            <div class="footer-copyright">
                &copy; <?php echo $current_year; ?> HRGetafe. All rights reserved. | Secure System
            </div>
        </div>
    </footer>
</body>
</html>