<?php
/**
 * CORNERSTONE INVENTORY TRACKER - Authentication & Authorization System
 * 
 * This file handles user authentication, session management, and role-based
 * access control (RBAC). It ensures secure login/logout and permission checking.
 */

// Include required files
if (!defined('CORNERSTONE_APP')) {
    require_once __DIR__ . '/config.php';
}
require_once __DIR__ . '/db.php';

class Auth {
    
    /**
     * Authenticate user with username/email and password
     * 
     * @param string $username Username or email
     * @param string $password Plain text password
     * @return array|false User data array on success, false on failure
     */
    public static function login($username, $password) {
        // Find user by username or email
        $sql = "SELECT id, username, email, password_hash, role, full_name 
                FROM users 
                WHERE username = ? OR email = ? 
                LIMIT 1";
        
        $user = Database::fetchOne($sql, [$username, $username], 'ss');
        
        if (!$user) {
            return false;
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        return $user;
    }
    
    /**
     * Log out the current user
     * 
     * @return void
     */
    public static function logout() {
        // Unset all session variables
        $_SESSION = [];
        
        // Destroy the session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Destroy the session
        session_destroy();
    }
    
    /**
     * Check if user is logged in
     * 
     * @return bool True if logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Get current user ID
     * 
     * @return int|null User ID or null if not logged in
     */
    public static function getUserId() {
        return self::isLoggedIn() ? $_SESSION['user_id'] : null;
    }
    
    /**
     * Get current username
     * 
     * @return string|null Username or null if not logged in
     */
    public static function getUsername() {
        return self::isLoggedIn() ? $_SESSION['username'] : null;
    }
    
    /**
     * Get current user's full name
     * 
     * @return string|null Full name or null if not logged in
     */
    public static function getFullName() {
        return self::isLoggedIn() ? $_SESSION['full_name'] : null;
    }
    
    /**
     * Get current user's role
     * 
     * @return string|null Role (admin/end_user) or null if not logged in
     */
    public static function getRole() {
        return self::isLoggedIn() ? $_SESSION['role'] : null;
    }
    
    /**
     * Check if current user has a specific role
     * 
     * @param string $role The role to check (admin/end_user)
     * @return bool True if user has the role
     */
    public static function hasRole($role) {
        return self::isLoggedIn() && $_SESSION['role'] === $role;
    }
    
    /**
     * Check if current user is an admin
     * 
     * @return bool True if user is admin
     */
    public static function isAdmin() {
        return self::hasRole('admin');
    }
    
    /**
     * Check if current user is an end user
     * 
     * @return bool True if user is end_user
     */
    public static function isEndUser() {
        return self::hasRole('end_user');
    }
    
    /**
     * Require user to be logged in (redirect to login if not)
     * 
     * @param string $redirectUrl URL to redirect to if not logged in
     * @return void
     */
    public static function requireLogin($redirectUrl = 'login.php') {
        if (!self::isLoggedIn()) {
            set_flash('error', 'Please log in to access this page.');
            redirect($redirectUrl);
        }
    }
    
    /**
     * Require user to have a specific role (redirect if not)
     * 
     * @param string $role Required role
     * @param string $redirectUrl URL to redirect to if unauthorized
     * @return void
     */
    public static function requireRole($role, $redirectUrl = 'dashboard.php') {
        self::requireLogin();
        
        if (!self::hasRole($role)) {
            set_flash('error', 'You do not have permission to access this page.');
            redirect($redirectUrl);
        }
    }
    
    /**
     * Require user to be an admin
     * 
     * @param string $redirectUrl URL to redirect to if not admin
     * @return void
     */
    public static function requireAdmin($redirectUrl = 'dashboard.php') {
        self::requireRole('admin', $redirectUrl);
    }
    
    /**
     * Generate CSRF token
     * 
     * @return string The CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME]) || 
            !isset($_SESSION[CSRF_TOKEN_NAME . '_time']) ||
            (time() - $_SESSION[CSRF_TOKEN_NAME . '_time']) > CSRF_TOKEN_EXPIRY) {
            
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
            $_SESSION[CSRF_TOKEN_NAME . '_time'] = time();
        }
        
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Validate CSRF token
     * 
     * @param string $token The token to validate
     * @return bool True if valid
     */
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION[CSRF_TOKEN_NAME]) || 
            !isset($_SESSION[CSRF_TOKEN_NAME . '_time'])) {
            return false;
        }
        
        // Check if token has expired
        if ((time() - $_SESSION[CSRF_TOKEN_NAME . '_time']) > CSRF_TOKEN_EXPIRY) {
            return false;
        }
        
        // Use hash_equals to prevent timing attacks
        return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    /**
     * Get CSRF token input field HTML
     * 
     * @return string HTML input field
     */
    public static function csrfField() {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . $token . '">';
    }
    
    /**
     * Require valid CSRF token (die if invalid)
     * 
     * @return void
     */
    public static function requireCSRF() {
        $token = post(CSRF_TOKEN_NAME);
        
        if (!self::validateCSRFToken($token)) {
            if (DEV_MODE) {
                die("CSRF token validation failed. This request appears to be invalid.");
            } else {
                die("Invalid request. Please try again.");
            }
        }
    }
    
    /**
     * Register a new user
     * 
     * @param string $username Username
     * @param string $email Email address
     * @param string $password Plain text password
     * @param string $fullName Full name
     * @param string $role User role (admin/end_user)
     * @return array|false User data on success, false on failure
     */
    public static function register($username, $email, $password, $fullName, $role = 'end_user') {
        // Validate inputs
        if (strlen($password) < MIN_PASSWORD_LENGTH) {
            return false;
        }
        
        // Check if username already exists
        $existingUser = Database::fetchOne(
            "SELECT id FROM users WHERE username = ? OR email = ?",
            [$username, $email],
            'ss'
        );
        
        if ($existingUser) {
            return false;
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);
        
        // Insert user
        $sql = "INSERT INTO users (username, email, password_hash, role, full_name) 
                VALUES (?, ?, ?, ?, ?)";
        
        $success = Database::execute($sql, [$username, $email, $passwordHash, $role, $fullName], 'sssss');
        
        if ($success) {
            $userId = Database::lastInsertId();
            return [
                'id' => $userId,
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'full_name' => $fullName
            ];
        }
        
        return false;
    }
    
    /**
     * Update user password
     * 
     * @param int $userId User ID
     * @param string $newPassword New plain text password
     * @return bool True on success
     */
    public static function updatePassword($userId, $newPassword) {
        if (strlen($newPassword) < MIN_PASSWORD_LENGTH) {
            return false;
        }
        
        $passwordHash = password_hash($newPassword, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);
        
        $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
        return Database::execute($sql, [$passwordHash, $userId], 'si');
    }
    
    /**
     * Get user by ID
     * 
     * @param int $userId User ID
     * @return array|null User data or null
     */
    public static function getUserById($userId) {
        $sql = "SELECT id, username, email, role, full_name, created_at 
                FROM users 
                WHERE id = ?";
        
        return Database::fetchOne($sql, [$userId], 'i');
    }
    
    /**
     * Get all users
     * 
     * @return array Array of user data
     */
    public static function getAllUsers() {
        $sql = "SELECT id, username, email, role, full_name, created_at 
                FROM users 
                ORDER BY full_name ASC";
        
        return Database::fetchAll($sql);
    }
    
    /**
     * Check if password needs rehashing (for security updates)
     * 
     * @param string $hash The current password hash
     * @return bool True if rehash needed
     */
    public static function needsRehash($hash) {
        return password_needs_rehash($hash, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);
    }
    
    /**
     * Check if email already exists
     * 
     * @param string $email Email address to check
     * @return bool True if email exists
     */
    public static function checkEmailExists($email) {
        $sql = "SELECT id FROM users WHERE email = ? LIMIT 1";
        $user = Database::fetchOne($sql, [$email], 's');
        return $user !== null;
    }
    
    /**
     * Check if username already exists
     * 
     * @param string $username Username to check
     * @return bool True if username exists
     */
    public static function checkUsernameExists($username) {
        $sql = "SELECT id FROM users WHERE username = ? LIMIT 1";
        $user = Database::fetchOne($sql, [$username], 's');
        return $user !== null;
    }
}

?>
