<?php
session_start();

$redirect_url = 'index.php'; // Default redirect for students

if (isset($_SESSION['admin_id'])) {
    error_log('Admin logged out: ' . $_SESSION['admin_id']);
    $redirect_url = 'admin_login.php'; // Redirect for admin
} else if (isset($_SESSION['student_id'])) {
    error_log('Student logged out: ' . $_SESSION['student_id']);
}

$_SESSION = array();

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

session_destroy();
setcookie('remember_me', '', time()-3600, '/');
setcookie('user_preferences', '', time()-3600, '/');

header('Location: ' . $redirect_url);
exit();
?>
