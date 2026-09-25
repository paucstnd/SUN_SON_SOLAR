    <?php

    session_start();
    require_once 'config.php';

    header('Content-Type: application/json; charset=utf-8');

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Please enter your admin username and password.'
        ]);
        exit();
    }

    $normalizedUsername = strtolower($username);
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
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. Invalid admin username or password.'
        ]);
        exit();
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_logged_in'] = true;

    echo json_encode([
        'success' => true,
        'message' => 'Admin login successful!'
    ]);

    exit();