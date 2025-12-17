<?php
/**
 * CORNERSTONE INVENTORY TRACKER - Signup Page
 * 
 * User registration with email verification
 */

// Start output buffering
ob_start();

require_once 'config.php';
require_once 'db.php';
require_once 'auth.php';
require_once 'email_helper.php';

// If already logged in, redirect
if (Auth::isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';
$verificationCode = '';

// Handle signup form submission
if (is_post()) {
    $fullName = post('full_name');
    $username = post('username');
    $email = post('email');
    $password = post('password');
    $confirmPassword = post('confirm_password');
    $role = post('role', 'end_user'); // Default to end_user if not selected
    $csrfToken = post(CSRF_TOKEN_NAME);
    
    // Validate CSRF token
    if (!Auth::validateCSRFToken($csrfToken)) {
        $error = 'Invalid request. Please try again.';
    }
    // Validate all fields filled
    elseif (empty($fullName) || empty($username) || empty($email) || empty($password) || empty($confirmPassword) || empty($role)) {
        $error = 'All fields are required.';
    }
    // Validate email format
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    }
    // Validate passwords match
    elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    }
    // Validate password length
    elseif (strlen($password) < MIN_PASSWORD_LENGTH) {
        $error = 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters long.';
    }
    // Check if username already exists
    elseif (Auth::checkUsernameExists($username)) {
        $error = 'Username already taken. Please choose another.';
    }
    // Check if email already exists
    elseif (Auth::checkEmailExists($email)) {
        $error = 'Email already registered. Please <a href="index.php">login</a> instead.';
    }
    else {
        // All validations passed - create verification code
        $code = EmailHelper::generateCode();
        $expiresAt = EmailHelper::getExpirationTime();
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);
        
        // Store user data as JSON
        $userData = json_encode([
            'username' => $username,
            'full_name' => $fullName,
            'password_hash' => $passwordHash,
            'role' => $role  // Include selected role
        ]);
        
        // Insert verification code into database
        $sql = "INSERT INTO verification_codes (email, code, user_data, expires_at) 
                VALUES (?, ?, ?, ?)";
        
        $success = Database::execute($sql, [$email, $code, $userData, $expiresAt], 'ssss');
        
        if ($success) {
            // Send verification email (or display code in dev mode)
            $emailResult = EmailHelper::sendVerificationEmail($email, $code, $username);
            
            if ($emailResult['success']) {
                $success = true;
                $verificationCode = $emailResult['code'] ?? ''; // Only set in dev mode
                
                // Store email in session for verify page
                $_SESSION['pending_verification_email'] = $email;
            } else {
                $error = 'Failed to send verification email. Please try again.';
            }
        } else {
            $error = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign up for Cornerstone Inventory Tracker">
    <title>Sign Up - Cornerstone Inventory Tracker</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    
    <div class="login-container">
        <!-- Left Side - Branding -->
        <div class="login-brand">
            <div class="brand-content">
                <div class="brand-logo">
                    <svg width="48" height="48" viewBox="0 0 32 32" fill="none">
                        <rect x="2" y="2" width="28" height="28" rx="4" fill="currentColor" opacity="0.2"/>
                        <path d="M16 8L8 14V24H12V18H20V24H24V14L16 8Z" fill="currentColor"/>
                    </svg>
                </div>
                <h1>Cornerstone</h1>
                <p class="brand-tagline">Inventory Tracker</p>
                <p class="brand-description">
                    Join thousands of construction professionals managing materials efficiently across multiple sites.
                </p>
                
                <div class="brand-features">
                    <div class="brand-feature">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/>
                            <path d="M7 10L9 12L13 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span>Real-Time Tracking</span>
                    </div>
                    <div class="brand-feature">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/>
                            <path d="M7 10L9 12L13 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span>Predictive Alerts</span>
                    </div>
                    <div class="brand-feature">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/>
                            <path d="M7 10L9 12L13 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span>Comprehensive Reports</span>
                    </div>
                </div>
                
                <a href="landing.html" class="back-link">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M10 4L6 8L10 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    Back to Home
                </a>
            </div>
        </div>

        <!-- Right Side - Signup Form -->
        <div class="login-form-container">
            <div class="login-form-content">
                <?php if ($success && $verificationCode): ?>
                    <!-- Success - Show verification code (dev mode) -->
                    <h2>✅ Registration Successful!</h2>
                    <p class="login-subtitle">Your verification code is:</p>
                    
                    <div class="alert alert-success" style="text-align: center; margin: 20px 0;">
                        <div style="font-size: 32px; font-weight: bold; letter-spacing: 8px; font-family: 'Courier New', monospace; color: #1e3a8a;">
                            <?php echo sanitize_output($verificationCode); ?>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <p><strong>Development Mode:</strong> In production, this code would be sent to your email.</p>
                        <p>This code will expire in <strong>15 minutes</strong>.</p>
                    </div>
                    
                    <a href="verify.php" class="btn btn-primary btn-block">
                        Continue to Verification
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M7 4L13 10L7 16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </a>
                    
                <?php elseif ($success): ?>
                    <!-- Success - Email sent (production mode) -->
                    <h2>✅ Check Your Email</h2>
                    <p class="login-subtitle">We've sent a verification code to your email address.</p>
                    
                    <div class="alert alert-info">
                        <p>Please check your inbox and enter the 6-digit code to complete your registration.</p>
                        <p>The code will expire in <strong>15 minutes</strong>.</p>
                    </div>
                    
                    <a href="verify.php" class="btn btn-primary btn-block">
                        Continue to Verification
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M7 4L13 10L7 16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </a>
                    
                <?php else: ?>
                    <!-- Signup Form -->
                    <h2>Create Your Account</h2>
                    <p class="login-subtitle">Start managing your construction inventory today</p>

                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="signup.php" class="login-form">
                        <?php echo Auth::csrfField(); ?>
                        
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input 
                                type="text" 
                                id="full_name" 
                                name="full_name" 
                                class="form-control" 
                                placeholder="Enter your full name"
                                value="<?php echo isset($fullName) ? sanitize_output($fullName) : ''; ?>"
                                required
                                autofocus
                            >
                        </div>

                        <div class="form-group">
                            <label for="username">Username</label>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                class="form-control" 
                                placeholder="Choose a username"
                                value="<?php echo isset($username) ? sanitize_output($username) : ''; ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-control" 
                                placeholder="Enter your email"
                                value="<?php echo isset($email) ? sanitize_output($email) : ''; ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-control" 
                                placeholder="Create a password (min 8 characters)"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                class="form-control" 
                                placeholder="Re-enter your password"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="role">Account Type</label>
                            <select 
                                id="role" 
                                name="role" 
                                class="form-control" 
                                required
                            >
                                <option value="end_user" <?php echo (!isset($role) || $role === 'end_user') ? 'selected' : ''; ?>>
                                    End User - View inventory, reports, and site data
                                </option>
                                <option value="admin" <?php echo (isset($role) && $role === 'admin') ? 'selected' : ''; ?>>
                                    Administrator - Full access to manage inventory and perform transactions
                                </option>
                            </select>
                            <small style="color: #6b7280; font-size: 0.875rem; display: block; margin-top: 0.5rem;">
                                Choose "End User" if you need to view data only. Choose "Administrator" if you need to update inventory.
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">
                            Create Account
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path d="M7 4L13 10L7 16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </form>

                    <div class="login-demo-credentials">
                        <p class="demo-title">Already have an account?</p>
                        <a href="index.php" class="btn btn-secondary btn-block">Sign In</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="scripts.js"></script>
</body>
</html>
