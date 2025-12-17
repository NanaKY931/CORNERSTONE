<?php
/**
 * CORNERSTONE INVENTORY TRACKER - Account Deletion
 * 
 * Allows users to permanently delete their account
 */

// Start output buffering
ob_start();

require_once 'config.php';
require_once 'db.php';
require_once 'auth.php';

// Require login
Auth::requireLogin();

// Get current user info
$userId = Auth::getUserId();
$username = Auth::getUsername();
$email = $_SESSION['email'] ?? '';

$error = '';
$confirmationRequired = true;

// Handle account deletion
if (is_post()) {
    $password = post('password');
    $confirmText = post('confirm_text');
    $csrfToken = post(CSRF_TOKEN_NAME);
    
    // Validate CSRF token
    if (!Auth::validateCSRFToken($csrfToken)) {
        $error = 'Invalid request. Please try again.';
    }
    // Validate password provided
    elseif (empty($password)) {
        $error = 'Please enter your password to confirm deletion.';
    }
    // Validate confirmation text
    elseif (strtoupper(trim($confirmText)) !== 'DELETE') {
        $error = 'Please type DELETE to confirm account deletion.';
    }
    else {
        // Verify password
        $user = Database::fetchOne(
            "SELECT id, password_hash FROM users WHERE id = ?",
            [$userId],
            'i'
        );
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Incorrect password. Please try again.';
        } else {
            // Password verified - proceed with deletion
            try {
                Database::beginTransaction();
                
                // 1. Delete verification codes for this email
                Database::execute(
                    "DELETE FROM verification_codes WHERE email = ?",
                    [$email],
                    's'
                );
                
                // 2. Update transactions to preserve audit trail
                // Set user_id to NULL for transactions by this user
                Database::execute(
                    "UPDATE transactions SET user_id = NULL WHERE user_id = ?",
                    [$userId],
                    'i'
                );
                
                // 3. Delete the user account
                $deleted = Database::execute(
                    "DELETE FROM users WHERE id = ?",
                    [$userId],
                    'i'
                );
                
                if ($deleted) {
                    Database::commit();
                    
                    // Logout user
                    Auth::logout();
                    
                    // Set success message
                    set_flash('success', 'Your account has been permanently deleted. We\'re sorry to see you go!');
                    
                    // Redirect to landing page
                    redirect('landing.html');
                } else {
                    throw new Exception('Failed to delete account.');
                }
                
            } catch (Exception $e) {
                Database::rollback();
                $error = 'An error occurred while deleting your account. Please try again or contact support.';
                error_log("Account deletion error for user $userId: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Account - Cornerstone Inventory Tracker</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .danger-zone {
            background: #fef2f2;
            border: 2px solid #ef4444;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 2rem 0;
        }
        .danger-zone h3 {
            color: #dc2626;
            margin-top: 0;
        }
        .warning-list {
            background: white;
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            margin: 1rem 0;
        }
        .warning-list ul {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
        }
        .warning-list li {
            margin: 0.5rem 0;
            color: #92400e;
        }
    </style>
</head>
<body class="app-page">
    
    <!-- Header -->
    <header class="app-header">
        <div class="header-content">
            <div class="header-left">
                <a href="dashboard.php" class="app-logo" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 0.75rem;">
                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                        <rect x="2" y="2" width="28" height="28" rx="4" fill="currentColor" opacity="0.2"/>
                        <path d="M16 8L8 14V24H12V18H20V24H24V14L16 8Z" fill="currentColor"/>
                    </svg>
                    <span>Cornerstone</span>
                </a>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <span class="user-name"><?php echo sanitize_output(Auth::getFullName()); ?></span>
                    <span class="user-role"><?php echo sanitize_output(ucfirst(str_replace('_', ' ', Auth::getRole()))); ?></span>
                </div>
                <a href="dashboard.php" class="btn btn-secondary btn-small">Cancel</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="app-main">
        <div class="container" style="max-width: 800px; margin: 0 auto;">
            
            <!-- Page Header -->
            <div class="page-header">
                <h1>⚠️ Delete Account</h1>
                <p>Permanently remove your account and all associated data</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="danger-zone">
                <h3>⚠️ Warning: This Action Cannot Be Undone</h3>
                
                <div class="warning-list">
                    <p><strong>Deleting your account will:</strong></p>
                    <ul>
                        <li>Permanently remove your user account</li>
                        <li>Delete all your personal information</li>
                        <li>Remove your access to the system</li>
                        <li>Preserve transaction history for audit purposes (anonymized)</li>
                    </ul>
                </div>

                <div class="warning-list">
                    <p><strong>You will NOT be able to:</strong></p>
                    <ul>
                        <li>Recover your account after deletion</li>
                        <li>Access any data associated with this account</li>
                        <li>Use the same email address to register again immediately</li>
                    </ul>
                </div>

                <p style="margin-top: 1.5rem; font-weight: 600; color: #dc2626;">
                    If you're sure you want to proceed, please confirm below:
                </p>

                <form method="POST" action="delete_account.php" style="margin-top: 1.5rem;">
                    <?php echo Auth::csrfField(); ?>
                    
                    <div class="form-group">
                        <label for="confirm_text">Type <strong>DELETE</strong> to confirm:</label>
                        <input 
                            type="text" 
                            id="confirm_text" 
                            name="confirm_text" 
                            class="form-control" 
                            placeholder="Type DELETE in capital letters"
                            required
                            autocomplete="off"
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Enter your password to confirm:</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="Your current password"
                            required
                        >
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button type="submit" class="btn btn-danger" style="flex: 1;">
                            Delete My Account Permanently
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary" style="flex: 1; text-align: center;">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

            <div class="card" style="margin-top: 2rem;">
                <div class="card-body">
                    <h3>Need Help?</h3>
                    <p>If you're experiencing issues or have concerns, please contact support before deleting your account.</p>
                    <p>Account Username: <strong><?php echo sanitize_output($username); ?></strong></p>
                    <p>Email: <strong><?php echo sanitize_output($email); ?></strong></p>
                </div>
            </div>

        </div>
    </main>

    <script src="scripts.js"></script>
</body>
</html>
