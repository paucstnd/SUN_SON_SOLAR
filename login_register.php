<?php

session_start();
require_once 'config.php';
require_once 'security.php';

$csrfToken = $_POST['csrf_token'] ?? '';

if (!csrf_verify($csrfToken)) {
    $_SESSION['error'] = "Your session expired. Please try again.";
    $_SESSION['active_form'] = isset($_POST['register']) ? 'register' : 'login';
    header("Location: index.php");
    exit();
}

if (isset($_POST['register'])) {

    $accountType = $_POST['accountType'] ?? '';
    $firstName = trim($_POST['firstName'] ?? '');
    $middleName = trim($_POST['middleName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $birthdate = $_POST['birthdate'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department = $_POST['department'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $rawPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

   
    $required = [$accountType, $firstName, $lastName, $birthdate, $gender, $email, $phone, $address, $username, $rawPassword, $confirmPassword];
    $hasEmpty = false;
    foreach ($required as $field) {
        if ($field === '') {
            $hasEmpty = true;
            break;
        }
    }

    if ($accountType === 'Employee' && $department === '') {
        $hasEmpty = true;
    }

    if ($hasEmpty) {
        $_SESSION['error'] = "Please fill in all required fields.";
        $_SESSION['active_form'] = 'register';
    } elseif ($rawPassword !== $confirmPassword) {
        $_SESSION['error'] = "Passwords do not match.";
        $_SESSION['active_form'] = 'register';
    } else {
        
    
        $status = ($accountType === 'Employee') ? 'pending' : 'approved';
        $department = ($accountType === 'Employee') ? $department : null;
        $password = password_hash($rawPassword, PASSWORD_DEFAULT);

        $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $check->bind_param("ss", $email, $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $_SESSION['error'] = "That username or email is already registered.";
            $_SESSION['active_form'] = 'register';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO users
                    (account_type, first_name, middle_name, last_name, birthdate, gender, email, phone, department, address, username, password, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "sssssssssssss",
                $accountType,
                $firstName,
                $middleName,
                $lastName,
                $birthdate,
                $gender,
                $email,
                $phone,
                $department,
                $address,
                $username,
                $password,
                $status
            );

            if ($stmt->execute()) {
                $_SESSION['success'] = ($accountType === 'Employee')
                    ? "Your account was submitted. Please allow up to 3 business days for the administrator to review and approve it before you log in."
                    : "Registration successful! You can now log in.";
                $_SESSION['active_form'] = 'login';
            } else {
                $_SESSION['error'] = "Something went wrong. Please try again.";
                $_SESSION['active_form'] = 'register';
            }
            $stmt->close();
        }
        $check->close();
    }

    header("Location: index.php");
    exit();
}


if (isset($_POST['login'])) {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $_SESSION['error'] = "Please fill in your username and password.";
        $_SESSION['active_form'] = 'login';
        header("Location: index.php");
        exit();
    }

    $throttleKey = 'user_' . strtolower($username) . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    if (login_is_locked($throttleKey)) {
        $_SESSION['error'] = "Too many failed attempts. Please try again in " . login_lock_seconds_remaining($throttleKey) . " seconds.";
        $_SESSION['active_form'] = 'login';
        header("Location: index.php");
        exit();
    }

    $stmt = $conn->prepare("SELECT id, username, password, account_type, status, department FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (!password_verify($password, $user['password'])) {
            login_register_failure($throttleKey);
            $_SESSION['error'] = "Incorrect username or password.";
            $_SESSION['active_form'] = 'login';
        } elseif ($user['account_type'] === 'Employee' && $user['status'] !== 'approved') {
            $_SESSION['error'] = "Your employee account is still waiting for administrator approval.";
            $_SESSION['active_form'] = 'login';
        } else {
            login_register_success($throttleKey);
            $stmt->close();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['account_type'] = $user['account_type'];
            $_SESSION['department'] = $user['department'];
            $_SESSION['success'] = "Login successful.";

            if ($user['department'] === 'IT') {
                header("Location: it_dashboard.php");
            } elseif ($user['department'] === 'Technician') {
                header("Location: technician_dashboard.php");
            } elseif ($user['department'] === 'Dispatcher') {
                header("Location: dispatcher_dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit();
        }
    } else {
        login_register_failure($throttleKey);
        $_SESSION['error'] = "Incorrect username or password.";
        $_SESSION['active_form'] = 'login';
    }

    $stmt->close();
    header("Location: index.php");
    exit();
}


header("Location: index.php");
exit();
