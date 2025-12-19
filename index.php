<?php
/**
 * CORNERSTONE INVENTORY TRACKER - Entry Point
 * 
 * This file serves as the main entry point for the application.
 * Redirects to appropriate page based on login status.
 */

require_once 'config.php';
require_once 'auth.php';

// If user is already logged in, redirect to dashboard
if (Auth::isLoggedIn()) {
    if (Auth::isAdmin()) {
        header('Location: inventory_admin.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

// Otherwise, redirect to landing page
header('Location: landing.html');
exit;
