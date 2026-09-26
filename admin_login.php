<?php

    session_start();
    require_once 'config.php';
    require_once 'security.php';

    header('Content-Type: application/json; charset=utf-8');

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!csrf_verify($csrfToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Your session expired. Please refresh the page and try again.'
        ]);
        exit();
    }

    if ($username === '' || $password === '') {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Please enter your admin username and password.'
        ]);
        exit();
    }

    $normalizedUsername = strtolower($username);
    $throttleKey = 'admin_' . $normalizedUsername . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    if (login_is_locked($throttleKey)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Too many failed attempts. Please try again in ' . login_lock_seconds_remaining($throttleKey) . ' seconds.'
        ]);
        exit();
    }

    $stmt = $conn->prepare("SELECT id, username, password_hash FROM admins WHERE username = ? AND status = 'active'");
    $stmt->bind_param('s', $normalizedUsername);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $validPassword = $admin && (
        password_verify($password, $admin['password_hash']) ||
        hash_equals($admin['password_hash'], hash('sha256', $password))
    );

    if (!$validPassword) {
        login_register_failure($throttleKey);
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. Invalid admin username or password.'
        ]);
        exit();
    }

    login_register_success($throttleKey);
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_logged_in'] = true;

    echo json_encode([
        'success' => true,
        'message' => 'Admin login successful!'
    ]);

    exit();
