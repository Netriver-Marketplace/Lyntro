<?php
// Nigerian Online Marketplace - Configuration File
// Security-focused configuration

// Error Reporting (Disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Security Headers
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Content-Security-Policy: default-src "self"; script-src "self" "unsafe-inline" "unsafe-eval"; style-src "self" "unsafe-inline"; img-src "self" data: https:; font-src "self" data:; connect-src "self"');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'nigerian_marketplace');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Security Configuration
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_ATTEMPT_WINDOW', 900); // 15 minutes in seconds
define('ACCOUNT_LOCKOUT_TIME', 1800); // 30 minutes in seconds
define('PASSWORD_MIN_LENGTH', 8);
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('CSRF_TOKEN_EXPIRY', 3600); // 1 hour

// File Upload Configuration
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('UPLOAD_PATH', __DIR__ . '/uploads/');

// Site Configuration
define('SITE_NAME', 'Lyntro');
define('SITE_URL', 'http://localhost');
define('SUPPORT_EMAIL', 'support@lyntro.ng');

// Pagination
define('PRODUCTS_PER_PAGE', 12);
define('MESSAGES_PER_PAGE', 20);

// Rate Limiting
define('API_RATE_LIMIT', 100); // requests per hour
define('SEARCH_RATE_LIMIT', 30); // searches per minute

// Create a PDO database connection with security settings
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // Prevent SQL injection
            PDO::ATTR_PERSISTENT => false
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection failed. Please try again later.");
    }
}

// CSRF Token Management
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token']) || 
        empty($_SESSION['csrf_token_time']) || 
        time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_EXPIRY) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_EXPIRY) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Sanitize input data
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Password hashing using bcrypt
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Rate limiting for brute force protection
function checkRateLimit($identifier, $maxAttempts, $timeWindow) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempts 
        FROM login_attempts 
        WHERE email = ? 
        AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->execute([$identifier, $timeWindow]);
    $result = $stmt->fetch();
    
    return $result['attempts'] < $maxAttempts;
}

function logLoginAttempt($email, $ipAddress, $success) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        INSERT INTO login_attempts (email, ip_address, success) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$email, $ipAddress, $success]);
}

// Check if user account is locked
function isAccountLocked($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT account_locked, locked_until 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return false;
    }
    
    if ($user['account_locked']) {
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return true;
        } else {
            // Unlock if lock time has passed
            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET account_locked = FALSE, locked_until = NULL, login_attempts = 0 
                WHERE id = ?
            ");
            $updateStmt->execute([$userId]);
            return false;
        }
    }
    
    return false;
}

// Lock user account
function lockAccount($userId) {
    $pdo = getDBConnection();
    $lockUntil = date('Y-m-d H:i:s', time() + ACCOUNT_LOCKOUT_TIME);
    $stmt = $pdo->prepare("
        UPDATE users 
        SET account_locked = TRUE, locked_until = ?, login_attempts = 0 
        WHERE id = ?
    ");
    $stmt->execute([$lockUntil, $userId]);
}

// Start secure session
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > SESSION_TIMEOUT) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// Check if user is logged in
function isLoggedIn() {
    startSecureSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get current user
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT id, username, email, full_name, phone, location, user_type, 
               profile_image, rating, total_reviews, is_verified 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Redirect helper
function redirect($url) {
    header("Location: $url");
    exit();
}

// JSON response helper
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Create directories if they don't exist
$directories = [UPLOAD_PATH, __DIR__ . '/logs', __DIR__ . '/uploads/products', __DIR__ . '/uploads/profiles'];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Start session
startSecureSession();
?>