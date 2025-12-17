<?php
/**
 * CORNERSTONE INVENTORY TRACKER - Logout Handler
 * 
 * This page handles user logout and session destruction
 */

require_once 'config.php';
require_once 'auth.php';

// Perform logout
Auth::logout();

// Set flash message
set_flash('success', 'You have been successfully logged out.');

// Check for redirect parameter
$redirect_to = get('redirect', 'login');

// Redirect based on parameter
if ($redirect_to === 'landing') {
    redirect('landing.html');
} else {
    redirect('index.php');
}
?>
