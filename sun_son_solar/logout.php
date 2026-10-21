<?php

session_start();
require_once 'security.php';

$wasAdmin = !empty($_SESSION['admin_logged_in']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid logout request.');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
}
session_destroy();

header('Location: ' . ($wasAdmin ? 'admin.php' : 'index.php'));
exit();