<?php
/**
 * Authentication Functions
 * Engineering Utility Monitoring System (EUMS)
 */

// Load database configuration and functions
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Authenticate user with username and password
 */
function authenticateUser($username, $password, $remember = false) {
    try {
        $db = getDB(); // ใช้ฟังก์ชัน getDB() ที่ปรับปรุงแล้ว
        
        // Get user by username
        $stmt = $db->prepare("
            SELECT id, username, password, fullname, email, role, status, 
                   login_attempts, last_login 
            FROM users 
            WHERE username = ? AND status = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
        }
        
        // Check if account is locked
        if ($user['login_attempts'] >= 5) {
            $lockoutTime = strtotime($user['last_login']) + (15 * 60); // 15 minutes lockout
            if (time() < $lockoutTime) {
                $remaining = ceil(($lockoutTime - time()) / 60);
                return ['success' => false, 'message' => "บัญชีถูกล็อค กรุณารอ $remaining นาที"];
            } else {
                // Reset attempts after lockout period
                resetLoginAttempts($user['id']);
            }
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            // Increment failed attempts
            incrementLoginAttempts($user['id']);
            return ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
        }
        
        // Check if password needs rehash
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            updatePasswordHash($user['id'], $newHash);
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        // Update last login and reset attempts
        updateLastLogin($user['id']);
        resetLoginAttempts($user['id']);
        
        // Set remember me cookie if requested
        if ($remember) {
            setRememberMeToken($user['id']);
        }
        
        // Get user permissions
        $permissions = getUserPermissions($user['id'], $user['role']);
        $_SESSION['user_permissions'] = $permissions;
        
        // Log successful login
        logActivity($user['id'], 'login', 'User logged in successfully');
        
        return [
            'success' => true,
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'fullname' => $user['fullname'],
                'role' => $user['role']
            ]
        ];
        
    } catch (PDOException $e) {
        error_log("Authentication error: " . $e->getMessage());
        return ['success' => false, 'message' => 'ระบบขัดข้อง กรุณาลองใหม่อีกครั้ง'];
    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

/**
 * Login with remember me token
 */
function loginWithToken($token) {
    try {
        $db = getDB();
        
        // Get token from database
        $stmt = $db->prepare("
            SELECT user_id, expires_at 
            FROM user_tokens 
            WHERE token = ? AND type = 'remember'
        ");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch();
        
        if (!$tokenData) {
            return ['success' => false, 'message' => 'Token ไม่ถูกต้อง'];
        }
        
        // Check if token expired
        if (strtotime($tokenData['expires_at']) < time()) {
            // Delete expired token
            deleteToken($token);
            return ['success' => false, 'message' => 'Token หมดอายุ'];
        }
        
        // Get user data
        $stmt = $db->prepare("
            SELECT id, username, fullname, email, role 
            FROM users 
            WHERE id = ? AND status = 1
        ");
        $stmt->execute([$tokenData['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'ไม่พบผู้ใช้'];
        }
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        // Update last login
        updateLastLogin($user['id']);
        
        // Get permissions
        $_SESSION['user_permissions'] = getUserPermissions($user['id'], $user['role']);
        
        // Refresh token
        refreshRememberMeToken($user['id'], $token);
        
        return ['success' => true];
        
    } catch (PDOException $e) {
        error_log("Token login error: " . $e->getMessage());
        return ['success' => false, 'message' => 'ระบบขัดข้อง'];
    }
}

/**
 * Set remember me token
 */
function setRememberMeToken($userId) {
    try {
        $db = getDB();
        
        // Generate token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Delete old tokens
        $stmt = $db->prepare("DELETE FROM user_tokens WHERE user_id = ? AND type = 'remember'");
        $stmt->execute([$userId]);
        
        // Insert new token
        $stmt = $db->prepare("
            INSERT INTO user_tokens (user_id, token, type, expires_at, created_at)
            VALUES (?, ?, 'remember', ?, NOW())
        ");
        $stmt->execute([$userId, $token, $expires]);
        
        // Set cookie
        setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true);
        
    } catch (PDOException $e) {
        error_log("Set remember token error: " . $e->getMessage());
    }
}

/**
 * Refresh remember me token
 */
function refreshRememberMeToken($userId, $oldToken) {
    try {
        $db = getDB();
        
        // Generate new token
        $newToken = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Update token
        $stmt = $db->prepare("
            UPDATE user_tokens 
            SET token = ?, expires_at = ?, updated_at = NOW()
            WHERE user_id = ? AND token = ? AND type = 'remember'
        ");
        $stmt->execute([$newToken, $expires, $userId, $oldToken]);
        
        // Update cookie
        setcookie('remember_token', $newToken, time() + (86400 * 30), '/', '', false, true);
        
    } catch (PDOException $e) {
        error_log("Refresh token error: " . $e->getMessage());
    }
}

/**
 * Delete token
 */
function deleteToken($token) {
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM user_tokens WHERE token = ?");
        $stmt->execute([$token]);
    } catch (PDOException $e) {
        error_log("Delete token error: " . $e->getMessage());
    }
}

/**
 * Increment login attempts
 */
function incrementLoginAttempts($userId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE users 
            SET login_attempts = login_attempts + 1, last_login = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Increment attempts error: " . $e->getMessage());
    }
}

/**
 * Reset login attempts
 */
function resetLoginAttempts($userId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE users SET login_attempts = 0 WHERE id = ?");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Reset attempts error: " . $e->getMessage());
    }
}

/**
 * Update last login
 */
function updateLastLogin($userId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Update last login error: " . $e->getMessage());
    }
}

/**
 * Update password hash
 */
function updatePasswordHash($userId, $newHash) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$newHash, $userId]);
    } catch (PDOException $e) {
        error_log("Update password hash error: " . $e->getMessage());
    }
}

/**
 * Get user permissions
 */
function getUserPermissions($userId, $role) {
    // Admin has all permissions
    if ($role === 'admin') {
        return ['*'];
    }
    
    try {
        $db = getDB();
        
        // Get role-based permissions
        $stmt = $db->prepare("
            SELECT p.permission_key 
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN roles r ON rp.role_id = r.id
            WHERE r.role_name = ?
        ");
        $stmt->execute([$role]);
        $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get user-specific permissions
        $stmt = $db->prepare("
            SELECT p.permission_key 
            FROM permissions p
            JOIN user_permissions up ON p.id = up.permission_id
            WHERE up.user_id = ? AND up.granted = 1
        ");
        $stmt->execute([$userId]);
        $userPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return array_unique(array_merge($permissions, $userPermissions));
        
    } catch (PDOException $e) {
        error_log("Get permissions error: " . $e->getMessage());
        return [];
    }
}

/**
 * Log failed login attempt
 */
function logFailedAttempt($username, $ip) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO login_attempts (username, ip_address, attempt_time)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$username, $ip]);
    } catch (PDOException $e) {
        error_log("Log failed attempt error: " . $e->getMessage());
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Require authentication
 */
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Require specific permission
 */
function requirePermission($permission) {
    requireAuth();
    
    if (!in_array('*', $_SESSION['user_permissions']) && 
        !in_array($permission, $_SESSION['user_permissions'])) {
        http_response_code(403);
        die('คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    }
}

/**
 * Logout user
 */
function logoutUser() {
    // Clear remember me token if exists
    if (isset($_COOKIE['remember_token'])) {
        deleteToken($_COOKIE['remember_token']);
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    // Log activity
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'logout', 'User logged out');
    }
    
    // Clear session
    $_SESSION = array();
    
    // Destroy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
}

?>