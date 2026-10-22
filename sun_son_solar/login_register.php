<?php

session_start();
require_once 'config.php';
require_once 'security.php';
require_once 'auth.php';

if (isset($_POST['register'])) {
    $registrationValues = [];
    foreach (['firstName', 'middleName', 'lastName', 'birthdate', 'gender', 'email', 'phone', 'address', 'username'] as $field) {
        if (isset($_POST[$field]) && is_string($_POST[$field])) {
            $registrationValues[$field] = $_POST[$field];
        }
    }
    $_SESSION['registration_values'] = $registrationValues;
}

$csrfToken = $_POST['csrf_token'] ?? '';

if (!csrf_verify($csrfToken)) {
    $_SESSION['error'] = "Your session expired. Please try again.";
    $_SESSION['active_form'] = isset($_POST['register']) ? 'register' : 'login';
    header("Location: index.php");
    exit();
}

if (isset($_POST['register'])) {

    $accountType = 'Customer';
    $firstName = trim($_POST['firstName'] ?? '');
    $middleName = trim($_POST['middleName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $birthdate = $_POST['birthdate'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $rawPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

   
    $required = [$firstName, $lastName, $birthdate, $gender, $email, $phone, $address, $username, $rawPassword, $confirmPassword];
    $hasEmpty = false;
    foreach ($required as $field) {
        if ($field === '') {
            $hasEmpty = true;
            break;
        }
    }

    if ($hasEmpty) {
        $_SESSION['error'] = "Please fill in all required fields.";
        $_SESSION['active_form'] = 'register';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address.";
        $_SESSION['active_form'] = 'register';
    } elseif (!preg_match('/^\\d{11}$/', $phone)) {
        $_SESSION['error'] = "Phone number must contain exactly 11 digits.";
        $_SESSION['active_form'] = 'register';
    } elseif (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $_SESSION['error'] = "Please select a valid gender.";
        $_SESSION['active_form'] = 'register';
    } elseif (strlen($rawPassword) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters long.";
        $_SESSION['active_form'] = 'register';
    } elseif ($rawPassword !== $confirmPassword) {
        $_SESSION['error'] = "Passwords do not match.";
        $_SESSION['active_form'] = 'register';
    } else {
        
    
        $status = 'approved';
        $department = null;
        $password = password_hash($rawPassword, PASSWORD_DEFAULT);

        try {
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
                    
                    unset($_SESSION['registration_values']);
                    $_SESSION['success'] = "Registration successful! You can now log in.";
                    $_SESSION['active_form'] = 'login';
                } else {
                    $_SESSION['error'] = "Something went wrong. Please try again.";
                    $_SESSION['active_form'] = 'register';
                }
                $stmt->close();
            }
            $check->close();
        } catch (mysqli_sql_exception $exception) {
            error_log('Customer registration failed: ' . $exception->getMessage());
            $_SESSION['error'] = "Something went wrong. Please try again.";
            $_SESSION['active_form'] = 'register';
        }
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

    $throttleUsername = strtolower($username);
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (login_is_locked($conn, $throttleUsername, $clientIp)) {
        $_SESSION['error'] = "Too many failed attempts. Please try again in " . login_lock_seconds_remaining($conn, $throttleUsername, $clientIp) . " seconds.";
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
            login_register_failure($conn, $throttleUsername, $clientIp);
            if (login_is_locked($conn, $throttleUsername, $clientIp)) {
                $_SESSION['error'] = "Too many failed attempts. Please try again in " . login_lock_seconds_remaining($conn, $throttleUsername, $clientIp) . " seconds.";
            } else {
                $_SESSION['error'] = "Incorrect username or password.";
            }
            $_SESSION['active_form'] = 'login';
        } elseif ($user['status'] !== 'approved') {
            $_SESSION['error'] = ($user['status'] === 'pending')
                ? "Your account is still waiting for administrator approval."
                : "This account has been deactivated. Please contact Sun Son Solar for help.";
            $_SESSION['active_form'] = 'login';
        } else {
            login_register_success($conn, $throttleUsername, $clientIp);
            $stmt->close();

            $role = role_from_user($user['account_type'], $user['department']);
            session_regenerate_id(true);
            unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_logged_in']);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['account_type'] = $user['account_type'];
            $_SESSION['department'] = $user['department'];
            $_SESSION['role'] = $role;
            $_SESSION['success'] = "Login successful.";
            log_activity('Logged in', ['role' => $role, 'id' => (int) $user['id'], 'name' => $user['username']]);

            header("Location: " . role_home($role));
            exit();
        }
    } else {
        login_register_failure($conn, $throttleUsername, $clientIp);
        $_SESSION['error'] = "Incorrect username or password.";
        $_SESSION['active_form'] = 'login';
    }

    $stmt->close();
    header("Location: index.php");
    exit();
}


header("Location: index.php");
exit();
