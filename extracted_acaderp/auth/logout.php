<?php
/**
 * Logout Script
 * Destroys session and redirects to login
 */

require_once __DIR__ . '/../config/config.php';

// Destroy session
session_destroy();

// Clear session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect to login
header('Location: ' . BASE_URL . 'auth/login.php?message=logged_out');
exit;
?>
