<?php
/**
 * Logout Handler
 * Engineering Utility Monitoring System (EUMS)
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Load auth functions
require_once __DIR__ . '/includes/auth_functions.php';

// Logout user
logoutUser();

// Redirect to login page
header('Location: login.php');
exit();
?>