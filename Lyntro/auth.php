<?php
// Nigerian Online Marketplace - Authentication System
// Secure user authentication with brute force protection

require_once 'config.php';

// User Registration
function registerUser($username, $email, $password, $fullName, $phone, $location, $userType) {
    $pdo = getDBConnection();
    
    // Validate inputs
    $username = sanitizeInput($username);
    $email = sanitizeInput($email);
    $fullName = sanitizeInput($fullName);
    $phone = sanitizeInput($phone);
    $location = sanitizeInput($location);
    $userType = in_array($userType, ['buyer', 'seller', 'both']) ? $userType : 'both';
    
    // Check password strength
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long'];
    }
    
    if (!validateEmail($email)) {
        return ['success' => false, 'message' => 'Invalid email address'];
    }
    
    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Username or email already exists'];
    }
    
    // Hash password
    $passwordHash = hashPassword($password);
    
    // Insert new user
    try {
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, full_name, phone, location, user_type) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$username, $email, $passwordHash, $fullName, $phone, $location, $userType]);
        
        $userId = $pdo->lastInsertId();
        
        // Log the user in
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['user_type'] = $userType;
        $_SESSION['login_time'] = time();
        
        return ['success' => true, 'message' => 'Registration successful', 'user_id' => $userId];
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }
}

// User Login with brute force protection
function loginUser($email, $password) {
    $pdo = getDBConnection();
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Sanitize inputs
    $email = sanitizeInput($email);
    
    // Check rate limiting
    if (!checkRateLimit($email, MAX_LOGIN_ATTEMPTS, LOGIN_ATTEMPT_WINDOW)) {
        return ['success' => false, 'message' => 'Too many login attempts. Please try again later.'];
    }
    
    // Get user by email
    $stmt = $pdo->prepare("
        SELECT id, username, email, password_hash, full_name, user_type, 
               login_attempts, account_locked, locked_until 
        FROM users 
        WHERE email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        logLoginAttempt($email, $ipAddress, false);
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    // Check if account is locked
    if (isAccountLocked($user['id'])) {
        return ['success' => false, 'message' => 'Account is temporarily locked. Please try again later.'];
    }
    
    // Verify password
    if (verifyPassword($password, $user['password_hash'])) {
        // Reset login attempts
        $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Log successful attempt
        logLoginAttempt($email, $ipAddress, true);
        
        // Set session
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['login_time'] = time();
        
        return ['success' => true, 'message' => 'Login successful', 'user' => $user];
    } else {
        // Increment login attempts
        $loginAttempts = $user['login_attempts'] + 1;
        $stmt = $pdo->prepare("UPDATE users SET login_attempts = ? WHERE id = ?");
        $stmt->execute([$loginAttempts, $user['id']]);
        
        // Log failed attempt
        logLoginAttempt($email, $ipAddress, false);
        
        // Lock account if max attempts reached
        if ($loginAttempts >= MAX_LOGIN_ATTEMPTS) {
            lockAccount($user['id']);
            return ['success' => false, 'message' => 'Account locked due to too many failed attempts. Please try again later.'];
        }
        
        $remainingAttempts = MAX_LOGIN_ATTEMPTS - $loginAttempts;
        return ['success' => false, 'message' => "Invalid email or password. $remainingAttempts attempts remaining."];
    }
}

// User Logout
function logoutUser() {
    // Unset all session variables
    $_SESSION = [];
    
    // Delete session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy session
    session_destroy();
    
    return ['success' => true, 'message' => 'Logout successful'];
}

// Update user profile
function updateProfile($userId, $fullName, $phone, $location, $profileImage = null) {
    $pdo = getDBConnection();
    
    // Validate inputs
    $fullName = sanitizeInput($fullName);
    $phone = sanitizeInput($phone);
    $location = sanitizeInput($location);
    
    try {
        if ($profileImage) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = ?, phone = ?, location = ?, profile_image = ? 
                WHERE id = ?
            ");
            $stmt->execute([$fullName, $phone, $location, $profileImage, $userId]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = ?, phone = ?, location = ? 
                WHERE id = ?
            ");
            $stmt->execute([$fullName, $phone, $location, $userId]);
        }
        
        return ['success' => true, 'message' => 'Profile updated successfully'];
    } catch (PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update profile'];
    }
}

// Change password
function changePassword($userId, $currentPassword, $newPassword) {
    $pdo = getDBConnection();
    
    // Validate password strength
    if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
        return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long'];
    }
    
    // Get current password hash
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    // Verify current password
    if (!verifyPassword($currentPassword, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Current password is incorrect'];
    }
    
    // Hash new password
    $newPasswordHash = hashPassword($newPassword);
    
    // Update password
    try {
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newPasswordHash, $userId]);
        
        return ['success' => true, 'message' => 'Password changed successfully'];
    } catch (PDOException $e) {
        error_log("Password change error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to change password'];
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle GET requests for actions like logout
    $action = $_GET['action'] ?? '';
    
    if ($action === 'logout') {
        $result = logoutUser();
        redirect('index.html');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Verify CSRF token for POST requests
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    switch ($action) {
        case 'register':
            $result = registerUser(
                $_POST['username'],
                $_POST['email'],
                $_POST['password'],
                $_POST['full_name'] ?? '',
                $_POST['phone'] ?? '',
                $_POST['location'] ?? '',
                $_POST['user_type'] ?? 'both'
            );
            jsonResponse($result);
            break;
            
        case 'login':
            $result = loginUser($_POST['email'], $_POST['password']);
            jsonResponse($result);
            break;
            
        case 'logout':
            $result = logoutUser();
            jsonResponse($result);
            break;
            
        case 'update_profile':
            if (!isLoggedIn()) {
                jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
            }
            $result = updateProfile(
                $_SESSION['user_id'],
                $_POST['full_name'],
                $_POST['phone'],
                $_POST['location']
            );
            jsonResponse($result);
            break;
            
        case 'change_password':
            if (!isLoggedIn()) {
                jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
            }
            $result = changePassword(
                $_SESSION['user_id'],
                $_POST['current_password'],
                $_POST['new_password']
            );
            jsonResponse($result);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
}
?>