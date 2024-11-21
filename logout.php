<?php
session_start();

$redirect_url = isset($_SESSION['admin_id']) ? 'admin_login.php' : 'index.php';

if (isset($_SESSION['admin_id'])) {
    error_log('Admin logged out: ' . $_SESSION['admin_id']);
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

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Location: " . $redirect_url);
exit();
?>