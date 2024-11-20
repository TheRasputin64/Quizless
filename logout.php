<?php
// logout.php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Clear any additional cookies if used
setcookie('remember_me', '', time()-3600, '/');
setcookie('user_preferences', '', time()-3600, '/');

// Log the logout action (optional)
if (function_exists('error_log')) {
    error_log('User logged out: ' . (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 'Unknown'));
}

// Redirect to login page
header('Location: admin_login.php');
exit();
?>