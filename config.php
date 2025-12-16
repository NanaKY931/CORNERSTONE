<?php
/**
 * CORNERSTONE INVENTORY TRACKER - Configuration File
 * 
 * This file contains all configuration settings for the application
 * including database connection parameters, security settings, and
 * environment-specific configurations.
 */

// Prevent direct access
if (!defined('CORNERSTONE_APP')) {
    define('CORNERSTONE_APP', true);
}

// ============================================
// DATABASE CONFIGURATION
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cornerstone_inventory');
define('DB_CHARSET', 'utf8mb4');

// ============================================
// SESSION CONFIGURATION
// ============================================

define('SESSION_NAME', 'cornerstone_session');
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('SESSION_REGENERATE_INTERVAL', 300); // Regenerate session ID every 5 minutes

// ============================================
// SECURITY SETTINGS
// ============================================

// CSRF Token Settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_EXPIRY', 3600); // 1 hour

// Password Requirements
define('MIN_PASSWORD_LENGTH', 8);
define('PASSWORD_HASH_ALGO', PASSWORD_BCRYPT);
define('PASSWORD_HASH_COST', 10);

// ============================================
// APPLICATION SETTINGS
// ============================================

define('APP_NAME', 'Cornerstone Inventory Tracker');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'America/Phoenix');

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// ============================================
// ERROR REPORTING
// ============================================

// Development mode - set to false in production
define('DEV_MODE', true);

if (DEV_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// ============================================
// PATH CONFIGURATION
// ============================================

define('BASE_PATH', __DIR__);
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('API_PATH', BASE_PATH . '/api');

// ============================================
// ALERT THRESHOLDS
// ============================================

// Percentage below reorder threshold to trigger predictive alerts
define('PREDICTIVE_ALERT_PERCENTAGE', 20);

// Days of usage history to analyze for predictions
define('USAGE_ANALYSIS_DAYS', 30);

// Variance percentage threshold for waste reports (flag if exceeded)
define('WASTE_VARIANCE_THRESHOLD', 10.0);

// ============================================
// PAGINATION SETTINGS
// ============================================

define('ITEMS_PER_PAGE', 50);
define('TRANSACTIONS_PER_PAGE', 20);

// ============================================
// FILE UPLOAD SETTINGS (for future enhancements)
// ============================================

define('MAX_UPLOAD_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf']);

// ============================================
// EMAIL CONFIGURATION
// ============================================

// Email mode: 'dev' = display code on screen, 'production' = send actual email
define('EMAIL_MODE', 'dev');

// SMTP Settings (for production mode)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', ''); // Set in production
define('SMTP_PASS', ''); // Set in production

// From address
define('FROM_EMAIL', 'noreply@cornerstone.com');
define('FROM_NAME', 'Cornerstone Inventory');

// ============================================
// VERIFICATION CODE SETTINGS
// ============================================

define('VERIFICATION_CODE_LENGTH', 6);
define('VERIFICATION_CODE_EXPIRY', 900); // 15 minutes in seconds


// ============================================
// INITIALIZE SESSION
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    ini_set('session.cookie_samesite', 'Strict');
    
    session_name(SESSION_NAME);
    session_start();
    
    // Session timeout check
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
    
    // Session ID regeneration for security
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > SESSION_REGENERATE_INTERVAL) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Sanitize output to prevent XSS attacks
 * 
 * @param string $data The data to sanitize
 * @return string Sanitized data
 */
function sanitize_output($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency for display
 * 
 * @param float $amount The amount to format
 * @return string Formatted currency string
 */
function format_currency($amount) {
    return '₵' . number_format($amount, 2);
}

/**
 * Format date for display
 * 
 * @param string $date The date to format
 * @param string $format The desired format (default: 'M d, Y')
 * @return string Formatted date string
 */
function format_date($date, $format = 'M d, Y') {
    if (empty($date)) return 'N/A';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : 'Invalid Date';
}

/**
 * Format datetime for display
 * 
 * @param string $datetime The datetime to format
 * @return string Formatted datetime string
 */
function format_datetime($datetime) {
    return format_date($datetime, 'M d, Y g:i A');
}

/**
 * Redirect to another page
 * 
 * @param string $url The URL to redirect to
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Check if request is POST
 * 
 * @return bool True if POST request
 */
function is_post() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Check if request is GET
 * 
 * @return bool True if GET request
 */
function is_get() {
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

/**
 * Get POST data safely
 * 
 * @param string $key The key to retrieve
 * @param mixed $default Default value if key doesn't exist
 * @return mixed The POST value or default
 */
function post($key, $default = null) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

/**
 * Get GET data safely
 * 
 * @param string $key The key to retrieve
 * @param mixed $default Default value if key doesn't exist
 * @return mixed The GET value or default
 */
function get($key, $default = null) {
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

/**
 * Set flash message in session
 * 
 * @param string $type The message type (success, error, warning, info)
 * @param string $message The message text
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 * 
 * @return array|null Flash message array or null
 */
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

?>
