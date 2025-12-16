<?php
/**
 * CORNERSTONE INVENTORY TRACKER - Verification Page
 * 
 * Email verification code entry
 */

// Start output buffering
ob_start();

require_once 'config.php';
require_once 'db.php';
require_once 'auth.php';

// If already logged in, redirect
if (Auth::isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$pendingEmail = $_SESSION['pending_verification_email'] ?? '';

// Handle verification form submission
if (is_post()) {
    $email = post('email');
    $code = strtoupper(trim(post('code'))); // Normalize code
    $csrfToken = post(CSRF_TOKEN_NAME);
    
    // Validate CSRF token
    if (!Auth::validateCSRFToken($csrfToken)) {
        $error = 'Invalid request. Please try again.';
    }
    // Validate fields filled
    elseif (empty($email) || empty($code)) {
        $error = 'Please enter both email and verification code.';
    }
    else {
        // Find verification code
        $sql = "SELECT id, user_data, expires_at, used 
                FROM verification_codes 
                WHERE email = ? AND code = ? 
                LIMIT 1";
        
        $verification = Database::fetchOne($sql, [$email, $code], 'ss');
        
        if (!$verification) {
            $error = 'Invalid verification code or email address.';
        }
        elseif ($verification['used'] == 1) {
            $error = 'This verification code has already been used.';
        }
        elseif (strtotime($verification['expires_at']) < time()) {
            $error = 'This verification code has expired. Please <a href="signup.php">sign up again</a>.';
        }
        else {
            // Code is valid - create user account
            $userData = json_decode($verification['user_data'], true);
            $selectedRole = $userData['role'] ?? 'end_user'; // Get selected role or default
            
            // Create user
            $userCreated = Auth::register(
                $userData['username'],
                $email,
                '', // Password already hashed
                $userData['full_name'],
                $selectedRole // Use selected role
            );
            
            // If register() fails because we're passing empty password, insert directly
            if (!$userCreated) {
                $sql = "INSERT INTO users (username, email, password_hash, role, full_name) 
                        VALUES (?, ?, ?, ?, ?)";
                
                $success = Database::execute($sql, [
                    $userData['username'],
                    $email,
                    $userData['password_hash'],
                    $selectedRole,
                    $userData['full_name']
                ], 'sssss');
                
                if ($success) {
                    $userId = Database::lastInsertId();
                    $userCreated = [
                        'id' => $userId,
                        'username' => $userData['username'],
                        'email' => $email,
                        'role' => $selectedRole,
                        'full_name' => $userData['full_name']
                    ];
                }
            }
            
            if ($userCreated) {
                // Mark code as used
                $updateSql = "UPDATE verification_codes SET used = 1 WHERE id = ?";
                Database::execute($updateSql, [$verification['id']], 'i');
                
                // Auto-login user
                $_SESSION['user_id'] = $userCreated['id'];
                $_SESSION['username'] = $userCreated['username'];
                $_SESSION['email'] = $email;
                $_SESSION['role'] = $userCreated['role']; // Use actual role
                $_SESSION['full_name'] = $userCreated['full_name'];
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();
                
                // Clear pending verification email
                unset($_SESSION['pending_verification_email']);
                
                // Set success message
                set_flash('success', 'Welcome! Your account has been verified successfully.');
                
                // Redirect to dashboard
                redirect('dashboard.php');
            } else {
                $error = 'Failed to create account. Please try again or contact support.';
            }
        }
    }
}
?>

<!-- HTML -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Verify your Cornerstone account">
    <title>Verify Account - Cornerstone Inventory Tracker</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    
    <div class="login-container" style="grid-template-columns: 1fr; max-width: 600px;">
        <!-- Verification Form -->
        <div class="login-form-container">
            <div class="login-form-content">
                <div style="text-align: center; margin-bottom: 2rem;">
                    <div class="brand-logo" style="display: inline-flex; margin-bottom: 1rem;">
                        <svg width="64" height="64" viewBox="0 0 32 32" fill="none">
                            <rect x="2" y="2" width="28" height="28" rx="4" fill="#3b82f6" opacity="0.2"/>
                            <path d="M16 8L8 14V24H12V18H20V24H24V14L16 8Z" fill="#1e3a8a"/>
                        </svg>
                    </div>
                    <h2>Verify Your Account</h2>
                    <p class="login-subtitle">Enter the 6-digit code <?php echo $pendingEmail ? 'sent to your email' : 'you received'; ?></p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="verify.php" class="login-form">
                    <?php echo Auth::csrfField(); ?>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            placeholder="Enter your email"
                            value="<?php echo sanitize_output($pendingEmail); ?>"
                            required
                            <?php echo $pendingEmail ? 'readonly' : 'autofocus'; ?>
                        >
                    </div>

                    <div class="form-group">
                        <label for="code">Verification Code</label>
                        <input 
                            type="text" 
                            id="code" 
                            name="code" 
                            class="form-control" 
                            placeholder="Enter 6-digit code (e.g., A3B7K9)"
                            maxlength="6"
                            style="font-size: 24px; letter-spacing: 8px; text-align: center; font-family: 'Courier New', monospace; text-transform: uppercase;"
                            required
                            <?php echo $pendingEmail ? 'autofocus' : ''; ?>
                        >
                        <small style="color: #6b7280; font-size: 0.875rem; display: block; margin-top: 0.5rem;">
                            Code expires in 15 minutes
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        Verify & Continue
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M7 4L13 10L7 16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </form>

                <div class="login-demo-credentials">
                    <p class="demo-title">Didn't receive a code?</p>
                    <div class="demo-accounts" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <a href="signup.php" class="btn btn-secondary btn-block">Sign Up Again</a>
                        <a href="index.php" style="text-align: center; color: #6b7280; font-size: 0.875rem;">Back to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="scripts.js"></script>
    <script>
        // Auto-format code input to uppercase
        document.getElementById('code').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });
    </script>
</body>
</html>
