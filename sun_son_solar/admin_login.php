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
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (login_is_locked($conn, $normalizedUsername, $clientIp)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Too many failed attempts. Please try again in ' . login_lock_seconds_remaining($conn, $normalizedUsername, $clientIp) . ' seconds.'
        ]);
        exit();
    }

    $stmt = $conn->prepare("SELECT id, username, password_hash FROM admins WHERE username = ? AND status = 'active'");
    $stmt->bind_param('s', $normalizedUsername);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $validPassword = $admin && password_verify($password, $admin['password_hash']);

    if (!$validPassword) {
        login_register_failure($conn, $normalizedUsername, $clientIp);
        if (login_is_locked($conn, $normalizedUsername, $clientIp)) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => 'Too many failed attempts. Please try again in ' . login_lock_seconds_remaining($conn, $normalizedUsername, $clientIp) . ' seconds.'
            ]);
            exit();
        }

        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. Invalid admin username or password.'
        ]);
        exit();
    }

    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $rehash = $conn->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
        $rehash->bind_param('si', $newHash, $admin['id']);
        $rehash->execute();
        $rehash->close();
    }

    login_register_success($conn, $normalizedUsername, $clientIp);
    session_regenerate_id(true);
    unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['account_type'], $_SESSION['department']);
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['role'] = 'admin';
    log_activity('Logged in', ['role' => 'admin', 'id' => (int) $admin['id'], 'name' => $admin['username']]);

    echo json_encode([
        'success' => true,
        'message' => 'Admin login successful!',
        'redirect' => 'admin_dashboard.php'
    ]);

    exit();
