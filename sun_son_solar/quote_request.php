<?php
session_start();
require_once 'config.php';
require_once 'security.php';
require_once 'functions.php';

function back(string $type, string $msg): void {
    $_SESSION[$type] = $msg;
    header('Location: index.php#quote');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? '')) {
    back('quote_err', 'Your session expired. Please try again.');
}


if (trim($_POST['website'] ?? '') !== '') {
    back('quote_ok', 'Thank you! We will contact you soon.');
}


if (!empty($_SESSION['last_quote']) && time() - (int) $_SESSION['last_quote'] < 30) {
    back('quote_err', 'Please wait a moment before sending another request.');
}

$name = clean_text($_POST['name'] ?? '', 100);
$email = clean_text($_POST['email'] ?? '', 100);
$phone = clean_text($_POST['phone'] ?? '', 20);
$message = clean_text($_POST['message'] ?? '', 500);
$serviceId = (int) ($_POST['service_id'] ?? 0);

if ($name === '' || $email === '' || $phone === '') {
    back('quote_err', 'Please enter your name, email and phone number.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    back('quote_err', 'Please enter a valid email address.');
}
if (!preg_match('/^\d{11}$/', $phone)) {
    back('quote_err', 'Phone number must contain exactly 11 digits.');
}

$serviceParam = null;
if ($serviceId > 0) {
    $chk = $conn->prepare('SELECT id FROM services WHERE id = ? AND is_active = 1');
    $chk->bind_param('i', $serviceId);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
        $serviceParam = $serviceId;
    }
    $chk->close();
}

$stmt = $conn->prepare('INSERT INTO quote_requests (name, email, phone, service_id, message) VALUES (?, ?, ?, ?, ?)');
$stmt->bind_param('sssis', $name, $email, $phone, $serviceParam, $message);
$stmt->execute();
$stmt->close();

$_SESSION['last_quote'] = time();
back('quote_ok', 'Thank you, ' . $name . '! Your quote request was received. Our team will contact you soon.');
