<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'quiz_system');

function getDBConnection() {$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);if ($conn->connect_error) {die("Connection failed: " . $conn->connect_error);}return $conn;}
session_start();

function isAdminLoggedIn() {return isset($_SESSION['admin_id']);}
function isStudentLoggedIn() {return isset($_SESSION['student_id']);}
function redirectIfNotAdmin() {if (!isAdminLoggedIn()) {header('Location: admin_login.php');exit();}}
function redirectIfNotStudent() {if (!isStudentLoggedIn()) {header('Location: student_login.php');exit();}}