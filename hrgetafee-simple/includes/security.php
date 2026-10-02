<?php
/**
 * Security Functions - Input Validation, Sanitization, CSRF Protection, Session Hardening
 *
 * Place at: hrgetafee-simple/includes/security.php
 */

if (session_status() === PHP_SESSION_NONE) {
    // Set secure session cookie params when possible
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Define SECRET_KEY (change to a strong value in production)
if (!defined('SECRET_KEY')) {
    define('SECRET_KEY', 'change-this-to-a-strong-random-value');
}

// Session hardening: fingerprint and rotation
function hardenSession() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $fingerprint = hash_hmac('sha256', $userAgent . '|' . $ip, SECRET_KEY);

    if (empty($_SESSION['fingerprint'])) {
        $_SESSION['fingerprint'] = $fingerprint;
        session_regenerate_id(true);
    } elseif (!hash_equals($_SESSION['fingerprint'], $fingerprint)) {
        session_unset();
        session_destroy();
        session_start();
    }
}
hardenSession();

// CSRF token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token, $ttl = 3600) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    $valid = hash_equals($_SESSION['csrf_token'], $token);
    if ($valid) {
        if (!empty($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time'] > $ttl)) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
    }
    return $valid;
}

// Input / output helpers
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

function escapeOutput($output) {
    return htmlspecialchars((string)$output, ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username) === 1;
}

function validatePassword($password) {
    if (strlen($password) < 8) return false;
    return preg_match('/(?=.*[A-Za-z])(?=.*[0-9])/', $password) === 1;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Role & Login enforcement
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        $redirect = defined('BASE_URL') ? BASE_URL . 'login.php' : 'login.php';
        header('Location: ' . $redirect);
        exit;
    }
}

function requireRole($required_roles) {
    requireLogin();
    $roles = is_array($required_roles) ? $required_roles : [$required_roles];
    if (!in_array($_SESSION['role_id'], $roles)) {
        http_response_code(403);
        die('Access Denied: You do not have permission to access this page.');
    }
}

// Logging
function logSecurityEvent($connection, $user_id, $action, $description = '') {
    try {
        $stmt = $connection->prepare(
            "INSERT INTO security_logs (user_id, action, description, ip_address, timestamp) 
             VALUES (?, ?, ?, ?, NOW())"
        );
        if ($stmt) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $stmt->bind_param('isss', $user_id, $action, $description, $ip);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        // Fail silently if logging not available
    }
}

// Simple rate limiter (session-backed)
function checkRateLimit($key, $limit = 5, $window = 300) {
    $cache_key = 'rate_limit:' . $key;
    if (!isset($_SESSION[$cache_key])) {
        $_SESSION[$cache_key] = ['count' => 0, 'reset_time' => time() + $window];
    }
    if (time() > $_SESSION[$cache_key]['reset_time']) {
        $_SESSION[$cache_key] = ['count' => 0, 'reset_time' => time() + $window];
    }
    $_SESSION[$cache_key]['count']++;
    return $_SESSION[$cache_key]['count'] <= $limit;
}

function get_client_ip() {
    $keys = ['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'];
    foreach ($keys as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = $_SERVER[$k];
            if (strpos($ip, ',') !== false) $ip = explode(',', $ip)[0];
            return trim($ip);
        }
    }
    return '0.0.0.0';
}
?>