<?php
/**
 * CORNERSTONE INVENTORY TRACKER - Login Page
 * 
 * This page handles user authentication and redirects based on role
 */

require_once 'config.php';
require_once 'db.php';
require_once 'auth.php';

// If already logged in, redirect based on role
if (Auth::isLoggedIn()) {
    if (Auth::isAdmin()) {
        redirect('inventory_admin.php');
    } else {
        redirect('dashboard.php');
    }
}

$error = '';

// Handle login form submission
if (is_post()) {
    $username = post('username');
    $password = post('password');
    $csrf_token = post(CSRF_TOKEN_NAME);
    
    // Validate CSRF token
    if (!Auth::validateCSRFToken($csrf_token)) {
        $error = 'Invalid request. Please try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Attempt login
        $user = Auth::login($username, $password);
        
        if ($user) {
            // Redirect based on role
            if ($user['role'] === 'admin') {
                redirect('inventory_admin.php');
            } else {
                redirect('dashboard.php');
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

// Get flash message if any
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to Cornerstone Inventory Tracker">
    <title>Login - Cornerstone Inventory Tracker</title>
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
                    Manage construction materials across multiple sites with real-time tracking, 
                    predictive alerts, and comprehensive reporting.
                </p>
                
                <div class="brand-features">
                    <div class="brand-feature">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/>
                            <path d="M7 10L9 12L13 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span>Multi-Site Tracking</span>
                    </div>
                    <div class="brand-feature">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/>
                            <path d="M7 10L9 12L13 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span>Real-Time Updates</span>
                    </div>
                    <div class="brand-feature">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/>
                            <path d="M7 10L9 12L13 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span>Waste Reporting</span>
                    </div>
                </div>
                
                <a href="landing.php" class="back-link">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M10 4L6 8L10 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    Back to Home
                </a>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="login-form-container">
            <div class="login-form-content">
                <h2>Welcome Back</h2>
                <p class="login-subtitle">Sign in to access your inventory dashboard</p>

                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo sanitize_output($flash['type']); ?>">
                        <?php echo sanitize_output($flash['message']); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <?php echo sanitize_output($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="index.php" class="login-form">
                    <?php echo Auth::csrfField(); ?>
                    
                    <div class="form-group">
                        <label for="username">Username or Email</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-control" 
                            placeholder="Enter your username or email"
                            value="<?php echo isset($username) ? sanitize_output($username) : ''; ?>"
                            required
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="Enter your password"
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        Sign In
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M7 4L13 10L7 16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </form>

                <div class="login-demo-credentials">
                    <p class="demo-title">Demo Credentials:</p>
                    <div class="demo-accounts">
                        <div class="demo-account">
                            <strong>Admin:</strong> admin / password123
                        </div>
                        <div class="demo-account">
                            <strong>Manager:</strong> manager / password123
                        </div>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                    <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">Don't have an account?</p>
                    <a href="signup.php" class="btn btn-secondary btn-block">Create Account</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
