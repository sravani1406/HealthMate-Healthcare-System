<?php
// auth/logout.php

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include functions for logging
if (file_exists('../includes/functions.php')) {
    require_once '../includes/functions.php';
    
    // Log the logout activity if user is logged in
    if (isset($_SESSION['user_id'])) {
        log_activity($_SESSION['user_id'], 'logout', 'User logged out');
    }
}

// Get user type for redirect (before destroying session)
$user_type = $_SESSION['user_type'] ?? 'patient';

// Destroy all session data
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to appropriate page
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/healthhive/');
}

// Redirect to index with logout success message
header('Location: ' . SITE_URL . 'index.php?logout=success');
exit();
?>